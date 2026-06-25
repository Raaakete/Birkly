<?php
/**
 * Remove stale AI runtime data (conversations, approvals).
 *
 * Usage:
 *   php scripts/cleanup-ai-data.php [--days=30] [--dry-run]
 */

$options = getopt('', ['days::', 'dry-run']);
$days = isset($options['days']) ? max(1, (int) $options['days']) : 30;
$dryRun = array_key_exists('dry-run', $options);
$cutoff = time() - ($days * 86400);

$root = dirname(__DIR__);
$targets = [
    $root . '/data/ai_conversations',
    $root . '/data/ai_approvals',
];

$removed = 0;

foreach ($targets as $dir) {
    if (!is_dir($dir)) {
        echo "Skip (missing): $dir\n";
        continue;
    }

    foreach (glob($dir . '/*.json') ?: [] as $file) {
        $mtime = filemtime($file) ?: 0;
        if ($mtime >= $cutoff) {
            continue;
        }

        if ($dryRun) {
            echo "Would remove: $file\n";
        } else {
            unlink($file);
            echo "Removed: $file\n";
        }
        $removed++;
    }
}

echo ($dryRun ? 'Would remove' : 'Removed') . " $removed file(s) older than $days days.\n";
exit(0);
