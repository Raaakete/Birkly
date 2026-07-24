<?php
/**
 * Event fieldtype value schema — schedule, location, capacity, recurrence (v1).
 */

class EventSchema
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    public static function defaultValue(): array
    {
        return [
            'starts_at' => '',
            'ends_at' => '',
            'timezone' => 'UTC',
            'recurrence' => self::defaultRecurrence(),
            'venue' => '',
            'virtual_url' => '',
            'hybrid' => false,
            'quantity_available' => null,
            'commerce_field' => '',
        ];
    }

    public static function defaultRecurrence(): array
    {
        return [
            'enabled' => false,
            'frequency' => 'weekly',
            'interval' => 1,
            'until' => null,
            'count' => null,
            'skip_dates' => [],
        ];
    }

    public static function normalizeValue($value): array
    {
        if (!is_array($value)) {
            return self::defaultValue();
        }

        $defaults = self::defaultValue();
        $recurrence = self::normalizeRecurrence($value['recurrence'] ?? []);

        $qty = $value['quantity_available'] ?? null;
        if ($qty === '' || $qty === false) {
            $qty = null;
        } elseif ($qty !== null) {
            $qty = max(0, (int) $qty);
        }

        return [
            'starts_at' => trim((string) ($value['starts_at'] ?? '')),
            'ends_at' => trim((string) ($value['ends_at'] ?? '')),
            'timezone' => self::normalizeTimezone($value['timezone'] ?? 'UTC'),
            'recurrence' => $recurrence,
            'venue' => trim((string) ($value['venue'] ?? '')),
            'virtual_url' => trim((string) ($value['virtual_url'] ?? '')),
            'hybrid' => !empty($value['hybrid']),
            'quantity_available' => $qty,
            'commerce_field' => trim((string) ($value['commerce_field'] ?? '')),
        ];
    }

    public static function normalizeRecurrence($value): array
    {
        $defaults = self::defaultRecurrence();
        if (!is_array($value)) {
            return $defaults;
        }

        $frequency = strtolower((string) ($value['frequency'] ?? $defaults['frequency']));
        if (!in_array($frequency, self::FREQUENCIES, true)) {
            $frequency = $defaults['frequency'];
        }

        $interval = max(1, (int) ($value['interval'] ?? 1));

        $until = $value['until'] ?? null;
        if ($until !== null && $until !== '') {
            $until = trim((string) $until);
        } else {
            $until = null;
        }

        $count = $value['count'] ?? null;
        if ($count === '' || $count === false) {
            $count = null;
        } elseif ($count !== null) {
            $count = max(1, (int) $count);
        }

        $skipDates = [];
        foreach ($value['skip_dates'] ?? [] as $date) {
            $date = trim((string) $date);
            if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
                $skipDates[] = substr($date, 0, 10);
            }
        }

        return [
            'enabled' => !empty($value['enabled']),
            'frequency' => $frequency,
            'interval' => $interval,
            'until' => $until,
            'count' => $count,
            'skip_dates' => array_values(array_unique($skipDates)),
        ];
    }

    public static function validate(array $value, bool $required = false): array
    {
        $errors = [];
        $normalized = self::normalizeValue($value);

        if ($required && $normalized['starts_at'] === '') {
            $errors[] = 'Event start date/time is required';
        }

        if ($normalized['starts_at'] !== '' && $normalized['ends_at'] !== '') {
            $start = strtotime($normalized['starts_at']);
            $end = strtotime($normalized['ends_at']);
            if ($start !== false && $end !== false && $end < $start) {
                $errors[] = 'Event end must be after start';
            }
        }

        $rec = $normalized['recurrence'];
        if ($rec['enabled']) {
            if ($rec['until'] === null && $rec['count'] === null) {
                $errors[] = 'Recurring events require either an end date (until) or occurrence count';
            }
        }

        return ['valid' => empty($errors), 'errors' => $errors, 'value' => $normalized];
    }

    public static function isEventFieldValue(array $value): bool
    {
        return isset($value['starts_at'])
            || isset($value['ends_at'])
            || isset($value['recurrence'])
            || isset($value['venue'])
            || isset($value['virtual_url']);
    }

    private static function normalizeTimezone(string $tz): string
    {
        $tz = trim($tz);
        if ($tz === '') {
            return 'UTC';
        }
        try {
            new DateTimeZone($tz);
            return $tz;
        } catch (Exception $e) {
            return 'UTC';
        }
    }
}

function event_normalize_value($value): array
{
    return EventSchema::normalizeValue($value);
}
