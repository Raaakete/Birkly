<?php
/**
 * Events plugin initialization.
 */

function events_plugin_dir(): string
{
    return __DIR__;
}

function events_data_dir(): string
{
    $root = dirname(__DIR__, 2);
    return $root . '/data/plugins/events';
}

function events_plugin_active(): bool
{
    if (!function_exists('birkly_is_plugin_active')) {
        require_once dirname(__DIR__, 2) . '/core/plugins.php';
    }
    return birkly_is_plugin_active('events');
}

function events_seed_collection(): void
{
    $source = events_plugin_dir() . '/collections/events';
    $target = dirname(__DIR__, 2) . '/content/collections/events';
    if (!is_dir($source) || is_dir($target)) {
        return;
    }
    events_copy_dir($source, $target);
}

function events_copy_dir(string $src, string $dst): void
{
    if (!is_dir($dst)) {
        mkdir($dst, 0755, true);
    }
    foreach (scandir($src) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $from = $src . '/' . $entry;
        $to = $dst . '/' . $entry;
        if (is_dir($from)) {
            events_copy_dir($from, $to);
        } else {
            copy($from, $to);
        }
    }
}

function events_require_admin(): void
{
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    $role = $_SESSION['user']['role'] ?? '';
    if (!in_array($role, ['admin', 'superadmin'], true)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
}

function events_on_payment_succeeded(array $data): void
{
    $paymentId = $data['payment_id'] ?? '';
    if ($paymentId === '') {
        return;
    }
    require_once __DIR__ . '/includes/RegistrationService.php';
    $service = new EventsRegistrationService();
    $service->confirmPaidByPaymentId($paymentId);
}

function events_on_payment_failed(array $data): void
{
    $paymentId = $data['payment_id'] ?? '';
    if ($paymentId === '') {
        return;
    }
    require_once __DIR__ . '/includes/RegistrationService.php';
    $service = new EventsRegistrationService();
    $service->failPaidByPaymentId($paymentId);
}

function events_init_automation_triggers(): void
{
    if (!function_exists('register_automation_block')) {
        return;
    }
    $blocks = [
        'event_rsvp' => ['name' => 'Event RSVP', 'description' => 'Fires when someone RSVPs (free)'],
        'event_registration_paid' => ['name' => 'Event Registration Paid', 'description' => 'Fires when a paid registration is confirmed'],
        'event_full' => ['name' => 'Event Full', 'description' => 'Fires when event capacity is reached'],
        'events_message_attendees' => ['name' => 'Message Attendees', 'description' => 'Fires when bulk messaging attendees from Events admin'],
    ];
    foreach ($blocks as $id => $meta) {
        register_automation_block('trigger', $id, [
            'name' => $meta['name'],
            'description' => $meta['description'],
            'category' => 'Events',
            'schema' => [],
            'handler' => function () {
                return true;
            },
        ]);
    }
}

function events_register_payment_handlers(): void
{
    require_once __DIR__ . '/includes/ConsumerPaymentHelper.php';
    EventsConsumerPaymentHelper::registerPaymentEventHandlers(
        function (array $data) {
            events_on_payment_succeeded($data);
        },
        function (array $data) {
            events_on_payment_failed($data);
        }
    );
}

function events_bootstrap(): void
{
    require_once __DIR__ . '/includes/EventSchema.php';
    require_once __DIR__ . '/includes/EventRecurrence.php';
    require_once __DIR__ . '/includes/EventIndexer.php';
    require_once __DIR__ . '/includes/IcsGenerator.php';
    require_once __DIR__ . '/includes/AttendeeService.php';
    require_once __DIR__ . '/includes/ConsumerPaymentHelper.php';
    require_once __DIR__ . '/includes/RegistrationStore.php';
    require_once __DIR__ . '/includes/EventCatalog.php';
    require_once __DIR__ . '/includes/CapacityService.php';
    require_once __DIR__ . '/includes/RegistrationService.php';
    events_register_payment_handlers();
}

function events_register_fieldtype(): void
{
    if (!function_exists('register_plugin_fieldtype') || !events_plugin_active()) {
        return;
    }

    if (!class_exists('Birkly\FieldTypes\BaseField')) {
        require_once dirname(__DIR__, 2) . '/fieldtypes/BaseField.php';
    }
    require_once __DIR__ . '/fieldtypes/Event/Event.php';

    register_plugin_fieldtype(
        'event',
        'Birkly\\FieldTypes\\Event\\Event',
        'events',
        [
            'label' => 'Event',
            'description' => 'Schedule, recurrence, location, and registration capacity',
        ]
    );
}

function events_calendar_feed_url(): string
{
    $base = function_exists('birkly_url')
        ? birkly_url('/api/index.php?route=events&action=webcal')
        : '/api/index.php?route=events&action=webcal';
    return $base;
}

function events_ics_url(string $collectionSlug, string $entryId, ?int $occurrenceIndex = null): string
{
    $base = function_exists('birkly_url')
        ? birkly_url('/api/index.php?route=events&action=ics')
        : '/api/index.php?route=events&action=ics';
    $url = $base . '&collection=' . urlencode($collectionSlug) . '&entry_id=' . urlencode($entryId);
    if ($occurrenceIndex !== null) {
        $url .= '&occurrence=' . (int) $occurrenceIndex;
    }
    return $url;
}

function events_upcoming(int $limit = 10): array
{
    events_bootstrap();
    $indexer = new EventIndexer();
    $index = $indexer->loadIndex();

    if (!empty($index['entries'])) {
        $now = new DateTimeImmutable('now');
        $events = [];
        foreach ($indexer->listOccurrences() as $occ) {
            try {
                $end = new DateTimeImmutable($occ['ends_at']);
            } catch (Exception $e) {
                continue;
            }
            if ($end < $now) {
                continue;
            }
            $events[] = [
                'id' => $occ['entry_id'] ?? '',
                'collection' => $occ['collection_slug'] ?? '',
                'title' => $occ['entry_title'] ?? '',
                'slug' => $occ['entry_slug'] ?? '',
                'start' => $occ['starts_at'] ?? '',
                'end' => $occ['ends_at'] ?? '',
                'venue' => $occ['venue'] ?? '',
                'virtual_url' => $occ['virtual_url'] ?? '',
                'occurrence_id' => $occ['occurrence_id'] ?? '',
                'ics_url' => events_ics_url(
                    (string) ($occ['collection_slug'] ?? ''),
                    (string) ($occ['entry_id'] ?? ''),
                    (int) ($occ['series_index'] ?? 0)
                ),
            ];
        }
        return array_slice($events, 0, max(1, $limit));
    }

    $catalog = new EventsCatalog();
    $events = [];
    foreach ($catalog->listPublished() as $entry) {
        $event = $catalog->normalizeEvent($entry);
        if (($event['visibility'] ?? 'public') === 'hidden') {
            continue;
        }
        $events[] = $event;
    }
    usort($events, function ($a, $b) {
        return strcmp($a['start'] ?? '', $b['start'] ?? '');
    });
    return array_slice($events, 0, max(1, $limit));
}
