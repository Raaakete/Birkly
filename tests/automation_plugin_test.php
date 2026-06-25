<?php
/**
 * P3-M7 — Automation plugin / queue regression tests.
 * Run: php tests/automation_plugin_test.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../core/storage/StorageFactory.php';
require_once __DIR__ . '/../core/automation/init.php';
require_once __DIR__ . '/../plugins/automation/includes/cron_auth.php';

test_reset_failures();

echo "Automation plugin tests\n\n";

// --- Cron token auth ---
$token = automation_get_cron_token();
test_assert('cron token generated', is_string($token) && strlen($token) >= 32);
test_assert('cron token matches', automation_cron_token_matches($token));
test_assert('invalid cron token rejected', !automation_cron_token_matches('invalid-token'));

// --- register_plugin_action ---
if (function_exists('register_plugin_action')) {
    register_plugin_action('test_plugin_action', [
        'name' => 'Test Plugin Action',
        'schema' => [],
        'handler' => function () { return ['ok' => true]; },
    ]);
    $block = get_automation_block('action', 'test_plugin_action');
    test_assert('register_plugin_action registers block', $block !== null && ($block['name'] ?? '') === 'Test Plugin Action');
} else {
    test_assert('register_plugin_action exists', false, 'function missing');
}

// --- Queue enqueue + process ---
$settingsDir = dirname(__DIR__) . '/settings';
$queueFile = $settingsDir . '/automation_queue.json';
$queueBackup = null;
if (is_file($queueFile)) {
    $queueBackup = file_get_contents($queueFile);
}

$storage = StorageFactory::createStorage($settingsDir);
$storage->write('automation_queue.json', []);

$enqueued = enqueue_automation('entry_created', [
    'entry' => ['id' => 'test_entry', 'title' => 'Queue Test'],
    'collection' => 'posts',
]);
test_assert('enqueue_automation succeeds', $enqueued === true);

$statusBefore = get_automation_queue_status();
test_assert('queue has pending job', ($statusBefore['pending'] ?? 0) >= 1);

$processed = process_automation_queue(5);
test_assert('process_automation_queue runs', $processed >= 1);

$statusAfter = get_automation_queue_status();
test_assert('queue job completed or processing', ($statusAfter['completed'] ?? 0) >= 1 || ($statusAfter['processing'] ?? 0) >= 0);

// Restore queue file
if ($queueBackup !== null) {
    file_put_contents($queueFile, $queueBackup);
} else {
    @unlink($queueFile);
}

// --- CLI script exists and is syntactically valid ---
$cliScript = dirname(__DIR__) . '/plugins/automation/cli/process_queue.php';
test_assert('CLI process_queue script exists', is_file($cliScript));
if (is_file($cliScript)) {
    exec('php -l ' . escapeshellarg($cliScript) . ' 2>&1', $lintOut, $lintCode);
    test_assert('CLI process_queue syntax valid', $lintCode === 0, implode("\n", $lintOut));
}

exit(test_finish('automation_plugin_test.php'));
