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
    public function __construct(
        private readonly SportlinkMcpService $mcpService
    ) {}

    public function sync(): SyncLog
    {
        $log = SyncLog::create([
            'type' => 'members',
            'status' => 'started',
            'records_synced' => 0,
            'started_at' => now(),
        ]);

        try {
            $membersData = $this->mcpService->getMembers();
            $synced = 0;

            foreach ($membersData as $memberData) {
                $dto = MemberDTO::fromMcpData($memberData);
                $member = $this->upsertMember($dto);

                if (isset($memberData['team_id'])) {
                    $team = Team::where('external_id', $memberData['team_id'])->first();
                    if ($team) {
                        $member->teams()->syncWithoutDetaching([
                            $team->id => [
                                'role' => $dto->role,
                                'is_active' => $dto->isActive,
                            ]
                        ]);
                    }
                }

                $synced++;
            }

            $log->update([
                'status' => 'completed',
                'records_synced' => $synced,
                'completed_at' => now(),
            ]);

            Log::info('Members synced successfully', ['count' => $synced]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            Log::error('Member sync failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $log;
    }

    private function upsertMember(MemberDTO $dto): Member
    {
        return Member::updateOrCreate(
            ['external_id' => $dto->externalId],
            [
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'date_of_birth' => $dto->dateOfBirth,
                'role' => $dto->role,
                'is_active' => $dto->isActive,
                'last_synced_at' => now(),
            ]
        );
    }
}
