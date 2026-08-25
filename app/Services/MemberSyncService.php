<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\MemberDTO;
use App\Models\Member;
use App\Models\SyncLog;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MemberSyncService
{
    private ?string $clubId = null;

    public function __construct(
        private readonly SportlinkMcpService $mcpService
    ) {}

    public function forClub(?string $clubId): static
    {
        $this->clubId = $clubId;
        $this->mcpService->forClub($clubId);
        return $this;
    }

    public function sync(): SyncLog
    {
        $log = SyncLog::create([
            'club_id'        => $this->clubId,
            'type'           => 'members',
            'status'         => 'started',
            'records_synced' => 0,
            'started_at'     => now(),
        ]);

        try {
            // Met foto's: die worden hieronder als bestand weggeschreven, zodat
            // de app een gewone URL krijgt in plaats van een base64-blob.
            $membersData = $this->mcpService->getMembers(null, true);
            $synced = 0;

            // Verzamel per lid álle teams die Sportlink in deze run meldt. Zo
            // kunnen we na afloop verouderde (vorig-seizoen) koppelingen
            // loskoppelen — een lid dat naar een nieuw team ging, mag niet aan
            // het oude team gekoppeld blijven.
            $desiredTeamsByMember = []; // memberId => [teamId => pivotData]
            $membersById          = []; // memberId => Member

            $loggedKeys = false;
            foreach ($membersData as $memberData) {
                $dto = MemberDTO::fromMcpData($memberData);

                // Eenmalige diagnose: log de beschikbare Sportlink-veldnamen +
                // de opgebouwde naam, zodat we kunnen bepalen welk veld de
                // achternaam bevat (leden tonen soms alleen de voornaam).
                if (! $loggedKeys) {
                    Log::info('[MemberSync] beschikbare lid-velden', [
                        'keys'          => is_array($memberData) ? array_keys($memberData) : gettype($memberData),
                        'resolved_name' => $dto->name,
                    ]);
                    $loggedKeys = true;
                }

                if (empty($dto->externalId)) {
                    Log::warning('Member skipped: no external ID', ['data_keys' => array_keys($memberData)]);
                    continue;
                }

                $member = $this->upsertMember($dto);
                $membersById[$member->id] = $member;

                // _teamcode is injected by getMembers() to indicate which team this player belongs to
                $teamcode = $memberData['_teamcode'] ?? null;
                if ($teamcode) {
                    $team = Team::where('external_id', (string) $teamcode)
                        ->when($this->clubId, fn($q) => $q->where('club_id', $this->clubId))
                        ->first();
                    if ($team) {
                        $desiredTeamsByMember[$member->id][$team->id] = [
                            'role'      => $dto->role,
                            'is_active' => $dto->isActive,
                        ];
                    }
                }

                $synced++;
            }

            // Koppelingen bijwerken: huidige teams (her)koppelen en verouderde
            // koppelingen binnen deze club loskoppelen. We beperken tot teams van
            // deze club zodat koppelingen aan teams van andere clubs ongemoeid
            // blijven. Handmatig (via het admin-panel) gekoppelde teams
            // (is_manual = true) worden NOOIT losgekoppeld. Leden zonder
            // team-info in deze run laten we ongemoeid.
            $clubTeamIds = Team::query()
                ->when($this->clubId, fn($q) => $q->where('club_id', $this->clubId))
                ->pluck('id')
                ->all();

            foreach ($desiredTeamsByMember as $memberId => $teamPivots) {
                $member         = $membersById[$memberId];
                $desiredTeamIds = array_keys($teamPivots);

                $staleTeamIds = $member->teams()
                    ->whereIn('teams.id', $clubTeamIds)
                    ->whereNotIn('teams.id', $desiredTeamIds)
                    ->wherePivot('is_manual', false)
                    ->pluck('teams.id')
                    ->all();

                if (!empty($staleTeamIds)) {
                    $member->teams()->detach($staleTeamIds);
                    Log::info('Member detached from stale team(s)', [
                        'member_id' => $memberId,
                        'team_ids'  => $staleTeamIds,
                    ]);
                }

                // Rol/functie alleen zetten bij een NIEUWE koppeling; op bestaande
                // koppelingen niet overschrijven, zodat een handmatig ingestelde
                // functie (bv. coach van dit team) behouden blijft. is_manual
                // geven we ook niet mee zodat een bestaande vlag behouden blijft;
                // nieuwe rijen krijgen de kolom-default (false).
                $existingTeamIds = $member->teams()->pluck('teams.id')->all();
                $pivotToSync = [];
                foreach ($teamPivots as $tid => $pivot) {
                    if (in_array($tid, $existingTeamIds, true)) {
                        unset($pivot['role']);
                    }
                    $pivotToSync[$tid] = $pivot;
                }
                $member->teams()->syncWithoutDetaching($pivotToSync);
            }

            $log->update([
                'status'         => 'completed',
                'records_synced' => $synced,
                'completed_at'   => now(),
            ]);

            Log::info('Members synced successfully', ['count' => $synced]);
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
            Log::error('Member sync failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $log;
    }

    private function upsertMember(MemberDTO $dto): Member
    {
        $existing = Member::where('external_id', $dto->externalId)->first();

        $velden = [
            'name'           => $dto->name,
            'last_name'      => $dto->lastName ?: $existing?->last_name,
            'email'          => $dto->email ?: $existing?->email,
            'phone'          => $dto->phone ?: $existing?->phone,
            'date_of_birth'  => $dto->dateOfBirth,
            'role'           => $dto->role,
            'is_active'      => $dto->isActive,
            'last_synced_at' => now(),
        ];

        // De pasfoto alleen aanraken als Sportlink er een meestuurt. Zou een run
        // zonder foto's het veld leegmaken, dan verdwijnen alle foto's zodra de
        // koppeling ze een keer niet levert.
        if ($dto->photo !== null) {
            $velden += $this->fotoVelden($dto, $existing);
        }

        return Member::updateOrCreate(['external_id' => $dto->externalId], $velden);
    }

    /**
     * De pasfoto wegschrijven als bestand en het pad teruggeven.
     *
     * Sportlink levert de foto als base64 mee in het antwoord. Die blob elke keer
     * door de API pompen zou een ledenlijst van tien man op een halve megabyte
     * brengen; als bestand is het een gewone URL die het toestel ook nog cachet.
     *
     * De hash zit in de bestandsnaam en niet alleen in de kolom: verandert de
     * foto, dan verandert de URL mee, en laat een toestel dat de oude nog in de
     * cache heeft staan hem niet oneindig staan.
     *
     * @return array<string, string|null>
     */
    private function fotoVelden(MemberDTO $dto, ?Member $bestaand): array
    {
        $base64 = $dto->photo ?? '';

        // Een data-URI mag ook: welke vorm de koppeling kiest is niet aan ons.
        if (str_contains($base64, ',') && str_starts_with($base64, 'data:')) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        $hash = md5($base64);
        if ($bestaand?->sportlink_photo_hash === $hash && $bestaand?->sportlink_photo) {
            return []; // ongewijzigd; niets te schrijven
        }

        $bytes = base64_decode(strtr($base64, ' ', '+'), true);
        if ($bytes === false || $bytes === '') {
            Log::warning('[MemberSync] pasfoto niet te decoderen', ['lid' => $dto->externalId]);
            return [];
        }

        // Een pasfoto van meer dan 2 MB is geen pasfoto meer; dan zit er iets
        // anders in het veld en willen we dat niet klakkeloos publiceren.
        if (strlen($bytes) > 2 * 1024 * 1024) {
            Log::warning('[MemberSync] pasfoto overgeslagen, te groot', [
                'lid'   => $dto->externalId,
                'bytes' => strlen($bytes),
            ]);
            return [];
        }

        $ext = self::extensieVan($bytes);
        if ($ext === null) {
            Log::warning('[MemberSync] pasfoto is geen herkenbare afbeelding', ['lid' => $dto->externalId]);
            return [];
        }

        $veilig = preg_replace('/[^A-Za-z0-9_-]/', '', $dto->externalId) ?: md5($dto->externalId);
        $pad    = 'member_photos/' . $veilig . '_' . substr($hash, 0, 8) . '.' . $ext;

        $disk = Storage::disk('member_photos');
        $disk->put(basename($pad), $bytes);

        // De vorige versie opruimen; anders groeit de map bij elke nieuwe pasfoto.
        if ($bestaand?->sportlink_photo && $bestaand->sportlink_photo !== $pad) {
            $disk->delete(basename($bestaand->sportlink_photo));
        }

        return [
            'sportlink_photo'      => $pad,
            'sportlink_photo_hash' => $hash,
        ];
    }

    /** Het formaat uit de eerste bytes; de koppeling meldt geen content-type. */
    private static function extensieVan(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, 'GIF87a'), str_starts_with($bytes, 'GIF89a') => 'gif',
            str_starts_with($bytes, "\xFF\xD8\xFF")                              => 'jpg',
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n")                         => 'png',
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP'   => 'webp',
            default                                                              => null,
        };
    }
}
