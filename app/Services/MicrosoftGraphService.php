<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RoomReservation;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * De agenda van Microsoft 365, voor de ruimtes van de club.
 *
 * De enige plek in de applicatie die iets van Microsoft Graph af weet.
 * Verandert er iets aan hun API, dan verandert er hier iets en verder nergens -
 * dezelfde afspraak als bij PayNlService en SportlinkMcpService.
 *
 * Geen inlogscherm en geen redirect: dit is een koppeling tussen twee servers,
 * met de client credentials van de app-registratie van de club. Het token wordt
 * gecachet zoals FcmService dat doet.
 *
 * Fouten komen als array terug en niet als uitzondering. Een agenda die er even
 * uit ligt hoort een reservering niet tegen te houden; hij komt dan later alsnog
 * in Outlook te staan.
 */
class MicrosoftGraphService
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    /**
     * De tijdzone waarin we schrijven en lezen.
     *
     * Wij bewaren Nederlandse wandkloktijd - net als agenda_items en matches -
     * en rekenen nergens naar UTC om. Door Graph expliciet deze zone mee te
     * geven blijft 19:00 hier ook 19:00 in Outlook. De Windows-naam en niet
     * 'Europe/Amsterdam': dat is wat Exchange verwacht.
     */
    public const TIJDZONE = 'W. Europe Standard Time';

    private ?string $clubId = null;
    private string $tenantId = '';
    private string $clientId = '';
    private string $clientSecret = '';

    public function __construct()
    {
        $this->bootSettings();
    }

    public function forClub(?string $clubId): static
    {
        $this->clubId = $clubId;
        $this->bootSettings();

        return $this;
    }

    private function bootSettings(): void
    {
        $id = $this->clubId;

        // Trimmen: deze drie worden geplakt uit het Azure-portaal, en een
        // meegekomen spatie levert een 401 op waar niets aan te zien is.
        $this->tenantId     = trim((string) (Setting::get('ms_tenant_id', '', $id) ?? ''));
        $this->clientId     = trim((string) (Setting::get('ms_client_id', '', $id) ?? ''));
        $this->clientSecret = trim((string) (Setting::get('ms_client_secret', '', $id) ?? ''));
    }

    public function isConfigured(): bool
    {
        return $this->tenantId !== '' && $this->clientId !== '' && $this->clientSecret !== '';
    }

    /** De cachesleutel van het token; ook gebruikt om hem te vergeten na een wijziging. */
    public static function tokenCacheKey(?string $clubId): string
    {
        return 'ms_graph_token_' . ($clubId ?? 'globaal');
    }

    /**
     * De ruimtes die Microsoft kent.
     *
     * Meteen de controle op de inrichting: komt hier niets uit, dan zijn de
     * ruimtes geen resource-postbussen en klopt de opzet niet.
     *
     * @return array{ok: bool, rooms?: array<int, array<string, string>>, error?: string}
     */
    public function ruimtes(): array
    {
        $antwoord = $this->vraag('GET', '/places/microsoft.graph.room');

        if (! $antwoord['ok']) {
            return $antwoord;
        }

        $ruimtes = collect($antwoord['data']['value'] ?? [])
            ->map(fn (array $r) => [
                'id'       => (string) ($r['id'] ?? ''),
                'naam'     => (string) ($r['displayName'] ?? ''),
                'email'    => (string) ($r['emailAddress'] ?? ''),
                'capacity' => (string) ($r['capacity'] ?? ''),
            ])
            ->filter(fn (array $r) => $r['email'] !== '')
            ->values()
            ->all();

        return ['ok' => true, 'rooms' => $ruimtes];
    }

    /**
     * Zet een reservering in de agenda van de ruimte.
     *
     * @return array{ok: bool, eventId?: string, icalUid?: string, error?: string}
     */
    public function maakAfspraak(RoomReservation $reservering, string $roomEmail): array
    {
        $antwoord = $this->vraag(
            'POST',
            '/users/' . rawurlencode($roomEmail) . '/events',
            $this->afspraakBody($reservering),
        );

        if (! $antwoord['ok']) {
            return $antwoord;
        }

        return [
            'ok'       => true,
            'eventId'  => (string) ($antwoord['data']['id'] ?? ''),
            'icalUid'  => (string) ($antwoord['data']['iCalUId'] ?? ''),
        ];
    }

    /** @return array{ok: bool, error?: string} */
    public function wijzigAfspraak(RoomReservation $reservering, string $roomEmail): array
    {
        if (! $reservering->ms_event_id) {
            return ['ok' => false, 'error' => 'Deze reservering staat nog niet in Microsoft.'];
        }

        return $this->vraag(
            'PATCH',
            '/users/' . rawurlencode($roomEmail) . '/events/' . rawurlencode($reservering->ms_event_id),
            $this->afspraakBody($reservering),
        );
    }

    /**
     * Haal de afspraak weg.
     *
     * Een 404 telt als gelukt: hij is dan al weg, en dat is precies wat we
     * wilden bereiken.
     *
     * @return array{ok: bool, error?: string}
     */
    public function verwijderAfspraak(string $eventId, string $roomEmail): array
    {
        $antwoord = $this->vraag(
            'DELETE',
            '/users/' . rawurlencode($roomEmail) . '/events/' . rawurlencode($eventId),
        );

        if (! $antwoord['ok'] && ($antwoord['status'] ?? 0) === 404) {
            return ['ok' => true];
        }

        return $antwoord;
    }

    /**
     * Wat er in de agenda van een ruimte staat tussen twee momenten.
     *
     * calendarView en niet /events: alleen deze klapt een terugkerende serie uit
     * in losse afspraken. Anders staat een wekelijkse vergadering er één keer.
     *
     * @return array{ok: bool, events?: array<int, array<string, mixed>>, error?: string}
     */
    public function agenda(string $roomEmail, \DateTimeInterface $van, \DateTimeInterface $tot): array
    {
        // Mét tijdzone-offset in de parameters. De documentatie zegt met zoveel
        // woorden dat de Prefer-header hier níét op werkt: zonder offset leest
        // Microsoft ze als UTC en schuift het venster twee uur.
        $query = http_build_query([
            'startDateTime' => $van->format('c'),
            'endDateTime'   => $tot->format('c'),
            '$select'       => 'id,iCalUId,subject,start,end,isCancelled,sensitivity,organizer',
            '$top'          => 100,
        ]);

        $antwoord = $this->vraag(
            'GET',
            '/users/' . rawurlencode($roomEmail) . '/calendar/calendarView?' . $query,
        );

        if (! $antwoord['ok']) {
            return $antwoord;
        }

        return ['ok' => true, 'events' => $antwoord['data']['value'] ?? []];
    }

    /**
     * De inhoud van een afspraak.
     *
     * Bij een privé-reservering gaat de titel er niet in en de aanvrager al
     * helemaal niet. Alles wat wij in de agenda van een ruimte zetten is
     * zichtbaar voor iedereen in de club met agendarechten - juist voor de
     * mensen die de rol Ruimteplanning níét hebben. Dit is de plek waar privé
     * het makkelijkst weglekt.
     *
     * @return array<string, mixed>
     */
    private function afspraakBody(RoomReservation $reservering): array
    {
        $prive = (bool) $reservering->is_private;

        return [
            'subject' => $prive
                ? RoomReservation::PRIVE_TITEL
                : (string) $reservering->title,
            'sensitivity' => $prive ? 'private' : 'normal',
            'body' => [
                'contentType' => 'text',
                'content' => $prive
                    ? 'Vastgelegd via VoetbalPlanner.'
                    : trim(sprintf(
                        "%s\n\nVastgelegd via VoetbalPlanner door %s.",
                        (string) ($reservering->notes ?? ''),
                        (string) ($reservering->requester_name ?? 'een beheerder'),
                    )),
            ],
            'start' => [
                'dateTime' => $reservering->starts_at?->format('Y-m-d\TH:i:s'),
                'timeZone' => self::TIJDZONE,
            ],
            'end' => [
                'dateTime' => $reservering->ends_at?->format('Y-m-d\TH:i:s'),
                'timeZone' => self::TIJDZONE,
            ],
        ];
    }

    /**
     * Eén aanroep naar Graph.
     *
     * @param  array<string, mixed>|null  $body
     * @return array{ok: bool, data?: array<mixed>, status?: int, error?: string}
     */
    private function vraag(string $methode, string $pad, ?array $body = null): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => 'De Microsoft-koppeling is nog niet ingesteld.'];
        }

        $token = $this->token();

        if (! $token) {
            return ['ok' => false, 'error' => 'Inloggen bij Microsoft is niet gelukt. Controleer de gegevens van de app-registratie.'];
        }

        try {
            $verzoek = Http::withToken($token)
                ->acceptJson()
                ->withHeaders(['Prefer' => 'outlook.timezone="' . self::TIJDZONE . '"'])
                ->timeout(15)
                // Niet werpen bij een 4xx: het antwoord van Microsoft bevat de
                // reden, en die willen we lezen.
                ->retry(2, 500, null, false);

            $antwoord = match ($methode) {
                'POST'   => $verzoek->post(self::GRAPH . $pad, $body ?? []),
                'PATCH'  => $verzoek->patch(self::GRAPH . $pad, $body ?? []),
                'DELETE' => $verzoek->delete(self::GRAPH . $pad),
                default  => $verzoek->get(self::GRAPH . $pad),
            };

            if (! $antwoord->successful()) {
                $reden = $this->foutTekst($antwoord->json() ?? [], $antwoord->status());

                Log::error('[Graph] verzoek mislukt', [
                    'methode' => $methode,
                    'pad'     => $pad,
                    'status'  => $antwoord->status(),
                    'body'    => mb_substr($antwoord->body(), 0, 500),
                ]);

                return ['ok' => false, 'status' => $antwoord->status(), 'error' => $reden];
            }

            return ['ok' => true, 'data' => $antwoord->json() ?? [], 'status' => $antwoord->status()];
        } catch (\Throwable $e) {
            Log::error('[Graph] verzoek gooide een fout', [
                'methode' => $methode,
                'pad'     => $pad,
                'error'   => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'Microsoft is even niet bereikbaar.'];
        }
    }

    /**
     * Een toegangstoken, ruim voor het verloopt vernieuwd.
     *
     * Per club gecachet: tenant en app-registratie verschillen per vereniging.
     */
    private function token(): ?string
    {
        $sleutel = self::tokenCacheKey($this->clubId);
        $gecacht = Cache::get($sleutel);

        if (is_string($gecacht) && $gecacht !== '') {
            return $gecacht;
        }

        try {
            $antwoord = Http::asForm()->timeout(15)->post(
                'https://login.microsoftonline.com/' . rawurlencode($this->tenantId) . '/oauth2/v2.0/token',
                [
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type'    => 'client_credentials',
                    'scope'         => 'https://graph.microsoft.com/.default',
                ],
            );
        } catch (\Throwable $e) {
            Log::error('[Graph] token ophalen gooide een fout', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $antwoord->successful()) {
            Log::error('[Graph] token ophalen mislukt', [
                'status' => $antwoord->status(),
                'body'   => mb_substr($antwoord->body(), 0, 300),
            ]);

            return null;
        }

        $token = $antwoord->json('access_token');
        $duur  = (int) ($antwoord->json('expires_in') ?? 3600);

        if (! is_string($token) || $token === '') {
            return null;
        }

        // Vijf minuten marge, zodat een aanroep nooit met een net verlopen token
        // vertrekt.
        Cache::put($sleutel, $token, max(60, $duur - 300));

        return $token;
    }

    /**
     * De reden uit het antwoord van Microsoft, in gewone taal.
     *
     * @param  array<mixed>  $data
     */
    private function foutTekst(array $data, int $status): string
    {
        $reden = $data['error']['message'] ?? null;

        if (is_string($reden) && trim($reden) !== '') {
            return trim(mb_substr($reden, 0, 250));
        }

        return match (true) {
            $status === 401 => 'Microsoft weigert de app-registratie. Controleer tenant, client-ID en geheim.',
            $status === 403 => 'De app-registratie mag hier niet bij. Controleer de rechten en of de beheerder toestemming heeft gegeven.',
            $status === 404 => 'Microsoft kent deze ruimte niet. Klopt het adres van de postbus?',
            default         => 'Microsoft antwoordde met HTTP ' . $status . '.',
        };
    }
}
