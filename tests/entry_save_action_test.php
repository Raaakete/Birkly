<?php
/**
 * Regression: admin entry editor posts ?action=save without entry_id for new
 * entries. That must resolve to create so entry_created automations fire.
 */

require_once __DIR__ . '/../core/entry_slug.php';

$failures = 0;
function assert_true(bool $cond, string $msg): void
{
    global $failures;
    if ($cond) {
        echo "PASS $msg\n";
        return;
    }
    $failures++;
    echo "FAIL $msg\n";
}

echo "Entry save action resolution tests\n\n";

assert_true(
    function_exists('birkly_resolve_entry_save_action'),
    'birkly_resolve_entry_save_action is defined'
);

assert_true(
    birkly_resolve_entry_save_action('') === 'create',
    'empty entry_id is create'
);

assert_true(
    birkly_resolve_entry_save_action(null) === 'create',
    'null entry_id is create'
);

assert_true(
    birkly_resolve_entry_save_action('   ') === 'create',
    'whitespace entry_id is create'
);

assert_true(
    birkly_resolve_entry_save_action('abc123') === 'update',
    'present entry_id is update'
);

$source = file_get_contents(__DIR__ . '/../api/admin_entries.php');
assert_true(
    str_contains($source, 'birkly_resolve_entry_save_action'),
    'saveEntry uses resolve helper'
);
assert_true(
    str_contains($source, "do_action('birkly_entry_saved'") &&
    str_contains($source, '$isNewEntry'),
    'birkly_entry_saved action includes is_new from isNewEntry'
);

if ($failures > 0) {
    fwrite(STDERR, "\n$failures failure(s)\n");
    exit(1);
}

echo "\nAll entry save action tests passed.\n";
exit(0);
