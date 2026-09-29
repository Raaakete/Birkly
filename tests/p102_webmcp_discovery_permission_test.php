<?php
/**
 * P102-M9 — WebMCP and Discovery use the screen permission (read).
 * A viewer who can open those screens can still load and save.
 * A session that only has manage_settings cannot.
 * Saves go to a temporary project root so live settings files stay untouched.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$project = sys_get_temp_dir() . '/birkly-p102-settings-' . bin2hex(random_bytes(4));
mkdir($project . '/settings', 0755, true);

$liveFiles = [
    $root . '/settings/webmcp.json',
    $root . '/settings/discovery.json',
];
$liveBefore = [];
foreach ($liveFiles as $path) {
    $liveBefore[$path] = is_file($path) ? hash_file('sha256', $path) : null;
}

function p102_settings_fail(string $message, array &$errors): void
{
    $errors[] = $message;
    fwrite(STDERR, "FAIL $message\n");
}

/**
 * @param array<string, mixed>|null $user
 */
function p102_settings_run(
    string $root,
    string $project,
    string $scriptRel,
    string $bodyGlobal,
    ?array $user,
    string $body
): string {
    $runner = tempnam(sys_get_temp_dir(), 'p102set');
    if ($runner === false) {
        throw new RuntimeException('temp runner failed');
    }
    $script = $runner . '.php';
    rename($runner, $script);
    $rootExport = var_export($root, true);
    $projectExport = var_export($project, true);
    $bodyExport = var_export($body, true);
    $userExport = var_export($user, true);
    $globalExport = var_export($bodyGlobal, true);
    $scriptExport = var_export($scriptRel, true);
    file_put_contents($script, <<<PHP
<?php
\$root = {$rootExport};
\$GLOBALS['__birkly_project_root'] = {$projectExport};
\$GLOBALS[{$globalExport}] = {$bodyExport};
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_GET = [];
\$_POST = [];
require_once \$root . '/api/session.php';
haru_secure_session_start();
\$user = {$userExport};
if (is_array(\$user)) {
    \$_SESSION['user'] = \$user;
    \$_SESSION['csrf_token'] = 'p102-csrf';
    \$_SESSION['last_activity'] = time();
}
require \$root . '/' . {$scriptExport};
PHP);
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open([PHP_BINARY, $script], $descriptors, $pipes, $root);
    if (!is_resource($process)) {
        @unlink($script);
        throw new RuntimeException('proc_open failed');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    proc_close($process);
    @unlink($script);
    $out = is_string($stdout) ? $stdout : '';
    if (is_string($stderr) && trim($stderr) !== '') {
        $out .= "\n" . $stderr;
    }
    return $out;
}

$viewer = ['email' => 'viewer@example.com', 'role' => 'viewer', 'permissions' => ['read', 'read_media']];
$settingsOnly = ['email' => 'ops@example.com', 'role' => 'billing', 'permissions' => ['manage_settings']];

$webmcpGet = p102_settings_run($root, $project, 'api/webmcp.php', '__birkly_webmcp_test_raw_body', $viewer, '');
$webmcpDecoded = json_decode($webmcpGet, true);
if (!is_array($webmcpDecoded) || ($webmcpDecoded['status'] ?? '') !== 'ok' || !isset($webmcpDecoded['data']['catalog'])) {
    p102_settings_fail('viewer WebMCP get failed: ' . $webmcpGet, $errors);
}

$webmcpDenied = p102_settings_run($root, $project, 'api/webmcp.php', '__birkly_webmcp_test_raw_body', $settingsOnly, '');
if (!str_contains($webmcpDenied, 'Forbidden')) {
    p102_settings_fail('manage_settings-only WebMCP get was not forbidden: ' . $webmcpDenied, $errors);
}

$webmcpAnon = p102_settings_run($root, $project, 'api/webmcp.php', '__birkly_webmcp_test_raw_body', null, '');
if (!str_contains($webmcpAnon, 'Unauthorized')) {
    p102_settings_fail('anonymous WebMCP get was not unauthorized: ' . $webmcpAnon, $errors);
}

$webmcpNoCsrf = p102_settings_run(
    $root,
    $project,
    'api/webmcp.php',
    '__birkly_webmcp_test_raw_body',
    $viewer,
    json_encode(['action' => 'save', 'config' => ['enabled' => true]])
);
if (!str_contains($webmcpNoCsrf, 'Invalid CSRF token') || is_file($project . '/settings/webmcp.json')) {
    p102_settings_fail('WebMCP save without CSRF was accepted: ' . $webmcpNoCsrf, $errors);
}

$webmcpSave = p102_settings_run(
    $root,
    $project,
    'api/webmcp.php',
    '__birkly_webmcp_test_raw_body',
    $viewer,
    json_encode([
        'action' => 'save',
        '_csrf' => 'p102-csrf',
        'config' => ['enabled' => true, 'form_allowlist' => ['contact']],
    ])
);
$webmcpSaveDecoded = json_decode($webmcpSave, true);
$webmcpStored = is_file($project . '/settings/webmcp.json')
    ? json_decode((string) file_get_contents($project . '/settings/webmcp.json'), true)
    : null;
if (!is_array($webmcpSaveDecoded) || ($webmcpSaveDecoded['status'] ?? '') !== 'ok') {
    p102_settings_fail('viewer WebMCP save failed: ' . $webmcpSave, $errors);
} elseif (!is_array($webmcpStored) || empty($webmcpStored['enabled']) || ($webmcpStored['form_allowlist'] ?? []) !== ['contact']) {
    p102_settings_fail('viewer WebMCP save did not persist the allowlist', $errors);
}

$webmcpDeniedSave = p102_settings_run(
    $root,
    $project,
    'api/webmcp.php',
    '__birkly_webmcp_test_raw_body',
    $settingsOnly,
    json_encode([
        'action' => 'save',
        '_csrf' => 'p102-csrf',
        'config' => ['enabled' => false, 'form_allowlist' => ['stolen']],
    ])
);
$webmcpAfterDeny = json_decode((string) file_get_contents($project . '/settings/webmcp.json'), true);
if (!str_contains($webmcpDeniedSave, 'Forbidden') || ($webmcpAfterDeny['form_allowlist'] ?? []) !== ['contact']) {
    p102_settings_fail('manage_settings-only WebMCP save changed config: ' . $webmcpDeniedSave, $errors);
}

$discoveryGet = p102_settings_run($root, $project, 'api/discovery.php', '__birkly_discovery_test_raw_body', $viewer, '');
$discoveryDecoded = json_decode($discoveryGet, true);
if (!is_array($discoveryDecoded) || ($discoveryDecoded['status'] ?? '') !== 'ok' || !isset($discoveryDecoded['data']['packs'])) {
    p102_settings_fail('viewer Discovery get failed: ' . $discoveryGet, $errors);
}

$discoveryDenied = p102_settings_run($root, $project, 'api/discovery.php', '__birkly_discovery_test_raw_body', $settingsOnly, '');
if (!str_contains($discoveryDenied, 'Forbidden')) {
    p102_settings_fail('manage_settings-only Discovery get was not forbidden: ' . $discoveryDenied, $errors);
}

$discoverySave = p102_settings_run(
    $root,
    $project,
    'api/discovery.php',
    '__birkly_discovery_test_raw_body',
    $viewer,
    json_encode([
        'action' => 'save_pack',
        '_csrf' => 'p102-csrf',
        'pack' => 'agent_guide',
        'patch' => ['delivery' => ['mode' => 'manual']],
    ])
);
$discoverySaveDecoded = json_decode($discoverySave, true);
$discoveryStored = is_file($project . '/settings/discovery.json')
    ? json_decode((string) file_get_contents($project . '/settings/discovery.json'), true)
    : null;
$savedMode = $discoveryStored['packs']['agent_guide']['delivery']['mode'] ?? '';
if (!is_array($discoverySaveDecoded) || ($discoverySaveDecoded['status'] ?? '') !== 'ok' || $savedMode !== 'manual') {
    p102_settings_fail('viewer Discovery save failed: ' . $discoverySave, $errors);
}

$discoveryNoCsrf = p102_settings_run(
    $root,
    $project,
    'api/discovery.php',
    '__birkly_discovery_test_raw_body',
    $viewer,
    json_encode([
        'action' => 'save_pack',
        'pack' => 'agent_guide',
        'patch' => ['delivery' => ['mode' => 'download']],
    ])
);
$discoveryAfter = json_decode((string) file_get_contents($project . '/settings/discovery.json'), true);
if (!str_contains($discoveryNoCsrf, 'Invalid CSRF token') || ($discoveryAfter['packs']['agent_guide']['delivery']['mode'] ?? '') !== 'manual') {
    p102_settings_fail('Discovery save without CSRF changed the pack: ' . $discoveryNoCsrf, $errors);
}

foreach ($liveFiles as $path) {
    $after = is_file($path) ? hash_file('sha256', $path) : null;
    if ($after !== $liveBefore[$path]) {
        p102_settings_fail('live settings file changed: ' . $path, $errors);
    }
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($project, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $item) {
    if ($item->isDir()) {
        @rmdir($item->getPathname());
    } else {
        @unlink($item->getPathname());
    }
}
@rmdir($project);

if ($errors !== []) {
    fwrite(STDERR, count($errors) . " failure(s)\n");
    exit(1);
}

echo "p102_webmcp_discovery_permission_test ok\n";
