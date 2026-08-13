<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AgendaCategoryResource\Pages;
use App\Models\AgendaCategory;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class AgendaCategoryResource extends Resource
{
    protected static ?string $model = AgendaCategory::class;
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-tag';
    protected static ?string $navigationLabel                  = 'Agenda-categorieën';
    protected static ?string $modelLabel                       = 'Agenda-categorie';
    protected static ?string $pluralModelLabel                 = 'Agenda-categorieën';
    protected static string|\UnitEnum|null $navigationGroup    = 'Planning';
    protected static ?int    $navigationSort                   = 12;
    protected static bool    $isScopedToTenant                 = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin']) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query  = parent::getEloquentQuery();
        $tenant = filament()->getTenant();

        if ($tenant) {
            $query->where('club_id', $tenant->id);
        } elseif (! auth()->user()?->hasRole('super_admin')) {
            $query->where('club_id', auth()->user()?->club_id);
        }

        return $query->withCount('items');
    }

    public static function form(Schema $schema): Schema
    {
        $clubId = fn () => filament()->getTenant()?->id ?? auth()->user()?->club_id;

        return $schema->components([
            Section::make('Categorie')->columns(2)->schema([
                Forms\Components\Hidden::make('club_id')->default($clubId),

                Forms\Components\TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(60)
                    // De slug is de sleutel waarop de app filtert; bij een nieuwe
                    // categorie leiden we hem af, bij bewerken laten we hem staan
                    // zodat bestaande app-filters blijven werken.
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        if ($state && blank($get('slug'))) {
                            $set('slug', Str::slug($state));
                        }
                    }),

                Forms\Components\TextInput::make('slug')
                    ->label('Sleutel')
                    ->required()
                    ->maxLength(60)
                    ->helperText('Wordt gebruikt door de app om te filteren. Wijzig dit niet zonder reden.'),

                Forms\Components\ColorPicker::make('color')
                    ->label('Kleur')
                    ->default('#16a34a')
                    ->required()
                    ->helperText('Bepaalt de kleurmarkering in de app en in het overzicht.'),

                Forms\Components\Select::make('icon')
                    ->label('Icoon')
                    ->options(AgendaCategory::$icons)
                    ->default('event')
                    ->required()
                    ->searchable()
                    ->live(),

                Forms\Components\Placeholder::make('icon_preview')
                    ->label('Voorbeeld')
                    ->content(fn (Get $get): HtmlString => new HtmlString(
                        '<span class="material-icons" style="font-size:3rem;line-height:1;color:'
                        . e((string) ($get('color') ?: '#16a34a')) . '">'
                        . e((string) ($get('icon') ?: 'event')) . '</span>'
                    )),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Volgorde')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lager = eerder in de lijst.'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Actief')
                    ->default(true)
                    ->helperText('Inactieve categorieën zijn niet meer te kiezen bij nieuwe activiteiten.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('color')->label(''),
                Tables\Columns\TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('Sleutel')->toggleable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Activiteiten')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('sort_order')->label('Volgorde')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->label('Actief')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Actief'),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    // De FK is nullOnDelete: activiteiten blijven bestaan maar
                    // verliezen hun kleur en label. Dat is het vermelden waard.
                    ->modalDescription(fn (AgendaCategory $record): string => $record->items_count > 0
                        ? "Let op: {$record->items_count} activiteit(en) verliezen hierdoor hun categorie."
                        : 'Weet je zeker dat je deze categorie wilt verwijderen?'),
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
            'index'  => Pages\ListAgendaCategories::route('/'),
            'create' => Pages\CreateAgendaCategory::route('/create'),
            'edit'   => Pages\EditAgendaCategory::route('/{record}/edit'),
        ];
    }
}
