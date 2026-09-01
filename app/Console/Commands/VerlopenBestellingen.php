<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Open bestellingen die niet betaald zijn geven hun kaarten terug.
 *
 * Zonder dit blijft voorraad hangen aan iemand die de betaalpagina heeft
 * weggeklikt: de kaarten tellen als vergeven zolang de bestelling openstaat, en
 * dan is een activiteit uitverkocht terwijl er niemand betaald heeft.
 *
 * Alleen een status omzetten, niets verwijderen. Wat er gebeurd is blijft
 * zichtbaar in de portal, en dat is precies wat je wilt weten als iemand belt
 * dat zijn betaling niet is aangekomen.
 */
class VerlopenBestellingen extends Command
{
    protected $signature = 'ticketshop:verlopen';

    protected $description = 'Zet onbetaalde bestellingen op verlopen en geeft hun kaarten terug';

    public function handle(): int
    {
        $aantal = Order::query()
            ->openEnVerlopen()
            ->update([
                'status'     => Order::STATUS_EXPIRED,
                'expires_at' => null,
                'updated_at' => now(),
            ]);

        if ($aantal > 0) {
            $this->info("{$aantal} bestelling(en) verlopen.");
        }

        return self::SUCCESS;
    }
}
