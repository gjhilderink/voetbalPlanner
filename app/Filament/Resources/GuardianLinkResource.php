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

    /**
     * Aantal openstaande verzoeken naast het menu-item.
     *
     * Een koppelverzoek blijft veertien dagen liggen en wordt normaal door het
     * kind zelf afgehandeld. Komt dat er niet van, dan belt de ouder de club —
     * en dan moet een beheerder het hier kunnen zien staan zonder ernaar te
     * zoeken. Verlopen verzoeken tellen niet mee; daar valt niets meer te doen.
     */
    public static function getNavigationBadge(): ?string
    {
        $aantal = static::getEloquentQuery()->pending()->count();

        return $aantal > 0 ? (string) $aantal : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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
                    ->seconds(false)
                    ->displayFormat('d-m-Y H:i')
                    ->disabled(),
                \Filament\Forms\Components\DateTimePicker::make('expires_at')
                    ->label('Vervalt op')
                    ->seconds(false)
                    ->displayFormat('d-m-Y H:i')
                    ->disabled(),
                \Filament\Forms\Components\DateTimePicker::make('resolved_at')
                    ->label('Beantwoord op')
                    ->seconds(false)
                    ->displayFormat('d-m-Y H:i')
                    ->disabled(),
                \Filament\Forms\Components\DateTimePicker::make('revoked_at')
                    ->label('Ingetrokken op')
                    ->seconds(false)
                    ->displayFormat('d-m-Y H:i')
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
                // Een verzoek dat is verlopen staat in de database nog op
                // 'pending' — het vervalt door de datum, niet door een
                // statuswijziging. Zonder dit onderscheid blijft het jaren als
                // "In afwachting" in de lijst staan terwijl er niets meer kan.
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => fn ($state, GuardianLink $record) => $record->isPending(),
                        'success' => 'approved',
                        'gray'    => fn ($state, GuardianLink $record) => $state === 'pending' && ! $record->isPending(),
                        'danger'  => fn ($state) => in_array($state, ['rejected', 'revoked']),
                    ])
                    ->formatStateUsing(fn (string $state, GuardianLink $record): string => match (true) {
                        $state === 'pending' && ! $record->isPending() => 'Verlopen',
                        $state === 'pending'  => 'In afwachting',
                        $state === 'approved' => 'Goedgekeurd',
                        $state === 'rejected' => 'Geweigerd',
                        $state === 'revoked'  => 'Ingetrokken',
                        default               => $state,
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
                // Goedkeuren namens het kind. Normaal bevestigt het kind zelf in
                // de app; lukt dat niet — geen app, geen e-mail, of het verzoek
                // blijft gewoon liggen — dan belt de ouder de club. Zonder deze
                // knop kon een beheerder daar niets mee.
                //
                // Alleen zolang het verzoek echt openstaat (isPending: pending
                // én niet verlopen). Een verlopen verzoek alsnog goedkeuren zou
                // de vervaltermijn zinloos maken; daarvoor dient de ouder een
                // nieuw verzoek in.
                Actions\Action::make('approve')
                    ->label('Goedkeuren')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Koppeling goedkeuren')
                    ->modalDescription(fn (GuardianLink $record): string =>
                        'Hiermee krijgt ' . ($record->guardian?->name ?? 'de ouder/verzorger')
                        . ' toegang tot de gegevens van ' . ($record->child?->name ?? 'dit lid')
                        . '. Doe dit alleen als je zeker weet dat dit klopt — normaal bevestigt het lid zelf in de app.')
                    ->modalSubmitActionLabel('Goedkeuren')
                    ->visible(fn (GuardianLink $record) => $record->isPending())
                    ->action(fn (GuardianLink $record) => $record->beslis(
                        'approved',
                        auth()->user()?->resolveMember()?->id,
                        doorBeheerder: true,
                    ))
                    ->successNotificationTitle('Koppeling goedgekeurd.'),

                Actions\Action::make('reject')
                    ->label('Weigeren')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Verzoek weigeren')
                    ->modalDescription('De ouder/verzorger krijgt geen toegang en ziet dat het verzoek is geweigerd.')
                    ->modalSubmitActionLabel('Weigeren')
                    ->visible(fn (GuardianLink $record) => $record->isPending())
                    ->action(fn (GuardianLink $record) => $record->beslis(
                        'rejected',
                        auth()->user()?->resolveMember()?->id,
                        doorBeheerder: true,
                    ))
                    ->successNotificationTitle('Verzoek geweigerd.'),

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
                            // resolveMember(): een beheerder die alleen via zijn
                            // e-mail aan een lid hangt heeft geen ->member.
                            'revoked_by_member_id' => auth()->user()?->resolveMember()?->id,
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
