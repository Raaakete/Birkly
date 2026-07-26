<?php
/**
 * AI Presence admin API (P46).
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

header('Content-Type: application/json');

require_once 'session.php';
haru_secure_session_start();

require_once 'auth.php';
require_once 'validate.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/ai_presence.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$currentUser = $_SESSION['user'];
$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '{}', true);
if (!is_array($input)) {
    $input = [];
}
$input = sanitize_input($input);
$action = $input['action'] ?? $_GET['action'] ?? 'get';

if (!birkly_ai_presence_can_view($currentUser)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

$readActions = ['get', 'completeness', 'staleness', 'preview_query', 'setup_prefill', 'public_urls'];
$writeActions = ['save_draft', 'publish', 'fetch_url_meta', 'suggest', 'accept_suggestion', 'setup_complete', 'setup_skip'];

if (in_array($action, $writeActions, true) && !birkly_ai_presence_can_edit($currentUser)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

if ($action === 'get') {
    $draft = birkly_ai_presence_load_draft();
    $published = birkly_ai_presence_load_published();
    $meta = birkly_ai_presence_load_meta();
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'draft' => $draft,
            'published' => $published,
            'meta' => $meta,
            'completeness' => birkly_ai_presence_completeness($draft),
            'next_steps' => birkly_ai_presence_next_steps($draft),
            'public_urls' => birkly_ai_presence_public_urls(),
            'staleness' => birkly_ai_presence_staleness($draft),
            'can_edit' => birkly_ai_presence_can_edit($currentUser),
        ],
    ]);
    exit;
}

if ($action === 'completeness') {
    $draft = birkly_ai_presence_load_draft();
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'completeness' => birkly_ai_presence_completeness($draft),
            'next_steps' => birkly_ai_presence_next_steps($draft),
        ],
    ]);
    exit;
}

if ($action === 'staleness') {
    echo json_encode([
        'status' => 'ok',
        'data' => ['warnings' => birkly_ai_presence_staleness()],
    ]);
    exit;
}

if ($action === 'public_urls') {
    echo json_encode(['status' => 'ok', 'data' => birkly_ai_presence_public_urls()]);
    exit;
}

if ($action === 'setup_prefill') {
    echo json_encode(['status' => 'ok', 'data' => ['prefill' => birkly_ai_presence_setup_prefill()]]);
    exit;
}

if ($action === 'preview_query') {
    $question = trim((string) ($input['question'] ?? $_GET['question'] ?? ''));
    $result = birkly_ai_presence_preview_query($question);
    echo json_encode(['status' => 'ok', 'data' => $result]);
    exit;
}

if (!in_array($action, $readActions, true)) {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
}

if ($action === 'save_draft') {
    try {
        $partial = $input['draft'] ?? $input;
        unset($partial['action'], $partial['_csrf']);
        $merged = birkly_ai_presence_merge_draft(is_array($partial) ? $partial : []);
        birkly_ai_presence_save_draft($merged);
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'draft' => $merged,
                'completeness' => birkly_ai_presence_completeness($merged),
            ],
        ]);
    } catch (InvalidArgumentException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'publish') {
    $result = birkly_ai_presence_publish();
    if (!$result['ok']) {
        echo json_encode(['status' => 'error', 'message' => implode('; ', $result['errors'] ?? ['Publish failed'])]);
        exit;
    }
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'version' => $result['version'],
            'meta' => birkly_ai_presence_load_meta(),
            'public_urls' => birkly_ai_presence_public_urls(),
        ],
    ]);
    exit;
}

if ($action === 'fetch_url_meta') {
    $url = trim((string) ($input['url'] ?? ''));
    $result = birkly_ai_presence_fetch_url_meta($url);
    if (!$result['ok']) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Failed']);
        exit;
    }
    echo json_encode(['status' => 'ok', 'data' => $result['data']]);
    exit;
}

if ($action === 'suggest') {
    $draft = birkly_ai_presence_load_draft();
    $suggestions = birkly_ai_presence_rule_suggestions($draft);

    $aiConnectionId = trim((string) ($input['ai_connection_id'] ?? ''));
    if ($aiConnectionId !== '' && function_exists('callAiProvider')) {
        require_once __DIR__ . '/ai_chat.php';
        require_once __DIR__ . '/ai_helpers.php';
        require_once __DIR__ . '/storage.php';
        $storage = new Storage('../users');
        $conn = $storage->read($aiConnectionId . '.json');
        if (is_array($conn) && ($conn['type'] ?? '') === 'ai') {
            $chunks = birkly_ai_presence_gather_cms_context($draft);
            $context = json_encode($chunks, JSON_UNESCAPED_UNICODE);
            $prompt = "Analyze this CMS content and suggest brand identity fields as JSON array with objects: id, field (identity.* or positioning.* or claims.new), label, value, source. Do not invent facts.\n\n" . $context;
            try {
                $apiKey = decryptApiKey($conn['api_key_encrypted'] ?? '');
                $response = callAiProvider(
                    (string) ($conn['provider'] ?? 'openai'),
                    $apiKey,
                    birkly_ai_resolve_model((string) ($conn['provider'] ?? 'openai'), $conn['model'] ?? null),
                    'You suggest structured brand context updates. Return JSON only.',
                    $prompt,
                    '',
                    (string) ($conn['api_url'] ?? '')
                );
                if (is_string($response)) {
                    $decoded = json_decode($response, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $row) {
                            if (is_array($row) && !empty($row['field'])) {
                                $suggestions[] = $row;
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                // Rule-based suggestions still returned
            }
        }
    }

    echo json_encode(['status' => 'ok', 'data' => ['suggestions' => $suggestions]]);
    exit;
}

if ($action === 'accept_suggestion') {
    $suggestion = $input['suggestion'] ?? null;
    if (!is_array($suggestion)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid suggestion']);
        exit;
    }
    $draft = birkly_ai_presence_load_draft();
    $draft = birkly_ai_presence_apply_suggestion($draft, $suggestion);
    birkly_ai_presence_save_draft($draft);
    echo json_encode(['status' => 'ok', 'data' => ['draft' => $draft]]);
    exit;
}

if ($action === 'setup_complete') {
    $draft = birkly_ai_presence_merge_draft($input['draft'] ?? []);
    $draft['setup_completed_at'] = date('c');
    birkly_ai_presence_save_draft($draft);
    echo json_encode(['status' => 'ok', 'data' => ['draft' => $draft]]);
    exit;
}

if ($action === 'setup_skip') {
    $draft = birkly_ai_presence_load_draft();
    $draft['setup_skipped_at'] = date('c');
    birkly_ai_presence_save_draft($draft);
    echo json_encode(['status' => 'ok', 'data' => ['draft' => $draft]]);
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
