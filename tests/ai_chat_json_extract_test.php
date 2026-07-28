<?php
/**
 * JSON action extraction tests — ensures CSS/HTML in file content does not break parsing.
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

$html = '<style>.card { color: #fff; }</style><body>Hello</body>';
$payload = json_encode([
    'actions' => [[
        'type' => 'write_project_file',
        'path' => 'index.html',
        'content' => $html,
    ]],
], JSON_UNESCAPED_SLASHES);

$response = "I'll update the homepage.\n\n```json\n{$payload}\n```\n\nDone.";
$actions = extractActionsFromResponse($response, []);

assert_true('extracts action from fenced json with css braces', count($actions) === 1);
assert_true(
    'path is index.html',
    ($actions[0]['data']['path'] ?? '') === 'index.html',
    'got: ' . ($actions[0]['data']['path'] ?? 'none')
);
assert_true(
    'content includes css rule',
    str_contains((string) ($actions[0]['data']['content'] ?? ''), '.card { color: #fff; }')
);

$inline = '{"actions":[{"type":"write_project_file","path":"css/style.css","content":"body { background: #030305; }"}]}';
$actions2 = extractActionsFromResponse('Updating styles ' . $inline, []);
assert_true('extracts inline json with css', count($actions2) === 1);
assert_true(
    'inline path css/style.css',
    ($actions2[0]['data']['path'] ?? '') === 'css/style.css'
);

assert_true(
    'rejects empty content',
    extractActionsFromResponse('{"actions":[{"type":"write_project_file","path":"index.html","content":""}]}', []) === []
);

exit($failures > 0 ? 1 : 0);
