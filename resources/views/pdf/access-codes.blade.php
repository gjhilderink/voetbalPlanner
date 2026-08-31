<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            margin: 14px 16px;
        }

        .header {
            border-bottom: 2px solid #16a34a;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header h1 { font-size: 15px; font-weight: bold; }
        .header p  { font-size: 10px; color: #555; margin-top: 2px; }

        /* Een tabel en geen flexbox: dompdf kan geen flex, en drie kaartjes
           per rij zijn met kolommen van 33% net zo goed te krijgen. */
        table { width: 100%; border-collapse: collapse; }

        td {
            width: 33.33%;
            padding: 6px;
            text-align: center;
            vertical-align: top;
        }

        .kaart {
            border: 1px dashed #bbb;
            border-radius: 6px;
            padding: 8px 4px;
        }

        .kaart img { width: 130px; height: 130px; }

        .code {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-top: 4px;
        }

        .label { font-size: 9px; color: #555; margin-top: 1px; }
        .voet  { font-size: 8px; color: #888; margin-top: 2px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $titel }}</h1>
        <p>
            {{ $datum }}
            @if ($clubNaam) · {{ $clubNaam }} @endif
            · {{ count($kaarten) }} {{ count($kaarten) === 1 ? 'code' : 'codes' }}
        </p>
    </div>

    <table>
        @foreach (array_chunk($kaarten, 3) as $rij)
            <tr>
                @foreach ($rij as $kaart)
                    <td>
                        <div class="kaart">
                            <img src="{{ $kaart['qr'] }}" alt="{{ $kaart['code'] }}">
                            <div class="code">{{ $kaart['code'] }}</div>
                            @if ($kaart['label'])
                                <div class="label">{{ $kaart['label'] }}</div>
                            @endif
                            @if ($kaart['max'] > 1)
                                <div class="voet">{{ $kaart['max'] }}x te gebruiken</div>
                            @endif
                        </div>
                    </td>
                @endforeach

                {{-- De laatste rij aanvullen, anders rekken de overgebleven
                     kaartjes op tot de volle breedte. --}}
                @for ($i = count($rij); $i < 3; $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>
</html>
