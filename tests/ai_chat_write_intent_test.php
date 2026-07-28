<?php
/**
 * Write-intent detection tests for AI chat action forcing.
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

assert_true(
    'fix it implies write',
    ai_chat_message_implies_write_intent('please try again to fix it')
);
assert_true(
    'try again implies write',
    ai_chat_message_implies_write_intent('please try again to fix the dark theme')
);
assert_true(
    'what is on homepage is read only',
    !ai_chat_message_implies_write_intent('what is on my homepage?')
);
assert_true(
    'list collections is read only',
    !ai_chat_message_implies_write_intent('list all collections')
);
assert_true(
    'proceed in site context implies write',
    ai_chat_message_implies_write_intent('yes proceed', [
        ['role' => 'user', 'content' => 'build a portfolio gallery site with index.html'],
    ])
);
assert_true(
    'false claim detected',
    ai_chat_response_implies_false_execution_claim('I have committed the new dark-theme design into index.html')
);
assert_true(
    'normal reply not false claim',
    !ai_chat_response_implies_false_execution_claim('I can update index.html if you want — should I proceed?')
);
assert_true(
    'review implies analysis',
    ai_chat_message_implies_analysis_intent('review the project website and tell me what to optimize')
);
assert_true(
    'so what is result implies analysis with history',
    ai_chat_message_implies_analysis_intent('so what is the result???', [
        ['role' => 'user', 'content' => 'review the project website'],
    ])
);
assert_true(
    'implement all is execute not analysis',
    ai_chat_message_implies_execute_intent('implement all of these')
);
assert_true(
    'yes execute is write not analysis',
    ai_chat_message_implies_write_intent('yes, execute them all', [
        ['role' => 'user', 'content' => 'Review the project website and tell me what to optimize'],
    ])
);
assert_true(
    'yes execute is not analysis when review in history',
    !ai_chat_message_implies_analysis_intent('yes, execute them all', [
        ['role' => 'user', 'content' => 'Review the project website and tell me what to optimize'],
    ])
);
assert_true(
    'do it and tell me is analysis not write',
    !ai_chat_message_implies_write_intent('do it and tell me', [
        ['role' => 'user', 'content' => 'review the project website'],
    ])
);
assert_true(
    'read stub detected',
    ai_chat_response_is_read_stub('Let me read the actual site files to give you a thorough')
);
assert_true(
    'real answer not stub',
    !ai_chat_response_is_read_stub('Your homepage loads a large CSS file inline. Consider moving styles to css/style.css and adding meta description tags.')
);

exit($failures > 0 ? 1 : 0);
