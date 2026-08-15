<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MatchResource\Pages;
use App\Filament\Support\TeamFilter;
use App\Models\FootballMatch;
use App\Models\Member;
use Filament\Actions;
use Filament\Forms;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MatchResource extends Resource
{
    protected static ?string $model = FootballMatch::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Wedstrijden';
    protected static ?string $modelLabel = 'Wedstrijd';
    protected static ?string $pluralModelLabel = 'Wedstrijden';
    protected static ?int $navigationSort = 3;
    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Wedstrijdgegevens')->schema([
                Forms\Components\Select::make('team_id')
                    ->label('Team')
                    // Alleen elftallen van de eigen club (en, voor niet-beheerders,
                    // de eigen elftallen); zonder scope stonden hier de teams van
                    // álle clubs in de lijst.
                    ->relationship('team', 'name', modifyQueryUsing: fn (Builder $query) => TeamFilter::scopeQuery($query))
                    ->required()
                    ->live()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('opponent')
                    ->label('Tegenstander')
                    ->required()
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('match_datetime')
                    ->label('Datum en tijd')
                    ->seconds(false)
                    ->displayFormat('d-m-Y H:i')
                    ->required(),
                Forms\Components\TextInput::make('location')
                    ->label('Locatie')
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_home')
                    ->label('Thuiswedstrijd')
                    ->default(true),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'scheduled' => 'Gepland',
                        'played' => 'Gespeeld',
                        'cancelled' => 'Geannuleerd',
                        'postponed' => 'Uitgesteld',
                    ])
                    ->default('scheduled')
                    ->required(),
            ])->columns(2),
            Section::make('Extra informatie')->schema([
                Forms\Components\TimePicker::make('arrival_time')
                    ->label('Aanwezig tijd')
                    ->seconds(false)
                    ->format('H:i')
                    ->displayFormat('H:i'),
                Forms\Components\TextInput::make('dressing_room')
                    ->label('Kleedkamer'),
                Forms\Components\Select::make('coaches')
                    ->label('Coach(es)')
                    ->multiple()
                    ->relationship(
                        name: 'coaches',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn(Builder $query, Get $get) => $query
                            ->whereIn('role', ['coach', 'staff'])
                            ->where('is_active', true)
                            ->when($get('team_id'), fn($q, $teamId) =>
                                $q->whereHas('teams', fn($t) => $t->where('teams.id', $teamId))
                            )
                            ->orderBy('name')
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Selecteer coach(es)...'),
                Forms\Components\Select::make('cleaners')
                    ->label('Wie maakt kleedkamer schoon?')
                    ->multiple()
                    ->relationship(
                        name: 'cleaners',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn(Builder $query, Get $get) => $query
                            ->where('is_active', true)
                            ->when($get('team_id'), fn($q, $teamId) =>
                                $q->whereHas('teams', fn($t) => $t->where('teams.id', $teamId))
                            )
                            ->orderBy('name')
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Selecteer schoonmakers...'),
                Forms\Components\Select::make('fruit_hero_id')
                    ->label('Fruitheld')
                    ->options(function (Get $get): array {
                        $teamId = $get('team_id');
                        $query = Member::where('role', 'player')->where('is_active', true)->orderBy('name');
                        if ($teamId) {
                            $query->whereHas('teams', fn($q) => $q->where('teams.id', $teamId));
                        }
                        return $query->pluck('name', 'id')->toArray();
                    })
                    ->searchable()
                    ->nullable()
                    ->placeholder('Selecteer fruitheld...'),
                Forms\Components\Select::make('vlagger_id')
                    ->label('Vlagger')
                    ->options(function (Get $get): array {
                        $teamId = $get('team_id');
                        $query = Member::where('is_active', true)->orderBy('name');
                        if ($teamId) {
                            $query->whereHas('teams', fn($q) => $q->where('teams.id', $teamId));
                        }
                        return $query->pluck('name', 'id')->toArray();
                    })
                    ->searchable()
                    ->nullable()
                    ->placeholder('Selecteer vlagger...'),
                Forms\Components\Select::make('drivers')
                    ->label('Rijders (uitwedstrijd)')
                    ->multiple()
                    ->relationship(
                        name: 'drivers',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn(Builder $query, Get $get) => $query
                            ->where('is_active', true)
                            ->when($get('team_id'), fn($q, $teamId) =>
                                $q->whereHas('teams', fn($t) => $t->where('teams.id', $teamId))
                            )
                            ->orderBy('name')
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Selecteer rijders...')
                    ->visible(fn(Get $get): bool => !(bool) $get('is_home')),
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
                Tables\Columns\TextColumn::make('team.name')->label('Team')->sortable()->searchable(),
                Tables\Columns\ImageColumn::make('opponent_logo')
                    ->label('Logo')
                    ->circular()
                    ->size(32)
                    ->extraImgAttributes(['loading' => 'lazy'])
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('opponent')->label('Tegenstander')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('match_datetime')
                    ->label('Datum')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('location')->label('Locatie'),
                Tables\Columns\IconColumn::make('is_home')->label('Thuis')->boolean(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'scheduled' => 'Gepland',
                        'played' => 'Gespeeld',
                        'cancelled' => 'Geannuleerd',
                        'postponed' => 'Uitgesteld',
                        default => $state,
                    })
                    ->color(fn($state) => match($state) {
                        'scheduled' => 'primary',
                        'played' => 'success',
                        'cancelled' => 'danger',
                        'postponed' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('vlagger.name')
                    ->label('Vlagger')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            // Filters staan boven de tabel (niet in het dropdown-menu) zodat het
            // team-filter altijd in beeld is; de keuze blijft per sessie staan.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
            ->filters([
                Tables\Filters\SelectFilter::make('team')
                    ->label('Team')
                    ->relationship('team', 'name', modifyQueryUsing: fn (Builder $query) => TeamFilter::scopeQuery($query))
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->placeholder('Alle teams'),
                // Standaard (blanco keuze) verbergt wedstrijden ouder dan een week.
                Tables\Filters\TernaryFilter::make('periode')
                    ->label('Periode')
                    ->placeholder('Vanaf 1 week geleden')
                    ->trueLabel('Alle wedstrijden')
                    ->falseLabel('Alleen ouder dan 1 week')
                    ->queries(
                        true:  fn (Builder $query) => $query,
                        false: fn (Builder $query) => $query->where('match_datetime', '<', now()->subWeek()),
                        blank: fn (Builder $query) => $query->where('match_datetime', '>=', now()->subWeek()),
                    ),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'scheduled' => 'Gepland',
                        'played' => 'Gespeeld',
                        'cancelled' => 'Geannuleerd',
                        'postponed' => 'Uitgesteld',
                    ]),
            ])
            ->defaultSort('match_datetime')
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query  = parent::getEloquentQuery();
        $user   = auth()->user();
        $tenant = filament()->getTenant();

        if ($tenant) {
            $query->whereHas('team', fn($q) => $q->where('club_id', $tenant->id));
        }

        if (!$user || $user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('team_id', $user->managedTeamIds());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMatches::route('/'),
            'create' => Pages\CreateMatch::route('/create'),
            'edit' => Pages\EditMatch::route('/{record}/edit'),
        ];
    }
}
