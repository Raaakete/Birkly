<?php
/**
 * P103 — path containment, checkout hosts, and password-reset argv.
 * Normal ids, hostnames, Stripe, the Hub origin, and BIRKLY_RESET_PASSWORD still work.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

function p103_fail(string $message, array &$errors): void
{
    $errors[] = $message;
}

require_once $root . '/core/birkly_paths.php';
require_once $root . '/core/analytics_config.php';
require_once $root . '/core/community_user_files.php';

$client = realpath($root . '/birkly-client.js');
if ($client === false || !birkly_resolved_path_is_inside_root($client, $root)) {
    p103_fail('birkly-client.js must still resolve inside the CMS root', $errors);
}
if (birkly_resolved_path_is_inside_root($root . '-other/secret.txt', $root)) {
    p103_fail('a sibling directory that shares the root prefix must not count as inside', $errors);
}
if (birkly_resolved_path_is_inside_root($root, $root)) {
    p103_fail('the root directory itself is not a file inside the root', $errors);
}

$indexProbe = tempnam(sys_get_temp_dir(), 'p103idx');
if ($indexProbe === false) {
    p103_fail('temp index probe failed', $errors);
} else {
    $indexProbeFile = $indexProbe . '.php';
    rename($indexProbe, $indexProbeFile);
    file_put_contents($indexProbeFile, <<<'PHP'
<?php
$uri = $argv[1] ?? '/';
$_SERVER['REQUEST_URI'] = $uri;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
require $argv[2];
PHP);
    $clientOut = [];
    $clientCode = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($indexProbeFile) . ' ' . escapeshellarg('/birkly-client.js') . ' ' . escapeshellarg($root . '/index.php') . ' 2>&1', $clientOut, $clientCode);
    $clientBody = implode("\n", $clientOut);
    if ($clientCode !== 0 || !str_starts_with($clientBody, '/**')) {
        p103_fail('index.php must still serve birkly-client.js from the CMS root', $errors);
    }
    $travelOut = [];
    $travelCode = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($indexProbeFile) . ' ' . escapeshellarg('/../Birkly-Docs/README.md') . ' ' . escapeshellarg($root . '/index.php') . ' 2>&1', $travelOut, $travelCode);
    if ($travelCode !== 0 || trim(implode("\n", $travelOut)) !== 'Not found') {
        p103_fail('index.php must not serve a path that leaves the CMS root: ' . implode("\n", $travelOut), $errors);
    }
    @unlink($indexProbeFile);
}

if (birkly_analytics_export_directory('.') !== null || birkly_analytics_export_directory('../settings') !== null) {
    p103_fail('analytics export must reject . and ..', $errors);
}
if (birkly_analytics_export_directory('example.com/../../etc') !== null) {
    p103_fail('analytics export must reject slashes', $errors);
}
if (birkly_analytics_export_directory('all') !== null) {
    p103_fail('analytics export must reject the all scope', $errors);
}
$hostDir = birkly_analytics_export_directory('P103.Example.com');
if ($hostDir === null || !str_ends_with(str_replace('\\', '/', $hostDir), '/data/p103.example.com')) {
    p103_fail('a hostname tracking id must still map under data/analytics/data: ' . var_export($hostDir, true), $errors);
}

$fixtureHost = 'p103-export.example';
$fixtureDir = $root . '/data/analytics/data/' . $fixtureHost;
$fixtureFile = $fixtureDir . '/2026-09-29.json';
$madeDir = !is_dir($fixtureDir);
if (!is_dir($fixtureDir) && !mkdir($fixtureDir, 0755, true) && !is_dir($fixtureDir)) {
    p103_fail('could not create analytics fixture', $errors);
} else {
    file_put_contents($fixtureFile, json_encode([
        ['timestamp' => '2026-09-29T12:00:00Z', 'event_type' => 'page_view', 'path' => '/p103'],
    ]));
    $exportDir = birkly_analytics_export_directory($fixtureHost);
    $readFile = ($exportDir ?? '') . '/2026-09-29.json';
    $raw = is_file($readFile) ? (string) file_get_contents($readFile) : '';
    if (!str_contains($raw, '/p103')) {
        p103_fail('hostname export directory must still read that host’s JSON', $errors);
    }
    @unlink($fixtureFile);
    if ($madeDir) {
        @rmdir($fixtureDir);
    }
}

$js = (string) file_get_contents($root . '/admin/js/plugins-page.js');
if (!preg_match('/\/\* p103-checkout-redirect-begin \*\/(.*?)\/\* p103-checkout-redirect-end \*\//s', $js, $checkoutMatch)) {
    p103_fail('checkout redirect helper markers missing', $errors);
} else {
    $node = <<<JS
{$checkoutMatch[1]}
const cases = [
  [birklyCheckoutRedirectAllowed('https://checkout.stripe.com/c/pay/cs_test', 'https://marketplace.birkly.cloud'), true, 'stripe'],
  [birklyCheckoutRedirectAllowed('http://localhost:8090/buy/complete?session_id=1&mock=1', 'http://localhost:8090'), true, 'mock hub'],
  [birklyCheckoutRedirectAllowed('https://marketplace.birkly.cloud/buy/demo', 'https://marketplace.birkly.cloud'), true, 'hub https'],
  [birklyCheckoutRedirectAllowed('https://evil.example/phish', 'https://marketplace.birkly.cloud'), false, 'other https'],
  [birklyCheckoutRedirectAllowed('javascript:alert(1)', 'https://marketplace.birkly.cloud'), false, 'javascript'],
  [birklyCheckoutRedirectAllowed('http://checkout.stripe.com/c/pay/cs_test', 'http://localhost:8090'), false, 'stripe http'],
];
let failed = [];
for (const [ok, expect, name] of cases) {
  if (ok !== expect) failed.push(name);
}
if (failed.length) {
  console.error(failed.join(','));
  process.exit(1);
}
JS;
    $nodeFile = tempnam(sys_get_temp_dir(), 'p103js');
    if ($nodeFile === false) {
        p103_fail('temp js file failed', $errors);
    } else {
        file_put_contents($nodeFile, $node);
        $nodeOut = [];
        $nodeCode = 0;
        exec('node ' . escapeshellarg($nodeFile) . ' 2>&1', $nodeOut, $nodeCode);
        @unlink($nodeFile);
        if ($nodeCode !== 0) {
            p103_fail('checkout host check failed: ' . implode("\n", $nodeOut), $errors);
        }
    }
}

function p103_run(string $script, string $prepend, string $stdin = ''): array
{
    $cmd = [PHP_BINARY, '-d', 'auto_prepend_file=' . $prepend, $script];
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($cmd, $descriptors, $pipes, dirname($script));
    if (!is_resource($process)) {
        return [1, 'proc_open failed'];
    }
    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $stdout = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return [$code, $stdout . $stderr];
}

$prepend = tempnam(sys_get_temp_dir(), 'p103pre');
if ($prepend === false) {
    p103_fail('temp prepend failed', $errors);
} else {
    $prependFile = $prepend . '.php';
    rename($prepend, $prependFile);
    file_put_contents($prependFile, <<<PHP
<?php
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET['action'] = 'get_config';
\$_GET['fieldtype'] = 'text';
require_once '{$root}/api/session.php';
haru_secure_session_start();
\$_SESSION['user'] = ['id' => 'p103', 'role' => 'admin', 'permissions' => ['*']];
\$_SESSION['last_activity'] = time();
PHP);
    [, $configOut] = p103_run($root . '/api/fieldtypes.php', $prependFile);
    $configJson = json_decode($configOut, true);
    if (!is_array($configJson) || ($configJson['status'] ?? '') !== 'ok' || ($configJson['data']['name'] ?? '') === '') {
        p103_fail('signed-in fieldtype config for text must still succeed: ' . $configOut, $errors);
    }
    foreach (['URL', 'OEmbed'] as $keptName) {
        file_put_contents($prependFile, <<<PHP
<?php
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET['action'] = 'get_config';
\$_GET['fieldtype'] = '{$keptName}';
require_once '{$root}/api/session.php';
haru_secure_session_start();
\$_SESSION['user'] = ['id' => 'p103', 'role' => 'admin', 'permissions' => ['*']];
\$_SESSION['last_activity'] = time();
PHP);
        [, $keptOut] = p103_run($root . '/api/fieldtypes.php', $prependFile);
        $keptJson = json_decode($keptOut, true);
        $keptMessage = is_array($keptJson) ? (string) ($keptJson['message'] ?? '') : '';
        $keptOk = is_array($keptJson) && (($keptJson['status'] ?? '') === 'ok' || $keptMessage === 'Invalid fieldtype definition');
        if (!$keptOk) {
            p103_fail('fieldtype ' . $keptName . ' must still resolve: ' . $keptOut, $errors);
        }
    }

    file_put_contents($prependFile, <<<PHP
<?php
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET['action'] = 'get_config';
\$_GET['fieldtype'] = '../Text';
require_once '{$root}/api/session.php';
haru_secure_session_start();
\$_SESSION['user'] = ['id' => 'p103', 'role' => 'admin', 'permissions' => ['*']];
\$_SESSION['last_activity'] = time();
PHP);
    [, $badField] = p103_run($root . '/api/fieldtypes.php', $prependFile);
    $badFieldJson = json_decode($badField, true);
    if (!is_array($badFieldJson) || ($badFieldJson['status'] ?? '') !== 'error') {
        p103_fail('fieldtype ../ must be rejected: ' . $badField, $errors);
    }

    $collection = $root . '/plugins/community_users/collections/community_users';
    $existed = is_dir($collection);
    if (!$existed && !mkdir($collection, 0755, true) && !is_dir($collection)) {
        p103_fail('could not create community users fixture', $errors);
    } else {
        $entries = $collection . '/entries';
        if (!is_dir($entries)) {
            mkdir($entries, 0755, true);
        }
        $createdId = 'p103user1';
        $direct = community_users_entry_file($entries, $createdId);
        if ($direct === null || community_users_entry_file($entries, '../config') !== null) {
            p103_fail('community user path helper must accept a uniqid-shaped id and reject ..', $errors);
        }
        file_put_contents($entries . '/' . $createdId . '.json', json_encode([
            'id' => $createdId,
            'username' => 'p103user',
            'email' => 'p103@example.com',
            'password_hash' => 'hidden',
        ]));
        if (is_file($direct) && dirname(realpath($direct) ?: '') !== realpath($entries)) {
            p103_fail('community user file must stay inside the entries directory', $errors);
        } else {
            file_put_contents($prependFile, <<<PHP
<?php
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET['action'] = 'get';
\$_GET['id'] = '{$createdId}';
require_once '{$root}/api/session.php';
haru_secure_session_start();
\$_SESSION['user'] = ['id' => 'p103', 'role' => 'admin', 'permissions' => ['manage_users']];
\$_SESSION['last_activity'] = time();
PHP);
            [, $got] = p103_run($root . '/api/community_users_admin.php', $prependFile);
            $gotJson = json_decode($got, true);
            if (!is_array($gotJson) || ($gotJson['data']['user']['email'] ?? '') !== 'p103@example.com') {
                p103_fail('community user get must still return the created user: ' . $got, $errors);
            }
            if (isset($gotJson['data']['user']['password_hash'])) {
                p103_fail('community user get must still hide the password hash', $errors);
            }

            file_put_contents($prependFile, <<<PHP
<?php
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET['action'] = 'get';
\$_GET['id'] = '../config';
require_once '{$root}/api/session.php';
haru_secure_session_start();
\$_SESSION['user'] = ['id' => 'p103', 'role' => 'admin', 'permissions' => ['manage_users']];
\$_SESSION['last_activity'] = time();
PHP);
            [, $traversal] = p103_run($root . '/api/community_users_admin.php', $prependFile);
            $traversalJson = json_decode($traversal, true);
            if (!is_array($traversalJson) || ($traversalJson['message'] ?? '') !== 'Invalid user id') {
                p103_fail('community user ../ must be rejected: ' . $traversal, $errors);
            }
        }
        foreach (glob($collection . '/entries/*.json') ?: [] as $file) {
            if (str_contains((string) file_get_contents($file), 'p103@example.com')) {
                @unlink($file);
            }
        }
        @rmdir($collection . '/entries');
        if (!$existed) {
            @rmdir($collection);
            @rmdir(dirname($collection));
            @rmdir(dirname($collection, 2));
        }
    }
    @unlink($prependFile);
}

$passwordScript = $root . '/scripts/reset-admin-password.php';
$reject = [];
$rejectCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($passwordScript) . ' --email=p103@example.com --password=Secret123! 2>&1', $reject, $rejectCode);
$rejectText = implode("\n", $reject);
if ($rejectCode === 0 || !str_contains($rejectText, 'BIRKLY_RESET_PASSWORD') || str_contains($rejectText, 'Password updated')) {
    p103_fail('reset script must reject --password without changing a password: ' . $rejectText, $errors);
}
$env = [];
$envCode = 0;
exec('BIRKLY_RESET_PASSWORD=' . escapeshellarg('NewPassword123!') . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($passwordScript) . ' --email=p103-missing@example.com 2>&1', $env, $envCode);
$envText = implode("\n", $env);
if ($envCode === 0 || !str_contains($envText, 'No account found') || str_contains($envText, 'Do not pass --password')) {
    p103_fail('env password must still reach account lookup: ' . $envText, $errors);
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "p103_aikido_fixes_test ok\n";
