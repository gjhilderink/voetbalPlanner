<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\RoomResource\Pages;
use App\Models\Room;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * De ruimtes van de club: kantine, bestuurskamer, kleedkamers.
 *
 * Het beheer zit hier; het inplannen gebeurt in de Ruimteplanner. Dat is met
 * opzet gescheiden: een ruimte maak je één keer aan en reserveer je honderd
 * keer.
 */
class RoomResource extends Resource
{
    protected static ?string $model = Room::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Ruimtes';
    protected static ?string $modelLabel = 'ruimte';
    protected static ?string $pluralModelLabel = 'Ruimtes';
    protected static string|\UnitEnum|null $navigationGroup = 'Beheer';
    protected static ?int $navigationSort = 12;
    protected static bool $isScopedToTenant = false;

    /** @var array<int, string> */
    public const ROLLEN = ['super_admin', 'club_admin', 'room-planning'];

    public static function getEloquentQuery(): Builder
    {
        $tenant = filament()->getTenant();
        $user   = auth()->user();

        return parent::getEloquentQuery()
            ->when($tenant, fn (Builder $q) => $q->where('club_id', $tenant->id))
            ->when(
                ! $tenant && ! $user?->hasRole('super_admin') && $user?->club_id,
                fn (Builder $q) => $q->where('club_id', $user->club_id),
            );
    }

    /**
     * Staat de module aan bij deze club?
     *
     * Ook gebruikt door de Ruimteplanner en de koppeling bij een agenda-item,
     * zodat de regel op één plek staat. Een super-admin zonder gekozen club
     * kijkt clubbreed en ziet alles.
     */
    public static function moduleAan(): bool
    {
        $club = filament()->getTenant();
        if ($club) {
            return (bool) $club->rooms_enabled;
        }

        $user = auth()->user();
        if ($user?->hasRole('super_admin')) {
            return true;
        }

        return (bool) $user?->club?->rooms_enabled;
    }

    public static function canViewAny(): bool
    {
        return (auth()->user()?->hasAnyRole(self::ROLLEN) ?? false) && static::moduleAan();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('De ruimte')
                ->schema([
                    Forms\Components\Hidden::make('club_id')
                        ->default(fn () => filament()->getTenant()?->id ?? auth()->user()?->club_id),

                    Forms\Components\TextInput::make('name')
                        ->label('Naam')
                        ->placeholder('Kantine')
                        ->required()
                        ->maxLength(120),

                    Forms\Components\TextInput::make('capacity')
                        ->label('Aantal personen')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(2000)
                        ->helperText('Optioneel. Wordt getoond bij het reserveren.'),

                    Forms\Components\Textarea::make('description')
                        ->label('Toelichting')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),

                    Forms\Components\ColorPicker::make('color')
                        ->label('Kleur in het rooster')
                        ->helperText('Met een paar ruimtes naast elkaar is kleur sneller dan lezen.'),

                    Forms\Components\TextInput::make('sort_order')
                        ->label('Volgorde')
                        ->numeric()
                        ->default(0)
                        ->helperText('Laag staat bovenaan in de planner.'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('In gebruik')
                        ->default(true)
                        ->helperText('Uit = niet meer te reserveren. Bestaande reserveringen blijven staan.')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            // Deze sectie doet in fase 1 nog niets: het veld wordt bewaard, maar
            // er wordt nog niets mee gesynchroniseerd. Het staat er alvast omdat
            // de koppeling per ruimte hoort en niet per club.
            Section::make('Microsoft 365')
                ->description('De postbus van deze ruimte. Laat leeg als de ruimte niet in Outlook staat.')
                ->schema([
                    Forms\Components\TextInput::make('ms_room_email')
                        ->label('Postbus van de ruimte')
                        ->placeholder('kantine@jouwclub.nl')
                        ->email()
                        ->maxLength(191)
                        ->helperText('Het e-mailadres van de resource-postbus in Microsoft 365.')
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('color')
                    ->label('')
                    ->width(40),

                Tables\Columns\TextColumn::make('name')
                    ->label('Ruimte')
                    ->description(fn (Room $record): ?string => $record->description)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('capacity')
                    ->label('Personen')
                    ->placeholder('—')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('reservations_count')
                    ->label('Reserveringen')
                    ->counts('reservations')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('ms_room_email')
                    ->label('Microsoft')
                    ->placeholder('Niet gekoppeld')
                    ->badge()
                    ->color(fn (?string $state): string => $state ? 'success' : 'gray')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('In gebruik')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('In gebruik')
                    ->default(true),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Nog geen ruimtes')
            ->emptyStateDescription('Voeg de kantine, de bestuurskamer of een kleedkamer toe; daarna kun je ze in de planner reserveren.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit'   => Pages\EditRoom::route('/{record}/edit'),
        ];
    }
}
