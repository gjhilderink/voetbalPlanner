<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Models\Member;
use App\Models\Team;
use Filament\Actions;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Leden';
    protected static ?string $modelLabel = 'Lid';
    protected static ?string $pluralModelLabel = 'Leden';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Persoonsgegevens')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label('Telefoon')
                    ->tel()
                    ->maxLength(20),
                Forms\Components\DatePicker::make('date_of_birth')
                    ->label('Geboortedatum'),
                Forms\Components\Select::make('role')
                    ->label('Rol')
                    ->options([
                        'player'  => 'Speler',
                        'coach'   => 'Coach',
                        'medical' => 'Medische staf',
                        'staff'   => 'Overige staf',
                    ])
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Actief')
                    ->default(true),
                Forms\Components\TextInput::make('external_id')
                    ->label('Extern ID (relatiecode)')
                    ->disabled(),
            ])->columns(2),

            Section::make('Teams')->schema([
                Forms\Components\Select::make('teams')
                    ->label('Gekoppelde teams')
                    ->multiple()
                    ->relationship('teams', 'name')
                    ->preload()
                    ->searchable()
                    ->columnSpanFull(),
            ]),
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
                Tables\Columns\TextColumn::make('teams.name')
                    ->label('Teams')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'player'  => 'Speler',
                        'coach'   => 'Coach',
                        'medical' => 'Medische staf',
                        'staff'   => 'Overige staf',
                        default   => $state,
                    })
                    ->color(fn($state) => match($state) {
                        'coach'   => 'warning',
                        'medical' => 'danger',
                        'staff'   => 'gray',
                        default   => 'primary',
                    }),
                Tables\Columns\TextColumn::make('email')->label('E-mail')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')->label('Telefoon')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')->label('Actief')->boolean(),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Laatste sync')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('teams')
                    ->label('Team')
                    ->relationship('teams', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rol')
                    ->options([
                        'player'  => 'Speler',
                        'coach'   => 'Coach',
                        'medical' => 'Medische staf',
                        'staff'   => 'Overige staf',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label('Actief'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user  = auth()->user();

        if (!$user || $user->isAdmin()) {
            return $query;
        }

        $teamIds = $user->managedTeamIds();
        return $query->whereHas('teams', fn($q) => $q->whereIn('teams.id', $teamIds));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit'   => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}
