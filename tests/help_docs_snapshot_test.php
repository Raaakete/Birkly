<?php
/**
 * Help manifest snapshot path normalization (P50 hotfix).
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/help_docs.php';

$failures = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

$path = birkly_help_topic_snapshot_path(['id' => 'getting-started']);
assert_true('snapshot path for getting-started', $path === '/admin/docs/snapshots/getting-started.md');

$pathAi = birkly_help_topic_snapshot_path(['id' => 'ai']);
assert_true('legacy snapshot path for ai', $pathAi === '/admin/docs/snapshots/ai-overview.md');

$normalized = birkly_normalize_help_topics([
    ['id' => 'content-model-overview', 'title' => 'Overview', 'docs_path' => '02-content/content-model-overview.html'],
]);
assert_true(
    'normalize adds snapshot when missing',
    count($normalized) === 1
        && ($normalized[0]['snapshot'] ?? '') === '/admin/docs/snapshots/content-model-overview.md'
);

$merged = birkly_merge_help_topics(
    [['id' => 'entries', 'title' => 'Entries']],
    [['id' => 'entries', 'title' => 'Entries live', 'docs_path' => '02-content/entries.html']]
);
assert_true(
    'merge preserves derived snapshot',
    count($merged) === 1
        && str_ends_with((string) ($merged[0]['snapshot'] ?? ''), 'entries.md')
);

exit($failures > 0 ? 1 : 0);
