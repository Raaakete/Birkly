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

    // Register built-in field types
    public static function registerBuiltInFieldTypes(): void {
        self::register('text', '\HaruCMS\FieldTypes\Text\Text');
        self::register('textarea', '\HaruCMS\FieldTypes\Textarea\Textarea');
        self::register('number', '\HaruCMS\FieldTypes\Number\Number');
        self::register('date', '\HaruCMS\FieldTypes\Date\Date');
        self::register('datetime', '\HaruCMS\FieldTypes\DateTime\DateTime');
        self::register('checkbox', '\HaruCMS\FieldTypes\Checkbox\Checkbox');
        self::register('select', '\HaruCMS\FieldTypes\Select\Select');
        self::register('media', '\HaruCMS\FieldTypes\Media\Media');
        self::register('file', '\HaruCMS\FieldTypes\File\File');
        self::register('gallery', '\HaruCMS\FieldTypes\Gallery\Gallery');
        self::register('map', '\HaruCMS\FieldTypes\Map\Map');
        self::register('wysiwyg', '\HaruCMS\FieldTypes\Wysiwyg\Wysiwyg');
        self::register('dynamicitems', '\HaruCMS\FieldTypes\DynamicItems\DynamicItems');
        self::register('tags', '\HaruCMS\FieldTypes\Tags\Tags');
        self::register('editor', '\HaruCMS\FieldTypes\Editor\Editor');
    }
} 