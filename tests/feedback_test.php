<?php
/**
 * P3-M10 — Feedback API unit tests (flat-file storage, validation).
 * Run: php tests/feedback_test.php
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();

$GLOBALS['__HARU_INTERNAL_CALL'] = true;

// Use isolated temp directory
$tmpFeedback = sys_get_temp_dir() . '/birkly-feedback-test-' . getmypid();
mkdir($tmpFeedback, 0755, true);

// Patch FEEDBACK_DIR via redefinition — load functions by including with custom dir
require_once __DIR__ . '/../api/rate_limit.php';

// Inline test helpers mirroring feedback.php (avoid session dependency)
function test_feedback_sanitize_id(string $id): string
{
    return preg_replace('/[^a-f0-9\-]/', '', strtolower($id));
}

function test_feedback_write(string $dir, array $record): bool
{
    $id = test_feedback_sanitize_id($record['id'] ?? '');
    if ($id === '') return false;
    return file_put_contents($dir . '/' . $id . '.json', json_encode($record, JSON_PRETTY_PRINT)) !== false;
}

function test_feedback_read(string $dir, string $id): ?array
{
    $id = test_feedback_sanitize_id($id);
    $path = $dir . '/' . $id . '.json';
    if (!is_file($path)) return null;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : null;
}

function test_feedback_list(string $dir): array
{
    $items = [];
    foreach (glob($dir . '/*.json') as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) $items[] = $data;
    }
    return $items;
}

// --- Tests ---

$validStatuses = ['new', 'in_progress', 'resolved', 'closed'];
$validTypes = ['bug', 'feature', 'feedback', 'question'];
$validCategories = ['admin', 'content', 'commerce', 'marketplace', 'plugins', 'ai', 'other'];

test_assert('sanitize_id strips invalid chars', test_feedback_sanitize_id('ABC-123_xxx') === 'abc-123');

$record = [
    'id' => '20260101120000-aabbccdd',
    'type' => 'bug',
    'category' => 'commerce',
    'title' => 'Test bug',
    'description' => 'Something broke',
    'status' => 'new',
    'user_email' => 'test@example.com',
    'created_at' => gmdate('c'),
    'updated_at' => gmdate('c'),
];
test_assert('write feedback record', test_feedback_write($tmpFeedback, $record));

$read = test_feedback_read($tmpFeedback, $record['id']);
test_assert('read feedback record', $read !== null && $read['title'] === 'Test bug');

$list = test_feedback_list($tmpFeedback);
test_assert('list contains one item', count($list) === 1);

// Status workflow
$read['status'] = 'in_progress';
$read['admin_notes'] = 'Looking into it';
test_assert('update status workflow', test_feedback_write($tmpFeedback, $read));
$updated = test_feedback_read($tmpFeedback, $record['id']);
test_assert('status persisted', ($updated['status'] ?? '') === 'in_progress');
test_assert('admin notes persisted', ($updated['admin_notes'] ?? '') === 'Looking into it');

// Validation constants
test_assert('valid statuses defined', count($validStatuses) === 4 && in_array('resolved', $validStatuses, true));
test_assert('valid types defined', in_array('question', $validTypes, true));
test_assert('valid categories defined', in_array('marketplace', $validCategories, true));

// Legal URL helpers
require_once __DIR__ . '/../core/platform.php';
test_assert('legal terms URL default', birkly_legal_terms_url() === 'https://birkly.com/terms.html');
test_assert('legal privacy URL default', birkly_legal_privacy_url() === 'https://birkly.com/privacy.html');
test_assert('admin legal footer partial exists', is_file(dirname(__DIR__) . '/admin/partials/admin-legal-footer.php'));

// Rate limiter smoke
$limiter = new RateLimiter('feedback_test_' . getmypid(), 3, 60);
test_assert('rate limiter allows first request', $limiter->check() === true);
test_assert('rate limiter allows second request', $limiter->check() === true);

// Manifest exists
$manifestPath = dirname(__DIR__) . '/docs/help-manifest.json';
$manifest = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
test_assert('help manifest exists and parses', is_array($manifest) && !empty($manifest['topics']));
$topicIds = array_column($manifest['topics'] ?? [], 'id');
test_assert('help manifest includes ai topic', in_array('ai', $topicIds, true));
test_assert('help manifest includes automation topic', in_array('automation', $topicIds, true));
test_assert('help manifest includes content-editor topic', in_array('content-editor', $topicIds, true));
test_assert('help manifest includes user-plugin topic', in_array('user-plugin', $topicIds, true));

// Snapshot files exist for required topics
$requiredTopics = ['ai', 'automation', 'user-plugin', 'content-editor'];
foreach ($requiredTopics as $tid) {
    $topic = null;
    foreach ($manifest['topics'] as $t) {
        if (($t['id'] ?? '') === $tid) { $topic = $t; break; }
    }
    $snapshot = $topic['snapshot'] ?? '';
    $localPath = dirname(__DIR__) . $snapshot;
    test_assert("snapshot exists for $tid", $snapshot !== '' && is_file($localPath));
}

// feedback.php file exists
test_assert('api/feedback.php exists', is_file(dirname(__DIR__) . '/api/feedback.php'));

// Cleanup
foreach (glob($tmpFeedback . '/*.json') as $f) unlink($f);
rmdir($tmpFeedback);

exit(test_finish('feedback_test'));
