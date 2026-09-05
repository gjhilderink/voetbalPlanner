<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    private static array $statusLabels = [
        'scheduled'  => 'Gepland',
        'cancelled'  => 'Afgelast',
        'postponed'  => 'Uitgesteld',
        'played'     => 'Gespeeld',
        'completed'  => 'Gespeeld',
        'finished'   => 'Gespeeld',
        'live'       => 'Live',
    ];

    private const WEEKDAYS = ['zondag', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag'];

    private const MONTHS = [
        1 => 'januari', 'februari', 'maart', 'april', 'mei', 'juni',
        'juli', 'augustus', 'september', 'oktober', 'november', 'december',
    ];

    public function toArray(Request $request): array
    {
        // Een afgelasting van de coach staat in een eigen kolom en niet in
        // status - anders zou de Sportlink-synchronisatie hem er de volgende
        // ochtend weer uit halen. Voor de lijsten in de app maakt de herkomst
        // niet uit: afgelast is afgelast.
        $rawStatus  = $this->cancelled_at !== null ? 'cancelled' : ($this->status ?? '');
        $myMemberId = $request->user()?->resolveMember()?->id;

        return [
            'id'             => $this->id,
            'opponent'       => $this->opponent ?? '',
            'opponentLogo'   => $this->opponent_logo ?? '',
            'location'       => $this->location ?? '',
            'matchDatetime'  => $this->match_datetime?->format('d-m-Y H:i') ?? '',
            // Kant-en-klare labels voor het dashboard ("zaterdag 24 mei" /
            // "10:00"); de app kan een datumstring niet zelf opsplitsen.
            'dateLabel'      => $this->match_datetime
                ? self::dateLabel($this->match_datetime)
                : '',
            'timeLabel'      => $this->match_datetime?->format('H:i') ?? '',
            // Alleen gevuld als iemand de tijd met de hand heeft gezet én de
            // bond inmiddels iets anders zegt. Dan hoort dat in beeld: een
            // verzette wedstrijd die achter een eigen afspraak verdwijnt is
            // erger dan een regel extra op het scherm.
            'sportlinkTimeLabel' => ($this->sportlink_datetime
                && $this->sportlink_datetime->format('H:i') !== $this->match_datetime?->format('H:i'))
                ? $this->sportlink_datetime->format('H:i')
                : '',
            // Is de tijd handmatig gezet? De app toont dan de knop om hem terug
            // te zetten naar wat de bond doorgeeft.
            'timeIsCustom'   => $this->sportlink_datetime !== null ? 'true' : 'false',
            // arrival_time is een tijdkolom zonder cast, dus ruw '14:30:00'.
            // Afkappen op H:i, anders staan de seconden in de app.
            'arrivalTime'    => substr((string) ($this->arrival_time ?? ''), 0, 5),
            // Alleen gevuld als iemand de verzameltijd met de hand heeft gezet
            // én de bond inmiddels iets anders zegt.
            'sportlinkArrivalLabel' => ($this->sportlink_arrival_time
                && substr((string) $this->sportlink_arrival_time, 0, 5) !== substr((string) ($this->arrival_time ?? ''), 0, 5))
                ? substr((string) $this->sportlink_arrival_time, 0, 5)
                : '',
            'arrivalIsCustom' => $this->sportlink_arrival_time !== null ? 'true' : 'false',
            'isHome'         => (bool) $this->is_home,
            'status'         => self::$statusLabels[strtolower($rawStatus)] ?? $rawStatus,
            'scoreHome'      => $this->score_home ?? 0,
            'scoreAway'      => $this->score_away ?? 0,
            'teamName'       => $this->team?->name ?? '',
            'teamId'         => $this->team_id ?? '',
            'coachName'      => $this->whenLoaded(
                'coaches',
                fn() => $this->coaches->isNotEmpty()
                    ? $this->coaches->pluck('name')->join(', ')
                    : ($this->coach?->name ?? ''),
                $this->coach?->name ?? ''
            ),
            'fruitHeroName'  => $this->fruitHero?->name ?? '',
            'fruitHeroId'    => $this->fruit_hero_id ?? '',
            'vlaggerName'    => $this->vlagger?->name ?? '',
            'vlaggerId'      => $this->vlagger_id ?? '',
            'notes'          => $this->notes ?? '',
            'isAfgelast'     => $this->cancelled_at !== null
                || strtolower((string) ($this->status ?? '')) === 'cancelled',
            'afgelastReden'  => (string) ($this->cancel_reason ?? ''),
            'isFruitHero'    => $myMemberId
                ? $this->fruit_hero_id === $myMemberId
                : false,
            'isVlagger'      => $myMemberId
                ? $this->vlagger_id === $myMemberId
                : false,
            'isDriver'       => $myMemberId
                ? (bool) $this->whenLoaded(
                    'drivers',
                    fn() => $this->drivers->contains('id', $myMemberId),
                    false,
                )
                : false,
            'driverNames'    => $this->whenLoaded(
                'drivers',
                fn() => $this->drivers->pluck('name')->join(', '),
                '',
            ),
        ];
    }

    /** "zaterdag 24 mei" — of met jaartal als de wedstrijd niet dit jaar is. */
    private static function dateLabel(\Carbon\Carbon $date): string
    {
        $label = self::WEEKDAYS[(int) $date->format('w')]
            . ' ' . $date->format('j')
            . ' ' . self::MONTHS[(int) $date->format('n')];

        return $date->year === now()->year ? $label : $label . ' ' . $date->year;
    }
}
