<?php
/**
 * Events plugin initialization.
 */

function events_plugin_dir(): string
{
    return __DIR__;
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
    require_once __DIR__ . '/includes/ConsumerPaymentHelper.php';
    require_once __DIR__ . '/includes/RegistrationStore.php';
    require_once __DIR__ . '/includes/EventCatalog.php';
    require_once __DIR__ . '/includes/CapacityService.php';
    require_once __DIR__ . '/includes/RegistrationService.php';
    events_register_payment_handlers();
}

function events_upcoming(int $limit = 10): array
{
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
