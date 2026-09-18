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
        $payload = $request->all();
        $nested  = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        // Alles loggen zodat we de exacte Pay.nl-payload kunnen inzien.
        Log::info('[Pay.nl] webhook ontvangen', [
            'method'       => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'sleutels'     => array_keys($payload),
            'data_sleutels' => array_keys($nested),
            'ruw'          => mb_substr($request->getContent(), 0, 1000),
        ]);

        // De action bepaalt of we iets moeten doen. Pending/processing acties
        // negeren we; alleen betaald of mislukt is actiegevoelig.
        $actieRuw = strtolower(trim((string) ($nested['action'] ?? ($payload['action'] ?? ''))));

        // Pay.nl stuurt de order_id in een intern formaat (bv. "3594196767Xd77cf")
        // dat verschilt van het EX-XXXX dat wij opslaan als paynl_transaction_id.
        // Wij sturen extra1 = order_number mee bij aanmaken; dat komt ongewijzigd
        // terug en is de betrouwbaarste manier om de bestelling te vinden.
        $orderNumber  = (string) ($nested['extra1'] ?? ($payload['extra1'] ?? ''));
        $transactieId = (string) (
            $nested['id']
            ?? $nested['orderId']
            ?? $nested['transactionId']
            ?? ($payload['order_id'] ?? null)
            ?? ($payload['orderId'] ?? null)
            ?? ($payload['id'] ?? null)
            ?? ''
        );

        if ($orderNumber === '' && $transactieId === '') {
            Log::warning('[Pay.nl] terugmelding zonder identificatie', [
                'sleutels'   => array_keys($payload),
                'ruw'        => mb_substr($request->getContent(), 0, 500),
            ]);

            return response('TRUE|Geen identificatie', 200);
        }

        // Zoek de bestelling: eerst via extra1, dan via de interne orderId
        // (paynl_order_id = bv. "3594290559X4cec9" — wat Pay.nl stuurt als order_id),
        // dan via het EX-XXXX transactie-ID als laatste fallback.
        $order = $orderNumber !== ''
            ? Order::where('order_number', $orderNumber)->first()
            : null;

        $order ??= $transactieId !== ''
            ? Order::where('paynl_order_id', $transactieId)->first()
            : null;

        $order ??= $transactieId !== ''
            ? Order::where('paynl_transaction_id', $transactieId)->first()
            : null;

        if (! $order) {
            Log::warning('[Pay.nl] terugmelding voor een onbekende bestelling', [
                'order_number' => $orderNumber,
                'transaction'  => $transactieId,
                'action'       => $actieRuw,
            ]);

            return response('TRUE|Onbekende bestelling', 200);
        }

        // Haal de status op bij Pay.nl voor verificatie.
        $stand = app(PayNlService::class)->forClub($order->club_id)->status($transactieId);

        if (! ($stand['ok'] ?? false)) {
            // De API-aanroep mislukte (bijv. 403 door ontbrekende leesrechten).
            // Lees de status uit de payload. Pay.nl gebruikt lowercase action-namen
            // zoals "paid", "new_ppt", "cancel". Bovenin is $actieRuw al gelezen.
            $statusObject = $nested['status'] ?? null;

            if (is_array($statusObject)) {
                $statusCode = (string) ($statusObject['code'] ?? '');
                $actie      = strtoupper((string) ($statusObject['action'] ?? ''));
            } else {
                $statusCode = (string) (
                    $nested['statusId']
                    ?? ($payload['status_id'] ?? ($payload['statusId'] ?? ''))
                );
                $actie = strtoupper($actieRuw);
            }

            // Pay.nl lowercase action-waarden: paid, paid_checkamount, cancel, expired.
            $betaald = $statusCode === '100'
                || in_array($actie, ['PAID', 'PAID_CHECKAMOUNT', 'AUTHORIZE', '100'], true);
            $mislukt = in_array($actie, ['CANCEL', 'DENIED', 'EXPIRED', 'FAILURE', 'CHARGEBACK'], true);

            Log::warning('[Pay.nl] API-verificatie mislukt, terugvallen op webhook-payload', [
                'transaction' => $transactieId,
                'api_error'   => $stand['error'] ?? '?',
                'status_code' => $statusCode,
                'actie'       => $actie,
                'betaald'     => $betaald,
                'mislukt'     => $mislukt,
            ]);

            if (! $betaald && ! $mislukt) {
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

        return response('TRUE|Verwerkt', 200);
    }
}
