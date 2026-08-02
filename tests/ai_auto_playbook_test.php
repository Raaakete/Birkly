<?php
/**
 * Auto playbook injection and casual site-build intent tests.
 */

declare(strict_types=1);

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

// Casual business site prompts
assert_true(
    'bakery website is site intent',
    ai_chat_is_project_site_intent('build me a birkly website for my bakery business')
);
assert_true(
    'restaurant site is site intent',
    ai_chat_is_project_site_intent('I need a site for my restaurant')
);
assert_true(
    'bakery prompt implies write',
    ai_chat_message_implies_write_intent('build me a birkly website for my bakery business')
);
assert_true(
    'website for my shop implies write',
    ai_chat_message_implies_write_intent('make a website for my shop')
);
assert_true(
    'list collections still read only',
    !ai_chat_message_implies_write_intent('list all collections')
);

// Topic detection
$topics = birkly_ai_detect_playbook_topics('build me a bakery website');
assert_true(
    'site build detects core topics',
    in_array('web-project-patterns', $topics, true)
    && in_array('site-building', $topics, true)
    && in_array('content-model', $topics, true)
    && in_array('templating', $topics, true),
    'topics: ' . implode(', ', $topics)
);
$commerceTopics = birkly_ai_detect_playbook_topics('build an online store with checkout');
assert_true(
    'commerce prompt adds commerce topic',
    in_array('commerce', $commerceTopics, true),
    'topics: ' . implode(', ', $commerceTopics)
);
assert_true(
    'store build prompt adds commerce-store-kit',
    in_array('commerce-store-kit', $commerceTopics, true),
    'topics: ' . implode(', ', $commerceTopics)
);
$storeKitTopics = birkly_ai_detect_playbook_topics('run the MCP store kit and seed products', []);
assert_true(
    'store kit phrase detects commerce-store-kit',
    in_array('commerce-store-kit', $storeKitTopics, true),
    'topics: ' . implode(', ', $storeKitTopics)
);

// Auto inject gates
assert_true(
    'fresh site build should inject playbooks',
    birkly_ai_should_auto_inject_playbooks('build me a bakery website', [])
);
assert_true(
    'execute should inject playbooks',
    birkly_ai_should_auto_inject_playbooks('yes proceed', [
        ['role' => 'user', 'content' => 'build a portfolio site'],
    ])
);
assert_true(
    'minor tweak after progress skips full inject',
    !birkly_ai_should_auto_inject_playbooks('what color is the header?', [
        ['role' => 'assistant', 'content' => "[Session notes — already done this conversation, do not repeat: project files: index.html]"],
    ])
);

// Playbook section content
$section = birkly_ai_build_auto_playbook_section(['site-building']);
assert_true(
    'auto playbook section includes site-building title',
    str_contains($section, 'site-building') && str_contains($section, 'pre-loaded automatically')
);
assert_true(
    'empty topics yields empty section',
    birkly_ai_build_auto_playbook_section([]) === ''
);

// MCP instructions
$mcpInstructions = birkly_ai_mcp_initialize_instructions();
assert_true(
    'MCP instructions include core rules',
    str_contains($mcpInstructions, 'birkly-client.js')
    && str_contains($mcpInstructions, 'web-project-patterns')
    && str_contains($mcpInstructions, 'write_project_file')
    && str_contains($mcpInstructions, 'detail pages')
);

// Project discovery gate
assert_true(
    'site write should auto discover',
    birkly_ai_should_auto_project_discovery('build me a bakery website', [])
);
assert_true(
    'read-only question skips discovery',
    !birkly_ai_should_auto_project_discovery('what is on my homepage?', [])
);

exit($failures > 0 ? 1 : 0);
