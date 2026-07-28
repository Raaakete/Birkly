<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/ai_helpers.php';

$failures = 0;

function assert_true(string $name, bool $ok): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
    }
}

$parts = birkly_ai_parse_openai_chat_response([
    'choices' => [[
        'message' => [
            'role' => 'assistant',
            'content' => [['type' => 'text', 'text' => 'Hello from Gemma']],
        ],
    ]],
], 'OpenRouter');
assert_true('parses multimodal content array', ($parts['success'] ?? false) && ($parts['message'] ?? '') === 'Hello from Gemma');

$tools = birkly_ai_parse_openai_chat_response([
    'choices' => [[
        'message' => [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [['function' => ['name' => 'read_project_file', 'arguments' => '{}']]],
        ],
    ]],
], 'OpenRouter');
assert_true('parses tool-only response', ($tools['success'] ?? false) && count($tools['tool_calls'] ?? []) === 1);

$empty = birkly_ai_parse_openai_chat_response(['choices' => []], 'OpenRouter');
assert_true('empty choices fails gracefully', !($empty['success'] ?? true));

exit($failures > 0 ? 1 : 0);
