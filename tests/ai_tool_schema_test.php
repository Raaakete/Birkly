<?php

require_once __DIR__ . '/../core/ai_tool_registry.php';

$failures = 0;

function assert_true(string $label, bool $condition): void
{
    global $failures;
    if (!$condition) {
        echo "FAIL: {$label}\n";
        $failures++;
    } else {
        echo "OK: {$label}\n";
    }
}

$normalized = birkly_ai_normalize_tool_input_schema([
    'type' => 'object',
    'properties' => [
        'fields' => ['type' => 'array', 'description' => 'Field schema'],
    ],
]);

$fields = $normalized['properties']['fields'] ?? [];
assert_true('array properties get items', isset($fields['items']) && is_array($fields['items']));
assert_true('field items are objects', ($fields['items']['type'] ?? '') === 'object');

$definitions = birkly_ai_tool_definitions();
$createFields = $definitions['create_collection']['inputSchema']['properties']['fields'] ?? [];
$updateFields = $definitions['update_collection']['inputSchema']['properties']['fields'] ?? [];

assert_true('create_collection fields has items', isset($createFields['items']));
assert_true('update_collection fields has items', isset($updateFields['items']));

$createNormalized = birkly_ai_normalize_tool_input_schema($definitions['create_collection']['inputSchema']);
$updateNormalized = birkly_ai_normalize_tool_input_schema($definitions['update_collection']['inputSchema']);

assert_true(
    'normalized create_collection fields has items',
    isset($createNormalized['properties']['fields']['items'])
);
assert_true(
    'normalized update_collection fields has items',
    isset($updateNormalized['properties']['fields']['items'])
);

$updateProps = $updateNormalized['properties'] ?? [];
assert_true('update_collection documents allow_public_read', isset($updateProps['allow_public_read']['type']) && $updateProps['allow_public_read']['type'] === 'boolean');

exit($failures > 0 ? 1 : 0);
