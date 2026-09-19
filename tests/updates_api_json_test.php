<?php
/**
 * Updates API must always return valid JSON (invalid UTF-8 in release notes must not blank the response).
 */

require_once __DIR__ . '/../core/api_response.php';
require_once __DIR__ . '/../core/version.php';
require_once __DIR__ . '/../core/platform_ops.php';

$errors = [];

$GLOBALS['birkly_ops_check_updates_override'] = static function (string $currentVersion): array {
    return [
        'ok' => true,
        'update_available' => true,
        'latest_version' => '9.9.9',
        'current_version' => $currentVersion,
        'release_notes' => "\xFF\xFE not valid utf-8",
        'download_url' => 'https://example.test/birkly.zip',
        'checksum_sha256' => str_repeat('a', 64),
    ];
};

$status = BirklyVersion::getUpdateStatus();
if (($status['status'] ?? '') !== 'ok') {
    $errors[] = 'getUpdateStatus should return status ok';
}

$payload = ['status' => 'ok', 'data' => $status];
ob_start();
api_json_echo($payload);
$body = ob_get_clean();

if ($body === '' || $body === false) {
    $errors[] = 'api_json_echo produced an empty body';
}

$decoded = json_decode($body, true);
if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'ok') {
    $errors[] = 'updates check payload is not valid JSON';
}

if (!isset($decoded['data']['current_version']) || $decoded['data']['current_version'] === '') {
    $errors[] = 'current_version should be present in encoded payload';
}

unset($GLOBALS['birkly_ops_check_updates_override']);

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "updates_api_json_test: PASS\n";
exit(0);
