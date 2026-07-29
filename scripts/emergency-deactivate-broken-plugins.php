<?php
/**
 * Boot-time safety net: deactivate plugins whose plugin.php fails a syntax check.
 * Does not load plugins.php or execute plugin code.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/birkly_paths.php';
require_once $root . '/core/birkly_data_paths.php';
require_once $root . '/api/storage.php';

$settingsDir = birkly_data_root() !== null
    ? birkly_data_root() . '/settings'
    : $root . '/settings';
$pluginsDir = $root . '/plugins';

if (!is_dir($pluginsDir) || !is_dir($settingsDir)) {
    exit(0);
}

$storage = new Storage($settingsDir);
$states = $storage->read('plugins.json');
if (!is_array($states)) {
    $states = [];
}

$changed = false;
$repairs = [];
$pluginIds = [];

foreach (scandir($pluginsDir) ?: [] as $entry) {
    if ($entry === '.' || $entry === '..' || !preg_match('/^[a-z0-9_-]+$/i', $entry)) {
        continue;
    }
    $pluginFile = $pluginsDir . '/' . $entry . '/plugin.php';
    if (!is_file($pluginFile)) {
        continue;
    }
    $pluginIds[] = $entry;
    if (!isset($states[$entry]) || !is_array($states[$entry])) {
        $states[$entry] = ['active' => false, 'settings' => []];
        $changed = true;
    }
}

foreach ($pluginIds as $pluginId) {
    $state = $states[$pluginId] ?? null;
    if (!is_array($state)) {
        continue;
    }

    $pluginFile = $pluginsDir . '/' . $pluginId . '/plugin.php';
    $cmd = 'php -l ' . escapeshellarg($pluginFile) . ' 2>&1';
    $output = shell_exec($cmd) ?? '';
    $syntaxOk = str_contains($output, 'No syntax errors detected');

    if (!$syntaxOk) {
        if (!empty($state['active'])) {
            $states[$pluginId]['active'] = false;
            $changed = true;
        }
        $repairs[] = [
            'plugin_id' => $pluginId,
            'reason' => 'syntax check failed',
            'detail' => trim($output),
        ];
        continue;
    }

    // Prevent first-discovery auto-activation of automation when not yet configured.
    if ($pluginId === 'automation' && !array_key_exists('active', $state)) {
        $states[$pluginId]['active'] = false;
        $changed = true;
        $repairs[] = ['plugin_id' => $pluginId, 'reason' => 'seed inactive until admin enables'];
    }
}

if (!$changed) {
    exit(0);
}

$storage->write('plugins.json', $states);

$logDir = birkly_data_root() !== null ? birkly_data_root() . '/logs' : $root . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logFile = $logDir . '/plugin-emergency-repair.log';
$entry = [
    'time' => gmdate('c'),
    'repairs' => $repairs,
];
file_put_contents($logFile, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

fwrite(STDERR, '[emergency-deactivate-broken-plugins] Updated ' . count($repairs) . " plugin record(s)\n");
