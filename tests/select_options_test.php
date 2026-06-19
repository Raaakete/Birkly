<?php
/**
 * P2-R03 — Select fieldtype accepts flat-string and label/value option shapes.
 */

require_once __DIR__ . '/../fieldtypes/BaseField.php';
require_once __DIR__ . '/../fieldtypes/Select/Select.php';

use Birkly\FieldTypes\Select\Select;

$failures = 0;

function assert_contains(string $haystack, string $needle, string $message): void {
    global $failures;
    if (strpos($haystack, $needle) === false) {
        echo "FAIL: $message\n";
        $failures++;
        return;
    }
    echo "PASS: $message\n";
}

$normalized = Select::normalizeOptions(['draft', 'published']);
if (count($normalized) !== 2 || $normalized[0]['value'] !== 'draft') {
    echo "FAIL: normalizeOptions flat strings\n";
    $failures++;
} else {
    echo "PASS: normalizeOptions flat strings\n";
}

$normalizedObjects = Select::normalizeOptions([
    ['label' => 'Draft', 'value' => 'draft'],
    ['label' => 'Published', 'value' => 'published'],
]);
if (count($normalizedObjects) !== 2) {
    echo "FAIL: normalizeOptions objects\n";
    $failures++;
} else {
    echo "PASS: normalizeOptions objects\n";
}

$select = new Select([
    'name' => 'status',
    'label' => 'Status',
    'value' => 'published',
    'options' => ['draft', 'published'],
]);

$html = $select->render();
assert_contains($html, 'value="published"', 'render includes published option');
assert_contains($html, 'selected', 'render marks selected value');

$errors = $select->validate('published');
if (!empty($errors)) {
    echo "FAIL: validate accepts normalized option value\n";
    $failures++;
} else {
    echo "PASS: validate accepts normalized option value\n";
}

if ($failures > 0) {
    echo "\nRESULT: FAIL ($failures)\n";
    exit(1);
}

echo "\nRESULT: PASS\n";
exit(0);
