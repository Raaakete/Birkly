<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/ai_helpers.php';

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
    'write path fixes ndex typo',
    birkly_ai_normalize_project_file_path('ndex.html') === 'index.html'
);
assert_true(
    'delete path keeps ndex typo',
    birkly_ai_normalize_project_file_path_for_delete('ndex.html') === 'ndex.html'
);
assert_true(
    'delete path strips project prefix only',
    birkly_ai_normalize_project_file_path_for_delete('project/ndex.html') === 'ndex.html'
);
assert_true(
    'delete action data does not rewrite typo filenames',
    birkly_ai_normalize_action_data('delete_project_file', ['path' => 'rtworks.html'])['path'] === 'rtworks.html'
);
assert_true(
    'write action data still fixes typos',
    birkly_ai_normalize_action_data('write_project_file', ['path' => 'bout.html'])['path'] === 'about.html'
);

$bulkBlock = birkly_ai_validate_bulk_project_deletes([
    ['type' => 'delete_project_file', 'data' => ['path' => 'index.html']],
    ['type' => 'delete_project_file', 'data' => ['path' => 'about.html']],
]);
assert_true(
    'bulk delete of core pages is blocked',
    is_string($bulkBlock) && str_contains($bulkBlock, 'Refusing bulk delete')
);

$singleOk = birkly_ai_validate_bulk_project_deletes([
    ['type' => 'delete_project_file', 'data' => ['path' => 'ndex.html']],
]);
assert_true(
    'single typo delete is allowed by bulk guard',
    $singleOk === null
);

exit($failures > 0 ? 1 : 0);
