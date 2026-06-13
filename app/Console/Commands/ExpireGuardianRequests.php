<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GuardianLink;
use Illuminate\Console\Command;

class ExpireGuardianRequests extends Command
{
    protected $signature   = 'guardian:expire';
    protected $description = 'Markeer verlopen koppelverzoeken als geweigerd.';

    public function handle(): int
    {
        $count = GuardianLink::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'rejected']);

        $this->info("Verlopen verzoeken bijgewerkt: {$count}");

        return self::SUCCESS;
    }
}
