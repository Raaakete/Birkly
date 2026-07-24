<?php
/**
 * Recurrence expansion (v1) — daily/weekly/monthly with interval, until or count.
 */

require_once __DIR__ . '/EventSchema.php';

class EventRecurrence
{
    public const MAX_OCCURRENCES = 366;

    /**
     * @return list<array{occurrence_id: string, starts_at: string, ends_at: string, series_index: int}>
     */
    public static function expandOccurrences(array $eventValue, string $entryKey, ?DateTimeInterface $rangeStart = null, ?DateTimeInterface $rangeEnd = null): array
    {
        $event = EventSchema::normalizeValue($eventValue);
        $startsAt = $event['starts_at'];
        if ($startsAt === '') {
            return [];
        }

        try {
            $tz = new DateTimeZone($event['timezone'] ?: 'UTC');
            $start = new DateTimeImmutable($startsAt, $tz);
        } catch (Exception $e) {
            return [];
        }

        $durationSeconds = self::durationSeconds($event, $start);

        if (!$event['recurrence']['enabled']) {
            $occ = self::buildOccurrence($entryKey, 0, $start, $durationSeconds);
            return self::filterByRange([$occ], $rangeStart, $rangeEnd);
        }

        $occurrences = [];
        $rec = $event['recurrence'];
        $skipSet = array_flip($rec['skip_dates']);
        $until = null;
        if ($rec['until'] !== null) {
            try {
                $until = new DateTimeImmutable($rec['until'] . ' 23:59:59', $tz);
            } catch (Exception $e) {
                $until = null;
            }
        }

        $maxCount = $rec['count'] ?? self::MAX_OCCURRENCES;
        $maxCount = min((int) $maxCount, self::MAX_OCCURRENCES);

        $current = $start;
        $index = 0;

        while ($index < $maxCount && count($occurrences) < self::MAX_OCCURRENCES) {
            if ($until !== null && $current > $until) {
                break;
            }

            $dateKey = $current->format('Y-m-d');
            if (!isset($skipSet[$dateKey])) {
                $occurrences[] = self::buildOccurrence($entryKey, $index, $current, $durationSeconds);
            }

            $index++;
            if ($index >= $maxCount) {
                break;
            }

            $current = self::advance($current, $rec['frequency'], (int) $rec['interval']);
            if ($until !== null && $current > $until) {
                break;
            }
        }

        return self::filterByRange($occurrences, $rangeStart, $rangeEnd);
    }

    private static function durationSeconds(array $event, DateTimeImmutable $start): int
    {
        $endsAt = $event['ends_at'] ?? '';
        if ($endsAt === '') {
            return 3600;
        }
        try {
            $tz = $start->getTimezone();
            $end = new DateTimeImmutable($endsAt, $tz);
            $diff = $end->getTimestamp() - $start->getTimestamp();
            return max(60, $diff);
        } catch (Exception $e) {
            return 3600;
        }
    }

    private static function buildOccurrence(string $entryKey, int $index, DateTimeImmutable $start, int $durationSeconds): array
    {
        $end = $start->modify('+' . $durationSeconds . ' seconds');
        return [
            'occurrence_id' => $entryKey . ':' . $index,
            'starts_at' => $start->format('c'),
            'ends_at' => $end->format('c'),
            'series_index' => $index,
        ];
    }

    private static function advance(DateTimeImmutable $current, string $frequency, int $interval): DateTimeImmutable
    {
        $interval = max(1, $interval);
        switch ($frequency) {
            case 'daily':
                return $current->modify('+' . $interval . ' day');
            case 'weekly':
                return $current->modify('+' . $interval . ' week');
            case 'monthly':
                return $current->modify('+' . $interval . ' month');
            default:
                return $current->modify('+1 week');
        }
    }

    /**
     * @param list<array<string, mixed>> $occurrences
     * @return list<array<string, mixed>>
     */
    private static function filterByRange(array $occurrences, ?DateTimeInterface $rangeStart, ?DateTimeInterface $rangeEnd): array
    {
        if ($rangeStart === null && $rangeEnd === null) {
            return $occurrences;
        }

        $filtered = [];
        foreach ($occurrences as $occ) {
            try {
                $start = new DateTimeImmutable($occ['starts_at']);
                $end = new DateTimeImmutable($occ['ends_at']);
            } catch (Exception $e) {
                continue;
            }
            if ($rangeEnd !== null && $start > $rangeEnd) {
                continue;
            }
            if ($rangeStart !== null && $end < $rangeStart) {
                continue;
            }
            $filtered[] = $occ;
        }

        return $filtered;
    }
}
