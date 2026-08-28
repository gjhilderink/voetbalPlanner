<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\SyncLogResource\Pages;
use App\Models\SyncLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * De synchronisaties, alleen om naar te kijken.
 *
 * Elke ronde schrijft per onderdeel een regel: teams, leden en wedstrijden. Zo
 * is te zien of de automatische ronde van 06:00 en 18:00 heeft gelopen, hoeveel
 * er is bijgewerkt, en waar het misging als er iets misging.
 */
class SyncLogResource extends Resource
{
    protected static ?string $model = SyncLog::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Sync-monitor';
    protected static ?string $modelLabel = 'synchronisatie';
    protected static ?string $pluralModelLabel = 'Synchronisaties';
    protected static ?int $navigationSort = 11;
    // Zelf filteren in plaats van via Filaments tenant-koppeling: die vraagt om
    // een relatie op Club, en zou de oudere regels zonder club_id (van vóór de
    // synchronisatie per club) helemaal wegfilteren.
    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $tenant = filament()->getTenant();

        return parent::getEloquentQuery()->when($tenant, fn ($q) => $q->where(
            fn ($x) => $x->where('club_id', $tenant->id)->orWhereNull('club_id'),
        ));
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    /**
     * Rood cijfer in het menu zodra er in het afgelopen etmaal iets is
     * misgegaan. Een monitor die je moet openen om te zien dat er iets stuk is,
     * wordt niet geopend.
     */
    public static function getNavigationBadge(): ?string
    {
        $aantal = static::getEloquentQuery()
            ->where('status', '!=', 'completed')
            ->where('started_at', '>=', now()->subDay())
            ->count();

        return $aantal > 0 ? (string) $aantal : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('started_at')
                    ->label('Gestart')
                    ->dateTime('d-m-Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Onderdeel')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'teams'   => 'Teams',
                        'members' => 'Leden',
                        'matches' => 'Wedstrijden',
                        default   => (string) $state,
                    })
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'completed' => 'success',
                        'started'   => 'warning',
                        'failed'    => 'danger',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'completed' => 'Voltooid',
                        'started'   => 'Bezig',
                        'failed'    => 'Mislukt',
                        default     => (string) $state,
                    }),
                Tables\Columns\TextColumn::make('records_synced')
                    ->label('Bijgewerkt')
                    ->numeric()
                    ->alignEnd(),
                // Hoe lang een ronde duurt zegt vaak meer dan het aantal: loopt
                // hij ineens minuten, dan is er iets aan de hand met de koppeling.
                Tables\Columns\TextColumn::make('duur')
                    ->label('Duur')
                    ->getStateUsing(function (SyncLog $record): string {
                        if (! $record->started_at || ! $record->completed_at) {
                            return '—';
                        }

                        $seconden = $record->completed_at->diffInSeconds($record->started_at);

                        return $seconden < 60
                            ? $seconden . ' s'
                            : floor($seconden / 60) . ' m ' . ($seconden % 60) . ' s';
                    }),
                Tables\Columns\TextColumn::make('error_message')
                    ->label('Foutmelding')
                    ->placeholder('—')
                    ->wrap()
                    ->limit(120)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('club.name')
                    ->label('Club')
                    ->placeholder('Alle clubs')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Onderdeel')
                    ->options([
                        'teams'   => 'Teams',
                        'members' => 'Leden',
                        'matches' => 'Wedstrijden',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'completed' => 'Voltooid',
                        'started'   => 'Bezig',
                        'failed'    => 'Mislukt',
                    ]),
            ])
            ->actions([])
            ->bulkActions([])
            // Een monitor die je openhoudt tijdens een handmatige ronde ververst
            // zichzelf; anders zit je te herladen.
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSyncLogs::route('/'),
        ];
    }
}
