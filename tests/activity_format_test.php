<?php
/**
 * P69 — activity formatter v2 tests.
 */

require_once __DIR__ . '/../core/activity_format.php';

function af_assert(string $label, bool $ok): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "OK: {$label}\n";
}

$item = birkly_activity_format_item(
    'publish_entry',
    'editor@example.com',
    ['collection' => 'blog', 'title' => 'Hello', 'id' => 'hello'],
    '2026-08-28T10:00:00+00:00',
    'security_log'
);

af_assert('has severity', ($item['severity'] ?? '') === 'success');
af_assert('has actor', ($item['actor']['label'] ?? '') === 'editor@example.com');
af_assert('has target collection', ($item['target']['type'] ?? '') === 'entry');
af_assert('has href', is_string($item['href'] ?? null) && $item['href'] !== '');
af_assert('has stable id', strlen((string) ($item['id'] ?? '')) > 0);
af_assert('message backward compat', str_contains((string) ($item['message'] ?? ''), 'Published'));

$project = birkly_activity_format_item(
    'project_git_pushed',
    'admin@example.com',
    ['branch' => 'main'],
    '2026-08-28T11:00:00+00:00'
);
af_assert('project group git', ($project['project_group'] ?? '') === 'git');

$plugin = birkly_activity_format_plugin_item([
    'id' => 'act_test123',
    'plugin' => 'commerce',
    'event' => 'order_paid',
    'title' => 'Order paid',
    'body' => 'Order #42',
    'created_at' => '2026-08-28T12:00:00+00:00',
    'meta' => ['order_id' => '42'],
]);
af_assert('plugin bus source', ($plugin['source'] ?? '') === 'plugin_bus');
af_assert('plugin title message', ($plugin['message'] ?? '') === 'Order paid');

$publicDenied = birkly_activity_format_item(
    'public_read_denied',
    'guest',
    [
        'collection' => 'blog',
        'reason' => 'public_read_disabled',
        'action' => 'get_entry',
        'ip' => '10.0.1.6',
        'origin' => 'https://birkly.io',
        'referer' => 'https://birkly.io/',
        'hint' => 'Enable Allow public read access in collection settings',
    ],
    '2026-08-29T15:52:23+00:00'
);
af_assert('public read denied category', ($publicDenied['category'] ?? '') === 'system');
af_assert('public read denied severity', ($publicDenied['severity'] ?? '') === 'warning');
af_assert('public read denied actor visitor', ($publicDenied['actor']['type'] ?? '') === 'visitor');
af_assert('public read denied actor label', str_contains((string) ($publicDenied['actor']['label'] ?? ''), 'birkly.io'));
af_assert('public read denied message', str_contains((string) ($publicDenied['message'] ?? ''), 'blog'));
af_assert('public read denied summary', str_contains((string) ($publicDenied['detail_summary'] ?? ''), 'Allow public read'));

af_assert('domain auth success hidden from feed', birkly_activity_is_routine_public_noise('domain_auth_success'));

echo "Activity format tests passed.\n";
