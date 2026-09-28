<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/../core/hosting_redirects.php';

set_cors_headers('admin');
haru_secure_session_start();
requireAuth('manage_settings');

function birkly_redirects_file(): string
{
    return birkly_redirects_settings_path();
}

function birkly_save_redirects(array $redirects): bool
{
    $file = birkly_redirects_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $encoded = json_encode(array_values($redirects), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $encoded !== false && file_put_contents($file, $encoded) !== false;
}

function birkly_normalize_redirect_record(array $input, ?array $existing = null): array
{
    $type = (int) ($input['type'] ?? $existing['type'] ?? 301);
    if (!in_array($type, [301, 302], true)) {
        $type = 301;
    }

    return [
        'id' => (string) ($input['id'] ?? $existing['id'] ?? uniqid('redirect_', true)),
        'from_path' => birkly_normalize_redirect_path((string) ($input['from_path'] ?? $existing['from_path'] ?? '')),
        'to_path' => trim((string) ($input['to_path'] ?? $existing['to_path'] ?? '')),
        'type' => $type,
        'enabled' => array_key_exists('enabled', $input)
            ? (bool) $input['enabled']
            : (bool) ($existing['enabled'] ?? true),
        'created_at' => $existing['created_at'] ?? date('c'),
        'updated_at' => date('c'),
    ];
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput ?: '[]', true);
if (!is_array($input)) {
    $input = [];
}

$action = trim((string) ($_GET['action'] ?? $input['action'] ?? 'list'));

if ($action === 'list') {
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'redirects' => birkly_load_redirects(),
        ],
    ]);
    exit;
}

$csrf = (string) ($input['_csrf'] ?? '');
if (!validate_csrf_token($csrf)) {
    log_security_event('permission_denied', ['action' => 'redirects_' . $action, 'reason' => 'invalid CSRF']);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

$redirects = birkly_load_redirects();

if ($action === 'save') {
    $record = birkly_normalize_redirect_record($input);
    if ($record['from_path'] === '' || $record['to_path'] === '') {
        echo json_encode(['status' => 'error', 'message' => 'From path and destination are required']);
        exit;
    }

    foreach ($redirects as $index => $redirect) {
        if (($redirect['id'] ?? '') === $record['id']) {
            $record = birkly_normalize_redirect_record($input, $redirect);
            $redirects[$index] = $record;
            if (!birkly_save_redirects($redirects)) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save redirect']);
                exit;
            }
            log_security_event('redirect_updated', ['redirect_id' => $record['id'], 'from_path' => $record['from_path']]);
            echo json_encode(['status' => 'ok', 'message' => 'Redirect updated', 'data' => ['redirect' => $record]]);
            exit;
        }
    }

    $redirects[] = $record;
    if (!birkly_save_redirects($redirects)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create redirect']);
        exit;
    }

    log_security_event('redirect_created', ['redirect_id' => $record['id'], 'from_path' => $record['from_path']]);
    echo json_encode(['status' => 'ok', 'message' => 'Redirect created', 'data' => ['redirect' => $record]]);
    exit;
}

if ($action === 'delete') {
    $id = trim((string) ($input['id'] ?? ''));
    if ($id === '') {
        echo json_encode(['status' => 'error', 'message' => 'Redirect id required']);
        exit;
    }

    $redirects = array_values(array_filter($redirects, static function (array $redirect) use ($id): bool {
        return (string) ($redirect['id'] ?? '') !== $id;
    }));

    if (!birkly_save_redirects($redirects)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete redirect']);
        exit;
    }

    log_security_event('redirect_deleted', ['redirect_id' => $id]);
    echo json_encode(['status' => 'ok', 'message' => 'Redirect deleted']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
