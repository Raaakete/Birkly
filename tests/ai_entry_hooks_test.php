<?php
/**
 * AI/MCP entry writes must fire after_entry_save / after_entry_delete
 * so plugins (e.g. Commerce catalog indexer) stay in sync.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once $root . '/api/ai_approvals.php';

if (!function_exists('birkly_ai_after_entry_write') || !function_exists('birkly_ai_after_entry_delete')) {
    fwrite(STDERR, "FAIL: AI entry hook helpers missing\n");
    exit(1);
}

require_once $root . '/core/plugins.php';

$GLOBALS['__AI_ENTRY_HOOK_EVENTS'] = [];
register_hook('after_entry_save', static function ($entry, $collection, $oldEntry = null) {
    $GLOBALS['__AI_ENTRY_HOOK_EVENTS'][] = [
        'event' => 'after_entry_save',
        'collection' => $collection,
        'id' => $entry['id'] ?? null,
        'had_old' => is_array($oldEntry),
    ];
});
register_hook('after_entry_delete', static function ($entry, $collection) {
    $GLOBALS['__AI_ENTRY_HOOK_EVENTS'][] = [
        'event' => 'after_entry_delete',
        'collection' => $collection,
        'id' => $entry['id'] ?? null,
    ];
});

birkly_ai_after_entry_write(
    ['id' => 'entry-create', 'title' => 'Hook create', 'status' => 'published'],
    'donations',
    null
);
birkly_ai_after_entry_write(
    ['id' => 'entry-update', 'title' => 'Hook update', 'status' => 'published'],
    'donations',
    ['id' => 'entry-update', 'title' => 'Old']
);
birkly_ai_after_entry_delete(
    ['id' => 'entry-delete', 'title' => 'Hook delete'],
    'donations'
);

$events = $GLOBALS['__AI_ENTRY_HOOK_EVENTS'];
if (count($events) !== 3) {
    fwrite(STDERR, 'FAIL: expected 3 hook events, got ' . count($events) . "\n");
    exit(1);
}
if (($events[0]['event'] ?? '') !== 'after_entry_save' || ($events[0]['had_old'] ?? true) !== false) {
    fwrite(STDERR, "FAIL: create hook shape wrong\n");
    exit(1);
}
if (($events[1]['event'] ?? '') !== 'after_entry_save' || ($events[1]['had_old'] ?? false) !== true) {
    fwrite(STDERR, "FAIL: update hook shape wrong\n");
    exit(1);
}
if (($events[2]['event'] ?? '') !== 'after_entry_delete') {
    fwrite(STDERR, "FAIL: delete hook missing\n");
    exit(1);
}

echo "PASS: ai_entry_hooks_test\n";
