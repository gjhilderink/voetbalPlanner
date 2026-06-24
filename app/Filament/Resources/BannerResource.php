<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BannerResource extends Resource
{
    protected static ?string $model = Banner::class;
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel                  = 'Banners';
    protected static ?string $modelLabel                       = 'Banner';
    protected static ?string $pluralModelLabel                 = 'Banners';
    protected static string|\UnitEnum|null $navigationGroup    = 'Marketing';
    protected static ?int    $navigationSort                   = 60;
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
            Section::make('Banner')->columns(2)->schema([
                Forms\Components\Hidden::make('club_id')
                    ->default($clubId),

                Forms\Components\TextInput::make('title')
                    ->label('Naam (intern)')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->helperText('Alleen zichtbaar in het beheerpaneel.'),

                Forms\Components\TextInput::make('image_url')
                    ->label('Afbeelding URL')
                    ->required()
                    ->url()
                    ->maxLength(500)
                    ->columnSpanFull()
                    ->placeholder('https://...'),

                Forms\Components\TextInput::make('link_url')
                    ->label('Link URL (optioneel)')
                    ->url()
                    ->maxLength(500)
                    ->columnSpanFull()
                    ->placeholder('https://...'),

                Forms\Components\Select::make('position')
                    ->label('Positie')
                    ->options(Banner::$positionLabels)
                    ->required()
                    ->default('global')
                    ->columnSpan(1),

                Forms\Components\Toggle::make('is_active')
                    ->label('Actief')
                    ->default(true)
                    ->columnSpan(1),

                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('Zichtbaar vanaf')
                    ->nullable()
                    ->displayFormat('d-m-Y H:i')
                    ->columnSpan(1),

                Forms\Components\DateTimePicker::make('ends_at')
                    ->label('Zichtbaar tot')
                    ->nullable()
                    ->displayFormat('d-m-Y H:i')
                    ->columnSpan(1),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Afbeelding')
                    ->height(48)
                    ->extraImgAttributes(['style' => 'object-fit:cover;border-radius:4px;'])
                    ->alignCenter()
                    ->width(80),

                Tables\Columns\TextColumn::make('title')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('position')
                    ->label('Positie')
                    ->badge()
                    ->formatStateUsing(fn($state) => Banner::$positionLabels[$state] ?? $state)
                    ->color(fn($state) => match ($state) {
                        'wedstrijden' => 'info',
                        'bardiensten' => 'warning',
                        'rijschema'   => 'success',
                        'chat'        => 'primary',
                        'global'      => 'gray',
                        default       => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean()
                    ->alignCenter()
                    ->width(70),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Vanaf')
                    ->dateTime('d-m-Y')
                    ->placeholder('Altijd')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Tot')
                    ->dateTime('d-m-Y')
                    ->placeholder('Altijd')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Bijgewerkt')
                    ->date('d-m-Y')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('position')
                    ->label('Positie')
                    ->options(Banner::$positionLabels),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Actief'),
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
            'index'  => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit'   => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
