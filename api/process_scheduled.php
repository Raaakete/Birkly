<?php
/**
 * Process Scheduled Publishing/Unpublishing
 *
 * CLI (no token):
 *   php /path/to/api/process_scheduled.php
 *
 * HTTP (token required on every request, including loopback):
 *   GET /api/process_scheduled.php?token=...
 * The token is generated into settings and shown on the admin system screen.
 */

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/../core/scheduled_publish.php';

if (birkly_scheduled_publish_is_http_request() && !birkly_scheduled_publish_http_authorized()) {
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: application/json');
    }
    echo json_encode(['status' => 'error', 'message' => 'Scheduled publish token required']);
    exit;
}

$result = birkly_process_scheduled_entries(__DIR__ . '/../content/collections');

if (($result['status'] ?? '') === 'error') {
    echo json_encode(['status' => 'error', 'message' => $result['message'] ?? 'Collections directory not found']);
    exit;
}

$processed = (int) $result['processed'];
$published = (int) $result['published'];
$unpublished = (int) $result['unpublished'];
$errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];

$result['timestamp'] = date('Y-m-d H:i:s');

if (birkly_scheduled_publish_is_http_request()) {
    header('Content-Type: application/json');
    echo json_encode($result);
} else {
    echo "Scheduled Publishing Processor\n";
    echo "==============================\n";
    echo "Processed entries: $processed\n";
    echo "Published: $published\n";
    echo "Unpublished: $unpublished\n";
    if (!empty($errors)) {
        echo "Errors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
    echo "Completed at: " . $result['timestamp'] . "\n";
}
