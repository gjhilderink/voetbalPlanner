<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ClothingItemResource\Pages;
use App\Models\ClothingItem;
use App\Models\ClothingSize;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * De kledingstukken van de club, met de maten die erbij horen.
 *
 * Kledingstuk en maten staan op één scherm. Ze los beheren zou betekenen dat je
 * een kledingstuk kunt aanmaken waar niemand een maat bij kan kiezen, en dat is
 * precies het soort halve toestand waar je later achterkomt.
 */
class ClothingItemResource extends Resource
{
    protected static ?string $model = ClothingItem::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';
    protected static ?string $navigationLabel = 'Kledingstukken';
    protected static ?string $modelLabel = 'kledingstuk';
    protected static ?string $pluralModelLabel = 'Kledingstukken';
    protected static string|\UnitEnum|null $navigationGroup = 'Beheer';
    protected static ?int $navigationSort = 8;
    protected static bool $isScopedToTenant = false;

    /** @var array<int, string> */
    private const ROLLEN = ['super_admin', 'club_admin', 'kleding_commissie'];

    public static function getEloquentQuery(): Builder
    {
        $tenant = filament()->getTenant();

        return parent::getEloquentQuery()
            ->when($tenant, fn (Builder $q) => $q->where('club_id', $tenant->id));
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(self::ROLLEN) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kledingstuk')->schema([
                Forms\Components\Hidden::make('club_id')
                    ->default(fn () => filament()->getTenant()?->id ?? auth()->user()?->club_id),

                Forms\Components\TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(60)
                    ->placeholder('Shirt'),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Volgorde')
                    ->numeric()
                    ->default(0)
                    ->helperText('Bepaalt de volgorde in de app en in het overzicht.'),

                Forms\Components\Toggle::make('is_active')
                    ->label('In gebruik')
                    ->default(true)
                    ->helperText('Uit betekent: dit seizoen niet uitdelen. De al opgegeven maten blijven bewaard.')
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Maten')
                ->description('De maten waaruit een lid kan kiezen. Voor sokken kun je bijvoorbeeld schoenmaten gebruiken, en voor een tas volstaat één regel.')
                ->schema([
                    Forms\Components\Repeater::make('sizes')
                        ->label('')
                        ->relationship()
                        ->schema([
                            Forms\Components\TextInput::make('label')
                                ->label('Maat')
                                ->required()
                                ->maxLength(30)
                                ->placeholder('M'),
                            Forms\Components\TextInput::make('sort_order')
                                ->label('Volgorde')
                                ->numeric()
                                ->default(0),
                        ])
                        ->columns(2)
                        ->orderColumn('sort_order')
                        ->addActionLabel('Maat toevoegen')
                        ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                        // Een maat die iemand heeft gekozen mag niet zomaar weg:
                        // dan verdwijnt stilzwijgend de opgave van tientallen
                        // leden, en dat merk je pas als de kleding besteld moet
                        // worden.
                        ->deleteAction(fn (Actions\Action $action) => $action->before(
                            function (array $arguments, Forms\Components\Repeater $component) {
                                $id = $component->getRawState()[$arguments['item']]['id'] ?? null;
                                $maat = $id ? ClothingSize::find($id) : null;

                                if ($maat && $maat->inGebruik()) {
                                    Notification::make()
                                        ->title('Deze maat is in gebruik')
                                        ->body("{$maat->label} is door minstens één lid gekozen. Zet het kledingstuk op 'niet in gebruik' of wijzig eerst die leden.")
                                        ->danger()
                                        ->send();

                                    $action->halt();
                                }
                            },
                        ))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Kledingstuk')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sizes_count')
                    ->label('Maten')
                    ->counts('sizes')
                    ->alignEnd(),
                // Wat de maten zíjn, niet alleen hoeveel het er zijn: anders moet
                // je elk kledingstuk openen om te zien of het klopt.
                Tables\Columns\TextColumn::make('sizes.label')
                    ->label('Welke')
                    ->badge()
                    ->separator(',')
                    ->limitList(6)
                    ->color('gray'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Volgorde')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('In gebruik')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('In gebruik'),
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

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListClothingItems::route('/'),
            'create' => Pages\CreateClothingItem::route('/create'),
            'edit'   => Pages\EditClothingItem::route('/{record}/edit'),
        ];
    }
}
