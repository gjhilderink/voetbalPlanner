<?php

declare(strict_types=1);

namespace App\Filament\Resources\AgendaItemResource\RelationManagers;

use App\Models\TicketType;
use App\Support\Geld;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * De kaartsoorten die voor deze activiteit te koop zijn.
 *
 * Bij het agenda-item en niet als eigen menu-item: een kaartsoort bestaat
 * alleen in de context van één activiteit, en je stelt hem in op het moment dat
 * je die activiteit aan het klaarzetten bent.
 */
class TicketTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'ticketTypes';

    protected static ?string $title = 'Kaartsoorten';

    /** Alleen tonen als de club de ticketshop gebruikt. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) $ownerRecord->club?->ticketshop_enabled;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Kaartsoort')
                    ->description(fn (TicketType $record): ?string => $record->description)
                    ->searchable(),

                Tables\Columns\TextColumn::make('price_cents')
                    ->label('Prijs')
                    ->formatStateUsing(fn (int $state): string => Geld::euro($state))
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('voorraad')
                    ->label('Nog beschikbaar')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (TicketType $record): string => match (true) {
                        $record->stock === null   => 'gray',
                        $record->isUitverkocht()  => 'danger',
                        default                   => 'success',
                    })
                    ->getStateUsing(function (TicketType $record): string {
                        if ($record->stock === null) {
                            return 'onbeperkt';
                        }

                        return $record->beschikbaar() . ' van ' . $record->stock;
                    }),

                Tables\Columns\TextColumn::make('max_per_order')
                    ->label('Max. per bestelling')
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Te koop')
                    ->boolean()
                    ->alignCenter()
                    ->width(70),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Kaartsoort toevoegen')
                    ->form(fn (): array => static::velden())
                    ->mutateFormDataUsing(function (array $data): array {
                        // club_id komt van de activiteit: de kaartsoort hoort
                        // per definitie bij dezelfde club.
                        $data['club_id'] = $this->getOwnerRecord()->club_id;

                        return $data;
                    }),
            ])
            ->actions([
                Actions\EditAction::make()->form(fn (): array => static::velden()),
                Actions\DeleteAction::make()
                    // Verkocht is verkocht: de bestelregels bewaren naam en
                    // prijs zelf, maar de voorraadberekening kan er niet meer
                    // bij als de soort weg is.
                    ->disabled(fn (TicketType $record): bool => $record->verkocht() > 0)
                    ->tooltip(fn (TicketType $record): ?string => $record->verkocht() > 0
                        ? 'Er zijn al kaarten van deze soort verkocht. Zet hem op niet te koop in plaats van hem te verwijderen.'
                        : null),
            ])
            ->emptyStateHeading('Nog geen kaartsoorten')
            ->emptyStateDescription('Zolang er geen kaartsoort is, staat deze activiteit niet in de ticketshop.');
    }

    /** @return array<int, mixed> */
    protected static function velden(): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->label('Naam')
                ->placeholder('Volwassene')
                ->required()
                ->maxLength(120),

            Forms\Components\TextInput::make('description')
                ->label('Toelichting')
                ->placeholder('Inclusief een consumptie')
                ->maxLength(300)
                ->helperText('Optioneel. Staat onder de naam in de winkel.'),

            // Euro's tonen, centen bewaren. formatStateUsing en
            // dehydrateStateUsing zijn precies het paar daarvoor, en het werkt
            // zowel bij toevoegen als bij bewerken.
            Forms\Components\TextInput::make('price_cents')
                ->label('Prijs')
                ->prefix('€')
                ->required()
                ->helperText('Bijvoorbeeld 7,50. Nul mag ook — dan is de kaart gratis maar wel te reserveren.')
                ->formatStateUsing(fn (?int $state): string => number_format(($state ?? 0) / 100, 2, ',', ''))
                ->dehydrateStateUsing(fn (?string $state): int => Geld::naarCenten($state))
                ->rule('regex:/^\s*€?\s*\d{1,5}([.,]\d{1,2})?\s*$/'),

            Forms\Components\TextInput::make('stock')
                ->label('Voorraad')
                ->numeric()
                ->minValue(0)
                ->maxValue(65535)
                ->helperText('Leeg laten = onbeperkt. Nul betekent uitverkocht.'),

            Forms\Components\TextInput::make('max_per_order')
                ->label('Maximaal per bestelling')
                ->numeric()
                ->minValue(1)
                ->maxValue(50)
                ->default(10)
                ->required(),

            Forms\Components\Toggle::make('is_active')
                ->label('Te koop')
                ->default(true)
                ->helperText('Uit = niet zichtbaar in de winkel. Al verkochte kaarten blijven gewoon geldig.'),
        ];
    }
}
