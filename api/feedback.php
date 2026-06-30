<?php
/**
 * Alpha feedback API — auth, CSRF, rate limit, flat-file storage in data/feedback/
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once __DIR__ . '/session.php';
haru_secure_session_start();

if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/platform.php';

const FEEDBACK_DIR = __DIR__ . '/../data/feedback';
const FEEDBACK_STATUSES = ['new', 'in_progress', 'resolved', 'closed'];
const FEEDBACK_TYPES = ['bug', 'feature', 'feedback', 'question'];

function feedback_ensure_dir(): void
{
    if (!is_dir(FEEDBACK_DIR)) {
        mkdir(FEEDBACK_DIR, 0755, true);
    }
}

function feedback_sanitize_id(string $id): string
{
    return preg_replace('/[^a-f0-9\-]/', '', strtolower($id));
}

function feedback_list_all(): array
{
    feedback_ensure_dir();
    $items = [];
    foreach (glob(FEEDBACK_DIR . '/*.json') as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            $items[] = $data;
        }
    }
    usort($items, static function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });
    return $items;
}

function feedback_read(string $id): ?array
{
    $id = feedback_sanitize_id($id);
    if ($id === '') {
        return null;
    }
    $path = FEEDBACK_DIR . '/' . $id . '.json';
    if (!is_file($path)) {
        return null;
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function feedback_write(array $record): bool
{
    feedback_ensure_dir();
    $id = feedback_sanitize_id($record['id'] ?? '');
    if ($id === '') {
        return false;
    }
    $path = FEEDBACK_DIR . '/' . $id . '.json';
    return file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

function feedback_generate_id(): string
{
    return sprintf('%s-%s', date('YmdHis'), bin2hex(random_bytes(4)));
}

function feedback_user_key(): string
{
    $user = $_SESSION['user'] ?? [];
    $email = strtolower(trim((string) ($user['email'] ?? $user['id'] ?? 'anonymous')));
    return preg_replace('/[^a-z0-9@._\-]/', '', $email) ?: 'anonymous';
}

function feedback_json_error(int $code, string $message, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(array_merge(['status' => 'error', 'message' => $message], $extra));
    exit;
}

/**
 * Relay feedback to platform inbox when feedback_relay_url is configured (P5.4-M2).
 *
 * @param array<string, mixed> $record
 */
function feedback_relay_to_platform(array $record): void
{
    $url = birkly_feedback_relay_url();
    if ($url === '' || !function_exists('curl_init')) {
        return;
    }

    $payload = [
        'install_id' => birkly_platform_install_id(),
        'feedback' => $record,
        'submitted_at' => gmdate('c'),
    ];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return;
    }

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    $secret = birkly_feedback_relay_secret();
    if ($secret !== '') {
        $headers[] = 'X-Birkly-Feedback-Signature: ' . hash_hmac('sha256', $body, $secret);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code < 200 || $code >= 300) {
        error_log('feedback relay failed HTTP ' . $code . ' for install ' . birkly_platform_install_id());
    }
    curl_close($ch);
}

// --- HTTP handlers (skip when included for tests) ---
if (!isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    requireAuth('read');

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? 'list';

    switch ($action) {
        case 'submit':
            $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
            if (!validate_csrf_token($csrf)) {
                feedback_json_error(403, 'Invalid CSRF token');
            }

            $limiter = new RateLimiter('feedback_submit_' . feedback_user_key(), 5, 3600);
            if (!$limiter->check()) {
                feedback_json_error(429, 'Rate limit exceeded', [
                    'retry_after' => $limiter->getRetryAfter(),
                ]);
            }

            $type = strtolower(trim((string) ($input['type'] ?? 'feedback')));
            if (!in_array($type, FEEDBACK_TYPES, true)) {
                feedback_json_error(400, 'Invalid feedback type');
            }

            $title = trim((string) ($input['title'] ?? ''));
            $description = trim((string) ($input['description'] ?? ''));
            if ($title === '' || $description === '') {
                feedback_json_error(400, 'Title and description are required');
            }
            if (strlen($title) > 200 || strlen($description) > 5000) {
                feedback_json_error(400, 'Title or description too long');
            }

            $user = $_SESSION['user'];
            $now = gmdate('c');
            $record = [
                'id' => feedback_generate_id(),
                'type' => $type,
                'title' => $title,
                'description' => $description,
                'status' => 'new',
                'user_id' => $user['id'] ?? '',
                'user_email' => $user['email'] ?? '',
                'user_role' => $user['role'] ?? '',
                'page_context' => substr((string) ($input['page_context'] ?? ''), 0, 500),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
                'admin_notes' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (!feedback_write($record)) {
                feedback_json_error(500, 'Failed to save feedback');
            }

            feedback_relay_to_platform($record);

            echo json_encode(['status' => 'ok', 'data' => ['id' => $record['id']]]);
            break;

        case 'list':
            requireAuth('manage_settings');
            if (!birkly_feedback_triage_enabled()) {
                feedback_json_error(403, 'Feedback triage is disabled on installs that relay to a central inbox');
            }
            $statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
            $typeFilter = strtolower(trim((string) ($_GET['type'] ?? '')));
            $items = feedback_list_all();
            if ($statusFilter !== '' && in_array($statusFilter, FEEDBACK_STATUSES, true)) {
                $items = array_values(array_filter($items, static fn($i) => ($i['status'] ?? '') === $statusFilter));
            }
            if ($typeFilter !== '' && in_array($typeFilter, FEEDBACK_TYPES, true)) {
                $items = array_values(array_filter($items, static fn($i) => ($i['type'] ?? '') === $typeFilter));
            }
            echo json_encode(['status' => 'ok', 'data' => ['items' => $items, 'total' => count($items)]]);
            break;

        case 'get':
            requireAuth('manage_settings');
            if (!birkly_feedback_triage_enabled()) {
                feedback_json_error(403, 'Feedback triage is disabled on installs that relay to a central inbox');
            }
            $id = feedback_sanitize_id((string) ($_GET['id'] ?? $input['id'] ?? ''));
            $item = feedback_read($id);
            if (!$item) {
                feedback_json_error(404, 'Feedback not found');
            }
            echo json_encode(['status' => 'ok', 'data' => $item]);
            break;

        case 'update_status':
            requireAuth('manage_settings');
            if (!birkly_feedback_triage_enabled()) {
                feedback_json_error(403, 'Feedback triage is disabled on installs that relay to a central inbox');
            }
            $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
            if (!validate_csrf_token($csrf)) {
                feedback_json_error(403, 'Invalid CSRF token');
            }

            $id = feedback_sanitize_id((string) ($input['id'] ?? ''));
            $status = strtolower(trim((string) ($input['status'] ?? '')));
            if ($id === '' || !in_array($status, FEEDBACK_STATUSES, true)) {
                feedback_json_error(400, 'Invalid id or status');
            }

            $item = feedback_read($id);
            if (!$item) {
                feedback_json_error(404, 'Feedback not found');
            }

            $item['status'] = $status;
            $item['updated_at'] = gmdate('c');
            if (array_key_exists('admin_notes', $input)) {
                $item['admin_notes'] = substr(trim((string) $input['admin_notes']), 0, 2000);
            }

            if (!feedback_write($item)) {
                feedback_json_error(500, 'Failed to update feedback');
            }

            echo json_encode(['status' => 'ok', 'data' => $item]);
            break;

        default:
            feedback_json_error(400, 'Unknown action');
    }
}
