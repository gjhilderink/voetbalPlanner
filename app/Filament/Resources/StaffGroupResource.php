<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\StaffGroupResource\Pages;
use App\Models\Member;
use App\Models\StaffGroup;
use App\Models\Team;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StaffGroupResource extends Resource
{
    protected static ?string $model = StaffGroup::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Staffgroepen';
    protected static ?string $modelLabel = 'Staffgroep';
    protected static ?string $pluralModelLabel = 'Staffgroepen';
    protected static string|\UnitEnum|null $navigationGroup = 'Beheer';
    protected static ?int $navigationSort = 20;
    protected static bool $isScopedToTenant = false;

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
        $query  = parent::getEloquentQuery()->with(['team', 'members']);
        $tenant = filament()->getTenant();

        if ($tenant) {
            $query->where('club_id', $tenant->id);
        }

        return $query->orderBy('name');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Staffgroep')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(100),
                Forms\Components\Select::make('team_id')
                    ->label('Gekoppeld elftal (optioneel)')
                    ->options(function (): array {
                        $tenant = filament()->getTenant();
                        $query  = Team::where('is_active', true)->orderBy('name');
                        if ($tenant) {
                            $query->where('club_id', $tenant->id);
                        }
                        return $query->pluck('name', 'id')->all();
                    })
                    ->placeholder('Geen (clubbreed)')
                    ->nullable(),
                Forms\Components\Textarea::make('description')
                    ->label('Omschrijving')
                    ->rows(3)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                Forms\Components\Select::make('members')
                    ->label('Leden')
                    ->multiple()
                    ->relationship(
                        name: 'members',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query): Builder {
                            $tenant = filament()->getTenant();
                            if ($tenant) {
                                $query->whereHas('teams', fn($t) => $t->where('club_id', $tenant->id));
                            }
                            return $query->where('is_active', true)->orderBy('name');
                        },
                    )
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('team.name')
                    ->label('Elftal')
                    ->default('Clubbreed')
                    ->sortable(),
                Tables\Columns\TextColumn::make('members_count')
                    ->label('Leden')
                    ->counts('members')
                    ->sortable(),
                Tables\Columns\TextColumn::make('members.name')
                    ->label('Samenstelling')
                    ->separator(', ')
                    ->limit(60)
                    ->default('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Omschrijving')
                    ->limit(50)
                    ->default('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('team_id')
                    ->label('Elftal')
                    ->options(function (): array {
                        $tenant = filament()->getTenant();
                        $query  = Team::where('is_active', true)->orderBy('name');
                        if ($tenant) {
                            $query->where('club_id', $tenant->id);
                        }
                        return $query->pluck('name', 'id')->all();
                    })
                    ->placeholder('Alle elftallen'),
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
            'index'  => Pages\ListStaffGroups::route('/'),
            'create' => Pages\CreateStaffGroup::route('/create'),
            'edit'   => Pages\EditStaffGroup::route('/{record}/edit'),
        ];
    }
}
