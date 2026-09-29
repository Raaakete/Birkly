<?php
/**
 * P102-M3 — legacy fetch-metadata.php must not fetch a URL.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$legacy = (string) file_get_contents($root . '/api/fetch-metadata.php');
$editor = (string) file_get_contents($root . '/fieldtypes/ContentEditor/link-metadata.php');

if (str_contains($legacy, 'file_get_contents') || str_contains($legacy, 'curl_')) {
    $errors[] = 'fetch-metadata.php must not fetch';
}
if (!str_contains($editor, "requireAuth('read')") || !str_contains($editor, 'birkly_safe_http_fetch')) {
    $errors[] = 'link-metadata.php must keep its auth check and safe fetch';
}

$script = tempnam(sys_get_temp_dir(), 'p102meta');
if ($script === false) {
    $errors[] = 'temp script failed';
} else {
    $php = $script . '.php';
    rename($script, $php);
    $rootExport = var_export($root, true);
    file_put_contents($php, <<<PHP
<?php
\$_GET = ['url' => 'http://127.0.0.1/'];
\$_SERVER['REQUEST_METHOD'] = 'GET';
require {$rootExport} . '/api/fetch-metadata.php';
PHP);
    $output = [];
    $code = 0;
    exec(PHP_BINARY . ' ' . escapeshellarg($php) . ' 2>&1', $output, $code);
    @unlink($php);
    $body = implode("\n", $output);
    $json = json_decode($body, true);
    if (!is_array($json) || ($json['success'] ?? true) !== false || !str_contains((string) ($json['error'] ?? ''), 'retired')) {
        $errors[] = 'retired endpoint should refuse without fetching: ' . $body;
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "p102_fetch_metadata_retired_test ok\n";
