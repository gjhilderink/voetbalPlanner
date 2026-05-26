<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Gebruikers';
    protected static ?string $modelLabel = 'Gebruiker';
    protected static ?string $pluralModelLabel = 'Gebruikers';
    protected static ?int $navigationSort = 9;

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

                Forms\Components\Select::make('managedTeams')
                    ->label('Toegewezen teams')
                    ->helperText('Welke teams mag deze gebruiker beheren? (relevant voor Coach rol)')
                    ->multiple()
                    ->relationship('managedTeams', 'name')
                    ->preload()
                    ->searchable()
                    ->columnSpanFull(),
            ]),
        ]);
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
                        default       => 'gray',
                    }),
                Tables\Columns\TextColumn::make('managedTeams.name')
                    ->label('Teams')
                    ->badge()
                    ->separator(','),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
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
