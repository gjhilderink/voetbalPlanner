<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\TicketMail;
use App\Models\AccessCode;
use App\Models\AgendaItem;
use App\Models\Club;
use App\Models\Order;
use App\Models\TicketType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Bestellingen aanmaken en afronden.
 *
 * Twee momenten die allebei op zichzelf moeten kloppen. Bij het aanmaken worden
 * de kaarten gereserveerd - vóór de betaling, niet erna, want anders kunnen twee
 * mensen tegelijk voor dezelfde laatste kaart afrekenen en heeft er één betaald
 * voor niets. Bij het afronden worden de codes gemaakt, en dat mag maar één keer
 * gebeuren ook al komen de terugkeerpagina en de webhook allebei langs.
 */
class OrderService
{
    /** Zoveel keer een nieuwe code proberen voordat we het opgeven. */
    private const MAX_POGINGEN = 25;

    /**
     * Reserveer kaarten en leg de bestelling vast.
     *
     * @param  array<string, int>  $aantallen  kaartsoort-id => aantal
     * @return array{ok: bool, order?: Order, fouten?: array<int, string>}
     */
    public function maakBestelling(
        Club $club,
        AgendaItem $item,
        array $aantallen,
        string $naam,
        string $email,
    ): array {
        $gevraagd = array_filter($aantallen, fn ($n) => (int) $n > 0);

        if ($gevraagd === []) {
            return ['ok' => false, 'fouten' => ['Kies eerst hoeveel kaarten je wilt.']];
        }

        return DB::transaction(function () use ($club, $item, $gevraagd, $naam, $email) {
            // Een slot op de kaartsoorten: zolang deze transactie loopt kan
            // niemand anders dezelfde voorraad wegkopen.
            $soorten = TicketType::query()
                ->where('agenda_item_id', $item->id)
                ->whereIn('id', array_keys($gevraagd))
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $fouten = [];
            $regels = [];
            $totaal = 0;

            foreach ($gevraagd as $soortId => $aantal) {
                $aantal = (int) $aantal;
                $soort  = $soorten->get($soortId);

                if (! $soort) {
                    $fouten[] = 'Een van de gekozen kaartsoorten bestaat niet meer.';
                    continue;
                }

                if ($aantal > $soort->max_per_order) {
                    $fouten[] = "Van {$soort->name} kun je er hooguit {$soort->max_per_order} tegelijk kopen.";
                    continue;
                }

                $over = $soort->beschikbaar();
                if ($over !== null && $aantal > $over) {
                    $fouten[] = $over === 0
                        ? "{$soort->name} is net uitverkocht."
                        : "Van {$soort->name} zijn er nog maar {$over} beschikbaar.";
                    continue;
                }

                $regelTotaal = $soort->price_cents * $aantal;
                $totaal     += $regelTotaal;

                $regels[] = [
                    'ticket_type_id'   => $soort->id,
                    'type_name'        => $soort->name,
                    'unit_price_cents' => $soort->price_cents,
                    'quantity'         => $aantal,
                    'line_total_cents' => $regelTotaal,
                ];
            }

            if ($fouten !== []) {
                return ['ok' => false, 'fouten' => $fouten];
            }

            $order = Order::create([
                'club_id'        => $club->id,
                'agenda_item_id' => $item->id,
                'order_number'   => $this->vrijBestelnummer(),
                'public_token'   => Order::nieuwToken(),
                'buyer_name'     => $naam,
                'buyer_email'    => $email,
                'total_cents'    => $totaal,
                'status'         => Order::STATUS_PENDING,
                'expires_at'     => now()->addMinutes(Order::RESERVERING_MINUTEN),
            ]);

            foreach ($regels as $regel) {
                $order->lines()->create($regel);
            }

            return ['ok' => true, 'order' => $order->load('lines')];
        });
    }

    /**
     * Rond een betaalde bestelling af: status, codes, en de mail.
     *
     * Idempotent. De terugkeerpagina en de webhook van Pay.nl komen allebei
     * langs, soms tegelijk, en dan mogen er geen dubbele codes ontstaan. Het
     * slot plus de statuscontrole binnen de transactie regelen dat.
     */
    public function afronden(Order $order): bool
    {
        $nieuw = DB::transaction(function () use ($order) {
            $vers = Order::whereKey($order->id)->lockForUpdate()->first();

            if (! $vers || $vers->status === Order::STATUS_PAID) {
                return false;
            }

            $vers->update([
                'status'     => Order::STATUS_PAID,
                'paid_at'    => now(),
                'expires_at' => null,
            ]);

            $this->maakCodes($vers);

            return true;
        });

        if ($nieuw) {
            $this->stuurTickets($order->refresh());
        }

        return $nieuw;
    }

    /** Zet een bestelling op mislukt. Alleen als hij nog openstond. */
    public function mislukt(Order $order): void
    {
        if ($order->status === Order::STATUS_PENDING) {
            $order->update(['status' => Order::STATUS_FAILED, 'expires_at' => null]);
        }
    }

    /**
     * Eén toegangscode per gekochte kaart.
     *
     * Losse codes en niet één code die vaker mag: zo kun je er drie doorsturen
     * naar wie meegaat en komt iedereen apart binnen.
     */
    private function maakCodes(Order $order): void
    {
        foreach ($order->lines as $regel) {
            for ($i = 0; $i < $regel->quantity; $i++) {
                $this->maakCode($order, $regel->type_name);
            }
        }
    }

    /**
     * Eén code, met opnieuw proberen bij een botsing.
     *
     * De bestaande generator in de portal ontdubbelt alleen in het geheugen.
     * Dat is genoeg als één beheerder op een knop drukt, maar niet als twee
     * kopers tegelijk afrekenen: dan loopt de tweede tegen de unieke sleutel
     * aan en krijgt een ruwe databasefout in plaats van een kaartje.
     */
    private function maakCode(Order $order, string $soortNaam): AccessCode
    {
        $label = trim($order->buyer_name . ' — ' . $soortNaam);

        for ($poging = 0; $poging < self::MAX_POGINGEN; $poging++) {
            try {
                return AccessCode::create([
                    'club_id'        => $order->club_id,
                    'agenda_item_id' => $order->agenda_item_id,
                    'order_id'       => $order->id,
                    'code'           => AccessCode::nieuweCode(),
                    'label'          => $label,
                    'max_uses'       => 1,
                    'used_count'     => 0,
                    'is_active'      => true,
                ]);
            } catch (QueryException $e) {
                if (! $this->isDuplicaat($e)) {
                    throw $e;
                }
            }
        }

        // Na vijfentwintig pogingen op een alfabet van 32 tekens over tien
        // posities is er iets anders aan de hand dan pech.
        throw new \RuntimeException('Kon geen vrije toegangscode maken voor bestelling ' . $order->order_number);
    }

    /** Een bestelnummer dat nog niet bestaat. */
    private function vrijBestelnummer(): string
    {
        for ($poging = 0; $poging < self::MAX_POGINGEN; $poging++) {
            $nummer = Order::nieuwBestelnummer();

            if (! Order::where('order_number', $nummer)->exists()) {
                return $nummer;
            }
        }

        throw new \RuntimeException('Kon geen vrij bestelnummer maken.');
    }

    /** Botst deze fout op een unieke sleutel? */
    private function isDuplicaat(QueryException $e): bool
    {
        // 23000 is de SQL-standaardklasse voor een geschonden beperking; MySQL
        // gebruikt 1062 voor een dubbele sleutel.
        return $e->getCode() === '23000'
            || (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    /**
     * De kaarten naar de koper mailen.
     *
     * Synchroon, want op deze hosting draait geen queue-worker: een mail in de
     * wachtrij zou nooit aankomen.
     *
     * Mislukken is niet fataal. De bestelling is betaald, de codes bestaan en
     * staan op de bevestigingspagina; de beheerder kan de mail opnieuw sturen
     * vanuit de portal. Een uitzondering hier zou de afronding laten stranden
     * ná de betaling, en dát is pas een probleem.
     */
    public function stuurTickets(Order $order): bool
    {
        $order->loadMissing(['lines', 'accessCodes', 'agendaItem', 'club']);

        if ($order->accessCodes->isEmpty()) {
            Log::warning('[Ticketshop] geen kaarten om te mailen', [
                'order' => $order->order_number,
            ]);

            return false;
        }

        try {
            Mail::to($order->buyer_email)->send(new TicketMail($order));

            $order->update(['mail_sent_at' => now()]);

            Log::info('[Ticketshop] kaarten gemaild', [
                'order' => $order->order_number,
                'aan'   => $order->buyer_email,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[Ticketshop] kaarten mailen mislukt', [
                'order' => $order->order_number,
                'aan'   => $order->buyer_email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
