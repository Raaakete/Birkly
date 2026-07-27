<?php
/**
 * AI Presence admin API (P46 + P47 Pro).
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
require_once __DIR__ . '/../core/ai_presence_pro.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$currentUser = $_SESSION['user'];
$userId = (string) ($currentUser['id'] ?? $currentUser['email'] ?? '');
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

$readActions = [
    'get', 'completeness', 'staleness', 'preview_query', 'setup_prefill', 'public_urls',
    'list_connections', 'list_versions', 'cms_diff', 'list_collections',
];
$writeActions = [
    'save_draft', 'publish', 'fetch_url_meta', 'suggest', 'accept_suggestion', 'accept_suggestions',
    'setup_complete', 'setup_skip', 'copilot', 'field_ai', 'wizard_generate', 'wizard_apply', 'cms_refresh',
];

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
            'cms_diff' => birkly_ai_presence_cms_sync_diff($draft),
            'collections' => birkly_ai_presence_list_collections(),
            'ai_connections' => birkly_ai_presence_list_ai_connections_for_user($userId),
            'can_edit' => birkly_ai_presence_can_edit($currentUser),
        ],
    ]);
    exit;
}

if ($action === 'list_connections') {
    echo json_encode([
        'status' => 'ok',
        'data' => ['connections' => birkly_ai_presence_list_ai_connections_for_user($userId)],
    ]);
    exit;
}

if ($action === 'list_versions') {
    echo json_encode(['status' => 'ok', 'data' => ['versions' => birkly_ai_presence_list_versions()]]);
    exit;
}

if ($action === 'cms_diff') {
    $draft = birkly_ai_presence_load_draft();
    echo json_encode(['status' => 'ok', 'data' => ['diffs' => birkly_ai_presence_cms_sync_diff($draft)]]);
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
    $aiConnectionId = trim((string) ($input['ai_connection_id'] ?? ''));
    $result = $aiConnectionId !== ''
        ? birkly_ai_presence_preview_query_pro($question, $aiConnectionId)
        : birkly_ai_presence_preview_query($question);
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

if ($action === 'list_collections') {
    echo json_encode([
        'status' => 'ok',
        'data' => ['collections' => birkly_ai_presence_list_collections()],
    ]);
    exit;
}

if ($action === 'suggest' || $action === 'copilot') {
    $draft = birkly_ai_presence_load_draft();
    $aiConnectionId = trim((string) ($input['ai_connection_id'] ?? ''));
    $mode = trim((string) ($input['mode'] ?? 'all'));
    $result = birkly_ai_presence_copilot_suggestions($draft, $aiConnectionId, $mode);
    echo json_encode(['status' => 'ok', 'data' => $result]);
    exit;
}

if ($action === 'field_ai') {
    $draft = birkly_ai_presence_load_draft();
    $field = trim((string) ($input['field'] ?? ''));
    $aiConnectionId = trim((string) ($input['ai_connection_id'] ?? ''));
    if ($field === '' || $aiConnectionId === '') {
        echo json_encode(['status' => 'error', 'message' => 'field and ai_connection_id required']);
        exit;
    }
    $result = birkly_ai_presence_field_ai($field, $draft, $aiConnectionId);
    if (!empty($result['error']) && empty($result['suggestions'])) {
        echo json_encode(['status' => 'error', 'message' => $result['error']]);
        exit;
    }
    echo json_encode(['status' => 'ok', 'data' => $result]);
    exit;
}

if ($action === 'wizard_generate') {
    $collections = $input['collections'] ?? [];
    if (!is_array($collections)) {
        $collections = [];
    }
    $aiConnectionId = trim((string) ($input['ai_connection_id'] ?? ''));
    $result = birkly_ai_presence_wizard_generate($collections, $aiConnectionId);
    echo json_encode(['status' => 'ok', 'data' => $result]);
    exit;
}

if ($action === 'wizard_apply') {
    $draft = birkly_ai_presence_load_draft();
    $patch = $input['draft_patch'] ?? [];
    if (is_array($patch) && $patch !== []) {
        $draft = birkly_ai_presence_merge_draft($patch);
    }
    $suggestions = $input['suggestions'] ?? [];
    if (is_array($suggestions)) {
        foreach ($suggestions as $suggestion) {
            if (is_array($suggestion)) {
                $draft = birkly_ai_presence_apply_suggestion($draft, $suggestion);
            }
        }
    }
    birkly_ai_presence_save_draft($draft);
    echo json_encode(['status' => 'ok', 'data' => ['draft' => $draft]]);
    exit;
}

if ($action === 'cms_refresh') {
    $draft = birkly_ai_presence_load_draft();
    $refIndex = (int) ($input['ref_index'] ?? -1);
    $field = isset($input['field']) ? trim((string) $input['field']) : null;
    if ($refIndex < 0) {
        echo json_encode(['status' => 'error', 'message' => 'ref_index required']);
        exit;
    }
    $draft = birkly_ai_presence_cms_sync_apply($draft, $refIndex, $field ?: null);
    birkly_ai_presence_save_draft($draft);
    echo json_encode(['status' => 'ok', 'data' => ['draft' => $draft]]);
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

if ($action === 'accept_suggestions') {
    $suggestions = $input['suggestions'] ?? [];
    if (!is_array($suggestions)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid suggestions']);
        exit;
    }
    $draft = birkly_ai_presence_load_draft();
    foreach ($suggestions as $suggestion) {
        if (is_array($suggestion)) {
            $draft = birkly_ai_presence_apply_suggestion($draft, $suggestion);
        }
    }
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
