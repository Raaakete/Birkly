<?php
namespace HaruCMS\FieldTypes;

class FieldRegistry {
    private static $fieldTypes = [];

    public static function register(string $type, string $class): void {
        if (!is_subclass_of($class, BaseField::class)) {
            throw new \InvalidArgumentException("Field type class must extend BaseField");
        }
        self::$fieldTypes[$type] = $class;
    }

    public static function getFieldType(string $type): ?string {
        return self::$fieldTypes[$type] ?? null;
    }

    public static function getAvailableTypes(): array {
        return array_keys(self::$fieldTypes);
    }

    public static function createField(string $type, array $config): BaseField {
        $class = self::getFieldType($type);
        if (!$class) {
            throw new \InvalidArgumentException("Unknown field type: {$type}");
        }
        return new $class($config);
    }

    public static function getFieldConfig(string $type): array {
        $class = self::getFieldType($type);
        if (!$class) {
            throw new \InvalidArgumentException("Unknown field type: {$type}");
        }
        return $class::getConfig();
    }

    /**
     * Auto-discover and register all fieldtypes from the fieldtypes directory
     * This method scans the directory and automatically registers all available fieldtypes
     * No hardcoded fieldtype list needed - fully self-contained!
     */
    public static function autoRegisterFieldTypes(): void {
        $fieldtypesDir = __DIR__;
        
        if (!is_dir($fieldtypesDir)) {
            return;
        }
        
        // Scan all subdirectories in fieldtypes folder
        $dirs = glob($fieldtypesDir . '/*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $fieldtypeName = basename($dir);
            
            // Skip special directories
            if (in_array($fieldtypeName, ['.', '..', 'BaseField.php'])) {
                continue;
            }
            
            // Check if PHP file exists (fieldtype must have {Name}/{Name}.php structure)
            $phpFile = $dir . '/' . $fieldtypeName . '.php';
            
            if (!file_exists($phpFile)) {
                continue;
            }
            
            // Construct class name
            $className = "HaruCMS\\FieldTypes\\{$fieldtypeName}\\{$fieldtypeName}";
            
            // Try to load the class
            if (!class_exists($className)) {
                require_once $phpFile;
            }
            
            // Verify class exists and extends BaseField
            if (class_exists($className) && is_subclass_of($className, BaseField::class)) {
                // Register with lowercase name for consistency
                self::register(strtolower($fieldtypeName), $className);
            }
        }
    }
    
    /**
     * @deprecated Use autoRegisterFieldTypes() instead. This method is kept for backward compatibility.
     */
    public static function registerBuiltInFieldTypes(): void {
        self::autoRegisterFieldTypes();
    }
} 