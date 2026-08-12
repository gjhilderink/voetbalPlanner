<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\BarDuty;
use App\Models\FootballMatch;
use App\Models\Member;
use App\Models\Team;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class BarDutyPlanner extends Page
{
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel                  = 'Bardienst planner';
    protected static ?string $title                            = 'Bardienst planner';
    protected static string|\UnitEnum|null $navigationGroup    = 'Planning';
    protected static ?int $navigationSort                      = 25;
    protected string $view = 'filament.pages.bar-duty-planner';

    public string $weekStart = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'bar_commissie']) ?? false;
    }

    public function mount(): void
    {
        $this->weekStart = Carbon::now()->startOfWeek()->toDateString();
    }

    public function previousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->toDateString();
    }

    public function goToCurrentWeek(): void
    {
        $this->weekStart = Carbon::now()->startOfWeek()->toDateString();
    }

    #[Computed]
    public function weekDays(): array
    {
        $start = Carbon::parse($this->weekStart);
        $days  = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $start->copy()->addDays($i);
        }
        return $days;
    }

    #[Computed]
    public function duties(): Collection
    {
        $end = Carbon::parse($this->weekStart)->endOfWeek();
        return BarDuty::with(['team', 'members'])
            ->where('club_id', filament()->getTenant()?->id)
            ->whereBetween('date', [$this->weekStart, $end->toDateString()])
            ->orderBy('date')
            ->orderBy('shift')
            ->get();
    }

    #[Computed]
    public function teams(): Collection
    {
        return Team::where('club_id', filament()->getTenant()?->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Het "eerste elftal" van de club. Eerst het handmatig gemarkeerde team
     * (is_first_team); anders een heuristiek (naam eindigt op " 1", anders exact
     * de clubnaam, anders het eerste team op naam).
     */
    #[Computed]
    public function firstTeam(): ?Team
    {
        $club  = filament()->getTenant();
        $teams = $this->teams;

        return $teams->first(fn(Team $t) => (bool) $t->is_first_team)
            ?? $teams->first(fn(Team $t) => (bool) preg_match('/\s1$/', trim($t->name)))
            ?? $teams->first(fn(Team $t) => mb_strtolower(trim($t->name)) === mb_strtolower(trim($club?->name ?? '')))
            ?? $teams->first();
    }

    /** Wedstrijden van het eerste elftal in de getoonde week, per datum. */
    #[Computed]
    public function firstTeamMatches(): Collection
    {
        $team = $this->firstTeam();
        if (!$team) {
            return collect();
        }
        $start = Carbon::parse($this->weekStart)->startOfDay();
        $end   = Carbon::parse($this->weekStart)->endOfWeek()->endOfDay();

        return FootballMatch::where('team_id', $team->id)
            ->whereBetween('match_datetime', [$start, $end])
            ->orderBy('match_datetime')
            ->get()
            ->groupBy(fn(FootballMatch $m) => $m->match_datetime?->toDateString());
    }

    /** De (eerste) wedstrijd van het eerste elftal op deze datum, of null. */
    public function firstMatchFor(string $date): ?FootballMatch
    {
        return $this->firstTeamMatches->get($date)?->first();
    }

    public function dutiesForSlot(string $date, string $shift): Collection
    {
        return $this->duties->filter(
            fn($d) => $d->date->toDateString() === $date && $d->shift === $shift
        )->values();
    }

    /** Handmatige (custom) bardiensten op deze datum — buiten de vaste dagdelen. */
    public function customDutiesForDate(string $date): Collection
    {
        return $this->duties->filter(
            fn($d) => $d->date->toDateString() === $date && $d->isCustom()
        )->values();
    }

    /** Zijn er handmatige bardiensten in de getoonde week? */
    public function hasCustomDuties(): bool
    {
        return $this->duties->contains(fn($d) => $d->isCustom());
    }

    public function dropTeamOnSlot(string $date, string $shift, string $teamId): void
    {
        $existing = BarDuty::where('club_id', filament()->getTenant()?->id)
            ->whereDate('date', $date)
            ->where('shift', $shift)
            ->where('team_id', $teamId)
            ->first();

        if ($existing) {
            Notification::make()->warning()->title('Dit elftal staat al in dit blok')->send();
            return;
        }

        BarDuty::create([
            'club_id' => filament()->getTenant()?->id,
            'team_id' => $teamId,
            'date'    => $date,
            'shift'   => $shift,
            'status'  => 'open',
        ]);

        unset($this->duties);

        Notification::make()->success()->title('Elftal ingepland')->send();
    }

    public function removeDuty(string $id): void
    {
        BarDuty::find($id)?->delete();
        unset($this->duties);
    }

    public function updateDutyStatus(string $id, string $status): void
    {
        BarDuty::find($id)?->update(['status' => $status]);
        unset($this->duties);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addDuty')
                ->label('Bardienst toevoegen')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->form([
                    Grid::make(2)->schema([
                        Forms\Components\Radio::make('shift_type')
                            ->label('Soort bardienst')
                            ->options([
                                'vast'      => 'Vast dagdeel (za/zo)',
                                'handmatig' => 'Handmatig (eigen dag & tijd)',
                            ])
                            ->inline()
                            ->dehydrated(false)
                            ->live()
                            ->default('vast')
                            ->afterStateUpdated(function (string $state, Set $set) {
                                if ($state === 'handmatig') {
                                    $set('shift', null);
                                } else {
                                    $set('custom_label', null);
                                    $set('start_time', null);
                                    $set('end_time', null);
                                    $set('required_count', null);
                                }
                            })
                            ->columnSpanFull(),
                        Forms\Components\DatePicker::make('date')
                            ->label('Datum')
                            ->displayFormat('d-m-Y')
                            ->default(fn() => Carbon::parse($this->weekStart))
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('shift')
                            ->label('Dagdeel')
                            ->options(fn(Get $get) => collect(
                                    BarDuty::shiftsForDate(Carbon::parse($get('date') ?: $this->weekStart))
                                )->mapWithKeys(fn($def, $key) => [
                                    $key => "{$def['label']} ({$def['start']}–{$def['end']})",
                                ])->all())
                            ->helperText('Alleen op zaterdag en zondag zijn er dagdelen.')
                            ->visible(fn(Get $get) => $get('shift_type') !== 'handmatig')
                            ->required(fn(Get $get) => $get('shift_type') !== 'handmatig')
                            ->live(),
                        Forms\Components\TextInput::make('custom_label')
                            ->label('Omschrijving')
                            ->placeholder('bijv. Toernooi, Feestavond')
                            ->maxLength(60)
                            ->visible(fn(Get $get) => $get('shift_type') === 'handmatig'),
                        Forms\Components\TimePicker::make('start_time')
                            ->label('Begintijd')
                            ->seconds(false)->format('H:i')->displayFormat('H:i')
                            ->visible(fn(Get $get) => $get('shift_type') === 'handmatig')
                            ->required(fn(Get $get) => $get('shift_type') === 'handmatig'),
                        Forms\Components\TimePicker::make('end_time')
                            ->label('Eindtijd')
                            ->seconds(false)->format('H:i')->displayFormat('H:i')
                            ->visible(fn(Get $get) => $get('shift_type') === 'handmatig')
                            ->required(fn(Get $get) => $get('shift_type') === 'handmatig'),
                        Forms\Components\TextInput::make('required_count')
                            ->label('Aantal personen')
                            ->numeric()->minValue(1)->maxValue(10)->default(2)
                            ->visible(fn(Get $get) => $get('shift_type') === 'handmatig')
                            ->required(fn(Get $get) => $get('shift_type') === 'handmatig'),
                        Forms\Components\Select::make('team_id')
                            ->label('Elftal (verantwoordelijk)')
                            ->options(fn() => Team::where('club_id', filament()->getTenant()?->id)
                                ->where('is_active', true)->orderBy('name')
                                ->pluck('name', 'id')->all())
                            ->searchable()
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'open'      => 'Open',
                                'bevestigd' => 'Bevestigd',
                                'vervuld'   => 'Vervuld',
                            ])
                            ->default('open')
                            ->required(),
                        Forms\Components\Select::make('member_ids')
                            ->label('Leden')
                            ->multiple()
                            ->maxItems(fn(Get $get) => BarDuty::SHIFTS[$get('shift')]['required'] ?? ((int) $get('required_count') ?: 2))
                            ->options(fn(Get $get) => Member::query()
                                ->when(
                                    $get('team_id'),
                                    fn($q, $tid) => $q->whereHas('teams', fn($t) => $t->where('teams.id', $tid)),
                                    fn($q) => $q->whereRaw('1=0'),
                                )
                                ->where('is_active', true)->orderBy('name')
                                ->pluck('name', 'id')->all())
                            ->helperText('Selecteer eerst een elftal. Aantal hangt af van het dagdeel (2 of 3).')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Opmerkingen')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                ])
                ->action(function (array $data): void {
                    $isCustom = empty($data['shift']) || !isset(BarDuty::SHIFTS[$data['shift']]);

                    $duty = BarDuty::create([
                        'club_id'        => filament()->getTenant()?->id,
                        'team_id'        => $data['team_id'],
                        'date'           => $data['date'],
                        'shift'          => $isCustom ? BarDuty::SHIFT_CUSTOM : $data['shift'],
                        'status'         => $data['status'] ?? 'open',
                        'notes'          => $data['notes'] ?? null,
                        'custom_label'   => $isCustom ? ($data['custom_label'] ?? null) : null,
                        'start_time'     => $isCustom ? ($data['start_time'] ?? null) : null,
                        'end_time'       => $isCustom ? ($data['end_time'] ?? null) : null,
                        'required_count' => $isCustom ? ((int) ($data['required_count'] ?? 2) ?: 2) : null,
                    ]);

                    if (!empty($data['member_ids'])) {
                        $duty->members()->sync($data['member_ids']);
                        $duty->refreshStatus();
                    }

                    unset($this->duties);

                    Notification::make()->success()->title('Bardienst toegevoegd')->send();
                }),

            Action::make('assignMembersAction')
                ->label('Leden toewijzen')
                ->icon('heroicon-o-user-plus')
                ->color('info')
                ->form([
                    Forms\Components\Select::make('duty_id')
                        ->label('Bardienst')
                        ->options(fn() => BarDuty::with('team')
                            ->where('club_id', filament()->getTenant()?->id)
                            ->orderBy('date')
                            ->get()
                            ->mapWithKeys(fn($d) => [
                                $d->id => $d->date?->locale('nl')->isoFormat('ddd D MMM')
                                    . ' – ' . $d->shiftLabel()
                                    . ($d->timeRange() ? ' ' . $d->timeRange() : '')
                                    . ($d->team ? ' (' . $d->team->name . ')' : ''),
                            ])->all())
                        ->searchable()
                        ->required()
                        ->live(),
                    Forms\Components\Select::make('member_ids')
                        ->label('Leden')
                        ->multiple()
                        ->maxItems(fn(Get $get) => BarDuty::find($get('duty_id'))?->requiredCount() ?? 2)
                        ->options(fn(Get $get) => $get('duty_id')
                            ? Member::whereHas('teams', fn($q) => $q->where(
                                'teams.id',
                                BarDuty::find($get('duty_id'))?->team_id
                            ))->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()
                            : []
                        )
                        ->default(fn(Get $get) => $get('duty_id')
                            ? BarDuty::find($get('duty_id'))?->members->pluck('id')->all()
                            : []
                        )
                        ->helperText('Selecteer eerst een bardienst')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $duty = BarDuty::find($data['duty_id']);
                    if (!$duty) return;

                    $duty->members()->sync($data['member_ids'] ?? []);
                    $duty->refreshStatus();
                    unset($this->duties);

                    Notification::make()->success()->title('Leden opgeslagen')->send();
                }),
        ];
    }
}
