<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\NewsItemResource\Pages;
use App\Models\NewsItem;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NewsItemResource extends Resource
{
    protected static ?string $model = NewsItem::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';
    protected static ?string $navigationLabel                = 'Nieuws';
    protected static ?string $modelLabel                     = 'Nieuwsitem';
    protected static ?string $pluralModelLabel               = 'Nieuwsitems';
    protected static string|\UnitEnum|null $navigationGroup  = 'Communicatie';
    protected static ?int    $navigationSort                 = 30;
    protected static bool    $isScopedToTenant               = false;

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
        $user   = auth()->user();
        $tenant = filament()->getTenant();

        if ($tenant) {
            $query->where('club_id', $tenant->id);
        } elseif (! $user?->hasRole('super_admin') && $user?->club_id) {
            $query->where('club_id', $user->club_id);
        }

        return $query->with(['author']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Nieuwsbericht')->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Titel')
                    ->required()
                    ->maxLength(200)
                    ->columnSpanFull(),
                Forms\Components\Select::make('category')
                    ->label('Categorie')
                    ->options([
                        'jeugd'    => 'Jeugd',
                        'senioren' => 'Senioren',
                        'algemeen' => 'Algemeen',
                    ])
                    ->default('algemeen')
                    ->required(),
                Forms\Components\DateTimePicker::make('published_at')
                    ->label('Publicatie-datum')
                    ->default(now())
                    ->seconds(false),
                Forms\Components\FileUpload::make('image_path')
                    ->label('Afbeelding')
                    ->image()
                    ->maxSize(5120)
                    ->disk('public')
                    ->directory('news_images')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('body')
                    ->label('Inhoud')
                    ->required()
                    ->rows(10)
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_published')
                    ->label('Gepubliceerd')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Afbeelding')
                    ->disk('public')
                    ->height(48)
                    ->extraImgAttributes(['style' => 'object-fit:cover;border-radius:4px;'])
                    ->alignCenter()
                    ->width(80),

                Tables\Columns\TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->limit(60),

                Tables\Columns\TextColumn::make('category')
                    ->label('Categorie')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => NewsItem::categoryLabel($state))
                    ->color(fn(string $state): string => match ($state) {
                        'jeugd'    => 'success',
                        'senioren' => 'info',
                        default    => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('Live')
                    ->boolean(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Datum')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Auteur')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categorie')
                    ->options([
                        'jeugd'    => 'Jeugd',
                        'senioren' => 'Senioren',
                        'algemeen' => 'Algemeen',
                    ]),
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Gepubliceerd'),
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
            'index'  => Pages\ListNewsItems::route('/'),
            'create' => Pages\CreateNewsItem::route('/create'),
            'edit'   => Pages\EditNewsItem::route('/{record}/edit'),
        ];
    }
}
