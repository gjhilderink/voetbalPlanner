{{--
    Het live verslag bij een wedstrijd in de beheerportal. Alleen lezen: het
    verslag wordt in de app vastgelegd en daar ook verwijderd.

    Inline stijl in plaats van Tailwind-klassen, zodat de kleuren hier hetzelfde
    zijn als op de publieke livepagina en niet meebewegen met het thema.
--}}
@php
    $kleurVoor = fn (string $type) => in_array($type, ['goal', 'card', 'fulltime'], true)
        ? '#e11d2e'
        : '#0d0f14';

    $tekenVoor = fn ($ev) => match ($ev->type) {
        'goal'         => '⚽',
        'card'         => $ev->card_type === 'red' ? '🟥' : '🟨',
        'substitution' => '⇄',
        'halftime', 'fulltime' => '⏱',
        default        => '▶',
    };

    $eigen = $match->is_home ? ($match->team?->name ?: 'Ons team') : ($match->opponent ?: 'Tegenstander');
    $ander = $match->is_home ? ($match->opponent ?: 'Tegenstander') : ($match->team?->name ?: 'Ons team');
    $score = $match->liveScore();
    $links  = $match->is_home ? $score['own'] : $score['opponent'];
    $rechts = $match->is_home ? $score['opponent'] : $score['own'];
@endphp

<div style="font-family: inherit;">
    <div style="display:flex; align-items:center; justify-content:center; gap:16px;
                background:#0d0f14; color:#fff; border-radius:12px; padding:14px 16px; margin-bottom:16px;">
        <div style="flex:1; text-align:right; font-weight:600;">{{ $eigen }}</div>
        <div style="font-size:26px; font-weight:800; white-space:nowrap;">{{ $links }} - {{ $rechts }}</div>
        <div style="flex:1; font-weight:600;">{{ $ander }}</div>
    </div>

    <div style="display:flex; flex-direction:column; gap:8px;">
        @foreach ($events as $ev)
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="flex:none; width:34px; height:34px; border-radius:50%;
                            display:flex; align-items:center; justify-content:center;
                            background:{{ $kleurVoor($ev->type) }}; color:#fff; font-size:15px;">
                    {{ $tekenVoor($ev) }}
                </div>
                <div style="flex:none; width:42px; font-weight:700;
                            color:{{ $ev->side === 'opponent' ? '#6b7280' : '#e11d2e' }};">
                    {{ $ev->minute !== null ? $ev->minute . "'" : '' }}
                </div>
                <div style="flex:1;">{{ $ev->label() }}</div>
            </div>
        @endforeach
    </div>
</div>
