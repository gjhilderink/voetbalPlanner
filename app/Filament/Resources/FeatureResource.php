<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\FeatureResource\Pages;
use App\Models\Feature;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class FeatureResource extends Resource
{
    protected static ?string $model = Feature::class;
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-sparkles';
    protected static ?string $navigationLabel                  = 'Nieuwe features';
    protected static ?string $modelLabel                       = 'Feature';
    protected static ?string $pluralModelLabel                 = 'Nieuwe features';
    protected static string|\UnitEnum|null $navigationGroup    = 'Features & Releases';
    protected static ?int    $navigationSort                   = 10;
    protected static bool    $isScopedToTenant                 = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Titel')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('description')
                    ->label('Omschrijving')
                    ->rows(6)
                    ->helperText('Wordt overgenomen in de release note zodra de feature op "Uitgebracht" staat.')
                    ->columnSpanFull(),

                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options(\App\Models\ReleaseNote::$typeLabels)
                    ->default('feature')
                    ->required()
                    ->helperText('Bug, nieuwe functie of verbetering.')
                    ->columnSpan(1),

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(Feature::$statusLabels)
                    ->default('idee')
                    ->required()
                    ->helperText('Zet op "Uitgebracht" om automatisch een release note te genereren.')
                    ->columnSpan(1),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Volgorde')
                    ->numeric()
                    ->default(0)
                    ->columnSpan(1),

                Forms\Components\DateTimePicker::make('released_at')
                    ->label('Uitgebracht op')
                    ->seconds(false)
                    ->helperText('Wordt automatisch ingevuld bij status "Uitgebracht" (mag je aanpassen).')
                    ->columnSpan(1),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => Feature::$statusLabels[$state] ?? $state)
                    ->color(fn($state) => match ($state) {
                        'idee'            => 'gray',
                        'gepland'         => 'info',
                        'in_ontwikkeling' => 'warning',
                        'uitgebracht'     => 'success',
                        default           => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('released_at')
                    ->label('Uitgebracht')
                    ->date('d-m-Y')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Bijgewerkt')
                    ->date('d-m-Y')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Feature::$statusLabels),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn() => static::canCreate()),
                Actions\DeleteAction::make()
                    ->visible(fn() => static::canCreate()),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->visible(fn() => static::canCreate()),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFeatures::route('/'),
            'create' => Pages\CreateFeature::route('/create'),
            'edit'   => Pages\EditFeature::route('/{record}/edit'),
        ];
    }
}
