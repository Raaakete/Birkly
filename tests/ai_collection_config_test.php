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
birkly_ai_apply_entry_method_config($collection, [
    'entry_method' => 'public_api',
    'api_settings' => ['auto_publish' => true, 'rate_limit' => 20],
], null);

assert_true('public_api sets entry_method', ($collection['entry_method'] ?? '') === 'public_api');
assert_true('public_api generates api_key', str_starts_with((string) ($collection['api_key'] ?? ''), 'birkly_'));
assert_true('public_api sets auto_publish', !empty($collection['api_settings']['auto_publish']));

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
