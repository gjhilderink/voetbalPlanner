<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\FootballMatch;
use App\Models\Team;
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

    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-truck';
    protected static ?string $navigationLabel                  = 'Rijschema per elftal';
    protected static ?string $title                            = 'Rijschema per elftal';
    protected static string|\UnitEnum|null $navigationGroup    = 'Rapportage';
    protected static ?int    $navigationSort                   = 20;

    protected string $view = 'filament.pages.reports.drive-schedule';

    public static function canAccess(): bool
    {
        return auth()->check();
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

                if (!$user?->isAdmin()) {
                    $query->whereIn('team_id', $user->managedTeamIds());
                }

                return $query;
            })
            ->columns([
                TextColumn::make('match_datetime')
                    ->label('Datum')
                    ->dateTime('D d-m-Y')
                    ->sortable(),
                TextColumn::make('match_datetime')
                    ->label('Tijd')
                    ->dateTime('H:i')
                    ->name('aanvangstijd'),
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
                        return 'Periode: ' . ($data['from'] ? \Carbon\Carbon::parse($data['from'])->format('d-m-Y') : '...') . ' t/m ' . ($data['until'] ? \Carbon\Carbon::parse($data['until'])->format('d-m-Y') : '...');
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
