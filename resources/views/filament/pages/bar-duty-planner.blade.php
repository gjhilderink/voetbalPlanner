<x-filament-panels::page>

<style>
.bdp-wrap       { display:flex; gap:1rem; align-items:flex-start; }
.bdp-sidebar    { width:200px; flex-shrink:0; display:flex; flex-direction:column; gap:1rem; }
.bdp-panel      { background:#fff; border:1px solid #e5e7eb; border-radius:.75rem; overflow:hidden; }
.dark .bdp-panel{ background:#1f2937; border-color:#374151; }
.bdp-panel-head { padding:.4rem .75rem; font-size:.7rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#fff; }
.bdp-panel-body { padding:.5rem; display:flex; flex-direction:column; gap:.25rem; }

.bdp-team-chip  {
    display:flex; align-items:center; gap:.4rem;
    padding:.35rem .5rem; border-radius:.5rem;
    background:#f0fdf4; border:1px solid #bbf7d0;
    font-size:.8rem; color:#166534;
    cursor:grab; user-select:none;
    white-space:nowrap; overflow:hidden;
}
.dark .bdp-team-chip { background:#14532d33; border-color:#166534; color:#86efac; }
.bdp-team-chip:active { cursor:grabbing; }
.bdp-team-chip svg { width:14px; height:14px; flex-shrink:0; opacity:.7; }
.bdp-team-chip span { overflow:hidden; text-overflow:ellipsis; }

.bdp-legend-row { display:flex; align-items:center; gap:.5rem; font-size:.75rem; color:#6b7280; margin-bottom:.25rem; }
.bdp-dot        { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
.bdp-legend-sep { border-top:1px solid #f3f4f6; margin:.25rem 0; }
.dark .bdp-legend-sep { border-color:#374151; }

.bdp-main       { flex:1; overflow-x:auto; }
.bdp-inner      { min-width:700px; }

.bdp-nav        { display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem; }
.bdp-nav-btns   { display:flex; gap:.4rem; }
.bdp-btn        {
    display:inline-flex; align-items:center; gap:.3rem;
    padding:.35rem .75rem; border-radius:.5rem;
    border:1px solid #d1d5db; background:#fff;
    font-size:.8rem; font-weight:500; color:#374151;
    cursor:pointer; white-space:nowrap;
}
.dark .bdp-btn  { background:#1f2937; border-color:#4b5563; color:#d1d5db; }
.bdp-btn:hover  { background:#f9fafb; }
.dark .bdp-btn:hover { background:#374151; }
.bdp-btn svg    { width:14px; height:14px; }
.bdp-week-label { font-size:.95rem; font-weight:600; color:#111827; }
.dark .bdp-week-label { color:#f9fafb; }

.bdp-day-grid   { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; }

.bdp-day-head   { text-align:center; padding:.5rem .25rem; border-radius:.5rem .5rem 0 0; font-size:.8rem; font-weight:600; }
.bdp-day-head .dn { font-size:.75rem; }
.bdp-day-head .dd { font-size:1.1rem; line-height:1.2; }
.bdp-day-head .dm { font-size:.7rem; opacity:.7; }
.bdp-day-normal { background:#f3f4f6; color:#374151; }
.dark .bdp-day-normal { background:#374151; color:#d1d5db; }
.bdp-day-today  { background:#16a34a; color:#fff; }

.bdp-shift-label { display:flex; align-items:center; gap:.4rem; padding:.2rem .25rem; margin-top:.75rem; margin-bottom:2px; }
.bdp-shift-label svg { width:10px; height:10px; }
.bdp-shift-label span { font-size:.68rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#9ca3af; }

.bdp-slot       { min-height:90px; border-radius:.5rem; border:1.5px solid; padding:.35rem; position:relative; transition:background .15s,box-shadow .15s; }
.bdp-slot.over  { box-shadow:0 0 0 2px currentColor; filter:brightness(.96); }

.bdp-slot-o     { background:#eff6ff; border-color:#bfdbfe; }
.dark .bdp-slot-o { background:#1e3a5f33; border-color:#1e40af55; }
.bdp-slot-m     { background:#fefce8; border-color:#fde68a; }
.dark .bdp-slot-m { background:#78350f33; border-color:#92400e55; }
.bdp-slot-a     { background:#faf5ff; border-color:#e9d5ff; }
.dark .bdp-slot-a { background:#4c1d9533; border-color:#6d28d955; }

.bdp-empty-hint { display:flex; align-items:center; justify-content:center; height:60px; pointer-events:none; }
.bdp-empty-hint svg { width:22px; height:22px; color:#d1d5db; }

.bdp-card       { border-radius:.4rem; border:1px solid; padding:.3rem .4rem; margin-bottom:.3rem; font-size:.75rem; position:relative; }
.bdp-card-open  { background:#fff7ed; border-color:#fed7aa; }
.dark .bdp-card-open { background:#431407; border-color:#9a3412; }
.bdp-card-bev   { background:#eff6ff; border-color:#bfdbfe; }
.dark .bdp-card-bev { background:#1e3a5f; border-color:#1e40af; }
.bdp-card-ver   { background:#f0fdf4; border-color:#bbf7d0; }
.dark .bdp-card-ver { background:#14532d; border-color:#166534; }

.bdp-card-name  { display:flex; align-items:center; gap:.3rem; font-weight:600; color:#1f2937; overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }
.dark .bdp-card-name { color:#f3f4f6; }
.bdp-card-dot   { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
.bdp-card-members { margin-top:.2rem; display:flex; flex-direction:column; gap:.1rem; }
.bdp-card-member { display:flex; align-items:center; gap:.25rem; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.dark .bdp-card-member { color:#9ca3af; }
.bdp-card-member svg { width:10px; height:10px; flex-shrink:0; opacity:.7; }
.bdp-card-empty { margin-top:.2rem; color:#9ca3af; font-style:italic; }

.bdp-card-actions { position:absolute; top:.25rem; right:.25rem; display:none; gap:.2rem; }
.bdp-card:hover .bdp-card-actions { display:flex; }
.bdp-act-btn    { padding:.15rem; border-radius:.3rem; background:#fff; border:none; cursor:pointer; color:#6b7280; display:flex; }
.dark .bdp-act-btn { background:#374151; }
.bdp-act-btn:hover { color:#2563eb; }
.bdp-act-btn.del:hover { color:#dc2626; }
.bdp-act-btn svg { width:12px; height:12px; }

.bdp-search     { display:flex; align-items:center; gap:.35rem; margin:.5rem; padding:.35rem .5rem; border-radius:.4rem; border:1px solid #d1d5db; background:#f9fafb; }
.dark .bdp-search { background:#374151; border-color:#4b5563; }
.bdp-search svg { width:13px; height:13px; color:#9ca3af; flex-shrink:0; }
.bdp-search input { border:none; background:transparent; outline:none; font-size:.8rem; color:#374151; width:100%; }
.dark .bdp-search input { color:#d1d5db; }
.bdp-search input::placeholder { color:#9ca3af; }
.bdp-teams-list { padding:.35rem; display:flex; flex-direction:column; gap:.25rem; max-height:340px; overflow-y:auto; }
.bdp-teams-list::-webkit-scrollbar { width:4px; }
.bdp-teams-list::-webkit-scrollbar-track { background:transparent; }
.bdp-teams-list::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:2px; }
.bdp-no-match   { font-size:.75rem; color:#9ca3af; padding:.25rem .5rem; text-align:center; }
</style>

{{-- Week navigation --}}
<div class="bdp-nav" style="margin-bottom:1rem;">
    <div class="bdp-nav-btns">
        <button class="bdp-btn" wire:click="previousWeek">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Vorige week
        </button>
        <button class="bdp-btn" wire:click="goToCurrentWeek">Vandaag</button>
        <button class="bdp-btn" wire:click="nextWeek">
            Volgende week
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>
    <div class="bdp-week-label">
        Week {{ \Carbon\Carbon::parse($weekStart)->weekOfYear }}
        &mdash;
        {{ \Carbon\Carbon::parse($weekStart)->locale('nl')->isoFormat('D MMM') }}
        t/m
        {{ \Carbon\Carbon::parse($weekStart)->endOfWeek()->locale('nl')->isoFormat('D MMM YYYY') }}
    </div>
</div>

<div class="bdp-wrap"
    x-data="{ draggingTeamId: null, dragType: null }"
>
    {{-- Sidebar --}}
    <div class="bdp-sidebar">
        @php
            $teamsJson = $this->teams->map(fn($t) => ['id' => $t->id, 'name' => $t->name])->values()->toJson();
        @endphp
        <div class="bdp-panel" x-data="{
            search: '',
            allTeams: {{ $teamsJson }},
            get filtered() {
                if (!this.search) return this.allTeams;
                const q = this.search.toLowerCase();
                return this.allTeams.filter(t => t.name.toLowerCase().includes(q));
            }
        }">
            <div class="bdp-panel-head" style="background:#16a34a;">Elftallen</div>
            <div class="bdp-search">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803a7.5 7.5 0 0010.607 10.607z"/></svg>
                <input
                    type="text"
                    placeholder="Zoeken..."
                    x-model="search"
                    autocomplete="off"
                >
            </div>
            <div class="bdp-teams-list">
                <template x-for="team in filtered" :key="team.id">
                    <div
                        class="bdp-team-chip"
                        draggable="true"
                        title="Sleep naar een blok om in te plannen"
                        @dragstart="draggingTeamId = team.id; dragType = 'team'; $event.dataTransfer.effectAllowed = 'copy';"
                        @dragend="draggingTeamId = null; dragType = null;"
                    >
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                        <span x-text="team.name"></span>
                    </div>
                </template>
                <p class="bdp-no-match" x-show="filtered.length === 0">
                    Geen elftallen gevonden
                </p>
            </div>
        </div>

        {{-- Legend --}}
        <div class="bdp-panel">
            <div class="bdp-panel-head" style="background:#6b7280;">Legenda</div>
            <div class="bdp-panel-body" style="padding:.75rem;">
                <div class="bdp-legend-row"><span class="bdp-dot" style="background:#60a5fa;"></span> Ochtend</div>
                <div class="bdp-legend-row"><span class="bdp-dot" style="background:#fbbf24;"></span> Middag</div>
                <div class="bdp-legend-row"><span class="bdp-dot" style="background:#c084fc;"></span> Avond</div>
                <div class="bdp-legend-sep"></div>
                <div class="bdp-legend-row"><span class="bdp-dot" style="background:#fb923c;"></span> Open</div>
                <div class="bdp-legend-row"><span class="bdp-dot" style="background:#3b82f6;"></span> Bevestigd</div>
                <div class="bdp-legend-row"><span class="bdp-dot" style="background:#22c55e;"></span> Vervuld</div>
            </div>
        </div>
    </div>

    {{-- Calendar --}}
    <div class="bdp-main">
        <div class="bdp-inner">

            {{-- Day headers --}}
            <div class="bdp-day-grid" style="margin-bottom:2px;">
                @foreach($this->weekDays as $day)
                    <div class="bdp-day-head {{ $day->isToday() ? 'bdp-day-today' : 'bdp-day-normal' }}">
                        <div class="dn">{{ $day->locale('nl')->isoFormat('ddd') }}</div>
                        <div class="dd">{{ $day->format('d') }}</div>
                        <div class="dm">{{ $day->locale('nl')->isoFormat('MMM') }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Dagdeel-rijen: za/zo hebben elk 4 dagdelen (eigen tijden), door-de-week geen --}}
            @php
                $slotColors = ['#60a5fa', '#fbbf24', '#f472b6', '#c084fc'];
            @endphp

            @for($row = 0; $row < 4; $row++)
                <div class="bdp-day-grid" style="margin-top:.5rem;">
                    @foreach($this->weekDays as $day)
                        @php
                            $dateStr   = $day->toDateString();
                            $dayShifts = \App\Models\BarDuty::shiftsForDate($day);
                            $shiftKey  = array_keys($dayShifts)[$row] ?? null;
                            $def       = array_values($dayShifts)[$row] ?? null;
                            $slotDuties = $shiftKey ? $this->dutiesForSlot($dateStr, $shiftKey) : collect();
                        @endphp

                        @if($shiftKey)
                        <div
                            class="bdp-slot"
                            @dragover.prevent="$el.classList.add('over')"
                            @dragleave="$el.classList.remove('over')"
                            @drop.prevent="
                                $el.classList.remove('over');
                                if (dragType === 'team' && draggingTeamId) {
                                    $wire.dropTeamOnSlot('{{ $dateStr }}', '{{ $shiftKey }}', draggingTeamId);
                                }
                            "
                        >
                            <div style="display:flex;align-items:center;gap:.35rem;margin-bottom:.3rem;font-size:.66rem;font-weight:700;color:#6b7280;">
                                <span class="bdp-dot" style="width:8px;height:8px;background:{{ $slotColors[$row] ?? '#9ca3af' }};border-radius:50%;display:inline-block;flex-shrink:0;"></span>
                                <span>{{ $def['label'] }}</span>
                                <span style="margin-left:auto;font-weight:600;color:#9ca3af;">{{ $def['start'] }}–{{ $def['end'] }} · {{ $def['required'] }}p</span>
                            </div>

                            @forelse($slotDuties as $duty)
                                @php
                                    $cardClass = match($duty->status) {
                                        'bevestigd' => 'bdp-card-bev',
                                        'vervuld'   => 'bdp-card-ver',
                                        default     => 'bdp-card-open',
                                    };
                                    $dotColor = match($duty->status) {
                                        'bevestigd' => '#3b82f6',
                                        'vervuld'   => '#22c55e',
                                        default     => '#fb923c',
                                    };
                                    $nextStatus = match($duty->status) {
                                        'open'      => 'bevestigd',
                                        'bevestigd' => 'vervuld',
                                        default     => 'open',
                                    };
                                @endphp

                                <div class="bdp-card {{ $cardClass }}">
                                    <div class="bdp-card-name">
                                        <span class="bdp-card-dot" style="background:{{ $dotColor }};"></span>
                                        {{ $duty->team?->name ?? '—' }}
                                    </div>

                                    @if($duty->members->isNotEmpty())
                                        <div class="bdp-card-members">
                                            @foreach($duty->members as $member)
                                                <div class="bdp-card-member">
                                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
                                                    {{ $member->name }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="bdp-card-empty">Geen leden</div>
                                    @endif

                                    <div class="bdp-card-actions">
                                        <button
                                            class="bdp-act-btn"
                                            wire:click="updateDutyStatus('{{ $duty->id }}', '{{ $nextStatus }}')"
                                            title="Status wijzigen"
                                        >
                                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                                        </button>
                                        <button
                                            class="bdp-act-btn del"
                                            wire:click="removeDuty('{{ $duty->id }}')"
                                            wire:confirm="Weet je zeker dat je deze bardienst wilt verwijderen?"
                                            title="Verwijderen"
                                        >
                                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                </div>
                            @empty
                                <div class="bdp-empty-hint">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                            @endforelse
                        </div>
                        @else
                        <div class="bdp-slot" style="opacity:.4;"></div>
                        @endif
                    @endforeach
                </div>
            @endfor

        </div>
    </div>
</div>

</x-filament-panels::page>
