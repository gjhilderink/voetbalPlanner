<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\BarDutyResource\Pages;
use App\Models\BarDuty;
use App\Models\Member;
use App\Models\Team;
use App\Services\WhatsAppService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BarDutyResource extends Resource
{
    protected static ?string $model = BarDuty::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Bardiensten';
    protected static ?string $modelLabel = 'Bardienst';
    protected static ?string $pluralModelLabel = 'Bardiensten';
    protected static string|\UnitEnum|null $navigationGroup = 'Planning';
    protected static ?int $navigationSort = 30;
    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'bar_commissie', 'coach']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'bar_commissie']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'bar_commissie']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'bar_commissie']) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query  = parent::getEloquentQuery();
        $user   = auth()->user();
        $tenant = filament()->getTenant();

        if ($tenant) {
            $query->where('club_id', $tenant->id);
        }

        if (!$user?->isAdmin() && !$user?->hasRole('bar_commissie')) {
            $query->whereIn('team_id', $user->managedTeamIds());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Bardienst')->schema([
                Forms\Components\DatePicker::make('date')
                    ->label('Datum')
                    ->displayFormat('d-m-Y')
                    ->live()
                    ->required(),
                Forms\Components\Select::make('shift')
                    ->label('Dagdeel')
                    ->options(fn(\Filament\Schemas\Components\Utilities\Get $get) => $get('date')
                        ? collect(\App\Models\BarDuty::shiftsForDate(\Carbon\Carbon::parse($get('date'))))
                            ->mapWithKeys(fn($def, $key) => [$key => "{$def['label']} ({$def['start']}–{$def['end']})"])
                            ->all()
                        : [])
                    ->helperText('Alleen op zaterdag en zondag zijn er dagdelen.')
                    ->required(),
                Forms\Components\Select::make('team_id')
                    ->label('Elftal (verantwoordelijk)')
                    ->options(function (): array {
                        $tenant = filament()->getTenant();
                        $query  = Team::where('is_active', true)->orderBy('name');
                        if ($tenant) {
                            $query->where('club_id', $tenant->id);
                        }
                        return $query->pluck('name', 'id')->all();
                    })
                    ->required()
                    ->live(),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'open'      => 'Open',
                        'bevestigd' => 'Bevestigd',
                        'vervuld'   => 'Vervuld',
                    ])
                    ->default('open')
                    ->required(),
                Forms\Components\Select::make('members')
                    ->label('Ingeplande leden')
                    ->multiple()
                    ->maxItems(fn(Get $get) => \App\Models\BarDuty::SHIFTS[$get('shift')]['required'] ?? 2)
                    ->relationship(
                        name: 'members',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn(Builder $query, Get $get) => $query
                            ->when(
                                $get('team_id'),
                                fn($q, $teamId) => $q->whereHas('teams', fn($t) => $t->where('teams.id', $teamId)),
                                fn($q) => $q->whereRaw('1=0'),
                            )
                            ->where('is_active', true)
                            ->orderBy('name'),
                    )
                    ->helperText('Selecteer eerst een elftal, dan maximaal 2 leden')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('notes')
                    ->label('Opmerkingen')
                    ->rows(3)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Datum')
                    ->formatStateUsing(fn($state) => $state?->locale('nl')->isoFormat('ddd DD-MM-YYYY'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('shift')
                    ->label('Dagdeel')
                    ->badge()
                    ->formatStateUsing(function ($state, $record) {
                        $def = \App\Models\BarDuty::SHIFTS[$state] ?? null;
                        return $def
                            ? "{$def['label']} ({$def['start']}–{$def['end']})"
                            : ucfirst((string) $state);
                    })
                    ->color('info'),
                Tables\Columns\TextColumn::make('team.name')
                    ->label('Elftal')
                    ->sortable(),
                Tables\Columns\TextColumn::make('members.name')
                    ->label('Ingepland')
                    ->separator(', ')
                    ->default('-'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'open'      => 'Open',
                        'bevestigd' => 'Bevestigd',
                        'vervuld'   => 'Vervuld',
                        default     => $state,
                    })
                    ->color(fn($state) => match($state) {
                        'open'      => 'warning',
                        'bevestigd' => 'info',
                        'vervuld'   => 'success',
                        default     => 'gray',
                    }),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Opmerkingen')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('verleden')
                    ->label('Periode')
                    ->placeholder('Alleen komende')
                    ->trueLabel('Alles (incl. verleden)')
                    ->falseLabel('Alleen verleden')
                    ->queries(
                        true:  fn(Builder $q) => $q,
                        false: fn(Builder $q) => $q->whereDate('date', '<', now()->toDateString()),
                        blank: fn(Builder $q) => $q->whereDate('date', '>=', now()->toDateString()),
                    ),
                SelectFilter::make('team_id')
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
                SelectFilter::make('shift')
                    ->label('Dagdeel')
                    ->options(collect(\App\Models\BarDuty::SHIFTS)->mapWithKeys(fn($def, $key) => [
                        $key => ($def['day'] === 6 ? 'Za ' : 'Zo ') . $def['label'],
                    ])->all()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'open'      => 'Open',
                        'bevestigd' => 'Bevestigd',
                        'vervuld'   => 'Vervuld',
                    ]),
                Filter::make('period')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Van')->displayFormat('d-m-Y'),
                        Forms\Components\DatePicker::make('until')->label('Tot')->displayFormat('d-m-Y'),
                    ])
                    ->query(fn(Builder $query, array $data) => $query
                        ->when($data['from'],  fn($q) => $q->whereDate('date', '>=', $data['from']))
                        ->when($data['until'], fn($q) => $q->whereDate('date', '<=', $data['until'])))
                    ->indicateUsing(function (array $data): ?string {
                        if (!$data['from'] && !$data['until']) return null;
                        $from  = $data['from']  ? \Carbon\Carbon::parse($data['from'])->format('d-m-Y')  : '...';
                        $until = $data['until'] ? \Carbon\Carbon::parse($data['until'])->format('d-m-Y') : '...';
                        return "Periode: {$from} t/m {$until}";
                    }),
            ])
            ->groups([
                Group::make('date')
                    ->label('Week')
                    ->getTitleFromRecordUsing(function (BarDuty $record): string {
                        $dt    = $record->date;
                        $start = $dt->copy()->startOfWeek()->locale('nl')->isoFormat('D MMM');
                        $end   = $dt->copy()->endOfWeek()->locale('nl')->isoFormat('D MMM YYYY');
                        return "Week {$dt->weekOfYear} ({$start} t/m {$end})";
                    })
                    ->orderQueryUsing(fn($query, $direction) => $query->orderBy('date', $direction))
                    ->collapsible(),
                Group::make('team.name')
                    ->label('Elftal')
                    ->collapsible(),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn(BarDuty $record) => self::canEdit($record)),

                Actions\Action::make('assignMembers')
                    ->label('Leden toewijzen')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->modalHeading(fn(BarDuty $record) => 'Leden toewijzen — '
                        . ($record->team?->name ?? '') . ' · '
                        . $record->date?->locale('nl')->isoFormat('ddd D MMM'))
                    ->visible(fn(BarDuty $record): bool => auth()->user()?->hasRole('coach')
                        && in_array($record->team_id, auth()->user()->managedTeamIds()->all()))
                    ->form(fn(BarDuty $record): array => [
                        Forms\Components\Select::make('members')
                            ->label('Leden')
                            ->multiple()
                            ->maxItems($record->requiredCount())
                            ->options(
                                Member::query()
                                    ->when(
                                        $record->team_id,
                                        fn($q) => $q->whereHas('teams', fn($t) => $t->where('teams.id', $record->team_id)),
                                    )
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all()
                            )
                            ->default($record->members->pluck('id')->all())
                            ->helperText('Selecteer maximaal ' . $record->requiredCount() . ' leden uit het elftal'),
                    ])
                    ->action(function (BarDuty $record, array $data): void {
                        $record->members()->sync($data['members'] ?? []);
                        $record->refreshStatus();
                        Notification::make()
                            ->success()
                            ->title('Leden opgeslagen')
                            ->send();
                    }),

                Actions\Action::make('notifyWhatsApp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->visible(fn(BarDuty $record): bool =>
                        $record->members->isNotEmpty()
                        && app(WhatsAppService::class)->forClub(filament()->getTenant()?->id)->isConfigured()
                        && auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'bar_commissie'])
                    )
                    ->form(fn(BarDuty $record): array => [
                        Forms\Components\Placeholder::make('recipients')
                            ->label('Ontvangers')
                            ->content($record->members->pluck('name')->join(', ')),
                        Forms\Components\Textarea::make('message')
                            ->label('Bericht')
                            ->default(
                                'Hallo! Je staat ingepland voor bardienst op '
                                . $record->date?->locale('nl')->isoFormat('ddd D MMMM')
                                . ' (' . $record->shiftLabel()
                                . ($record->timeRange() ? ' ' . $record->timeRange() : '') . ')'
                                . '. Graag aanwezig zijn!'
                            )
                            ->rows(4)
                            ->required(),
                    ])
                    ->action(function (BarDuty $record, array $data): void {
                        $service  = app(WhatsAppService::class)->forClub(filament()->getTenant()?->id);
                        $sent     = 0;
                        $failed   = [];

                        foreach ($record->members as $member) {
                            if (empty($member->phone)) {
                                $failed[] = $member->name . ' (geen nummer)';
                                continue;
                            }
                            $result = $service->sendMessage($member->phone, $data['message']);
                            $result['success'] ? $sent++ : ($failed[] = $member->name . ': ' . $result['error']);
                        }

                        if ($sent > 0 && empty($failed)) {
                            Notification::make()->success()->title("WhatsApp verstuurd naar {$sent} leden")->send();
                        } elseif ($sent > 0) {
                            Notification::make()->warning()
                                ->title("{$sent} verstuurd, " . count($failed) . ' mislukt')
                                ->body(implode("\n", $failed))
                                ->send();
                        } else {
                            Notification::make()->danger()->title('Versturen mislukt')->body(implode("\n", $failed))->send();
                        }
                    }),

                Actions\DeleteAction::make()
                    ->visible(fn(BarDuty $record) => self::canDelete($record)),
            ])
            ->defaultGroup('date')
            ->defaultSort('date')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBarDuties::route('/'),
            'create' => Pages\CreateBarDuty::route('/create'),
            'edit'   => Pages\EditBarDuty::route('/{record}/edit'),
        ];
    }
}
