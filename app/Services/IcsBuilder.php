<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AgendaItem;
use Carbon\Carbon;

/**
 * Bouwt iCalendar-bestanden (.ics) voor agenda-items, zonder externe package.
 *
 * TIJDZONE — de belangrijkste subtiliteit hier. config/app.php staat op 'UTC',
 * maar beheerders typen Nederlandse kloktijd en die wordt ongewijzigd opgeslagen
 * (net als matches.match_datetime). De waarde in de kolom is dus wandkloktijd in
 * Europe/Amsterdam, géén UTC. Daarom rekenen we niets om maar labelen we de tijd
 * met TZID, inclusief een VTIMEZONE-blok — zonder dat blok negeert Outlook de
 * TZID en interpreteert het de tijd als UTC, wat twee uur scheelt.
 *
 * Uitbreiden naar Google/Outlook: die willen wél UTC in de URL — zie
 * googleUrl()/outlookUrl(), waar de conversie expliciet gebeurt.
 */
class IcsBuilder
{
    public const TIMEZONE = 'Europe/Amsterdam';

    /** Standaardduur als een activiteit geen eindtijd heeft. */
    private const DEFAULT_DURATION_HOURS = 2;

    /** EU-zomertijdregels; nodig omdat Outlook een TZID zonder VTIMEZONE negeert. */
    private const VTIMEZONE = [
        'BEGIN:VTIMEZONE',
        'TZID:Europe/Amsterdam',
        'BEGIN:DAYLIGHT',
        'DTSTART:19700329T020000',
        'TZOFFSETFROM:+0100',
        'TZOFFSETTO:+0200',
        'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
        'TZNAME:CEST',
        'END:DAYLIGHT',
        'BEGIN:STANDARD',
        'DTSTART:19701025T030000',
        'TZOFFSETFROM:+0200',
        'TZOFFSETTO:+0100',
        'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
        'TZNAME:CET',
        'END:STANDARD',
        'END:VTIMEZONE',
    ];

    /** Eén activiteit als compleet .ics-bestand. */
    public function item(AgendaItem $item): string
    {
        return $this->calendar([$item]);
    }

    /**
     * Meerdere activiteiten in één bestand — de basis voor een latere
     * abonneerbare feed van de hele verenigingsagenda.
     *
     * @param iterable<AgendaItem> $items
     */
    public function calendar(iterable $items): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//VoetbalPlanner//Verenigingsagenda//NL',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            ...self::VTIMEZONE,
        ];

        foreach ($items as $item) {
            $lines = array_merge($lines, $this->vevent($item));
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    /** @return array<string> */
    private function vevent(AgendaItem $item): array
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:' . $item->id . '@voetbalplanner',
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'SEQUENCE:' . ($item->updated_at?->getTimestamp() ?? 0),
        ];

        if ($item->is_all_day) {
            // Bij hele dagen is DTEND exclusief: een activiteit op één dag
            // eindigt volgens de standaard op de dag erna.
            $lines[] = 'DTSTART;VALUE=DATE:' . $item->starts_at->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:' . ($item->ends_at ?? $item->starts_at)->copy()->addDay()->format('Ymd');
        } else {
            $end = $item->ends_at ?? $item->starts_at->copy()->addHours(self::DEFAULT_DURATION_HOURS);
            $lines[] = 'DTSTART;TZID=' . self::TIMEZONE . ':' . $item->starts_at->format('Ymd\THis');
            $lines[] = 'DTEND;TZID=' . self::TIMEZONE . ':' . $end->format('Ymd\THis');
        }

        $lines[] = 'SUMMARY:' . self::escape($item->title);

        $description = trim(strip_tags((string) ($item->description ?: $item->summary)));
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . self::escape($description);
        }
        if ($item->location) {
            $lines[] = 'LOCATION:' . self::escape($item->location);
        }
        if ($item->external_url) {
            $lines[] = 'URL:' . self::escape($item->external_url);
        }

        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    /** Kant-en-klare "toevoegen aan Google Agenda"-link (wil UTC). */
    public function googleUrl(AgendaItem $item): string
    {
        [$start, $end] = $this->utcRange($item);

        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action'   => 'TEMPLATE',
            'text'     => $item->title,
            'dates'    => $start->format('Ymd\THis\Z') . '/' . $end->format('Ymd\THis\Z'),
            'details'  => trim(strip_tags((string) ($item->description ?: $item->summary))),
            'location' => (string) $item->location,
        ]);
    }

    /** Kant-en-klare "toevoegen aan Outlook"-link (wil UTC). */
    public function outlookUrl(AgendaItem $item): string
    {
        [$start, $end] = $this->utcRange($item);

        return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query([
            'path'    => '/calendar/action/compose',
            'rru'     => 'addevent',
            'subject' => $item->title,
            'startdt' => $start->toIso8601ZuluString(),
            'enddt'   => $end->toIso8601ZuluString(),
            'body'    => trim(strip_tags((string) ($item->description ?: $item->summary))),
            'location' => (string) $item->location,
        ]);
    }

    /**
     * Begin en eind als echte UTC-momenten. De opgeslagen waarde is Amsterdamse
     * wandkloktijd, dus die lezen we expliciet als zodanig in vóór de conversie.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function utcRange(AgendaItem $item): array
    {
        $toUtc = fn (Carbon $d): Carbon => Carbon::parse($d->format('Y-m-d H:i:s'), self::TIMEZONE)->utc();

        $end = $item->ends_at ?? $item->starts_at->copy()->addHours(self::DEFAULT_DURATION_HOURS);

        return [$toUtc($item->starts_at), $toUtc($end)];
    }

    /** RFC 5545 §3.3.11: backslash, puntkomma, komma en regeleindes escapen. */
    private static function escape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\;', '\,', '\n', '\n', '\n'],
            $value,
        );
    }

    /** RFC 5545 §3.1: regels langer dan 75 octetten vouwen met CRLF + spatie. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = substr($line, 0, 75);
        foreach (str_split(substr($line, 75), 74) as $chunk) {
            $folded .= "\r\n " . $chunk;
        }

        return $folded;
    }
}
