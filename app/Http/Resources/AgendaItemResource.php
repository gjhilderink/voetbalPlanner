<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AgendaRegistration;
use App\Services\IcsBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Agenda-item voor de app.
 *
 * Datums gaan als kant-en-klare strings mee (dateLabel, startTime, …) naast de
 * ruwe waarden. Reden: de opgeslagen tijd is Nederlandse wandkloktijd terwijl de
 * app-timezone UTC is; toIso8601String() zou er '+00:00' op plakken en de app
 * twee uur laten schuiven. De labels zijn hier al goed opgemaakt, precies zoals
 * MatchResource en BarDutyResource dat doen.
 *
 * De eigen aanmeldstatus komt uit een vooraf geladen 'myRegistration'-relatie
 * (zie AgendaController::index) zodat er geen query per item nodig is.
 */
class AgendaItemResource extends JsonResource
{
    private const WEEKDAYS = ['zo', 'ma', 'di', 'wo', 'do', 'vr', 'za'];

    private const MONTHS = [
        1 => 'januari', 'februari', 'maart', 'april', 'mei', 'juni',
        'juli', 'augustus', 'september', 'oktober', 'november', 'december',
    ];

    public function toArray(Request $request): array
    {
        $ics        = app(IcsBuilder::class);
        $start      = $this->starts_at;
        $end        = $this->ends_at;
        $myReg      = $this->resource->relationLoaded('myRegistration')
            ? $this->resource->getRelation('myRegistration')
            : null;
        // going_people/going_guests worden in de lijst als aggregaat meegeladen;
        // bij een los item vallen we terug op de (dan goedkope) query.
        $goingCount = $this->resource->going_people !== null
            ? (int) $this->resource->going_people + (int) $this->resource->going_guests
            : $this->goingCount();

        return [
            'id'      => $this->id,
            'title'   => $this->title,
            'summary' => (string) $this->summary,
            'description' => (string) $this->description,
            'extraInfo'   => (string) $this->extra_info,
            'imageUrl'    => $this->image_path ? asset('storage/' . $this->image_path) : '',

            'categoryId'    => $this->agenda_category_id,
            'categorySlug'  => $this->category?->slug ?? '',
            'categoryLabel' => $this->category?->name ?? 'Overig',
            'categoryColor' => $this->category?->color ?? '#6b7280',
            'categoryIcon'  => $this->category?->icon ?? 'event',

            'startDate' => $start->format('d-m-Y'),
            'startTime' => $this->is_all_day ? '' : $start->format('H:i'),
            'endDate'   => $end?->format('d-m-Y') ?? '',
            'endTime'   => ($this->is_all_day || ! $end) ? '' : $end->format('H:i'),
            'isAllDay'  => (bool) $this->is_all_day,
            'dateLabel' => self::dateLabel($start),
            'timeLabel' => self::timeLabel($this->is_all_day, $start, $end),
            'daysUntil' => (int) floor(now()->startOfDay()->diffInDays($start->copy()->startOfDay(), false)),
            'isPast'    => $this->isPast(),

            'location'    => (string) $this->location,
            'locationUrl' => (string) $this->location_url,
            'externalUrl' => (string) $this->external_url,

            'audience'      => $this->audience,
            'audienceLabel' => $this->audienceLabel(),

            'registrationEnabled'  => (bool) $this->registration_enabled,
            'registrationOpen'     => $this->isRegistrationOpen(),
            'registrationClosesAt' => $this->registration_closes_at?->format('d-m-Y H:i') ?? '',
            'capacity'         => $this->capacity,
            'goingCount'       => $goingCount,
            'spotsLeft'        => $this->spotsLeft(),
            'isFull'           => $this->isFull(),
            'allowGuests'      => (bool) $this->allow_guests,
            'showParticipants' => (bool) $this->show_participants,

            'myStatus'      => $myReg?->status ?? '',
            'myGuestCount'  => (int) ($myReg?->guest_count ?? 0),
            'isRegistered'  => $myReg?->status === AgendaRegistration::STATUS_GOING,
            'canRegister'   => $this->isRegistrationOpen()
                && ($myReg?->status !== AgendaRegistration::STATUS_GOING)
                && ! $this->isFull(),

            'icsUrl'             => url("/api/v1/agenda/{$this->id}/ics"),
            'googleCalendarUrl'  => $ics->googleUrl($this->resource),
            'outlookCalendarUrl' => $ics->outlookUrl($this->resource),

            'isHighlighted' => (bool) $this->is_highlighted,
        ];
    }

    /** "za 12 september" — of met jaartal als het niet dit jaar is. */
    private static function dateLabel(\Carbon\Carbon $date): string
    {
        $label = self::WEEKDAYS[(int) $date->format('w')]
            . ' ' . $date->format('j')
            . ' ' . self::MONTHS[(int) $date->format('n')];

        return $date->year === now()->year ? $label : $label . ' ' . $date->year;
    }

    private static function timeLabel(bool $isAllDay, \Carbon\Carbon $start, ?\Carbon\Carbon $end): string
    {
        if ($isAllDay) {
            return 'Hele dag';
        }

        if (! $end) {
            return $start->format('H:i');
        }

        // Meerdaags: de einddatum erbij, anders leest "10:00 – 17:00" verkeerd.
        return $end->isSameDay($start)
            ? $start->format('H:i') . ' – ' . $end->format('H:i')
            : $start->format('H:i') . ' – ' . self::dateLabel($end) . ' ' . $end->format('H:i');
    }
}
