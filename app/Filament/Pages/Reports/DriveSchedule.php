<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Exports\DriveScheduleExport;
use App\Filament\Support\ImportNotifier;
use App\Imports\DriveScheduleImport;
use App\Models\FootballMatch;
use App\Models\Team;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    /** Elftallen waar deze gebruiker bij mag; null = geen beperking (beheerder). */
    private function allowedTeamIds(): ?array
    {
        $user = auth()->user();

        return $user?->isAdmin() ? null : ($user?->managedTeamIds()->all() ?? []);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Excel exporteren')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): BinaryFileResponse {
                    $filters = $this->tableFilters;
                    $teamId  = $filters['team_id']['value'] ?? null;

                    return Excel::download(
                        new DriveScheduleExport(
                            clubId:         filament()->getTenant()?->id,
                            teamId:         $teamId,
                            from:           $filters['period']['from']  ?? null,
                            until:          $filters['period']['until'] ?? null,
                            allowedTeamIds: $this->allowedTeamIds(),
                        ),
                        'rijschema' . DriveScheduleExport::teamSlug($teamId)
                            . '-' . now()->format('Y-m-d') . '.xlsx',
                    );
                }),

            Action::make('import')
                ->label('Importeren')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('Excel bestand (.xlsx)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->disk('local')
                        ->directory('imports')
                        ->required(),
                    Forms\Components\Placeholder::make('format_hint')
                        ->label('Zo werkt het')
                        ->content('Exporteer eerst, vul de rijders in Excel in en zet het bestand terug.')
                        ->helperText(
                            'De export bevat álle uitwedstrijden in je huidige filter, ook die zonder rijder — daar vul je ze dus in. '
                            . 'Laat de ID-kolom staan: daarop wordt de wedstrijd teruggevonden. '
                            . 'Meerdere rijders scheid je met een puntkomma ("Jan Jansen; Piet Pietersen"), en de naam moet exact overeenkomen met het lid. '
                            . 'Een lege Rijders-cel haalt de rijders van die wedstrijd weg; een lege Verzameltijd laat de huidige tijd staan. '
                            . 'Datum, elftal en tegenstander staan er alleen ter herkenning in en worden nooit gewijzigd.'
                        ),
                ])
                ->action(function (array $data): void {
                    $clubId = filament()->getTenant()?->id;
                    if (! $clubId) {
                        Notification::make()->danger()->title('Geen club geselecteerd')->send();

                        return;
                    }

                    // FileUpload levert het pad al mét de map erin; zie ListMembers.
                    $path   = Storage::disk('local')->path($data['file']);
                    $import = new DriveScheduleImport($clubId, $this->allowedTeamIds());

                    Excel::import($import, $path);

                    ImportNotifier::report(
                        $import->imported,
                        $import->created,
                        $import->skipped,
                        $import->errors,
                        'wedstrijden',
                        $import->notices,
                    );

                    Storage::disk('local')->delete($data['file']);
                }),

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
                    // ?? null op elke sleutel — zie MatchRoster: zodra een ánder
                    // filter aanstaat komt dit filter zonder 'from'/'until'
                    // terug, en een ontbrekende sleutel wordt in Laravel een
                    // uitzondering in plaats van een lege waarde.
                    ->query(function (Builder $query, array $data): Builder {
                        $from  = $data['from']  ?? null;
                        $until = $data['until'] ?? null;

                        return $query
                            ->when($from,  fn($q) => $q->whereDate('match_datetime', '>=', $from))
                            ->when($until, fn($q) => $q->whereDate('match_datetime', '<=', $until));
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $from  = $data['from']  ?? null;
                        $until = $data['until'] ?? null;
                        if (! $from && ! $until) {
                            return null;
                        }

                        return 'Periode: '
                            . ($from  ? Carbon::parse($from)->format('d-m-Y')  : '...')
                            . ' t/m '
                            . ($until ? Carbon::parse($until)->format('d-m-Y') : '...');
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
