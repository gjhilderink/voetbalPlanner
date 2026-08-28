<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Setting;
use App\Services\MatchSyncService;
use App\Services\MemberSyncService;
use App\Services\TeamSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Haalt teams, leden en wedstrijden op uit Sportlink.
 *
 * Draait zonder wachtrij, want op deze hosting is een queue-worker niet
 * gegarandeerd — en een synchronisatie die stilletjes in een wachtrij blijft
 * staan is erger dan er geen te hebben.
 *
 * Per club met een actieve koppeling. Dat is dezelfde weg als de knoppen in de
 * portal nemen; een club zonder koppeling wordt overgeslagen, zodat een
 * demo-club niet meesync't.
 */
class SyncSportlink extends Command
{
    protected $signature = 'sportlink:sync {--geen-mail : Geen statusmail versturen}';

    protected $description = 'Synchroniseert teams, leden en wedstrijden met Sportlink';

    public function handle(): int
    {
        $clubs = Club::where('is_active', true)->orderBy('name')->get()
            ->filter(fn (Club $club) => filter_var(
                Setting::get('mcp_enabled', false, $club->id),
                FILTER_VALIDATE_BOOLEAN,
            ));

        if ($clubs->isEmpty()) {
            $this->warn('Geen enkele club heeft een actieve Sportlink-koppeling; niets te doen.');

            return self::SUCCESS;
        }

        $regels = [];
        $fouten = 0;

        foreach ($clubs as $club) {
            $this->info($club->name);
            $regels[] = $club->name;

            foreach ([
                'Teams'       => TeamSyncService::class,
                'Leden'       => MemberSyncService::class,
                'Wedstrijden' => MatchSyncService::class,
            ] as $label => $service) {
                try {
                    $log = app($service)->forClub($club->id)->sync();

                    $regel = sprintf(
                        '  %-12s %5d  %s%s',
                        $label,
                        (int) $log->records_synced,
                        $log->status,
                        $log->error_message ? '  — ' . $log->error_message : '',
                    );

                    if ($log->status !== 'completed') {
                        $fouten++;
                        $this->error($regel);
                    } else {
                        $this->line($regel);
                    }

                    $regels[] = $regel;
                } catch (\Throwable $e) {
                    // De services vangen hun eigen fouten al af en schrijven een
                    // mislukte SyncLog; dit is voor wat daar doorheen glipt.
                    $fouten++;
                    $regel = sprintf('  %-12s mislukt — %s', $label, $e->getMessage());
                    $this->error($regel);
                    $regels[] = $regel;
                }
            }

            $regels[] = '';
        }

        Setting::set('last_sync_at', now()->toISOString(), 'mcp');

        if (! $this->option('geen-mail')) {
            $this->stuurMail($regels, $fouten);
        }

        // Geen foutcode bij een mislukt onderdeel: de scheduler zou dan elke keer
        // een cron-foutmail sturen bovenop de statusmail die dit al vertelt.
        return self::SUCCESS;
    }

    /** @param array<int, string> $regels */
    private function stuurMail(array $regels, int $fouten): void
    {
        $adres = Setting::get('sync_notification_email', '', null)
            ?: Setting::get('registration_notification_email', '', null);

        if (! $adres) {
            $this->comment('Geen adres ingesteld voor de statusmail; niet verstuurd.');

            return;
        }

        $kop = $fouten === 0
            ? 'Sportlink-synchronisatie voltooid'
            : ($fouten === 1
                ? 'Sportlink-synchronisatie: 1 onderdeel mislukt'
                : "Sportlink-synchronisatie: {$fouten} onderdelen mislukt");

        $tekst = implode("\n", array_merge(
            [$kop, now()->format('d-m-Y H:i'), ''],
            $regels,
            ['De aantallen zijn de bijgewerkte records. Bekijk de details in de portal onder Instellingen.'],
        ));

        try {
            Mail::raw($tekst, fn ($bericht) => $bericht->to($adres)->subject($kop));
            $this->line("Statusmail verstuurd naar {$adres}.");
        } catch (\Throwable $e) {
            // Een mislukte mail mag de synchronisatie niet ongedaan maken of als
            // mislukt laten gelden; het werk is dan al gedaan.
            $this->error('Statusmail niet verstuurd: ' . $e->getMessage());
        }
    }
}
