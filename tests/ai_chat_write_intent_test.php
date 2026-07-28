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

exit($failures > 0 ? 1 : 0);
