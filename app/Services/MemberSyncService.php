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

            foreach ($membersData as $memberData) {
                $dto = MemberDTO::fromMcpData($memberData);

                if (empty($dto->externalId)) {
                    Log::warning('Member skipped: no external ID', ['data_keys' => array_keys($memberData)]);
                    continue;
                }

                $member = $this->upsertMember($dto);

                // _teamcode is injected by getMembers() to indicate which team this player belongs to
                $teamcode = $memberData['_teamcode'] ?? null;
                if ($teamcode) {
                    $team = Team::where('external_id', (string) $teamcode)
                        ->when($this->clubId, fn($q) => $q->where('club_id', $this->clubId))
                        ->first();
                    if ($team) {
                        $member->teams()->syncWithoutDetaching([
                            $team->id => [
                                'role'      => $dto->role,
                                'is_active' => $dto->isActive,
                            ]
                        ]);
                    }
                }

                $synced++;
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
