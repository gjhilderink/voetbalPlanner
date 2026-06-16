<x-filament-panels::page>

    {{-- Week navigation --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <button
                wire:click="previousWeek"
                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
            >
                <x-heroicon-s-chevron-left class="w-4 h-4" />
                Vorige week
            </button>
            <button
                wire:click="goToCurrentWeek"
                class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
            >
                Vandaag
            </button>
            <button
                wire:click="nextWeek"
                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
            >
                Volgende week
                <x-heroicon-s-chevron-right class="w-4 h-4" />
            </button>
        </div>
        <div class="text-base font-semibold text-gray-900 dark:text-white">
            Week {{ \Carbon\Carbon::parse($weekStart)->weekOfYear }} &mdash;
            {{ \Carbon\Carbon::parse($weekStart)->locale('nl')->isoFormat('D MMM') }}
            t/m
            {{ \Carbon\Carbon::parse($weekStart)->endOfWeek()->locale('nl')->isoFormat('D MMM YYYY') }}
        </div>
    </div>

    {{-- Main layout: teams sidebar + calendar --}}
    <div
        class="flex gap-4"
        x-data="{
            draggingTeamId: null,
            draggingTeamName: null,
            draggingMemberId: null,
            draggingMemberName: null,
            dragType: null,
        }"
    >
        {{-- ===== Teams / Members sidebar ===== --}}
        <div class="w-52 shrink-0 space-y-4">

            {{-- Teams --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-primary-600 text-white text-xs font-semibold uppercase tracking-wide px-3 py-2">
                    Elftallen
                </div>
                <div class="p-2 space-y-1">
                    @foreach($this->teams as $team)
                        <div
                            draggable="true"
                            @dragstart="
                                draggingTeamId = '{{ $team->id }}';
                                draggingTeamName = '{{ addslashes($team->name) }}';
                                dragType = 'team';
                                $event.dataTransfer.effectAllowed = 'copy';
                            "
                            @dragend="draggingTeamId = null; dragType = null;"
                            class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-primary-50 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-700 cursor-grab active:cursor-grabbing select-none text-sm text-primary-800 dark:text-primary-200 hover:bg-primary-100 dark:hover:bg-primary-900/50"
                            title="Sleep naar een bardienst-blok"
                        >
                            <x-heroicon-s-user-group class="w-3.5 h-3.5 shrink-0 opacity-70" />
                            <span class="truncate">{{ $team->name }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-gray-500 text-white text-xs font-semibold uppercase tracking-wide px-3 py-2">
                    Legenda
                </div>
                <div class="p-3 space-y-1.5 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-400 shrink-0"></span>
                        <span class="text-gray-600 dark:text-gray-400">Ochtend</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-yellow-400 shrink-0"></span>
                        <span class="text-gray-600 dark:text-gray-400">Middag</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-purple-400 shrink-0"></span>
                        <span class="text-gray-600 dark:text-gray-400">Avond</span>
                    </div>
                    <div class="border-t border-gray-100 dark:border-gray-700 pt-1.5 mt-1.5 space-y-1.5">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-orange-400 shrink-0"></span>
                            <span class="text-gray-600 dark:text-gray-400">Open</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-500 shrink-0"></span>
                            <span class="text-gray-600 dark:text-gray-400">Bevestigd</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-green-500 shrink-0"></span>
                            <span class="text-gray-600 dark:text-gray-400">Vervuld</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Calendar grid ===== --}}
        <div class="flex-1 overflow-x-auto">
            <div class="min-w-[700px]">

                {{-- Day header row --}}
                <div class="grid grid-cols-7 gap-px mb-px">
                    @foreach($this->weekDays as $day)
                        @php
                            $isToday = $day->isToday();
                        @endphp
                        <div class="text-center py-2 rounded-t-lg text-sm font-semibold
                            {{ $isToday
                                ? 'bg-primary-600 text-white'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300' }}">
                            <div>{{ $day->locale('nl')->isoFormat('ddd') }}</div>
                            <div class="text-lg leading-tight {{ $isToday ? 'text-white' : '' }}">{{ $day->format('d') }}</div>
                            <div class="text-xs opacity-75">{{ $day->locale('nl')->isoFormat('MMM') }}</div>
                        </div>
                    @endforeach
                </div>

                {{-- Shift rows --}}
                @foreach(['ochtend' => ['label'=>'Ochtend','color'=>'blue'], 'middag' => ['label'=>'Middag','color'=>'yellow'], 'avond' => ['label'=>'Avond','color'=>'purple']] as $shift => $meta)
                    @php
                        $shiftColors = [
                            'blue'   => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-700/50',
                            'yellow' => 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-700/50',
                            'purple' => 'bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-700/50',
                        ];
                        $dotColors = [
                            'blue'   => 'bg-blue-400',
                            'yellow' => 'bg-yellow-400',
                            'purple' => 'bg-purple-400',
                        ];
                        $dropHover = [
                            'blue'   => 'ring-2 ring-blue-400 bg-blue-100 dark:bg-blue-900/40',
                            'yellow' => 'ring-2 ring-yellow-400 bg-yellow-100 dark:bg-yellow-900/40',
                            'purple' => 'ring-2 ring-purple-400 bg-purple-100 dark:bg-purple-900/40',
                        ];
                    @endphp

                    {{-- Shift label row --}}
                    <div class="grid grid-cols-7 gap-px mt-3 mb-px">
                        <div class="col-span-7 flex items-center gap-1.5 px-1 py-0.5">
                            <span class="w-2 h-2 rounded-full {{ $dotColors[$meta['color']] }}"></span>
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $meta['label'] }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-7 gap-px">
                        @foreach($this->weekDays as $day)
                            @php
                                $dateStr   = $day->toDateString();
                                $slotDuties = $this->dutiesForSlot($dateStr, $shift);
                                $cellBase   = $shiftColors[$meta['color']];
                                $hoverClass = $dropHover[$meta['color']];
                            @endphp

                            <div
                                class="min-h-[90px] rounded-lg border {{ $cellBase }} p-1.5 transition-all duration-150"
                                x-on:dragover.prevent="$el.classList.add(...'{{ $hoverClass }}'.split(' '))"
                                x-on:dragleave="$el.classList.remove(...'{{ $hoverClass }}'.split(' '))"
                                x-on:drop.prevent="
                                    $el.classList.remove(...'{{ $hoverClass }}'.split(' '));
                                    if (dragType === 'team' && draggingTeamId) {
                                        $wire.dropTeamOnSlot('{{ $dateStr }}', '{{ $shift }}', draggingTeamId);
                                    }
                                "
                            >
                                {{-- Existing duties in this slot --}}
                                @foreach($slotDuties as $duty)
                                    @php
                                        $statusColors = [
                                            'open'      => 'bg-orange-100 dark:bg-orange-900/30 border-orange-300 dark:border-orange-600',
                                            'bevestigd' => 'bg-blue-100 dark:bg-blue-900/30 border-blue-300 dark:border-blue-600',
                                            'vervuld'   => 'bg-green-100 dark:bg-green-900/30 border-green-300 dark:border-green-600',
                                        ];
                                        $dotStatus = [
                                            'open'      => 'bg-orange-400',
                                            'bevestigd' => 'bg-blue-500',
                                            'vervuld'   => 'bg-green-500',
                                        ];
                                    @endphp
                                    <div
                                        class="group relative rounded-md border {{ $statusColors[$duty->status] ?? 'bg-gray-100 border-gray-300' }} px-1.5 py-1 mb-1 text-xs"
                                        x-data="{ open: false }"
                                    >
                                        {{-- Team name --}}
                                        <div class="flex items-center gap-1 font-semibold text-gray-800 dark:text-gray-200 truncate">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $dotStatus[$duty->status] ?? 'bg-gray-400' }} shrink-0"></span>
                                            <span class="truncate">{{ $duty->team?->name ?? '—' }}</span>
                                        </div>

                                        {{-- Members --}}
                                        @if($duty->members->isNotEmpty())
                                            <div class="mt-0.5 text-gray-600 dark:text-gray-400 space-y-0.5">
                                                @foreach($duty->members as $member)
                                                    <div class="flex items-center gap-1 truncate">
                                                        <x-heroicon-s-user class="w-2.5 h-2.5 shrink-0 opacity-60" />
                                                        {{ $member->name }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="mt-0.5 text-gray-400 dark:text-gray-500 italic">Geen leden</div>
                                        @endif

                                        {{-- Actions overlay --}}
                                        <div class="absolute top-0.5 right-0.5 hidden group-hover:flex items-center gap-0.5">
                                            {{-- Status toggle --}}
                                            <button
                                                wire:click="updateDutyStatus('{{ $duty->id }}', '{{ match($duty->status) { 'open' => 'bevestigd', 'bevestigd' => 'vervuld', default => 'open' } }}')"
                                                class="p-0.5 rounded bg-white dark:bg-gray-700 shadow text-gray-500 hover:text-blue-600"
                                                title="Wijzig status"
                                            >
                                                <x-heroicon-s-arrow-path class="w-3 h-3" />
                                            </button>
                                            {{-- Delete --}}
                                            <button
                                                wire:click="removeDuty('{{ $duty->id }}')"
                                                wire:confirm="Weet je zeker dat je deze bardienst wilt verwijderen?"
                                                class="p-0.5 rounded bg-white dark:bg-gray-700 shadow text-gray-500 hover:text-red-600"
                                                title="Verwijderen"
                                            >
                                                <x-heroicon-s-x-mark class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </div>
                                @endforeach

                                {{-- Drop hint when empty --}}
                                @if($slotDuties->isEmpty())
                                    <div class="h-full flex items-center justify-center text-gray-300 dark:text-gray-600 pointer-events-none">
                                        <x-heroicon-o-plus-circle class="w-5 h-5" />
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach

            </div>
        </div>
    </div>

</x-filament-panels::page>
