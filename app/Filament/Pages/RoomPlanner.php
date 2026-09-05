<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\RoomResource;
use App\Models\Room;
use App\Models\RoomReservation;
use App\Services\RoomReservationService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Het weekoverzicht van de ruimtes: wie zit waar, en wat is er vrij.
 *
 * Ruimtes als rijen en dagen als kolommen, en niet een tijdraster van vierentwintig
 * uur. Een club heeft een handvol ruimtes en de vraag is bijna altijd "is de
 * kantine zaterdag vrij" - niet "wat gebeurt er dinsdag om kwart over drie".
 *
 * Gebouwd naar de bardienstplanner: dezelfde weeknavigatie, dezelfde
 * #[Computed]-opzet en dezelfde manier om de cache leeg te gooien na een
 * wijziging.
 */
class RoomPlanner extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel               = 'Ruimteplanner';
    protected static ?string $title                         = 'Ruimteplanner';
    protected static string|\UnitEnum|null $navigationGroup = 'Planning';
    protected static ?int $navigationSort                   = 26;
    protected string $view = 'filament.pages.room-planner';

    public string $weekStart = '';

    public static function canAccess(): bool
    {
        return (auth()->user()?->hasAnyRole(RoomResource::ROLLEN) ?? false)
            && RoomResource::moduleAan();
    }

    public function mount(): void
    {
        $this->weekStart = Carbon::now()->startOfWeek()->toDateString();
    }

    public function previousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->toDateString();
    }

    public function goToCurrentWeek(): void
    {
        $this->weekStart = Carbon::now()->startOfWeek()->toDateString();
    }

    /** @return array<int, Carbon> */
    #[Computed]
    public function weekDays(): array
    {
        $start = Carbon::parse($this->weekStart);

        return collect(range(0, 6))
            ->map(fn (int $i) => $start->copy()->addDays($i))
            ->all();
    }

    /** @return Collection<int, Room> */
    #[Computed]
    public function rooms(): Collection
    {
        return Room::query()
            ->where('club_id', $this->clubId())
            ->actief()
            ->geordend()
            ->get();
    }

    /** @return Collection<int, RoomReservation> */
    #[Computed]
    public function reservations(): Collection
    {
        $start = Carbon::parse($this->weekStart)->startOfDay();
        $eind  = $start->copy()->addDays(7);

        return RoomReservation::query()
            ->where('club_id', $this->clubId())
            ->bevestigd()
            ->overlapt($start, $eind)
            ->with(['agendaItem:id,title'])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * De reserveringen van één ruimte op één dag.
     *
     * Een meerdaagse reservering hoort op elke dag te staan waar hij overheen
     * loopt; anders lijkt de ruimte de dagen erna vrij.
     *
     * @return Collection<int, RoomReservation>
     */
    public function reservationsFor(string $roomId, string $date): Collection
    {
        $dag     = Carbon::parse($date)->startOfDay();
        $dagEind = $dag->copy()->addDay();

        return $this->reservations
            ->filter(fn (RoomReservation $r) => $r->room_id === $roomId
                && $r->starts_at?->lessThan($dagEind)
                && $r->ends_at?->greaterThan($dag))
            ->values();
    }

    /** Hoe deze reservering op de kaart heet, voor wie er nu kijkt. */
    public function labelVoor(RoomReservation $reservering): string
    {
        return $reservering->titelVoor(auth()->user());
    }

    private function clubId(): ?string
    {
        return filament()->getTenant()?->id ?? auth()->user()?->club_id;
    }

    /**
     * De velden van een reservering.
     *
     * Eén definitie voor toevoegen en bewerken: dezelfde vragen op twee plekken
     * lopen na de eerste wijziging uiteen.
     *
     * @return array<int, mixed>
     */
    protected function reserveringVelden(): array
    {
        return [
            Grid::make(2)->schema([
                Forms\Components\Select::make('room_id')
                    ->label('Ruimte')
                    ->options(fn (): array => $this->rooms->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('title')
                    ->label('Waarvoor')
                    ->placeholder('Bestuursvergadering')
                    ->required()
                    ->maxLength(120)
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

    /**
     * Reserveren. De kaart in de blade roept dit aan met de ruimte en de dag die
     * je aantikte, zodat je alleen de tijd nog hoeft in te vullen.
     */
    public function reserveerAction(): Action
    {
        return Action::make('reserveer')
            ->modalHeading('Ruimte reserveren')
            ->modalSubmitActionLabel('Reserveren')
            ->fillForm(fn (array $arguments): array => [
                'room_id'    => $arguments['room'] ?? null,
                'datum'      => $arguments['datum'] ?? $this->weekStart,
                'van'        => '19:00',
                'tot'        => '21:00',
                'is_private' => false,
            ])
            ->form(fn (): array => $this->reserveringVelden())
            ->action(function (array $data): void {
                $room = $this->rooms->firstWhere('id', $data['room_id']);

                if (! $room) {
                    Notification::make()->danger()->title('Deze ruimte bestaat niet meer')->send();

                    return;
                }

                [$begin, $eind] = $this->tijdvak($data);

                $uitkomst = app(RoomReservationService::class)->reserveer(
                    $room,
                    $begin,
                    $eind,
                    auth()->user(),
                    [
                        'title'      => $data['title'] ?? '',
                        'notes'      => $data['notes'] ?? null,
                        'is_private' => (bool) ($data['is_private'] ?? false),
                        'source'     => RoomReservation::SOURCE_PORTAL,
                    ],
                );

                if (! $uitkomst['ok']) {
                    Notification::make()->danger()
                        ->title('Niet gereserveerd')
                        ->body($uitkomst['error'] ?? '')
                        ->send();

                    return;
                }

                unset($this->reservations);

                Notification::make()->success()->title('Ruimte gereserveerd')->send();
            });
    }

    /** Een bestaande reservering openen vanuit het rooster. */
    public function editReservationAction(): Action
    {
        return Action::make('editReservation')
            ->modalHeading('Reservering aanpassen')
            ->modalSubmitActionLabel('Opslaan')
            ->fillForm(function (array $arguments): array {
                $r = $this->vindReservering($arguments['reservering'] ?? '');

                if (! $r) {
                    return [];
                }

                return [
                    'room_id'    => $r->room_id,
                    'title'      => $r->title,
                    'datum'      => $r->starts_at?->toDateString(),
                    'van'        => $r->starts_at?->format('H:i'),
                    'tot'        => $r->ends_at?->format('H:i'),
                    'is_private' => (bool) $r->is_private,
                    'notes'      => $r->notes,
                ];
            })
            ->form(fn (): array => $this->reserveringVelden())
            ->action(function (array $data, array $arguments): void {
                $r = $this->vindReservering($arguments['reservering'] ?? '');

                if (! $r) {
                    Notification::make()->danger()->title('Deze reservering bestaat niet meer')->send();

                    return;
                }

                // Een afspraak die uit Outlook komt hoort daar aangepast te
                // worden; hier zou de eerstvolgende leesronde het terugdraaien.
                if ($r->isExtern()) {
                    Notification::make()->warning()
                        ->title('Deze afspraak komt uit Outlook')
                        ->body('Pas hem daar aan; hier wordt hij bij de volgende synchronisatie overschreven.')
                        ->send();

                    return;
                }

                [$begin, $eind] = $this->tijdvak($data);

                $uitkomst = app(RoomReservationService::class)->verplaats($r, $begin, $eind);

                if (! $uitkomst['ok']) {
                    Notification::make()->danger()
                        ->title('Niet aangepast')
                        ->body($uitkomst['error'] ?? '')
                        ->send();

                    return;
                }

                $r->update([
                    'title'      => $data['title'] ?? $r->title,
                    'notes'      => $data['notes'] ?? null,
                    'is_private' => (bool) ($data['is_private'] ?? false),
                    'room_id'    => $data['room_id'] ?? $r->room_id,
                ]);

                unset($this->reservations);

                Notification::make()->success()->title('Reservering aangepast')->send();
            });
    }

    /**
     * Een reservering annuleren.
     *
     * Een gewone Livewire-methode met wire:confirm in de blade, en geen knop in
     * het bewerkscherm: dat is hetzelfde patroon als removeDuty in de
     * bardienstplanner, en het werkt zonder aannames over hoe een actie binnen
     * een ander modaal zich gedraagt.
     */
    public function annuleerReservering(string $id): void
    {
        $reservering = $this->vindReservering($id);

        if (! $reservering) {
            return;
        }

        app(RoomReservationService::class)->annuleer($reservering);

        unset($this->reservations);

        Notification::make()->success()->title('Reservering geannuleerd')->send();
    }

    /**
     * Datum plus twee tijden naar een begin en een eind.
     *
     * Loopt de eindtijd voorbij middernacht, dan hoort hij op de volgende dag:
     * een kantine die van 21:00 tot 01:00 open is, is één reservering en niet
     * een fout.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: Carbon, 1: Carbon}
     */
    private function tijdvak(array $data): array
    {
        $datum = Carbon::parse($data['datum']);
        $begin = $datum->copy()->setTimeFromTimeString((string) $data['van']);
        $eind  = $datum->copy()->setTimeFromTimeString((string) $data['tot']);

        if ($eind->lessThanOrEqualTo($begin)) {
            $eind->addDay();
        }

        return [$begin, $eind];
    }

    private function vindReservering(string $id): ?RoomReservation
    {
        if ($id === '') {
            return null;
        }

        return RoomReservation::where('club_id', $this->clubId())->find($id);
    }
}
