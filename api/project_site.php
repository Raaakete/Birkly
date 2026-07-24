<?php
/**
 * Admin API for bundled public site workspace (project/).
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/../core/project_site.php';
require_once __DIR__ . '/../core/project_site_filesystem.php';

set_cors_headers('admin');
haru_secure_session_start();

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput ?: '[]', true);
if (!is_array($input)) {
    $input = [];
}

$action = trim((string) ($_GET['action'] ?? $input['action'] ?? 'overview'));

function birkly_project_settings_storage(): Storage
{
    return new Storage(__DIR__ . '/../settings');
}

/**
 * @return array<string, mixed>
 */
function birkly_load_project_settings(): array
{
    $settings = birkly_project_settings_storage()->read('project.json');
    if (!is_array($settings)) {
        $settings = [];
    }

    return array_merge([
        'show_visit_button' => false,
    ], $settings);
}

/**
 * @param array<string, mixed> $patch
 */
function birkly_save_project_settings(array $patch): bool
{
    $existing = birkly_load_project_settings();
    $updated = array_merge($existing, $patch);
    $updated['_updated_at'] = date('c');
    $updated['_updated_by'] = $_SESSION['user']['id'] ?? 'unknown';

    return birkly_project_settings_storage()->write('project.json', $updated);
}

function birkly_project_site_starter_label(): ?string
{
    $siteOnboarding = birkly_project_settings_storage()->read('onboarding.json');
    if (is_array($siteOnboarding)) {
        foreach (['starter_template', 'use_case'] as $key) {
            if (!empty($siteOnboarding[$key]) && is_string($siteOnboarding[$key])) {
                return $siteOnboarding[$key];
            }
        }
    }

    $usersDir = __DIR__ . '/../users';
    if (!is_dir($usersDir)) {
        return null;
    }

    foreach (glob($usersDir . '/*_onboarding.json') ?: [] as $file) {
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            continue;
        }
        $progress = is_array($data['progress'] ?? null) ? $data['progress'] : [];
        foreach (['starter_template', 'use_case'] as $key) {
            if (!empty($progress[$key]) && is_string($progress[$key])) {
                return $progress[$key];
            }
        }
        foreach (['starter_template', 'use_case'] as $key) {
            if (!empty($data[$key]) && is_string($data[$key])) {
                return $data[$key];
            }
        }
    }

    return null;
}

/**
 * @return array{timestamp: ?string, path: ?string}
 */
function birkly_project_site_last_change(): array
{
    $base = birkly_public_site_path();
    if (!is_dir($base)) {
        return ['timestamp' => null, 'path' => null];
    }

    $latestMtime = 0;
    $latestPath = null;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $name = $fileInfo->getFilename();
        if (str_starts_with($name, '.')) {
            continue;
        }
        $mtime = $fileInfo->getMTime();
        if ($mtime >= $latestMtime) {
            $latestMtime = $mtime;
            $latestPath = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($base) + 1));
        }
    }

    if ($latestMtime === 0) {
        return ['timestamp' => null, 'path' => null];
    }

    return [
        'timestamp' => date('c', $latestMtime),
        'path' => $latestPath,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_project_site_activity(int $limit = 30): array
{
    require_once __DIR__ . '/activity.php';

    $projectEvents = [
        'project_file_written',
        'project_file_created',
        'project_file_deleted',
        'ai_approval_approved',
        'ai_approval_created',
    ];

    $activity = [];
    $logFile = __DIR__ . '/../logs/security.log';
    if (!is_file($logFile)) {
        return [];
    }

    $lines = array_reverse(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if (!is_array($entry)) {
            continue;
        }

        $event = (string) ($entry['event'] ?? '');
        if (!in_array($event, $projectEvents, true)) {
            continue;
        }

        $details = is_array($entry['details'] ?? null) ? $entry['details'] : [];
        $actionType = (string) ($details['action_type'] ?? '');

        if ($event === 'ai_approval_approved' || $event === 'ai_approval_created') {
            if (!in_array($actionType, ['write_project_file', 'create_project_file', 'delete_project_file'], true)) {
                continue;
            }
        }

        $user = (string) ($entry['user'] ?? 'Unknown');
        $timestamp = (string) ($entry['timestamp'] ?? '');
        $item = formatActivityItem($event, $user, $details, $timestamp);
        if ($item) {
            $activity[] = $item;
            if (count($activity) >= $limit) {
                break;
            }
        }
    }

    return $activity;
}

if ($action === 'overview') {
    requireAuth('read_settings');

    birkly_ensure_public_site_path();
    $info = birkly_project_site_info();
    $projectSettings = birkly_load_project_settings();
    $lastChange = birkly_project_site_last_change();
    $indexPath = birkly_public_site_path() . '/index.html';

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'site_dir' => $info['site_dir'],
            'site_path' => $info['site_path'],
            'public_url' => $info['public_url_example'],
            'has_index' => is_file($indexPath),
            'starter' => birkly_project_site_starter_label(),
            'last_change' => $lastChange,
            'allowed_extensions' => $info['allowed_extensions'],
            'show_visit_button' => (bool) ($projectSettings['show_visit_button'] ?? false),
            'collections' => $info['collections'],
        ],
    ]);
    exit;
}

if ($action === 'list') {
    requireAuth('read_settings');

    $path = (string) ($_GET['path'] ?? $input['path'] ?? '');
    $fs = new ProjectSiteFileSystem();

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'path' => $path,
            'entries' => $fs->listFiles($path),
        ],
    ]);
    exit;
}

if ($action === 'read') {
    requireAuth('read_settings');

    $path = (string) ($_GET['path'] ?? $input['path'] ?? '');
    if ($path === '') {
        echo json_encode(['status' => 'error', 'message' => 'path is required']);
        exit;
    }

    $fs = new ProjectSiteFileSystem();
    $file = $fs->readFile($path);
    if ($file === null) {
        echo json_encode(['status' => 'error', 'message' => 'File not found or not allowed']);
        exit;
    }

    echo json_encode(['status' => 'ok', 'data' => $file]);
    exit;
}

if ($action === 'activity') {
    requireAuth('read_settings');

    $limit = max(1, min(100, (int) ($_GET['limit'] ?? $input['limit'] ?? 30)));
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'activity' => birkly_project_site_activity($limit),
        ],
    ]);
    exit;
}

requireAuth('manage_settings');

$csrf = (string) ($input['_csrf'] ?? '');
if (!validate_csrf_token($csrf)) {
    log_security_event('permission_denied', ['action' => 'project_site_' . $action, 'reason' => 'invalid CSRF']);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

if ($action === 'save_settings') {
    $showVisit = array_key_exists('show_visit_button', $input)
        ? filter_var($input['show_visit_button'], FILTER_VALIDATE_BOOLEAN)
        : null;

    if ($showVisit === null) {
        echo json_encode(['status' => 'error', 'message' => 'No settings to save']);
        exit;
    }

    if (!birkly_save_project_settings(['show_visit_button' => $showVisit])) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save settings']);
        exit;
    }

    log_security_event('project_settings_updated', [
        'show_visit_button' => $showVisit,
    ]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'Project settings saved',
        'data' => ['show_visit_button' => $showVisit],
    ]);
    exit;
}

if ($action === 'create') {
    $path = trim((string) ($input['path'] ?? ''));
    $content = (string) ($input['content'] ?? '');

    if ($path === '') {
        echo json_encode(['status' => 'error', 'message' => 'path is required']);
        exit;
    }

    $fs = new ProjectSiteFileSystem();
    if (!$fs->createFile($path, $content)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create file (exists, blocked, or invalid path)']);
        exit;
    }

    log_security_event('project_file_created', [
        'path' => $path,
        'source' => 'admin',
    ]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'File created',
        'data' => ['path' => $path],
    ]);
    exit;
}

if ($action === 'delete') {
    $path = trim((string) ($input['path'] ?? ''));
    if ($path === '') {
        echo json_encode(['status' => 'error', 'message' => 'path is required']);
        exit;
    }

    $fs = new ProjectSiteFileSystem();
    if (!$fs->deleteFile($path)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete file (not found or blocked)']);
        exit;
    }

    log_security_event('project_file_deleted', [
        'path' => $path,
        'source' => 'admin',
    ]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'File deleted',
        'data' => ['path' => $path],
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
