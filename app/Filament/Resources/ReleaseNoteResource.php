<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ReleaseNoteResource\Pages;
use App\Models\ReleaseNote;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ReleaseNoteResource extends Resource
{
    protected static ?string $model = ReleaseNote::class;
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel                  = 'Release notes';
    protected static ?string $modelLabel                       = 'Release note';
    protected static ?string $pluralModelLabel                 = 'Release notes';
    protected static string|\UnitEnum|null $navigationGroup    = 'Features & Releases';
    protected static ?int    $navigationSort                   = 20;
    protected static bool    $isScopedToTenant                 = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    // Release notes worden automatisch gegenereerd uit features (status
    // "Uitgebracht"); handmatig aanmaken is daarom uitgeschakeld.
    public static function canCreate(): bool
    {
        return false;
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
                Forms\Components\TextInput::make('title')
                    ->label('Titel')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\DateTimePicker::make('released_at')
                    ->label('Uitgebracht op')
                    ->seconds(false)
                    ->columnSpanFull(),

                Forms\Components\RichEditor::make('body')
                    ->label('Inhoud')
                    ->helperText('Automatisch overgenomen uit de feature; pas gerust aan voor de publicatie.')
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
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('feature.status')
                    ->label('Feature-status')
                    ->badge()
                    ->formatStateUsing(fn($state) => \App\Models\Feature::$statusLabels[$state] ?? ($state ?? '—'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('released_at')
                    ->label('Uitgebracht')
                    ->dateTime('d-m-Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('released_at', 'desc')
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn() => auth()->user()?->hasRole('super_admin') ?? false),
                Actions\DeleteAction::make()
                    ->visible(fn() => auth()->user()?->hasRole('super_admin') ?? false),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()?->hasRole('super_admin') ?? false),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReleaseNotes::route('/'),
            'edit'  => Pages\EditReleaseNote::route('/{record}/edit'),
        ];
    }
}
