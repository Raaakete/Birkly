<?php
// Always load the abstract base class and registry first
require_once __DIR__ . '/../fieldtypes/BaseField.php';
require_once __DIR__ . '/../fieldtypes/FieldRegistry.php';

use HaruCMS\FieldTypes\FieldRegistry;

// Custom autoloader for HaruCMS\FieldTypes\*
spl_autoload_register(function ($class) {
    if (strpos($class, 'HaruCMS\\FieldTypes\\') === 0) {
        $parts = explode('\\', $class);
        // e.g. HaruCMS\FieldTypes\Text\Text => fieldtypes/Text/Text.php
        $type = $parts[2] ?? null;
        $file = __DIR__ . "/../fieldtypes/{$type}/{$type}.php";
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Register all available field types
$fieldtypesDir = __DIR__ . '/../fieldtypes';
foreach (glob($fieldtypesDir . '/*', GLOB_ONLYDIR) as $typeDir) {
    $type = basename($typeDir);
    $class = "HaruCMS\\FieldTypes\\$type\\$type";
    if (class_exists($class)) {
        FieldRegistry::register(strtolower($type), $class);
    }
} 