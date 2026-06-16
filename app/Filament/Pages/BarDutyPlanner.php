<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\BarDuty;
use App\Models\Member;
use App\Models\Team;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
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

    public function dutiesForSlot(string $date, string $shift): Collection
    {
        return $this->duties->filter(
            fn($d) => $d->date->toDateString() === $date && $d->shift === $shift
        )->values();
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
                        Forms\Components\DatePicker::make('date')
                            ->label('Datum')
                            ->displayFormat('d-m-Y')
                            ->default(fn() => Carbon::parse($this->weekStart))
                            ->required(),
                        Forms\Components\Select::make('shift')
                            ->label('Dienst')
                            ->options([
                                'ochtend' => 'Ochtend',
                                'middag'  => 'Middag',
                                'avond'   => 'Avond',
                            ])
                            ->required(),
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
                            ->label('Leden (max. 2)')
                            ->multiple()
                            ->maxItems(2)
                            ->options(fn(Forms\Get $get) => Member::query()
                                ->when(
                                    $get('team_id'),
                                    fn($q, $tid) => $q->whereHas('teams', fn($t) => $t->where('teams.id', $tid)),
                                    fn($q) => $q->whereRaw('1=0'),
                                )
                                ->where('is_active', true)->orderBy('name')
                                ->pluck('name', 'id')->all())
                            ->helperText('Selecteer eerst een elftal')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Opmerkingen')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                ])
                ->action(function (array $data): void {
                    $duty = BarDuty::create([
                        'club_id' => filament()->getTenant()?->id,
                        'team_id' => $data['team_id'],
                        'date'    => $data['date'],
                        'shift'   => $data['shift'],
                        'status'  => $data['status'] ?? 'open',
                        'notes'   => $data['notes'] ?? null,
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
                                    . ' – ' . ucfirst($d->shift)
                                    . ($d->team ? ' (' . $d->team->name . ')' : ''),
                            ])->all())
                        ->searchable()
                        ->required()
                        ->live(),
                    Forms\Components\Select::make('member_ids')
                        ->label('Leden (max. 2)')
                        ->multiple()
                        ->maxItems(2)
                        ->options(fn(Forms\Get $get) => $get('duty_id')
                            ? Member::whereHas('teams', fn($q) => $q->where(
                                'teams.id',
                                BarDuty::find($get('duty_id'))?->team_id
                            ))->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()
                            : []
                        )
                        ->default(fn(Forms\Get $get) => $get('duty_id')
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
