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
require_once __DIR__ . '/../core/outbound_http.php';
require_once __DIR__ . '/../core/feedback_attachments.php';

const FEEDBACK_DIR = __DIR__ . '/../data/feedback';
const FEEDBACK_STATUSES = ['new', 'in_progress', 'resolved', 'closed'];
const FEEDBACK_TYPES = ['bug', 'feature', 'feedback', 'question'];
const FEEDBACK_CATEGORIES = ['admin', 'content', 'commerce', 'marketplace', 'plugins', 'ai', 'other'];

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

function feedback_cms_version(): string
{
    if (!is_file(__DIR__ . '/../core/version.php')) {
        return '';
    }
    require_once __DIR__ . '/../core/version.php';

    return class_exists('BirklyVersion') ? BirklyVersion::getCurrentVersion() : '';
}

function feedback_parse_contact_allowed(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (int) $value === 1;
    }
    $normalized = strtolower(trim((string) $value));

    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
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
 * @return array{ok:bool,http_code:int,error?:string}
 */
function feedback_relay_post(string $url, string $body, string $secret): array
{
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Birkly-Feedback-Signature: ' . hash_hmac('sha256', $body, $secret),
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    birkly_outbound_curl_apply_transport_defaults($ch);
    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'http_code' => $code, 'error' => $curlErr !== '' ? $curlErr : 'curl failed'];
    }
    if ($code < 200 || $code >= 300) {
        $snippet = is_string($response) ? substr($response, 0, 200) : '';
        return [
            'ok' => false,
            'http_code' => $code,
            'error' => $snippet !== '' ? $snippet : ('HTTP ' . $code),
        ];
    }

    return ['ok' => true, 'http_code' => $code];
}

function feedback_relay_ensure_secret(string $installId): bool
{
    if (birkly_feedback_relay_secret() !== '') {
        return true;
    }
    if (!is_file(__DIR__ . '/../core/platform_ops.php')) {
        return false;
    }
    require_once __DIR__ . '/../core/platform_ops.php';
    error_log('feedback relay: no secret yet — forcing Ops registration for install ' . $installId);
    $provisioned = birkly_ops_register_installation(true);
    if ($provisioned !== '' && birkly_feedback_relay_secret() === '') {
        $GLOBALS['__birkly_platform_config'] = array_merge(
            birkly_platform_config(),
            ['feedback_relay_secret' => $provisioned]
        );
    }
    return birkly_feedback_relay_secret() !== '';
}

/**
 * Relay feedback to platform inbox when feedback_relay_url is configured (P5.4-M2).
 *
 * @param array<string, mixed> $record
 * @return array{relayed:bool,http_code?:int,install_id:string,error?:string}
 */
function feedback_relay_to_platform(array $record): array
{
    $installId = birkly_platform_install_id();
    $url = birkly_feedback_relay_url();
    if ($url === '' && defined('BIRKLY_OPS_DEFAULT_FEEDBACK_RELAY_URL')) {
        $url = BIRKLY_OPS_DEFAULT_FEEDBACK_RELAY_URL;
    }
    if ($url === '') {
        return ['relayed' => false, 'install_id' => $installId, 'error' => 'relay_url_unconfigured'];
    }
    if (!function_exists('curl_init')) {
        error_log('feedback relay skipped: curl extension missing for install ' . $installId);
        return ['relayed' => false, 'install_id' => $installId, 'error' => 'curl_missing'];
    }

    if (!feedback_relay_ensure_secret($installId)) {
        error_log(
            'feedback relay skipped: registration did not provision a relay secret for install ' .
            $installId . ' (will retry on next submit)'
        );
        return ['relayed' => false, 'install_id' => $installId, 'error' => 'no_relay_secret'];
    }

    $relayRecord = $record;
    $attachments = is_array($record['attachments'] ?? null) ? $record['attachments'] : [];
    if ($attachments !== []) {
        $relayRecord['attachments'] = feedback_relay_attachments_payload((string) ($record['id'] ?? ''), $attachments);
    }

    $payload = [
        'install_id' => $installId,
        'feedback' => $relayRecord,
        'submitted_at' => gmdate('c'),
    ];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        error_log('feedback relay skipped: failed to encode payload for install ' . $installId);
        return ['relayed' => false, 'install_id' => $installId, 'error' => 'encode_failed'];
    }

    $secret = birkly_feedback_relay_secret();
    $result = feedback_relay_post($url, $body, $secret);

    if (!$result['ok'] && ($result['http_code'] ?? 0) === 401) {
        birkly_platform_clear_feedback_relay_secret();
        if (feedback_relay_ensure_secret($installId)) {
            $secret = birkly_feedback_relay_secret();
            $result = feedback_relay_post($url, $body, $secret);
        }
    }

    if (!$result['ok']) {
        error_log(
            'feedback relay failed HTTP ' . ($result['http_code'] ?? 0) .
            ' for install ' . $installId .
            (isset($result['error']) ? (' ' . $result['error']) : '')
        );
        return [
            'relayed' => false,
            'install_id' => $installId,
            'http_code' => $result['http_code'] ?? 0,
            'error' => $result['error'] ?? 'relay_failed',
        ];
    }

    return ['relayed' => true, 'install_id' => $installId, 'http_code' => $result['http_code']];
}

// --- HTTP handlers (skip when included for tests) ---
if (!isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    requireAuth('read');

    $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    $isMultipart = stripos($contentType, 'multipart/form-data') !== false;
    $input = $isMultipart
        ? $_POST
        : (json_decode(file_get_contents('php://input'), true) ?? []);
    if (!is_array($input)) {
        $input = [];
    }
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

            $category = strtolower(trim((string) ($input['category'] ?? 'other')));
            if (!in_array($category, FEEDBACK_CATEGORIES, true)) {
                feedback_json_error(400, 'Invalid feedback category');
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
            $contactAllowed = feedback_parse_contact_allowed($input['contact_allowed'] ?? false);
            $now = gmdate('c');
            $feedbackId = feedback_generate_id();
            $attachments = feedback_process_uploads($feedbackId);
            $record = [
                'id' => $feedbackId,
                'type' => $type,
                'category' => $category,
                'title' => $title,
                'description' => $description,
                'status' => 'new',
                'cms_version' => feedback_cms_version(),
                'user_id' => $user['id'] ?? '',
                'contact_allowed' => $contactAllowed,
                'user_email' => $contactAllowed ? trim((string) ($user['email'] ?? '')) : '',
                'user_role' => $user['role'] ?? '',
                'page_context' => substr((string) ($input['page_context'] ?? ''), 0, 500),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
                'admin_notes' => '',
                'attachments' => $attachments,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (!feedback_write($record)) {
                feedback_json_error(500, 'Failed to save feedback');
            }

            $relay = feedback_relay_to_platform($record);
            $responseData = ['id' => $record['id']];
            require_once __DIR__ . '/../core/developer_mode.php';
            if (birkly_developer_mode_enabled()) {
                $responseData['relay'] = $relay;
            }

            echo json_encode(['status' => 'ok', 'data' => $responseData]);
            break;

        case 'list':
            requireAuth('manage_settings');
            if (!birkly_feedback_triage_enabled()) {
                feedback_json_error(403, 'Feedback triage is disabled on installs that relay to a central inbox');
            }
            $statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
            $typeFilter = strtolower(trim((string) ($_GET['type'] ?? '')));
            $categoryFilter = strtolower(trim((string) ($_GET['category'] ?? '')));
            $items = feedback_list_all();
            if ($statusFilter !== '' && in_array($statusFilter, FEEDBACK_STATUSES, true)) {
                $items = array_values(array_filter($items, static fn($i) => ($i['status'] ?? '') === $statusFilter));
            }
            if ($typeFilter !== '' && in_array($typeFilter, FEEDBACK_TYPES, true)) {
                $items = array_values(array_filter($items, static fn($i) => ($i['type'] ?? '') === $typeFilter));
            }
            if ($categoryFilter !== '' && in_array($categoryFilter, FEEDBACK_CATEGORIES, true)) {
                $items = array_values(array_filter($items, static fn($i) => ($i['category'] ?? 'other') === $categoryFilter));
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
