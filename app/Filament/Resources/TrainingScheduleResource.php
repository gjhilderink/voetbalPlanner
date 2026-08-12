<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TrainingScheduleResource\Pages;
use App\Filament\Support\TeamFilter;
use App\Models\Team;
use App\Models\TrainingSchedule;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TrainingScheduleResource extends Resource
{
    protected static ?string $model = TrainingSchedule::class;
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel                  = 'Trainingen';
    protected static ?string $modelLabel                       = 'Trainingsschema';
    protected static ?string $pluralModelLabel                 = 'Trainingen';
    protected static string|\UnitEnum|null $navigationGroup    = 'Beheer';
    protected static ?int    $navigationSort                   = 25;
    protected static bool    $isScopedToTenant                 = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'coach']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'coach']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'coach']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'coach']) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query  = parent::getEloquentQuery()->with('team');
        $tenant = filament()->getTenant();
        if ($tenant) {
            $query->where('club_id', $tenant->id);
        }
        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Trainingsmoment')->columns(2)->schema([
                Forms\Components\Select::make('team_id')
                    ->label('Team')
                    ->required()
                    ->searchable()
                    ->options(function (): array {
                        $query  = Team::query();
                        $tenant = filament()->getTenant();
                        if ($tenant) {
                            $query->where('club_id', $tenant->id);
                        }
                        return $query->orderBy('name')->pluck('name', 'id')->all();
                    })
                    ->columnSpanFull(),

                Forms\Components\Select::make('weekday')
                    ->label('Dag')
                    ->options(TrainingSchedule::$weekdayLabels)
                    ->required()
                    ->columnSpan(1),

                Forms\Components\TextInput::make('location')
                    ->label('Locatie')
                    ->maxLength(255)
                    ->columnSpan(1),

                Forms\Components\TextInput::make('dressing_room')
                    ->label('Kleedkamer')
                    ->maxLength(255)
                    ->columnSpan(1),

                Forms\Components\TimePicker::make('start_time')
                    ->label('Begintijd')
                    ->seconds(false)
                    ->required()
                    ->columnSpan(1),

                Forms\Components\TimePicker::make('end_time')
                    ->label('Eindtijd')
                    ->seconds(false)
                    ->columnSpan(1),

                Forms\Components\Toggle::make('is_active')
                    ->label('Actief')
                    ->helperText('Uit = deze training wordt niet in de app getoond.')
                    ->default(true)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('team.name')
                    ->label('Team')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('weekday')
                    ->label('Dag')
                    ->formatStateUsing(fn($state) => TrainingSchedule::$weekdayLabels[$state] ?? $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Begin')
                    ->formatStateUsing(fn($state) => $state ? substr((string) $state, 0, 5) : '')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('Eind')
                    ->formatStateUsing(fn($state) => $state ? substr((string) $state, 0, 5) : '—')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('location')
                    ->label('Locatie')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('dressing_room')
                    ->label('Kleedkamer')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->defaultSort('weekday')
            ->filters([
                Tables\Filters\SelectFilter::make('team_id')
                    ->label('Team')
                    ->relationship('team', 'name', modifyQueryUsing: fn (Builder $query) => TeamFilter::scopeQuery($query))
                    ->searchable()
                    ->preload()
                    ->placeholder('Alle teams'),
            ])
            ->actions([
                Actions\EditAction::make()->visible(fn() => static::canCreate()),
                Actions\DeleteAction::make()->visible(fn() => static::canCreate()),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()->visible(fn() => static::canCreate()),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTrainingSchedules::route('/'),
            'create' => Pages\CreateTrainingSchedule::route('/create'),
            'edit'   => Pages\EditTrainingSchedule::route('/{record}/edit'),
        ];
    }
}
