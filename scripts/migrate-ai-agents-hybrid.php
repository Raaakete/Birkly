#!/usr/bin/env php
<?php
/**
 * P5.1-M3 — Migrate legacy AI agents to hybrid personal + service model.
 *
 * Usage:
 *   php scripts/migrate-ai-agents-hybrid.php [--dry-run]
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/ai_agents.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

echo "P5.1 hybrid agents migration" . ($dryRun ? ' (dry run)' : '') . "\n\n";

$store = birkly_ai_agents_load_store();
$changed = 0;

foreach ($store['agents'] as &$agent) {
    $before = json_encode($agent);
    $agent = birkly_ai_agents_migrate_legacy_row($agent);
    if (json_encode($agent) !== $before) {
        $changed++;
        echo 'Migrated agent: ' . ($agent['id'] ?? 'unknown') . ' → type=' . ($agent['type'] ?? '') . "\n";
    }
}
unset($agent);

$hasPersonal = false;
foreach ($store['agents'] as $agent) {
    if (($agent['id'] ?? '') === BIRKLY_AI_AGENT_PERSONAL_ID) {
        $hasPersonal = true;
        break;
    }
}

if (!$hasPersonal) {
    echo "Personal agent will be created on next ensure_personal() call.\n";
}

$store['version'] = 2;

if ($dryRun) {
    echo "\nDry run complete. {$changed} agent row(s) would be updated.\n";
    exit(0);
}

if ($changed > 0 || ($store['version'] ?? 1) < 2) {
    birkly_ai_agents_save_store($store);
}

$personal = birkly_ai_agents_ensure_personal();
echo 'Personal agent: ' . ($personal['id'] ?? '') . "\n";
echo "\nMigration complete. {$changed} agent row(s) updated.\n";
