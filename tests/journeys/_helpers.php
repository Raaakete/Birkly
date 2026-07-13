<?php
/**
 * Shared helpers for P28 user journey tests.
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/http_test_helpers.php';

/** @var int */
$GLOBALS['journey_failures'] = 0;

function journey_repo_root(): string
{
    return dirname(__DIR__, 2);
}

function journey_sibling_repo(string $name): ?string
{
    $path = dirname(journey_repo_root()) . '/' . $name;
    return is_dir($path) ? $path : null;
}

function journey_reset_failures(): void
{
    $GLOBALS['journey_failures'] = 0;
    test_reset_failures();
}

function journey_failures_count(): int
{
    return (int) ($GLOBALS['journey_failures'] ?? 0);
}

function journey_assert(string $name, bool $ok, string $detail = ''): void
{
    global $journey_failures;
    if (!$ok) {
        $journey_failures++;
        if ($detail !== '') {
            fwrite(STDERR, "  $name: $detail\n");
        }
    }
    test_assert($name, $ok, $detail);
}

function journey_fail(string $message): void
{
    throw new RuntimeException($message);
}

function journey_parse_base_url(?string $cliBase = null): string
{
    $base = $cliBase ?: (getenv('BIRKLY_JOURNEY_BASE_URL') ?: 'http://127.0.0.1:8080');
    return rtrim($base, '/');
}

function journey_is_external_base(string $base): bool
{
    $host = parse_url($base, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return false;
    }
    return !in_array($host, ['127.0.0.1', 'localhost'], true);
}

function journey_base_port(string $base): int
{
    $port = parse_url($base, PHP_URL_PORT);
    if (is_int($port)) {
        return $port;
    }
    return parse_url($base, PHP_URL_SCHEME) === 'https' ? 443 : 80;
}

function journey_http_ready(string $base): bool
{
    return birkly_test_http_status_only($base . '/api/health.php') > 0;
}

function journey_start_cms_server(string $root, int $port): int
{
    return birkly_test_http_server_start($root, $port);
}

function journey_stop_cms_server(int $pid): void
{
    birkly_test_http_server_stop($pid);
}

/**
 * @return array{pid:int,started:bool,base:string}
 */
function journey_ensure_cms_server(string $root, string $base): array
{
    if (journey_http_ready($base)) {
        return ['pid' => 0, 'started' => false, 'base' => $base];
    }
    $port = journey_base_port($base);
    $pid = journey_start_cms_server($root, $port);
    return ['pid' => $pid, 'started' => true, 'base' => $base];
}

function journey_run_script(string $script, array $args = [], ?string $cwd = null): array
{
    $root = journey_repo_root();
    $path = str_starts_with($script, '/') ? $script : $root . '/' . ltrim($script, '/');
    if (!is_file($path)) {
        return ['ok' => false, 'code' => 1, 'output' => "missing script: $path"];
    }

    $cmd = escapeshellarg($path);
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg((string) $arg);
    }

    $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $descriptor, $pipes, $cwd ?: $root);
    if (!is_resource($proc)) {
        return ['ok' => false, 'code' => 1, 'output' => 'proc_open failed'];
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);

    return [
        'ok' => $code === 0,
        'code' => $code,
        'output' => trim($stdout . "\n" . $stderr),
    ];
}

function journey_install_reference_project(string $project): void
{
    $result = journey_run_script('scripts/install-reference-project.sh', [$project]);
    if (!$result['ok']) {
        journey_fail('install-reference-project.sh ' . $project . ' failed: ' . $result['output']);
    }
}

function journey_install_dev_plugins(string ...$ids): void
{
    $result = journey_run_script('scripts/install-dev-plugins.sh');
    if (!$result['ok']) {
        journey_fail('install-dev-plugins.sh failed: ' . $result['output']);
    }
}

function journey_activate_plugins(string ...$pluginIds): void
{
    $path = journey_repo_root() . '/settings/plugins.json';
    $data = [];
    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    foreach ($pluginIds as $id) {
        if (!isset($data[$id]) || !is_array($data[$id])) {
            $data[$id] = ['active' => true, 'settings' => []];
        } else {
            $data[$id]['active'] = true;
        }
    }
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function journey_grant_collection_create(string $collectionSlug, string $tier = 'all'): void
{
    $path = journey_repo_root() . '/data/plugins/user_management/metadata/collection_permissions.json';
    $data = [];
    if (is_file($path)) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    if (!isset($data[$collectionSlug]) || !is_array($data[$collectionSlug])) {
        $data[$collectionSlug] = [];
    }
    if (!isset($data[$collectionSlug][$tier]) || !is_array($data[$collectionSlug][$tier])) {
        $data[$collectionSlug][$tier] = [];
    }
    $data[$collectionSlug][$tier]['create'] = true;
    $data[$collectionSlug][$tier]['edit_own'] = true;
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function journey_commerce_reindex(): void
{
    $root = journey_repo_root();
    require_once $root . '/plugins/commerce/init.php';
    require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
    $indexer = new CatalogIndexer();
    $indexer->reindexAll($root . '/content');
}

function journey_http(string $base, string $path, array $options = []): array
{
    $url = str_starts_with($path, 'http') ? $path : rtrim($base, '/') . '/' . ltrim($path, '/');
    return birkly_test_http_request($url, $options);
}

function journey_page_bundle(string $base, array $requests, string $language = 'en'): ?array
{
    $response = journey_http($base, '/api/page_bundle.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'language' => $language,
            'requests' => $requests,
            'lean' => true,
        ]),
    ]);

    if ($response['code'] !== 200) {
        return null;
    }

    return birkly_test_json_decode($response['body']);
}

function journey_temp_cookie_file(string $label): string
{
    return sys_get_temp_dir() . '/birkly-journey-' . $label . '-' . getmypid() . '.txt';
}

function journey_admin_csrf(string $base, string $cookieFile): string
{
    $csrfResponse = journey_http($base, '/api/csrf.php', [
        'cookie_file' => $cookieFile,
        'headers' => ['Referer: ' . $base . '/admin/index.php'],
    ]);
    $csrfData = birkly_test_json_decode($csrfResponse['body']);
    return (string) ($csrfData['data']['csrf_token'] ?? '');
}

function journey_create_first_admin(string $base, string $cookieFile, string $email, string $password, string $name = 'Journey Admin'): void
{
    $create = journey_http($base, '/api/onboarding.php?action=create_first_user', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => json_encode([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    $data = birkly_test_json_decode($create['body']);
    journey_assert('create_first_user HTTP 200', $create['code'] === 200, 'code ' . $create['code']);
    journey_assert('create_first_user status ok', ($data['status'] ?? '') === 'ok', $create['body']);
}

function journey_admin_login(string $base, string $cookieFile, string $email, string $password): void
{
    $loginGet = journey_http($base, '/admin/login.php', ['cookie_file' => $cookieFile]);
    preg_match('/name="_csrf"\s+value="([^"]+)"/', $loginGet['body'], $csrfMatch);
    $loginCsrf = $csrfMatch[1] ?? '';

    $loginPost = journey_http($base, '/admin/login.php', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body' => http_build_query([
            'email' => $email,
            'password' => $password,
            '_csrf' => $loginCsrf,
        ]),
        'cookie_file' => $cookieFile,
    ]);
    journey_assert('admin login POST ok', stripos($loginPost['body'], 'Invalid security token') === false);
}

function journey_onboarding_skip(string $base, string $cookieFile): void
{
    $skip = journey_http($base, '/api/onboarding.php?action=skip', [
        'method' => 'POST',
        'headers' => ['Content-Type: application/json'],
        'body' => '{}',
        'cookie_file' => $cookieFile,
    ]);
    $skipData = birkly_test_json_decode($skip['body']);
    journey_assert('onboarding skip ok', ($skipData['status'] ?? '') === 'ok', $skip['body']);
}

function journey_build_release_extract(): array
{
    $root = journey_repo_root();
    $zip = sys_get_temp_dir() . '/birkly-journey-release-' . getmypid() . '.zip';
    $extract = sys_get_temp_dir() . '/birkly-journey-extract-' . getmypid();

    $cmd = 'OUT=' . escapeshellarg($zip) . ' ' . escapeshellarg($root . '/scripts/build-cms-release.sh');
    exec($cmd, $out, $code);
    if ($code !== 0 || !is_file($zip)) {
        journey_fail('build-cms-release.sh failed: ' . implode("\n", $out));
    }

    if (is_dir($extract)) {
        journey_remove_tree($extract);
    }
    mkdir($extract, 0755, true);

    $zipArchive = new ZipArchive();
    if ($zipArchive->open($zip) !== true) {
        journey_fail('cannot open release zip');
    }
    $zipArchive->extractTo($extract);
    $zipArchive->close();

    return ['zip' => $zip, 'dir' => $extract];
}

function journey_remove_tree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            journey_remove_tree($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function journey_start_static_server(string $root, int $port): int
{
    $cmd = sprintf(
        'php -S 127.0.0.1:%d -t %s',
        $port,
        escapeshellarg($root)
    );
    $spec = [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
    $proc = proc_open($cmd, $spec, $pipes);
    if (!is_resource($proc)) {
        journey_fail('failed to start static server');
    }
    $status = proc_get_status($proc);
    $pid = (int) ($status['pid'] ?? 0);

    $ready = "http://127.0.0.1:$port/";
    for ($i = 0; $i < 40; $i++) {
        if (birkly_test_http_status_only($ready) > 0) {
            return $pid;
        }
        usleep(150000);
    }
    journey_stop_cms_server($pid);
    journey_fail("static server not ready on port $port");
}

function journey_make_plugin_zip(string $pluginId): string
{
    $src = journey_repo_root() . '/plugins/' . $pluginId;
    if (!is_dir($src) || !is_file($src . '/plugin.php')) {
        journey_fail("plugin source missing: $pluginId");
    }

    $zipPath = sys_get_temp_dir() . '/birkly-journey-' . $pluginId . '-' . getmypid() . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        journey_fail('cannot create plugin zip');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        $path = $file->getPathname();
        $rel = $pluginId . '/' . substr($path, strlen($src) + 1);
        $rel = str_replace('\\', '/', $rel);
        if ($file->isDir()) {
            $zip->addEmptyDir($rel);
        } else {
            $zip->addFile($path, $rel);
        }
    }
    $zip->close();

    return $zipPath;
}

function journey_enable_collection_public_api(string $collectionDir, bool $enable = true): ?string
{
    $path = journey_repo_root() . '/content/collections/' . $collectionDir . '/collection.json';
    if (!is_file($path)) {
        return null;
    }
    $backup = file_get_contents($path);
    $json = json_decode($backup, true);
    if (!is_array($json)) {
        return null;
    }
    if ($enable) {
        $json['entry_method'] = 'public_api';
        $json['default_public_status'] = 'published';
    } else {
        unset($json['entry_method'], $json['default_public_status']);
    }
    file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    return $backup;
}

function journey_restore_file(string $path, ?string $backup): void
{
    if ($backup === null) {
        return;
    }
    file_put_contents($path, $backup);
}

function journey_finish(): void
{
    if (journey_failures_count() > 0) {
        journey_fail(journey_failures_count() . ' assertion(s) failed');
    }
}
