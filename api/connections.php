<?php
/**
 * Connection registry API for Settings → Connections.
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require_once 'session.php';
haru_secure_session_start();

if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once 'storage.php';
require_once 'auth.php';
require_once 'validate.php';
require_once '../core/csrf.php';
require_once __DIR__ . '/../core/connections/registry.php';
require_once __DIR__ . '/../core/connections/email_picker.php';
require_once __DIR__ . '/../core/connections/resolve.php';
require_once __DIR__ . '/../core/connections/http.php';
require_once __DIR__ . '/../core/mail/birkly_send_mail.php';
require_once __DIR__ . '/../core/mail/dns_hints.php';

ob_start();

$storage = new Storage('../settings');
$rawInput = file_get_contents('php://input');
$input = [];
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $input = $decoded;
    }
}
$action = $input['action'] ?? $_GET['action'] ?? '';

/**
 * Email picker is read-only metadata for Commerce, Automation, and collection forms.
 * Shop/content admins may not have manage_settings but still need to choose a connection.
 */
function connections_require_action_auth(string $action): void
{
    if ($action === 'email_picker') {
        requireAuth();
        $user = $_SESSION['user'] ?? null;
        if (!is_array($user)) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }
        $canPick = birkly_email_picker_allowed($user);
        if (!$canPick) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
            exit;
        }
        return;
    }

    requireAuth('manage_settings');
}

connections_require_action_auth($action);

function connections_json_response(array $data, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json');
    }
    echo json_encode($data);
    exit;
}

function connections_validate_csrf(array $input): bool
{
    $csrf = $input['_csrf'] ?? '';
    return validate_csrf_token($csrf);
}

function connections_provider_schema(): array
{
    return [
        'log' => [
            'label' => 'Log only',
            'description' => 'Capture messages in logs/mail.jsonl without sending.',
            'type' => 'email',
            'fields' => [],
        ],
        'mail' => [
            'label' => 'PHP mail()',
            'description' => 'Use the native PHP mail() function (development/local only).',
            'type' => 'email',
            'fields' => [],
        ],
        'smtp' => [
            'label' => 'SMTP',
            'description' => 'Any SMTP relay (STARTTLS or SSL).',
            'type' => 'email',
            'fields' => [
                ['name' => 'config[host]', 'type' => 'text', 'label' => 'SMTP host', 'required' => true, 'placeholder' => 'smtp.example.com'],
                ['name' => 'config[port]', 'type' => 'number', 'label' => 'Port', 'required' => true, 'value' => 587],
                ['name' => 'config[encryption]', 'type' => 'select', 'label' => 'Encryption', 'options' => ['tls' => 'STARTTLS', 'ssl' => 'SSL', 'none' => 'None'], 'value' => 'tls'],
                ['name' => 'config[auth]', 'type' => 'select', 'label' => 'Auth', 'options' => ['login' => 'LOGIN', 'plain' => 'PLAIN', 'none' => 'None'], 'value' => 'login'],
                ['name' => 'secrets[username]', 'type' => 'text', 'label' => 'Username'],
                ['name' => 'secrets[password]', 'type' => 'password', 'label' => 'Password / app token'],
            ],
        ],
        'resend' => [
            'label' => 'Resend',
            'description' => 'Send via Resend REST API.',
            'type' => 'email',
            'fields' => [
                ['name' => 'secrets[api_key]', 'type' => 'password', 'label' => 'API key', 'required' => true],
            ],
        ],
        'postmark' => [
            'label' => 'Postmark',
            'description' => 'Send via Postmark REST API.',
            'type' => 'email',
            'fields' => [
                ['name' => 'secrets[api_key]', 'type' => 'password', 'label' => 'Server API token', 'required' => true],
            ],
        ],
        'ses' => [
            'label' => 'Amazon SES (HTTPS API)',
            'description' => 'Send via AWS SES HTTPS API with Signature V4.',
            'type' => 'email',
            'fields' => [
                ['name' => 'config[region]', 'type' => 'text', 'label' => 'AWS region', 'required' => true, 'value' => 'us-east-1'],
                ['name' => 'secrets[access_key]', 'type' => 'text', 'label' => 'Access key ID', 'required' => true],
                ['name' => 'secrets[secret_key]', 'type' => 'password', 'label' => 'Secret access key', 'required' => true],
            ],
        ],
        'mailgun' => [
            'label' => 'Mailgun',
            'description' => 'Send via Mailgun REST API.',
            'type' => 'email',
            'fields' => [
                ['name' => 'config[domain]', 'type' => 'text', 'label' => 'Domain', 'required' => true],
                ['name' => 'config[region]', 'type' => 'select', 'label' => 'Region', 'options' => ['us' => 'US', 'eu' => 'EU'], 'value' => 'us'],
                ['name' => 'secrets[api_key]', 'type' => 'password', 'label' => 'API key', 'required' => true],
            ],
        ],
        'basic_auth' => [
            'label' => 'HTTP Basic Auth',
            'description' => 'Stored username and password for outbound HTTP requests.',
            'type' => 'http_credential',
            'fields' => [
                ['name' => 'config[base_url]', 'type' => 'url', 'label' => 'Base URL', 'placeholder' => 'https://api.example.com'],
                ['name' => 'config[timeout]', 'type' => 'number', 'label' => 'Timeout (seconds)', 'value' => 30],
                ['name' => 'secrets[username]', 'type' => 'text', 'label' => 'Username'],
                ['name' => 'secrets[password]', 'type' => 'password', 'label' => 'Password'],
            ],
        ],
        'bearer' => [
            'label' => 'HTTP Bearer Token',
            'description' => 'Stored bearer token for outbound HTTP requests.',
            'type' => 'http_credential',
            'fields' => [
                ['name' => 'config[base_url]', 'type' => 'url', 'label' => 'Base URL', 'placeholder' => 'https://api.example.com'],
                ['name' => 'config[timeout]', 'type' => 'number', 'label' => 'Timeout (seconds)', 'value' => 30],
                ['name' => 'secrets[token]', 'type' => 'password', 'label' => 'Token', 'required' => true],
            ],
        ],
        'custom_header' => [
            'label' => 'HTTP Custom Header',
            'description' => 'Stored custom header name and value for outbound HTTP requests.',
            'type' => 'http_credential',
            'fields' => [
                ['name' => 'config[base_url]', 'type' => 'url', 'label' => 'Base URL', 'placeholder' => 'https://api.example.com'],
                ['name' => 'config[timeout]', 'type' => 'number', 'label' => 'Timeout (seconds)', 'value' => 30],
                ['name' => 'config[header_name]', 'type' => 'text', 'label' => 'Header name', 'required' => true, 'placeholder' => 'X-API-Key'],
                ['name' => 'secrets[header_value]', 'type' => 'password', 'label' => 'Header value', 'required' => true],
            ],
        ],
        'slack' => [
            'label' => 'Slack webhook',
            'description' => 'Post to a Slack incoming webhook URL.',
            'type' => 'channel',
            'fields' => [
                ['name' => 'config[channel]', 'type' => 'text', 'label' => 'Channel override (optional)', 'placeholder' => '#alerts'],
                ['name' => 'secrets[webhook_url]', 'type' => 'password', 'label' => 'Webhook URL', 'required' => true],
            ],
        ],
        'discord' => [
            'label' => 'Discord webhook',
            'description' => 'Post to a Discord incoming webhook URL.',
            'type' => 'channel',
            'fields' => [
                ['name' => 'secrets[webhook_url]', 'type' => 'password', 'label' => 'Webhook URL', 'required' => true],
            ],
        ],
        'twilio_sms' => [
            'label' => 'Twilio SMS',
            'description' => 'Send SMS messages via Twilio.',
            'type' => 'channel',
            'fields' => [
                ['name' => 'config[from_number]', 'type' => 'text', 'label' => 'From number', 'required' => true, 'placeholder' => '+15551234567'],
                ['name' => 'secrets[account_sid]', 'type' => 'text', 'label' => 'Account SID', 'required' => true],
                ['name' => 'secrets[auth_token]', 'type' => 'password', 'label' => 'Auth token', 'required' => true],
            ],
        ],
    ];
}

if ($action === 'list') {
    birkly_connections_ensure_inbound_signing_secrets();
    $store = birkly_connections_load();
    $connections = array_map('birkly_connection_admin_view', $store['connections']);
    connections_json_response(['status' => 'ok', 'connections' => $connections]);
}

if ($action === 'providers') {
    connections_json_response(['status' => 'ok', 'providers' => connections_provider_schema()]);
}

if ($action === 'email_picker') {
    $picker = birkly_email_connections_for_picker();
    connections_json_response([
        'status' => 'ok',
        'connections' => $picker['connections'],
        'default_alias' => $picker['default_alias'],
        'has_connections' => $picker['has_connections'],
    ]);
}

if ($action === 'get') {
    birkly_connections_ensure_inbound_signing_secrets();
    $id = $_GET['id'] ?? $input['id'] ?? '';
    $stored = null;
    foreach (birkly_connections_load()['connections'] as $row) {
        if (is_array($row) && (string) ($row['id'] ?? '') === (string) $id) {
            $stored = $row;
            break;
        }
    }
    $connection = is_array($stored) ? birkly_connection_admin_view($stored) : null;
    if ($connection === null) {
        connections_json_response(['status' => 'error', 'message' => 'Connection not found.'], 404);
    }
    connections_json_response(['status' => 'ok', 'connection' => $connection]);
}

if ($action === 'create') {
    if (!connections_validate_csrf($input)) {
        connections_json_response(['status' => 'error', 'message' => 'Invalid CSRF token.'], 403);
    }
    $result = birkly_connection_create($input);
    if (!$result['ok']) {
        connections_json_response(['status' => 'error', 'errors' => $result['errors']], 422);
    }
    connections_json_response(['status' => 'ok', 'connection' => $result['connection']]);
}

if ($action === 'update') {
    if (!connections_validate_csrf($input)) {
        connections_json_response(['status' => 'error', 'message' => 'Invalid CSRF token.'], 403);
    }
    $id = $input['id'] ?? '';
    if ($id === '') {
        connections_json_response(['status' => 'error', 'message' => 'Connection ID is required.'], 422);
    }
    $result = birkly_connection_update($id, $input);
    if (!$result['ok']) {
        connections_json_response(['status' => 'error', 'errors' => $result['errors']], 422);
    }
    connections_json_response(['status' => 'ok', 'connection' => $result['connection']]);
}

if ($action === 'delete') {
    if (!connections_validate_csrf($input)) {
        connections_json_response(['status' => 'error', 'message' => 'Invalid CSRF token.'], 403);
    }
    $id = $input['id'] ?? '';
    if ($id === '') {
        connections_json_response(['status' => 'error', 'message' => 'Connection ID is required.'], 422);
    }
    $result = birkly_connection_delete($id);
    if (!$result['ok']) {
        connections_json_response(['status' => 'error', 'errors' => $result['errors']], 422);
    }
    connections_json_response(['status' => 'ok']);
}

if ($action === 'set_default') {
    if (!connections_validate_csrf($input)) {
        connections_json_response(['status' => 'error', 'message' => 'Invalid CSRF token.'], 403);
    }
    $id = $input['id'] ?? '';
    if ($id === '') {
        connections_json_response(['status' => 'error', 'message' => 'Connection ID is required.'], 422);
    }
    $result = birkly_connection_set_default($id);
    if (!$result['ok']) {
        connections_json_response(['status' => 'error', 'errors' => $result['errors']], 422);
    }
    connections_json_response(['status' => 'ok', 'connection' => $result['connection']]);
}

if ($action === 'dns_hints') {
    $provider = $_GET['provider'] ?? $input['provider'] ?? '';
    $domain = $_GET['domain'] ?? $input['domain'] ?? '';
    if ($provider === '' || $domain === '') {
        connections_json_response(['status' => 'error', 'message' => 'Provider and domain are required.'], 422);
    }
    connections_json_response(['status' => 'ok', 'hints' => birkly_mail_dns_hints($provider, $domain)]);
}

if ($action === 'test') {
    if (!connections_validate_csrf($input)) {
        connections_json_response(['status' => 'error', 'message' => 'Invalid CSRF token.'], 403);
    }
    $alias = $input['alias'] ?? '';
    $testEmail = $input['test_email'] ?? ($_SESSION['user']['email'] ?? '');
    if ($alias === '') {
        connections_json_response(['status' => 'error', 'message' => 'Connection alias is required.'], 422);
    }

    $testConnection = birkly_connection_get_by_alias($alias, false);
    if ($testConnection === null) {
        connections_json_response(['status' => 'error', 'message' => 'Connection not found.'], 404);
    }

    if (($testConnection['type'] ?? 'email') === 'http_credential') {
        $baseUrl = $testConnection['config']['base_url'] ?? '';
        if ($baseUrl === '') {
            connections_json_response(['status' => 'error', 'message' => 'HTTP credential has no base URL to test.'], 422);
        }
        $result = birkly_http_request($alias, 'GET', $baseUrl);
        birkly_connection_audit_log('connection_tested', [
            'alias' => $alias,
            'type' => 'http_credential',
            'status' => $result['status'],
            'error' => $result['error'] ?? null,
        ]);
        connections_json_response(['status' => 'ok', 'send_result' => $result]);
    }

    if ($testEmail === '' || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        connections_json_response(['status' => 'error', 'message' => 'A valid test email address is required.'], 422);
    }

    $result = birkly_send_mail([
        'to' => $testEmail,
        'subject' => 'Birkly connection test',
        'html' => '<p>This is a test message from Birkly Settings → Connections.</p>',
        'text' => 'This is a test message from Birkly Settings → Connections.',
    ], $alias);

    birkly_connection_audit_log('connection_tested', [
        'alias' => $alias,
        'test_email' => $testEmail,
        'status' => $result['status'],
        'error' => $result['error'] ?? null,
    ]);

    connections_json_response(['status' => 'ok', 'send_result' => $result]);
}

connections_json_response(['status' => 'error', 'message' => 'Unknown action.'], 400);
