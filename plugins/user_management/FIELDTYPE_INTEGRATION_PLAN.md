# User Fieldtype Integration Plan

## Understanding & Alignment ✅

You're absolutely right. Let me confirm understanding:

### Core Requirements
1. **User fieldtype lives in plugin** - Not in `fieldtypes/` folder, fully self-contained
2. **Fieldtype Shelf needed** - Hook system for plugins to register fieldtypes
3. **Simple storage** - Just stores user IDs (string or array)
4. **Frontend handles relationships** - Plugin provides fieldtype + permission checks, frontend manages adding/removing users
5. **Auto-register/unregister** - Fieldtype appears when plugin activates, disappears when deactivated
6. **Flexible relationships** - "friends", "following", etc. are just field names using the same User fieldtype

### What User Fieldtype Does
- **Stores**: User IDs (single or multiple)
- **Displays**: User selector in admin, user list/names in frontend
- **Validates**: User IDs exist and are valid
- **Nothing more** - No relationship logic, no friend requests, just storage

### What Frontend Does
- Handles "Add Friend" button → Adds user ID to friend's user fieldtype field
- Handles "Follow" button → Adds user ID to following user fieldtype field  
- Handles "Unfriend" → Removes user ID from field
- All relationship management is frontend logic

### Permission Checks
- Template: `{user_in_field entry.friends}` - Check if current user is in field
- Template: `{user_can_view entry_id="post123"}` - Check visibility based on relationships

---

## Implementation Plan

### Phase 1: Create Fieldtype Shelf System

#### 1.1 Add Fieldtype Shelf to Core
**File**: `core/plugins/fieldtype_shelf.php` (new)

```php
<?php
/**
 * Fieldtype Shelf - Allows plugins to register fieldtypes
 * Part of the "Rooms and Shelves" architecture
 */

// Global registry for plugin-registered fieldtypes
$haru_plugin_fieldtypes = [];

/**
 * Register a fieldtype from a plugin (Place fieldtype on Fieldtype Shelf)
 * 
 * @param string $type Fieldtype identifier (e.g., 'user')
 * @param string $class Full class name (e.g., 'UserManagement\\Fieldtypes\\User')
 * @param string $plugin_id Plugin ID that owns this fieldtype
 * @return bool True on success
 */
function register_plugin_fieldtype($type, $class, $plugin_id) {
    global $haru_plugin_fieldtypes;
    
    // Validate
    if (empty($type) || empty($class) || empty($plugin_id)) {
        return false;
    }
    
    // Check if plugin is active
    global $haru_plugins;
    if (!isset($haru_plugins[$plugin_id]) || !($haru_plugins[$plugin_id]['active'] ?? false)) {
        error_log("Cannot register fieldtype '$type': Plugin '$plugin_id' is not active");
        return false;
    }
    
    // Verify class exists and extends BaseField
    if (!class_exists($class)) {
        error_log("Fieldtype class '$class' not found for type '$type'");
        return false;
    }
    
    if (!is_subclass_of($class, 'Birkly\\FieldTypes\\BaseField')) {
        error_log("Fieldtype class '$class' must extend BaseField");
        return false;
    }
    
    // Register on shelf
    $haru_plugin_fieldtypes[$type] = [
        'class' => $class,
        'plugin_id' => $plugin_id,
        'registered_at' => date('c')
    ];
    
    // Also register in FieldRegistry so it works with existing system
    if (class_exists('Birkly\\FieldTypes\\FieldRegistry')) {
        \Birkly\FieldTypes\FieldRegistry::register($type, $class);
    }
    
    return true;
}

/**
 * Unregister a plugin fieldtype (Remove from Fieldtype Shelf)
 * 
 * @param string $type Fieldtype identifier
 * @param string $plugin_id Plugin ID
 * @return bool True on success
 */
function unregister_plugin_fieldtype($type, $plugin_id) {
    global $haru_plugin_fieldtypes;
    
    if (!isset($haru_plugin_fieldtypes[$type])) {
        return false;
    }
    
    // Only unregister if owned by this plugin
    if ($haru_plugin_fieldtypes[$type]['plugin_id'] !== $plugin_id) {
        return false;
    }
    
    unset($haru_plugin_fieldtypes[$type]);
    
    // Note: We can't remove from FieldRegistry easily, but it won't be available
    // since the class won't be loaded when plugin is inactive
    
    return true;
}

/**
 * Get all plugin-registered fieldtypes
 * 
 * @return array Fieldtypes with metadata
 */
function get_plugin_fieldtypes() {
    global $haru_plugin_fieldtypes;
    
    // Filter by active plugins
    $active_fieldtypes = [];
    global $haru_plugins;
    
    foreach ($haru_plugin_fieldtypes as $type => $data) {
        $plugin_id = $data['plugin_id'];
        if (isset($haru_plugins[$plugin_id]) && ($haru_plugins[$plugin_id]['active'] ?? false)) {
            $active_fieldtypes[$type] = $data;
        }
    }
    
    return $active_fieldtypes;
}

/**
 * Check if a fieldtype is plugin-registered
 * 
 * @param string $type Fieldtype identifier
 * @return bool True if registered by a plugin
 */
function is_plugin_fieldtype($type) {
    global $haru_plugin_fieldtypes;
    return isset($haru_plugin_fieldtypes[$type]);
}
```

#### 1.2 Integrate with FieldRegistry
**File**: `fieldtypes/FieldRegistry.php` (modify)

Add method to check plugin fieldtypes:

```php
public static function getFieldType(string $type): ?string {
    // First check plugin-registered fieldtypes
    if (function_exists('is_plugin_fieldtype') && is_plugin_fieldtype($type)) {
        $plugin_fieldtypes = get_plugin_fieldtypes();
        if (isset($plugin_fieldtypes[$type])) {
            $class = $plugin_fieldtypes[$type]['class'];
            // Load class if needed
            if (!class_exists($class)) {
                // Class should be loaded by plugin, but try to require if path known
                return $class;
            }
            return $class;
        }
    }
    
    // Fall back to directory-discovered fieldtypes
    return self::$fieldTypes[$type] ?? null;
}
```

#### 1.3 Load Fieldtype Shelf in Core
**File**: `core/plugins.php` (modify)

Add:
```php
require_once __DIR__ . '/plugins/fieldtype_shelf.php';
```

---

### Phase 2: Create User Fieldtype in Plugin

#### 2.1 Directory Structure
```
plugins/user_management/
├── fieldtypes/
│   └── User/
│       ├── User.php
│       └── fieldtype.js
```

#### 2.2 User Fieldtype Class
**File**: `plugins/user_management/fieldtypes/User/User.php`

```php
<?php
namespace UserManagement\Fieldtypes;

use Birkly\FieldTypes\BaseField;

class User extends BaseField {
    protected $multiple = false;
    protected $allowRoles = []; // Filter by roles
    
    public function __construct(array $config) {
        parent::__construct($config);
        $this->multiple = $config['multiple'] ?? false;
        $this->allowRoles = $config['allow_roles'] ?? [];
    }
    
    public function getType(): string {
        return 'user';
    }
    
    public function render(): string {
        // Load users from user management plugin
        $users = $this->getAvailableUsers();
        $value = $this->value ?? ($this->multiple ? [] : '');
        
        $html = '<div class="user-fieldtype">';
        
        if ($this->multiple) {
            // Multi-select
            $html .= '<select name="' . htmlspecialchars($this->name) . '[]" multiple class="form-control" size="5">';
            foreach ($users as $user) {
                $selected = is_array($value) && in_array($user['id'], $value) ? 'selected' : '';
                $html .= '<option value="' . htmlspecialchars($user['id']) . '" ' . $selected . '>';
                $html .= htmlspecialchars($user['name'] . ' (' . $user['email'] . ')');
                $html .= '</option>';
            }
            $html .= '</select>';
        } else {
            // Single select
            $html .= '<select name="' . htmlspecialchars($this->name) . '" class="form-control">';
            $html .= '<option value="">-- Select User --</option>';
            foreach ($users as $user) {
                $selected = $value === $user['id'] ? 'selected' : '';
                $html .= '<option value="' . htmlspecialchars($user['id']) . '" ' . $selected . '>';
                $html .= htmlspecialchars($user['name'] . ' (' . $user['email'] . ')');
                $html .= '</option>';
            }
            $html .= '</select>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    public function validate($value): array {
        $errors = [];
        
        if ($this->required) {
            if ($this->multiple) {
                if (empty($value) || !is_array($value) || count($value) === 0) {
                    $errors[] = 'At least one user must be selected';
                }
            } else {
                if (empty($value)) {
                    $errors[] = 'A user must be selected';
                }
            }
        }
        
        // Validate user IDs exist
        $userIds = $this->multiple ? (is_array($value) ? $value : []) : [$value];
        foreach ($userIds as $userId) {
            if (!empty($userId) && !$this->userExists($userId)) {
                $errors[] = "User ID '$userId' does not exist";
            }
        }
        
        return $errors;
    }
    
    public function getJavaScript(): string {
        // Return JS for admin interface (search, autocomplete, etc.)
        return file_get_contents(__DIR__ . '/fieldtype.js');
    }
    
    public function getCSS(): string {
        return '.user-fieldtype { /* styles */ }';
    }
    
    public function getAdminConfig(): array {
        return [
            'multiple' => [
                'type' => 'boolean',
                'label' => 'Allow Multiple Users',
                'default' => false
            ],
            'allow_roles' => [
                'type' => 'multiselect',
                'label' => 'Filter by Roles',
                'options' => ['member', 'subscriber', 'premium', 'vip'],
                'default' => []
            ]
        ];
    }
    
    public function getSchema(): array {
        return [
            'type' => $this->multiple ? 'array' : 'string',
            'items' => $this->multiple ? ['type' => 'string'] : null
        ];
    }
    
    public function getDefaultValue() {
        return $this->multiple ? [] : '';
    }
    
    public function processValue($value) {
        // Store as array if multiple, string if single
        if ($this->multiple) {
            return is_array($value) ? $value : (empty($value) ? [] : [$value]);
        }
        return is_array($value) ? ($value[0] ?? '') : $value;
    }
    
    public function processLoadedValue($value) {
        // Ensure correct format when loading
        return $this->processValue($value);
    }
    
    // Helper methods
    protected function getAvailableUsers() {
        // Get users from user management plugin
        if (class_exists('UserService')) {
            $service = new \UserService();
            $users = $service->listUsers(['status' => 'active']);
            
            // Filter by roles if specified
            if (!empty($this->allowRoles)) {
                $users = array_filter($users, function($user) {
                    return in_array($user['role'] ?? '', $this->allowRoles);
                });
            }
            
            return $users;
        }
        
        return [];
    }
    
    protected function userExists($userId) {
        if (class_exists('UserService')) {
            $service = new \UserService();
            return $service->getUser($userId) !== null;
        }
        return false;
    }
}
```

#### 2.3 Register Fieldtype in Plugin
**File**: `plugins/user_management/plugin.php` (modify)

```php
// Register User fieldtype when plugin activates
if (function_exists('register_plugin_fieldtype')) {
    require_once __DIR__ . '/fieldtypes/User/User.php';
    
    register_plugin_fieldtype('user', 'UserManagement\\Fieldtypes\\User', 'user_management');
}

// Unregister when plugin deactivates
if (function_exists('register_hook')) {
    register_hook('plugin_deactivated', function($plugin_id) {
        if ($plugin_id === 'user_management' && function_exists('unregister_plugin_fieldtype')) {
            unregister_plugin_fieldtype('user', 'user_management');
        }
    });
}
```

---

### Phase 3: Template Functions for Permission Checks

#### 3.1 User in Field Check
**File**: `plugins/user_management/plugin.php` (add)

```php
// Check if current user is in a user fieldtype field
register_template_function('user_in_field', function($fieldValue) {
    $auth = new UserAuth();
    $currentUser = $auth->getCurrentUser();
    
    if (!$currentUser) {
        return false;
    }
    
    $userId = $currentUser['id'] ?? null;
    if (!$userId) {
        return false;
    }
    
    // Handle both single and multiple
    if (is_array($fieldValue)) {
        return in_array($userId, $fieldValue);
    }
    
    return $fieldValue === $userId;
}, [
    'description' => 'Check if current user is in a user fieldtype field',
    'arguments' => ['field_value']
]);
```

#### 3.2 User Field Helper
**File**: `plugins/user_management/plugin.php` (add)

```php
// Get user data from user fieldtype value
register_template_function('user_field', function($userIds) {
    if (empty($userIds)) {
        return [];
    }
    
    $ids = is_array($userIds) ? $userIds : [$userIds];
    $service = new UserService();
    $users = [];
    
    foreach ($ids as $id) {
        $user = $service->getUser($id);
        if ($user) {
            $users[] = $user;
        }
    }
    
    return $users;
}, [
    'description' => 'Get user data from user fieldtype value',
    'arguments' => ['user_ids']
]);
```

---

## Usage Examples

### Collection Schema
```json
{
  "collection": "profiles",
  "fields": [
    {
      "name": "friends",
      "type": "user",
      "label": "Friends",
      "multiple": true
    },
    {
      "name": "following",
      "type": "user",
      "label": "Following",
      "multiple": true
    }
  ]
}
```

### Template Usage
```html
<!-- Check if current user is friend -->
{if {user_in_field entry.friends}}
    <p>You are friends!</p>
{/if}

<!-- Display friends -->
{user_field entry.friends}
    <div>{user.name} - {user.email}</div>
{/user_field}

<!-- Check permission based on relationship -->
{if {user_in_field entry.author.friends}}
    <div class="private-content">Friends-only content</div>
{/if}
```

### Frontend Relationship Management
```javascript
// Add friend (frontend handles this)
function addFriend(userId) {
    fetch('/api/index.php?route=user_admin&action=update_entry', {
        method: 'POST',
        body: JSON.stringify({
            collection: 'profiles',
            entry_id: currentUserProfileId,
            friends: [...currentFriends, userId] // Add to array
        })
    });
}
```

---

## Concerns & Considerations

### ✅ Concerns Addressed
1. **Self-contained**: Fieldtype lives in plugin, disappears when deactivated
2. **Simple**: Just stores user IDs, no relationship logic
3. **Flexible**: Can be used for friends, following, blocked, any relationship
4. **Frontend-driven**: Frontend manages adding/removing users
5. **Permission checks**: Template functions for checking relationships

### ⚠️ Potential Issues
1. **Class loading**: Need to ensure User class is loaded when fieldtype is used
2. **Circular dependencies**: UserService needs to be available
3. **Performance**: Loading all users for dropdown might be slow (need pagination/search)
4. **Admin UI**: Need good user selector (search, autocomplete)

### 💡 Improvements
1. **User selector component**: Searchable, paginated user picker
2. **Caching**: Cache user list for performance
3. **Lazy loading**: Load users on demand in admin
4. **Validation**: Ensure user IDs are valid when saving

---

## Next Steps

1. ✅ Create Fieldtype Shelf system in core
2. ✅ Create User fieldtype in plugin
3. ✅ Register fieldtype on plugin activation
4. ✅ Add template functions for permission checks
5. ✅ Test with profile collection
6. ✅ Create admin UI for user selector (searchable)

Does this align with your vision? Any adjustments needed?
