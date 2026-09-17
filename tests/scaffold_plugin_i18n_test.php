<?php
/**
 * P85-G37 — scaffold-plugin.php emits lang/en.json and admin.i18n manifest.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/birkly-scaffold-i18n-' . bin2hex(random_bytes(4));
if (!mkdir($tmp, 0755, true) && !is_dir($tmp)) {
    fwrite(STDERR, "Failed to create temp dir\n");
    exit(1);
}

$cmd = sprintf(
    'php %s --id=p85-scaffold-test --title="P85 Scaffold Test" --out=%s 2>&1',
    escapeshellarg($root . '/scripts/scaffold-plugin.php'),
    escapeshellarg($tmp)
);
exec($cmd, $output, $code);
if ($code !== 0) {
    fwrite(STDERR, "scaffold failed: " . implode("\n", $output) . "\n");
    exit(1);
}

$pluginDir = $tmp . '/p85-scaffold-test';
$langFile = $pluginDir . '/lang/en.json';
$pluginJsonFile = $pluginDir . '/plugin.json';

$errors = [];
if (!is_file($langFile)) {
    $errors[] = 'lang/en.json not created';
}
if (!is_file($pluginJsonFile)) {
    $errors[] = 'plugin.json not created';
} else {
    $pluginJson = json_decode((string) file_get_contents($pluginJsonFile), true);
    $i18n = $pluginJson['admin']['i18n'] ?? null;
    if (!is_array($i18n) || ($i18n['path'] ?? '') !== 'lang' || ($i18n['namespace'] ?? '') !== 'plugins.p85_scaffold_test') {
        $errors[] = 'admin.i18n manifest missing or incorrect';
    }
}

// cleanup
$rm = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pluginDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($rm as $file) {
    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
}
rmdir($pluginDir);
rmdir($tmp);

if ($errors !== []) {
    fwrite(STDERR, implode('; ', $errors) . "\n");
    exit(1);
}

echo "scaffold_plugin_i18n_test: ok\n";
