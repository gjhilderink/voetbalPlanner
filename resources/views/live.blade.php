{{--
    Publieke livepagina. Zelfstandig HTML-bestand met inline stijl, zoals de
    andere publieke views hier — geen layout en geen Vite-afhankelijkheid, zodat
    de pagina het ook doet als de build ontbreekt.

    Donkere kop met de stand, daaronder drie tabbladen. De tabbladen wisselen in
    de browser zonder nieuwe aanvraag; het pollen ververst alleen de inhoud.
--}}
@php
    $accent = $club?->primary_color ?: '#e11d2e';
    $thuis  = ($state['isHome'] ?? 'false') === 'true';
    // De thuisploeg staat links, zoals op elk scorebord. Bij een uitwedstrijd is
    // dat dus de tegenstander.
    $linksNaam   = $thuis ? ($state['teamName'] ?: 'Ons team') : ($state['opponent'] ?: 'Tegenstander');
    $rechtsNaam  = $thuis ? ($state['opponent'] ?: 'Tegenstander') : ($state['teamName'] ?: 'Ons team');
    $linksLogo   = $thuis ? ($state['teamLogo'] ?? '') : ($state['opponentLogo'] ?? '');
    $rechtsLogo  = $thuis ? ($state['opponentLogo'] ?? '') : ($state['teamLogo'] ?? '');
    $linksScore  = $thuis ? $state['scoreOwn'] : $state['scoreOpponent'];
    $rechtsScore = $thuis ? $state['scoreOpponent'] : $state['scoreOwn'];
    $lineup      = $state['lineup'] ?? ['starters' => [], 'bench' => []];
    $heeftOpstelling = $lineup['starters'] || $lineup['bench'];
    $stats       = $state['stats'] ?? [];

    // Dezelfde indeling als het script onderaan. Bewust hier én daar: de pagina
    // moet zonder JavaScript compleet zijn, en met JavaScript mag er bij een
    // verversing niets verspringen.
    $icoonVoor = fn (string $type) => match ($type) {
        'goal'         => 'i-bal',
        'card'         => 'i-kaart',
        'substitution' => 'i-wissel',
        'halftime', 'fulltime' => 'i-fluit',
        default        => 'i-klok',
    };
    $isHoogtepunt = fn (string $type) => in_array($type, ['goal', 'card', 'fulltime'], true);

    $statRijen = [
        ['Doelpunten',   $stats['goalsOwn']  ?? '0', $stats['goalsOpponent']  ?? '0'],
        ['Gele kaarten', $stats['yellowOwn'] ?? '0', $stats['yellowOpponent'] ?? '0'],
        ['Rode kaarten', $stats['redOwn']    ?? '0', $stats['redOpponent']    ?? '0'],
        ['Schoten op doel', $stats['shotsOwn'] ?? '0', $stats['shotsOpponent'] ?? '0'],
    ];
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0d0f14">
    <title>{{ $linksNaam }} - {{ $rechtsNaam }} · live</title>
    <style>
        :root {
            --accent: {{ $accent }};
            --ink: #0d0f14;
            --ink-2: #181b22;
            --paper: #f1f2f5;
            --line: #e4e6eb;
            --muted: #6b7280;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: var(--paper); color: #16181d;
            -webkit-font-smoothing: antialiased;
        }
        .wrap { max-width: 560px; margin: 0 auto; background: var(--paper); min-height: 100vh; }

        /* ── Kop ─────────────────────────────────────────────────────────── */
        .hero {
            position: relative; overflow: hidden;
            background: var(--ink);
            padding: 18px 16px 26px;
            text-align: center;
        }
        /* Twee zachte gloeden links en rechts; puur decoratief. */
        .hero::before, .hero::after {
            content: ''; position: absolute; top: 50%; width: 320px; height: 320px;
            transform: translateY(-50%); pointer-events: none;
            background: radial-gradient(circle, color-mix(in srgb, var(--accent) 55%, transparent) 0%, transparent 68%);
        }
        .hero::before { left: -150px; }
        .hero::after  { right: -150px; }
        .hero > * { position: relative; z-index: 1; }

        .badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--accent); color: #fff;
            font-size: 13px; font-weight: 700; letter-spacing: .08em;
            padding: 8px 18px; border-radius: 999px;
        }
        .badge.ended { background: rgba(255,255,255,.14); }
        .dot { width: 9px; height: 9px; border-radius: 50%; background: #fff; }
        .badge.live .dot { animation: pulse 1.4s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: .2 } }

        .board {
            display: grid; grid-template-columns: 1fr auto 1fr;
            align-items: center; gap: 10px; margin-top: 26px;
        }
        .side { min-width: 0; }
        .crest {
            width: 78px; height: 78px; margin: 0 auto 14px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 14px;
        }
        .crest img { max-width: 100%; max-height: 100%; display: block; }
        /* Valt terug op een schild met een bal als er geen embleem is. */
        .crest.blank { background: var(--accent); border-radius: 10px 10px 46% 46%; color: #fff; }
        .crest.blank svg { width: 38px; height: 38px; }
        .side .naam {
            color: #fff; font-size: 16px; font-weight: 600; line-height: 1.25;
            overflow-wrap: anywhere;
        }
        .score {
            color: #fff; font-size: 52px; font-weight: 800; letter-spacing: 1px;
            white-space: nowrap; line-height: 1;
        }
        .klok { color: var(--accent); font-size: 15px; font-weight: 700; margin-top: 16px; }

        /* ── Tabbladen ───────────────────────────────────────────────────── */
        .tabs { display: flex; background: var(--ink-2); }
        .tab {
            flex: 1; appearance: none; border: 0; background: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 18px 6px 15px; font-size: 15px; font-family: inherit;
            color: rgba(255,255,255,.55); border-bottom: 3px solid transparent;
        }
        .tab svg { width: 19px; height: 19px; flex: none; }
        .tab[aria-selected="true"] { color: #fff; font-weight: 700; border-bottom-color: var(--accent); }

        /* ── Tijdlijn ────────────────────────────────────────────────────── */
        .panel { padding: 18px 16px 8px; }
        .panel[hidden] { display: none; }
        .tijdlijn { position: relative; }
        /* De verbindingslijn loopt door het midden van de rondjes. */
        .tijdlijn::before {
            content: ''; position: absolute; left: 35px; top: 8px; bottom: 8px;
            width: 2px; background: var(--line);
        }
        .ev { position: relative; display: flex; align-items: stretch; gap: 14px; margin-bottom: 14px; }
        .ev .bol {
            position: relative; z-index: 1; flex: none;
            width: 54px; height: 54px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            background: var(--ink); color: #fff;
            align-self: center;
        }
        .ev.hoogtepunt .bol { background: var(--accent); }
        .ev .bol svg { width: 24px; height: 24px; }
        .ev .kaart {
            flex: 1; min-width: 0; background: #fff; border-radius: 14px;
            padding: 14px 16px; box-shadow: 0 1px 3px rgba(13,15,20,.07);
            display: flex; align-items: center; gap: 12px;
        }
        .ev .tekst { flex: 1; min-width: 0; }
        .ev .min { color: var(--accent); font-size: 15px; font-weight: 700; }
        .ev.tegenstander .min { color: var(--muted); }
        .ev .label { font-size: 16px; line-height: 1.35; overflow-wrap: anywhere; }
        .ev .extra { flex: none; display: flex; align-items: center; gap: 6px; color: var(--ink); }
        .ev .extra svg { width: 22px; height: 22px; }
        .kaartje { width: 15px; height: 21px; border-radius: 3px; display: block; }
        .kaartje.geel { background: #facc15; }
        .kaartje.rood { background: #e11d2e; }
        .pijl-in  { color: #16a34a; }
        .pijl-uit { color: #e11d2e; }

        .leeg {
            background: #fff; border-radius: 14px; padding: 26px 18px;
            text-align: center; color: var(--muted); font-size: 15px;
        }

        /* ── Opstelling & statistieken ───────────────────────────────────── */
        .kop { font-size: 13px; font-weight: 700; letter-spacing: .06em;
               text-transform: uppercase; color: var(--muted); margin: 4px 2px 10px; }
        .lijst { background: #fff; border-radius: 14px; box-shadow: 0 1px 3px rgba(13,15,20,.07);
                 margin-bottom: 20px; overflow: hidden; }
        .lijst div { padding: 13px 16px; border-bottom: 1px solid #f0f1f4; font-size: 16px; }
        .lijst div:last-child { border-bottom: 0; }
        .stat {
            background: #fff; border-radius: 14px; box-shadow: 0 1px 3px rgba(13,15,20,.07);
            display: grid; grid-template-columns: 1fr auto 1fr; align-items: center;
            padding: 14px 16px; margin-bottom: 10px; gap: 10px;
        }
        .stat .n { font-size: 22px; font-weight: 800; }
        .stat .n.rechts { text-align: right; }
        .stat .wat { font-size: 13px; color: var(--muted); text-align: center; }

        /* ── Voettekst ───────────────────────────────────────────────────── */
        .voet {
            margin: 22px 16px 30px; background: var(--ink); color: #fff;
            border-radius: 16px; padding: 16px 18px;
            display: flex; align-items: flex-start; gap: 12px;
        }
        .voet svg { width: 24px; height: 24px; flex: none; color: var(--accent); margin-top: 2px; }
        .voet b { display: block; font-size: 16px; margin-bottom: 3px; }
        .voet span { font-size: 14px; color: rgba(255,255,255,.62); }
    </style>
</head>
<body>
<div class="wrap">

    {{-- Iconen één keer, en hergebruiken via <use>. --}}
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <defs>
            <g id="i-bal" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round">
                <circle cx="12" cy="12" r="8.6"/>
                <path d="M12 7.4l3.5 2.5-1.35 4.1h-4.3L8.5 9.9z" fill="currentColor" stroke="none"/>
                {{-- Spaken naar de rand: zonder deze lijnen leest de vijfhoek als een stip. --}}
                <path d="M12 7.4V3.4M15.5 9.9l3.8-1.2M14.15 14l2.4 3.2M9.85 14l-2.4 3.2M8.5 9.9L4.7 8.7"/>
            </g>
            <g id="i-fluit" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round">
                <path d="M11.5 8.5h8.2a1 1 0 011 1v1.8a5.2 5.2 0 11-9.2-3.3z"/>
                <path d="M11.6 8.6L7 6.2a1.4 1.4 0 00-2 1.3v1.4"/>
                <circle cx="14.8" cy="14.2" r="1.5"/>
            </g>
            <g id="i-wissel" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <path d="M4 9h11l-3-3M20 15H9l3 3"/>
            </g>
            <g id="i-kaart" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="8" y="4" width="8" height="16" rx="1.5"/>
            </g>
            <g id="i-klok" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <circle cx="12" cy="13" r="7"/><path d="M12 10v3.5M9.5 3h5"/>
            </g>
            <g id="i-lijst" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>
            </g>
            <g id="i-mensen" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <circle cx="9" cy="8" r="3"/><path d="M3.5 19c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/>
                <path d="M16 5.5a3 3 0 010 5.6M17.5 19c0-2-.7-3.6-1.8-4.6"/>
            </g>
            <g id="i-staaf" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <path d="M6 19v-6M12 19V5M18 19v-9"/>
            </g>
            <g id="i-zender" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <circle cx="12" cy="12" r="2"/>
                <path d="M8.5 8.5a5 5 0 000 7M15.5 8.5a5 5 0 010 7M5.5 5.5a9 9 0 000 13M18.5 5.5a9 9 0 010 13"/>
            </g>
            <g id="i-pijl-op" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M12 19V6M7 11l5-5 5 5"/>
            </g>
            <g id="i-pijl-neer" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M12 5v13M7 13l5 5 5-5"/>
            </g>
        </defs>
    </svg>

    <div class="hero">
        <span class="badge {{ $state['hasEnded'] === 'true' ? 'ended' : 'live' }}" id="badge">
            <span class="dot"></span><span id="periode">{{ $state['hasEnded'] === 'true' ? 'AFGELOPEN' : 'LIVE' }}</span>
        </span>

        <div class="board">
            <div class="side">
                @if ($linksLogo)
                    <div class="crest"><img src="{{ $linksLogo }}" alt=""></div>
                @else
                    <div class="crest blank"><svg><use href="#i-bal"/></svg></div>
                @endif
                <div class="naam">{{ $linksNaam }}</div>
            </div>
            {{-- Score en klok in de middenkolom, zodat de minuut op één lijn met
                 de teamnamen uitkomt in plaats van eronder. --}}
            <div>
                <div class="score" id="score">{{ $linksScore }} - {{ $rechtsScore }}</div>
                <div class="klok" id="klok">{{ $state['minute'] }}'</div>
            </div>
            <div class="side">
                @if ($rechtsLogo)
                    <div class="crest"><img src="{{ $rechtsLogo }}" alt=""></div>
                @else
                    <div class="crest blank"><svg><use href="#i-bal"/></svg></div>
                @endif
                <div class="naam">{{ $rechtsNaam }}</div>
            </div>
        </div>

    </div>

    <div class="tabs" role="tablist">
        <button class="tab" role="tab" aria-selected="true" data-panel="tijdlijn">
            <svg><use href="#i-lijst"/></svg>Tijdlijn
        </button>
        <button class="tab" role="tab" aria-selected="false" data-panel="opstelling">
            <svg><use href="#i-mensen"/></svg>Opstelling
        </button>
        <button class="tab" role="tab" aria-selected="false" data-panel="statistieken">
            <svg><use href="#i-staaf"/></svg>Statistieken
        </button>
    </div>

    <div class="panel" id="panel-tijdlijn">
        <div class="tijdlijn" id="tijdlijn">
            @forelse ($state['events'] as $ev)
                <div class="ev {{ $isHoogtepunt($ev['type']) ? 'hoogtepunt' : '' }} {{ $ev['side'] === 'opponent' ? 'tegenstander' : '' }}">
                    <div class="bol"><svg><use href="#{{ $icoonVoor($ev['type']) }}"/></svg></div>
                    <div class="kaart">
                        <div class="tekst">
                            <div class="min">{{ $ev['minute'] }}</div>
                            <div class="label">{{ $ev['label'] }}</div>
                        </div>
                        @if ($ev['type'] === 'goal')
                            <div class="extra"><svg><use href="#i-bal"/></svg></div>
                        @elseif ($ev['type'] === 'card')
                            <div class="extra"><span class="kaartje {{ ($ev['cardType'] ?? '') === 'red' ? 'rood' : 'geel' }}"></span></div>
                        @elseif ($ev['type'] === 'substitution')
                            <div class="extra">
                                <svg class="pijl-in"><use href="#i-pijl-op"/></svg>
                                <svg class="pijl-uit"><use href="#i-pijl-neer"/></svg>
                            </div>
                        @elseif (in_array($ev['type'], ['halftime', 'fulltime'], true))
                            <div class="extra"><svg><use href="#i-fluit"/></svg></div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="leeg">Nog niets gebeurd.</div>
            @endforelse
        </div>
    </div>

    <div class="panel" id="panel-opstelling" hidden>
        @if ($heeftOpstelling)
            @if ($lineup['starters'])
                <div class="kop">Basis</div>
                <div class="lijst">@foreach ($lineup['starters'] as $naam)<div>{{ $naam }}</div>@endforeach</div>
            @endif
            @if ($lineup['bench'])
                <div class="kop">Bank</div>
                <div class="lijst">@foreach ($lineup['bench'] as $naam)<div>{{ $naam }}</div>@endforeach</div>
            @endif
        @else
            <div class="leeg">Voor deze wedstrijd is geen opstelling vastgelegd.</div>
        @endif
    </div>

    <div class="panel" id="panel-statistieken" hidden>
        <div id="statistieken">
            @foreach ($statRijen as [$wat, $eigen, $tegen])
                <div class="stat">
                    <div class="n">{{ $thuis ? $eigen : $tegen }}</div>
                    <div class="wat">{{ $wat }}</div>
                    <div class="n rechts">{{ $thuis ? $tegen : $eigen }}</div>
                </div>
            @endforeach
            <div class="stat">
                <div class="n">{{ $stats['substitutions'] ?? '0' }}</div>
                <div class="wat">Wissels</div>
                <div class="n rechts"></div>
            </div>
        </div>
    </div>

    <div class="voet">
        <svg><use href="#i-zender"/></svg>
        <div>
            <b>Live verslag</b>
            <span>Deze pagina werkt zolang de wedstrijd bezig is.</span>
        </div>
    </div>
</div>

<script>
    // De pagina toont hierboven al de stand; dit script houdt hem bij. Zonder
    // JavaScript blijft de begintoestand gewoon staan en werken de tabbladen niet
    // — daarom staat de tijdlijn, het belangrijkste, als eerste open.
    var stateUrl = @json($stateUrl);
    var thuis = @json($thuis);
    var timer = null;
    var gestopt = false;

    var tabs = Array.prototype.slice.call(document.querySelectorAll('.tab'));
    tabs.forEach(function (knop) {
        knop.addEventListener('click', function () {
            tabs.forEach(function (t) {
                var actief = t === knop;
                t.setAttribute('aria-selected', actief ? 'true' : 'false');
                document.getElementById('panel-' + t.dataset.panel).hidden = !actief;
            });
        });
    });

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Zelfde indeling als de server hierboven rendert, zodat een verversing niets
    // laat verspringen.
    function icoonVoor(type) {
        if (type === 'goal') return 'i-bal';
        if (type === 'card') return 'i-kaart';
        if (type === 'substitution') return 'i-wissel';
        if (type === 'halftime' || type === 'fulltime') return 'i-fluit';
        return 'i-klok';
    }
    function isHoogtepunt(type) {
        return type === 'goal' || type === 'card' || type === 'fulltime';
    }
    function extraVoor(e) {
        if (e.type === 'goal') return '<svg><use href="#i-bal"/></svg>';
        if (e.type === 'card') return '<span class="kaartje ' + (e.cardType === 'red' ? 'rood' : 'geel') + '"></span>';
        if (e.type === 'substitution') {
            return '<svg class="pijl-in"><use href="#i-pijl-op"/></svg>'
                 + '<svg class="pijl-uit"><use href="#i-pijl-neer"/></svg>';
        }
        if (e.type === 'halftime' || e.type === 'fulltime') return '<svg><use href="#i-fluit"/></svg>';
        return '';
    }

    function tekenTijdlijn(events) {
        if (!events.length) {
            return '<div class="leeg">Nog niets gebeurd.</div>';
        }
        var html = '';
        for (var i = 0; i < events.length; i++) {
            var e = events[i];
            var extra = extraVoor(e);
            html += '<div class="ev ' + (isHoogtepunt(e.type) ? 'hoogtepunt ' : '')
                 +  (e.side === 'opponent' ? 'tegenstander' : '') + '">'
                 +  '<div class="bol"><svg><use href="#' + icoonVoor(e.type) + '"/></svg></div>'
                 +  '<div class="kaart"><div class="tekst">'
                 +  '<div class="min">' + escapeHtml(e.minute) + '</div>'
                 +  '<div class="label">' + escapeHtml(e.label) + '</div>'
                 +  '</div>' + (extra ? '<div class="extra">' + extra + '</div>' : '') + '</div>'
                 +  '</div>';
        }
        return html;
    }

    function tekenStats(s) {
        var rijen = [
            ['Doelpunten', s.stats.goalsOwn, s.stats.goalsOpponent],
            ['Gele kaarten', s.stats.yellowOwn, s.stats.yellowOpponent],
            ['Rode kaarten', s.stats.redOwn, s.stats.redOpponent],
            ['Schoten op doel', s.stats.shotsOwn, s.stats.shotsOpponent]
        ];
        var html = '';
        for (var i = 0; i < rijen.length; i++) {
            var links  = thuis ? rijen[i][1] : rijen[i][2];
            var rechts = thuis ? rijen[i][2] : rijen[i][1];
            html += '<div class="stat"><div class="n">' + escapeHtml(links) + '</div>'
                 +  '<div class="wat">' + rijen[i][0] + '</div>'
                 +  '<div class="n rechts">' + escapeHtml(rechts) + '</div></div>';
        }
        html += '<div class="stat"><div class="n">' + escapeHtml(s.stats.substitutions) + '</div>'
             +  '<div class="wat">Wissels</div><div class="n rechts"></div></div>';
        return html;
    }

    function teken(s) {
        var links  = thuis ? s.scoreOwn : s.scoreOpponent;
        var rechts = thuis ? s.scoreOpponent : s.scoreOwn;
        document.getElementById('score').textContent = links + ' - ' + rechts;
        document.getElementById('klok').textContent = s.minute + "'";

        var afgelopen = s.hasEnded === 'true';
        var badge = document.getElementById('badge');
        badge.classList.toggle('live', !afgelopen);
        badge.classList.toggle('ended', afgelopen);
        document.getElementById('periode').textContent = afgelopen ? 'AFGELOPEN' : 'LIVE';

        document.getElementById('tijdlijn').innerHTML = tekenTijdlijn(s.events);
        document.getElementById('statistieken').innerHTML = tekenStats(s);

        // Afgelopen: stoppen met vragen, er verandert niets meer.
        if (afgelopen) {
            gestopt = true;
            stopPollen();
        }
    }

    function ophalen() {
        fetch(stateUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (r) {
                // 404 = de link is verlopen; verder proberen heeft geen zin.
                if (r.status === 404) { gestopt = true; stopPollen(); return null; }
                return r.ok ? r.json() : null;
            })
            .then(function (s) { if (s) teken(s); })
            .catch(function () { /* netwerkhik: bij de volgende ronde opnieuw */ });
    }

    function startPollen() {
        if (timer || gestopt) return;
        // Meteen één keer, niet pas over tien seconden. De stand hierboven staat
        // al goed, maar dit eerste verzoek is ook het teken van leven waaruit de
        // coach afleest hoeveel mensen er meekijken.
        ophalen();
        timer = setInterval(ophalen, 10000);
    }

    function stopPollen() {
        if (!timer) return;
        clearInterval(timer);
        timer = null;
    }

    if (@json($state['hasEnded']) !== 'true') {
        startPollen();
        // Écht stoppen zolang de pagina op de achtergrond staat. Hier stond een
        // listener die het interval liet doorlopen en er alleen een extra
        // verzoek bovenop deed. Een browser knijpt een verborgen tabblad terug
        // naar ongeveer één verzoek per minuut, en daarmee valt zo'n kijker
        // telkens uit de telling en springt er weer in: de coach ziet het
        // aantal meekijkers dan staan knipperen.
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                startPollen();
            } else {
                stopPollen();
            }
        });
    }
</script>
</body>
</html>
