<?php
/**
 * P2-R12 — create_collection accepts schema.fields or top-level fields.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../core/collection_schema.php';

test_reset_failures();

$fields = [
    ['name' => 'title', 'type' => 'text', 'label' => 'Title', 'required' => true],
];

test_assert(
    'top-level fields',
    birkly_normalize_collection_schema(['fields' => $fields]) === ['fields' => $fields]
);

test_assert(
    'schema.fields',
    birkly_normalize_collection_schema(['schema' => ['fields' => $fields]]) === ['fields' => $fields]
);

test_assert(
    'schema as bare field list',
    birkly_normalize_collection_schema(['schema' => $fields]) === ['fields' => $fields]
);

test_assert(
    'missing keys returns empty fields array',
    birkly_normalize_collection_schema(['title' => 'Test']) === ['fields' => []]
);

exit(test_finish('collection_schema_test'));
