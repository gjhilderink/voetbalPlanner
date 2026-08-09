<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\MatchDTO;
use App\Models\FootballMatch;
use App\Models\SyncLog;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MatchSyncService
{
    private ?string $clubId = null;

    /** Cache binnen 1 sync-run: DOCUMENT-id => lokale logo-URL (voorkomt dubbele downloads). */
    private array $logoCache = [];

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
            'type'           => 'matches',
            'status'         => 'started',
            'records_synced' => 0,
            'started_at'     => now(),
        ]);

        try {
            $schedule = $this->mcpService->getSchedule();
            $results  = $this->mcpService->getResults();
            $synced   = 0;

            $loggedKeys = false;
            foreach ([['schedule', $schedule], ['results', $results]] as [$type, $matchesData]) {
                foreach ($matchesData as $matchData) {
                    $dto  = MatchDTO::fromMcpData($matchData, $type);

                    // Eenmalige diagnose: log de beschikbare Sportlink-veldnamen zodat
                    // we de exacte logo-veldnaam kunnen bepalen als 'opponent_logo' leeg blijft.
                    if (! $loggedKeys) {
                        Log::info('[MatchSync] beschikbare match-velden', [
                            'keys'          => is_array($matchData) ? array_keys($matchData) : gettype($matchData),
                            'opponent_logo' => $dto->opponentLogo,
                        ]);
                        $loggedKeys = true;
                    }
                    $team = Team::where('external_id', $dto->teamExternalId)
                        ->when($this->clubId, fn($q) => $q->where('club_id', $this->clubId))
                        ->first();

                    if (!$team) {
                        Log::warning('Team not found for match', ['team_external_id' => $dto->teamExternalId]);
                        continue;
                    }

                    $this->upsertMatch($dto, $team->id);
                    $synced++;
                }
            }

            $log->update([
                'status'         => 'completed',
                'records_synced' => $synced,
                'completed_at'   => now(),
            ]);

            Log::info('Matches synced successfully', ['count' => $synced]);
        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
            Log::error('Match sync failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $log;
    }

    private function upsertMatch(MatchDTO $dto, string $teamId): FootballMatch
    {
        $attrs = [
            'team_id'        => $teamId,
            'opponent'       => $dto->opponent,
            'match_datetime' => $dto->matchDatetime,
            'location'       => $dto->location,
            'is_home'        => $dto->isHome,
            'status'         => $dto->status,
            'score_home'     => $dto->scoreHome,
            'score_away'     => $dto->scoreAway,
            'arrival_time'   => $dto->arrivalTime,
            'last_synced_at' => now(),
        ];

        // Sportlink-logo-URL's verlopen (expires+sig). Download het logo en sla
        // het permanent op; bewaar de lokale URL. Bij een mislukte download laten
        // we een eerder opgeslagen logo staan (niet overschrijven met null).
        $localLogo = $dto->opponentLogo ? $this->cacheLogo($dto->opponentLogo) : null;
        if ($localLogo !== null) {
            $attrs['opponent_logo'] = $localLogo;
        }

        return FootballMatch::updateOrCreate(
            ['external_id' => $dto->externalId],
            $attrs,
        );
    }

    /**
     * Downloadt een (verlopende) Sportlink-logo-URL en slaat 'm permanent op de
     * public disk op. Dedup op de stabiele DOCUMENT-id in het pad, zodat elk
     * uniek logo maar één keer wordt gedownload. Retourneert de lokale asset-URL
     * of null bij mislukking.
     */
    private function cacheLogo(string $url): ?string
    {
        // Stabiele sleutel = laatste padsegment vóór de query (Sportlink DOCUMENT-id).
        $path  = parse_url($url, PHP_URL_PATH) ?: '';
        $docId = preg_replace('/[^A-Za-z0-9_-]/', '', basename($path));
        if ($docId === '') {
            $docId = md5($url);
        }

        if (isset($this->logoCache[$docId])) {
            return $this->logoCache[$docId];
        }

        $disk = Storage::disk('public');

        // Al eerder opgeslagen? Hergebruik (ongeacht extensie).
        foreach (['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'] as $ext) {
            $existing = "match_logos/{$docId}.{$ext}";
            if ($disk->exists($existing)) {
                return $this->logoCache[$docId] = asset('storage/' . $existing);
            }
        }

        try {
            $resp = Http::timeout(15)->get($url);
            if (! $resp->successful() || $resp->body() === '') {
                return null;
            }
            $ext  = $this->extensionFromContentType($resp->header('Content-Type'));
            $stored = "match_logos/{$docId}.{$ext}";
            $disk->put($stored, $resp->body());
            return $this->logoCache[$docId] = asset('storage/' . $stored);
        } catch (\Throwable $e) {
            Log::warning('[MatchSync] logo download mislukt', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function extensionFromContentType(?string $contentType): string
    {
        return match (true) {
            $contentType === null                             => 'png',
            str_contains($contentType, 'image/jpeg')          => 'jpg',
            str_contains($contentType, 'image/jpg')           => 'jpg',
            str_contains($contentType, 'image/webp')          => 'webp',
            str_contains($contentType, 'image/gif')           => 'gif',
            str_contains($contentType, 'image/svg')           => 'svg',
            default                                           => 'png',
        };
    }
}
