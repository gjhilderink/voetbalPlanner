<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\MicrosoftConsent;
use App\Services\MicrosoftGraphService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Heen naar Microsoft om toestemming te vragen, en terug met het antwoord.
 *
 * Twee stappen, meer is het niet. De beheerder van de club klikt op koppelen,
 * logt in bij Microsoft en ziet daar welke rechten VoetbalPlanner vraagt. Zegt
 * hij ja, dan komt hij hier terug met het nummer van zijn organisatie en is de
 * koppeling rond - zonder dat er ergens een tenant-ID is overgetypt.
 */
class MicrosoftConsentController extends Controller
{
    /** Naar Microsoft toe. */
    public function start(Request $request): RedirectResponse
    {
        $gebruiker = $request->user();

        abort_unless((bool) $gebruiker?->isAdmin(), 403);

        if (! MicrosoftConsent::beschikbaar()) {
            Notification::make()
                ->title('Koppelen kan nog niet')
                ->body('De app-registratie van VoetbalPlanner staat nog niet in de omgevingsinstellingen. '
                    . 'Vul MS_GRAPH_CLIENT_ID en MS_GRAPH_CLIENT_SECRET in.')
                ->danger()
                ->persistent()
                ->send();

            return back();
        }

        $clubId = $this->clubVan($request);

        return redirect()->away(MicrosoftConsent::url($clubId, $this->terugUrl($request)));
    }

    /**
     * Terug van Microsoft.
     *
     * Microsoft stuurt bij toestemming admin_consent=True met het nummer van de
     * organisatie erbij, en bij weigering een foutcode. Alles wat daarvan
     * afwijkt behandelen we als weigering: de koppeling hoort alleen te staan
     * als er echt toestemming is gegeven.
     */
    public function terug(Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->isAdmin(), 403);

        $state = MicrosoftConsent::leesState($request->query('state'));

        if ($state === null) {
            Notification::make()
                ->title('De koppeling is verlopen')
                ->body('Het duurde te lang, of de link is onderweg veranderd. Probeer het opnieuw.')
                ->danger()
                ->send();

            return redirect(url('/'));
        }

        if ($request->filled('error')) {
            Log::warning('[Graph] toestemming geweigerd', [
                'error'       => (string) $request->query('error'),
                'description' => mb_substr((string) $request->query('error_description'), 0, 300),
            ]);

            Notification::make()
                ->title('Geen toestemming gegeven')
                ->body($this->uitleg((string) $request->query('error')))
                ->danger()
                ->persistent()
                ->send();

            return redirect($state['terug']);
        }

        $tenant = trim((string) $request->query('tenant', ''));
        $gegeven = filter_var($request->query('admin_consent', 'false'), FILTER_VALIDATE_BOOLEAN);

        if (! $gegeven || ! preg_match('/^[0-9a-f-]{36}$/i', $tenant)) {
            Notification::make()
                ->title('De koppeling is niet gelukt')
                ->body('Microsoft stuurde geen organisatie terug. Log in met een account dat beheerder is van de organisatie.')
                ->danger()
                ->persistent()
                ->send();

            return redirect($state['terug']);
        }

        $clubId = $state['club'];

        Setting::set('ms_tenant_id', $tenant, 'microsoft', false, $clubId);
        Setting::set('ms_connected_at', now()->toDateTimeString(), 'microsoft', false, $clubId);
        Setting::set('ms_connected_by', (string) ($request->user()?->email ?? ''), 'microsoft', false, $clubId);

        // Het token in de cache hoort bij de vorige koppeling.
        Cache::forget(MicrosoftGraphService::tokenCacheKey($clubId));

        Log::info('[Graph] club verbonden met Microsoft 365', [
            'club'   => $clubId,
            'tenant' => $tenant,
        ]);

        Notification::make()
            ->title('Verbonden met Microsoft 365')
            ->body('De ruimtes van de club kunnen nu opgehaald worden. Controleer bij Ruimtes of de postbussen doorkomen.')
            ->success()
            ->send();

        return redirect($state['terug']);
    }

    /**
     * Voor welke club dit gebeurt.
     *
     * Een club-beheerder kan alleen zijn eigen club koppelen, wat er ook in de
     * link staat. Alleen een super-admin mag een club kiezen, want die werkt
     * vanuit de portal van een andere club.
     */
    private function clubVan(Request $request): ?string
    {
        $gebruiker = $request->user();

        if (! $gebruiker?->hasRole('super_admin')) {
            return $gebruiker?->club_id;
        }

        $gevraagd = trim((string) $request->query('club', ''));

        return $gevraagd !== '' ? $gevraagd : $gebruiker?->club_id;
    }

    /** Waar de beheerder na afloop weer uitkomt. */
    private function terugUrl(Request $request): string
    {
        $vorige = (string) url()->previous();

        return str_starts_with($vorige, url('/')) ? $vorige : url('/');
    }

    /** De foutcode van Microsoft in gewone taal. */
    private function uitleg(string $code): string
    {
        return match ($code) {
            'access_denied' => 'De toestemming is geweigerd, of je account is geen beheerder van de organisatie. '
                . 'Alleen iemand met beheerrechten in Microsoft 365 kan dit goedkeuren.',
            'admin_consent_required', 'consent_required' => 'Deze toestemming moet door een beheerder van de '
                . 'organisatie gegeven worden.',
            default => 'Microsoft gaf een foutmelding (' . $code . '). Probeer het opnieuw, of kijk in de logboeken.',
        };
    }
}
