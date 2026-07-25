<?php
/**
 * read_birkly_docs topic resolution tests.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/ai_helpers.php';

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

$coreTopics = ['templating', 'admin', 'plugins', 'ai', 'mcp', 'sub-collections'];

foreach ($coreTopics as $topic) {
    $result = readBirklyDocsTopic($topic);
    assert_true(
        "readBirklyDocsTopic({$topic})",
        is_array($result) && ($result['text'] ?? '') !== '',
        'expected non-empty topic payload'
    );
}

$missing = readBirklyDocsTopic('not-a-real-topic');
assert_true('readBirklyDocsTopic(unknown)', $missing === null);

$templating = readBirklyDocsTopic('templating');
assert_true(
    'templating mentions birkly-client.js',
    is_array($templating) && str_contains((string) ($templating['text'] ?? ''), 'birkly-client.js')
);
assert_true(
    'templating does not mention cms-template.v2.js',
    is_array($templating) && !str_contains((string) ($templating['text'] ?? ''), 'cms-template.v2.js')
);

exit($failures > 0 ? 1 : 0);
