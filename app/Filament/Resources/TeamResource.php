<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Models\Team;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Teams';
    protected static ?string $modelLabel = 'Team';
    protected static ?string $pluralModelLabel = 'Teams';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('category')
                    ->label('Categorie')
                    ->maxLength(100),
                Forms\Components\TextInput::make('age_group')
                    ->label('Leeftijdsklasse')
                    ->maxLength(50),
                Forms\Components\TextInput::make('season')
                    ->label('Seizoen')
                    ->maxLength(20),
                Forms\Components\Toggle::make('is_active')
                    ->label('Actief')
                    ->default(true),
                Forms\Components\TextInput::make('external_id')
                    ->label('Extern ID')
                    ->disabled(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category')->label('Categorie')->sortable(),
                Tables\Columns\TextColumn::make('age_group')->label('Leeftijdsklasse'),
                Tables\Columns\TextColumn::make('season')->label('Seizoen'),
                Tables\Columns\IconColumn::make('is_active')->label('Actief')->boolean(),
                Tables\Columns\TextColumn::make('members_count')
                    ->label('Leden')
                    ->counts('members'),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Laatste sync')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
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
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
        ];
    }
}
