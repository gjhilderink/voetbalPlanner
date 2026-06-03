<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentationResource\Pages;
use App\Models\Documentation;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DocumentationResource extends Resource
{
    protected static ?string $model = Documentation::class;
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-book-open';
    protected static ?string $navigationLabel                  = 'Documentatie';
    protected static ?string $modelLabel                       = 'Sectie';
    protected static ?string $pluralModelLabel                 = 'Documentatie';
    protected static string|\UnitEnum|null $navigationGroup    = 'Documentatie';
    protected static ?int    $navigationSort                   = 90;
    protected static bool    $isScopedToTenant                 = false;

    public static function canViewAny(): bool
    {
        return auth()->check();
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
                Forms\Components\Select::make('category')
                    ->label('Categorie')
                    ->options(Documentation::$categoryLabels)
                    ->required()
                    ->columnSpan(1),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Volgorde')
                    ->numeric()
                    ->default(0)
                    ->columnSpan(1),

                Forms\Components\TextInput::make('title')
                    ->label('Titel')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('body')
                    ->label('Inhoud')
                    ->required()
                    ->rows(14)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Actief')
                    ->default(true)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category')
                    ->label('Categorie')
                    ->badge()
                    ->formatStateUsing(fn($state) => Documentation::$categoryLabels[$state] ?? $state)
                    ->color(fn($state) => match ($state) {
                        'app'         => 'success',
                        'platform'    => 'info',
                        'koppelingen' => 'warning',
                        default       => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Volgorde')
                    ->sortable()
                    ->alignCenter()
                    ->width(80),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean()
                    ->alignCenter()
                    ->width(70),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Bijgewerkt')
                    ->date('d-m-Y')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->groups([
                Tables\Grouping\Group::make('category')
                    ->label('Categorie')
                    ->getTitleFromRecordUsing(
                        fn(Documentation $r) => Documentation::$categoryLabels[$r->category] ?? $r->category
                    )
                    ->collapsible(),
            ])
            ->defaultGroup('category')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categorie')
                    ->options(Documentation::$categoryLabels),
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
            'index'  => Pages\ListDocumentations::route('/'),
            'create' => Pages\CreateDocumentation::route('/create'),
            'edit'   => Pages\EditDocumentation::route('/{record}/edit'),
        ];
    }
}
