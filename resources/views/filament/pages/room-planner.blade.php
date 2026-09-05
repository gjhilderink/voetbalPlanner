<x-filament-panels::page>

<style>
/* Alle opmaak staat hier en niet in een stylesheet, net als bij de
   bardienstplanner: het is één scherm met een eigen rooster, en een los
   bestand zou bij elke wijziging op twee plekken moeten worden bijgehouden. */
.rp-nav        { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap; }
.rp-nav-btns   { display:flex; gap:.4rem; }
.rp-btn        { display:inline-flex; align-items:center; gap:.3rem; padding:.4rem .7rem; border-radius:.5rem;
                 border:1px solid #e5e7eb; background:#fff; font-size:.8rem; color:#374151; cursor:pointer; }
.rp-btn:hover  { background:#f9fafb; }
.dark .rp-btn  { background:#1f2937; border-color:#374151; color:#d1d5db; }
.dark .rp-btn:hover { background:#374151; }
.rp-btn svg    { width:14px; height:14px; }
.rp-week-label { font-size:.85rem; font-weight:600; color:#6b7280; }
.dark .rp-week-label { color:#9ca3af; }

.rp-grid       { display:grid; grid-template-columns:150px repeat(7, 1fr); gap:.35rem; margin-top:1rem; min-width:900px; }
.rp-scroll     { overflow-x:auto; }
.rp-head       { font-size:.7rem; font-weight:700; color:#6b7280; text-align:center; padding:.3rem 0; }
.dark .rp-head { color:#9ca3af; }
.rp-head.today { color:#16a34a; }
.rp-roomname   { font-size:.8rem; font-weight:600; color:#1f2937; display:flex; align-items:center; gap:.4rem;
                 padding:.5rem .4rem; }
.dark .rp-roomname { color:#f3f4f6; }
.rp-dot        { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
.rp-cap        { font-size:.68rem; color:#9ca3af; font-weight:500; }

.rp-cell       { min-height:64px; border:1px dashed #e5e7eb; border-radius:.5rem; padding:.25rem;
                 display:flex; flex-direction:column; gap:.25rem; cursor:pointer; transition:background .12s; }
.rp-cell:hover { background:#f9fafb; border-color:#d1d5db; }
.dark .rp-cell { border-color:#374151; }
.dark .rp-cell:hover { background:#111827; }
.rp-cell.weekend { background:#fafafa; }
.dark .rp-cell.weekend { background:#0f151f; }

.rp-block      { border-radius:.35rem; padding:.25rem .35rem; font-size:.7rem; line-height:1.25; color:#fff;
                 cursor:pointer; overflow:hidden; }
.rp-block:hover { filter:brightness(1.08); }
.rp-block .t   { font-weight:700; opacity:.9; }
.rp-block .n   { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.rp-block.extern { border:1px solid rgba(255,255,255,.55); }

.rp-legend     { display:flex; gap:1rem; flex-wrap:wrap; margin-top:1rem; font-size:.72rem; color:#6b7280; }
.dark .rp-legend { color:#9ca3af; }
.rp-legend span { display:inline-flex; align-items:center; gap:.3rem; }

.rp-leeg       { text-align:center; color:#9ca3af; padding:2.5rem 1rem; font-size:.85rem; }
</style>

{{-- Weeknavigatie --}}
<div class="rp-nav" style="margin-bottom:.5rem;">
    <div class="rp-nav-btns">
        <button class="rp-btn" wire:click="previousWeek">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Vorige week
        </button>
        <button class="rp-btn" wire:click="goToCurrentWeek">Vandaag</button>
        <button class="rp-btn" wire:click="nextWeek">
            Volgende week
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>
    <div class="rp-week-label">
        Week {{ \Carbon\Carbon::parse($weekStart)->weekOfYear }}
        &mdash;
        {{ \Carbon\Carbon::parse($weekStart)->locale('nl')->isoFormat('D MMM') }}
        t/m
        {{ \Carbon\Carbon::parse($weekStart)->endOfWeek()->locale('nl')->isoFormat('D MMM YYYY') }}
    </div>
</div>

@if ($this->rooms->isEmpty())
    <div class="rp-leeg">
        Er zijn nog geen ruimtes. Voeg ze toe bij <strong>Beheer &rarr; Ruimtes</strong>;
        daarna kun je ze hier inplannen.
    </div>
@else
    <div class="rp-scroll">
        <div class="rp-grid">
            {{-- Kop: lege hoek plus de zeven dagen --}}
            <div></div>
            @foreach ($this->weekDays as $day)
                <div class="rp-head {{ $day->isToday() ? 'today' : '' }}">
                    {{ $day->locale('nl')->isoFormat('ddd D MMM') }}
                </div>
            @endforeach

            @foreach ($this->rooms as $room)
                <div class="rp-roomname">
                    <span class="rp-dot" style="background:{{ $room->kleur() }};"></span>
                    <span>
                        {{ $room->name }}
                        @if ($room->capacity)
                            <br><span class="rp-cap">{{ $room->capacity }} personen</span>
                        @endif
                    </span>
                </div>

                @foreach ($this->weekDays as $day)
                    @php
                        $datum   = $day->toDateString();
                        $blokken = $this->reservationsFor($room->id, $datum);
                    @endphp
                    {{-- Op een lege plek klikken opent het reserveerscherm met deze
                         ruimte en dag al ingevuld; alleen de tijd hoef je nog te
                         kiezen. --}}
                    <div
                        class="rp-cell {{ $day->isWeekend() ? 'weekend' : '' }}"
                        wire:click="mountAction('reserveer', { room: '{{ $room->id }}', datum: '{{ $datum }}' })"
                        title="Klik om {{ $room->name }} te reserveren op {{ $day->locale('nl')->isoFormat('D MMMM') }}"
                    >
                        @foreach ($blokken as $blok)
                            <div
                                class="rp-block {{ $blok->isExtern() ? 'extern' : '' }}"
                                style="background:{{ $room->kleur() }};"
                                x-on:click.stop
                                wire:click="mountAction('editReservation', { reservering: '{{ $blok->id }}' })"
                                title="{{ $blok->isExtern() ? 'Komt uit Outlook' : 'Klik om aan te passen' }}"
                            >
                                <div class="t">
                                    {{ $blok->starts_at?->format('H:i') }}&ndash;{{ $blok->ends_at?->format('H:i') }}
                                    @if ($blok->is_private) &middot; privé @endif
                                    {{-- Annuleren gaat niet via het bewerkscherm maar hier, met
                                         een bevestiging: hetzelfde als de bardienstplanner doet.
                                         click.stop, anders opent het blok ook nog. --}}
                                    @unless ($blok->isExtern())
                                        <span
                                            style="float:right;cursor:pointer;opacity:.75;font-weight:700;"
                                            x-on:click.stop
                                            wire:click="annuleerReservering('{{ $blok->id }}')"
                                            wire:confirm="Deze reservering annuleren?"
                                            title="Reservering annuleren"
                                        >&times;</span>
                                    @endunless
                                </div>
                                <div class="n">{{ $this->labelVoor($blok) }}</div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            @endforeach
        </div>
    </div>

    <div class="rp-legend">
        <span>Klik op een leeg vak om te reserveren.</span>
        <span><span class="rp-dot" style="background:#94a3b8;border:1px solid #fff;box-shadow:0 0 0 1px #94a3b8;"></span> Rand = afspraak uit Outlook</span>
        <span>Privé staat als bezet, zonder titel.</span>
    </div>
@endif

</x-filament-panels::page>
