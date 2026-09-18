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

        // Pay.nl stuurt het transactienummer onder wisselende namen. Bij de
        // "SIGNED JSON POST"-methode zit alles genest onder een 'data'-object.
        // Bij oudere POST-varianten staat het plat bovenin.
        $transactieId = (string) (
            $nested['id']
            ?? $nested['orderId']
            ?? $nested['transactionId']
            ?? ($payload['order_id'] ?? null)
            ?? ($payload['orderId'] ?? null)
            ?? ($payload['id'] ?? null)
            ?? ''
        );

        if ($transactieId === '') {
            Log::warning('[Pay.nl] terugmelding zonder transactienummer', [
                'sleutels'   => array_keys($payload),
                'data_keys'  => array_keys($nested),
                'ruw'        => mb_substr($request->getContent(), 0, 500),
            ]);

            return response('TRUE|Geen transactienummer', 200);
        }

        $order = Order::where('paynl_transaction_id', $transactieId)->first();

        if (! $order) {
            Log::warning('[Pay.nl] terugmelding voor een onbekende bestelling', [
                'transaction' => $transactieId,
            ]);

            return response('TRUE|Onbekende bestelling', 200);
        }

        // Haal de status op bij Pay.nl voor verificatie.
        $stand = app(PayNlService::class)->forClub($order->club_id)->status($transactieId);

        if (! ($stand['ok'] ?? false)) {
            // De API-aanroep mislukte (bijv. 403 door ontbrekende leesrechten).
            // Lees dan de status direct uit de payload. Bij SIGNED JSON POST
            // zit die onder data.status.code / data.status.action; bij een
            // platte POST als action / status_id.
            $statusObject = $nested['status'] ?? null;

            if (is_array($statusObject)) {
                $statusCode = (string) ($statusObject['code'] ?? '');
                $actie      = strtoupper((string) ($statusObject['action'] ?? ''));
            } else {
                $statusCode = (string) (
                    $nested['statusId']
                    ?? ($payload['status_id'] ?? ($payload['statusId'] ?? ''))
                );
                $actie = strtoupper((string) (
                    $nested['statusAction']
                    ?? ($payload['action_name'] ?? ($payload['action'] ?? ''))
                ));
            }

            $betaald = $statusCode === '100'
                || in_array($actie, ['PAID', 'PAID_CHECKAMOUNT', 'AUTHORIZE'], true);
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
