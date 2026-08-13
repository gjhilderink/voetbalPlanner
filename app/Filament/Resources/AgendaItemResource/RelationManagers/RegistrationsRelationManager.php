<?php

declare(strict_types=1);

namespace App\Filament\Resources\AgendaItemResource\RelationManagers;

use App\Models\AgendaRegistration;
use App\Models\Member;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Deelnemers van een activiteit: wie komt, met hoeveel introducés, en wie
 * expliciet heeft afgezegd. Handmatig toevoegen kan ook — niet iedereen meldt
 * zich via de app aan.
 */
class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'Deelnemers';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => AgendaRegistration::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === AgendaRegistration::STATUS_GOING ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('guest_count')
                    ->label('Introducés')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "+{$state}" : '—'),

                Tables\Columns\TextColumn::make('member.name')
                    ->label('Lid')
                    ->placeholder('— (account zonder lidprofiel)')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Aangemeld door')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('note')
                    ->label('Opmerking')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('registered_at')
                    ->label('Wanneer')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(AgendaRegistration::STATUSES),
            ])
            ->defaultSort('registered_at')
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('Deelnemer toevoegen')
                    ->form([
                        Forms\Components\Select::make('member_id')
                            ->label('Lid')
                            ->options(fn () => Member::query()
                                ->whereHas('teams', fn ($q) => $q->where('club_id', $this->getOwnerRecord()->club_id))
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->helperText('Laat leeg om iemand zonder lidprofiel toe te voegen.'),
                        Forms\Components\TextInput::make('name')
                            ->label('Naam')
                            ->helperText('Wordt overgenomen van het lid als je er een kiest.')
                            ->maxLength(120),
                        Forms\Components\TextInput::make('guest_count')
                            ->label('Introducés')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(10)
                            ->default(0),
                        Forms\Components\TextInput::make('note')
                            ->label('Opmerking')
                            ->maxLength(255),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $item     = $this->getOwnerRecord();
                        $memberId = $data['member_id'] ?? null;

                        $data['club_id'] = $item->club_id;
                        $data['status']  = AgendaRegistration::STATUS_GOING;
                        $data['name']    = $data['name']
                            ?: (Member::find($memberId)?->name ?? 'Onbekend');
                        // Handmatig toegevoegd: geen user_id, subject_key valt
                        // terug op het lid. Zonder lid maken we een eigen sleutel
                        // zodat de unique-index niet botst met andere handmatige rijen.
                        $data['subject_key'] = $memberId
                            ? AgendaRegistration::subjectKey($memberId, null)
                            : 'h:' . \Illuminate\Support\Str::uuid()->toString();

                        return $data;
                    }),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(AgendaRegistration::STATUSES)
                            ->required(),
                        Forms\Components\TextInput::make('guest_count')
                            ->label('Introducés')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(10),
                        Forms\Components\TextInput::make('note')
                            ->label('Opmerking')
                            ->maxLength(255),
                    ]),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
