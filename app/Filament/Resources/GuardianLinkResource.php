<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\GuardianLinkResource\Pages;
use App\Models\GuardianLink;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class GuardianLinkResource extends Resource
{
    protected static ?string $model = GuardianLink::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Ouder/Verzorger';
    protected static ?string $modelLabel = 'Koppeling';
    protected static ?string $pluralModelLabel = 'Koppelingen';
    protected static string|\UnitEnum|null $navigationGroup = 'Leden';
    protected static ?int $navigationSort = 25;
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

        return $query->with(['guardian', 'child', 'revokedBy']);
    }

    public static function form(Schema $schema): Schema
    {
        // Read-only detail view
        return $schema->components([
            Section::make('Koppeling')->schema([
                \Filament\Forms\Components\TextInput::make('guardian.name')
                    ->label('Ouder / Verzorger')
                    ->disabled(),
                \Filament\Forms\Components\TextInput::make('child.name')
                    ->label('Kind / Lid')
                    ->disabled(),
                \Filament\Forms\Components\TextInput::make('status')
                    ->label('Status')
                    ->disabled(),
                \Filament\Forms\Components\DateTimePicker::make('created_at')
                    ->label('Aangevraagd op')
                    ->disabled(),
                \Filament\Forms\Components\DateTimePicker::make('expires_at')
                    ->label('Vervalt op')
                    ->disabled(),
                \Filament\Forms\Components\DateTimePicker::make('resolved_at')
                    ->label('Beantwoord op')
                    ->disabled(),
                \Filament\Forms\Components\DateTimePicker::make('revoked_at')
                    ->label('Ingetrokken op')
                    ->disabled(),
                \Filament\Forms\Components\TextInput::make('revokedBy.name')
                    ->label('Ingetrokken door')
                    ->disabled()
                    ->placeholder('—'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('guardian.name')
                    ->label('Ouder / Verzorger')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('child.name')
                    ->label('Kind / Lid')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger'  => fn ($state) => in_array($state, ['rejected', 'revoked']),
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'  => 'In afwachting',
                        'approved' => 'Goedgekeurd',
                        'rejected' => 'Geweigerd',
                        'revoked'  => 'Ingetrokken',
                        default    => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aangevraagd')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Vervalt')
                    ->dateTime('d-m-Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('resolved_at')
                    ->label('Beantwoord')
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending'  => 'In afwachting',
                        'approved' => 'Goedgekeurd',
                        'rejected' => 'Geweigerd',
                        'revoked'  => 'Ingetrokken',
                    ]),
            ])
            ->actions([
                // Revoke: available for approved or pending links
                Actions\Action::make('revoke')
                    ->label('Intrekken')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Koppeling intrekken')
                    ->modalDescription('Weet je zeker dat je deze ouder/verzorger-koppeling wilt intrekken?')
                    ->visible(fn (GuardianLink $record) => in_array($record->status, ['pending', 'approved']))
                    ->action(function (GuardianLink $record): void {
                        $record->update([
                            'status'               => 'revoked',
                            'revoked_by_member_id' => auth()->user()?->member?->id,
                            'revoked_at'           => now(),
                        ]);
                    })
                    ->successNotificationTitle('Koppeling ingetrokken.'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGuardianLinks::route('/'),
        ];
    }
}
