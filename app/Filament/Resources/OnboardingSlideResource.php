<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\OnboardingSlideResource\Pages;
use App\Models\OnboardingSlide;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OnboardingSlideResource extends Resource
{
    protected static ?string $model = OnboardingSlide::class;
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-presentation-chart-bar';
    protected static ?string $navigationLabel                  = 'Onboarding-slides';
    protected static ?string $modelLabel                       = 'Onboarding-slide';
    protected static ?string $pluralModelLabel                 = 'Onboarding-slides';
    protected static string|\UnitEnum|null $navigationGroup    = 'Marketing';
    protected static ?int    $navigationSort                   = 65;
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
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        $clubId = fn() => filament()->getTenant()?->id ?? auth()->user()?->club_id;

        return $schema->components([
            Section::make('Slide')->columns(2)->schema([
                Forms\Components\Hidden::make('club_id')
                    ->default($clubId),

                Forms\Components\Select::make('icon')
                    ->label('Icoon')
                    ->options(OnboardingSlide::$icons)
                    ->default('info')
                    ->required()
                    ->searchable()
                    ->live()
                    ->columnSpan(1),

                Forms\Components\Placeholder::make('icon_preview')
                    ->label('Voorbeeld')
                    ->content(fn (\Filament\Schemas\Components\Utilities\Get $get): \Illuminate\Support\HtmlString =>
                        new \Illuminate\Support\HtmlString(
                            '<span class="material-icons" style="font-size:3rem;line-height:1;color:var(--primary-600,#2563eb)">'
                            . e((string) ($get('icon') ?: 'info')) . '</span>'
                        ))
                    ->columnSpan(1),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Volgorde')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lager = eerder. Je kunt ook slepen in de lijst.')
                    ->columnSpan(1),

                Forms\Components\TextInput::make('title')
                    ->label('Titel')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('body')
                    ->label('Tekst')
                    ->required()
                    ->rows(4)
                    ->maxLength(2000)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Zichtbaar')
                    ->default(true)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->width(50)
                    ->sortable(),

                Tables\Columns\TextColumn::make('icon')
                    ->label('Icoon')
                    ->html()
                    ->formatStateUsing(fn($state) => new \Illuminate\Support\HtmlString(
                        '<span class="material-icons" style="font-size:1.4rem;vertical-align:middle">'
                        . e((string) $state) . '</span>&nbsp;'
                        . e(OnboardingSlide::$icons[$state] ?? (string) $state)
                    )),

                Tables\Columns\TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('body')
                    ->label('Tekst')
                    ->limit(70)
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Zichtbaar')
                    ->boolean()
                    ->alignCenter()
                    ->width(80),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Zichtbaar'),
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
            'index'  => Pages\ListOnboardingSlides::route('/'),
            'create' => Pages\CreateOnboardingSlide::route('/create'),
            'edit'   => Pages\EditOnboardingSlide::route('/{record}/edit'),
        ];
    }
}
