<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
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
            padding: 6px 8px;
            text-align: left;
            font-size: 10px;
        }
        td {
            padding: 5px 8px;
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
            padding: 5px 8px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
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
                <img src="{{ public_path('storage/' . $club->logo_path) }}" alt="{{ $club->name }}">
            @endif
        </div>
        <div class="header-text">
            <h1>{{ $club?->name ?? 'VoetbalPlanner' }}</h1>
            <h2>Rijschema{{ $teamName ? ' — ' . $teamName : '' }}</h2>
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
                <th style="width:150px">Datum & aanvang</th>
                <th style="width:90px">Elftal</th>
                <th>Tegenstander</th>
                <th>Accommodatie</th>
                <th style="width:70px">Verzamelen</th>
                <th>Rijders</th>
                <th>Coach(es)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($matches as $match)
                @if(!$teamName && $match->team?->name !== $currentTeam)
                    @php $currentTeam = $match->team?->name; @endphp
                    <tr>
                        <td colspan="7" class="team-group">{{ $currentTeam }}</td>
                    </tr>
                @endif
                <tr>
                    <td>{{ $match->match_datetime?->locale('nl')->isoFormat('ddd DD-MM-YYYY HH:mm') }}</td>
                    <td>{{ $match->team?->name }}</td>
                    <td>{{ $match->opponent }}</td>
                    <td>{{ $match->location }}</td>
                    <td>{{ $match->arrival_time ? \Carbon\Carbon::parse($match->arrival_time)->format('H:i') : '-' }}</td>
                    <td>{{ $match->drivers->pluck('name')->join(' | ') ?: '-' }}</td>
                    <td>{{ $match->coaches->pluck('name')->join(', ') ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align:center;color:#6b7280;padding:20px">
                        Geen uitwedstrijden met rijders gevonden.
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
