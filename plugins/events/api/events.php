<?php
/**
 * Events public + admin API — v2 indexer, iCal, webcal, occurrences.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../../api/session.php';
haru_secure_session_start();

require_once __DIR__ . '/../init.php';

if (!events_plugin_active()) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Events plugin is not active']);
    exit;
}

events_bootstrap();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
if (!is_array($input)) {
    $input = [];
}
if ($input === [] && !empty($_POST)) {
    $input = $_POST;
}
$action = $input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? '';

$catalog = new EventsCatalog();
$registrations = new EventsRegistrationStore();
$service = new EventsRegistrationService($catalog, $registrations);
$indexer = new EventIndexer();
$attendees = new AttendeeService($registrations);

switch ($action) {
    case 'ics':
        $collectionSlug = $_GET['collection'] ?? $input['collection'] ?? '';
        $entryId = $_GET['entry_id'] ?? $input['entry_id'] ?? '';
        $occurrenceIndex = isset($_GET['occurrence']) ? (int) $_GET['occurrence'] : (isset($input['occurrence']) ? (int) $input['occurrence'] : 0);

        if ($collectionSlug === '' || $entryId === '') {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'collection and entry_id required']);
            break;
        }

        $row = $indexer->getIndexedEntry($collectionSlug, $entryId);
        if ($row === null) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Event not found in index']);
            break;
        }

        $key = $collectionSlug . ':' . $entryId;
        $occurrences = EventRecurrence::expandOccurrences($row['event'] ?? [], $key);
        $occurrence = $occurrences[$occurrenceIndex] ?? $occurrences[0] ?? null;
        if ($occurrence === null) {
            header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Occurrence not found']);
            break;
        }

        $vevent = IcsGenerator::buildEvent($row, $occurrence);
        $ics = IcsGenerator::wrapCalendar([$vevent], (string) ($row['entry_title'] ?? 'Event'));
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="event-' . preg_replace('/[^a-z0-9_-]/i', '-', $entryId) . '.ics"');
        echo $ics;
        exit;

    case 'webcal':
    case 'feed':
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;
        $vevents = [];
        foreach ($indexer->listOccurrences($from, $to) as $occ) {
            $vevents[] = IcsGenerator::buildEvent($occ, $occ);
        }
        $ics = IcsGenerator::wrapCalendar($vevents, 'Birkly Events');
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: inline; filename="birkly-events.ics"');
        echo $ics;
        exit;

    case 'list_occurrences':
        $from = $_GET['from'] ?? $input['from'] ?? null;
        $to = $_GET['to'] ?? $input['to'] ?? null;
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'occurrences' => $indexer->listOccurrences($from, $to),
                'feed_url' => events_calendar_feed_url(),
            ],
        ]);
        break;

    case 'list_indexed_events':
        events_require_admin();
        header('Content-Type: application/json');
        $entries = array_values($indexer->loadIndex()['entries'] ?? []);
        echo json_encode(['status' => 'ok', 'data' => ['events' => $entries]]);
        break;

    case 'overview':
        events_require_admin();
        header('Content-Type: application/json');
        $stats = $attendees->overviewStats($indexer);
        $stats['feed_url'] = events_calendar_feed_url();
        $stats['webcal_url'] = preg_replace('#^https?#', 'webcal', events_calendar_feed_url());
        echo json_encode(['status' => 'ok', 'data' => $stats]);
        break;

    case 'reindex':
        events_require_admin();
        header('Content-Type: application/json');
        $count = $indexer->reindexAll();
        echo json_encode(['status' => 'ok', 'data' => ['indexed' => $count]]);
        break;

    case 'export_ics':
        events_require_admin();
        $from = $_GET['from'] ?? $input['from'] ?? null;
        $to = $_GET['to'] ?? $input['to'] ?? null;
        $vevents = [];
        foreach ($indexer->listOccurrences($from, $to) as $occ) {
            $vevents[] = IcsGenerator::buildEvent($occ, $occ);
        }
        $ics = IcsGenerator::wrapCalendar($vevents, 'Birkly Events Export');
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="events-export.ics"');
        echo $ics;
        exit;

    case 'list_events':
        header('Content-Type: application/json');
        $events = array_map(function ($entry) use ($catalog, $registrations) {
            $event = $catalog->normalizeEvent($entry);
            $capacity = new EventsCapacityService($registrations);
            $event['remaining'] = $capacity->remainingCapacity($event);
            return $event;
        }, $catalog->listPublished());
        echo json_encode(['status' => 'ok', 'data' => ['events' => $events]]);
        break;

    case 'rsvp':
        header('Content-Type: application/json');
        $eventId = $input['event_id'] ?? $_POST['event_id'] ?? $_GET['event_id'] ?? '';
        $name = trim($input['name'] ?? $_POST['name'] ?? '');
        $email = trim($input['email'] ?? $_POST['email'] ?? '');
        $ticketType = $input['ticket_type'] ?? $_POST['ticket_type'] ?? 'free';
        $result = $service->registerFree($eventId, $name, $email, $ticketType);
        if (!($result['ok'] ?? false)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'RSVP failed']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => $result]);
        break;

    case 'paid_register':
        header('Content-Type: application/json');
        $eventId = $input['event_id'] ?? $_POST['event_id'] ?? $_GET['event_id'] ?? '';
        $ticketType = $input['ticket_type'] ?? $_POST['ticket_type'] ?? $_GET['ticket_type'] ?? '';
        $name = trim($input['name'] ?? $_POST['name'] ?? '');
        $email = trim($input['email'] ?? $_POST['email'] ?? '');
        $result = $service->startPaidRegistration($eventId, $ticketType, $name, $email);
        if (!($result['ok'] ?? false)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Registration failed']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => $result]);
        break;

    case 'list_registrations':
    case 'list_attendees':
        events_require_admin();
        header('Content-Type: application/json');
        $eventId = $input['event_id'] ?? $_GET['event_id'] ?? '';
        $collectionSlug = $input['collection'] ?? $_GET['collection'] ?? '';
        $entryId = $input['entry_id'] ?? $_GET['entry_id'] ?? '';
        $items = $attendees->listAll(
            $eventId !== '' ? $eventId : null,
            $collectionSlug !== '' ? $collectionSlug : null,
            $entryId !== '' ? $entryId : null
        );
        echo json_encode(['status' => 'ok', 'data' => ['registrations' => $items, 'attendees' => $items]]);
        break;

    case 'export_registrations':
        events_require_admin();
        $eventId = $input['event_id'] ?? $_GET['event_id'] ?? '';
        $items = $attendees->listAll($eventId !== '' ? $eventId : null);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendees.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['source', 'id', 'entry_id', 'collection', 'name', 'email', 'ticket_type', 'status', 'amount_cents', 'registered_at']);
        foreach ($items as $row) {
            fputcsv($out, [
                $row['source'] ?? '',
                $row['id'] ?? '',
                $row['entry_id'] ?? '',
                $row['collection_slug'] ?? '',
                $row['name'] ?? '',
                $row['email'] ?? '',
                $row['ticket_type'] ?? '',
                $row['status'] ?? '',
                $row['amount_cents'] ?? 0,
                $row['registered_at'] ?? '',
            ]);
        }
        fclose($out);
        exit;

    case 'message_attendees':
        events_require_admin();
        header('Content-Type: application/json');
        $subject = trim($input['subject'] ?? '');
        $body = trim($input['body'] ?? '');
        $eventId = $input['event_id'] ?? '';
        $entryId = $input['entry_id'] ?? '';
        $collectionSlug = $input['collection'] ?? '';

        $recipientList = $attendees->listAll(
            $eventId !== '' ? $eventId : null,
            $collectionSlug !== '' ? $collectionSlug : null,
            $entryId !== '' ? $entryId : null
        );

        $recipientEmails = $input['recipient_emails'] ?? null;
        if (is_array($recipientEmails) && count($recipientEmails) > 0) {
            $allow = array_map(static fn ($e) => strtolower(trim((string) $e)), $recipientEmails);
            $recipientList = array_values(array_filter(
                $recipientList,
                static fn ($r) => in_array(strtolower(trim((string) ($r['email'] ?? ''))), $allow, true)
            ));
        }

        $emails = array_values(array_unique(array_filter(array_map(fn ($r) => $r['email'] ?? '', $recipientList))));

        if (function_exists('do_action')) {
            do_action('events_message_attendees', [
                'subject' => $subject,
                'body' => $body,
                'emails' => $emails,
                'event_id' => $eventId,
                'entry_id' => $entryId,
                'collection' => $collectionSlug,
            ]);
        }

        echo json_encode([
            'status' => 'ok',
            'data' => [
                'recipient_count' => count($emails),
                'export_hint' => 'Use Automation trigger events_message_attendees for delivery',
            ],
        ]);
        break;

    default:
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
}
