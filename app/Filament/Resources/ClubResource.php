<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ClubResource\Pages;
use App\Models\Club;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ClubResource extends Resource
{
    protected static ?string $model = Club::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Clubs';
    protected static ?string $modelLabel = 'Club';
    protected static ?string $pluralModelLabel = 'Clubs';
    protected static ?int $navigationSort = 8;
    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Club informatie')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn($state, Forms\Set $set, string $operation) =>
                        $operation === 'create'
                            ? $set('slug', Str::slug($state))
                            : null
                    ),
                Forms\Components\TextInput::make('slug')
                    ->label('Slug (URL)')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(100)
                    ->helperText('Gebruikt in de URL: voetbalplanner.nubix.nl/admin/[slug]'),
                Forms\Components\FileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->disk('public')
                    ->directory('logos')
                    ->imagePreviewHeight('80')
                    ->maxSize(2048)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Actief')
                    ->default(true),
            ])->columns(2),

            Section::make('Contactgegevens')->schema([
                Forms\Components\Textarea::make('address')
                    ->label('Adres')
                    ->rows(2)
                    ->maxLength(500),
                Forms\Components\TextInput::make('city')
                    ->label('Woonplaats')
                    ->maxLength(100),
                Forms\Components\TextInput::make('phone')
                    ->label('Telefoon')
                    ->tel()
                    ->maxLength(30),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('website')
                    ->label('Website')
                    ->url()
                    ->maxLength(255),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->height(40)
                    ->width(40),
                Tables\Columns\TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->copyable(),
                Tables\Columns\TextColumn::make('city')
                    ->label('Woonplaats'),
                Tables\Columns\TextColumn::make('teams_count')
                    ->label('Teams')
                    ->counts('teams'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean(),
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
            'index'  => Pages\ListClubs::route('/'),
            'create' => Pages\CreateClub::route('/create'),
            'edit'   => Pages\EditClub::route('/{record}/edit'),
        ];
    }
}
