<?php
/**
 * read_birkly_docs topic resolution tests.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/ai_helpers.php';
require_once dirname(__DIR__) . '/core/ai_playbooks.php';

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

$coreTopics = [
    'templating', 'admin', 'plugins', 'ai', 'mcp',
    'sub-collections', 'site-building', 'web-project-patterns', 'playbooks-index',
    'content-model', 'public-forms', 'commerce', 'automation',
    'agent-guide', 'mcp-setup',
];

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

$publicForms = readBirklyDocsTopic('public-forms');
assert_true(
    'public-forms mentions public_api',
    is_array($publicForms) && str_contains((string) ($publicForms['text'] ?? ''), 'public_api')
);

$patterns = readBirklyDocsTopic('web-project-patterns');
assert_true(
    'readBirklyDocsTopic(web-project-patterns)',
    $patterns !== null && str_contains($patterns['text'], 'birkly-client.js')
);

$commerce = readBirklyDocsTopic('commerce');
assert_true(
    'commerce playbook mentions fulfillment',
    is_array($commerce) && str_contains((string) ($commerce['text'] ?? ''), 'fulfillment')
);

$catalog = birkly_ai_playbook_catalog();
assert_true('playbook catalog has playbooks-index', isset($catalog['playbooks-index']));
assert_true('playbook catalog count >= 15', count($catalog) >= 15);

exit($failures > 0 ? 1 : 0);
