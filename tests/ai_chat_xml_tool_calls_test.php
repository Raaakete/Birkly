<?php
/**
 * XML-style tool call extraction and display sanitization tests.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/ai_helpers.php';

$GLOBALS['__aiChatInternalLibrary'] = true;
require_once dirname(__DIR__) . '/api/ai_chat.php';

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

$xml = <<<'XML'
I'll apply the changes now.
<tool_call>delete_project_file
<arg_key>path</arg_key>
<arg_value>ndex.html</arg_value>
</tool_call>
<tool_call>write_project_file
<arg_key>path</arg_key>
<arg_value>index.html</arg_value><arg_key>content</arg_key>
<arg_value><!DOCTYPE html><html><body>Hello</body></html></arg_value>
</tool_call>
XML;

$actions = extractActionsFromResponse($xml, []);
assert_true('extracts multiple xml tool calls', count($actions) === 2, 'count=' . count($actions));
assert_true(
    'delete path parsed',
    ($actions[0]['type'] ?? '') === 'delete_project_file' && ($actions[0]['data']['path'] ?? '') === 'ndex.html'
);
assert_true(
    'write content parsed',
    ($actions[1]['type'] ?? '') === 'write_project_file'
        && ($actions[1]['data']['path'] ?? '') === 'index.html'
        && str_contains((string) ($actions[1]['data']['content'] ?? ''), '<!DOCTYPE html>')
);

$merged = extractActionsFromResponse($xml, [
    [
        'function' => [
            'name' => 'delete_project_file',
            'arguments' => json_encode(['path' => 'ndex.html']),
        ],
    ],
]);
assert_true('merges native and xml without duplicate delete', count($merged) === 2);

$clean = ai_chat_sanitize_display_message($xml);
assert_true('sanitize strips xml tool blocks', !str_contains($clean, '<tool_call>'));
assert_true('sanitize keeps intro prose', str_contains($clean, 'apply the changes'));

assert_true(
    'detects tool call markup',
    ai_chat_message_contains_tool_call_markup('<tool_call>list_entries</tool_call>')
);

exit($failures > 0 ? 1 : 0);
