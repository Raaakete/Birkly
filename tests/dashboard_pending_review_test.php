<?php
/**
 * Pending review dashboard collection tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Dashboard pending review tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/core/dashboard_widgets.php';
require_once $root . '/core/storage/StorageFactory.php';

$tempRoot = sys_get_temp_dir() . '/birkly_pending_review_' . bin2hex(random_bytes(4));
$contentDir = $tempRoot . '/content/collections/blog/entries';
@mkdir($contentDir, 0755, true);

$storage = StorageFactory::createStorage($tempRoot . '/content');

file_put_contents($contentDir . '/post-one.json', json_encode([
    'id' => 'post-one',
    'title' => 'Awaiting approval',
    'status' => 'pending_review',
    'reviewer' => 'reviewer-user',
    'author_id' => 'author-user',
    'submitted_by' => 'author-user',
    'updated_at' => '2026-07-21T10:00:00+00:00',
], JSON_PRETTY_PRINT));

file_put_contents($contentDir . '/post-two.json', json_encode([
    'id' => 'post-two',
    'title' => 'Published post',
    'status' => 'published',
    'updated_at' => '2026-07-21T11:00:00+00:00',
], JSON_PRETTY_PRINT));

$userIndex = [
    'author-user' => ['id' => 'author-user', 'name' => 'Casey Author', 'email' => 'author@example.test'],
    'reviewer-user' => ['id' => 'reviewer-user', 'name' => 'Riley Reviewer', 'email' => 'reviewer@example.test'],
    'reviewer@example.test' => ['id' => 'reviewer-user', 'name' => 'Riley Reviewer', 'email' => 'reviewer@example.test'],
];

$entries = birkly_collect_pending_review_entries(10, 'all', 'reviewer-user', $storage, $userIndex);
test_assert('collects pending review entries only', count($entries) === 1);
test_assert('pending review entry resolves reviewer and author',
    ($entries[0]['title'] ?? '') === 'Awaiting approval'
    && ($entries[0]['reviewer'] ?? '') === 'Riley Reviewer'
    && ($entries[0]['submitted_by'] ?? '') === 'Casey Author');

$entriesWithSubmitter = birkly_collect_pending_review_entries(10, 'all', null, $storage, $userIndex);
test_assert('pending review prefers submitted_by field', ($entriesWithSubmitter[0]['submitted_by'] ?? '') === 'Casey Author');

$assignedToMe = birkly_collect_pending_review_entries(10, 'me', 'reviewer-user', $storage, $userIndex);
test_assert('assigned_to=me includes matching reviewer', count($assignedToMe) === 1);

$assignedToOther = birkly_collect_pending_review_entries(10, 'me', 'someone-else', $storage, $userIndex);
test_assert('assigned_to=me excludes non-matching reviewer', count($assignedToOther) === 0);

test_assert('resolve user display handles missing references', birkly_resolve_user_display('', $userIndex) === 'Unassigned');

array_map('unlink', glob($contentDir . '/*.json') ?: []);
@rmdir($contentDir);
@rmdir(dirname($contentDir));
@rmdir(dirname(dirname($contentDir)));
@rmdir(dirname(dirname(dirname($contentDir))));
@rmdir($tempRoot);

exit(test_finish('dashboard_pending_review_test'));
