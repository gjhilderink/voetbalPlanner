<?php

declare(strict_types=1);

namespace App\Filament\Resources\AgendaItemResource\RelationManagers;

use App\Models\Room;
use App\Models\RoomReservation;
use App\Services\RoomReservationService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * De ruimtes die voor deze activiteit zijn vastgelegd.
 *
 * Bij het agenda-item en niet ergens apart: je legt een ruimte vast op het
 * moment dat je de activiteit klaarzet, en dan staan de tijden er al. Ze hier
 * overtypen zou de enige plek zijn waar ze uit elkaar kunnen lopen.
 */
class RoomReservationsRelationManager extends RelationManager
{
    protected static string $relationship = 'roomReservations';

    protected static ?string $title = 'Ruimtes';

    /** Alleen tonen als de club de module gebruikt. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) $ownerRecord->club?->rooms_enabled;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\ColorColumn::make('room.color')
                    ->label('')
                    ->width(40),

                Tables\Columns\TextColumn::make('room.name')
                    ->label('Ruimte')
                    ->description(fn (RoomReservation $record): ?string => $record->notes),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Van')
                    ->dateTime('d-m-Y H:i')
                    // Verzet iemand de activiteit, dan schuift de ruimte niet
                    // mee: dat automatisch doen zou op een bezette ruimte kunnen
                    // stuklopen, en dan zou de reservering stilletjes verdwijnen.
                    // Hier alleen laten zien dát het uit elkaar loopt.
                    ->description(function (RoomReservation $record): ?string {
                        $item = $record->agendaItem;

                        if (! $item?->starts_at || $item->starts_at->equalTo($record->starts_at)) {
                            return null;
                        }

                        return 'Activiteit begint om ' . $item->starts_at->format('H:i');
                    })
                    ->color(fn (RoomReservation $record): ?string => $record->agendaItem?->starts_at
                        && ! $record->agendaItem->starts_at->equalTo($record->starts_at)
                            ? 'warning'
                            : null),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Tot')
                    ->dateTime('H:i'),

                Tables\Columns\IconColumn::make('is_private')
                    ->label('Privé')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === RoomReservation::STATUS_BEVESTIGD
                        ? 'Bevestigd'
                        : 'Geannuleerd')
                    ->color(fn (string $state): string => $state === RoomReservation::STATUS_BEVESTIGD
                        ? 'success'
                        : 'gray'),
            ])
            ->defaultSort('starts_at')
            ->headerActions([
                // Een eigen actie en geen CreateAction: een reservering moet
                // langs RoomReservationService, want daar zit de controle op
                // dubbele boekingen. Rechtstreeks wegschrijven via de relatie
                // zou die controle overslaan.
                Actions\Action::make('reserveerRuimte')
                    ->label('Ruimte reserveren')
                    ->icon('heroicon-o-building-office-2')
                    ->modalSubmitActionLabel('Reserveren')
                    ->fillForm(fn (): array => $this->beginwaarden())
                    ->form(fn (): array => $this->velden())
                    ->action(function (array $data): void {
                        $this->leggVast($data);
                    }),
            ])
            ->actions([
                Actions\Action::make('annuleer')
                    ->label('Annuleren')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (RoomReservation $record): bool => ! $record->isGeannuleerd())
                    ->action(function (RoomReservation $record): void {
                        app(RoomReservationService::class)->annuleer($record);

                        Notification::make()->success()->title('Reservering geannuleerd')->send();
                    }),
            ])
            ->emptyStateHeading('Nog geen ruimte vastgelegd')
            ->emptyStateDescription('Reserveer de kantine of een kleedkamer voor deze activiteit; de tijden worden overgenomen.');
    }

    /**
     * De begintijden komen van de activiteit.
     *
     * Duurt de activiteit een hele dag of is er geen eindtijd, dan een blok van
     * twee uur vanaf de starttijd: een reservering zonder eind is er geen.
     *
     * @return array<string, mixed>
     */
    private function beginwaarden(): array
    {
        $item  = $this->getOwnerRecord();
        $begin = $item->starts_at ? Carbon::parse($item->starts_at) : Carbon::now();
        $eind  = $item->ends_at ? Carbon::parse($item->ends_at) : $begin->copy()->addHours(2);

        if ($eind->lessThanOrEqualTo($begin)) {
            $eind = $begin->copy()->addHours(2);
        }

        return [
            'datum'      => $begin->toDateString(),
            'van'        => $begin->format('H:i'),
            'tot'        => $eind->format('H:i'),
            'title'      => $item->title,
            'is_private' => false,
        ];
    }

    /** @return array<int, mixed> */
    private function velden(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\Select::make('room_id')
                    ->label('Ruimte')
                    ->options(fn (): array => Room::query()
                        ->where('club_id', $this->getOwnerRecord()->club_id)
                        ->actief()
                        ->geordend()
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('title')
                    ->label('Waarvoor')
                    ->required()
                    ->maxLength(120)
                    ->helperText('Overgenomen van de activiteit; je kunt het aanpassen.')
                    ->columnSpanFull(),

                Forms\Components\DatePicker::make('datum')
                    ->label('Datum')
                    ->displayFormat('d-m-Y')
                    ->required(),

                Forms\Components\Toggle::make('is_private')
                    ->label('Privé')
                    ->helperText('De ruimte staat dan als bezet, zonder titel en zonder naam.'),

                Forms\Components\TimePicker::make('van')
                    ->label('Van')
                    ->seconds(false)->format('H:i')->displayFormat('H:i')
                    ->required(),

                Forms\Components\TimePicker::make('tot')
                    ->label('Tot')
                    ->seconds(false)->format('H:i')->displayFormat('H:i')
                    ->required(),

                Forms\Components\Textarea::make('notes')
                    ->label('Opmerkingen')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]),
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function leggVast(array $data): void
    {
        $item = $this->getOwnerRecord();

        $room = Room::where('club_id', $item->club_id)->find($data['room_id'] ?? '');

        if (! $room) {
            Notification::make()->danger()->title('Deze ruimte bestaat niet meer')->send();

            return;
        }

        $datum = Carbon::parse($data['datum']);
        $begin = $datum->copy()->setTimeFromTimeString((string) $data['van']);
        $eind  = $datum->copy()->setTimeFromTimeString((string) $data['tot']);

        // Voorbij middernacht hoort op de volgende dag: een feest van 21:00 tot
        // 01:00 is één reservering en geen fout.
        if ($eind->lessThanOrEqualTo($begin)) {
            $eind->addDay();
        }

        $uitkomst = app(RoomReservationService::class)->reserveer(
            $room,
            $begin,
            $eind,
            auth()->user(),
            [
                'title'          => $data['title'] ?? $item->title,
                'notes'          => $data['notes'] ?? null,
                'is_private'     => (bool) ($data['is_private'] ?? false),
                'agenda_item_id' => $item->id,
                'source'         => RoomReservation::SOURCE_PORTAL,
            ],
        );

        if (! $uitkomst['ok']) {
            Notification::make()->danger()
                ->title('Niet gereserveerd')
                ->body($uitkomst['error'] ?? '')
                ->send();

            return;
        }

        Notification::make()->success()
            ->title($room->name . ' is gereserveerd')
            ->send();
    }
}
