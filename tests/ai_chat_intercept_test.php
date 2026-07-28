<?php
/**
 * AI chat intercept / site-build mode detection tests.
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

$genericEntryHistory = [
    ['role' => 'assistant', 'content' => "Created entry.\n\n[Session notes — already done this conversation, do not repeat: cms: entry:blog]"],
];

assert_true(
    'generic create_entry does not enable site-build mode',
    !ai_chat_session_has_site_build_progress($genericEntryHistory)
);

$siteBuildHistory = [
    ['role' => 'user', 'content' => 'Build a portfolio website in project/'],
    ['role' => 'assistant', 'content' => "Updated collection.\n\n[Session notes — already done this conversation, do not repeat: cms: collection:about]"],
];

assert_true(
    'site-build conversation with cms actions enables site-build mode',
    ai_chat_session_has_site_build_progress($siteBuildHistory)
);

$projectFileHistory = [
    ['role' => 'assistant', 'content' => "[Session notes — already done this conversation, do not repeat: project files: index.html]"],
];

assert_true(
    'project file writes always enable site-build mode',
    ai_chat_session_has_site_build_progress($projectFileHistory)
);

assert_true(
    'bare yes is not a proceed message',
    !ai_chat_is_proceed_message('yes')
);

assert_true(
    'yes but why is not a proceed message',
    !ai_chat_is_proceed_message('yes but why?')
);

assert_true(
    'explicit proceed is detected',
    ai_chat_is_proceed_message('please proceed with the next step')
);

assert_true(
    'generic blog question is not site intent',
    !ai_chat_is_project_site_intent('Can you shorten the title?', $genericEntryHistory)
);

exit($failures > 0 ? 1 : 0);
