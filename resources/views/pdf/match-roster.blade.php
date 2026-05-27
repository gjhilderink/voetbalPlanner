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
            margin: 18px 24px;
        }

        .header {
            display: table;
            width: 100%;
            margin-bottom: 18px;
            border-bottom: 2px solid #16a34a;
            padding-bottom: 12px;
        }
        .header-logo {
            display: table-cell;
            width: 70px;
            vertical-align: middle;
        }
        .header-logo img {
            max-width: 60px;
            max-height: 60px;
        }
        .header-text {
            display: table-cell;
            vertical-align: middle;
            padding-left: 12px;
        }
        .header-text h1 {
            font-size: 18px;
            font-weight: bold;
            color: #15803d;
        }
        .header-text h2 {
            font-size: 13px;
            color: #4b5563;
            margin-top: 2px;
        }
        .header-meta {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            font-size: 10px;
            color: #6b7280;
            white-space: nowrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        th {
            background-color: #16a34a;
            color: #ffffff;
            font-weight: bold;
            padding: 6px 6px;
            text-align: left;
            font-size: 9px;
        }
        td {
            padding: 4px 6px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        tr:nth-child(even) td {
            background-color: #f9fafb;
        }

        .team-group {
            background-color: #dcfce7 !important;
            font-weight: bold;
            color: #15803d;
            font-size: 11px;
            padding: 5px 6px;
        }

        .badge {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-primary  { background: #dbeafe; color: #1d4ed8; }
        .badge-success  { background: #dcfce7; color: #15803d; }
        .badge-danger   { background: #fee2e2; color: #b91c1c; }
        .badge-warning  { background: #fef9c3; color: #a16207; }

        .footer {
            position: fixed;
            bottom: 0;
            left: 24px;
            right: 24px;
            text-align: center;
            font-size: 9px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="header-logo">
            @if($club?->logo_path)
                <img src="{{ public_path('logos/' . basename($club->logo_path)) }}" alt="{{ $club->name }}">
            @endif
        </div>
        <div class="header-text">
            <h1>{{ $club?->name ?? 'VoetbalPlanner' }}</h1>
            <h2>Wedstrijd rooster{{ $teamName ? ' — ' . $teamName : '' }}</h2>
        </div>
        <div class="header-meta">
            Afgedrukt op {{ now()->locale('nl')->isoFormat('ddd D MMM YYYY') }}<br>
            @if($periodLabel){{ $periodLabel }}@endif
        </div>
    </div>

    @php $currentTeam = null; @endphp

    <table>
        <thead>
            <tr>
                <th style="width:130px">Datum</th>
                <th style="width:45px">Aanvang</th>
                <th style="width:80px">Elftal</th>
                <th style="width:35px">T/U</th>
                <th>Tegenstander</th>
                <th>Accommodatie</th>
                <th style="width:70px">Status</th>
                <th style="width:45px">Uitslag</th>
                <th>Coach(es)</th>
                <th>Rijders</th>
                <th>Kleedkamer</th>
                <th>Fruitheld</th>
            </tr>
        </thead>
        <tbody>
            @forelse($matches as $match)
                @if(!$teamName && $match->team?->name !== $currentTeam)
                    @php $currentTeam = $match->team?->name; @endphp
                    <tr>
                        <td colspan="12" class="team-group">{{ $currentTeam }}</td>
                    </tr>
                @endif
                @php
                    $statusLabel = match($match->status) {
                        'scheduled' => 'Gepland',
                        'played'    => 'Gespeeld',
                        'cancelled' => 'Geannuleerd',
                        'postponed' => 'Uitgesteld',
                        default     => $match->status,
                    };
                    $statusClass = match($match->status) {
                        'scheduled' => 'badge-primary',
                        'played'    => 'badge-success',
                        'cancelled' => 'badge-danger',
                        'postponed' => 'badge-warning',
                        default     => '',
                    };
                    $score = $match->score_home !== null
                        ? $match->score_home . '-' . $match->score_away
                        : '-';
                @endphp
                <tr>
                    <td>{{ $match->match_datetime?->locale('nl')->isoFormat('ddd DD-MM-YYYY') }}</td>
                    <td>{{ $match->arrival_time ? \Carbon\Carbon::parse($match->arrival_time)->format('H:i') : '-' }}</td>
                    <td>{{ $match->team?->name }}</td>
                    <td>{{ $match->is_home ? 'Thuis' : 'Uit' }}</td>
                    <td>{{ $match->opponent }}</td>
                    <td>{{ $match->location }}</td>
                    <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                    <td>{{ $score }}</td>
                    <td>{{ $match->coaches->pluck('name')->join(', ') ?: '-' }}</td>
                    <td>{{ $match->drivers->pluck('name')->join(' | ') ?: '-' }}</td>
                    <td>{{ $match->cleaners->pluck('name')->join(', ') ?: '-' }}</td>
                    <td>{{ $match->fruitHero?->name ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" style="text-align:center;color:#6b7280;padding:20px">
                        Geen wedstrijden gevonden.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ $club?->name }} &bull; Gegenereerd via VoetbalPlanner
    </div>

</body>
</html>
