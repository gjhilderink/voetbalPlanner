<?php

declare(strict_types=1);

namespace App\Filament\Resources\ReleaseNoteResource\RelationManagers;

use App\Models\ReleaseNoteSend;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Alleen-lezen verzendlog onder een release note: naar wie, wanneer, door wie
 * en met welk bereik/resultaat er gemaild is.
 */
class SendsRelationManager extends RelationManager
{
    protected static string $relationship = 'sends';

    protected static ?string $title = 'Verzendlog';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Ontvanger')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('scope')
                    ->label('Bereik')
                    ->badge()
                    ->formatStateUsing(fn($state) => ReleaseNoteSend::$scopeLabels[$state] ?? $state)
                    ->color(fn($state) => match ($state) {
                        'all'      => 'success',
                        'selected' => 'info',
                        default    => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn($state) => $state === 'sent' ? 'Verstuurd' : 'Mislukt')
                    ->color(fn($state) => $state === 'sent' ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('sentBy.name')
                    ->label('Door')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Wanneer')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('sent_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('scope')
                    ->label('Bereik')
                    ->options(ReleaseNoteSend::$scopeLabels),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(['sent' => 'Verstuurd', 'failed' => 'Mislukt']),
            ]);
    }

    // Alleen-lezen: geen aanmaken via dit paneel.
    public function canCreate(): bool
    {
        return false;
    }
}
