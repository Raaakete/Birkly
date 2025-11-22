# Fieldtypes Architecture - Self-Contained Design

## Core Principle

**All fieldtypes must be fully self-contained. No reference or any related code shall be in the core system. All functionality must come only from the fieldtype folder itself, and the system needs to automatically recognize available fieldtypes.**

## Architecture Overview

### 1. Directory Structure

Each fieldtype must follow this structure:

```
fieldtypes/
├── BaseField.php          # Abstract base class (core system)
├── FieldRegistry.php      # Registry system (core system)
└── {FieldtypeName}/       # Each fieldtype in its own folder
    ├── {FieldtypeName}.php    # Main PHP class
    └── fieldtype.js           # JavaScript (optional but recommended)
```

**Example:**
```
fieldtypes/
├── Text/
│   ├── Text.php
│   └── fieldtype.js
├── Email/
│   ├── Email.php
│   └── fieldtype.js
└── MultiSelect/
    ├── MultiSelect.php
    └── fieldtype.js
```

### 2. Auto-Discovery Mechanism

The system automatically discovers fieldtypes by:

1. **Scanning the `fieldtypes/` directory** for subdirectories
2. **Checking for PHP files** matching the pattern `{DirectoryName}/{DirectoryName}.php`
3. **Loading and verifying** the class extends `BaseField`
4. **Registering** the fieldtype automatically

**No hardcoded fieldtype lists needed!**

### 3. Class Naming Convention

- **Directory name** = **Class name** (e.g., `Text/` → `Text` class)
- **Namespace**: `HaruCMS\FieldTypes\{FieldtypeName}\{FieldtypeName}`
- **Example**: `fieldtypes/Email/Email.php` → `HaruCMS\FieldTypes\Email\Email`

### 4. Auto-Discovery Implementation

#### In `src/bootstrap_fieldtypes.php`:
```php
// Scans fieldtypes directory and auto-registers all fieldtypes
foreach (glob($fieldtypesDir . '/*', GLOB_ONLYDIR) as $typeDir) {
    $type = basename($typeDir);
    $class = "HaruCMS\\FieldTypes\\$type\\$type";
    if (class_exists($class)) {
        FieldRegistry::register(strtolower($type), $class);
    }
}
```

#### In `api/admin_fieldtypes.php`:
```php
function getFieldtypeClass($fieldtypeName) {
    // Auto-discovers fieldtype by scanning directory
    // No hardcoded map - fully dynamic!
    $dirs = glob($fieldtypesDir . '/*', GLOB_ONLYDIR);
    // Finds matching directory and loads class
}
```

#### In `fieldtypes/FieldRegistry.php`:
```php
public static function autoRegisterFieldTypes(): void {
    // Scans directory and auto-registers all fieldtypes
    // No hardcoded list needed!
}
```

### 5. Fieldtype Requirements

Each fieldtype must:

1. **Extend BaseField**: `class Email extends BaseField`
2. **Implement all abstract methods**:
   - `getType()` - Returns fieldtype identifier
   - `render()` - Returns HTML
   - `validate($value)` - Validates input
   - `getJavaScript()` - Returns JavaScript code
   - `getCSS()` - Returns CSS code
   - `getAdminConfig()` - Returns admin configuration
   - `getSchema()` - Returns schema definition
   - `getDefaultValue()` - Returns default value
   - `processValue($value)` - Processes value before saving
   - `processLoadedValue($value)` - Processes value after loading

3. **Be self-contained**: All functionality in the fieldtype folder
4. **Follow naming convention**: Directory name = Class name

### 6. Adding a New Fieldtype

To add a new fieldtype:

1. **Create directory**: `fieldtypes/MyNewFieldtype/`
2. **Create PHP file**: `fieldtypes/MyNewFieldtype/MyNewFieldtype.php`
3. **Implement class**: Extend `BaseField` and implement all methods
4. **Add JavaScript** (optional): `fieldtypes/MyNewFieldtype/fieldtype.js`
5. **Done!** The system will automatically discover and register it

**No code changes needed in core system!**

### 7. Aliases (Minimal Edge Cases)

Only minimal aliases are allowed for edge cases:

```php
$aliases = [
    'tag' => 'tags',           // "tag" fieldtype uses "Tags" class
    'dynamic-items' => 'dynamicitems',  // Handle hyphenated names
];
```

These are convenience mappings, not hardcoded fieldtypes.

### 8. Benefits of This Architecture

✅ **Fully self-contained**: Each fieldtype is independent  
✅ **Auto-discovery**: No manual registration needed  
✅ **Easy to add**: Just create folder and files  
✅ **No core changes**: Adding fieldtypes doesn't require core system changes  
✅ **Maintainable**: Clear separation of concerns  
✅ **Extensible**: Third-party fieldtypes work the same way  

### 9. Example: Adding a New Fieldtype

```php
// fieldtypes/Phone/Phone.php
<?php
namespace HaruCMS\FieldTypes\Phone;

use HaruCMS\FieldTypes\BaseField;

class Phone extends BaseField {
    public function getType(): string {
        return 'phone';
    }
    
    public function render(): string {
        // Implementation
    }
    
    // ... implement all other methods
}
```

That's it! The system will automatically:
- Discover the `Phone/` directory
- Load `Phone.php`
- Register it as `phone` fieldtype
- Make it available throughout the system

### 10. Verification

To verify a fieldtype is properly self-contained:

- ✅ No references in core system files
- ✅ All code in fieldtype folder
- ✅ Auto-discovered by directory scan
- ✅ Works without manual registration
- ✅ Can be removed by deleting folder

## Summary

The fieldtype system is **fully self-contained and auto-discovering**. The core system only provides:
- `BaseField` abstract class (interface)
- `FieldRegistry` (registration mechanism)
- Auto-discovery code (scans directory)

Everything else comes from the individual fieldtype folders themselves!

