<?php
/**
 * Events public + admin API.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../../api/session.php';
haru_secure_session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../init.php';

if (!events_plugin_active()) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Events plugin is not active']);
    exit;
}

events_bootstrap();

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];
if ($action === '' && isset($input['action'])) {
    $action = $input['action'];
}

$catalog = new EventsCatalog();
$registrations = new EventsRegistrationStore();
$service = new EventsRegistrationService($catalog, $registrations);

switch ($action) {
    case 'list_events':
        $events = array_map(function ($entry) use ($catalog) {
            $event = $catalog->normalizeEvent($entry);
            $capacity = new EventsCapacityService($registrations);
            $event['remaining'] = $capacity->remainingCapacity($event);
            return $event;
        }, $catalog->listPublished());
        echo json_encode(['status' => 'ok', 'data' => ['events' => $events]]);
        break;

    case 'rsvp':
        $eventId = $input['event_id'] ?? $_GET['event_id'] ?? '';
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $ticketType = $input['ticket_type'] ?? 'free';
        $result = $service->registerFree($eventId, $name, $email, $ticketType);
        if (!($result['ok'] ?? false)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'RSVP failed']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => $result]);
        break;

    case 'paid_register':
        $eventId = $input['event_id'] ?? $_GET['event_id'] ?? '';
        $ticketType = $input['ticket_type'] ?? $_GET['ticket_type'] ?? '';
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $result = $service->startPaidRegistration($eventId, $ticketType, $name, $email);
        if (!($result['ok'] ?? false)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Registration failed']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => $result]);
        break;

    case 'list_registrations':
        events_require_admin();
        $eventId = $input['event_id'] ?? $_GET['event_id'] ?? '';
        $items = $eventId !== '' ? $registrations->listForEvent($eventId) : $registrations->listAll();
        echo json_encode(['status' => 'ok', 'data' => ['registrations' => $items]]);
        break;

    case 'export_registrations':
        events_require_admin();
        $eventId = $input['event_id'] ?? $_GET['event_id'] ?? '';
        $items = $eventId !== '' ? $registrations->listForEvent($eventId) : $registrations->listAll();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="registrations.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id', 'event_id', 'name', 'email', 'ticket_type', 'status', 'amount_cents', 'created_at']);
        foreach ($items as $row) {
            fputcsv($out, [
                $row['id'] ?? '',
                $row['event_id'] ?? '',
                $row['name'] ?? '',
                $row['email'] ?? '',
                $row['ticket_type'] ?? '',
                $row['status'] ?? '',
                $row['amount_cents'] ?? 0,
                $row['created_at'] ?? '',
            ]);
        }
        fclose($out);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
}
