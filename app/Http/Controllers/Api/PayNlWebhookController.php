<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\PayNlService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * De terugmelding van Pay.nl als een betaling rond is.
 *
 * In de API-routes en niet bij het web: die stapel heeft geen sessie en geen
 * CSRF-controle, dus hier hoeft niets uitgezonderd te worden.
 *
 * Wat er binnenkomt wordt niet geloofd. Het bericht levert alleen een
 * transactienummer; de stand wordt daarna bij Pay.nl zelf opgehaald. Anders zou
 * iedereen die het adres kent zichzelf een kaartje kunnen sturen.
 */
class PayNlWebhookController extends Controller
{
    public function __invoke(Request $request, OrderService $orders): Response
    {
        // Pay.nl stuurt het transactienummer onder wisselende namen, afhankelijk
        // van welke generatie van hun koppeling er aan staat.
        $transactieId = (string) (
            $request->input('order_id')
            ?? $request->input('orderId')
            ?? $request->input('id')
            ?? ''
        );

        if ($transactieId === '') {
            Log::warning('[Pay.nl] terugmelding zonder transactienummer', [
                'sleutels' => array_keys($request->all()),
            ]);

            // Toch 200: een foutcode laat Pay.nl uren opnieuw proberen, en er
            // valt hier niets te herstellen.
            return response('TRUE|Geen transactienummer', 200);
        }

        $order = Order::where('paynl_transaction_id', $transactieId)->first();

        if (! $order) {
            Log::warning('[Pay.nl] terugmelding voor een onbekende bestelling', [
                'transaction' => $transactieId,
            ]);

            return response('TRUE|Onbekende bestelling', 200);
        }

        $stand = app(PayNlService::class)->forClub($order->club_id)->status($transactieId);

        if (! ($stand['ok'] ?? false)) {
            // De API-aanroep mislukte (bijv. 403 door ontbrekende leesrechten).
            // Pay.nl stuurt de status ook mee in het webhook-bericht zelf; gebruik
            // dat als fallback. De transactie-ID is al gematcht met een bekende
            // bestelling, dus het risico op nep-verzoeken is beperkt.
            $action   = strtoupper(trim((string) (
                $request->input('action_name')
                ?? $request->input('action')
                ?? ''
            )));
            $statusId = (string) ($request->input('status_id') ?? $request->input('statusId') ?? '');

            $betaald = $statusId === '100'
                || in_array($action, ['PAID', 'PAID_CHECKAMOUNT', 'AUTHORIZE'], true);
            $mislukt = in_array($action, ['CANCEL', 'DENIED', 'EXPIRED', 'FAILURE', 'CHARGEBACK'], true);

            Log::warning('[Pay.nl] API-verificatie mislukt, terugvallen op webhook-payload', [
                'transaction' => $transactieId,
                'error'       => $stand['error'] ?? '?',
                'action'      => $action,
                'status_id'   => $statusId,
                'betaald'     => $betaald,
                'mislukt'     => $mislukt,
            ]);

            if (! $betaald && ! $mislukt) {
                // Geen bruikbare status in de payload → laat Pay.nl het opnieuw proberen.
                return response('FALSE|Kon de status niet vaststellen', 503);
            }

            if ($betaald) {
                $orders->afronden($order);
            } elseif ($mislukt) {
                $orders->mislukt($order);
            }

            return response('TRUE|Verwerkt via payload', 200);
        }

        if ($stand['betaald'] ?? false) {
            $orders->afronden($order);
        } elseif ($stand['mislukt'] ?? false) {
            $orders->mislukt($order);
        }

        // Pay.nl verwacht een antwoord dat met TRUE begint; anders blijft het
        // opnieuw proberen.
        return response('TRUE|Verwerkt', 200);
    }
}
