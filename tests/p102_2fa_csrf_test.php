<?php
/**
 * P102-M9 — 2FA enable reads the JSON _csrf the account screen already sends.
 * The QR step stays a GET and does not require that field.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$usersDir = sys_get_temp_dir() . '/birkly-p102-2fa-' . bin2hex(random_bytes(4));
mkdir($usersDir, 0755, true);
$email = 'p102-2fa@example.com';
$userFile = $usersDir . '/' . md5(strtolower($email)) . '.json';
file_put_contents($userFile, json_encode([
    'email' => $email,
    'role' => 'admin',
], JSON_PRETTY_PRINT));

function p102_2fa_fail(string $message, array &$errors): void
{
    $errors[] = $message;
}

function p102_2fa_run(string $root, string $usersDir, string $method, string $action, string $body, bool $withSetup): string
{
    $runner = tempnam(sys_get_temp_dir(), 'p1022fa');
    if ($runner === false) {
        throw new RuntimeException('temp runner failed');
    }
    $script = $runner . '.php';
    rename($runner, $script);
    $rootExport = var_export($root, true);
    $usersExport = var_export($usersDir, true);
    $methodExport = var_export($method, true);
    $actionExport = var_export($action, true);
    $bodyExport = var_export($body, true);
    $setupExport = $withSetup ? 'true' : 'false';
    file_put_contents($script, <<<PHP
<?php
\$root = {$rootExport};
\$GLOBALS['__birkly_2fa_users_dir'] = {$usersExport};
\$GLOBALS['__birkly_2fa_test_raw_body'] = {$bodyExport};
\$_SERVER['REQUEST_METHOD'] = {$methodExport};
\$_GET = ['action' => {$actionExport}];
\$_POST = [];
require_once \$root . '/api/session.php';
haru_secure_session_start();
\$_SESSION['user'] = ['email' => 'p102-2fa@example.com', 'role' => 'admin', 'permissions' => ['*']];
\$_SESSION['csrf_token'] = 'p102-csrf';
\$_SESSION['last_activity'] = time();
if ({$setupExport}) {
    \$_SESSION['2fa_setup'] = [
        'secret' => 'JBSWY3DPEHPK3PXP',
        'backup_codes' => ['AAAA-BBBB'],
        'timestamp' => time(),
    ];
}
require \$root . '/api/2fa.php';
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

try {
    $js = (string) file_get_contents($root . '/admin/js/account-settings.js');
    if (!str_contains($js, "JSON.stringify({ code, _csrf: getCsrfToken() })")) {
        p102_2fa_fail('account screen enable call must send JSON _csrf', $errors);
    }

    $GLOBALS['__HARU_INTERNAL_CALL'] = true;
    require_once $root . '/api/2fa.php';
    unset($GLOBALS['__HARU_INTERNAL_CALL']);
    $code = generateTOTP('JBSWY3DPEHPK3PXP', time());

    $status = p102_2fa_run($root, $usersDir, 'GET', 'status', '', false);
    $statusJson = json_decode($status, true);
    if (!is_array($statusJson) || ($statusJson['status'] ?? '') !== 'ok' || ($statusJson['enabled'] ?? true) !== false) {
        p102_2fa_fail('status GET should still load without a CSRF field: ' . $status, $errors);
    }

    $qr = p102_2fa_run($root, $usersDir, 'GET', 'qr_code', '', true);
    $qrJson = json_decode($qr, true);
    if (!is_array($qrJson) || ($qrJson['status'] ?? '') !== 'ok' || !str_contains((string) ($qrJson['qr_url'] ?? ''), 'otpauth://totp/')) {
        p102_2fa_fail('QR GET should still load without a CSRF field: ' . $qr, $errors);
    }

    $before = (string) file_get_contents($userFile);
    $denied = p102_2fa_run($root, $usersDir, 'POST', 'enable', json_encode(['code' => $code], JSON_THROW_ON_ERROR), true);
    if (!str_contains($denied, 'Invalid CSRF token') || file_get_contents($userFile) !== $before) {
        p102_2fa_fail('enable without _csrf must be rejected and must not write: ' . $denied, $errors);
    }

    $enabled = p102_2fa_run($root, $usersDir, 'POST', 'enable', json_encode([
        'code' => $code,
        '_csrf' => 'p102-csrf',
    ], JSON_THROW_ON_ERROR), true);
    $enabledJson = json_decode($enabled, true);
    $stored = json_decode((string) file_get_contents($userFile), true);
    if (!is_array($enabledJson) || ($enabledJson['status'] ?? '') !== 'ok' || ($stored['2fa']['enabled'] ?? false) !== true) {
        p102_2fa_fail('enable with the account screen _csrf should turn 2FA on: ' . $enabled, $errors);
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
} finally {
    if (is_file($userFile)) {
        unlink($userFile);
    }
    @rmdir($usersDir);
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "p102_2fa_csrf_test ok\n";
