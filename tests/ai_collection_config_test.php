<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/ai_collection_config.php';

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

$collection = ['slug' => 'contact', 'name' => 'Contact', 'entry_method' => 'admin_only'];
$mergeError = birkly_ai_merge_collection_config($collection, [
    'entry_method' => 'public_api',
    'api_settings' => ['auto_publish' => true, 'rate_limit' => 20],
], null);
assert_true('public_api merge succeeds', $mergeError === null);

assert_true('public_api sets entry_method', ($collection['entry_method'] ?? '') === 'public_api');
assert_true('public_api generates api_key', str_starts_with((string) ($collection['api_key'] ?? ''), 'birkly_'));
assert_true('public_api sets auto_publish', !empty($collection['api_settings']['auto_publish']));

$existing = [
    'slug' => 'supporters',
    'entry_method' => 'public_api',
    'api_key' => 'birkly_testkey',
    'api_settings' => ['auto_publish' => true, 'rate_limit' => 20, 'allow_anonymous_read' => true],
];
$patchTarget = $existing;
$mergeError = birkly_ai_merge_collection_config($patchTarget, [
    'api_settings' => ['allow_anonymous_read' => false],
], $existing);
assert_true('api_settings-only patch succeeds', $mergeError === null);
assert_true(
    'allow_anonymous_read false persists without entry_method',
    array_key_exists('allow_anonymous_read', $patchTarget['api_settings'] ?? [])
        && ($patchTarget['api_settings']['allow_anonymous_read'] ?? null) === false
);
assert_true('rate_limit preserved on partial patch', ($patchTarget['api_settings']['rate_limit'] ?? null) === 20);

$unknownError = birkly_validate_api_settings_keys(['not_a_real_key' => true]);
assert_true('unknown api_settings key rejected', $unknownError !== null && str_contains($unknownError, 'not_a_real_key'));

assert_true('keys derived from schema', in_array('allow_anonymous_read', birkly_collection_api_settings_keys(), true));

$subs = birkly_ai_process_sub_collections([
    [
        'slug' => 'comments',
        'name' => 'Comments',
        'entry_method' => 'public_api',
        'fields' => [
            ['name' => 'author', 'type' => 'text', 'label' => 'Author'],
            ['name' => 'content', 'type' => 'textarea', 'label' => 'Comment'],
        ],
    ],
], 'blog');

assert_true('sub-collection processed', count($subs) === 1 && ($subs[0]['entry_method'] ?? '') === 'public_api');
assert_true('sub-collection parent_field', ($subs[0]['parent_field'] ?? '') === 'blog_id');

exit($failures > 0 ? 1 : 0);
