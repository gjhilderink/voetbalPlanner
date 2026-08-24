<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Support\TeamFilter;
use App\Models\Club;
use App\Models\Member;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Gebruikers';
    protected static ?string $modelLabel = 'Gebruiker';
    protected static ?string $pluralModelLabel = 'Gebruikers';
    protected static ?int $navigationSort = 9;
    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Accountgegevens')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('E-mailadres')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->label('Wachtwoord')
                    ->password()
                    ->revealable()
                    ->required(fn(string $operation): bool => $operation === 'create')
                    ->dehydrateStateUsing(fn($state) => Hash::make($state))
                    ->dehydrated(fn($state) => filled($state))
                    ->helperText('Laat leeg om het huidige wachtwoord te behouden.'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Actief')
                    ->default(true),
            ])->columns(2),

            Section::make('Club')->schema([
                // Standaard de club waarin je werkt. Zonder default bleef dit veld
                // leeg en kreeg een nieuwe gebruiker géén club — de placeholder
                // "alle clubs" oogde bovendien als een normale keuze.
                Forms\Components\Select::make('club_id')
                    ->label('Club')
                    ->default(fn () => filament()->getTenant()?->id ?? auth()->user()?->club_id)
                    ->options(function (): array {
                        $query = Club::where('is_active', true)->orderBy('name');

                        // Alleen een super admin kan een gebruiker aan een andere
                        // club hangen of clubloos laten.
                        if (! auth()->user()?->hasRole('super_admin')) {
                            $query->whereKey(filament()->getTenant()?->id ?? auth()->user()?->club_id);
                        }

                        return $query->pluck('name', 'id')->all();
                    })
                    ->searchable()
                    ->required(fn (): bool => ! (auth()->user()?->hasRole('super_admin') ?? false))
                    ->placeholder(fn (): string => auth()->user()?->hasRole('super_admin')
                        ? '— Alle clubs (super admin) —'
                        : 'Kies een club')
                    ->helperText('Super admins hebben automatisch toegang tot alle clubs en hebben geen club-koppeling nodig.')
                    ->columnSpanFull(),
            ]),

            Section::make('Rechten')->schema([
                Forms\Components\Select::make('roles')
                    ->label('Rollen')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->options(
                        \Spatie\Permission\Models\Role::where('guard_name', 'web')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn($r) => [
                                $r->id => UserRole::tryFrom($r->name)?->label() ?? ucwords(str_replace('_', ' ', $r->name))
                            ])
                    )
                    ->preload()
                    ->columnSpanFull(),

                Forms\Components\Repeater::make('managed_team_functions')
                    ->label('Toegewezen teams & functies')
                    ->helperText('Functie per team. Coach/Trainer of Leider geeft beheerrechten (opstelling & score) voor dat team.')
                    ->schema([
                        Forms\Components\Select::make('team_id')
                            ->label('Team')
                            ->options(fn () => TeamFilter::options())
                            ->searchable()
                            ->required()
                            ->distinct(),
                        Forms\Components\Select::make('role')
                            ->label('Functie')
                            ->options(Member::TEAM_FUNCTIONS)
                            ->default(Member::ROLE_COACH)
                            ->required(),
                    ])
                    ->columns(2)
                    ->addActionLabel('Team toevoegen')
                    ->default([])
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    /**
     * Slaat de "Toegewezen teams & functies"-repeater op naar de user_team pivot.
     * Aangeroepen vanuit Create-/EditUser (afterCreate / afterSave).
     */
    public static function syncManagedTeamFunctions(User $user, array $rows): void
    {
        $sync = [];
        foreach ($rows as $row) {
            $teamId = $row['team_id'] ?? null;
            if (! $teamId) {
                continue;
            }
            $sync[$teamId] = ['role' => $row['role'] ?? Member::ROLE_COACH];
        }

        $user->managedTeams()->sync($sync);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rol(len)')
                    ->badge()
                    ->formatStateUsing(fn($state) => UserRole::tryFrom($state)?->label() ?? ucwords(str_replace('_', ' ', $state)))
                    ->color(fn($state) => match($state) {
                        'super_admin' => 'danger',
                        'club_admin'  => 'warning',
                        'coach'       => 'info',
                        'guardian'    => 'success',
                        default       => 'gray',
                    }),
                Tables\Columns\TextColumn::make('managedTeams.name')
                    ->label('Teams')
                    ->badge()
                    ->separator(','),
                // Bij wie hoort dit account? Voor een ouder is dat de enige
                // manier om te zien waarom hij toegang heeft; zonder deze
                // kolom stond er alleen een naam zonder team of lidmaatschap.
                Tables\Columns\TextColumn::make('guardianChildren')
                    ->label('Ouder van')
                    ->badge()
                    ->getStateUsing(fn($record) => $record->guardianChildren()
                        ->pluck('name')
                        ->all())
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('club.name')
                    ->label('Club')
                    ->getStateUsing(fn($record) => $record->hasRole('super_admin') ? 'Alle clubs' : ($record->club?->name ?? '-'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Laatste login')
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('Nog nooit')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aangemaakt')
                    ->dateTime('d-m-Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Actief'),
            ])
            ->actions([
                Actions\Action::make('impersonate')
                    ->label('Login als')
                    ->icon('heroicon-o-identification')
                    ->color('warning')
                    ->visible(fn(User $record): bool =>
                        auth()->user()?->canImpersonate() && $record->canBeImpersonated()
                    )
                    ->action(function (User $record) {
                        auth()->user()->impersonate($record);
                        return redirect('/admin');
                    })
                    ->requiresConfirmation()
                    ->modalHeading(fn(User $record) => 'Inloggen als ' . $record->name)
                    ->modalDescription('Je krijgt toegang als deze gebruiker. Gebruik de "Terug naar eigen account" knop bovenin om terug te keren.')
                    ->modalSubmitActionLabel('Inloggen als'),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        $query  = parent::getEloquentQuery();
        $tenant = filament()->getTenant();

        if ($tenant) {
            $query->where('club_id', $tenant->id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
