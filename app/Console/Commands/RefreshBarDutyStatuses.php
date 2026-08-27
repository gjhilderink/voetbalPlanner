<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BarDuty;
use Illuminate\Console\Command;

/**
 * Zet de status van bardiensten gelijk aan de werkelijke bezetting.
 *
 * Nodig als eenmalige reparatie: de status werd bepaald uit een relatie die op
 * dat moment al geladen was, dus met de bezetting van vóór de toewijzing. Een
 * dienst die vol werd gezet bleef daardoor "open" staan. Dat is verholpen, maar
 * de rijen die er al stonden veranderen niet vanzelf mee.
 *
 * Blijft daarna bruikbaar: na een import of een handmatige ingreep in de
 * database is dit de manier om alles weer kloppend te krijgen.
 */
class RefreshBarDutyStatuses extends Command
{
    protected $signature = 'bardiensten:status-bijwerken {--dry-run : Alleen tonen wat er zou veranderen}';

    protected $description = 'Zet de status van alle bardiensten gelijk aan het aantal gevulde plekken';

    public function handle(): int
    {
        $droog = (bool) $this->option('dry-run');
        $gewijzigd = 0;
        $bekeken = 0;

        BarDuty::query()
            ->with(['members', 'users'])
            ->chunkById(200, function ($diensten) use (&$gewijzigd, &$bekeken, $droog): void {
                foreach ($diensten as $dienst) {
                    $bekeken++;

                    // 'vervuld' is een handmatige bevestiging; die overschrijven
                    // we niet, net zomin als refreshStatus() dat doet.
                    if ($dienst->status === 'vervuld') {
                        continue;
                    }

                    $hoort = $dienst->filledCount() >= $dienst->requiredCount()
                        ? 'bevestigd'
                        : 'open';

                    if ($dienst->status === $hoort) {
                        continue;
                    }

                    $this->line(sprintf(
                        '  %s %s — %d/%d plekken: %s → %s',
                        $dienst->date?->format('d-m-Y') ?? '??-??-????',
                        $dienst->shiftLabel(),
                        $dienst->filledCount(),
                        $dienst->requiredCount(),
                        $dienst->status ?: 'leeg',
                        $hoort,
                    ));

                    if (! $droog) {
                        $dienst->update(['status' => $hoort]);
                    }

                    $gewijzigd++;
                }
            });

        $this->newLine();
        $this->info(sprintf(
            '%d bardiensten bekeken, %d %s.',
            $bekeken,
            $gewijzigd,
            $droog ? 'zouden worden bijgewerkt' : 'bijgewerkt',
        ));

        if ($droog && $gewijzigd > 0) {
            $this->comment('Draai zonder --dry-run om dit door te voeren.');
        }

        return self::SUCCESS;
    }
}
