<?php
/**
 * Hosting settings API (P43, P66 delivery probes/repair).
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

if (!headers_sent() && !isset($GLOBALS['__HARU_INTERNAL_CALL'])) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/hosting_urls.php';
require_once __DIR__ . '/../core/hosting_probe.php';
require_once __DIR__ . '/../core/hosting_detect.php';

$rawInput = file_get_contents('php://input');
$input = [];
if ($rawInput !== '') {
    $decoded = json_decode($rawInput, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $input = $decoded;
    }
}

$action = $_GET['action'] ?? $input['action'] ?? '';

function birkly_hosting_api_payload(): array
{
    $config = birkly_hosting_load();
    $publicHost = birkly_hosting_public_host();
    $probe = birkly_delivery_probe(['deep' => !empty($_GET['deep'])]);

    return [
        'hosting' => $config,
        'cms_host' => birkly_hosting_cms_host(),
        'public_host' => $publicHost,
        'registered_public_host' => $publicHost,
        'request_host_role' => birkly_request_host_role(),
        'same_host' => birkly_hosting_same_host(),
        'overlapping_aliases' => birkly_hosting_overlapping_delivery_aliases($config),
        'config_warnings' => birkly_hosting_config_warnings($config),
        'client_snippet' => birkly_hosting_client_snippet(),
        'suggested_cms_url' => birkly_hosting_build_url_from_request(),
        'delivery' => $probe,
        'domain_roles' => birkly_hosting_domain_roles_map(),
        'delivery_matrix' => birkly_hosting_delivery_matrix(),
        'detect' => birkly_hosting_detect_environment(),
    ];
}

if ($action === 'get' || $action === 'status' || $action === 'probe') {
    $data = birkly_hosting_api_payload();
    echo json_encode([
        'status' => 'ok',
        'data' => $data,
    ]);
    exit;
}

if ($action === 'detect') {
    echo json_encode([
        'status' => 'ok',
        'data' => birkly_hosting_propose_config(),
    ]);
    exit;
}

if ($action === 'suggest_domain_role') {
    $host = (string) ($_GET['host'] ?? $input['host'] ?? '');
    echo json_encode([
        'status' => 'ok',
        'data' => array_merge(
            ['host' => birkly_normalize_host($host)],
            birkly_hosting_suggest_delivery_role($host)
        ),
    ]);
    exit;
}

requireAuth('manage_settings');

if ($action === 'apply_proposal') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $proposal = is_array($input['proposal'] ?? null) ? $input['proposal'] : [];
    $result = birkly_hosting_apply_proposal($proposal);
    if (($result['status'] ?? '') === 'ok') {
        $result['data'] = array_merge($result['data'] ?? [], birkly_hosting_api_payload());
    }
    echo json_encode($result);
    exit;
}

if ($action === 'set_domain_role') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $host = (string) ($input['host'] ?? $input['domain'] ?? '');
    $role = (string) ($input['role'] ?? '');
    $result = birkly_hosting_set_domain_delivery_role($host, $role);
    if (($result['status'] ?? '') === 'ok') {
        $result['data'] = birkly_hosting_api_payload();
    }
    echo json_encode($result);
    exit;
}

if ($action === 'set_alias_redirect') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $enabled = array_key_exists('enabled', $input)
        ? !empty($input['enabled'])
        : !empty($input['alias_redirect_to_primary']);
    $result = birkly_hosting_set_alias_redirect($enabled);
    if (($result['status'] ?? '') === 'ok') {
        $result['data'] = birkly_hosting_api_payload();
    }
    echo json_encode($result);
    exit;
}

if ($action === 'set_public_primary') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    $host = (string) ($input['host'] ?? $input['domain'] ?? '');
    $result = birkly_hosting_set_public_primary($host);
    if (($result['status'] ?? '') === 'ok') {
        $result['data'] = birkly_hosting_api_payload();
    }
    echo json_encode($result);
    exit;
}

if ($action === 'set_same_host_public') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $result = birkly_hosting_set_same_host_landing('public');
    $result['delivery'] = birkly_delivery_probe(['deep' => false]);
    $result['data'] = birkly_hosting_api_payload();
    echo json_encode($result);
    exit;
}

if ($action === 'repair' || $action === 'fix_project_paths') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    if ($action === 'fix_project_paths') {
        require_once __DIR__ . '/../core/project_site.php';
        $preview = !empty($input['preview']) || !empty($_GET['preview']);
        $pathRepair = birkly_project_repair_legacy_paths(!$preview);
        $result = [
            'status' => 'ok',
            'message' => $pathRepair['changed']
                ? 'Updated project HTML paths in ' . count($pathRepair['files']) . ' file(s).'
                : 'Project HTML paths are already compatible.',
            'changed' => $pathRepair['changed'],
            'path_repair' => $pathRepair,
        ];
    } else {
        $preview = !empty($input['preview']) || !empty($_GET['preview']);
        $syncDomains = !array_key_exists('sync_domains', $input) || !empty($input['sync_domains']);
        $result = birkly_hosting_repair_delivery(!$preview, $syncDomains);
    }

    $result['delivery'] = birkly_delivery_probe(['deep' => false]);
    $result['data'] = birkly_hosting_api_payload();
    echo json_encode($result);
    exit;
}

if ($action === 'save') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }

    $hosting = $input['hosting'] ?? $input;
    if (!is_array($hosting)) {
        echo json_encode(['status' => 'error', 'message' => 'Hosting settings required']);
        exit;
    }

    $current = birkly_hosting_load();
    $config = array_merge($current, [
        'cms_base_url' => (string) ($hosting['cms_base_url'] ?? $current['cms_base_url'] ?? ''),
        'cms_host_aliases' => $hosting['cms_host_aliases'] ?? $current['cms_host_aliases'] ?? [],
        'public_site_mode' => (string) ($hosting['public_site_mode'] ?? $current['public_site_mode'] ?? 'none'),
        'public_site_url' => (string) ($hosting['public_site_url'] ?? $current['public_site_url'] ?? ''),
        'public_host_aliases' => $hosting['public_host_aliases'] ?? $current['public_host_aliases'] ?? [],
        'same_host_landing' => (string) ($hosting['same_host_landing'] ?? $current['same_host_landing'] ?? 'admin'),
        'strict_admin_host' => (bool) ($hosting['strict_admin_host'] ?? $current['strict_admin_host'] ?? true),
        'alias_redirect_to_primary' => (bool) ($hosting['alias_redirect_to_primary'] ?? $current['alias_redirect_to_primary'] ?? true),
    ]);

    if ($config['public_site_mode'] !== 'none' && $config['public_site_url'] === '') {
        echo json_encode(['status' => 'error', 'message' => 'Public site URL is required when a public site mode is selected']);
        exit;
    }

    birkly_hosting_backup_config();
    if (!birkly_hosting_persist($config, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save hosting settings']);
        exit;
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Hosting settings saved',
        'data' => birkly_hosting_api_payload(),
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
