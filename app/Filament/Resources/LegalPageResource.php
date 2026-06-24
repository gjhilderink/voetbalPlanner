<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\LegalPageResource\Pages;
use App\Models\LegalPage;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LegalPageResource extends Resource
{
    protected static ?string $model = LegalPage::class;
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel                  = 'Juridische pagina\'s';
    protected static ?string $modelLabel                       = 'Juridische pagina';
    protected static ?string $pluralModelLabel                 = 'Juridische pagina\'s';
    protected static string|\UnitEnum|null $navigationGroup    = 'Documentatie';
    protected static ?int    $navigationSort                   = 91;
    protected static bool    $isScopedToTenant                 = false;

    // Alleen de super_admin mag juridische pagina's zien en bewerken.
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
            Section::make()->schema([
                Forms\Components\TextInput::make('slug')
                    ->label('Slug (URL)')
                    ->required()
                    ->maxLength(120)
                    ->helperText("De URL-naam, bijv. 'privacy' wordt /privacy. Wijzig dit niet voor een bestaande pagina tenzij je bewust de URL wilt veranderen.")
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('title')
                    ->label('Titel')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\RichEditor::make('body')
                    ->label('Inhoud')
                    ->required()
                    ->columnSpanFull(),
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
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('URL')
                    ->badge()
                    ->formatStateUsing(fn($state) => '/' . $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Bijgewerkt')
                    ->date('d-m-Y')
                    ->sortable(),
            ])
            ->defaultSort('title')
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
            'index'  => Pages\ListLegalPages::route('/'),
            'create' => Pages\CreateLegalPage::route('/create'),
            'edit'   => Pages\EditLegalPage::route('/{record}/edit'),
        ];
    }
}
