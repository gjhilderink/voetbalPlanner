<!DOCTYPE html>
<html lang="nl">
{{-- Eén kaart per A4, in de kleuren van de club. Zelfde bestand voor de bijlage
     bij de mail (één kaart) en het afdrukvel van de beheerder (er zoveel achter
     elkaar als er verkocht zijn).

     Maten in millimeters en geen pixels: dit blad gaat naar een printer, en een
     QR van 62 mm is bij elke dpi-instelling van dompdf even groot. Opmaak met
     tabellen, want dompdf kent geen flexbox. --}}
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #14283D;
            font-size: 11pt;
        }

        .breek { page-break-after: always; }

        /* ── De band bovenaan: logo, clubnaam, en welk kaartje van hoeveel ── */

        .band { padding: 10mm 16mm 9mm; }
        .bandrij { width: 100%; }
        .bandrij td { vertical-align: middle; }

        .logovak {
            width: 26mm;
            padding-right: 6mm;
        }
        .logovak span {
            display: block;
            background: #fff;
            border-radius: 2mm;
            padding: 2mm;
            text-align: center;
        }
        .logovak img { max-width: 16mm; max-height: 16mm; }

        .clubnaam { font-size: 16pt; font-weight: bold; }
        .kaartsoort { font-size: 10pt; margin-top: 1mm; }

        .bandnummer { text-align: right; white-space: nowrap; }
        .bandnummer .nummer { font-size: 10pt; font-weight: bold; }
        .bandnummer .bestelling { font-size: 8pt; margin-top: 1mm; }

        .streep { height: 2.5mm; }

        /* ── De activiteit ─────────────────────────────────────────────────── */

        .inhoud { padding: 12mm 16mm 0; }

        .titel { font-size: 22pt; font-weight: bold; line-height: 1.2; }
        .wanneer { font-size: 12pt; margin-top: 3mm; }
        .waar { font-size: 11pt; color: #6B7280; margin-top: 1.5mm; }

        /* ── De code: het enige wat bij de ingang telt ─────────────────────── */

        .qrvak {
            margin-top: 10mm;
            border: 0.4mm solid #E5E7EB;
            border-radius: 3mm;
            padding: 8mm 6mm 7mm;
            text-align: center;
        }
        .qrvak img { width: 62mm; height: 62mm; }

        .code {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 20pt;
            font-weight: bold;
            letter-spacing: 2mm;
            /* De letterafstand duwt de tekst naar rechts; met dezelfde inspring
               links blijft de code onder de QR gecentreerd. */
            padding-left: 2mm;
            margin-top: 5mm;
        }
        .codehulp { font-size: 9pt; color: #6B7280; margin-top: 3mm; }

        /* ── Voor wie de kaart is ──────────────────────────────────────────── */

        .gegevens {
            width: 100%;
            margin-top: 9mm;
            border-collapse: collapse;
            font-size: 10pt;
        }
        .gegevens td {
            padding: 3mm 0;
            border-bottom: 0.3mm solid #E5E7EB;
            vertical-align: top;
        }
        .gegevens tr:last-child td { border-bottom: 0; }
        .gegevens .kop { width: 38mm; color: #6B7280; }
        .gegevens .waarde { font-weight: bold; }

        /* ── Kleine letters ────────────────────────────────────────────────── */

        .voet {
            margin-top: 10mm;
            border-top: 0.4mm dashed #C7CDD4;
            padding-top: 4mm;
            font-size: 8pt;
            color: #9CA3AF;
            line-height: 1.5;
        }
    </style>
</head>
<body>
@foreach ($kaarten as $kaart)
    <div class="{{ $loop->last ? '' : 'breek' }}">

        <div class="band" style="background: {{ $kaart['kleur'] }}; color: {{ $kaart['bandTekst'] }}">
            <table class="bandrij">
                <tr>
                    @if ($kaart['logo'])
                        <td class="logovak">
                            <span><img src="{{ $kaart['logo'] }}" alt="{{ $kaart['clubNaam'] }}"></span>
                        </td>
                    @endif
                    <td>
                        <div class="clubnaam">{{ $kaart['clubNaam'] }}</div>
                        <div class="kaartsoort">Toegangsbewijs</div>
                    </td>
                    <td class="bandnummer">
                        @if ($kaart['volgnummer'])
                            <div class="nummer">{{ $kaart['volgnummer'] }}</div>
                        @endif
                        @if ($kaart['bestelnummer'])
                            <div class="bestelling">{{ $kaart['bestelnummer'] }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <div class="streep" style="background: {{ $kaart['accent'] }}"></div>

        <div class="inhoud">
            <div class="titel">{{ $kaart['titel'] }}</div>

            @if ($kaart['datum'] || $kaart['tijd'])
                <div class="wanneer">
                    {{ $kaart['datum'] }}@if ($kaart['datum'] && $kaart['tijd']) · @endif{{ $kaart['tijd'] }}
                </div>
            @endif

            @if ($kaart['locatie'])
                <div class="waar">{{ $kaart['locatie'] }}</div>
            @endif

            <div class="qrvak">
                <img src="{{ $kaart['qr'] }}" alt="QR-code {{ $kaart['code'] }}">
                <div class="code">{{ $kaart['code'] }}</div>
                <div class="codehulp">
                    Laat deze code bij de ingang scannen.
                    @if ($kaart['meermalig'])
                        Deze kaart is {{ $kaart['meermalig'] }} keer te gebruiken.
                    @else
                        Geldig voor één persoon, één keer.
                    @endif
                    Lukt scannen niet, dan kan de code ook worden ingetypt.
                </div>
            </div>

            <table class="gegevens">
                @if ($kaart['houder'])
                    <tr>
                        <td class="kop">Op naam van</td>
                        <td class="waarde">{{ $kaart['houder'] }}</td>
                    </tr>
                @endif
                @if ($kaart['soort'])
                    <tr>
                        <td class="kop">Soort kaart</td>
                        <td class="waarde">{{ $kaart['soort'] }}</td>
                    </tr>
                @endif
                @if ($kaart['bestelnummer'])
                    <tr>
                        <td class="kop">Bestelnummer</td>
                        <td class="waarde">{{ $kaart['bestelnummer'] }}</td>
                    </tr>
                @endif
            </table>

            <div class="voet">
                Deze kaart geeft toegang aan één persoon en werkt maar één keer;
                bewaar hem tot na afloop. Vragen over je bestelling? Neem contact
                op met {{ $kaart['clubNaam'] }}.<br>
                Kaartverkoop via {{ config('app.name') }}.
            </div>
        </div>

    </div>
@endforeach
</body>
</html>
