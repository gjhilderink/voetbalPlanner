<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\MemberDTO;
use App\Models\Member;
use App\Models\SyncLog;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

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
            $membersData = $this->mcpService->getMembers();
            $synced = 0;

            // Verzamel per lid álle teams die Sportlink in deze run meldt. Zo
            // kunnen we na afloop verouderde (vorig-seizoen) koppelingen
            // loskoppelen — een lid dat naar een nieuw team ging, mag niet aan
            // het oude team gekoppeld blijven.
            $desiredTeamsByMember = []; // memberId => [teamId => pivotData]
            $membersById          = []; // memberId => Member

            foreach ($membersData as $memberData) {
                $dto = MemberDTO::fromMcpData($memberData);

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

                // Alleen role/is_active meegeven; is_manual bewust NIET, zodat een
                // bestaande handmatige vlag behouden blijft. Nieuwe rijen krijgen
                // de kolom-default (false).
                $member->teams()->syncWithoutDetaching($teamPivots);
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

        return Member::updateOrCreate(
            ['external_id' => $dto->externalId],
            [
                'name'           => $dto->name,
                'email'          => $dto->email ?: $existing?->email,
                'phone'          => $dto->phone ?: $existing?->phone,
                'date_of_birth'  => $dto->dateOfBirth,
                'role'           => $dto->role,
                'is_active'      => $dto->isActive,
                'last_synced_at' => now(),
            ]
        );
    }
}
