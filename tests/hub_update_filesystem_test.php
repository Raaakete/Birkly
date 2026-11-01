<?php
/**
 * Hub update filesystem detection (Docker vs zip hosting).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/hub_update_filesystem.php';

$failures = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

$tempBase = dirname(__DIR__) . '/temp';
if (!is_dir($tempBase)) {
    mkdir($tempBase, 0777, true);
}
$root = $tempBase . '/hub-fs-' . uniqid();
mkdir($root, 0700, true);
mkdir($root . '/core', 0555, true);
foreach (['admin', 'api', 'fieldtypes'] as $programDir) {
    mkdir($root . '/' . $programDir, 0555, true);
}
file_put_contents($root . '/BUILD_REVISION', 'test');

try {
    assert_true('detects docker deployment', birkly_is_docker_deployment($root) === true);
    assert_true('core not writable in fixture', birkly_hub_update_program_dirs_writable($root) === false);
    assert_true(
        'install mode is docker_redeploy',
        birkly_hub_update_install_mode($root) === 'docker_redeploy',
    );
    $msg = birkly_hub_update_preflight_error($root);
    assert_true(
        'preflight mentions Docker redeploy',
        is_string($msg) && str_contains($msg, 'Docker'),
        'got: ' . var_export($msg, true),
    );
} finally {
    foreach (['core', 'admin', 'api', 'fieldtypes'] as $programDir) {
        if (is_dir($root . '/' . $programDir)) {
            chmod($root . '/' . $programDir, 0755);
            rmdir($root . '/' . $programDir);
        }
    }
    if (is_file($root . '/BUILD_REVISION')) {
        unlink($root . '/BUILD_REVISION');
    }
    if (is_dir($root)) {
        rmdir($root);
    }
}

if ($failures > 0) {
    echo "\nhub_update_filesystem_test: FAIL ($failures)\n";
    exit(1);
}

echo "\nhub_update_filesystem_test: PASS\n";
exit(0);
