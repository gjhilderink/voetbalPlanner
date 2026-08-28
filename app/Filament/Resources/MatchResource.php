<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MatchResource\Pages;
use App\Filament\Support\TeamFilter;
use App\Models\FootballMatch;
use App\Models\Member;
use App\Models\Team;
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
                        // Bij een gekozen elftal: iedereen die daar als staf bij
                        // hoort, ongeacht wáár dat is vastgelegd. Zonder elftal
                        // valt hij terug op de clubfunctie, want dan is er niets
                        // om op te filteren.
                        //
                        // Wie er nú op de wedstrijd staat blijft er altijd bij,
                        // ook als hij buiten dat filter valt. Zonder dat toont
                        // het veld hem niet - en haalt opslaan hem er stilzwijgend
                        // af, want een leeg keuzeveld leest als "niemand".
                        modifyQueryUsing: fn(Builder $query, Get $get, ?FootballMatch $record) => $query
                            ->where(function (Builder $q) use ($get, $record) {
                                $q->where(fn(Builder $x) => $x
                                    ->where('is_active', true)
                                    ->when(
                                        $get('team_id'),
                                        fn($y, $teamId) => $y->whereIn(
                                            'members.id',
                                            Team::find($teamId)?->staffMemberIds() ?? [],
                                        ),
                                        fn($y) => $y->whereIn('members.role', ['coach', 'staff']),
                                    ));

                                $huidig = $record?->coaches->pluck('id')->all() ?? [];
                                if ($huidig) {
                                    $q->orWhereIn('members.id', $huidig);
                                }
                            })
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

            // Het live verslag uit de app, alleen om te lezen: vastleggen en
            // verwijderen gebeurt daar. Staat er niets, dan ook geen lege sectie
            // — bij de meeste wedstrijden is er nooit een verslag bijgehouden.
            Section::make('Verslag')
                ->description('Het live verslag zoals de coach het in de app heeft vastgelegd.')
                ->icon('heroicon-o-list-bullet')
                ->collapsible()
                ->visible(fn (?FootballMatch $record): bool => $record?->events()->exists() ?? false)
                ->schema([
                    Forms\Components\Placeholder::make('verslag')
                        ->hiddenLabel()
                        ->content(fn (FootballMatch $record) => view('filament.matches.report', [
                            'match' => $record,
                            // match meeladen: label() leest de tegenstander
                            // daaruit, en zonder dit doet elke regel een eigen
                            // query.
                            'events' => $record->events()
                                ->with(['member', 'relatedMember', 'match'])
                                ->orderByDesc('created_at')
                                ->get(),
                        ]))
                        ->columnSpanFull(),
                ]),
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
