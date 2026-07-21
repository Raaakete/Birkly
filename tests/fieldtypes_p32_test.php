<?php
/**
 * Phase 32 Pillar D fieldtype registration test.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Phase 32 fieldtype registry tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/src/bootstrap_fieldtypes.php';

use Birkly\FieldTypes\BaseField;
use Birkly\FieldTypes\FieldRegistry;

$expectedTypes = ['phone', 'toggle', 'markdown', 'oembed', 'code'];
$availableTypes = FieldRegistry::getAvailableTypes();

foreach ($expectedTypes as $type) {
    test_assert(
        "available types include {$type}",
        in_array($type, $availableTypes, true),
        'Available: ' . implode(', ', $availableTypes)
    );

    $class = FieldRegistry::getFieldType($type);
    test_assert(
        "registry resolves {$type} class",
        is_string($class) && $class !== '' && is_subclass_of($class, BaseField::class),
        'Resolved class: ' . var_export($class, true)
    );

    if (is_string($class) && $class !== '') {
        $field = FieldRegistry::createField($type, [
            'name' => $type,
            'label' => ucfirst($type),
        ]);
        test_assert(
            "createField builds {$type}",
            $field instanceof BaseField && $field->getType() === $type
        );
    }
}

exit(test_finish('Phase 32 fieldtype registry tests'));
