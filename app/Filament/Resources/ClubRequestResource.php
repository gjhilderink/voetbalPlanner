<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ClubRequestResource\Pages;
use App\Models\ClubRequest;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ClubRequestResource extends Resource
{
    protected static ?string $model = ClubRequest::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope-open';
    protected static ?string $navigationLabel = 'Clubaanvragen';
    protected static ?string $modelLabel = 'Clubaanvraag';
    protected static ?string $pluralModelLabel = 'Clubaanvragen';
    protected static ?int $navigationSort = 9;
    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Clubgegevens')->schema([
                Forms\Components\TextInput::make('club_name')
                    ->label('Clubnaam')
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('contact_name')
                    ->label('Contactpersoon')
                    ->disabled(),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->disabled(),
                Forms\Components\TextInput::make('phone')
                    ->label('Telefoon')
                    ->disabled(),
            ])->columns(2),

            Section::make('Sportlink-inloggegevens')->schema([
                Forms\Components\TextInput::make('sportlink_username')
                    ->label('Gebruikersnaam')
                    ->disabled(),
                Forms\Components\TextInput::make('sportlink_password')
                    ->label('Wachtwoord')
                    ->password()
                    ->revealable()
                    ->disabled(),
            ])->columns(2),

            Section::make('Opmerkingen aanvrager')->schema([
                Forms\Components\Textarea::make('notes')
                    ->label('Opmerkingen')
                    ->disabled()
                    ->rows(3)
                    ->columnSpanFull(),
            ]),

            Section::make('Beoordeling')->schema([
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending'  => 'In behandeling',
                        'approved' => 'Goedgekeurd',
                        'rejected' => 'Afgewezen',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('admin_notes')
                    ->label('Interne notities')
                    ->rows(3)
                    ->helperText('Alleen zichtbaar voor beheerders.')
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('club_name')
                    ->label('Club')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Contactpersoon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefoon')
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                    ])
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'approved' => 'Goedgekeurd',
                        'rejected' => 'Afgewezen',
                        default    => 'In behandeling',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aangevraagd')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending'  => 'In behandeling',
                        'approved' => 'Goedgekeurd',
                        'rejected' => 'Afgewezen',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make()->label('Beoordelen'),
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
            'index' => Pages\ListClubRequests::route('/'),
            'edit'  => Pages\EditClubRequest::route('/{record}/edit'),
        ];
    }
}
