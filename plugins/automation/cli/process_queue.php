#!/usr/bin/env php
<?php
/**
 * CLI entry point for automation queue processing.
 *
 * Usage: php plugins/automation/cli/process_queue.php [limit]
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$root = dirname(__DIR__, 3);
chdir($root);

require_once $root . '/core/automation/bootstrap.php';
if (!automation_bootstrap()) {
    fwrite(STDERR, "Automation plugin is not active.\n");
    exit(1);
}
require_once __DIR__ . '/../includes/cron_auth.php';

$limit = isset($argv[1]) ? max(1, (int) $argv[1]) : 10;

$processed = process_automation_queue($limit);
$status = get_automation_queue_status();

echo json_encode([
    'status' => 'ok',
    'processed' => $processed,
    'queue' => $status,
], JSON_PRETTY_PRINT) . "\n";

exit(0);
