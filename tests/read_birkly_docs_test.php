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
    'ai-quality-guide', 'fieldtypes', 'collection-groups',
    'content-model', 'public-forms', 'commerce', 'commerce-store-kit', 'automation',
    'agent-guide', 'mcp-setup', 'ai-connections', 'plugins-overview',
    'media', 'cms-pages', 'content-editor', 'settings-roles', 'user-management', 'events',
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
    'templating playbook mentions ordered by newest',
    is_array($templating) && str_contains((string) ($templating['text'] ?? ''), 'ordered by newest')
);
assert_true(
    'templating clarifies entry for forms vs phantom',
    is_array($templating) && str_contains((string) ($templating['text'] ?? ''), '{entry for')
);

$spec = readBirklyDocsTopic('template-language-spec');
assert_true(
    'template-language-spec alias loads full TEMPLATE_LANGUAGE_SPEC',
    is_array($spec) && str_contains((string) ($spec['text'] ?? ''), 'Template Language Spec')
);

$mcp = readBirklyDocsTopic('mcp');
assert_true(
    'mcp alias resolves to mcp-setup playbook',
    is_array($mcp) && str_contains((string) ($mcp['text'] ?? ''), 'MCP')
);

$fieldtypes = readBirklyDocsTopic('fieldtypes');
assert_true(
    'fieldtypes mentions editor type',
    is_array($fieldtypes) && str_contains((string) ($fieldtypes['text'] ?? ''), 'editor')
);

$quality = readBirklyDocsTopic('ai-quality-guide');
assert_true(
    'ai-quality-guide has golden rules',
    is_array($quality) && str_contains((string) ($quality['text'] ?? ''), 'Golden rules')
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

$siteBuilding = readBirklyDocsTopic('site-building');
assert_true(
    'site-building from playbooks folder',
    is_array($siteBuilding) && str_contains((string) ($siteBuilding['text'] ?? ''), 'Build order')
);

$events = readBirklyDocsTopic('events');
assert_true(
    'events playbook has failure modes',
    is_array($events) && str_contains((string) ($events['text'] ?? ''), 'Common failure modes')
);

$publicFormsBad = readBirklyDocsTopic('public-forms');
assert_true(
    'public-forms has known bad patterns',
    is_array($publicFormsBad) && str_contains((string) ($publicFormsBad['text'] ?? ''), 'Known bad patterns')
);

$catalog = birkly_ai_playbook_catalog();
assert_true('playbook catalog has playbooks-index', isset($catalog['playbooks-index']));
assert_true('playbook catalog has ai-quality-guide', isset($catalog['ai-quality-guide']));
assert_true('playbook catalog has fieldtypes', isset($catalog['fieldtypes']));
assert_true('playbook catalog has commerce-store-kit', isset($catalog['commerce-store-kit']));
assert_true('playbook catalog count >= 20', count($catalog) >= 20);

$storeKit = readBirklyDocsTopic('commerce-store-kit');
assert_true(
    'commerce-store-kit has step order for products + checkout',
    is_array($storeKit)
        && str_contains((string) ($storeKit['text'] ?? ''), 'weight_grams')
        && str_contains((string) ($storeKit['text'] ?? ''), 'commerce-storefront.js')
);

$topics = birkly_ai_detect_playbook_topics('how do I connect Cursor via MCP', []);
assert_true(
    'detect mcp-setup from MCP message',
    in_array('mcp-setup', $topics, true)
);

$topicsForm = birkly_ai_detect_playbook_topics('fix my contact form validation failed', []);
assert_true(
    'detect public-forms from contact form message',
    in_array('public-forms', $topicsForm, true)
);

assert_true(
    'auto-inject for non-site MCP topic',
    birkly_ai_should_auto_inject_playbooks('connect claude to mcp', [])
);

exit($failures > 0 ? 1 : 0);
