<?php
/**
 * Discovery admin API (P67-M1).
 *
 * Pack masters, status, and verify checklist for SEO · Agent Guide · WebMCP.
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
require_once __DIR__ . '/../core/discovery.php';
require_once __DIR__ . '/../core/site_seo.php';
require_once __DIR__ . '/../core/site_seo_url_map.php';

// Same permission as the Discovery, Agent Guide, and AI Presence screens.
requireAuth('read');

$raw = $GLOBALS['__birkly_discovery_test_raw_body'] ?? file_get_contents('php://input');
if (!is_string($raw)) {
    $raw = '';
}
$input = json_decode($raw ?: '{}', true);
if (!is_array($input)) {
    $input = [];
}
$input = sanitize_input($input);
$action = $input['action'] ?? $_GET['action'] ?? 'get';

if ($action === 'get') {
    echo json_encode([
        'status' => 'ok',
        'data' => birkly_discovery_status(),
    ]);
    exit;
}

$csrf = $input['_csrf'] ?? '';
if (!validate_csrf_token($csrf)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

if ($action === 'save_pack') {
    $packId = (string) ($input['pack'] ?? '');
    $patch = $input['patch'] ?? $input;
    if (!is_array($patch)) {
        $patch = [];
    }
    unset($patch['action'], $patch['_csrf'], $patch['pack']);

    $result = birkly_discovery_save_pack($packId, $patch);
    if (!$result['ok']) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Could not save']);
        exit;
    }

    // Sync legacy stores when masters change (additive — data never deleted).
    if ($packId === 'webmcp' && array_key_exists('enabled', $patch)) {
        require_once __DIR__ . '/../core/webmcp.php';
        $wm = birkly_webmcp_load();
        $wm['enabled'] = (bool) $patch['enabled'];
        birkly_webmcp_save($wm);
    }
    if ($packId === 'seo' && array_key_exists('enabled', $patch) && empty($patch['enabled'])) {
        birkly_site_seo_unpublish();
    }

    echo json_encode([
        'status' => 'ok',
        'data' => birkly_discovery_status(),
    ]);
    exit;
}

if ($action === 'save_seo') {
    $payload = $input['config'] ?? $input;
    if (!is_array($payload)) {
        $payload = [];
    }
    unset($payload['action'], $payload['_csrf']);

    $result = birkly_site_seo_save($payload);
    if (!$result['ok']) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Could not save']);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'seo' => $result['config'],
            'status' => birkly_discovery_status(),
        ],
    ]);
    exit;
}

if ($action === 'publish_seo') {
    $result = birkly_site_seo_publish();
    if (!$result['ok']) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Could not publish']);
        exit;
    }
    $discovery = birkly_discovery_load();
    $discovery['packs']['seo']['enabled'] = true;
    birkly_discovery_save($discovery);

    echo json_encode([
        'status' => 'ok',
        'data' => birkly_discovery_status(),
    ]);
    exit;
}

if ($action === 'unpublish_seo') {
    birkly_site_seo_unpublish();
    echo json_encode([
        'status' => 'ok',
        'data' => birkly_discovery_status(),
    ]);
    exit;
}

if ($action === 'verify') {
    $result = birkly_discovery_run_verify();
    echo json_encode([
        'status' => 'ok',
        'data' => array_merge(birkly_discovery_status(), [
            'verify_checks' => $result['checks'],
            'verify_last_run' => $result['ran_at'],
        ]),
    ]);
    exit;
}

if ($action === 'discovery_setup' || $action === 'seo_generate_defaults') {
    $status = birkly_discovery_status();
    $proposal = [
        'seo' => [
            'defaults' => [
                'description' => '',
                'title_suffix' => '',
            ],
        ],
        'message' => 'Review proposed defaults before applying.',
    ];
    if ($action === 'seo_generate_defaults') {
        require_once __DIR__ . '/../core/ai_presence.php';
        $draft = birkly_ai_presence_load_draft();
        $identity = is_array($draft['identity'] ?? null) ? $draft['identity'] : [];
        $proposal['seo']['defaults']['description'] = trim((string) ($identity['short_description'] ?? ''));
        $name = trim((string) ($identity['official_name'] ?? ''));
        if ($name !== '') {
            $proposal['seo']['defaults']['site_title'] = $name;
            $proposal['seo']['defaults']['title_suffix'] = ' · ' . $name;
        }
    }
    echo json_encode(['status' => 'ok', 'data' => $proposal, 'status_snapshot' => $status]);
    exit;
}

if ($action === 'scan_urls') {
    $result = birkly_seo_scan_and_save();
    if (!$result['ok']) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Scan failed']);
        exit;
    }
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'seo' => $result['config'],
            'rows' => $result['rows'] ?? [],
            'stats' => $result['stats'] ?? [],
            'summary' => $result['summary'] ?? [],
            'status' => birkly_discovery_status(),
        ],
    ]);
    exit;
}

if ($action === 'seo_url_map_setup') {
    $result = birkly_seo_automated_setup();
    if (!$result['ok']) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Setup failed']);
        exit;
    }
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'seo' => $result['config'],
            'rows' => $result['rows'] ?? [],
            'summary' => $result['summary'] ?? [],
            'status' => birkly_discovery_status(),
        ],
    ]);
    exit;
}

if ($action === 'save_url_map') {
    $patch = $input['url_map'] ?? $input;
    if (!is_array($patch)) {
        $patch = [];
    }
    unset($patch['action'], $patch['_csrf']);
    $result = birkly_seo_save_url_map($patch);
    if (!$result['ok']) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Could not save']);
        exit;
    }
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'seo' => $result['config'],
            'rows' => birkly_seo_url_map_admin_rows($result['config'] ?? []),
            'status' => birkly_discovery_status(),
        ],
    ]);
    exit;
}

if ($action === 'save_detail_binding') {
    $binding = $input['binding'] ?? $input;
    if (!is_array($binding)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid binding']);
        exit;
    }
    unset($binding['action'], $binding['_csrf']);
    $result = birkly_seo_save_detail_binding($binding);
    if (!$result['ok']) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Could not save binding']);
        exit;
    }
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'seo' => $result['config'],
            'rows' => birkly_seo_url_map_admin_rows($result['config'] ?? []),
            'status' => birkly_discovery_status(),
        ],
    ]);
    exit;
}

if ($action === 'delete_detail_binding') {
    $bindingId = trim((string) ($input['binding_id'] ?? $input['id'] ?? ''));
    if ($bindingId === '') {
        echo json_encode(['status' => 'error', 'message' => 'Missing binding id']);
        exit;
    }
    $result = birkly_seo_delete_detail_binding($bindingId);
    if (!$result['ok']) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Could not delete binding']);
        exit;
    }
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'seo' => $result['config'],
            'rows' => birkly_seo_url_map_admin_rows($result['config'] ?? []),
            'status' => birkly_discovery_status(),
        ],
    ]);
    exit;
}

if ($action === 'seo_propose_all') {
    $proposed = birkly_seo_propose_overrides();
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'proposed' => $proposed,
            'message' => 'Review proposed overrides — nothing is saved until you apply rows.',
        ],
    ]);
    exit;
}

if ($action === 'seo_apply_proposals') {
    $proposed = $input['proposed'] ?? [];
    if (!is_array($proposed)) {
        $proposed = [];
    }
    $result = birkly_seo_save_url_map($proposed);
    if (!$result['ok']) {
        echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Could not apply']);
        exit;
    }
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'seo' => $result['config'],
            'rows' => birkly_seo_url_map_admin_rows($result['config'] ?? []),
            'warnings' => $result['warnings'] ?? [],
            'status' => birkly_discovery_status(),
        ],
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
