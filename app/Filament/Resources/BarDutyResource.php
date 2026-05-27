<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\BarDutyResource\Pages;
use App\Models\BarDuty;
use App\Models\Member;
use App\Models\Team;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BarDutyResource extends Resource
{
    protected static ?string $model = BarDuty::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Bardiensten';
    protected static ?string $modelLabel = 'Bardienst';
    protected static ?string $pluralModelLabel = 'Bardiensten';
    protected static string|\UnitEnum|null $navigationGroup = 'Planning';
    protected static ?int $navigationSort = 30;
    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'bar_commissie', 'coach']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'bar_commissie']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'bar_commissie']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'bar_commissie']) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query  = parent::getEloquentQuery();
        $user   = auth()->user();
        $tenant = filament()->getTenant();

        if ($tenant) {
            $query->where('club_id', $tenant->id);
        }

        if (!$user?->isAdmin() && !$user?->hasRole('bar_commissie')) {
            $query->whereIn('team_id', $user->managedTeamIds());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Bardienst')->schema([
                Forms\Components\DatePicker::make('date')
                    ->label('Datum')
                    ->displayFormat('d-m-Y')
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
                    ->options(function (): array {
                        $tenant = filament()->getTenant();
                        $query  = Team::where('is_active', true)->orderBy('name');
                        if ($tenant) {
                            $query->where('club_id', $tenant->id);
                        }
                        return $query->pluck('name', 'id')->all();
                    })
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
                Forms\Components\Select::make('members')
                    ->label('Ingeplande leden (max. 2)')
                    ->multiple()
                    ->maxItems(2)
                    ->relationship(
                        name: 'members',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn(Builder $query, Get $get) => $query
                            ->when(
                                $get('team_id'),
                                fn($q, $teamId) => $q->whereHas('teams', fn($t) => $t->where('teams.id', $teamId)),
                                fn($q) => $q->whereRaw('1=0'),
                            )
                            ->where('is_active', true)
                            ->orderBy('name'),
                    )
                    ->helperText('Selecteer eerst een elftal, dan maximaal 2 leden')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('notes')
                    ->label('Opmerkingen')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Datum')
                    ->formatStateUsing(fn($state) => $state?->locale('nl')->isoFormat('ddd DD-MM-YYYY'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('shift')
                    ->label('Dienst')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'ochtend' => 'Ochtend',
                        'middag'  => 'Middag',
                        'avond'   => 'Avond',
                        default   => $state,
                    })
                    ->color(fn($state) => match($state) {
                        'ochtend' => 'info',
                        'middag'  => 'warning',
                        'avond'   => 'primary',
                        default   => 'gray',
                    }),
                Tables\Columns\TextColumn::make('team.name')
                    ->label('Elftal')
                    ->sortable(),
                Tables\Columns\TextColumn::make('members.name')
                    ->label('Ingepland')
                    ->separator(', ')
                    ->default('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'open'      => 'Open',
                        'bevestigd' => 'Bevestigd',
                        'vervuld'   => 'Vervuld',
                        default     => $state,
                    })
                    ->color(fn($state) => match($state) {
                        'open'      => 'warning',
                        'bevestigd' => 'info',
                        'vervuld'   => 'success',
                        default     => 'gray',
                    }),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Opmerkingen')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('team_id')
                    ->label('Elftal')
                    ->options(function (): array {
                        $tenant = filament()->getTenant();
                        $query  = Team::where('is_active', true)->orderBy('name');
                        if ($tenant) {
                            $query->where('club_id', $tenant->id);
                        }
                        return $query->pluck('name', 'id')->all();
                    })
                    ->placeholder('Alle elftallen'),
                SelectFilter::make('shift')
                    ->label('Dienst')
                    ->options([
                        'ochtend' => 'Ochtend',
                        'middag'  => 'Middag',
                        'avond'   => 'Avond',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'open'      => 'Open',
                        'bevestigd' => 'Bevestigd',
                        'vervuld'   => 'Vervuld',
                    ]),
                Filter::make('period')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Van')->displayFormat('d-m-Y'),
                        Forms\Components\DatePicker::make('until')->label('Tot')->displayFormat('d-m-Y'),
                    ])
                    ->query(fn(Builder $query, array $data) => $query
                        ->when($data['from'],  fn($q) => $q->whereDate('date', '>=', $data['from']))
                        ->when($data['until'], fn($q) => $q->whereDate('date', '<=', $data['until'])))
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['from'] && !$data['until']) return null;
                        $from  = $data['from']  ? \Carbon\Carbon::parse($data['from'])->format('d-m-Y')  : '...';
                        $until = $data['until'] ? \Carbon\Carbon::parse($data['until'])->format('d-m-Y') : '...';
                        return "Periode: {$from} t/m {$until}";
                    }),
            ])
            ->groups([
                Group::make('date')
                    ->label('Week')
                    ->getTitleFromRecordUsing(function (BarDuty $record): string {
                        $dt    = $record->date;
                        $start = $dt->copy()->startOfWeek()->locale('nl')->isoFormat('D MMM');
                        $end   = $dt->copy()->endOfWeek()->locale('nl')->isoFormat('D MMM YYYY');
                        return "Week {$dt->weekOfYear} ({$start} t/m {$end})";
                    })
                    ->orderQueryUsing(fn($query, $direction) => $query->orderBy('date', $direction))
                    ->collapsible(),
                Group::make('team.name')
                    ->label('Elftal')
                    ->collapsible(),
            ])
            ->defaultGroup('date')
            ->defaultSort('date')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBarDuties::route('/'),
            'create' => Pages\CreateBarDuty::route('/create'),
            'edit'   => Pages\EditBarDuty::route('/{record}/edit'),
        ];
    }
}
