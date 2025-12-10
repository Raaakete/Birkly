# Plugin System Architecture - Comprehensive Plan

## Table of Contents
1. [Overview](#overview)
2. [Conceptual Model: Rooms and Shelves](#conceptual-model-rooms-and-shelves)
3. [Core Principles](#core-principles)
4. [Use Cases & Scenarios](#use-cases--scenarios)
5. [System Architecture](#system-architecture)
6. [Capability & Permission System](#capability--permission-system)
7. [UI Injection System](#ui-injection-system)
8. [Plugin-to-Plugin Communication](#plugin-to-plugin-communication)
9. [Hook System Enhancement](#hook-system-enhancement)
10. [Security Model](#security-model)
11. [Hardening & Operational Safeguards](#hardening--operational-safeguards)
12. [Non-Breaking Invariants & Upgrade Safety](#non-breaking-invariants--upgrade-safety)
13. [Implementation Phases](#implementation-phases)
14. [Examples](#examples)
15. [Supply Chain, Marketplace & Operational Policies](#supply-chain-marketplace--operational-policies)

---

## Overview

The Plugin System is designed to be fully self-contained, allowing plugins to extend HaruCMS functionality while maintaining security, isolation, and clear boundaries. Plugins can:

- **Extend Core Functionality**: Add features to collections, entries, admin UI
- **Interact with Core**: Modify behavior through hooks and capabilities
- **Interact with Other Plugins**: Communicate and integrate with other plugins
- **Inject UI Components**: Add controls, settings, and interfaces to specific locations
- **Declare Capabilities**: Clearly state what they can do
- **Request Permissions**: Admin grants specific permissions

### Key Goals

1. **Self-Contained**: Plugins are isolated and don't break core functionality
2. **Secure**: Permission-based access control
3. **Flexible**: Support complex integrations
4. **User-Friendly**: Clear admin interface for managing plugins
5. **Extensible**: Easy to add new hooks and injection points

---

## Conceptual Model: Rooms and Shelves

The plugin system can be visualized as a building with three types of rooms:

### 🏛️ The Core Room (HaruCMS Core)

**Purpose:** The foundation and rule-maker of the entire system.

**Responsibilities:**
- **Provides Shelves**: Core exposes well-defined extension points (hooks, UI injection points, registries)
- **Enforces Rules**: Core always checks permissions and denylist before allowing access
- **Manages Access**: Controls who can access what through the door policy (capabilities + denylist)
- **Maintains Security**: Validates all inputs, sanitizes outputs, enforces boundaries

**Shelves Provided:**
- **UI Shelves**: Collection editor sidebar, entry editor tabs, settings panels, dashboard widgets, navigation
- **Data Shelves**: Content hooks (before/after save), collection hooks, entry hooks, validation hooks
- **Service Shelves**: Service registry for plugin-to-plugin communication
- **Operation Shelves**: Lifecycle hooks (activate/deactivate), migration hooks, scheduled tasks

**Door Policy:**
- Capability checks: Does the plugin have permission?
- Denylist checks: Is the resource protected? (always checked first)
- Scope checks: Is the permission valid for this collection/entry?

### 🚪 Plugin Rooms (Individual Plugins)

**Purpose:** Isolated spaces where each plugin lives and operates.

**Characteristics:**
- **Self-Contained**: Each plugin has its own code, assets, settings, data
- **Isolated**: Plugins cannot directly access other plugins or core internals
- **Declarative**: Plugins declare what they need (capabilities) and what they offer (services)
- **Controlled Access**: Plugins can only access shelves they're explicitly allowed to use

**What Plugins Do:**
1. **Declare Needs**: Request capabilities in `plugin.json`
2. **Place Items on Shelves**: Register hooks, UI components, services
3. **Offer Services**: Publish services to the lobby for other plugins
4. **Respect Boundaries**: Only access what they're permitted to access

**Example:**
- User Management Plugin has its own room
- Requests `manage_users` capability
- Places permission controls on the "collection editor sidebar" shelf
- Offers "user_service" to the lobby

### 🏢 The Lobby (Plugin Registry & Exchange)

**Purpose:** A controlled, shared space where plugins showcase and exchange functionality.

**Functions:**
1. **Service Registry**: Plugins publish services here; others can discover and use them
   - Service ID, version, provider plugin
   - Permission checks before access
   - Dependency resolution

2. **Event Bus**: Plugins broadcast events; others can subscribe
   - Event names, data payloads
   - Permission-based subscriptions
   - Core mediates all communication

3. **Shared UI Directory**: Central registry of plugin UI components
   - Navigation items
   - Dashboard widgets
   - Plugin-specific admin pages
   - All rendered by core according to permissions

**Rules:**
- Core controls all access (permission checks)
- Plugins cannot directly access each other
- All communication goes through core APIs
- Denylist applies here too (protected services/events)

**Example:**
- Payment Plugin publishes "payment_service" in the lobby
- E-Commerce Plugin discovers it, requests access
- Core checks permissions, grants access
- E-Commerce Plugin uses payment service through core APIs

### 📋 The Shelves (Extension Points)

**Categorized by Purpose:**

#### UI Shelves
- `collection_editor_sidebar` - Add controls to collection editor
- `entry_editor_tabs` - Add tabs to entry editor
- `entry_editor_sidebar` - Add widgets to entry sidebar
- `settings_page` - Inject into settings pages
- `dashboard_widgets` - Add dashboard widgets
- `admin_navigation` - Add navigation items

#### Data Shelves
- `before_entry_save` - Modify entry before saving
- `after_entry_save` - React to entry save
- `before_collection_save` - Modify collection before saving
- `entry_validation` - Add custom validation
- `entry_filter` - Filter entries in queries

#### Service Shelves
- `service_registry` - Register services for other plugins
- `service_discovery` - Discover available services

#### Operation Shelves
- `plugin_activated` - React to plugin activation
- `plugin_deactivated` - React to plugin deactivation
- `scheduled_task` - Register scheduled tasks
- `migration_hook` - Handle data migrations

### 🔒 Security Model

**Three-Layer Protection:**

1. **Denylist (Hard Barrier)**
   - Protected spaces/collections are completely off-limits
   - Checked FIRST, before any capability check
   - Cannot be overridden by permissions
   - Admin configurable in Global Security settings

2. **Capabilities (Permission System)**
   - Plugins declare what they need
   - Admin grants specific capabilities
   - Granular control (global, collection, entry level)
   - Audit trail of all grants

3. **Isolation (Sandboxing)**
   - Plugins run in isolated contexts
   - No direct access to core globals
   - All access through core APIs
   - Input validation and output sanitization

### 🔄 How It Works Together

**Example Flow: User Management Plugin**

1. **Plugin Activation:**
   - Plugin loads in its own room
   - Declares capabilities: `manage_users`, `modify_collection_permissions`
   - Admin grants permissions in Settings

2. **Placing on Shelves:**
   - Plugin registers UI component on `collection_editor_sidebar` shelf
   - Core checks: Does plugin have `modify_collection_permissions`? ✓
   - Core checks: Is collection on denylist? ✗ (not protected)
   - Component appears in collection editor

3. **Offering to Lobby:**
   - Plugin registers "user_service" in the lobby
   - Other plugins can discover it
   - Payment Plugin requests access
   - Core checks permissions, grants access
   - Payment Plugin uses service through core APIs

4. **Accessing Protected Data:**
   - Plugin tries to access "payroll" collection
   - Core checks denylist FIRST: "payroll" is protected ✗
   - Access denied immediately (no capability check needed)
   - Plugin receives permission denied error

### 🎯 Design Principles

**Robust:**
- Clear separation of concerns (rooms)
- Well-defined interfaces (shelves)
- Graceful error handling
- Dependency management

**Flexible:**
- Many extension points (shelves)
- Plugin-to-plugin communication (lobby)
- Custom capabilities
- Dynamic service discovery

**Simple:**
- Clear metaphor (rooms and shelves)
- Minimal APIs
- Convention over configuration
- Self-documenting structure

**Secure:**
- Three-layer protection (denylist, capabilities, isolation)
- Core always enforces rules
- No direct plugin-to-plugin access
- Audit trails

---

## Core Principles

### 1. Capability Declaration
Plugins must declare what they can do (capabilities) in `plugin.json`:
- `read_collections` - Can read collection data
- `modify_collections` - Can modify collection settings
- `read_entries` - Can read entry data
- `modify_entries` - Can modify entries
- `manage_users` - Can manage users
- `access_payment_api` - Can access payment functionality
- etc.

### 2. Permission Grants
Admin must explicitly grant permissions to plugins:
- Granular control per capability
- Per-collection permissions
- Per-plugin permissions
- Audit trail of permissions

### 3. UI Injection Points
Standardized hooks for injecting UI:
- Collection editor sidebar
- Entry editor tabs
- Settings pages
- Dashboard widgets
- Navigation items

### 4. Plugin Communication
Standardized APIs for plugin-to-plugin communication:
- Service discovery
- Event broadcasting
- Data sharing (with permissions)
- Dependency management

### 5. Security First
- Sandboxed execution
- Input validation
- Output sanitization
- CSRF protection
- Permission checks

---

## Use Cases & Scenarios

### Use Case 1: User Management Plugin

**Scenario:** Plugin manages user registration, tiers, and permissions

**Requirements:**
- Inject permission controls into collection editor
- Set who can create/edit entries per collection
- User tier system (free, premium, enterprise)
- Integration with payment plugin

**Capabilities Needed:**
- `manage_users` - Create, edit, delete users
- `read_users` - Read user data
- `modify_collection_permissions` - Set collection-level permissions
- `access_user_tiers` - Read user tier information

**UI Injections:**
- Collection Editor: Permission settings section
- Entry Editor: User assignment controls
- Settings: User management page
- Dashboard: User statistics

**Plugin Interactions:**
- Payment Plugin: Check if user has paid subscription
- Permission Plugin: Apply tier-based permissions

---

### Use Case 2: Payment Plugin

**Scenario:** Handle payments, subscriptions, and billing

**Requirements:**
- Process payments
- Manage subscriptions
- Integrate with user plugin for tier management
- Webhook handling

**Capabilities Needed:**
- `process_payments` - Handle payment processing
- `manage_subscriptions` - Create/update subscriptions
- `read_user_payments` - Access payment history
- `send_webhooks` - Trigger webhooks

**UI Injections:**
- User Profile: Payment history
- Settings: Payment configuration
- Dashboard: Revenue statistics

**Plugin Interactions:**
- User Plugin: Update user tier based on payment
- Email Plugin: Send payment receipts

---

### Use Case 3: E-Commerce Plugin

**Scenario:** Full e-commerce functionality

**Requirements:**
- Product management
- Shopping cart
- Order processing
- Inventory management

**Capabilities Needed:**
- `create_products` - Create product entries
- `process_orders` - Handle order processing
- `manage_inventory` - Track stock levels
- `access_payment_api` - Process payments (requires payment plugin)

**UI Injections:**
- Collection Editor: Product-specific fields
- Entry Editor: Inventory controls, pricing
- Admin: Order management page
- Dashboard: Sales statistics

**Plugin Interactions:**
- Payment Plugin: Process payments
- User Plugin: Customer accounts
- Email Plugin: Order confirmations

---

### Use Case 4: Analytics Plugin

**Scenario:** Track and analyze site usage

**Requirements:**
- Track page views
- User behavior analytics
- Custom event tracking
- Reporting dashboard

**Capabilities Needed:**
- `track_events` - Record analytics events
- `read_analytics_data` - Access analytics data
- `modify_tracking_code` - Inject tracking scripts

**UI Injections:**
- Dashboard: Analytics widgets
- Settings: Analytics configuration
- Entry Editor: View statistics per entry

**Plugin Interactions:**
- User Plugin: Track user-specific analytics
- All Plugins: Can track custom events

---

### Use Case 5: SEO Plugin

**Scenario:** SEO optimization and management

**Requirements:**
- Meta tag management
- Sitemap generation
- Schema markup
- SEO analysis

**Capabilities Needed:**
- `modify_entry_meta` - Add/edit meta tags
- `generate_sitemap` - Create sitemap files
- `inject_head_content` - Add content to <head>
- `read_entry_content` - Analyze content for SEO

**UI Injections:**
- Entry Editor: SEO meta fields
- Settings: SEO configuration
- Dashboard: SEO score widgets

**Plugin Interactions:**
- Analytics Plugin: Track SEO performance
- All Collections: Can add SEO fields

---

## System Architecture

### Plugin Structure

```
plugins/
  my_plugin/
    plugin.json          # Metadata, capabilities, dependencies
    plugin.php           # Main plugin file
    admin/               # Admin UI components
      settings.html
      settings.js
      components/
        permission-controls.html
        permission-controls.js
    api/                 # API endpoints
      my_api.php
    assets/              # Static assets
      css/
      js/
      images/
    hooks/               # Hook handlers
      collection_editor.php
      entry_editor.php
    services/            # Services for other plugins
      user_service.php
    README.md
```

### Plugin Metadata (`plugin.json`)

```json
{
  "name": "User Management",
  "id": "user_management",
  "version": "1.0.0",
  "description": "Complete user management system with tiers and permissions",
  "author": "Plugin Author",
  "requires": "1.0.0",
  "requires_php": "8.0",
  
  "capabilities": {
    "manage_users": {
      "label": "Manage Users",
      "description": "Create, edit, and delete users",
      "required": true,
      "scope": "global"
    },
    "read_users": {
      "label": "Read User Data",
      "description": "Access user information",
      "required": true,
      "scope": "global"
    },
    "modify_collection_permissions": {
      "label": "Modify Collection Permissions",
      "description": "Set who can create/edit entries in collections",
      "required": false,
      "scope": "collection"
    },
    "access_user_tiers": {
      "label": "Access User Tiers",
      "description": "Read and modify user tier information",
      "required": true,
      "scope": "global"
    }
  },
  
  "dependencies": {
    "plugins": [],
    "php_extensions": ["pdo", "json"]
  },
  
  "provides": {
    "services": [
      {
        "id": "user_service",
        "name": "User Service",
        "description": "User management API",
        "version": "1.0.0"
      }
    ],
    "hooks": [
      "user_created",
      "user_updated",
      "user_deleted"
    ]
  },
  
  "ui_injections": [
    {
      "hook": "collection_editor_sidebar",
      "component": "admin/components/permission-controls.html",
      "script": "admin/components/permission-controls.js",
      "position": "after_settings",
      "required_capability": "modify_collection_permissions"
    },
    {
      "hook": "entry_editor_tabs",
      "component": "admin/components/user-assignment.html",
      "script": "admin/components/user-assignment.js",
      "position": "after_content",
      "required_capability": "read_users"
    }
  ],
  
  "settings_schema": {
    "enable_registration": {
      "type": "boolean",
      "default": true,
      "label": "Enable Registration",
      "description": "Allow new users to register"
    },
    "default_tier": {
      "type": "select",
      "default": "free",
      "options": ["free", "premium", "enterprise"],
      "label": "Default User Tier"
    }
  },
  
  "api_endpoints": [
    {
      "route": "user_management",
      "file": "api/user_management.php",
      "capabilities_required": ["manage_users"]
    }
  ],
  
  "admin_pages": [
    {
      "slug": "users",
      "file": "admin/users.html",
      "capabilities_required": ["manage_users"],
      "navigation": {
        "name": "Users",
        "icon": "users",
        "position": "after_content"
      }
    }
  ]
}
```

### Mapping Conceptual Model to Implementation

**How "Rooms and Shelves" Translate to Code:**

#### The Core Room → Core System Files
- **Location**: `core/plugins.php`, `core/admin.php`, `api/`
- **Shelves**: Hook system (`register_hook`, `do_action`), UI injection system (`register_ui_injection`), Service registry (`register_plugin_service`)
- **Door Policy**: Permission checking functions (`has_plugin_capability`, `is_resource_protected`)

#### Plugin Rooms → Plugin Directories
- **Location**: `plugins/{plugin_id}/`
- **Isolation**: Each plugin has its own directory with `plugin.php`, `admin/`, `api/`, `assets/`
- **Declaration**: `plugin.json` declares capabilities, services, UI injections
- **Access Control**: All access through core APIs, no direct file access

#### The Lobby → Global Registries
- **Service Registry**: `$haru_plugin_services` (global array)
- **Event Bus**: `$haru_hooks` (global array with event names)
- **UI Directory**: `$haru_ui_injections` (global array)
- **Navigation**: `$haru_admin_pages` (global array)

#### Shelves → Hook/Injection Points
- **UI Shelves**: Template hooks like `<?php echo render_ui_injections('collection_editor_sidebar'); ?>`
- **Data Shelves**: Action hooks like `do_action('before_entry_save', $entry_data);`
- **Service Shelves**: Service registration like `register_plugin_service('user_management', 'user_service', 'UserService');`
- **Operation Shelves**: Lifecycle hooks like `do_action('plugin_activated', $plugin_id);`

**Example: Plugin Placing Item on Shelf**

```php
// In plugin.php (Plugin Room)
register_ui_injection('user_management', 'collection_editor_sidebar',
  __DIR__ . '/admin/components/permission-controls.html',
  [
    'script' => __DIR__ . '/admin/components/permission-controls.js',
    'position' => 'after_settings',
    'required_capability' => 'modify_collection_permissions'
  ]
);

// Core checks (Door Policy):
// 1. Is collection on denylist? → Check denylist FIRST
// 2. Does plugin have capability? → Check permissions
// 3. If both pass → Add to shelf (register in $haru_ui_injections)

// In collection-editor.html (Core Room)
<?php 
// Core renders shelf contents
echo render_ui_injections('collection_editor_sidebar', [
  'collection' => $collection,
  'schema' => $schema
]);
?>
```

**Example: Plugin Offering Service to Lobby**

```php
// In user_management/plugin.php (Plugin Room)
register_plugin_service('user_management', 'user_service', 'UserService');

// Service is now in the Lobby ($haru_plugin_services)

// In payment_plugin/plugin.php (Another Plugin Room)
try {
  $user_service = get_plugin_service('user_service');
  // Core checks:
  // 1. Is service available? (is plugin active?)
  // 2. Does requesting plugin have permission?
  // 3. If both pass → Return service instance
} catch (ServiceNotFoundException $e) {
  // Service not available
}
```

---

## Capability & Permission System

### Hard Denylist (Spaces & Collections)
- **Purpose**: Absolute security guardrail. Even if a plugin is granted capabilities, it cannot access items on the denylist.
- **Scopes**:
  - **Spaces** (future multi-space support): e.g., `finance-space`, `legal-space`
  - **Collections**: e.g., `payroll`, `hr_files`, `legal`
- **Behavior**:
  - Denylist is checked first; if matched, access is blocked regardless of permissions.
  - Applies to read, write, hooks, UI injections, and service calls.
- **Storage**: Extend `settings/plugin_permissions.json` with `deny_spaces` and `deny_collections`.
- **UI**: Settings → Plugins → Global Security: multi-select for protected spaces/collections, with warning that denylist overrides all plugin grants.
- **Audit**: Changes to denylist are logged with user, timestamp, and diff.

### Capability Types

#### 1. Global Capabilities
Apply to entire system:
- `manage_users` - User management
- `manage_plugins` - Plugin management
- `access_settings` - System settings
- `view_analytics` - Analytics access

#### 2. Collection Capabilities
Apply to specific collections:
- `read_collection:{slug}` - Read collection data
- `modify_collection:{slug}` - Modify collection
- `create_entries:{slug}` - Create entries
- `edit_entries:{slug}` - Edit entries
- `delete_entries:{slug}` - Delete entries

#### 3. Entry Capabilities
Apply to specific entries:
- `read_entry:{id}` - Read entry
- `edit_entry:{id}` - Edit entry
- `delete_entry:{id}` - Delete entry

### Permission Storage

**File:** `settings/plugin_permissions.json` (per-plugin permissions)

```json
{
  "user_management": {
    "granted": [
      "manage_users",
      "read_users",
      "access_user_tiers"
    ],
    "denied": [],
    "collection_permissions": {
      "blog": {
        "modify_collection_permissions": true
      }
    },
    "granted_by": "admin_user_123",
    "granted_at": "2024-01-15T10:30:00Z"
  },
  "payment_plugin": {
    "granted": [
      "process_payments",
      "manage_subscriptions"
    ],
    "collection_permissions": {},
    "granted_by": "admin_user_123",
    "granted_at": "2024-01-15T10:30:00Z"
  }
}
```

**File:** `settings/plugin_denylist.json` (global denylist - applies to ALL plugins)

```json
{
  "spaces": [
    "finance-space",
    "legal-space"
  ],
  "collections": [
    "payroll",
    "hr_files",
    "legal_documents"
  ],
  "updated_by": "admin_user_123",
  "updated_at": "2024-01-15T10:30:00Z",
  "audit_log": [
    {
      "action": "add_collection",
      "collection": "payroll",
      "by": "admin_user_123",
      "at": "2024-01-15T10:30:00Z"
    }
  ]
}
```

**Important:** The denylist is checked BEFORE plugin permissions. If a collection or space is on the denylist, no plugin can access it, regardless of granted capabilities.

### Permission Checking

**Denylist-First Approach:** The denylist is always checked FIRST, before any capability check. This ensures protected resources are completely inaccessible, regardless of permissions.

```php
// Check if plugin has capability (denylist checked first)
function has_plugin_capability($plugin_id, $capability, $context = null) {
  $permissions = load_plugin_permissions();
  $denylist = load_denylist();
  
  // STEP 1: Check denylist FIRST (hard barrier - cannot be overridden)
  if ($context) {
    // Check if collection is on denylist
    if (in_array($context, $denylist['collections'] ?? [])) {
      return false; // Denied immediately, no capability check needed
    }
    
    // Check if space is on denylist (if context includes space)
    if (isset($context['space']) && in_array($context['space'], $denylist['spaces'] ?? [])) {
      return false; // Denied immediately
    }
  }
  
  // STEP 2: Check if explicitly denied (plugin-specific denial)
  if (in_array($capability, $permissions[$plugin_id]['denied'] ?? [])) {
    return false;
  }
  
  // STEP 3: Check if granted globally
  if (in_array($capability, $permissions[$plugin_id]['granted'] ?? [])) {
    return true;
  }
  
  // STEP 4: Check collection-specific permissions
  if ($context && isset($permissions[$plugin_id]['collection_permissions'][$context])) {
    $collection_perms = $permissions[$plugin_id]['collection_permissions'][$context];
    if (isset($collection_perms[$capability]) && $collection_perms[$capability]) {
      return true;
    }
  }
  
  return false;
}

// Load denylist (protected spaces/collections)
function load_denylist() {
  $storage = new PluginStorage(__DIR__ . '/../settings');
  $denylist = $storage->read('plugin_denylist.json');
  
  return [
    'spaces' => $denylist['spaces'] ?? [],
    'collections' => $denylist['collections'] ?? []
  ];
}

// Check if resource is protected (denylist check)
function is_resource_protected($type, $identifier) {
  $denylist = load_denylist();
  
  if ($type === 'collection') {
    return in_array($identifier, $denylist['collections'] ?? []);
  }
  
  if ($type === 'space') {
    return in_array($identifier, $denylist['spaces'] ?? []);
  }
  
  return false;
}

// Request capability (throws exception if not granted)
function require_plugin_capability($plugin_id, $capability, $context = null) {
  if (!has_plugin_capability($plugin_id, $capability, $context)) {
    throw new PermissionDeniedException(
      "Plugin '{$plugin_id}' does not have capability '{$capability}'"
    );
  }
}
```

### Admin Permission Management UI

**Location:** Settings > Plugins > [Plugin Name] > Permissions

**Features:**
- List all declared capabilities
- Grant/deny checkboxes
- Collection-specific permissions
- Permission audit log
- Bulk operations
- Protected areas (read-only): show denylisted spaces/collections with note “Plugins cannot access these, even if granted.”

**UI:**
```
┌─────────────────────────────────────┐
│ User Management - Permissions       │
├─────────────────────────────────────┤
│ Global Permissions                 │
│                                     │
│ ☑ Manage Users                     │
│ ☑ Read User Data                   │
│ ☑ Access User Tiers                │
│ ☐ Modify Collection Permissions    │
│                                     │
│ Protected (denylist)               │
│   Spaces: [finance-space]          │
│   Collections: [payroll, legal]    │
│   (Access blocked for all plugins) │
│                                     │
│ Collection-Specific Permissions     │
│                                     │
│ Blog Collection                    │
│   ☑ Modify Collection Permissions │
│                                     │
│ Products Collection                │
│   ☐ Modify Collection Permissions │
│                                     │
│ [Save Permissions]                 │
└─────────────────────────────────────┘
```

---

## UI Injection System

### Injection Points (Hooks)

#### 1. Collection Editor

**Hook:** `collection_editor_sidebar`
- **Location:** Right sidebar in collection editor
- **Positions:** `before_settings`, `after_settings`, `before_automation`, `after_automation`
- **Context:** Collection data, schema

**Hook:** `collection_editor_fields`
- **Location:** Fields section
- **Positions:** `before_fields`, `after_fields`, `before_field_config`
- **Context:** Field data, schema

**Hook:** `collection_editor_tabs`
- **Location:** Tab navigation
- **Positions:** `before_tabs`, `after_tabs`
- **Context:** Collection data

#### 2. Entry Editor

**Hook:** `entry_editor_tabs`
- **Location:** Tab navigation
- **Positions:** `after_content`, `before_settings`
- **Context:** Entry data, collection schema

**Hook:** `entry_editor_sidebar`
- **Location:** Right sidebar
- **Positions:** `before_meta`, `after_meta`
- **Context:** Entry data

**Hook:** `entry_editor_fields`
- **Location:** Fields section
- **Positions:** `before_fields`, `after_fields`
- **Context:** Entry data, field data

#### 3. Settings Pages

**Hook:** `settings_page`
- **Location:** Settings pages
- **Positions:** `before_content`, `after_content`
- **Context:** Settings data

#### 4. Dashboard

**Hook:** `dashboard_widgets`
- **Location:** Dashboard
- **Positions:** `top`, `middle`, `bottom`
- **Context:** Dashboard data

#### 5. Navigation

**Hook:** `admin_navigation`
- **Location:** Admin sidebar
- **Positions:** `before_content`, `after_content`, `before_settings`
- **Context:** Current user, permissions

### UI Component System

**Component Structure:**
```html
<!-- admin/components/permission-controls.html -->
<div class="plugin-ui-injection" data-plugin="user_management" data-hook="collection_editor_sidebar">
  <div class="card">
    <h3>User Permissions</h3>
    <div class="permission-controls">
      <!-- Component content -->
    </div>
  </div>
</div>
```

**JavaScript:**
```javascript
// admin/components/permission-controls.js
(function() {
  'use strict';
  
  // Component initialization
  HaruPluginUI.register('user_management', 'collection_editor_sidebar', {
    init: function(context) {
      // context.collection - Collection data
      // context.schema - Collection schema
      this.loadPermissions(context.collection.slug);
    },
    
    loadPermissions: function(collectionSlug) {
      // Load and display permissions
    },
    
    savePermissions: function(collectionSlug, permissions) {
      // Save permissions via API
    }
  });
})();
```

### Injection API

```php
// Register UI injection
function register_ui_injection($plugin_id, $hook, $component_path, $options = []) {
  global $haru_ui_injections;
  
  // Verify plugin has required capability
  $required_capability = $options['required_capability'] ?? null;
  if ($required_capability && !has_plugin_capability($plugin_id, $required_capability)) {
    return false;
  }
  
  $haru_ui_injections[$hook][] = [
    'plugin_id' => $plugin_id,
    'component' => $component_path,
    'script' => $options['script'] ?? null,
    'position' => $options['position'] ?? 'after',
    'priority' => $options['priority'] ?? 10,
    'required_capability' => $required_capability
  ];
  
  return true;
}

// Render UI injections
function render_ui_injections($hook, $context = []) {
  global $haru_ui_injections;
  
  if (!isset($haru_ui_injections[$hook])) {
    return '';
  }
  
  // Sort by priority
  usort($haru_ui_injections[$hook], function($a, $b) {
    return ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10);
  });
  
  $output = '';
  foreach ($haru_ui_injections[$hook] as $injection) {
    // Check capability
    if ($injection['required_capability'] && 
        !has_plugin_capability($injection['plugin_id'], $injection['required_capability'])) {
      continue;
    }
    
    // Load component
    $component_path = $injection['component'];
    if (file_exists($component_path)) {
      $output .= render_component($component_path, $context);
    }
    
    // Load script
    if ($injection['script'] && file_exists($injection['script'])) {
      $output .= '<script src="' . htmlspecialchars($injection['script']) . '"></script>';
    }
  }
  
  return $output;
}
```

### Example: Permission Controls in Collection Editor

**Plugin Code:**
```php
// In plugin.php
register_ui_injection('user_management', 'collection_editor_sidebar', 
  __DIR__ . '/admin/components/permission-controls.html',
  [
    'script' => __DIR__ . '/admin/components/permission-controls.js',
    'position' => 'after_settings',
    'priority' => 20,
    'required_capability' => 'modify_collection_permissions'
  ]
);
```

**Template Integration:**
```php
<!-- In collection-editor.html -->
<div class="sidebar-tabs">
  <!-- Existing tabs -->
  
  <?php 
  // Render plugin UI injections
  echo render_ui_injections('collection_editor_sidebar', [
    'collection' => $collection,
    'schema' => $schema
  ]);
  ?>
</div>
```

---

## Plugin-to-Plugin Communication

### Service Discovery

**Service Registration:**
```php
// Plugin provides a service
function register_plugin_service($plugin_id, $service_id, $service_class) {
  global $haru_plugin_services;
  
  $haru_plugin_services[$service_id] = [
    'plugin_id' => $plugin_id,
    'class' => $service_class,
    'version' => get_plugin_version($plugin_id)
  ];
}

// Get service from another plugin
function get_plugin_service($service_id) {
  global $haru_plugin_services;
  
  if (!isset($haru_plugin_services[$service_id])) {
    throw new ServiceNotFoundException("Service '{$service_id}' not found");
  }
  
  $service_info = $haru_plugin_services[$service_id];
  
  // Check if providing plugin is active
  if (!get_plugin_state($service_info['plugin_id'])) {
    throw new ServiceUnavailableException("Service '{$service_id}' plugin is inactive");
  }
  
  // Check if requesting plugin has permission
  $requesting_plugin = get_calling_plugin();
  if ($requesting_plugin && !has_plugin_permission($requesting_plugin, $service_info['plugin_id'], 'use_service')) {
    throw new PermissionDeniedException("Plugin '{$requesting_plugin}' cannot use service '{$service_id}'");
  }
  
  return new $service_info['class']();
}
```

### Event Broadcasting

**Broadcast Event:**
```php
// Plugin broadcasts event
function broadcast_plugin_event($plugin_id, $event_name, $data = []) {
  // Check capability
  require_plugin_capability($plugin_id, 'broadcast_events');
  
  // Trigger hook for other plugins
  do_action("plugin_event_{$event_name}", $plugin_id, $data);
}

// Listen to plugin events
register_hook('plugin_event_user_created', function($source_plugin, $data) {
  // Handle user created event from user plugin
  if ($source_plugin === 'user_management') {
    // Send welcome email, etc.
  }
});
```

### Data Sharing

**Shared Data Storage:**
```php
// Store shared data
function set_plugin_shared_data($plugin_id, $key, $value, $readable_by = []) {
  // Store in plugin-specific storage
  $storage = new PluginStorage("plugins/{$plugin_id}/shared");
  $storage->write("{$key}.json", [
    'value' => $value,
    'readable_by' => $readable_by,
    'created_at' => date('c')
  ]);
}

// Read shared data (with permission check)
function get_plugin_shared_data($source_plugin, $key, $requesting_plugin = null) {
  $storage = new PluginStorage("plugins/{$source_plugin}/shared");
  $data = $storage->read("{$key}.json");
  
  if (!$data) {
    return null;
  }
  
  // Check if requesting plugin has permission
  if ($requesting_plugin && !in_array($requesting_plugin, $data['readable_by'])) {
    throw new PermissionDeniedException("Plugin '{$requesting_plugin}' cannot read '{$key}' from '{$source_plugin}'");
  }
  
  return $data['value'];
}
```

### Dependency Management

**Dependency Declaration:**
```json
{
  "dependencies": {
    "plugins": [
      {
        "id": "payment_plugin",
        "version": ">=1.0.0",
        "required": true
      },
      {
        "id": "email_plugin",
        "version": ">=0.5.0",
        "required": false
      }
    ],
    "services": [
      {
        "id": "user_service",
        "provided_by": "user_management",
        "version": ">=1.0.0"
      }
    ]
  }
}
```

**Dependency Checking:**
```php
function check_plugin_dependencies($plugin_id) {
  $plugin = get_plugin_metadata($plugin_id);
  $dependencies = $plugin['dependencies'] ?? [];
  $errors = [];
  
  // Check plugin dependencies
  foreach ($dependencies['plugins'] ?? [] as $dep) {
    $dep_plugin = get_plugin_metadata($dep['id']);
    if (!$dep_plugin) {
      $errors[] = "Required plugin '{$dep['id']}' not found";
      continue;
    }
    
    if (!version_compare($dep_plugin['version'], $dep['version'], '>=')) {
      $errors[] = "Plugin '{$dep['id']}' version mismatch. Required: {$dep['version']}, Found: {$dep_plugin['version']}";
    }
    
    if (!get_plugin_state($dep['id'])) {
      $errors[] = "Required plugin '{$dep['id']}' is not active";
    }
  }
  
  // Check service dependencies
  foreach ($dependencies['services'] ?? [] as $service) {
    if (!service_exists($service['id'])) {
      $errors[] = "Required service '{$service['id']}' not available";
    }
  }
  
  return $errors;
}
```

---

## Hook System Enhancement

### Enhanced Hook Registration

```php
// Register hook with priority and capability check
function register_hook($event, $callback, $options = []) {
  global $haru_hooks;
  
  $priority = $options['priority'] ?? 10;
  $required_capability = $options['required_capability'] ?? null;
  $plugin_id = $options['plugin_id'] ?? get_calling_plugin();
  
  // Check capability if specified
  if ($required_capability && $plugin_id) {
    if (!has_plugin_capability($plugin_id, $required_capability)) {
      return false;
    }
  }
  
  if (!isset($haru_hooks[$event])) {
    $haru_hooks[$event] = [];
  }
  
  $haru_hooks[$event][] = [
    'callback' => $callback,
    'plugin_id' => $plugin_id,
    'priority' => $priority,
    'required_capability' => $required_capability
  ];
  
  // Sort by priority
  usort($haru_hooks[$event], function($a, $b) {
    return $a['priority'] <=> $b['priority'];
  });
  
  return true;
}
```

### Filter Hooks

**Filter System (modify data):**
```php
// Register filter
function add_filter($filter_name, $callback, $priority = 10) {
  register_hook("filter_{$filter_name}", $callback, ['priority' => $priority]);
}

// Apply filter
function apply_filters($filter_name, $value, ...$args) {
  $filtered_value = $value;
  
  do_action("filter_{$filter_name}", $filtered_value, ...$args);
  
  return $filtered_value;
}

// Usage
$entry_title = apply_filters('entry_title', $entry['title'], $entry);
```

### Action Hooks

**Action System (do something):**
```php
// Register action
function add_action($action_name, $callback, $priority = 10) {
  register_hook("action_{$action_name}", $callback, ['priority' => $priority]);
}

// Trigger action
function do_action($action_name, ...$args) {
  // Existing implementation
}
```

### Hook Context

**Pass context to hooks:**
```php
function do_action_with_context($event, $context = [], ...$args) {
  global $haru_hooks;
  
  // Merge context into args
  $args_with_context = array_merge([$context], $args);
  
  if (isset($haru_hooks[$event])) {
    foreach ($haru_hooks[$event] as $hook_data) {
      $callback = $hook_data['callback'];
      $plugin_id = $hook_data['plugin_id'];
      
      // Check if plugin is active
      if ($plugin_id && !get_plugin_state($plugin_id)) {
        continue;
      }
      
      // Check capability
      if ($hook_data['required_capability'] && $plugin_id) {
        if (!has_plugin_capability($plugin_id, $hook_data['required_capability'], $context)) {
          continue;
        }
      }
      
      call_user_func_array($callback, $args_with_context);
    }
  }
}
```

---

## Security Model

### Sandboxing

**Isolated Execution:**
- Plugins run in separate namespace
- No direct access to core globals
- All access through APIs

### Input Validation

**Validate all inputs:**
```php
function validate_plugin_input($data, $schema) {
  $errors = [];
  
  foreach ($schema as $field => $rules) {
    $value = $data[$field] ?? null;
    
    // Required check
    if (!empty($rules['required']) && empty($value)) {
      $errors[$field] = "Field '{$field}' is required";
      continue;
    }
    
    // Type validation
    if (isset($rules['type'])) {
      $type = gettype($value);
      if ($type !== $rules['type']) {
        $errors[$field] = "Field '{$field}' must be of type '{$rules['type']}'";
      }
    }
    
    // Sanitization
    if (isset($rules['sanitize'])) {
      $data[$field] = call_user_func($rules['sanitize'], $value);
    }
  }
  
  return ['valid' => empty($errors), 'errors' => $errors, 'data' => $data];
}
```

### Output Sanitization

**Sanitize all outputs:**
```php
function sanitize_plugin_output($output, $type = 'html') {
  switch ($type) {
    case 'html':
      return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
    case 'json':
      return json_encode($output, JSON_UNESCAPED_SLASHES);
    case 'url':
      return urlencode($output);
    default:
      return $output;
  }
}
```

### CSRF Protection

**All plugin API endpoints require CSRF:**
```php
function verify_plugin_csrf($token) {
  return validate_csrf_token($token);
}

// In plugin API
if (!verify_plugin_csrf($_POST['_csrf'] ?? '')) {
  http_response_code(403);
  echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
  exit;
}
```

### Permission Checks

**Check permissions before operations:**
```php
function plugin_api_handler($plugin_id, $action, $data) {
  // Check if plugin is active
  if (!get_plugin_state($plugin_id)) {
    throw new PluginInactiveException("Plugin '{$plugin_id}' is not active");
  }
  
  // Check required capability
  $required_capability = get_plugin_action_capability($plugin_id, $action);
  if ($required_capability) {
    require_plugin_capability($plugin_id, $required_capability, $data['context'] ?? null);
  }
  
  // Execute action
  return execute_plugin_action($plugin_id, $action, $data);
}
```

---

## Hardening & Operational Safeguards

- **Strict Isolation & Whitelisting**
  - Enforce namespacing; avoid leaking globals. Only expose a documented, whitelisted core API to plugins.
  - Optional “high-risk” mode: run privileged plugins in a separate process/worker for stronger isolation.
- **Denylist & Permissions Performance**
  - Cache denylist and permission lookups per request; treat them as immutable per request to avoid repeated I/O.
  - Fast-path evaluation: denylist → capability → scope to minimize overhead in hot hooks.
- **Service Contracts & Versioning**
  - Require services to publish an interface/contract (schema or PHP interface) plus version.
  - Enforce compatibility checks on `get_plugin_service`; fail clearly or fallback gracefully.
  - Set timeouts and error handling for cross-plugin calls; treat services as potentially untrusted.
- **UI Injection Safety**
  - Sanitize all injected HTML; provide CSP guidance for admin (limit script sources).
  - Prefer external JS with integrity; reject inline scripts unless explicitly allowed.
- **Lifecycle Safety**
  - Activation/deactivation hooks must be idempotent; provide rollback on failure.
  - Migration hooks should be resumable and logged.
- **Tiered Plugin Classes**
  - “Safe” plugins: UI-only, no data access. “Privileged” plugins: data/service access; require explicit admin approval and extra review.
- **Policy Engine (Optional)**
  - Express allow/deny rules in a small policy file (e.g., JSON) compiled with the denylist for clearer audits.
- **Observability & Auditing**
  - Per-plugin logs and metrics: errors, hook timings, denied accesses, service call counts.
  - Central audit log for permission changes, denylist updates, activations/deactivations, failed dependency checks.
- **Testing Harness**
  - Sandbox runner with mocked shelves/services to test plugins before activation.
- **Minimal vs. Privileged Path**
  - Provide a “minimal plugin” scaffold (UI shelves only) and a “privileged plugin” checklist (capabilities, denylist awareness, contracts, tests) to reduce complexity for simple plugins.

---

## Non-Breaking Invariants & Upgrade Safety

**Goals**
- Updating a plugin must not break core or content.
- Updating the core must not break plugins or content.
- Updating content must not break core or plugins.

**Policies & Mechanisms**
- **Stable Contracts + Semver**
  - Version and freeze hook signatures, UI injection contexts, service interfaces.
  - Breaking changes get new hook/interface names or major version bumps; additive changes only in minor/patch.
- **Compatibility Gates**
  - Core upgrade: check plugin `requires` core version; refuse activation if incompatible.
  - Plugin upgrade: check required core version and required services; refuse activation if unmet.
  - Service retrieval: enforce version compatibility and fail clearly (or fallback) on mismatch.
- **Graceful Degradation**
  - Unknown/missing hooks or injections are no-ops, not fatal.
  - Per-injection error isolation: failed component is skipped; host UI still renders.
- **Isolation & Write Boundaries**
  - Plugins cannot modify core files or schemas; only use whitelisted APIs.
  - Denylist and permissions always enforced before access.
- **Schema Safety**
  - Plugins may add (via defined extension mechanisms) but not mutate required/core fields.
  - Content updates validated against schemas; plugin-added fields are additive.
- **Rollback & Atomicity**
  - Activation/upgrade is atomic with rollback on failure; keep previous version for quick restore.
  - Migration hooks must be idempotent and resumable.
- **Preflight & Health Checks**
  - Run a lightweight compatibility/health suite on plugin/core upgrade (load hooks, render sample injections in sandbox).
  - Block activation if preflight fails.
- **UI Injection Safety**
  - Sanitize and sandbox injected UI; CSP guidance. Injection failure must not break host pages.
- **Service Call Resilience**
  - Timeouts, retries/fallbacks for cross-plugin service calls; treat services as potentially untrusted.
- **Observability for Upgrades**
  - Log and surface per-plugin errors, hook timings, denied accesses during/after upgrade.
  - Audit log of permission/denylist changes tied to upgrade events.

**Enforcement in Flow**
1) Evaluate denylist → capabilities → scope (fast path).
2) Compatibility check before activation/upgrade.
3) If any step fails: abort and rollback; leave system in last-known-good state.

---

## Supply Chain, Marketplace & Operational Policies

- **Plugin Authenticity & Trust**
  - Sign plugins or verify checksums; record provenance/source.
  - Optional trust levels: untrusted (no default permissions), trusted (minimal defaults), verified (curated).
  - Safe mode: boot with plugins disabled if last boot failed or integrity check fails.
- **Backups & Rollbacks**
  - Snapshot config and content before core/plugin upgrades; allow one-click rollback.
  - Keep last-known-good plugin/core versions for rapid restore.
- **Stable Hook/API Policy**
  - Publish a “stable hooks”/“stable injections” list; treat others as experimental.
  - Deprecation policy with grace periods and shims where feasible.
- **Marketplace/Directory Policy**
  - If distributing plugins: vetting rules, capability defaults (start with no permissions), mandatory metadata (version, requires, capabilities), and automated lint/preflight checks.
- **Operational Guardrails**
  - Rate limits on plugin API endpoints/services; time/memory caps on long-running hooks.
  - Health checks for critical plugins; auto-disable if repeatedly failing.
- **Tooling & Developer UX**
  - CLI/linter to validate `plugin.json` (capabilities, versions, contracts) and run preflight tests.
  - Provide “safe” plugin scaffold and “privileged” checklist.

---

## Implementation Phases

### Phase 1: Capability System Foundation

**Tasks:**
1. Extend `plugin.json` schema for capabilities
2. Create permission storage system
3. Implement permission checking functions
4. Build admin permission management UI
5. Add permission audit logging

**Deliverables:**
- Capability declaration system
- Permission storage
- Permission checking API
- Admin UI for permissions

**Estimated Time:** 1-2 weeks

---

### Phase 2: UI Injection System

**Tasks:**
1. Define injection point hooks
2. Implement injection registration system
3. Create component rendering system
4. Add injection points to core templates
5. Build JavaScript component system

**Deliverables:**
- UI injection API
- Component system
- Template integration
- JavaScript helpers

**Estimated Time:** 2 weeks

---

### Phase 3: Plugin-to-Plugin Communication

**Tasks:**
1. Implement service discovery
2. Create service registration API
3. Build event broadcasting system
4. Add shared data storage
5. Implement dependency checking

**Deliverables:**
- Service discovery system
- Event broadcasting
- Data sharing APIs
- Dependency management

**Estimated Time:** 1-2 weeks

---

### Phase 4: Enhanced Hook System

**Tasks:**
1. Add priority system to hooks
2. Implement filter hooks
3. Add context passing to hooks
4. Create hook debugging tools
5. Document all hooks

**Deliverables:**
- Enhanced hook system
- Filter system
- Hook documentation
- Debugging tools

**Estimated Time:** 1 week

---

### Phase 5: Security Hardening

**Tasks:**
1. Implement sandboxing
2. Add input validation system
3. Create output sanitization
4. Enhance CSRF protection
5. Add security audit logging

**Deliverables:**
- Security measures
- Validation system
- Audit logging
- Security documentation

**Estimated Time:** 1 week

---

### Phase 6: Example Plugins

**Tasks:**
1. Create User Management plugin example
2. Create Payment plugin example
3. Create integration example
4. Write plugin development guide
5. Create plugin templates

**Deliverables:**
- Example plugins
- Development guide
- Plugin templates
- Best practices documentation

**Estimated Time:** 1-2 weeks

---

## Examples

### Example 1: User Management Plugin

**plugin.json:**
```json
{
  "name": "User Management",
  "id": "user_management",
  "version": "1.0.0",
  "capabilities": {
    "manage_users": {
      "label": "Manage Users",
      "description": "Create, edit, and delete users",
      "required": true
    },
    "modify_collection_permissions": {
      "label": "Modify Collection Permissions",
      "description": "Set collection-level permissions",
      "required": false,
      "scope": "collection"
    }
  },
  "ui_injections": [
    {
      "hook": "collection_editor_sidebar",
      "component": "admin/components/permission-controls.html",
      "script": "admin/components/permission-controls.js",
      "position": "after_settings",
      "required_capability": "modify_collection_permissions"
    }
  ],
  "provides": {
    "services": [
      {
        "id": "user_service",
        "name": "User Service",
        "version": "1.0.0"
      }
    ]
  }
}
```

**plugin.php:**
```php
<?php
// Register UI injection
register_ui_injection('user_management', 'collection_editor_sidebar',
  __DIR__ . '/admin/components/permission-controls.html',
  [
    'script' => __DIR__ . '/admin/components/permission-controls.js',
    'position' => 'after_settings',
    'required_capability' => 'modify_collection_permissions'
  ]
);

// Register service
register_plugin_service('user_management', 'user_service', 'UserService');

// Register hooks
register_hook('before_entry_create', function($collection, $entry_data) {
  // Check user permissions
  $user = get_current_user();
  if (!has_collection_permission($user, $collection, 'create')) {
    throw new PermissionDeniedException("User cannot create entries in this collection");
  }
}, ['required_capability' => 'manage_users']);
```

### Example 2: Payment Plugin Integration

**plugin.php:**
```php
<?php
// Get user service from user management plugin
try {
  $user_service = get_plugin_service('user_service');
  $user = $user_service->getUser($user_id);
  
  // Check if user has paid subscription
  if ($user->tier === 'premium') {
    // Process payment
    $payment = process_payment($amount, $user_id);
    
    // Broadcast event
    broadcast_plugin_event('payment_plugin', 'payment_completed', [
      'user_id' => $user_id,
      'amount' => $amount,
      'payment_id' => $payment->id
    ]);
  }
} catch (ServiceNotFoundException $e) {
  // User plugin not available
  error_log("User service not available: " . $e->getMessage());
}
```

### Example 3: Collection Permission Controls

**admin/components/permission-controls.html:**
```html
<div class="plugin-ui-injection" data-plugin="user_management">
  <div class="card">
    <h3>User Permissions</h3>
    <div class="permission-controls">
      <div class="form-group">
        <label>Who can create entries?</label>
        <select name="permissions[create]" class="input">
          <option value="admin">Admins only</option>
          <option value="authenticated">Authenticated users</option>
          <option value="tier:premium">Premium users</option>
          <option value="tier:enterprise">Enterprise users</option>
          <option value="public">Anyone</option>
        </select>
      </div>
      <div class="form-group">
        <label>Who can edit entries?</label>
        <select name="permissions[edit]" class="input">
          <option value="admin">Admins only</option>
          <option value="author">Entry authors</option>
          <option value="authenticated">Authenticated users</option>
        </select>
      </div>
    </div>
  </div>
</div>
```

**admin/components/permission-controls.js:**
```javascript
HaruPluginUI.register('user_management', 'collection_editor_sidebar', {
  init: function(context) {
    this.collection = context.collection;
    this.loadPermissions();
    this.bindEvents();
  },
  
  loadPermissions: function() {
    const collectionSlug = this.collection.slug;
    fetch(`/api/plugins.php?action=get_permissions&plugin=user_management&collection=${collectionSlug}`)
      .then(res => res.json())
      .then(data => {
        if (data.status === 'ok') {
          this.renderPermissions(data.permissions);
        }
      });
  },
  
  renderPermissions: function(permissions) {
    // Render permission controls
  },
  
  bindEvents: function() {
    // Handle permission changes
    document.querySelectorAll('.permission-controls select').forEach(select => {
      select.addEventListener('change', (e) => {
        this.savePermissions();
      });
    });
  },
  
  savePermissions: function() {
    const permissions = {
      create: document.querySelector('[name="permissions[create]"]').value,
      edit: document.querySelector('[name="permissions[edit]"]').value
    };
    
    fetch('/api/plugins.php?action=save_permissions', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        plugin: 'user_management',
        collection: this.collection.slug,
        permissions: permissions
      })
    });
  }
});
```

---

## Plugin Management UX

- **Plugin List (Settings → Plugins)**:
  - Activate/Deactivate toggle per plugin
  - Version, author, description
  - Granted capabilities summary (with badge for denylist shield)
  - Quick link to Permissions and Plugin Page
- **Permissions Panel (per plugin)**:
  - Capability grants/denies
  - Collection-specific permissions
  - Protected areas display (spaces/collections denylist)
  - Audit trail of changes
- **Global Security Panel**:
  - Configure denylisted spaces/collections (no plugin access even if granted)
  - Warning banner explaining precedence of denylist
  - Change log for denylist updates
- **Plugin Page (main sidebar under “Plugins”)**:
  - Each plugin can expose its own admin page from metadata
  - Respects capabilities and denylist
  - Houses plugin-specific settings and actions
- **States & Visibility**:
  - Deactivated plugins: UI hidden, hooks not executed, endpoints disabled
  - Active plugins: UI shown based on capabilities; blocked from denylisted resources
  - Inline indicators when an action is blocked by denylist

---

## Conclusion

This Plugin System Architecture provides a robust, secure, and flexible foundation for extending HaruCMS. By implementing capabilities, permissions, UI injection, and plugin communication, we enable developers to create powerful, integrated plugins while maintaining security and system stability.

The phased implementation approach ensures we can deliver value incrementally, starting with core capabilities and building toward a comprehensive plugin ecosystem.

---

**Document Version:** 1.0  
**Last Updated:** 2024-01-15  
**Status:** Planning Phase
