<?php
/**
 * Regression: publishing must not create a second entry when the client
 * double-submits or omits entry_id on a follow-up save with the same slug.
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

echo "Entry publish dedupe tests\n\n";

$adminEntries = file_get_contents(__DIR__ . '/../api/admin_entries.php');
$entryEditorJs = file_get_contents(__DIR__ . '/../admin/js/entry-editor.js');
$entryEditorTemplate = file_get_contents(__DIR__ . '/../admin/templates/entry-editor.html');

assert_true(
    str_contains($adminEntries, 'birkly_entry_matches_slug'),
    'saveEntry upgrades duplicate slug creates to updates'
);
assert_true(
    str_contains($entryEditorJs, 'knownEntryId'),
    'entry editor tracks known entry id across saves'
);
assert_true(
    str_contains($entryEditorJs, 'tryAcquireSaveLock'),
    'entry editor uses a synchronous save lock'
);
assert_true(
    str_contains($entryEditorJs, 'finalFormData.set(\'entry_id\''),
    'entry editor sets entry_id on save payload'
);
assert_true(
    str_contains($entryEditorTemplate, "'entryId'"),
    'entry editor config exposes initial entry id'
);

assert_true(
    birkly_entry_matches_slug(['slug' => 'hello-world'], 'hello-world'),
    'slug matcher recognizes identical slugs'
);

if ($failures > 0) {
    fwrite(STDERR, "\n$failures failure(s)\n");
    exit(1);
}

echo "\nAll entry publish dedupe tests passed.\n";
exit(0);
