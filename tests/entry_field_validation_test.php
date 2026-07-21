<?php
/**
 * Entry required-field validation tests (P30-M10).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/entry_validation.php';

test_reset_failures();
echo "Entry field validation tests\n\n";

$fields = [
    ['name' => 'title', 'label' => 'Title', 'required' => true],
    ['name' => 'body', 'label' => 'Body', 'required' => false],
];

$errors = birkly_validate_entry_required_fields(
    $fields,
    ['title' => ''],
    ['title' => ''],
    false,
    []
);
test_assert('empty required title fails', isset($errors['title']));
test_assert('optional body not in errors', !isset($errors['body']));

$ok = birkly_validate_entry_required_fields(
    $fields,
    ['title' => 'Hello'],
    ['title' => 'Hello'],
    false,
    []
);
test_assert('filled required title passes', count($ok) === 0);

exit(test_finish('entry_field_validation_test'));
