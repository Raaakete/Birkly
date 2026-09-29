<?php
/**
 * P102-M9 — fieldtype APIs require an admin session and do not print traces.
 * A logged-in session can still list fieldtypes. Includes keep working without a session.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

function p102_ft_fail(string $message, array &$errors): void
{
    $errors[] = $message;
}

function p102_ft_run(string $script, string $prepend): string
{
    $cmd = [PHP_BINARY];
    if ($prepend !== '') {
        $cmd[] = '-d';
        $cmd[] = 'auto_prepend_file=' . $prepend;
    }
    $cmd[] = $script;
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($cmd, $descriptors, $pipes, dirname($script));
    if (!is_resource($process)) {
        throw new RuntimeException('proc_open failed');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    proc_close($process);
    $out = is_string($stdout) ? $stdout : '';
    if (is_string($stderr) && trim($stderr) !== '') {
        $out .= "\n" . $stderr;
    }
    return $out;
}

$prependAnon = tempnam(sys_get_temp_dir(), 'p102ft');
$prependUser = tempnam(sys_get_temp_dir(), 'p102ft');
if ($prependAnon === false || $prependUser === false) {
    fwrite(STDERR, "temp prepend failed\n");
    exit(1);
}
$anonFile = $prependAnon . '.php';
$userFile = $prependUser . '.php';
rename($prependAnon, $anonFile);
rename($prependUser, $userFile);

file_put_contents($anonFile, <<<'PHP'
<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'get_all';
PHP);
file_put_contents($userFile, <<<'PHP'
<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'get_all';
require_once __DIR__ . '/api/session.php';
PHP);
// The prepend file is not inside the repo, so point session at an absolute path.
file_put_contents($userFile, <<<PHP
<?php
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET['action'] = 'list';
require_once '{$root}/api/session.php';
haru_secure_session_start();
\$_SESSION['user'] = [
    'id' => 'p102-editor',
    'role' => 'editor',
    'permissions' => ['read', 'write', 'manage_content'],
];
\$_SESSION['last_activity'] = time();
PHP);
file_put_contents($anonFile, <<<PHP
<?php
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_GET['action'] = 'get_all';
PHP);

try {
    $legacy = (string) file_get_contents($root . '/api/fieldtypes.php');
    if (str_contains($legacy, 'getTraceAsString') || str_contains($legacy, "ini_set('display_errors', 1)") || str_contains($legacy, 'ini_set("display_errors", 1)')) {
        p102_ft_fail('fieldtypes.php must not print traces', $errors);
    }
    if (!str_contains($legacy, "ini_set('display_errors', '0')") || !str_contains($legacy, 'requireAuth()')) {
        p102_ft_fail('fieldtypes.php must hide errors and require a session', $errors);
    }

    $anon = p102_ft_run($root . '/api/fieldtypes.php', $anonFile);
    if (!str_contains($anon, 'Unauthorized') || str_contains($anon, 'Trace') || str_contains($anon, 'Fatal error')) {
        p102_ft_fail('anonymous fieldtypes.php must be unauthorized without a trace: ' . $anon, $errors);
    }

    $userPrepend = $userFile . '.legacy.php';
    file_put_contents($userPrepend, str_replace("\$_GET['action'] = 'list';", "\$_GET['action'] = 'get_all';", (string) file_get_contents($userFile)));
    $listed = p102_ft_run($root . '/api/fieldtypes.php', $userPrepend);
    $listedJson = json_decode($listed, true);
    $rows = [];
    if (is_array($listedJson['data']['fieldtypes'] ?? null)) {
        $rows = $listedJson['data']['fieldtypes'];
    }
    $names = array_column($rows, 'name');
    if (!is_array($listedJson) || ($listedJson['status'] ?? '') !== 'ok' || !in_array('text', $names, true)) {
        p102_ft_fail('signed-in fieldtypes.php should still list the text fieldtype: ' . $listed, $errors);
    }

    $adminAnon = p102_ft_run($root . '/api/admin_fieldtypes.php', $anonFile);
    if (!str_contains($adminAnon, 'Unauthorized')) {
        p102_ft_fail('anonymous admin fieldtypes list must be unauthorized: ' . $adminAnon, $errors);
    }

    $adminListed = p102_ft_run($root . '/api/admin_fieldtypes.php', $userFile);
    $adminJson = json_decode($adminListed, true);
    $adminNames = [];
    if (is_array($adminJson['data'] ?? null)) {
        foreach ($adminJson['data'] as $row) {
            if (is_array($row) && isset($row['name'])) {
                $adminNames[] = $row['name'];
            } elseif (is_array($row) && isset($row['type'])) {
                $adminNames[] = $row['type'];
            }
        }
    }
    if (!is_array($adminJson) || ($adminJson['status'] ?? '') !== 'ok' || $adminNames === []) {
        p102_ft_fail('signed-in admin fieldtypes should still list fieldtypes: ' . $adminListed, $errors);
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
} finally {
    @unlink($anonFile);
    @unlink($userFile);
    if (isset($userPrepend)) {
        @unlink($userPrepend);
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "p102_fieldtypes_session_test ok\n";
