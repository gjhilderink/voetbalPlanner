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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DriveSchedule extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel                = 'Rijschema per elftal';
    protected static ?string $title                          = 'Rijschema per elftal';
    protected static string|\UnitEnum|null $navigationGroup  = 'Rapportage';
    protected static ?int $navigationSort                    = 20;

    protected string $view = 'filament.pages.reports.drive-schedule';

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
                    $fromDate  = $filters['period']['from'] ?? null;
                    $untilDate = $filters['period']['until'] ?? null;

                    $query = FootballMatch::query()
                        ->with(['team', 'drivers', 'coaches'])
                        ->where('is_home', false)
                        ->whereHas('drivers')
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

                    $pdf = Pdf::loadView('pdf.drive-schedule', [
                        'matches'     => $matches,
                        'club'        => $tenant,
                        'teamName'    => $teamName,
                        'periodLabel' => $periodLabel,
                    ])
                    ->setPaper('a4', 'landscape');

                    $filename = 'rijschema-' . now()->format('Y-m-d') . '.pdf';

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
                    ->with(['team', 'drivers', 'coaches'])
                    ->where('is_home', false)
                    ->whereHas('drivers')
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
                    ->label('Datum & aanvang')
                    ->formatStateUsing(fn($state) => $state ? $state->locale('nl')->isoFormat('ddd DD-MM-YYYY HH:mm') : '-')
                    ->sortable(),
                TextColumn::make('team.name')
                    ->label('Elftal')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('opponent')
                    ->label('Tegenstander')
                    ->searchable(),
                TextColumn::make('location')
                    ->label('Accommodatie'),
                TextColumn::make('arrival_time')
                    ->label('Verzameltijd')
                    ->time('H:i'),
                TextColumn::make('drivers.name')
                    ->label('Rijders')
                    ->separator(' | ')
                    ->wrap(),
                TextColumn::make('coaches.name')
                    ->label('Coach(es)')
                    ->separator(', '),
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
                Filter::make('period')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Van')->displayFormat('d-m-Y'),
                        Forms\Components\DatePicker::make('until')->label('Tot')->displayFormat('d-m-Y'),
                    ])
                    ->query(fn(Builder $query, array $data) => $query
                        ->when($data['from'],  fn($q) => $q->whereDate('match_datetime', '>=', $data['from']))
                        ->when($data['until'], fn($q) => $q->whereDate('match_datetime', '<=', $data['until'])))
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['from'] && !$data['until']) return null;
                        $from  = $data['from']  ? Carbon::parse($data['from'])->format('d-m-Y')  : '...';
                        $until = $data['until'] ? Carbon::parse($data['until'])->format('d-m-Y') : '...';
                        return "Periode: {$from} t/m {$until}";
                    }),
            ])
            ->groups([
                \Filament\Tables\Grouping\Group::make('team.name')
                    ->label('Elftal')
                    ->collapsible(),
            ])
            ->defaultGroup('team.name')
            ->striped()
            ->paginated(false);
    }
}
