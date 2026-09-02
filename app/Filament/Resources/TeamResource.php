<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Models\Team;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Teams';
    protected static ?string $modelLabel = 'Team';
    protected static ?string $pluralModelLabel = 'Teams';
    protected static ?int $navigationSort = 1;
    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('category')
                    ->label('Categorie')
                    ->maxLength(100),
                Forms\Components\TextInput::make('age_group')
                    ->label('Leeftijdscategorie')
                    ->placeholder('JO12, MO15, Senioren')
                    ->maxLength(50),
                Forms\Components\TextInput::make('match_day')
                    ->label('Speeldag')
                    ->placeholder('Zaterdag')
                    ->maxLength(30),
                Forms\Components\TextInput::make('gender')
                    ->label('Geslacht')
                    ->placeholder('Jongens, Meisjes, Gemengd')
                    ->maxLength(30),
                Forms\Components\TextInput::make('season')
                    ->label('Seizoen')
                    ->maxLength(20),
                Forms\Components\Toggle::make('is_active')
                    ->label('Actief')
                    ->default(true),
                Forms\Components\Toggle::make('is_first_team')
                    ->label('Eerste elftal')
                    ->helperText('Toont de wedstrijd van dit team bovenaan de bardienst-planner.')
                    ->default(false),
                Forms\Components\TextInput::make('external_id')
                    ->label('Extern ID')
                    ->disabled(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category')->label('Categorie')->sortable(),
                Tables\Columns\TextColumn::make('age_group')
                    ->label('Leeftijdscategorie')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('match_day')
                    ->label('Speeldag')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('gender')
                    ->label('Geslacht')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('season')
                    ->label('Seizoen')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')->label('Actief')->boolean(),
                Tables\Columns\IconColumn::make('is_first_team')->label('1e elftal')->boolean(),
                Tables\Columns\TextColumn::make('members_count')
                    ->label('Leden')
                    ->counts('members')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'gray')
                    ->sortable(),
                // Naast het aantal leden, want dat bepaalt of een leeg elftal
                // echt leeg is. Nul leden en toch wedstrijden betekent dat er
                // iets aan hangt wat je kwijtraakt als je hem weggooit.
                Tables\Columns\TextColumn::make('matches_count')
                    ->label('Wedstrijden')
                    ->counts('matches')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Laatste sync')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([
                // De keuzes komen uit wat er werkelijk in de database staat en
                // niet uit een vaste lijst. Sportlink bepaalt zelf hoe het een
                // leeftijdscategorie of speeldag noemt, en een lijst die daar
                // naast zit levert een filter op dat niets vindt.
                Tables\Filters\SelectFilter::make('age_group')
                    ->label('Leeftijdscategorie')
                    ->options(fn (): array => static::keuzesVoor('age_group'))
                    ->multiple(),
                Tables\Filters\SelectFilter::make('match_day')
                    ->label('Speeldag')
                    ->options(fn (): array => static::keuzesVoor('match_day'))
                    ->multiple(),
                Tables\Filters\SelectFilter::make('gender')
                    ->label('Geslacht')
                    ->options(fn (): array => static::keuzesVoor('gender'))
                    ->multiple(),
                Tables\Filters\SelectFilter::make('category')
                    ->label('Competitiesoort')
                    ->options(fn (): array => static::keuzesVoor('category'))
                    ->multiple(),
                // Om de elftallen te vinden die uit een oude of dubbele
                // synchronisatie zijn blijven staan. Verwijderen gaat met de
                // gewone knop: een elftal wordt zacht verwijderd, dus het is
                // terug te halen - maar de synchronisatie zet het bewust niet
                // terug, want dan stond het er de volgende ochtend weer.
                Tables\Filters\Filter::make('zonder_leden')
                    ->label('Zonder leden')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->doesntHave('members')),
                Tables\Filters\Filter::make('zonder_wedstrijden')
                    ->label('Zonder wedstrijden')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->doesntHave('matches')),
                Tables\Filters\TernaryFilter::make('is_active')->label('Actief'),
            ])
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

    /**
     * De waarden die in deze kolom voorkomen, als filterkeuzes.
     *
     * Alleen binnen de eigen club: een beheerder hoort in zijn filter niet de
     * leeftijdscategorieen van een andere vereniging te zien staan.
     *
     * @return array<string, string>
     */
    protected static function keuzesVoor(string $kolom): array
    {
        return Team::query()
            ->when(
                filament()->getTenant(),
                fn (Builder $q, $club) => $q->where('club_id', $club->id),
            )
            ->whereNotNull($kolom)
            ->where($kolom, '!=', '')
            ->distinct()
            ->orderBy($kolom)
            ->pluck($kolom, $kolom)
            ->all();
    }

    public static function getEloquentQuery(): Builder
    {
        $query  = parent::getEloquentQuery();
        $user   = auth()->user();
        $tenant = filament()->getTenant();

        if ($tenant) {
            $query->where('club_id', $tenant->id);
        }

        if (!$user || $user->isAdmin()) {
            return $query;
        }

        return $query->whereIn('id', $user->managedTeamIds());
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
        ];
    }
}
