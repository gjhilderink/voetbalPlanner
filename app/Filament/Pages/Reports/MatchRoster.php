<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\FootballMatch;
use App\Models\Team;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MatchRoster extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel                = 'Wedstrijd rooster';
    protected static ?string $title                          = 'Wedstrijd rooster per periode';
    protected static string|\UnitEnum|null $navigationGroup  = 'Rapportage';
    protected static ?int    $navigationSort                  = 21;

    protected string $view = 'filament.pages.reports.match-roster';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportPdf')
                ->label('PDF exporteren')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function (): \Symfony\Component\HttpFoundation\StreamedResponse {
                    $user    = auth()->user();
                    $tenant  = filament()->getTenant();
                    $filters = $this->tableFilters;

                    $teamId    = $filters['team_id']['value'] ?? null;
                    $status    = $filters['status']['value'] ?? null;
                    $fromDate  = $filters['period']['from'] ?? null;
                    $untilDate = $filters['period']['until'] ?? null;

                    $query = FootballMatch::query()
                        ->with(['team', 'coaches', 'drivers', 'cleaners', 'fruitHero'])
                        ->orderBy('match_datetime');

                    if ($tenant) {
                        $query->whereHas('team', fn($q) => $q->where('club_id', $tenant->id));
                    }
                    if (!$user?->isAdmin()) {
                        $query->whereIn('team_id', $user->managedTeamIds());
                    }
                    if ($teamId) {
                        $query->where('team_id', $teamId);
                    }
                    if ($status) {
                        $query->where('status', $status);
                    }
                    if ($fromDate) {
                        $query->whereDate('match_datetime', '>=', $fromDate);
                    }
                    if ($untilDate) {
                        $query->whereDate('match_datetime', '<=', $untilDate);
                    }

                    $matches  = $query->get();
                    $teamName = $teamId ? Team::find($teamId)?->name : null;

                    $periodLabel = null;
                    if ($fromDate || $untilDate) {
                        $from  = $fromDate  ? Carbon::parse($fromDate)->format('d-m-Y')  : '...';
                        $until = $untilDate ? Carbon::parse($untilDate)->format('d-m-Y') : '...';
                        $periodLabel = "Periode: {$from} t/m {$until}";
                    }

                    $pdf = Pdf::loadView('pdf.match-roster', [
                        'matches'     => $matches,
                        'club'        => $tenant,
                        'teamName'    => $teamName,
                        'periodLabel' => $periodLabel,
                    ])
                    ->setPaper('a4', 'landscape');

                    $filename = 'wedstrijd-rooster-' . now()->format('Y-m-d') . '.pdf';

                    return response()->streamDownload(
                        fn() => print($pdf->output()),
                        $filename,
                        ['Content-Type' => 'application/pdf']
                    );
                }),
        ];
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->query(function () use ($user): Builder {
                $query = FootballMatch::query()
                    ->with(['team', 'coaches', 'drivers', 'cleaners', 'fruitHero'])
                    ->orderBy('match_datetime');

                if ($tenant = filament()->getTenant()) {
                    $query->whereHas('team', fn($q) => $q->where('club_id', $tenant->id));
                }

                if (!$user?->isAdmin()) {
                    $query->whereIn('team_id', $user->managedTeamIds());
                }

                return $query;
            })
            ->columns([
                TextColumn::make('match_datetime')
                    ->label('Datum')
                    ->formatStateUsing(fn($state) => $state ? $state->locale('nl')->isoFormat('ddd DD-MM-YYYY') : '-')
                    ->sortable(),
                TextColumn::make('arrival_time')
                    ->label('Aanvang')
                    ->time('H:i'),
                TextColumn::make('team.name')
                    ->label('Elftal')
                    ->sortable()
                    ->searchable(),
                IconColumn::make('is_home')
                    ->label('Thuis')
                    ->boolean()
                    ->trueIcon('heroicon-o-home')
                    ->falseIcon('heroicon-o-arrow-right-circle')
                    ->trueColor('success')
                    ->falseColor('warning'),
                TextColumn::make('opponent')
                    ->label('Tegenstander')
                    ->searchable(),
                TextColumn::make('location')
                    ->label('Accommodatie')
                    ->toggleable(),
                TextColumn::make('dressing_room')
                    ->label('Kleedkamer')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'scheduled'  => 'Gepland',
                        'played'     => 'Gespeeld',
                        'cancelled'  => 'Geannuleerd',
                        'postponed'  => 'Uitgesteld',
                        default      => $state,
                    })
                    ->color(fn($state) => match($state) {
                        'scheduled'  => 'primary',
                        'played'     => 'success',
                        'cancelled'  => 'danger',
                        'postponed'  => 'warning',
                        default      => 'gray',
                    }),
                TextColumn::make('score')
                    ->label('Uitslag')
                    ->getStateUsing(fn($record) => $record->score_home !== null
                        ? $record->score_home . ' - ' . $record->score_away
                        : '-'),
                TextColumn::make('coaches.name')
                    ->label('Coach(es)')
                    ->separator(', ')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('drivers.name')
                    ->label('Rijders')
                    ->separator(', ')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('cleaners.name')
                    ->label('Kleedkamer schoon')
                    ->separator(', ')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fruitHero.name')
                    ->label('Fruitheld')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('team_id')
                    ->label('Elftal')
                    ->options(function () use ($user) {
                        $query = Team::where('is_active', true)->orderBy('name');
                        if (!$user?->isAdmin()) {
                            $query->whereIn('id', $user->managedTeamIds());
                        }
                        return $query->pluck('name', 'id');
                    })
                    ->placeholder('Alle elftallen'),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'scheduled'  => 'Gepland',
                        'played'     => 'Gespeeld',
                        'cancelled'  => 'Geannuleerd',
                        'postponed'  => 'Uitgesteld',
                    ]),
                Filter::make('period')
                    ->form([
                        Forms\Components\Select::make('preset')
                            ->label('Periode')
                            ->options([
                                'this_week'   => 'Deze week',
                                'next_week'   => 'Volgende week',
                                'this_month'  => 'Deze maand',
                                'next_month'  => 'Volgende maand',
                                'custom'      => 'Aangepast',
                            ])
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                match ($state) {
                                    'this_week'  => [$set('from', now()->startOfWeek()->toDateString()), $set('until', now()->endOfWeek()->toDateString())],
                                    'next_week'  => [$set('from', now()->addWeek()->startOfWeek()->toDateString()), $set('until', now()->addWeek()->endOfWeek()->toDateString())],
                                    'this_month' => [$set('from', now()->startOfMonth()->toDateString()), $set('until', now()->endOfMonth()->toDateString())],
                                    'next_month' => [$set('from', now()->addMonth()->startOfMonth()->toDateString()), $set('until', now()->addMonth()->endOfMonth()->toDateString())],
                                    default      => null,
                                };
                            }),
                        Forms\Components\DatePicker::make('from')
                            ->label('Van')
                            ->displayFormat('d-m-Y'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Tot')
                            ->displayFormat('d-m-Y'),
                    ])
                    ->query(fn(Builder $query, array $data) => $query
                        ->when($data['from'],  fn($q) => $q->whereDate('match_datetime', '>=', $data['from']))
                        ->when($data['until'], fn($q) => $q->whereDate('match_datetime', '<=', $data['until'])))
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['from'] && !$data['until']) return null;
                        $from  = isset($data['from'])  ? Carbon::parse($data['from'])->format('d-m-Y')  : '...';
                        $until = isset($data['until']) ? Carbon::parse($data['until'])->format('d-m-Y') : '...';
                        return "Periode: {$from} t/m {$until}";
                    }),
            ])
            ->groups([
                Group::make('match_datetime')
                    ->label('Week')
                    ->getTitleFromRecordUsing(function ($record): string {
                        $dt    = $record->match_datetime;
                        $start = $dt->copy()->startOfWeek()->locale('nl')->isoFormat('D MMM');
                        $end   = $dt->copy()->endOfWeek()->locale('nl')->isoFormat('D MMM YYYY');
                        return "Week {$dt->weekOfYear} ({$start} t/m {$end})";
                    })
                    ->orderQueryUsing(fn($query, $direction) => $query->orderBy('match_datetime', $direction))
                    ->collapsible(),
                Group::make('match_datetime')
                    ->label('Maand')
                    ->getTitleFromRecordUsing(function ($record): string {
                        return ucfirst($record->match_datetime->locale('nl')->isoFormat('MMMM YYYY'));
                    })
                    ->orderQueryUsing(fn($query, $direction) => $query->orderBy('match_datetime', $direction))
                    ->collapsible(),
            ])
            ->defaultGroup('match_datetime')
            ->striped()
            ->paginationPageOptions([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50);
    }
}
