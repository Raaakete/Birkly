<?php
/**
 * Private directories stay off HTTP. Public assets and the import cron URL stay available.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

require_once $root . '/core/project_site.php';
require_once $root . '/core/cli_only.php';
require_once $root . '/core/initial_admin.php';

function private_tree_fail(string $message, array &$errors): void
{
    $errors[] = $message;
}

$denied = [
    '/settings/security.json',
    '/Settings/hosting.json',
    '/users/abc.json',
    '/content/collections/blog/collection.json',
    '/data/plugins/commerce/encryption_key.txt',
    '/core/secret_box.php',
    '/cache/ssr/page.html',
    '/logs/php_errors.log',
    '/scripts/create-default-user.php',
    '/scripts/',
    '/tests/private_tree_http_test.php',
    '/project/.git/config',
    '/project/.GIT/HEAD',
];
foreach ($denied as $uri) {
    if (!birkly_is_private_tree_uri($uri) && !birkly_is_git_metadata_path($uri)) {
        private_tree_fail('must refuse ' . $uri, $errors);
    }
}

$allowed = [
    '/birkly-client.js',
    '/admin/js/plugins-page.js',
    '/api/auth.php',
    '/media/photo.jpg',
    '/project/css/style.css',
    '/project/content/style.css',
    '/scripts/commerce-import-sync.php',
    '/site/demo',
];
foreach ($allowed as $uri) {
    if (birkly_is_private_tree_uri($uri) || birkly_is_git_metadata_path($uri)) {
        private_tree_fail('must allow ' . $uri, $errors);
    }
}

if (!birkly_is_cli_sapi('cli') || !birkly_is_cli_sapi('phpdbg')) {
    private_tree_fail('cli and phpdbg are maintenance sapis', $errors);
}
if (birkly_is_cli_sapi('fpm-fcgi') || birkly_is_cli_sapi('apache2handler')) {
    private_tree_fail('web sapis must not count as the shell', $errors);
}

$htaccess = (string) file_get_contents($root . '/.htaccess');
$nginxExample = (string) file_get_contents($root . '/nginx.conf.example');
$nginxDocker = (string) file_get_contents($root . '/docker/nginx-site.conf');
foreach (['core', 'settings', 'users', 'content', 'data', 'scripts', 'tests'] as $name) {
    if (!str_contains($htaccess, $name) || !str_contains($nginxExample, $name) || !str_contains($nginxDocker, $name)) {
        private_tree_fail('every server config must name ' . $name, $errors);
    }
}
if (!str_contains($htaccess, 'commerce-import-sync.php')
    || !str_contains($nginxExample, 'commerce-import-sync.php')
    || !str_contains($nginxDocker, 'commerce-import-sync.php')) {
    private_tree_fail('the import cron URL must stay in every server config', $errors);
}

$createScript = (string) file_get_contents($root . '/scripts/create-default-user.php');
$resetScript = (string) file_get_contents($root . '/scripts/reset-admin-password.php');
if (!str_contains($createScript, 'birkly_require_cli') || !str_contains($resetScript, 'birkly_require_cli')) {
    private_tree_fail('password scripts must refuse HTTP', $errors);
}
if (preg_match("/password\\s*=\\s*'password'/", $createScript) === 1) {
    private_tree_fail('the first-admin script must not ship a shared password', $errors);
}

function private_tree_http(string $url): array
{
    $context = stream_context_create([
        'http' => ['ignore_errors' => true, 'timeout' => 3],
    ]);
    $body = file_get_contents($url, false, $context);
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#HTTP/\\S+\\s+(\\d+)#', $line, $match) === 1) {
            $status = (int) $match[1];
        }
    }

    return [$status, is_string($body) ? $body : ''];
}

$temp = sys_get_temp_dir() . '/birkly-initial-admin-' . bin2hex(random_bytes(4));
mkdir($temp, 0755, true);
$GLOBALS['__birkly_project_root'] = $temp;
$first = birkly_create_initial_admin('admin@localhost', 'InitialAdmin123!');
if (($first['status'] ?? '') !== 'created') {
    private_tree_fail('first admin must be created on an empty install: ' . json_encode($first), $errors);
}
$second = birkly_create_initial_admin('other@example.com', 'InitialAdmin123!');
if (($second['status'] ?? '') !== 'exists') {
    private_tree_fail('a second known account must not be added: ' . json_encode($second), $errors);
}
$files = glob($temp . '/users/*.json') ?: [];
if (count($files) !== 1) {
    private_tree_fail('exactly one account file should exist', $errors);
}
unset($GLOBALS['__birkly_project_root']);
foreach ($files as $file) {
    @unlink($file);
}
@rmdir($temp . '/users');
@rmdir($temp);

$bridge = (string) file_get_contents($root . '/mcp-bridge.js');
if (str_contains($bridge, "searchParams.set('api_key'")) {
    private_tree_fail('the MCP bridge must not put the API key in the URL', $errors);
}
if (!str_contains($bridge, 'X-MCP-API-Key')) {
    private_tree_fail('the MCP bridge must send the API key header', $errors);
}

$port = 8766;
$server = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root, $root . '/server-router.php'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    $root
);
if (!is_resource($server)) {
    private_tree_fail('could not start the built-in server', $errors);
} else {
    $ready = false;
    for ($i = 0; $i < 20; $i++) {
        [$probeStatus, $probeBody] = private_tree_http('http://127.0.0.1:' . $port . '/birkly-client.js');
        if ($probeStatus === 200 && str_starts_with($probeBody, '/**')) {
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) {
        private_tree_fail('built-in server did not serve birkly-client.js', $errors);
    } else {
        [$settingsStatus, $settingsBody] = private_tree_http('http://127.0.0.1:' . $port . '/settings/hosting.json');
        if ($settingsStatus !== 403 || $settingsBody !== "Forbidden\n") {
            private_tree_fail('settings JSON must be forbidden on the built-in server: ' . $settingsStatus . ' ' . $settingsBody, $errors);
        }
        [$scriptStatus, $scriptBody] = private_tree_http('http://127.0.0.1:' . $port . '/scripts/create-default-user.php');
        if ($scriptStatus !== 403 || $scriptBody !== "Forbidden\n") {
            private_tree_fail('create-default-user.php must be forbidden over HTTP: ' . $scriptStatus . ' ' . $scriptBody, $errors);
        }
        [, $cronBody] = private_tree_http('http://127.0.0.1:' . $port . '/scripts/commerce-import-sync.php');
        if ($cronBody === "Forbidden\n") {
            private_tree_fail('the import cron URL must still reach its script', $errors);
        }
    }
    proc_terminate($server);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($server);
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "private_tree_http_test ok\n";
