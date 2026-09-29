<?php
/**
 * P102-M9 — HTTP scheduled publish requires a settings token.
 * CLI detection does not treat loopback as authorized.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/scheduled_publish.php';

$errors = [];

function p102_assert(bool $ok, string $message, array &$errors): void
{
    if (!$ok) {
        $errors[] = $message;
    }
}

function p102_rmtree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

$tmp = sys_get_temp_dir() . '/birkly-p102-cron-' . bin2hex(random_bytes(4));
$settings = $tmp . '/settings';
$collections = $tmp . '/collections';
$previousGet = $_GET;
$previousRequest = $_SERVER['REQUEST_METHOD'] ?? null;
$previousHost = $_SERVER['HTTP_HOST'] ?? null;
$previousAddr = $_SERVER['REMOTE_ADDR'] ?? null;

try {
    mkdir($settings, 0755, true);
    $GLOBALS['__birkly_scheduled_publish_settings_dir'] = $settings;

    p102_assert(birkly_scheduled_publish_is_http_request() === false, 'a CLI run without an HTTP host should not require a token', $errors);

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    unset($_GET['token'], $_SERVER['HTTP_X_BIRKLY_CRON_TOKEN']);
    p102_assert(birkly_scheduled_publish_is_http_request() === true, 'a web request should be treated as HTTP', $errors);
    p102_assert(birkly_scheduled_publish_http_authorized() === false, 'loopback without a token must be rejected', $errors);
    $generated = birkly_scheduled_publish_read_token();
    p102_assert($generated !== '' && strlen($generated) === 64, 'a rejected HTTP call should still leave a generated token in settings', $errors);

    $_GET['token'] = 'not-the-token';
    p102_assert(birkly_scheduled_publish_http_authorized() === false, 'a wrong token must be rejected', $errors);

    $_GET['token'] = $generated;
    p102_assert(birkly_scheduled_publish_http_authorized() === true, 'the settings token should authorize HTTP', $errors);
    p102_assert(birkly_scheduled_publish_read_token() === $generated, 'authorizing should not rotate the token', $errors);

    unset($_GET['token']);
    $_SERVER['HTTP_X_BIRKLY_CRON_TOKEN'] = $generated;
    p102_assert(birkly_scheduled_publish_http_authorized() === true, 'the cron header should authorize HTTP', $errors);

    $adminUrl = birkly_scheduled_publish_admin_url();
    p102_assert(str_contains($adminUrl, 'process_scheduled.php?token=' . $generated), 'admin URL should include the token', $errors);

    $entryDir = $collections . '/news/entries';
    mkdir($entryDir, 0755, true);
    $entryPath = $entryDir . '/hello.json';
    file_put_contents($entryPath, json_encode([
        'status' => 'scheduled',
        'published_at' => '2000-01-01 00:00:00',
        'title' => 'Hello',
    ], JSON_THROW_ON_ERROR));
    $futureDir = $collections . '/news/entries';
    file_put_contents($futureDir . '/later.json', json_encode([
        'status' => 'scheduled',
        'published_at' => '2999-01-01 00:00:00',
        'title' => 'Later',
    ], JSON_THROW_ON_ERROR));

    $result = birkly_process_scheduled_entries($collections);
    p102_assert(($result['status'] ?? '') === 'ok' && ($result['published'] ?? 0) === 1, 'a due scheduled entry should still publish: ' . json_encode($result), $errors);
    $published = json_decode((string) file_get_contents($entryPath), true);
    p102_assert(is_array($published) && ($published['status'] ?? '') === 'published', 'the due entry should be published', $errors);
    $later = json_decode((string) file_get_contents($futureDir . '/later.json'), true);
    p102_assert(is_array($later) && ($later['status'] ?? '') === 'scheduled', 'a future entry should stay scheduled', $errors);

    $script = (string) file_get_contents($root . '/api/process_scheduled.php');
    $gate = strpos($script, 'birkly_scheduled_publish_http_authorized');
    $run = strpos($script, 'birkly_process_scheduled_entries');
    p102_assert($gate !== false && $run !== false && $gate < $run, 'HTTP token check should run before entries are processed', $errors);
    p102_assert(!str_contains($script, '127.0.0.1'), 'the endpoint should not exempt loopback', $errors);
    $settingsHtml = (string) file_get_contents($root . '/admin/templates/settings.html');
    p102_assert(str_contains($settingsHtml, 'birkly_scheduled_publish_admin_url'), 'admin settings should show the scheduled publish URL', $errors);
} catch (Throwable $e) {
    $errors[] = $e::class . ': ' . $e->getMessage();
} finally {
    $_GET = $previousGet;
    if ($previousRequest === null) {
        unset($_SERVER['REQUEST_METHOD']);
    } else {
        $_SERVER['REQUEST_METHOD'] = $previousRequest;
    }
    if ($previousHost === null) {
        unset($_SERVER['HTTP_HOST']);
    } else {
        $_SERVER['HTTP_HOST'] = $previousHost;
    }
    if ($previousAddr === null) {
        unset($_SERVER['REMOTE_ADDR']);
    } else {
        $_SERVER['REMOTE_ADDR'] = $previousAddr;
    }
    unset($_SERVER['HTTP_X_BIRKLY_CRON_TOKEN'], $GLOBALS['__birkly_scheduled_publish_settings_dir']);
    p102_rmtree($tmp);
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "p102_scheduled_publish_token_test: OK\n";
