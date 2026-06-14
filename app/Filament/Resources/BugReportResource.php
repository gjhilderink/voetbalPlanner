<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\BugReportResource\Pages;
use App\Models\BugReport;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class BugReportResource extends Resource
{
    protected static ?string $model = BugReport::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bug-ant';
    protected static ?string $navigationLabel = 'Bug meldingen';
    protected static ?string $modelLabel = 'Bug melding';
    protected static ?string $pluralModelLabel = 'Bug meldingen';
    protected static string|\UnitEnum|null $navigationGroup = 'Support';
    protected static ?int $navigationSort = 50;
    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
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

        return $query->with(['user', 'club']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Melding')->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Titel')
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->label('Omschrijving')
                    ->rows(8)
                    ->disabled()
                    ->columnSpanFull(),
            ]),

            Section::make('Schermafbeeldingen')
                ->schema([
                    Forms\Components\Placeholder::make('screenshots_preview')
                        ->label('')
                        ->content(function (BugReport $record): HtmlString {
                            $paths = $record->screenshot_paths ?? [];
                            if (empty($paths)) {
                                return new HtmlString('<em>Geen schermafbeeldingen.</em>');
                            }
                            $imgs = collect($paths)->map(function ($p) {
                                $url = asset('storage/' . $p);
                                return '<a href="' . $url . '" target="_blank">'
                                    . '<img src="' . $url . '" style="max-width:200px; max-height:200px; border:1px solid #ddd; border-radius:6px; margin:4px;" />'
                                    . '</a>';
                            })->implode('');
                            return new HtmlString('<div style="display:flex; flex-wrap:wrap; gap:8px;">' . $imgs . '</div>');
                        })
                        ->columnSpanFull(),
                ])
                ->collapsible(),

            Section::make('Apparaat & versie')->schema([
                Forms\Components\TextInput::make('app_version')
                    ->label('App versie')
                    ->disabled(),
                Forms\Components\TextInput::make('platform')
                    ->label('Platform')
                    ->disabled(),
                Forms\Components\TextInput::make('device_info')
                    ->label('Apparaat')
                    ->disabled()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Beheer')->schema([
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'open'        => 'Open',
                        'in_progress' => 'In behandeling',
                        'resolved'    => 'Opgelost',
                        'closed'      => 'Gesloten',
                    ])
                    ->required(),
                Forms\Components\Textarea::make('admin_notes')
                    ->label('Interne notities')
                    ->rows(4)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->limit(60),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Gemeld door')
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'open',
                        'info'    => 'in_progress',
                        'success' => 'resolved',
                        'gray'    => 'closed',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open'        => 'Open',
                        'in_progress' => 'In behandeling',
                        'resolved'    => 'Opgelost',
                        'closed'      => 'Gesloten',
                        default       => $state,
                    }),
                Tables\Columns\TextColumn::make('platform')
                    ->label('Platform')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('app_version')
                    ->label('Versie')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Gemeld')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'open'        => 'Open',
                        'in_progress' => 'In behandeling',
                        'resolved'    => 'Opgelost',
                        'closed'      => 'Gesloten',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make()->label('Openen'),
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
            'index' => Pages\ListBugReports::route('/'),
            'edit'  => Pages\EditBugReport::route('/{record}/edit'),
        ];
    }
}
