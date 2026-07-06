<?php
/**
 * iCalendar (.ics) generation for events and feeds.
 */

class IcsGenerator
{
    public static function formatDateTime(string $iso): string
    {
        try {
            $dt = new DateTimeImmutable($iso);
            return $dt->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        } catch (Exception $e) {
            return gmdate('Ymd\THis\Z');
        }
    }

    public static function escapeText(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(';', '\;', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace(["\r\n", "\n", "\r"], '\n', $text);
        return $text;
    }

    /**
     * @param array<string, mixed> $event
     */
    public static function buildEvent(array $event, array $occurrence, string $siteName = 'Birkly'): string
    {
        $uid = ($occurrence['occurrence_id'] ?? uniqid('evt_', true)) . '@birkly';
        $summary = self::escapeText((string) ($event['entry_title'] ?? 'Event'));
        $location = self::escapeText((string) ($event['venue'] ?? ''));
        $description = '';
        if (!empty($event['virtual_url'])) {
            $description = self::escapeText('Join: ' . $event['virtual_url']);
        }

        $lines = [
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . self::formatDateTime((string) ($occurrence['starts_at'] ?? '')),
            'DTEND:' . self::formatDateTime((string) ($occurrence['ends_at'] ?? '')),
            'SUMMARY:' . $summary,
        ];

        if ($location !== '') {
            $lines[] = 'LOCATION:' . $location;
        }
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . $description;
        }

        $lines[] = 'END:VEVENT';
        return implode("\r\n", $lines);
    }

    /**
     * @param list<string> $vevents
     */
    public static function wrapCalendar(array $vevents, string $calName = 'Birkly Events'): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Birkly//Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::escapeText($calName),
        ];
        foreach ($vevents as $vevent) {
            $lines[] = $vevent;
        }
        $lines[] = 'END:VCALENDAR';
        return implode("\r\n", $lines) . "\r\n";
    }
}
