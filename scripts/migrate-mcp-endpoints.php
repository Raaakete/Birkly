#!/usr/bin/env php
<?php
/**
 * Migrate legacy per-AI-connection MCP keys to standalone endpoint profiles.
 * Usage: php scripts/migrate-mcp-endpoints.php [--dry-run]
 */

require_once __DIR__ . '/../core/mcp_endpoints.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

echo "MCP endpoint migration\n";
echo $dryRun ? "(dry run — no writes)\n\n" : "\n";

if ($dryRun) {
    $usersStorage = new Storage(__DIR__ . '/../users');
    $count = 0;
    foreach ($usersStorage->list('') as $userFile) {
        $user = $usersStorage->read($userFile);
        if ($user && ($user['type'] ?? '') === 'ai' && !empty($user['mcp_api_key'])) {
            $count++;
            echo "  Would migrate: {$user['id']} ({$user['name']})\n";
        }
    }
    echo "\nTotal: $count connection(s) with mcp_api_key\n";
    exit(0);
}

$result = birkly_mcp_migrate_from_ai_connections();
echo "Migrated: {$result['migrated']}\n";
echo "Skipped: {$result['skipped']}\n";
echo "Legacy dual-resolve until: {$result['legacy_until']}\n";
echo "\nDone.\n";
