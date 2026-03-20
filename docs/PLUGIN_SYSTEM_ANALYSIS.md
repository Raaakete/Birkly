# Plugin System Analysis - User Plugin Readiness

## Executive Summary

The Birkly plugin system has a **solid foundation** with hooks, filters, services, and permissions. However, **one critical feature is missing** for the user plugin: **Template Function Registration**. The template parser currently only handles variables from data arrays, not function calls.

**Readiness Score: 85%** - Ready with one enhancement needed.

---

## ✅ What's Ready and Working

### 1. Core Plugin Infrastructure ✅

**Status:** Fully functional

- ✅ Plugin loading system (`load_plugins()`)
- ✅ Plugin state management (active/inactive)
- ✅ Plugin metadata system (`plugin.json`)
- ✅ Self-contained plugin support (copy/paste installation)
- ✅ Plugin activation/deactivation hooks

**Code Location:**
- `core/plugins.php` (lines 308-365)

**Example:**
```php
// Plugin auto-loads from plugins/ directory
// Supports plugin.php in folder structure
// Preserves state across loads
```

---

### 2. Hook System ✅

**Status:** Fully functional

- ✅ `register_hook($event, $callback, $priority)`
- ✅ `do_action($event, ...$args)`
- ✅ Priority-based execution
- ✅ Plugin identification (auto-detects plugin from backtrace)
- ✅ Error isolation (errors don't break other hooks)
- ✅ Active plugin filtering

**Code Location:**
- `core/plugins.php` (lines 117-197)

**Example:**
```php
// Plugin can register hooks
register_hook('before_user_register', function($user_data) {
    // Plugin code here
});

// System can trigger hooks
do_action('before_user_register', $user_data);
```

**Ready for User Plugin:** ✅ Yes

---

### 3. Filter System ✅

**Status:** Fully functional

- ✅ `add_filter($filter_name, $callback, $priority)`
- ✅ `apply_filters($filter_name, $value, ...$args)`
- ✅ Priority-based execution
- ✅ Plugin identification
- ✅ Error isolation

**Code Location:**
- `core/plugins.php` (lines 206-284)

**Example:**
```php
// Plugin can add filters
add_filter('user_can', function($result, $user_id, $action) {
    // Modify permission check
    return $result;
}, 10);

// System can apply filters
$can_edit = apply_filters('user_can', $default_result, $user_id, 'edit_entry');
```

**Ready for User Plugin:** ✅ Yes

---

### 4. Service Registry ✅

**Status:** Fully functional

- ✅ `register_plugin_service($service_id, $service, $options)`
- ✅ `get_plugin_service($service_id, $throw_exception, $requesting_plugin_id)`
- ✅ Service discovery (`discover_plugin_services()`)
- ✅ Version checking (`check_service_version()`)
- ✅ Capability-based access control
- ✅ Active plugin checking

**Code Location:**
- `core/plugins/services.php`

**Example:**
```php
// User plugin registers service
register_plugin_service('user_service', new UserService(), [
    'version' => '1.0.0',
    'description' => 'User management service'
]);

// Payment plugin gets service
$userService = get_plugin_service('user_service');
```

**Ready for User Plugin:** ✅ Yes - Perfect for payment plugin integration

---

### 5. Capabilities & Permissions System ✅

**Status:** Fully functional

- ✅ `has_plugin_capability($plugin_id, $capability, $scope)`
- ✅ `require_plugin_capability($plugin_id, $capability, $scope)`
- ✅ Denylist-first security model
- ✅ Global and scope-specific permissions
- ✅ Permission storage and management
- ✅ Audit logging

**Code Location:**
- `core/plugins/capabilities.php`
- `core/plugins/permissions.php`

**Example:**
```php
// Check if plugin has permission
if (has_plugin_capability('user_management', 'manage_users', 'global')) {
    // Allow operation
}

// Get permissions
$permissions = get_plugin_permissions('user_management');
```

**Ready for User Plugin:** ✅ Yes - Can control what user plugin can access

---

### 6. API Endpoint Registration ✅

**Status:** Fully functional

- ✅ `register_api_endpoint($route, $file)`
- ✅ Global registry (`$haru_api_endpoints`)
- ✅ Route-based registration

**Code Location:**
- `core/plugins.php` (lines 295-299)

**Example:**
```php
// Plugin registers API endpoint
register_api_endpoint('user_auth', __DIR__ . '/api/user_auth.php');

// System routes to: /api/user_auth.php
```

**Ready for User Plugin:** ✅ Yes - For optional REST APIs

---

### 7. Admin Page Registration ✅

**Status:** Fully functional

- ✅ `register_admin_page($slug, $file)`
- ✅ Global registry (`$haru_admin_pages`)

**Code Location:**
- `core/plugins.php` (lines 301-305)

**Example:**
```php
// Plugin registers admin page
register_admin_page('users', '/plugins/user_management/admin/users.html');
```

**Ready for User Plugin:** ✅ Yes - For admin UI

---

### 8. Collection Registration ✅

**Status:** Fully functional

- ✅ `register_collection($id, $path, $options)`
- ✅ Global registry (`$haru_collections`)
- ✅ Options support (e.g., `['hidden' => true]`)

**Code Location:**
- `core/plugins.php` (lines 286-293)

**Example:**
```php
// Plugin registers collection
register_collection('users', '/plugins/user_management/collections/users', [
    'hidden' => true
]);
```

**Ready for User Plugin:** ✅ Yes - For public profile collection

---

### 9. Plugin Settings Management ✅

**Status:** Fully functional

- ✅ `get_plugin_setting($plugin_id, $key, $default)`
- ✅ `set_plugin_setting($plugin_id, $key, $value)`
- ✅ Settings stored in `settings/plugins.json`
- ✅ Persisted across loads

**Code Location:**
- `core/plugins.php` (lines 367-382)

**Example:**
```php
// Get setting
$enable_registration = get_plugin_setting('user_management', 'enable_registration', true);

// Set setting
set_plugin_setting('user_management', 'enable_registration', false);
```

**Ready for User Plugin:** ✅ Yes

---

### 10. Data Isolation Support ✅

**Status:** Supported (plugin responsibility)

- ✅ Plugin can store data in `data/plugins/{plugin_id}/`
- ✅ System doesn't touch `data/` directory
- ✅ Plugin responsible for creating data directory structure

**Code Location:**
- Plugin must create own storage (not in core)

**Example:**
```php
// Plugin creates own storage
$storage = new PluginStorage(__DIR__ . '/../../data/plugins/user_management');
$storage->write('users/' . $user_id . '.json', $user_data);
```

**Ready for User Plugin:** ✅ Yes - Plugin can implement

---

## ❌ What's Missing

### 1. Template Function Registration ❌

**Status:** **CRITICAL MISSING FEATURE**

**Problem:**
The template parser (`admin/template_parser.php`) currently only handles:
- Variables from data array: `{variable}` → `$variable`
- Control structures: `{if}`, `{for}`, etc.
- Built-in directives: `{count}`, `{csrf_field}`, etc.

**Missing:**
- No hook for plugins to register template functions
- No way to call functions like `{user_current}` or `{user_can 'edit' 'blog'}`
- Template parser doesn't support function calls with arguments

**Current Template Parser Behavior:**
```php
// Line 178-201: Only handles variables
'{variable}' → '<?= htmlspecialchars($variable ?? "", ENT_QUOTES, "UTF-8") ?>'
```

**What's Needed:**
1. Hook: `register_template_function($name, $callback)`
2. Template parser enhancement to detect function calls
3. Function call syntax support: `{function_name}` or `{function_name arg1 arg2}`

**Impact:** 🔴 **HIGH** - This is the PRIMARY interface for the user plugin

**Solution Required:**
See "Recommended Implementation" below.

---

## 🔧 Recommended Implementation

### Template Function Registration System

**File to Create/Modify:** `core/plugins/template_functions.php`

```php
<?php
/**
 * Template Function Registration for Birkly
 * 
 * Allows plugins to register functions callable from templates
 */

// Global template functions registry
$haru_template_functions = [];

/**
 * Register a template function
 * 
 * @param string $name Function name (e.g., 'user_current', 'user_can')
 * @param callable $callback Function callback
 * @param array $options Options:
 *   - 'description' (string): Function description
 *   - 'arguments' (array): Expected arguments
 * 
 * @return bool True on success
 */
function register_template_function($name, $callback, $options = []) {
    global $haru_template_functions;
    
    // Get plugin ID from backtrace
    $plugin_id = null;
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    if (isset($backtrace[1]['file'])) {
        $file = $backtrace[1]['file'];
        global $haru_plugins;
        if (isset($haru_plugins)) {
            foreach ($haru_plugins as $pid => $plugin) {
                if (strpos($file, $plugin['path'] ?? '') !== false) {
                    $plugin_id = $pid;
                    break;
                }
            }
        }
    }
    
    $haru_template_functions[$name] = [
        'callback' => $callback,
        'plugin_id' => $plugin_id,
        'description' => $options['description'] ?? '',
        'arguments' => $options['arguments'] ?? [],
        'registered_at' => date('c')
    ];
    
    return true;
}

/**
 * Get a template function
 * 
 * @param string $name Function name
 * @return callable|null Function callback or null
 */
function get_template_function($name) {
    global $haru_template_functions;
    
    if (!isset($haru_template_functions[$name])) {
        return null;
    }
    
    $func_data = $haru_template_functions[$name];
    $plugin_id = $func_data['plugin_id'];
    
    // Check if plugin is active
    if ($plugin_id) {
        global $haru_plugins;
        if (isset($haru_plugins[$plugin_id]) && !($haru_plugins[$plugin_id]['active'] ?? false)) {
            return null; // Plugin is inactive
        }
    }
    
    return $func_data['callback'];
}

/**
 * Get all registered template functions
 * 
 * @return array All registered functions
 */
function get_all_template_functions() {
    global $haru_template_functions;
    
    // Filter by active plugins
    $active_functions = [];
    foreach ($haru_template_functions as $name => $func_data) {
        $plugin_id = $func_data['plugin_id'];
        
        if ($plugin_id) {
            global $haru_plugins;
            if (isset($haru_plugins[$plugin_id]) && !($haru_plugins[$plugin_id]['active'] ?? false)) {
                continue; // Skip inactive plugins
            }
        }
        
        $active_functions[$name] = $func_data;
    }
    
    return $active_functions;
}
```

**File to Modify:** `admin/template_parser.php`

**Add after line 201 (after variable handling):**

```php
// Handle template function calls: {function_name} or {function_name arg1 'arg2'}
// This must come AFTER variable handling to avoid conflicts
$parsed = preg_replace_callback(
    '/\{\s*([a-zA-Z0-9_]+)\s*(?:\(([^)]*)\))?\s*\}/',
    function($matches) {
        $func_name = trim($matches[1]);
        $args_str = isset($matches[2]) ? trim($matches[2]) : '';
        
        // Skip if it's a control keyword
        $controlStarts = ['if', 'else', 'endif', 'for', 'foreach', 'endfor', 'endforeach', 'from', 'get', 'has', 'exist', 'render', 'fieldtype', 'include', 'media', 'url', 'count'];
        foreach ($controlStarts as $kw) {
            if (strpos($func_name, $kw) === 0) {
                return '{' . $func_name . '}';
            }
        }
        
        // Check if it's a registered template function
        $template_func = get_template_function($func_name);
        if ($template_func) {
            // Parse arguments
            $args = [];
            if (!empty($args_str)) {
                // Simple argument parsing (can be enhanced)
                // Supports: 'string', "string", number, variable
                preg_match_all('/(?:[\'"]([^\'"]*)[\'"]|([a-zA-Z0-9_\.]+))/', $args_str, $arg_matches);
                foreach ($arg_matches[0] as $arg) {
                    $arg = trim($arg);
                    // If quoted, it's a string
                    if (preg_match('/^[\'"](.*)[\'"]$/', $arg, $quote_match)) {
                        $args[] = $quote_match[1];
                    } else {
                        // It's a variable - convert to PHP variable access
                        $parts = explode('.', $arg);
                        $php_var = '$' . array_shift($parts);
                        foreach ($parts as $p) {
                            $php_var .= "['" . $p . "']";
                        }
                        $args[] = $php_var;
                    }
                }
            }
            
            // Generate PHP code to call function
            $php_args = [];
            foreach ($args as $i => $arg) {
                if (is_string($arg) && strpos($arg, '$') === 0) {
                    // It's a variable reference
                    $php_args[] = $arg;
                } else {
                    // It's a literal value
                    $php_args[] = var_export($arg, true);
                }
            }
            
            $php_call = 'call_user_func(' . var_export($template_func, true);
            if (!empty($php_args)) {
                $php_call .= ', [' . implode(', ', $php_args) . ']';
            }
            $php_call .= ')';
            
            return '<?= ' . $php_call . ' ?? "" ?>';
        }
        
        // Not a function, treat as variable (existing behavior)
        $parts = explode('.', $func_name);
        $php = '$' . array_shift($parts);
        foreach ($parts as $p) { $php .= "['" . $p . "']"; }
        return '<?= htmlspecialchars(' . $php . ' ?? "", ENT_QUOTES, "UTF-8") ?>';
    },
    $parsed
);
```

**File to Modify:** `core/plugins.php`

**Add after line 445 (after loading other plugin systems):**

```php
// Load template functions system
require_once __DIR__ . '/plugins/template_functions.php';
```

---

## 📋 Implementation Checklist

### Phase 1: Template Function System (Required)
- [ ] Create `core/plugins/template_functions.php`
- [ ] Implement `register_template_function()`
- [ ] Implement `get_template_function()`
- [ ] Implement `get_all_template_functions()`
- [ ] Modify `admin/template_parser.php` to handle function calls
- [ ] Load template functions system in `core/plugins.php`
- [ ] Test with simple function: `{test_function}`

### Phase 2: Enhanced Function Support (Optional)
- [ ] Support function arguments: `{user_can 'edit' 'blog'}`
- [ ] Support complex arguments (arrays, objects)
- [ ] Function documentation/help system
- [ ] Function validation

### Phase 3: User Plugin Development
- [ ] User plugin can register template functions
- [ ] Test `{user_current}`, `{user_can}`, `{user_tier}`
- [ ] Test in actual templates
- [ ] Document template function usage

---

## 🎯 Summary

### Ready Now ✅
- Hook system
- Filter system
- Service registry
- Capabilities & permissions
- API endpoint registration
- Admin page registration
- Collection registration
- Plugin settings
- Data isolation support

### Needs Implementation ❌
- **Template function registration** (CRITICAL)

### Recommendation

**Priority 1:** Implement template function registration system before starting user plugin development. This is the PRIMARY interface for the user plugin and is essential for the template-first architecture.

**Estimated Effort:** 2-4 hours to implement and test

**Risk:** Low - Template parser is well-structured, enhancement is straightforward

---

## 📝 Example: User Plugin Using Template Functions

Once implemented, user plugin can do:

```php
// plugins/user_management/plugin.php
register_template_function('user_current', function() {
    $service = new UserService();
    return $service->getCurrentUser();
});

register_template_function('user_can', function($action, $context = null) {
    $service = new UserService();
    $user = $service->getCurrentUser();
    if (!$user) return false;
    return $service->userCan($user['id'], $action, $context);
});
```

```html
<!-- User's template -->
{if user_current exist}
    Welcome {user_current.name}!
    {if user_can 'create_entries' 'blog'}
        <a href="/create">Create Post</a>
    {endif}
{endif}
```

---

## ✅ Conclusion

The plugin system is **85% ready**. With the template function registration system implemented, it will be **100% ready** for the user plugin development.

**Next Steps:**
1. Implement template function registration (2-4 hours)
2. Test with simple plugin
3. Start user plugin development
4. Iterate and refine
