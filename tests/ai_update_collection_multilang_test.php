<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/ai_collection_config.php';
require_once dirname(__DIR__) . '/core/ai_write_validation.php';

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

$collection = ['slug' => 'i18n-write-test-temp', 'name' => 'I18n write test', 'entry_method' => 'admin_only'];
$mergeError = birkly_ai_merge_collection_config($collection, [
    'multilanguage' => [
        'enabled' => true,
        'languages' => ['en', 'de'],
    ],
], null);
assert_true('multilanguage merge succeeds', $mergeError === null, (string) $mergeError);
assert_true(
    'multilanguage enabled persisted',
    !empty($collection['multilanguage']['enabled']),
    json_encode($collection['multilanguage'] ?? null)
);
assert_true(
    'multilanguage languages persisted',
    ($collection['multilanguage']['languages'] ?? []) === ['en', 'de'],
    json_encode($collection['multilanguage'] ?? null)
);

$unknownKeys = birkly_ai_reject_unknown_keys(
    ['multilanguage' => ['enabled' => true, 'languages' => ['en']]],
    birkly_ai_collection_action_allowed_keys(),
    'update_collection'
);
assert_true('multilanguage is an allowed update_collection key', $unknownKeys === null, (string) $unknownKeys);

$rejectUnknown = birkly_ai_merge_collection_config($collection, [
    'multilanguage' => [
        'enabled' => true,
        'languages' => ['en'],
        'extra' => true,
    ],
], $collection);
assert_true(
    'unknown multilanguage keys rejected',
    $rejectUnknown !== null && str_contains((string) $rejectUnknown, 'Unknown multilanguage keys'),
    (string) $rejectUnknown
);

$rejectEmpty = birkly_ai_merge_collection_config($collection, [
    'multilanguage' => [
        'enabled' => true,
        'languages' => [],
    ],
], $collection);
assert_true(
    'enabled without languages rejected',
    $rejectEmpty !== null && str_contains((string) $rejectEmpty, 'at least one language'),
    (string) $rejectEmpty
);

$dedupeTarget = ['slug' => 'blog'];
$dedupeError = birkly_ai_merge_collection_config($dedupeTarget, [
    'multilanguage' => [
        'enabled' => true,
        'languages' => ['EN', 'en', 'de', 'DE'],
    ],
], null);
assert_true('duplicate language codes normalized', $dedupeError === null, (string) $dedupeError);
assert_true(
    'language codes lowercased and deduped',
    ($dedupeTarget['multilanguage']['languages'] ?? []) === ['en', 'de'],
    json_encode($dedupeTarget['multilanguage'] ?? null)
);

echo "\nai_update_collection_multilang_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
