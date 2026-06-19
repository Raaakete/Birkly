<?php
// Always load the abstract base class and registry first
require_once __DIR__ . '/../fieldtypes/BaseField.php';
require_once __DIR__ . '/../fieldtypes/FieldRegistry.php';

use Birkly\FieldTypes\FieldRegistry;

// Custom autoloader for Birkly\FieldTypes\*
spl_autoload_register(function ($class) {
    if (strpos($class, 'Birkly\\FieldTypes\\') === 0) {
        $parts = explode('\\', $class);
        // e.g. Birkly\FieldTypes\Text\Text => fieldtypes/Text/Text.php
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
    $class = "Birkly\\FieldTypes\\$type\\$type";
    if (class_exists($class)) {
        FieldRegistry::register(strtolower($type), $class);
    }
}

// Legacy schema aliases (demo content and AI actions)
$legacyAliases = [
    'editor' => 'contenteditor',
    'wysiwyg' => 'contenteditor',
    'content-editor' => 'contenteditor',
    'media' => 'file',
];
foreach ($legacyAliases as $alias => $target) {
    $class = FieldRegistry::getFieldType($target);
    if ($class) {
        FieldRegistry::register($alias, $class);
    }
} 