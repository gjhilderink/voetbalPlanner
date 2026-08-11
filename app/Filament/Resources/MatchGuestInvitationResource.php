<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MatchGuestInvitationResource\Pages;
use App\Models\MatchGuestInvitation;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MatchGuestInvitationResource extends Resource
{
    protected static ?string $model = MatchGuestInvitation::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';
    protected static ?string $navigationLabel = 'Gastspelers';
    protected static ?string $modelLabel = 'Gastspeler-uitnodiging';
    protected static ?string $pluralModelLabel = 'Gastspeler-uitnodigingen';
    protected static string|\UnitEnum|null $navigationGroup = 'Leden';
    protected static ?int $navigationSort = 26;
    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin']) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query  = parent::getEloquentQuery();
        $user   = auth()->user();
        $tenant = filament()->getTenant();

        if ($tenant) {
            $query->where('club_id', $tenant->id);
        } elseif (! $user?->hasRole('super_admin') && $user?->club_id) {
            $query->where('club_id', $user->club_id);
        }

        return $query->with(['member', 'match.team', 'team', 'invitedBy']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('member.name')
                    ->label('Gastspeler')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('match.opponent')
                    ->label('Wedstrijd (tegenstander)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('match.match_datetime')
                    ->label('Wedstrijddatum')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('team.name')
                    ->label('Uit team')
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors(['success' => 'active', 'danger' => 'revoked'])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active'  => 'Actief',
                        'revoked' => 'Ingetrokken',
                        default   => $state,
                    }),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Vervalt')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('invitedBy.name')
                    ->label('Uitgenodigd door')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(['active' => 'Actief', 'revoked' => 'Ingetrokken']),
            ])
            ->actions([
                Actions\Action::make('revoke')
                    ->label('Intrekken')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (MatchGuestInvitation $record) => $record->status === 'active')
                    ->action(function (MatchGuestInvitation $record): void {
                        $record->update([
                            'status'             => 'revoked',
                            'revoked_by_user_id' => auth()->id(),
                            'revoked_at'         => now(),
                        ]);
                    })
                    ->successNotificationTitle('Uitnodiging ingetrokken.'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMatchGuestInvitations::route('/'),
        ];
    }
}
