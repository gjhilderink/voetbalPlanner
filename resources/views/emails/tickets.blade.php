<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Je kaarten</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .wrapper { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: {{ $primaryColor }}; padding: 28px 32px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; }
        .body { padding: 32px; color: #333; font-size: 15px; line-height: 1.6; }
        .kaart { border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px; margin: 10px 0; }
        .kaart .code { font-family: "Courier New", monospace; font-size: 20px; font-weight: bold; letter-spacing: 2px; }
        .kaart .wie { color: #6b7280; font-size: 13px; }
        .regels { width: 100%; border-collapse: collapse; margin: 18px 0; font-size: 14px; }
        .regels td { padding: 6px 0; border-bottom: 1px solid #eee; }
        .regels td.rechts { text-align: right; white-space: nowrap; }
        .regels tr.totaal td { border-bottom: 0; font-weight: bold; padding-top: 10px; }
        .button { display: inline-block; margin: 18px 0 4px; padding: 13px 28px; background: {{ $primaryColor }}; color: #fff; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: bold; }
        .note { font-size: 13px; color: #888; margin-top: 22px; }
        .footer { padding: 16px 32px; background: #f9f9f9; font-size: 12px; color: #aaa; text-align: center; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>{{ $headerText }}</h1>
    </div>

    <div class="body">
        <p>Hallo {{ $order->buyer_name }},</p>

        <p>
            Bedankt voor je bestelling. Hieronder staan je kaarten voor
            <strong>{{ $order->agendaItem?->title }}</strong>@if ($order->agendaItem?->starts_at),
            {{ $order->agendaItem->starts_at->translatedFormat('l j F Y') }}
            @unless ($order->agendaItem->is_all_day)
                om {{ $order->agendaItem->starts_at->format('H:i') }}
            @endunless
            @endif.
        </p>

        @if ($order->agendaItem?->location)
            <p>Waar: {{ $order->agendaItem->location }}</p>
        @endif

        <table class="regels">
            @foreach ($order->lines as $regel)
                <tr>
                    <td>{{ $regel->quantity }}× {{ $regel->type_name }}</td>
                    <td class="rechts">{{ \App\Support\Geld::euro($regel->line_total_cents) }}</td>
                </tr>
            @endforeach
            <tr class="totaal">
                <td>Totaal betaald</td>
                <td class="rechts">{{ \App\Support\Geld::euro($order->total_cents) }}</td>
            </tr>
        </table>

        <p><strong>Je kaarten</strong></p>

        @foreach ($order->accessCodes as $code)
            <div class="kaart">
                <div class="code">{{ $code->code }}</div>
                @if ($code->label)
                    <div class="wie">{{ $code->label }}</div>
                @endif
            </div>
        @endforeach

        <p>
            De QR-codes zitten als afbeelding bij deze mail. Laat er bij de
            ingang één per persoon scannen; elke code werkt één keer. Lukt het
            scannen niet, dan kan de code hierboven ook worden overgetypt.
        </p>

        <p style="text-align:center">
            <a class="button" href="{{ $bestelUrl }}">Je kaarten online bekijken</a>
        </p>

        <p class="note">
            Bestelnummer {{ $order->order_number }}. Bewaar deze mail tot na
            afloop. Vragen over je bestelling? Neem contact op met
            {{ $headerText }}.
        </p>
    </div>

    <div class="footer">
        @if ($footerText)
            {{ $footerText }}
        @else
            &copy; {{ date('Y') }} {{ $headerText }} &mdash; kaartverkoop via {{ config('app.name') }}
        @endif
    </div>
</div>
</body>
</html>
