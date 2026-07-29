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
if (!is_array($states) || $states === []) {
    exit(0);
}

$changed = false;
$repairs = [];

foreach ($states as $pluginId => $state) {
    if (!is_array($state) || empty($state['active'])) {
        continue;
    }
    if (!is_string($pluginId) || !preg_match('/^[a-z0-9_-]+$/i', $pluginId)) {
        continue;
    }

    $pluginFile = $pluginsDir . '/' . $pluginId . '/plugin.php';
    if (!is_file($pluginFile)) {
        $states[$pluginId]['active'] = false;
        $changed = true;
        $repairs[] = ['plugin_id' => $pluginId, 'reason' => 'plugin.php missing'];
        continue;
    }

    $cmd = 'php -l ' . escapeshellarg($pluginFile) . ' 2>&1';
    $output = shell_exec($cmd) ?? '';
    if (!str_contains($output, 'No syntax errors detected')) {
        $states[$pluginId]['active'] = false;
        $changed = true;
        $repairs[] = [
            'plugin_id' => $pluginId,
            'reason' => 'syntax check failed',
            'detail' => trim($output),
        ];
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

fwrite(STDERR, '[emergency-deactivate-broken-plugins] Deactivated ' . count($repairs) . " plugin(s)\n");
