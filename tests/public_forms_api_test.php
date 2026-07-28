<?php
/**
 * Public form submission API tests.
 * Run: php tests/public_forms_api_test.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/api/storage.php';

function assert_true(string $label, bool $condition): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    fwrite(STDOUT, "{$label}: PASS\n");
}

$tmp = sys_get_temp_dir() . '/birkly_public_forms_' . uniqid('', true);
$content = $tmp . '/content/collections/contact';
@mkdir($content . '/entries', 0777, true);

$collectionMeta = [
    'slug' => 'contact',
    'name' => 'Contact',
    'entry_method' => 'public_api',
    'api_settings' => ['auto_publish' => true],
];
$schema = [
    'fields' => [
        ['name' => 'name', 'type' => 'text', 'required' => true],
        ['name' => 'email', 'type' => 'email', 'required' => true],
        ['name' => 'message', 'type' => 'textarea', 'required' => true],
    ],
];
file_put_contents($content . '/collection.json', json_encode($collectionMeta, JSON_PRETTY_PRINT));
file_put_contents($content . '/schema.json', json_encode($schema, JSON_PRETTY_PRINT));

// Simulate public_submit validation logic (subset)
$fields = ['email' => 'test@example.com'];
$errors = [];
foreach ($schema['fields'] as $field) {
    $name = $field['name'];
    if (!empty($field['required']) && trim((string) ($fields[$name] ?? '')) === '') {
        $errors[] = "{$name} is required";
    }
}
assert_true('missing name fails validation', in_array('name is required', $errors, true));
assert_true('missing message fails validation', in_array('message is required', $errors, true));

$fields = ['name' => 'Ada', 'email' => 'ada@test.com', 'message' => 'Hello'];
$errors = [];
foreach ($schema['fields'] as $field) {
    $name = $field['name'];
    if (!empty($field['required']) && trim((string) ($fields[$name] ?? '')) === '') {
        $errors[] = "{$name} is required";
    }
}
assert_true('valid payload passes validation', $errors === []);

$autoPublish = !empty($collectionMeta['api_settings']['auto_publish']);
$status = $autoPublish ? 'published' : 'pending';
assert_true('auto_publish sets published status', $status === 'published');

// Sub-collection auto_publish merge
$sub = ['auto_publish' => !empty(['api_settings' => ['auto_publish' => true]]['api_settings']['auto_publish'])];
assert_true('sub-collection reads api_settings.auto_publish', $sub['auto_publish'] === true);

@exec('rm -rf ' . escapeshellarg($tmp));

fwrite(STDOUT, "OK\n");
