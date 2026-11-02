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
require_once __DIR__ . '/../core/project_settings.php';
require_once __DIR__ . '/../core/project_ssr.php';
require_once __DIR__ . '/../core/activity_format.php';

set_cors_headers('admin');
haru_secure_session_start();

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput ?: '[]', true);
if (!is_array($input)) {
    $input = [];
}

$action = trim((string) ($_GET['action'] ?? $input['action'] ?? $_POST['action'] ?? 'overview'));

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

    try {
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
    } catch (Throwable $e) {
        return ['timestamp' => null, 'path' => null];
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
function birkly_project_site_activity_from_approvals(int $limit): array
{
    $storage = new Storage(__DIR__ . '/../data/ai_approvals');
    if (!is_dir($storage->basePath)) {
        return [];
    }

    $items = [];
    foreach ($storage->list('') as $file) {
        $approval = $storage->read($file);
        if (!is_array($approval) || ($approval['status'] ?? '') !== 'approved') {
            continue;
        }

        $actionType = (string) ($approval['action_type'] ?? '');
        if (!in_array($actionType, ['write_project_file', 'create_project_file', 'delete_project_file'], true)) {
            continue;
        }

        $path = (string) ($approval['action_data']['path'] ?? '');
        $timestamp = (string) ($approval['reviewed_at'] ?? $approval['created_at'] ?? '');
        $reviewedBy = (string) ($approval['reviewed_by'] ?? '');
        $aiUserId = (string) ($approval['ai_user_id'] ?? '');

        $details = [
            'path' => $path,
            'source' => 'ai',
            'ai_user_id' => $aiUserId,
            'reviewed_by' => $reviewedBy,
        ];
        $items[] = birkly_activity_format_item(
            $actionType,
            $reviewedBy !== '' ? $reviewedBy : $aiUserId,
            $details,
            $timestamp,
            'approval_store'
        );
    }

    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['timestamp'] ?? ''), (string) ($a['timestamp'] ?? ''));
    });

    return array_slice($items, 0, $limit);
}

function birkly_project_site_activity_icon(string $event): string
{
    switch ($event) {
        case 'write_project_file':
        case 'project_file_written':
            return '📝';
        case 'create_project_file':
        case 'project_file_created':
            return '📄';
        case 'delete_project_file':
        case 'project_file_deleted':
            return '🗑️';
        case 'project_settings_updated':
            return '⚙️';
        case 'project_folder_created':
            return '📁';
        case 'project_downloaded':
            return '⬇️';
        case 'project_deleted':
            return '🗑️';
        case 'project_git_connected':
        case 'project_git_disconnected':
            return '🔗';
        case 'project_git_pushed':
            return '⬆️';
        case 'project_git_pulled':
            return '⬇️';
        case 'project_git_conflict_resolved':
            return '🔀';
        default:
            return '📌';
    }
}

/**
 * @param array<string, mixed> $details
 */
function birkly_project_site_activity_message(string $event, array $details): string
{
    $path = (string) ($details['path'] ?? '');
    $pathSuffix = $path !== '' ? ' ' . $path : '';

    switch ($event) {
        case 'write_project_file':
        case 'project_file_written':
            return 'Wrote project file' . $pathSuffix;
        case 'create_project_file':
        case 'project_file_created':
            return 'Created project file' . $pathSuffix;
        case 'delete_project_file':
        case 'project_file_deleted':
            return 'Deleted project file' . $pathSuffix;
        case 'project_settings_updated':
            return 'Updated project settings';
        case 'project_folder_created':
            return 'Created project folder' . $pathSuffix;
        case 'project_downloaded':
            return 'Downloaded the project as a ZIP';
        case 'project_deleted':
            return 'Deleted all project files';
        case 'project_git_connected':
            return 'Connected git remote ' . (string) ($details['remote'] ?? '');
        case 'project_git_disconnected':
            return 'Disconnected the git remote';
        case 'project_git_pushed':
            return 'Pushed project files to ' . (string) ($details['branch'] ?? 'the remote');
        case 'project_git_pulled':
            return 'Pulled project files from ' . (string) ($details['branch'] ?? 'the remote');
        case 'project_git_conflict_resolved':
            return 'Resolved a git conflict (' . (string) ($details['strategy'] ?? 'unknown') . ')';
        case 'ai_approval_created':
            return 'Requested approval: ' . str_replace('_', ' ', (string) ($details['action_type'] ?? 'project file action')) . $pathSuffix;
        case 'ai_approval_approved':
            return 'Approved: ' . str_replace('_', ' ', (string) ($details['action_type'] ?? 'project file action')) . $pathSuffix;
        default:
            return ucfirst(str_replace('_', ' ', $event)) . $pathSuffix;
    }
}

/**
 * @return list<array<string, mixed>>
 */
function birkly_project_site_activity(int $limit = 30): array
{
    $projectEvents = [
        'project_file_written',
        'project_file_created',
        'project_file_deleted',
        'project_settings_updated',
        'project_folder_created',
        'project_downloaded',
        'project_deleted',
        'project_git_connected',
        'project_git_disconnected',
        'project_git_pushed',
        'project_git_pulled',
        'project_git_conflict_resolved',
        'ai_approval_approved',
        'ai_approval_created',
    ];

    $items = [];
    $logFile = __DIR__ . '/../logs/security.log';
    if (is_file($logFile)) {
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
                $details['action_type'] = $actionType;
            }

            $user = (string) ($entry['user'] ?? 'Unknown');
            $timestamp = (string) ($entry['timestamp'] ?? '');
            $formatEvent = $event;
            if ($event === 'ai_approval_approved' || $event === 'ai_approval_created') {
                $formatEvent = $actionType;
            }
            $formatted = birkly_activity_format_item($formatEvent, $user, $details, $timestamp, 'security_log');
            if ($formatted !== []) {
                $items[] = $formatted;
            }
        }
    }

    foreach (birkly_project_site_activity_from_approvals($limit) as $approvalItem) {
        $items[] = $approvalItem;
    }

    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['timestamp'] ?? ''), (string) ($a['timestamp'] ?? ''));
    });

    $deduped = [];
    $seen = [];
    foreach ($items as $item) {
        $key = ($item['event'] ?? '') . '|' . ($item['timestamp'] ?? '') . '|' . ($item['details']['path'] ?? '') . '|' . ($item['user'] ?? '');
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $deduped[] = $item;
        if (count($deduped) >= $limit) {
            break;
        }
    }

    return $deduped;
}

if ($action === 'overview') {
    requireAuth('read_settings');
    require_once __DIR__ . '/../core/project_ssr.php';
    require_once __DIR__ . '/../core/hosting_detect.php';

    birkly_ensure_public_site_path();
    $fs = new ProjectSiteFileSystem();
    $projectSettings = birkly_load_project_settings();
    $lastChange = birkly_project_site_last_change();
    $indexPath = birkly_public_site_path() . '/index.html';
    $hosting = birkly_hosting_load();
    $publicHosts = birkly_hosting_public_delivery_hosts();
    $publicPrimary = birkly_hosting_url_host((string) ($hosting['public_site_url'] ?? ''));
    $publicAliases = array_values(array_filter(
        $publicHosts,
        static fn (string $host): bool => $host !== '' && $host !== $publicPrimary
    ));

    require_once __DIR__ . '/../core/project_custom_404.php';
    $custom404Slice = birkly_project_custom_404_settings_slice($projectSettings);

    echo json_encode([
        'status' => 'ok',
        'data' => [
            'site_dir' => birkly_public_site_dir(),
            'site_path' => birkly_public_site_path(),
            'public_url' => birkly_public_site_visit_url($projectSettings),
            'public_primary_host' => $publicPrimary,
            'public_aliases' => $publicAliases,
            'also_served_on' => $publicAliases,
            'cms_url' => (string) ($hosting['cms_base_url'] ?? ''),
            'public_site_mode' => (string) ($hosting['public_site_mode'] ?? 'none'),
            'visit_url' => birkly_public_site_visit_url($projectSettings),
            'visit_url_format' => birkly_normalize_visit_url_format((string) ($projectSettings['visit_url_format'] ?? 'auto')),
            'visit_url_format_resolved' => birkly_resolve_visit_url_format($projectSettings),
            'has_index' => is_file($indexPath),
            'starter' => birkly_project_site_starter_label(),
            'last_change' => $lastChange,
            'allowed_extensions' => $fs->allowedExtensions(),
            'show_visit_button' => (bool) ($projectSettings['show_visit_button'] ?? false),
            'render_mode' => (string) ($projectSettings['render_mode'] ?? 'server'),
            'hosting_type' => (string) ($projectSettings['hosting_type'] ?? 'birkly'),
            'ssr_cache_ttl' => (int) ($projectSettings['ssr_cache_ttl'] ?? 300),
            'ssr_runtime' => birkly_project_ssr_runtime_status(),
            'custom_404_enabled' => $custom404Slice['custom_404_enabled'],
            'custom_404_path' => $custom404Slice['custom_404_path'],
            'custom_404_file_exists' => birkly_project_custom_404_resolve_file($projectSettings) !== null,
            'custom_404_suggestion' => birkly_project_custom_404_show_enable_suggestion(),
            'custom_404_applies' => birkly_project_custom_404_hosting_applies(),
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

    echo json_encode(['status' => 'ok', 'data' => array_merge($file, [
        'editable' => $fs->isTextFile($path),
        'image' => $fs->isImageFile($path),
    ])]);
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

if ($action === 'download') {
    requireAuth('manage_settings');

    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'ZipArchive is not available on this server']);
        exit;
    }

    birkly_ensure_public_site_path();
    $fs = new ProjectSiteFileSystem();
    $base = $fs->basePath();
    $files = $fs->listAllFilePaths();

    $zipName = 'project-' . date('Ymd-His') . '.zip';
    $zipPath = sys_get_temp_dir() . '/' . $zipName;
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Could not create archive']);
        exit;
    }

    foreach ($files as $relativePath) {
        $full = $base . '/' . $relativePath;
        if (is_file($full)) {
            $zip->addFile($full, 'project/' . $relativePath);
        }
    }
    $zip->close();

    log_security_event('project_downloaded', ['source' => 'admin', 'file_count' => count($files)]);

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $zipName . '"');
    header('Content-Length: ' . (string) filesize($zipPath));
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

requireAuth('manage_settings');

$csrf = (string) ($input['_csrf'] ?? $_POST['_csrf'] ?? '');
if (!validate_csrf_token($csrf)) {
    log_security_event('permission_denied', ['action' => 'project_site_' . $action, 'reason' => 'invalid CSRF']);
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

if ($action === 'save_settings') {
    $patch = [];

    if (array_key_exists('show_visit_button', $input)) {
        $patch['show_visit_button'] = filter_var($input['show_visit_button'], FILTER_VALIDATE_BOOLEAN);
    }

    if (array_key_exists('visit_url_format', $input)) {
        $patch['visit_url_format'] = birkly_normalize_visit_url_format((string) $input['visit_url_format']);
    }

    // Render / SSR controls (P51-M3): whitelist + clamp.
    $renderSettings = birkly_project_ssr_validate_render_settings($input);
    if ($renderSettings['error'] !== null) {
        echo json_encode(['status' => 'error', 'message' => $renderSettings['error']]);
        exit;
    }
    $patch = array_merge($patch, $renderSettings['patch']);

    require_once __DIR__ . '/../core/project_custom_404.php';
    $custom404 = birkly_project_custom_404_validate_save_input($input);
    if ($custom404['error'] !== null) {
        echo json_encode(['status' => 'error', 'message' => $custom404['error']]);
        exit;
    }
    if ($custom404['patch'] !== []) {
        $patch = array_merge($patch, $custom404['patch']);
    }

    if ($patch === []) {
        echo json_encode(['status' => 'error', 'message' => 'No settings to save']);
        exit;
    }

    if (!birkly_save_project_settings($patch)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save settings']);
        exit;
    }

    // Any change that affects server-side rendering busts the SSR cache so the
    // next request re-renders under the new configuration.
    if ($renderSettings['ssr_affected']) {
        birkly_project_ssr_invalidate_all();
    }
    if ($custom404['patch'] !== []) {
        birkly_project_ssr_invalidate_all();
    }

    log_security_event('project_settings_updated', $patch);

    birkly_ensure_public_site_path();
    $indexPath = birkly_public_site_path() . '/index.html';
    $settings = birkly_load_project_settings();
    $custom404Slice = birkly_project_custom_404_settings_slice($settings);
    $custom404Warning = $custom404['warning'];
    if ($custom404Warning === null && $custom404Slice['custom_404_enabled'] && birkly_project_custom_404_resolve_file($settings) === null) {
        $custom404Warning = 'The chosen custom 404 page was not found in project/. Visitors will still see the plain Not found response until the file exists.';
    }

    echo json_encode([
        'status' => 'ok',
        'message' => 'Project settings saved',
        'data' => [
            'show_visit_button' => (bool) ($settings['show_visit_button'] ?? false),
            'visit_url_format' => birkly_normalize_visit_url_format((string) ($settings['visit_url_format'] ?? 'auto')),
            'visit_url_format_resolved' => birkly_resolve_visit_url_format($settings),
            'render_mode' => (string) ($settings['render_mode'] ?? 'server'),
            'hosting_type' => (string) ($settings['hosting_type'] ?? 'birkly'),
            'ssr_cache_ttl' => (int) ($settings['ssr_cache_ttl'] ?? 300),
            'ssr_cache_invalidated' => $renderSettings['ssr_affected'] || $custom404['patch'] !== [],
            'visit_url' => birkly_public_site_visit_url($settings),
            'has_index' => is_file($indexPath),
            'custom_404_enabled' => $custom404Slice['custom_404_enabled'],
            'custom_404_path' => $custom404Slice['custom_404_path'],
            'custom_404_file_exists' => birkly_project_custom_404_resolve_file($settings) !== null,
            'custom_404_warning' => $custom404Warning,
            'custom_404_suggestion' => birkly_project_custom_404_show_enable_suggestion(),
            'custom_404_applies' => birkly_project_custom_404_hosting_applies(),
        ],
    ]);
    exit;
}

if ($action === 'invalidate_ssr_cache') {
    birkly_project_ssr_invalidate_all();

    log_security_event('project_ssr_cache_invalidated', [
        'source' => 'admin',
    ]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'SSR cache invalidated',
    ]);
    exit;
}

if ($action === 'write') {
    $path = trim((string) ($input['path'] ?? ''));
    $content = (string) ($input['content'] ?? '');

    if ($path === '') {
        echo json_encode(['status' => 'error', 'message' => 'path is required']);
        exit;
    }

    $fs = new ProjectSiteFileSystem();
    if (!$fs->isTextFile($path)) {
        echo json_encode(['status' => 'error', 'message' => 'This file type cannot be edited as text']);
        exit;
    }
    if (!$fs->writeFile($path, $content)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save file']);
        exit;
    }

    log_security_event('project_file_written', [
        'path' => $path,
        'source' => 'admin',
    ]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'File saved',
        'data' => ['path' => $path],
    ]);
    exit;
}

if ($action === 'create_folder') {
    $path = trim((string) ($input['path'] ?? ''));
    if ($path === '') {
        echo json_encode(['status' => 'error', 'message' => 'path is required']);
        exit;
    }

    $fs = new ProjectSiteFileSystem();
    if (!$fs->createDirectory($path)) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create folder']);
        exit;
    }

    log_security_event('project_folder_created', [
        'path' => $path,
        'source' => 'admin',
    ]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'Folder created',
        'data' => ['path' => $path],
    ]);
    exit;
}

if ($action === 'upload') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
        exit;
    }

    $dir = trim((string) ($_POST['path'] ?? ''));
    $dir = $dir !== '' ? rtrim(str_replace('\\', '/', $dir), '/') : '';
    $filename = basename((string) ($_FILES['file']['name'] ?? ''));
    if ($filename === '' || str_starts_with($filename, '.')) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid filename']);
        exit;
    }

    $relativePath = $dir === '' ? $filename : $dir . '/' . $filename;
    $fs = new ProjectSiteFileSystem();
    if (!$fs->writeBinaryFile($relativePath, (string) $_FILES['file']['tmp_name'])) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to upload file (blocked type or invalid path)']);
        exit;
    }

    log_security_event('project_file_created', [
        'path' => $relativePath,
        'source' => 'admin',
        'upload' => true,
    ]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'File uploaded',
        'data' => ['path' => $relativePath],
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

if ($action === 'delete_project') {
    $confirmPhrase = trim((string) ($input['confirm_phrase'] ?? ''));
    if ($confirmPhrase !== 'DELETE') {
        echo json_encode(['status' => 'error', 'message' => 'Confirmation phrase required (type DELETE)']);
        exit;
    }

    birkly_ensure_public_site_path();
    $fs = new ProjectSiteFileSystem();
    if (!$fs->clearAllContents()) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete project contents']);
        exit;
    }

    birkly_project_ssr_invalidate_all();

    log_security_event('project_deleted', [
        'source' => 'admin',
    ]);

    echo json_encode([
        'status' => 'ok',
        'message' => 'Project contents deleted',
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
