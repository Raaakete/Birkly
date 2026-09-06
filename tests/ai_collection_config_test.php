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
    'api_settings' => ['auto_publish' => true, 'rate_limit' => 20],
];
$patchTarget = $existing;
$mergeError = birkly_ai_merge_collection_config($patchTarget, [
    'api_settings' => ['rate_limit' => 30],
], $existing);
assert_true('api_settings-only patch succeeds', $mergeError === null);
assert_true('rate_limit updated on partial patch', ($patchTarget['api_settings']['rate_limit'] ?? null) === 30);
assert_true('auto_publish preserved on partial patch', !empty($patchTarget['api_settings']['auto_publish']));

$legacyReadError = birkly_validate_api_settings_keys(['allow_anonymous_read' => true]);
assert_true('allow_anonymous_read rejected as api_settings key', $legacyReadError !== null && str_contains($legacyReadError, 'allow_anonymous_read'));

$publicReadTarget = ['slug' => 'blog', 'entry_method' => 'admin_only'];
$mergeError = birkly_ai_merge_collection_config($publicReadTarget, ['allow_public_read' => false], $publicReadTarget);
assert_true('allow_public_read patch succeeds', $mergeError === null);
assert_true('allow_public_read false persists', array_key_exists('allow_public_read', $publicReadTarget) && $publicReadTarget['allow_public_read'] === false);

$unknownError = birkly_validate_api_settings_keys(['not_a_real_key' => true]);
assert_true('unknown api_settings key rejected', $unknownError !== null && str_contains($unknownError, 'not_a_real_key'));

assert_true('keys derived from schema', in_array('allow_anonymous_submit', birkly_collection_api_settings_keys(), true));
assert_true('allow_anonymous_read not in schema keys', !in_array('allow_anonymous_read', birkly_collection_api_settings_keys(), true));

$migrated = birkly_migrate_collection_api_settings([
    'allow_anonymous_read' => true,
    'public_read_fields' => ['title'],
    'rate_limit' => 5,
]);
assert_true('migration strips legacy read keys', !array_key_exists('allow_anonymous_read', $migrated) && !array_key_exists('public_read_fields', $migrated));
assert_true('migration keeps valid keys', ($migrated['rate_limit'] ?? null) === 5);

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

$nested = birkly_ai_process_sub_collections([
    [
        'slug' => 'comments',
        'name' => 'Comments',
        'entry_method' => 'public_api',
        'fields' => [
            ['name' => 'author', 'type' => 'text', 'label' => 'Author'],
            ['name' => 'content', 'type' => 'textarea', 'label' => 'Comment'],
        ],
        'sub_collections' => [
            [
                'slug' => 'replies',
                'name' => 'Replies',
                'entry_method' => 'public_api',
                'fields' => [
                    ['name' => 'author', 'type' => 'text', 'label' => 'Author'],
                    ['name' => 'content', 'type' => 'textarea', 'label' => 'Reply'],
                ],
            ],
        ],
    ],
], 'club-posts');

assert_true('nested sub-collection count', count($nested) === 1);
assert_true('nested replies preserved', count($nested[0]['sub_collections'] ?? []) === 1);
assert_true('nested replies slug', ($nested[0]['sub_collections'][0]['slug'] ?? '') === 'replies');
assert_true('nested replies parent_sub_collection', ($nested[0]['sub_collections'][0]['parent_sub_collection'] ?? '') === 'comments');
assert_true('nested replies parent_field', ($nested[0]['sub_collections'][0]['parent_field'] ?? '') === 'comments_id');
assert_true('top-level comments has no parent_sub_collection', !array_key_exists('parent_sub_collection', $nested[0]));

$summary = birkly_ai_summarize_sub_collections_for_read($nested);
assert_true('read summary includes nested', count($summary[0]['sub_collections'] ?? []) === 1);
assert_true('read summary nested slug', ($summary[0]['sub_collections'][0]['slug'] ?? '') === 'replies');

exit($failures > 0 ? 1 : 0);
