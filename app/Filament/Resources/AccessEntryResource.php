<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AccessEntryResource\Pages;
use App\Models\AccessEntry;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Wie er binnen is, per activiteit.
 *
 * Alleen lezen. Voor codes staat de teller al op de code zelf, maar leden die
 * met hun eigen lidnummer binnenkomen hebben helemaal geen coderegel - zonder
 * dit overzicht zou je van hen alleen een getal zien en geen namen.
 */
class AccessEntryResource extends Resource
{
    protected static ?string $model = AccessEntry::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';
    protected static ?string $navigationLabel = 'Binnenkomsten';
    protected static ?string $modelLabel = 'binnenkomst';
    protected static ?string $pluralModelLabel = 'Binnenkomsten';
    protected static string|\UnitEnum|null $navigationGroup = 'Beheer';
    protected static ?int $navigationSort = 10;
    protected static bool $isScopedToTenant = false;

    /** @var array<int, string> */
    private const ROLLEN = ['super_admin', 'club_admin', 'toegang'];

    public static function canViewAny(): bool
    {
        return (auth()->user()?->hasAnyRole(self::ROLLEN) ?? false)
            && AccessCodeResource::moduleAan();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * Verwijderen mag: dan kan het lid opnieuw naar binnen.
     *
     * Dat is de enige manier om een verkeerde scan terug te draaien - bij een
     * code zet je de teller terug, bij een lid haal je de binnenkomst weg.
     */
    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(self::ROLLEN) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query  = parent::getEloquentQuery();
        $user   = auth()->user();
        $tenant = filament()->getTenant();

        // Op de club van de activiteit: access_entries heeft zelf geen club_id,
        // want de activiteit bepaalt al bij wie het hoort.
        if ($tenant) {
            $query->whereHas('agendaItem', fn (Builder $q) => $q->where('club_id', $tenant->id));
        } elseif (! $user?->hasRole('super_admin') && $user?->club_id) {
            $query->whereHas('agendaItem', fn (Builder $q) => $q->where('club_id', $user->club_id));
        }

        return $query->with(['agendaItem', 'accessCode', 'member', 'user']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('agendaItem.title')
                    ->label('Activiteit')
                    ->description(fn (AccessEntry $record): string => $record->agendaItem?->starts_at?->format('d-m-Y H:i') ?? '')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('entered_at')
                    ->label('Binnen om')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('wie')
                    ->label('Wie')
                    ->getStateUsing(function (AccessEntry $record): string {
                        if ($record->member) {
                            return $record->member->name;
                        }

                        return $record->accessCode?->label
                            ?: ($record->accessCode?->code ?? '—');
                    })
                    ->description(fn (AccessEntry $record): string => $record->member ? 'Lidnummer' : 'Toegangscode')
                    ->searchable(false),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Gescand door')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('entered_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('agenda_item_id')
                    ->label('Activiteit')
                    ->options(fn () => AccessCodeResource::agendaOpties()),

                Tables\Filters\Filter::make('leden')
                    ->label('Alleen op lidnummer')
                    ->query(fn (Builder $q) => $q->whereNotNull('member_id')),
            ])
            ->actions([
                Actions\DeleteAction::make()
                    ->label('Terugdraaien')
                    ->modalHeading('Binnenkomst terugdraaien')
                    ->modalDescription('Het lid of de code kan hierna opnieuw naar binnen. Bij een toegangscode zet dit de teller niet terug; dat doe je bij Toegangscodes.'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccessEntries::route('/'),
        ];
    }
}
