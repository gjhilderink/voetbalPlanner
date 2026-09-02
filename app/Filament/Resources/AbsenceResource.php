<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AbsenceResource\Pages;
use App\Models\Absence;
use App\Models\Team;
use App\Models\TrainingSchedule;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AbsenceResource extends Resource
{
    protected static ?string $model = Absence::class;
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-user-minus';
    protected static ?string $navigationLabel                  = 'Afmeldingen';
    protected static ?string $modelLabel                       = 'Afmelding';
    protected static ?string $pluralModelLabel                 = 'Afmeldingen';
    protected static string|\UnitEnum|null $navigationGroup    = 'Beheer';
    protected static ?int    $navigationSort                   = 26;
    protected static bool    $isScopedToTenant                 = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'coach']) ?? false;
    }

    // Alleen-lezen overzicht; afmelden/aanmelden gebeurt in de app.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin']) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query  = parent::getEloquentQuery()->with(['member', 'user', 'match', 'trainingSchedule.team']);
        $tenant = filament()->getTenant();
        if ($tenant) {
            $query->where('club_id', $tenant->id);
        }
        return $query->latest();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('wie')
                    ->label('Lid / account')
                    ->getStateUsing(fn(Absence $r) => $r->member?->name ?? $r->user?->name ?? '—')
                    // De naam komt uit twee relaties en staat dus in geen enkele
                    // kolom van deze tabel; zonder eigen zoekopdracht valt er
                    // niet op te zoeken. En dat is nu juist waarmee je begint:
                    // "waarom staat dit lid niet op honderd procent".
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where(fn (Builder $q) => $q
                            ->whereHas('member', fn (Builder $m) => $m->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%")))),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state === Absence::TYPE_MATCH ? 'Wedstrijd' : 'Training')
                    ->color(fn($state) => $state === Absence::TYPE_MATCH ? 'info' : 'success'),

                Tables\Columns\TextColumn::make('betreft')
                    ->label('Betreft')
                    ->getStateUsing(function (Absence $r): string {
                        if ($r->type === Absence::TYPE_TRAINING) {
                            $day  = $r->trainingSchedule ? (TrainingSchedule::$weekdayLabels[$r->trainingSchedule->weekday] ?? '') : '';
                            $date = $r->training_date?->format('d-m-Y') ?? '';
                            return trim("$day $date");
                        }
                        $opp  = $r->match?->opponent ?? '';
                        $date = $r->match?->match_datetime?->format('d-m-Y') ?? '';
                        return trim("$opp $date");
                    }),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reden')
                    ->wrap()
                    ->limit(80),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Afgemeld op')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        Absence::TYPE_TRAINING => 'Training',
                        Absence::TYPE_MATCH    => 'Wedstrijd',
                    ]),

                // Op elftal, want de opkomstcijfers gaan per elftal. Via het lid
                // en niet via de wedstrijd of het schema: dan vallen beide
                // soorten afmeldingen onder hetzelfde filter.
                Tables\Filters\SelectFilter::make('team')
                    ->label('Elftal')
                    ->options(fn (): array => Team::query()
                        ->when(
                            filament()->getTenant(),
                            fn (Builder $q, $club) => $q->where('club_id', $club->id),
                        )
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when(
                            $data['value'] ?? null,
                            fn (Builder $q, $teamId) => $q->whereHas(
                                'member.teams',
                                fn (Builder $t) => $t->where('teams.id', $teamId),
                            ),
                        )),
            ])
            ->actions([
                Actions\DeleteAction::make()
                    ->visible(fn(Absence $record) => static::canDelete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbsences::route('/'),
        ];
    }
}
