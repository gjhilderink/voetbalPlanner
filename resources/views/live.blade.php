{{--
    Publieke livepagina. Zelfstandig HTML-bestand met inline stijl, zoals de
    andere publieke views hier — geen layout en geen Vite-afhankelijkheid, zodat
    de pagina het ook doet als de build ontbreekt.
--}}
@php
    $kleur = $club?->primary_color ?: '#1e3a5f';
    $thuis = ($state['isHome'] ?? 'false') === 'true';
    // De thuisploeg staat links, zoals op elk scorebord.
    $linksNaam   = $thuis ? ($state['teamName'] ?: 'Ons team') : ($state['opponent'] ?: 'Tegenstander');
    $rechtsNaam  = $thuis ? ($state['opponent'] ?: 'Tegenstander') : ($state['teamName'] ?: 'Ons team');
    $linksScore  = $thuis ? $state['scoreOwn'] : $state['scoreOpponent'];
    $rechtsScore = $thuis ? $state['scoreOpponent'] : $state['scoreOwn'];
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $linksNaam }} - {{ $rechtsNaam }} · live</title>
    <style>
        :root { --clr: {{ $kleur }}; }
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: #f4f5f7; margin: 0; color: #1f2937;
        }
        .wrap { max-width: 560px; margin: 0 auto; padding: 0 0 40px; }
        header {
            background: var(--clr); color: #fff; padding: 20px 16px 26px;
            border-radius: 0 0 26px 26px; text-align: center;
        }
        .status { display: inline-flex; align-items: center; gap: 7px; font-size: 13px;
                  background: rgba(0,0,0,.25); padding: 5px 12px; border-radius: 20px; }
        .dot { width: 9px; height: 9px; border-radius: 50%; background: #ef4444; }
        .dot.live { animation: pulse 1.4s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1 } 50% { opacity: .25 } }
        .board { display: flex; align-items: center; justify-content: center; gap: 14px; margin-top: 18px; }
        .team { flex: 1; font-size: 15px; font-weight: 600; line-height: 1.3; }
        .score { font-size: 44px; font-weight: 800; letter-spacing: 2px; white-space: nowrap; }
        .clock { margin-top: 10px; font-size: 14px; opacity: .85; }
        h2 { font-size: 15px; margin: 24px 16px 10px; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; }
        .card { background: #fff; margin: 0 16px; border-radius: 16px; box-shadow: 0 2px 10px rgba(0,0,0,.08); overflow: hidden; }
        .ev { display: flex; gap: 12px; align-items: flex-start; padding: 13px 16px; border-bottom: 1px solid #eef0f4; }
        .ev:last-child { border-bottom: 0; }
        .ev .min { min-width: 38px; font-weight: 700; color: var(--clr); font-size: 14px; }
        .ev .txt { flex: 1; font-size: 15px; line-height: 1.35; }
        .ev.opponent .min { color: #9ca3af; }
        .empty { padding: 22px 16px; color: #6b7280; font-size: 15px; text-align: center; }
        footer { text-align: center; margin-top: 26px; font-size: 12px; color: #9ca3af; padding: 0 16px; }
        .ended { background: #eef0f4; color: #4b5563; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <span class="status" id="status">
            <span class="dot" id="dot"></span>
            <span id="periode">{{ $state['periodLabel'] }}</span>
        </span>
        <div class="board">
            <div class="team">{{ $linksNaam }}</div>
            <div class="score" id="score">{{ $linksScore }} - {{ $rechtsScore }}</div>
            <div class="team">{{ $rechtsNaam }}</div>
        </div>
        <div class="clock" id="klok">{{ $state['minute'] }}'</div>
    </header>

    <h2>Verslag</h2>
    <div class="card" id="tijdlijn">
        @forelse ($state['events'] as $ev)
            <div class="ev {{ $ev['side'] === 'opponent' ? 'opponent' : '' }}">
                <div class="min">{{ $ev['minute'] }}</div>
                <div class="txt">{{ $ev['label'] }}</div>
            </div>
        @empty
            <div class="empty">Nog niets gebeurd.</div>
        @endforelse
    </div>

    <footer>Deze pagina werkt zolang de wedstrijd bezig is.</footer>
</div>

<script>
    // De pagina toont hierboven al de stand; dit script houdt hem bij. Zonder
    // JavaScript blijft de begintoestand gewoon staan.
    var stateUrl = @json($stateUrl);
    var thuis = @json($thuis);
    var timer = null;

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function teken(s) {
        var links  = thuis ? s.scoreOwn : s.scoreOpponent;
        var rechts = thuis ? s.scoreOpponent : s.scoreOwn;
        document.getElementById('score').textContent = links + ' - ' + rechts;
        document.getElementById('klok').textContent = s.minute + "'";
        document.getElementById('periode').textContent = s.periodLabel;

        var dot = document.getElementById('dot');
        dot.classList.toggle('live', s.isLive === 'true');
        document.getElementById('status').classList.toggle('ended', s.hasEnded === 'true');

        var html = '';
        for (var i = 0; i < s.events.length; i++) {
            var e = s.events[i];
            html += '<div class="ev ' + (e.side === 'opponent' ? 'opponent' : '') + '">'
                 +  '<div class="min">' + escapeHtml(e.minute) + '</div>'
                 +  '<div class="txt">' + escapeHtml(e.label) + '</div>'
                 +  '</div>';
        }
        document.getElementById('tijdlijn').innerHTML = html || '<div class="empty">Nog niets gebeurd.</div>';

        // Afgelopen: stoppen met vragen, er verandert niets meer.
        if (s.hasEnded === 'true' && timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function ophalen() {
        fetch(stateUrl, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (r) {
                // 404 = de link is verlopen; verder proberen heeft geen zin.
                if (r.status === 404) { clearInterval(timer); timer = null; return null; }
                return r.ok ? r.json() : null;
            })
            .then(function (s) { if (s) teken(s); })
            .catch(function () { /* netwerkhik: bij de volgende ronde opnieuw */ });
    }

    if (@json($state['hasEnded']) !== 'true') {
        timer = setInterval(ophalen, 10000);
        // Niet pollen terwijl de pagina op de achtergrond staat.
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') ophalen();
        });
    }
</script>
</body>
</html>
