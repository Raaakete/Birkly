# User Plugin Design - Comprehensive Brainstorming

## Overview

A flexible, secure, and extensible user management plugin that integrates deeply with Birkly's collection/entry system, enabling fine-grained permissions, tier-based access, and seamless payment integration.

## ⚠️ Critical Architecture Principles

### 1. Fully Self-Contained Plugin
**The plugin must be completely independent - just copy/paste to install.**

- ✅ **All functions inside plugin**: Plugin defines its own functions, classes, helpers
- ✅ **No core dependencies**: Plugin hooks into system, doesn't depend on core functions
- ✅ **Copy/paste installation**: Just copy `plugins/user_management/` folder → plugin works
- ✅ **Self-registering**: Plugin registers itself via hooks/filters on load

**How it works:**
```php
// plugins/user_management/plugin.php
// Plugin loads and registers itself
if (function_exists('register_hook')) {
    register_hook('template_functions', function() {
        // Register template functions
        return [
            'user_current' => 'UserManagementPlugin::getCurrentUser',
            'user_can' => 'UserManagementPlugin::userCan',
            'user_tier' => 'UserManagementPlugin::getUserTier',
            // ... all functions defined in plugin
        ];
    });
}
```

**Key Points:**
- Plugin defines ALL its own functions (no external dependencies)
- Plugin hooks into system via `register_hook()` / `add_filter()` (system provides these)
- Plugin can be installed by just copying the folder
- Plugin can be removed by just deleting the folder

### 2. Templating Engine Integration (Primary Interface)
**Users build websites using the templating engine - REST APIs are optional.**

- ✅ **Template functions**: Plugin provides template functions users can call in templates
- ✅ **Template-first**: Primary way to interact with plugin is via template language
- ✅ **REST APIs optional**: REST APIs available but not required for basic functionality
- ✅ **Natural syntax**: Functions work with existing template syntax

**Template Functions:**
```html
<!-- In user's template -->
{if user_current exist}
    <p>Welcome, {user_current.name}!</p>
    {if user_can 'create_entries' 'blog'}
        <a href="/create-post">Create Post</a>
    {endif}
    {if user_tier == 'premium'}
        <p>You have premium access!</p>
    {endif}
{else}
    <a href="/login">Login</a>
{endif}
```

**Plugin Registers Template Functions:**
```php
// Plugin registers functions that work with template parser
register_hook('template_functions', function() {
    return [
        'user_current' => function() {
            return UserManagementPlugin::getCurrentUser();
        },
        'user_can' => function($action, $context = null) {
            return UserManagementPlugin::userCan($action, $context);
        },
        'user_tier' => function() {
            return UserManagementPlugin::getUserTier();
        }
    ];
});
```

### 3. Frontend/UX Separation
**The plugin provides template functions and optional APIs, NOT frontend UI.**

- ✅ Plugin provides: Template functions, Service classes, Hooks/Filters
- ✅ User provides: All frontend HTML/CSS/JS, Custom templates using template functions
- ✅ Optional: REST API endpoints (for advanced use cases)
- ✅ Optional: Admin UI templates (for admin panel only)

**Example:**
- Template function: `{user_current.name}` → Renders user name in template
- User's template: Custom HTML that uses template functions
- Optional API: `/api/user_registration.php` (for AJAX forms, etc.)

### 4. Data Isolation from Core System
**All plugin data stored separately to ensure safety from system updates.**

- ✅ Plugin data: `data/plugins/user_management/` (NEVER touched by system updates)
- ✅ Plugin code: `plugins/user_management/` (updated by plugin updates only)
- ❌ NOT in: `core/`, `content/` (except optional public profiles), or other core directories

**Why this matters:**
- System updates won't affect plugin data
- Plugin can be safely disabled/removed
- Easy backup/restore of plugin data
- Multiple plugin instances possible (if needed)

**Data Structure:**
```
data/plugins/user_management/
├── users/              # Private user data (encrypted)
├── permissions/        # Permission configurations
├── tiers/             # Tier definitions
└── metadata/          # Plugin settings
```

## Core Philosophy

**Simplicity + Flexibility**: Start simple, but design for extensibility. Every feature should be optional and configurable.

**Security First**: User data is private by default. All access is explicit and auditable.

**Integration Native**: Users are first-class citizens that can interact with collections, entries, and other plugins.

**Separation of Concerns**: 
- **Backend/API**: Plugin provides APIs, services, and data management
- **Frontend/UX**: User's frontend handles all presentation (plugin may provide optional templates)
- **Data Isolation**: All plugin data stored separately from core system (safe from updates)

---

## 0. Architecture & Data Isolation

### Critical Principle: Data Isolation from Core System

**All plugin data MUST be stored outside core system directories to ensure:**
- ✅ Safety from system updates
- ✅ Easy backup/restore of plugin data
- ✅ Plugin can be disabled/removed without affecting core
- ✅ Multiple plugin instances possible (if needed)

### Data Storage Locations

```
Birkly/
├── core/                    # Core system (updated by system updates)
│   ├── plugins.php
│   └── ...
├── plugins/                 # Plugin code (updated by plugin updates)
│   └── user_management/
│       ├── plugin.php
│       ├── api/
│       └── ...
├── data/                    # Plugin data (NEVER touched by system updates)
│   └── plugins/
│       └── user_management/
│           ├── users/              # Private user data (encrypted)
│           │   ├── {user_id}.json
│           │   └── ...
│           ├── permissions/        # Permission data
│           │   └── permissions.json
│           ├── tiers/              # Tier configurations
│           │   └── tiers.json
│           └── metadata/           # Plugin metadata
│               └── ...
├── content/                 # Public profile data (collection-based)
│   └── collections/
│       └── users/                  # User profiles as entries
│           ├── collection.json
│           └── entries/
│               └── {user_id}.json
└── settings/                # Plugin settings (shared with other plugins)
    └── plugins.json         # Plugin states (managed by core)
```

**Key Points:**
- **Plugin Code**: `plugins/user_management/` - Updated by plugin updates
- **Plugin Data**: `data/plugins/user_management/` - NEVER touched by system updates
- **Public Profiles**: `content/collections/users/` - Uses collection system (optional)
- **Settings**: `settings/plugins.json` - Plugin states (managed by core, but plugin-specific settings in plugin data)

### Frontend/UX Separation

**Important**: The plugin provides **APIs and services**, NOT frontend UI.

**Plugin Provides:**
- ✅ REST API endpoints (`/api/user_auth.php`, `/api/user_registration.php`, etc.)
- ✅ Service interface (`UserService` class)
- ✅ Hooks and filters for extensibility
- ✅ Optional admin UI templates (for admin panel only)
- ✅ Data models and business logic

**User's Frontend Handles:**
- ✅ Registration forms (custom HTML/CSS/JS)
- ✅ Login pages (custom design)
- ✅ User profile pages (custom templates)
- ✅ User dashboards (custom UI)
- ✅ All public-facing UI

**Example Flow:**
```php
// Plugin provides API
POST /api/user_registration.php
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "secure123"
}

// User's frontend calls API
fetch('/api/user_registration.php', {
  method: 'POST',
  body: JSON.stringify(userData)
})
.then(response => response.json())
.then(data => {
  // Handle response in user's custom UI
  if (data.status === 'success') {
    showCustomSuccessMessage();
    redirectToCustomDashboard();
  }
});
```

**Optional Admin UI:**
- Plugin MAY provide admin UI templates for managing users in admin panel
- These are optional and can be overridden
- Stored in `plugins/user_management/admin/` (not in core)

---

## 1. User Storage & Privacy

### Storage Strategy (Updated with Data Isolation)

- **Option A**: Store users as entries in a special collection (like `community_users` does)
  - ✅ Pros: Leverages existing collection system, easy to query/filter, consistent with content
  - ❌ Cons: Less secure by default, visible in collection UI, stored in `content/` (core directory)
  
- **Option B**: Separate secure storage in plugin data directory
  - ✅ Pros: More secure, private by default, can encrypt sensitive fields, **isolated from core**
  - ✅ Location: `data/plugins/user_management/users/` (safe from system updates)
  - ❌ Cons: Separate from collection system, needs custom query logic

- **Option C**: Hybrid (Recommended) - **WITH DATA ISOLATION**
  - Store **public profile data** in collection: `content/collections/users/` (optional, for display/search)
  - Store **private data** (passwords, emails, payment info) in plugin data: `data/plugins/user_management/users/`
  - Link them via user ID
  - Best of both worlds: flexible display + security + **data isolation**

### Privacy Levels
```json
{
  "privacy_level": "public|private|friends_only|tier_based",
  "public_fields": ["name", "avatar", "bio"],
  "private_fields": ["email", "phone", "address"],
  "tier_visible_fields": {
    "premium": ["email", "phone"],
    "basic": ["name", "avatar"]
  }
}
```

### Data Encryption
- Encrypt sensitive fields (email, phone, payment info) at rest
- Use plugin setting to enable/disable encryption
- Support field-level encryption (some fields encrypted, others not)

---

## 2. User Registration & Authentication

### Registration Methods
1. **Public Registration** (via website form)
   - Configurable fields (name, email, password, custom fields)
   - Email verification (optional)
   - Admin approval (optional)
   - Rate limiting
   - CAPTCHA/honeypot support

2. **Invite-Only Registration**
   - Generate invite codes/tokens
   - Set expiration dates
   - Limit uses per invite
   - Track who invited whom

3. **OAuth/SSO Integration** (future)
   - Google, GitHub, etc.
   - Plugin hook: `user_oauth_login`

4. **Admin-Created Users**
   - Admins can create users directly
   - Set initial role/tier
   - Send welcome email (optional)

### Authentication
- Session-based (existing system)
- JWT tokens (optional, for API access)
- 2FA support (hook into existing 2FA system)
- Remember me / persistent sessions
- Password reset flow
- Account recovery

### Registration Hooks
```php
// Before registration
do_action('before_user_register', $user_data);
// After registration
do_action('after_user_register', $user_id, $user_data);
// On email verification
do_action('user_email_verified', $user_id);
// On admin approval
do_action('user_approved', $user_id);
```

---

## 3. User Display & Management

### Admin Interface
- **User List View** (similar to entries list)
  - Search/filter by name, email, role, tier, status
  - Bulk actions (approve, delete, change tier)
  - Export users (CSV, JSON)
  - Import users (CSV, JSON)

- **User Detail View**
  - Profile information
  - Activity log (what collections/entries they accessed/modified)
  - Permissions overview
  - Tier/subscription info (if payment plugin connected)
  - Connected accounts (OAuth, etc.)

### Public User Profiles
- Configurable profile pages (`/users/{username}` or `/users/{id}`)
- Customizable profile fields (via collection schema)
- Privacy controls (who can view profile)
- User avatars, bios, social links
- Activity feed (optional)

### User Collection Integration
- Users stored as entries in `users` collection
- Can use collection fieldtypes for custom fields
- Can create sub-collections (e.g., `user_posts`, `user_comments`)
- Can reference users in other collections (via fieldtype)

---

## 4. Permissions & Access Control

### Permission System Architecture

#### User Roles (Built-in)
- `admin` - Full access
- `editor` - Can edit content
- `author` - Can create content
- `user` - Basic user (default)
- `guest` - Limited access

#### Custom Roles (Plugin-defined)
- Define custom roles via plugin settings
- Each role has permissions array
- Permissions can be:
  - Global: `manage_content`, `manage_users`
  - Collection-specific: `edit_entries:blog`, `create_entries:products`
  - Entry-specific: `edit_entry:123`, `delete_entry:456`

#### Permission Granularity
```json
{
  "user_id": "user_123",
  "role": "author",
  "permissions": {
    "global": ["read", "create_entries"],
    "collections": {
      "blog": {
        "create": true,
        "edit_own": true,
        "edit_all": false,
        "delete_own": true
      },
      "products": {
        "read": true,
        "create": false
      }
    },
    "entries": {
      "blog:entry_456": {
        "edit": true,
        "delete": false
      }
    }
  }
}
```

### Permission Checking
```php
// Check if user can perform action
function user_can($user_id, $action, $context = null) {
  // Check role permissions
  // Check collection permissions
  // Check entry permissions
  // Check tier permissions (if payment plugin connected)
  // Apply filters for extensibility
  return apply_filters('user_can', $result, $user_id, $action, $context);
}
```

### Permission Inheritance
- Users inherit permissions from their role
- Can override at collection/entry level
- Tier-based permissions (from payment plugin) can add/restrict

---

## 5. Collection & Entry Integration

### User Ownership
- Entries can have `author_id` or `user_id` field
- Users can "own" entries
- Ownership grants automatic edit/delete permissions

### User-Generated Content
- Users can create entries in allowed collections
- Auto-populate `author_id` field
- Respect collection permissions
- Support moderation workflow

### User References
- Fieldtype: `user_reference` or `user_select`
- Can reference single user or multiple users
- Display user name, avatar, link to profile
- Search/filter by referenced users

### Collection-Level Permissions
```json
{
  "collection": "blog",
  "user_permissions": {
    "user_123": {
      "create": true,
      "edit_own": true,
      "edit_all": false,
      "delete_own": true,
      "publish": false  // Needs admin approval
    },
    "role:author": {
      "create": true,
      "edit_own": true,
      "publish": true
    }
  }
}
```

### Entry-Level Permissions
- Each entry can have custom permissions
- Override collection defaults
- Support collaborative editing (multiple users can edit)

---

## 6. Tier-Based Access (Payment Integration)

### Tier System
```json
{
  "tier_id": "premium",
  "name": "Premium",
  "permissions": {
    "collections": {
      "premium_content": {
        "read": true,
        "create": true
      }
    },
    "features": [
      "unlimited_entries",
      "custom_domain",
      "priority_support"
    ]
  },
  "limits": {
    "max_entries": -1,  // -1 = unlimited
    "max_storage_mb": 1000,
    "max_collections": 10
  }
}
```

### Payment Plugin Integration
- **Service Interface**: User plugin exposes `user_service`
- Payment plugin can:
  - Get user's current tier: `$userService->getUserTier($user_id)`
  - Set user tier: `$userService->setUserTier($user_id, $tier_id)`
  - Check tier permissions: `$userService->userHasTier($user_id, $tier_id)`
  - Get tier limits: `$userService->getTierLimits($tier_id)`

### Tier Hooks
```php
// When tier changes
do_action('user_tier_changed', $user_id, $old_tier, $new_tier);
// Check if user can access resource (tier-based)
apply_filters('user_tier_access', $allowed, $user_id, $resource, $tier);
```

### Tier Features
- **Content Access**: Restrict collections/entries by tier
- **Feature Flags**: Enable/disable features per tier
- **Limits**: Entry count, storage, collections, etc.
- **Expiration**: Auto-downgrade when subscription expires

---

## 7. Advanced Features

### User Activity & Analytics
- Track user actions (login, content created, etc.)
- Activity feed per user
- Admin dashboard: user activity overview
- Export activity logs

### User Groups/Teams
- Users can belong to groups
- Group permissions (all members get permissions)
- Group ownership of entries
- Collaborative workspaces

### User Preferences
- Language settings
- Theme preferences
- Notification preferences
- Dashboard customization

### User Metadata
- Custom fields via collection schema
- Extensible via hooks/filters
- Support for user tags/categories
- User relationships (follow, friend, etc.)

### Email Notifications
- Welcome emails
- Email verification
- Password reset
- Account approval/rejection
- Activity notifications (optional)
- Hook: `send_user_email($user_id, $template, $data)`

### User Import/Export
- Import from CSV/JSON
- Export to CSV/JSON
- Support for bulk operations
- Migration tools

### User Search & Discovery
- Search users by name, email, role
- Filter by tier, status, registration date
- Public user directory (optional)
- User recommendations

---

## 8. Security Features

### Data Protection
- Password hashing (bcrypt/argon2)
- Encrypted sensitive fields
- Secure session management
- CSRF protection
- Rate limiting on auth endpoints

### Privacy Controls
- GDPR compliance (data export, deletion)
- Privacy settings per user
- Anonymize user data option
- Audit logs for sensitive operations

### Access Control
- IP whitelisting (optional)
- Device tracking
- Suspicious activity detection
- Account lockout after failed attempts
- 2FA support

---

## 9. Plugin Architecture

### Plugin Structure (Fully Self-Contained)

```
plugins/user_management/          # Plugin code (copy/paste to install)
├── plugin.php                    # Main plugin file (self-registers)
├── plugin.json                   # Metadata
├── includes/                     # All plugin code (self-contained)
│   ├── UserService.php          # Core service class
│   ├── UserStorage.php          # Data storage (isolated)
│   ├── UserAuth.php             # Authentication logic
│   ├── UserPermissions.php      # Permission system
│   ├── UserTiers.php            # Tier system
│   └── TemplateHelpers.php      # Template function definitions
├── api/                          # Optional REST API endpoints
│   ├── user_auth.php            # Authentication endpoints
│   ├── user_registration.php    # Registration endpoint
│   ├── user_profile.php         # Profile CRUD endpoint
│   └── user_admin.php           # Admin CRUD endpoint
├── admin/                        # Optional admin UI (for admin panel only)
│   ├── users.html               # Admin UI template
│   └── users.js                 # Admin UI JS
└── examples/                     # Example templates (NOT used by default)
    ├── registration.html         # Example registration form
    ├── login.html               # Example login form
    └── profile.html             # Example profile page

data/plugins/user_management/     # Plugin data (NEVER touched by system updates)
├── users/                        # Private user data
│   ├── {user_id}.json           # Encrypted sensitive data
│   └── ...
├── permissions/
│   └── permissions.json          # Permission configurations
├── tiers/
│   └── tiers.json               # Tier definitions
├── groups/
│   └── groups.json              # User groups
└── metadata/
    ├── settings.json             # Plugin-specific settings
    └── cache/                    # Cached data

content/collections/users/        # Optional: Public profile data (if using collection)
├── collection.json
├── schema.json
└── entries/
    └── {user_id}.json           # Public profile data only
```

**Critical Requirements:**
- **Self-Contained**: All code in `includes/` - no external dependencies
- **Self-Registering**: `plugin.php` registers all hooks/filters on load
- **Template Functions**: Registered via `template_functions` hook
- **Copy/Paste Install**: Just copy folder → plugin works
- **Data Isolation**: All data in `data/plugins/user_management/`
- **REST APIs Optional**: Available but not required (template functions are primary)

### Plugin Bootstrap (Self-Contained)

```php
<?php
// plugins/user_management/plugin.php
// Fully self-contained - all code in includes/

// Only load if plugin system is available
if (!function_exists('register_hook')) {
    return; // System not ready
}

// Load all plugin code (self-contained)
require_once __DIR__ . '/includes/UserService.php';
require_once __DIR__ . '/includes/UserStorage.php';
require_once __DIR__ . '/includes/UserAuth.php';
require_once __DIR__ . '/includes/UserPermissions.php';
require_once __DIR__ . '/includes/UserTiers.php';
require_once __DIR__ . '/includes/TemplateHelpers.php';

// Initialize plugin (self-registering)
UserManagementPlugin::init();
```

### Template Functions Registration (Primary Interface)

```php
<?php
// plugins/user_management/includes/TemplateHelpers.php
// All template functions defined here

class UserManagementPlugin {
    private static $initialized = false;
    
    public static function init() {
        if (self::$initialized) return;
        
        // Register template functions (PRIMARY INTERFACE)
        if (function_exists('register_hook')) {
            register_hook('template_functions', function() {
                return [
                    // Current user
                    'user_current' => [self::class, 'getCurrentUser'],
                    'user_logged_in' => [self::class, 'isLoggedIn'],
                    
                    // Permissions
                    'user_can' => [self::class, 'userCan'],
                    'user_role' => [self::class, 'getUserRole'],
                    
                    // Tiers
                    'user_tier' => [self::class, 'getUserTier'],
                    'user_has_tier' => [self::class, 'userHasTier'],
                    
                    // User data
                    'user_by_id' => [self::class, 'getUserById'],
                    'user_by_email' => [self::class, 'getUserByEmail'],
                    'user_profile' => [self::class, 'getUserProfile'],
                    
                    // Lists
                    'users_list' => [self::class, 'getUsersList'],
                    'users_count' => [self::class, 'getUsersCount'],
                ];
            });
        }
        
        // Register service for other plugins (optional)
        if (function_exists('register_plugin_service')) {
            register_plugin_service('user_service', new UserService(), [
                'version' => '1.0.0',
                'description' => 'User management service',
                'capability' => 'access_user_service'
            ]);
        }
        
        // Register API endpoints (optional)
        if (function_exists('register_api_endpoint')) {
            register_api_endpoint('user_auth', __DIR__ . '/api/user_auth.php');
            register_api_endpoint('user_registration', __DIR__ . '/api/user_registration.php');
        }
        
        self::$initialized = true;
    }
    
    // Template function implementations
    public static function getCurrentUser() {
        $service = new UserService();
        return $service->getCurrentUser();
    }
    
    public static function isLoggedIn() {
        $service = new UserService();
        return $service->isLoggedIn();
    }
    
    public static function userCan($action, $context = null) {
        $service = new UserService();
        $user = $service->getCurrentUser();
        if (!$user) return false;
        return $service->userCan($user['id'], $action, $context);
    }
    
    // ... more template functions
}
```

### Service Interface (Self-Contained)

```php
<?php
// plugins/user_management/includes/UserService.php
// All functions defined here - no external dependencies

class UserService {
    private $storage;
    
    public function __construct() {
        // Use plugin's own storage (isolated)
        $this->storage = new UserStorage(__DIR__ . '/../../data/plugins/user_management');
    }
    
    // User CRUD
    public function createUser($data) {
        // Implementation in plugin
    }
    
    public function getUser($user_id) {
        // Implementation in plugin
    }
    
    // ... all methods self-contained
}
```

### Hooks & Filters
```php
// Actions
'before_user_register'
'after_user_register'
'user_login'
'user_logout'
'user_updated'
'user_deleted'
'user_tier_changed'
'user_permission_changed'

// Filters
'user_can'                    // Modify permission check
'user_data'                   // Modify user data before save
'user_display_data'           // Modify data for display
'user_tier_access'           // Check tier-based access
'user_registration_fields'    // Add/remove registration fields
```

---

## 10. Settings & Configuration

### Plugin Settings Schema
```json
{
  "registration": {
    "enabled": true,
    "require_email_verification": false,
    "require_admin_approval": false,
    "default_role": "user",
    "allowed_domains": [],  // Restrict to specific email domains
    "max_users": 0  // 0 = unlimited
  },
  "privacy": {
    "default_privacy_level": "private",
    "allow_public_profiles": true,
    "encrypt_sensitive_fields": true
  },
  "permissions": {
    "enable_custom_roles": true,
    "enable_entry_permissions": true,
    "enable_collection_permissions": true
  },
  "display": {
    "show_user_list": true,
    "user_profile_slug": "users",
    "enable_user_search": true
  },
  "security": {
    "password_min_length": 8,
    "require_strong_password": false,
    "session_timeout": 3600,
    "max_login_attempts": 5,
    "lockout_duration": 900
  },
  "tiers": {
    "enabled": false,  // Enable when payment plugin connected
    "default_tier": "free"
  }
}
```

---

## 11. What Sets Us Apart

### 1. **Deep Collection Integration**
- Users aren't separate from content; they're part of the content system
- Users can own entries, have permissions per collection/entry
- Leverage existing collection fieldtypes for user fields

### 2. **Flexible Permission System**
- Role-based + collection-based + entry-based + tier-based
- Granular control without complexity
- Extensible via hooks/filters

### 3. **Service-Oriented Architecture**
- Clean API for other plugins
- Payment plugin integration is seamless
- Other plugins can extend user functionality

### 4. **Privacy by Default**
- Sensitive data encrypted
- Public/private profiles
- GDPR-ready

### 5. **Simplicity**
- Start with basic features
- Everything is optional
- No bloat, but extensible

### 6. **Developer-Friendly**
- Well-documented hooks/filters
- Service interface for integrations
- Easy to extend

---

## 12. Implementation Phases

### Phase 1: Core (MVP)
- [ ] User storage (secure + collection)
- [ ] Registration (public + admin)
- [ ] Authentication (login/logout)
- [ ] Basic permissions (roles)
- [ ] Admin UI (list, view, edit users)
- [ ] User service registration

### Phase 2: Permissions
- [ ] Collection-level permissions
- [ ] Entry-level permissions
- [ ] Permission checking system
- [ ] User ownership of entries

### Phase 3: Display & Profiles
- [ ] Public user profiles
- [ ] User list/search
- [ ] Custom user fields
- [ ] User avatars

### Phase 4: Advanced Features
- [ ] User groups/teams
- [ ] Activity tracking
- [ ] Email notifications
- [ ] Import/export

### Phase 5: Payment Integration
- [ ] Tier system
- [ ] Payment plugin hooks
- [ ] Tier-based permissions
- [ ] Tier limits

---

## 13. Detailed Features & Use Cases

### Feature Deep Dives

#### Feature 1: User Registration Flow

**Scenario A: Public Blog Registration**
1. User visits `/register` page
2. Fills form: Name, Email, Password, Bio (optional)
3. System validates:
   - Email format & uniqueness
   - Password strength (min 8 chars)
   - Rate limiting (max 3 registrations per IP/hour)
4. User receives verification email (if enabled)
5. User clicks link → account activated
6. User auto-assigned "author" role
7. User can immediately create blog posts

**Scenario B: Invite-Only Registration**
1. Admin generates invite code: `SUMMER2024`
2. Admin sets: 50 uses, expires in 30 days
3. User receives invite link: `/register?invite=SUMMER2024`
4. Registration form pre-fills invite code
5. User completes registration
6. Invite usage count decrements
7. User gets "member" role (pre-configured for invite)

**Scenario C: Admin-Created User**
1. Admin goes to Users → Add New
2. Fills: Name, Email, Role (select: "editor")
3. System generates temporary password
4. Admin sends welcome email with login link
5. User receives email, sets own password on first login
6. User immediately has "editor" permissions

---

#### Feature 2: Permission System in Action

**Scenario A: Multi-Author Blog**

**Setup:**
- Collection: "blog_posts"
- Users: Alice (admin), Bob (author), Carol (author)

**Permissions Configuration:**
```json
{
  "blog_posts": {
    "role:author": {
      "create": true,
      "edit_own": true,
      "delete_own": true,
      "publish": false  // Needs admin approval
    },
    "role:admin": {
      "create": true,
      "edit_all": true,
      "delete_all": true,
      "publish": true
    }
  }
}
```

**User Journey:**
1. Bob logs in → sees "Create Post" button
2. Bob creates post "My First Article"
3. Post saved with `author_id: bob_123`, `status: draft`
4. Bob can edit/delete his own post
5. Bob clicks "Submit for Review"
6. Alice (admin) sees notification
7. Alice reviews, edits if needed, publishes
8. Carol can see Bob's published posts but can't edit them

**Scenario B: Collaborative Editing**

**Setup:**
- Entry: "Product Roadmap 2024"
- Users: Product Manager (owner), Designer, Developer

**Entry-Level Permissions:**
```json
{
  "entry_id": "roadmap_456",
  "owner": "pm_123",
  "permissions": {
    "pm_123": {
      "edit": true,
      "delete": true,
      "manage_collaborators": true
    },
    "designer_789": {
      "edit": true,
      "edit_sections": ["design", "ui"]
    },
    "dev_456": {
      "edit": true,
      "edit_sections": ["technical", "api"]
    }
  }
}
```

**User Journey:**
1. PM creates roadmap, adds Designer & Developer as collaborators
2. Designer can edit "Design" and "UI" sections only
3. Developer can edit "Technical" and "API" sections only
4. PM can edit everything and manage collaborators
5. All changes tracked with `modified_by` field
6. Activity log shows who changed what

---

#### Feature 3: Tier-Based Access (Payment Integration)

**Scenario: Online Course Platform**

**Setup:**
- Collections: "free_lessons", "premium_lessons", "exclusive_content"
- Tiers: Free, Basic ($9/mo), Premium ($29/mo), Enterprise ($99/mo)

**Tier Configuration:**
```json
{
  "tiers": {
    "free": {
      "collections": {
        "free_lessons": {"read": true}
      },
      "limits": {"max_lessons": 5}
    },
    "basic": {
      "collections": {
        "free_lessons": {"read": true},
        "premium_lessons": {"read": true}
      },
      "limits": {"max_lessons": -1}
    },
    "premium": {
      "collections": {
        "free_lessons": {"read": true},
        "premium_lessons": {"read": true},
        "exclusive_content": {"read": true, "create": true}
      },
      "features": ["certificates", "priority_support"]
    }
  }
}
```

**User Journey:**
1. User registers → automatically "free" tier
2. User can access 5 free lessons
3. User upgrades to "premium" via payment plugin
4. Payment plugin calls: `$userService->setUserTier($user_id, "premium")`
5. User immediately gets access to all premium content
6. User can create entries in "exclusive_content" collection
7. User gets "certificates" and "priority_support" features
8. If payment fails, tier auto-downgrades to "free"

**Advanced: Tier Expiration**
1. Premium subscription expires
2. Payment plugin triggers: `do_action('user_tier_expired', $user_id, 'premium')`
3. User plugin auto-downgrades to "free"
4. User loses access to premium content
5. User's premium entries become read-only
6. User receives email: "Your premium subscription has expired"

---

#### Feature 4: User-Generated Content

**Scenario: Community Forum**

**Setup:**
- Collection: "forum_topics" (public_api entry method)
- Sub-collection: "forum_replies" (public_api)
- Users: Members (can post), Moderators (can moderate)

**Collection Configuration:**
```json
{
  "forum_topics": {
    "entry_method": "public_api",
    "user_permissions": {
      "role:member": {
        "create": true,
        "edit_own": true,
        "delete_own": true
      }
    },
    "sub_collections": [{
      "id": "forum_replies",
      "entry_method": "public_api",
      "permissions": {
        "create": "authenticated",
        "edit_own": true
      }
    }]
  }
}
```

**User Journey:**
1. User logs in → sees "Create Topic" button
2. User creates topic: "How to use Birkly?"
3. Entry saved with `author_id: user_123`, `created_at: now`
4. Other users can view and reply
5. User can edit/delete their own topic
6. Moderator can edit/delete any topic
7. Replies are sub-entries linked to topic
8. Each reply has `author_id` for ownership

**Moderation Workflow:**
1. New topic created → status: "pending"
2. Moderator reviews in admin panel
3. Moderator approves → status: "published"
4. Or moderator rejects → user notified, topic deleted
5. Auto-moderation: Topics with certain keywords auto-flagged

---

#### Feature 5: User Profiles & Discovery

**Scenario: Professional Network**

**Setup:**
- User collection with fields: Name, Bio, Skills, Location, Portfolio URL
- Privacy: Public profiles enabled

**Profile Configuration:**
```json
{
  "user_profile": {
    "fields": [
      {"name": "name", "type": "text", "public": true},
      {"name": "bio", "type": "editor", "public": true},
      {"name": "skills", "type": "tags", "public": true},
      {"name": "location", "type": "text", "public": true},
      {"name": "email", "type": "email", "public": false},
      {"name": "phone", "type": "text", "public": false}
    ],
    "profile_url": "/users/{username}",
    "enable_search": true
  }
}
```

**User Journey:**
1. User registers → profile auto-created
2. User edits profile: Adds bio, skills, portfolio
3. Profile visible at `/users/john-doe`
4. Other users can search: "Find developers in Berlin"
5. Search filters by: Location, Skills, Role
6. User can set privacy: "Public", "Private", "Friends Only"
7. If private, only name/avatar visible to others

**Profile Integration:**
1. User creates blog post → Author shows as link to profile
2. User comments on post → Avatar/name links to profile
3. User's profile shows: "Posts by this user", "Comments by this user"
4. Activity feed on profile: Recent posts, comments, activity

---

#### Feature 6: User Groups/Teams

**Scenario: Agency Workspace**

**Setup:**
- Groups: "Design Team", "Development Team", "Marketing Team"
- Collections: "client_projects", "internal_docs"

**Group Configuration:**
```json
{
  "groups": {
    "design_team": {
      "members": ["alice", "bob"],
      "permissions": {
        "collections": {
          "client_projects": {
            "read": true,
            "create": true,
            "edit_own": true
          },
          "internal_docs": {
            "read": true,
            "create": true
          }
        }
      }
    }
  }
}
```

**User Journey:**
1. Admin creates "Design Team" group
2. Admin adds Alice & Bob to group
3. Both automatically get group permissions
4. Alice creates project → visible to all group members
5. Bob can edit Alice's project (group permission)
6. Non-members can't see group projects
7. If Alice leaves group → loses group permissions
8. Group projects remain, but Alice can't access

**Collaborative Features:**
1. Project entry has `group_id: design_team`
2. All group members can edit
3. Activity log shows: "Alice edited project", "Bob added comment"
4. Notifications: "Bob mentioned you in project comment"
5. Group dashboard: Shows all group projects, activity

---

### Real-World Use Cases

#### Use Case 1: Content Marketing Blog

**Requirements:**
- Multiple authors
- Guest writers
- Editorial workflow
- Author profiles

**Setup:**
1. **Collections:**
   - `blog_posts` (main content)
   - `authors` (user profiles with extended fields)

2. **Roles:**
   - `admin` - Full control
   - `editor` - Can publish, edit all
   - `author` - Can create, edit own, submit for review
   - `guest_author` - Can create, can't publish

3. **Permissions:**
   ```json
   {
     "blog_posts": {
       "role:author": {
         "create": true,
         "edit_own": true,
         "publish": false
       },
       "role:editor": {
         "create": true,
         "edit_all": true,
         "publish": true
       }
     }
   }
   ```

4. **Workflow:**
   - Author writes post → saves as draft
   - Author submits for review
   - Editor reviews, edits if needed
   - Editor publishes
   - Post shows author name, links to author profile
   - Author profile shows all their posts

**Features Used:**
- User registration (authors)
- Role-based permissions
- Entry ownership
- User profiles
- Activity tracking

---

#### Use Case 2: Online Learning Platform

**Requirements:**
- Free and paid courses
- Student progress tracking
- Certificates for completion
- Instructor accounts

**Setup:**
1. **Collections:**
   - `courses` (admin only)
   - `lessons` (admin only)
   - `student_progress` (user can create)
   - `certificates` (system generated)

2. **Tiers:**
   - `free` - Access to free courses only
   - `student` ($19/mo) - Access to all courses
   - `instructor` - Can create courses
   - `lifetime` ($299) - Lifetime access

3. **User Types:**
   - Students (default tier: free)
   - Instructors (special role + tier)

4. **Permissions:**
   ```json
   {
     "courses": {
       "tier:free": {
         "read": true,
         "filter": {"tags": ["free"]}
       },
       "tier:student": {
         "read": true
       },
       "role:instructor": {
         "create": true,
         "edit_own": true
       }
     },
     "student_progress": {
       "role:user": {
         "create": true,
         "edit_own": true,
         "read_own": true
       }
     }
   }
   ```

5. **Workflow:**
   - Student registers → free tier
   - Student enrolls in free course
   - Student upgrades to "student" tier
   - Student accesses all courses
   - Student completes course → progress entry created
   - System generates certificate (if tier allows)
   - Instructor creates course → auto-assigned as owner
   - Instructor can edit their courses only

**Features Used:**
- Tier-based access
- Payment integration
- Role-based permissions
- Entry ownership
- User-generated content (progress)

---

#### Use Case 3: E-Commerce Marketplace

**Requirements:**
- Sellers can list products
- Buyers can purchase
- Reviews and ratings
- Seller profiles

**Setup:**
1. **Collections:**
   - `products` (sellers can create)
   - `orders` (system/payment plugin)
   - `reviews` (buyers can create)
   - `seller_profiles` (user profiles)

2. **Roles:**
   - `buyer` - Can purchase, review
   - `seller` - Can create products, manage orders
   - `admin` - Full control

3. **Permissions:**
   ```json
   {
     "products": {
       "role:seller": {
         "create": true,
         "edit_own": true,
         "delete_own": true
       },
       "role:buyer": {
         "read": true
       }
     },
     "reviews": {
       "role:buyer": {
         "create": true,
         "edit_own": true
       }
     }
   }
   ```

4. **Workflow:**
   - User registers → "buyer" role
   - User applies to become seller → admin approves
   - User role changes to "seller"
   - Seller creates products
   - Buyers purchase (via payment plugin)
   - Buyers leave reviews (sub-entries on products)
   - Seller profile shows: products, ratings, reviews

**Features Used:**
- Role management
- Entry ownership
- User profiles
- Sub-entries (reviews)
- Permission escalation

---

#### Use Case 4: SaaS Application (Team Collaboration)

**Requirements:**
- Team workspaces
- Project management
- File sharing
- Role-based access

**Setup:**
1. **Collections:**
   - `workspaces` (admin/owner creates)
   - `projects` (team members create)
   - `documents` (team members create)
   - `tasks` (team members create)

2. **Groups:**
   - Each workspace is a group
   - Members added to workspace group

3. **Permissions:**
   ```json
   {
     "workspaces": {
       "owner": {
         "read": true,
         "edit": true,
         "manage_members": true
       },
       "member": {
         "read": true
       }
     },
     "projects": {
       "group_member": {
         "create": true,
         "edit_all": true
       }
     }
   }
   ```

4. **Workflow:**
   - Admin creates workspace "Acme Corp"
   - Admin adds team members to workspace
   - Team members can create projects/documents
   - All workspace members can edit
   - Owner can manage members
   - If member leaves → loses access to workspace content

**Features Used:**
- User groups
- Group permissions
- Collection-level access
- Member management

---

#### Use Case 5: Community Forum

**Requirements:**
- Public registration
- Post moderation
   - User reputation
   - Badges/achievements

**Setup:**
1. **Collections:**
   - `categories` (admin only)
   - `topics` (members can create)
   - `posts` (sub-entries, members can create)
   - `user_reputation` (system tracks)

2. **Roles:**
   - `member` - Can post
   - `moderator` - Can moderate
   - `admin` - Full control

3. **Permissions:**
   ```json
   {
     "topics": {
       "role:member": {
         "create": true,
         "edit_own": true,
         "delete_own": true
       },
       "role:moderator": {
         "create": true,
         "edit_all": true,
         "delete_all": true,
         "moderate": true
       }
     }
   }
   ```

4. **Workflow:**
   - User registers → "member" role
   - Member creates topic
   - Topic needs moderation (if new member)
   - Moderator approves/rejects
   - Member posts replies (sub-entries)
   - System tracks: post count, reputation
   - High reputation → auto-promote to "trusted member"
   - Trusted members → posts auto-approved

**Features Used:**
- Public registration
- Role-based permissions
- Moderation workflow
- Activity tracking
- Auto-promotion (via hooks)

---

#### Use Case 6: Membership Site with Tiers

**Requirements:**
- Free content
- Premium content
- Multiple subscription tiers
- Content gating

**Setup:**
1. **Collections:**
   - `free_content` (public)
   - `premium_content` (tier-gated)
   - `exclusive_content` (highest tier)

2. **Tiers:**
   - `free` - Free content only
   - `basic` ($9/mo) - Free + basic premium
   - `premium` ($29/mo) - All content
   - `vip` ($99/mo) - All + exclusive + features

3. **Content Gating:**
   ```php
   // In template
   if (user_can_access($current_user, 'premium_content')) {
     // Show premium content
   } else {
     // Show upgrade CTA
   }
   ```

4. **Workflow:**
   - User registers → "free" tier
   - User browses free content
   - User clicks premium article → sees "Upgrade to access"
   - User upgrades via payment plugin
   - Tier changes → immediate access
   - User can access all premium content
   - Subscription expires → tier downgrades
   - User loses access, sees upgrade prompt again

**Features Used:**
- Tier system
- Payment integration
- Content gating
- Tier expiration handling

---

### Advanced Scenarios

#### Scenario 1: Multi-Tenant SaaS

**Challenge:** Multiple organizations, isolated data

**Solution:**
- Each organization = user group
- Collections scoped to groups
- Users can belong to multiple groups
- Group admin manages members

**Implementation:**
```json
{
  "user_groups": {
    "org_acme": {
      "type": "organization",
      "collections": {
        "projects": {"scope": "group"},
        "documents": {"scope": "group"}
      }
    }
  }
}
```

---

#### Scenario 2: Content Moderation Queue

**Challenge:** Review user submissions before publishing

**Solution:**
- New entries default to "pending"
- Moderators see queue
- Approve/reject workflow
- Auto-approve trusted users

**Implementation:**
- Entry status field: `pending`, `approved`, `rejected`
- Permission: `moderate_entries`
- Hook: `before_entry_publish` → check status
- Filter: `entry_auto_approve` → check user reputation

---

#### Scenario 3: User Onboarding Flow

**Challenge:** Guide new users through setup

**Solution:**
- Track onboarding step in user metadata
- Show different UI based on step
- Auto-assign permissions as they progress
- Completion badge/achievement

**Implementation:**
```json
{
  "user_metadata": {
    "onboarding_step": "profile_complete",
    "onboarding_completed": false
  }
}
```

- Hook: `after_user_register` → set step 1
- Hook: `user_profile_updated` → check if complete
- Action: `onboarding_completed` → grant full permissions

---

#### Scenario 4: Guest Checkout → Account Creation

**Challenge:** Allow purchases without account, then convert

**Solution:**
- Payment plugin creates "guest" user
- After purchase, prompt to create account
- Link guest order to new account
- Merge guest data

**Implementation:**
- Guest users: `role: guest`, `tier: none`
- After registration: `role: user`, link orders
- Hook: `user_registered_from_guest` → merge data

---

### Feature Combinations

#### Combination 1: User Profiles + Collections + Sub-Entries

**Use Case:** User portfolio site
- User profile (collection entry)
- Portfolio items (collection, owned by user)
- Portfolio comments (sub-entries)
- User can manage all their content from profile

#### Combination 2: Tiers + Groups + Permissions

**Use Case:** Premium community
- Free tier: Can read public posts
- Premium tier: Can create posts, join groups
- Group members: Can access private group content
- Group admin: Can manage group

#### Combination 3: Registration + Moderation + Tiers

**Use Case:** Exclusive community
- Invite-only registration
- Admin approval required
- Approved users get "member" tier
- Members can access member-only content

---

### Edge Cases & Considerations

1. **User Deletion:**
   - What happens to owned entries? (Option: Transfer to admin, Delete, Archive)
   - What about comments/sub-entries? (Option: Anonymize, Delete)

2. **Permission Conflicts:**
   - User has role permission + collection permission + entry permission
   - Resolution: Most specific wins (entry > collection > role)

3. **Tier Downgrade:**
   - User loses access to content
   - What about content they created? (Option: Read-only, Transfer ownership)

4. **Concurrent Edits:**
   - Two users edit same entry
   - Solution: Last-write-wins, or conflict resolution UI

5. **Bulk Operations:**
   - Admin changes 100 users' roles
   - Need batch permission updates
   - Need audit log for compliance

---

## 14. User Journeys & Interactions

### Journey 1: New User Registration → First Content Creation

**Step-by-Step:**
1. **Discovery**: User visits website, sees "Sign Up" button
2. **Registration**: 
   - Clicks "Sign Up"
   - Fills form: Name, Email, Password
   - Optional: Bio, Avatar upload
   - Submits form
3. **Verification** (if enabled):
   - Receives email: "Verify your email"
   - Clicks link → account activated
4. **Welcome**:
   - Redirected to dashboard
   - Sees welcome message
   - Sees available actions based on role
5. **First Action**:
   - Sees "Create Your First Post" prompt
   - Clicks → redirected to entry editor
   - Creates content
   - Saves/submits
6. **Feedback**:
   - If needs approval: "Your post is pending review"
   - If auto-published: "Your post is live!"
   - Sees link to published content

**UI Elements:**
- Registration form (customizable fields)
- Email verification page
- Welcome dashboard
- Onboarding tooltips
- First-action prompts

---

### Journey 2: Admin Managing Users

**Step-by-Step:**
1. **Access**: Admin logs in → sees "Users" in sidebar
2. **Overview**: 
   - User list page shows: Name, Email, Role, Status, Last Active
   - Filters: Role, Status, Registration Date
   - Search: By name, email
3. **User Details**:
   - Clicks user → detail page
   - Sees: Profile, Permissions, Activity, Content created
4. **Edit User**:
   - Changes role: "author" → "editor"
   - Adds to group: "Content Team"
   - Sets tier: "free" → "premium"
   - Saves changes
5. **Bulk Actions**:
   - Selects multiple users
   - Bulk action: "Change role to author"
   - Confirms → all updated
6. **Audit**:
   - All changes logged
   - Can see: Who changed what, when

**UI Elements:**
- User list table (sortable, filterable)
- User detail page (tabs: Profile, Permissions, Activity, Content)
- Role selector
- Permission matrix
- Bulk action toolbar
- Activity timeline

---

### Journey 3: User Creating & Managing Content

**Step-by-Step:**
1. **Dashboard**: User logs in → sees dashboard
2. **Content Overview**:
   - "My Content" section
   - Shows: Published (5), Drafts (2), Pending (1)
3. **Create New**:
   - Clicks "Create Entry"
   - Selects collection: "Blog Posts"
   - Fills form: Title, Content, Tags
   - Sets status: "Draft" or "Submit for Review"
   - Saves
4. **Edit Own Content**:
   - Sees list of own entries
   - Clicks entry → edit
   - Makes changes
   - Saves
5. **Collaboration** (if enabled):
   - Invites collaborator to entry
   - Collaborator can edit
   - Sees edit history
6. **Permissions Check**:
   - Tries to edit someone else's entry
   - Gets: "You don't have permission"
   - Or: "You can edit this entry" (if granted)

**UI Elements:**
- Content dashboard
- Entry list (filtered by user)
- Entry editor
- Permission indicators
- Collaboration panel
- Edit history

---

### Journey 4: Payment Integration → Tier Upgrade

**Step-by-Step:**
1. **Discovery**: User sees premium content, clicks "Upgrade"
2. **Payment**:
   - Redirected to payment page (payment plugin)
   - Selects tier: "Premium ($29/mo)"
   - Enters payment info
   - Completes payment
3. **Upgrade**:
   - Payment plugin calls: `setUserTier($user_id, "premium")`
   - User plugin updates tier
   - Triggers: `do_action('user_tier_changed', $user_id, 'free', 'premium')`
4. **Access Granted**:
   - User redirected back to site
   - Sees: "Welcome to Premium!"
   - Can now access premium content
   - Sees new features enabled
5. **Ongoing**:
   - User uses premium features
   - Monthly payment processed
   - If payment fails → tier downgrades
   - User notified: "Your subscription expired"

**UI Elements:**
- Upgrade prompts
- Tier comparison table
- Payment form
- Success message
- Feature indicators
- Subscription status

---

### Journey 5: Moderator Reviewing Content

**Step-by-Step:**
1. **Queue**: Moderator logs in → sees "Moderation Queue"
2. **Review**:
   - Sees list: Pending entries (10)
   - Clicks entry → preview
   - Reads content
   - Checks: Author, Date, Content quality
3. **Decision**:
   - **Approve**: Clicks "Approve" → entry published
   - **Reject**: Clicks "Reject" → entry deleted, author notified
   - **Edit**: Clicks "Edit" → makes changes, then approves
4. **Bulk Moderation**:
   - Selects multiple entries
   - Bulk action: "Approve All"
   - Confirms → all approved
5. **Auto-Moderation** (if enabled):
   - Trusted users' content auto-approved
   - New users' content needs review
   - After X approved posts → auto-approve enabled

**UI Elements:**
- Moderation queue
- Entry preview
- Approve/Reject buttons
- Edit inline
- Bulk actions
- Moderation history

---

## 15. Practical Implementation Examples

### Example 1: Using Template Functions (Primary Method)

```html
<!-- In user's template file (e.g., templates/blog.html) -->
<!-- Plugin provides template functions - use them directly in templates -->

{if user_logged_in}
    <div class="user-menu">
        <p>Welcome, {user_current.name}!</p>
        
        {if user_can 'create_entries' 'blog'}
            <a href="/admin/create-post">Create Post</a>
        {endif}
        
        {if user_can 'edit_entry' entry.id}
            <a href="/admin/edit-post?id={entry.id}">Edit This Post</a>
        {endif}
        
        {if user_tier == 'premium'}
            <div class="premium-badge">Premium Member</div>
            <!-- Show premium content -->
            <div class="premium-content">
                {premium_content}
            </div>
        {else}
            <a href="/upgrade">Upgrade to Premium</a>
        {endif}
    </div>
{else}
    <a href="/login">Login</a>
    <a href="/register">Register</a>
{endif}
```

**How it works:**
1. Plugin registers `user_logged_in`, `user_current`, `user_can`, `user_tier` as template functions
2. Template parser calls these functions when rendering
3. Functions return data that gets inserted into template
4. No REST API needed - everything works in templates

### Example 1b: Optional REST API (For Advanced Use Cases)

```php
<?php
// Optional: Plugin provides REST API for AJAX, SPAs, etc.
// But template functions are PRIMARY interface

// API: /api/user_permissions.php (optional)
header('Content-Type: application/json');

// Plugin's own function (self-contained)
$userService = new UserService();
$current_user = $userService->getCurrentUser();

$permissions = [
    'can_edit_entry' => $userService->userCan($current_user['id'], 'edit_entry', $_GET['entry_id']),
    'can_create_entries' => $userService->userCan($current_user['id'], 'create_entries', $_GET['collection']),
    'tier' => $userService->getUserTier($current_user['id']),
];

echo json_encode($permissions);
?>
```

```javascript
// Optional: User's frontend can use REST API for AJAX
// But template functions are simpler for most cases

fetch('/api/user_permissions.php?entry_id=123&collection=blog')
  .then(response => response.json())
  .then(permissions => {
    // Use for dynamic updates, SPAs, etc.
  });
```

---

### Example 2: User Registration (Template-First Approach)

```html
<!-- User's template: templates/register.html -->
<!-- Plugin provides template functions for registration -->

<form method="POST" action="/register">
    <input type="text" name="name" placeholder="Name" required>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Password" required>
    
    {if registration_error exist}
        <div class="error">{registration_error}</div>
    {endif}
    
    {if registration_success exist}
        <div class="success">Registration successful! <a href="/login">Login here</a></div>
    {endif}
    
    <button type="submit">Register</button>
</form>
```

```php
<?php
// User's page handler (e.g., render_page.php or custom router)
// Plugin provides template function, user handles form submission

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['email']) {
    // Use plugin's service (self-contained)
    $userService = new UserService();
    
    try {
        $user_id = $userService->createUser([
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'password' => $_POST['password']
        ]);
        
        // Set success message for template
        $template_data['registration_success'] = true;
    } catch (Exception $e) {
        $template_data['registration_error'] = $e->getMessage();
    }
}

// Render template with data
echo render_template_file('templates/register.html', $template_data);
?>
```

### Example 2b: Optional REST API (For AJAX Forms)

```php
<?php
// Optional: Plugin provides REST API for AJAX registration
// plugins/user_management/api/user_registration.php

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$userService = new UserService(); // Plugin's own class

try {
    $user_id = $userService->createUser($input);
    echo json_encode(['status' => 'success', 'user_id' => $user_id]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
```

```javascript
// Optional: User's frontend can use REST API for AJAX forms
fetch('/api/user_registration.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(formData)
})
.then(response => response.json())
.then(result => {
    // Handle response
});
```

---

### Example 3: Custom Registration Hook

```php
<?php
// In plugin or theme
register_hook('after_user_register', function($user_id, $user_data) {
    // Auto-assign to group
    $userService = get_plugin_service('user_service');
    $userService->addUserToGroup($user_id, 'new_members');
    
    // Send welcome email
    send_user_email($user_id, 'welcome', [
        'name' => $user_data['name']
    ]);
    
    // Create default profile entry
    createEntry('user_profiles', [
        'user_id' => $user_id,
        'name' => $user_data['name'],
        'bio' => '',
        'avatar' => ''
    ]);
    
    // Log registration
    log_user_activity($user_id, 'registered', [
        'source' => 'website',
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
});
?>
```

---

### Example 4: Content Gating API (Plugin Provides)

```php
<?php
// Plugin API: /api/user_tier_check.php
// Returns JSON with tier access info

header('Content-Type: application/json');

$entry_id = $_GET['entry_id'] ?? null;
$current_user = getCurrentUser();
$userService = get_plugin_service('user_service');

// Get entry (from collection system)
$entry = getEntry('premium_content', $entry_id);
$required_tier = $entry['required_tier'] ?? null;

if ($required_tier) {
    $user_tier = $userService->getUserTier($current_user['id']);
    $tier_levels = ['free' => 0, 'basic' => 1, 'premium' => 2, 'vip' => 3];
    
    $user_level = $tier_levels[$user_tier] ?? 0;
    $required_level = $tier_levels[$required_tier] ?? 0;
    
    $has_access = $user_level >= $required_level;
    
    echo json_encode([
        'has_access' => $has_access,
        'user_tier' => $user_tier,
        'required_tier' => $required_tier,
        'content' => $has_access ? $entry['content'] : null
    ]);
} else {
    // Public content
    echo json_encode([
        'has_access' => true,
        'content' => $entry['content']
    ]);
}
?>
```

```javascript
// User's frontend (custom content rendering)
// User calls API, receives JSON, renders in their custom UI

async function renderContent(entryId) {
    const response = await fetch(`/api/user_tier_check.php?entry_id=${entryId}`);
    const data = await response.json();
    
    if (data.has_access) {
        // User's custom content rendering
        document.getElementById('content-area').innerHTML = data.content;
    } else {
        // User's custom upgrade prompt
        document.getElementById('content-area').innerHTML = `
            <div class="custom-gated-content">
                <h2>Premium Content</h2>
                <p>This content requires ${data.required_tier} tier.</p>
                <a href="/upgrade" class="custom-upgrade-button">Upgrade Now</a>
            </div>
        `;
    }
}
```

---

### Example 5: User Activity Feed

```php
<?php
// Get user activity
function getUserActivity($user_id, $limit = 10) {
    $activities = [];
    
    // Get entries created
    $entries = getEntries('blog', ['author_id' => $user_id], $limit);
    foreach ($entries as $entry) {
        $activities[] = [
            'type' => 'entry_created',
            'title' => $entry['title'],
            'date' => $entry['created_at'],
            'url' => '/blog/' . $entry['slug']
        ];
    }
    
    // Get comments made
    $comments = getSubEntries('blog_comments', ['author_id' => $user_id], $limit);
    foreach ($comments as $comment) {
        $activities[] = [
            'type' => 'comment_created',
            'title' => 'Commented on: ' . $comment['parent_title'],
            'date' => $comment['created_at'],
            'url' => '/blog/' . $comment['parent_slug'] . '#comment-' . $comment['id']
        ];
    }
    
    // Sort by date
    usort($activities, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    
    return array_slice($activities, 0, $limit);
}

// Display in template
$activities = getUserActivity($current_user['id']);
foreach ($activities as $activity) {
    echo '<div class="activity-item">';
    echo '<span class="activity-type">' . $activity['type'] . '</span>';
    echo '<a href="' . $activity['url'] . '">' . $activity['title'] . '</a>';
    echo '<span class="activity-date">' . $activity['date'] . '</span>';
    echo '</div>';
}
?>
```

---

### Example 6: Permission Matrix UI

```html
<!-- Admin UI for setting permissions -->
<div class="permission-matrix">
    <table>
        <thead>
            <tr>
                <th>Collection</th>
                <th>Create</th>
                <th>Edit Own</th>
                <th>Edit All</th>
                <th>Delete Own</th>
                <th>Delete All</th>
                <th>Publish</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Blog Posts</td>
                <td><input type="checkbox" data-permission="create_entries:blog"></td>
                <td><input type="checkbox" data-permission="edit_own:blog"></td>
                <td><input type="checkbox" data-permission="edit_all:blog"></td>
                <td><input type="checkbox" data-permission="delete_own:blog"></td>
                <td><input type="checkbox" data-permission="delete_all:blog"></td>
                <td><input type="checkbox" data-permission="publish:blog"></td>
            </tr>
            <!-- More collections... -->
        </tbody>
    </table>
</div>

<script>
// Save permissions
function savePermissions(userId, permissions) {
    fetch('/api/user_admin.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'update_permissions',
            user_id: userId,
            permissions: permissions
        })
    });
}
</script>
```

---

### Example 7: User Search & Filter

```php
<?php
// Search users
function searchUsers($query, $filters = []) {
    $userService = get_plugin_service('user_service');
    
    $search_params = [
        'query' => $query,
        'filters' => $filters
    ];
    
    // Search by name, email, username
    $users = $userService->findUsers($search_params);
    
    return $users;
}

// In template
$filters = [
    'role' => $_GET['role'] ?? null,
    'tier' => $_GET['tier'] ?? null,
    'status' => $_GET['status'] ?? 'active',
    'registered_after' => $_GET['date_from'] ?? null
];

$users = searchUsers($_GET['q'] ?? '', $filters);

foreach ($users as $user) {
    echo '<div class="user-card">';
    echo '<img src="' . $user['avatar'] . '" alt="' . $user['name'] . '">';
    echo '<h3>' . $user['name'] . '</h3>';
    echo '<p>' . $user['bio'] . '</p>';
    echo '<a href="/users/' . $user['username'] . '">View Profile</a>';
    echo '</div>';
}
?>
```

---

## 16. Integration Patterns

### Pattern 1: User Plugin + Payment Plugin

```php
<?php
// Payment plugin calls user plugin
$userService = get_plugin_service('user_service');

// After successful payment
register_hook('payment_completed', function($payment_data) {
    $user_id = $payment_data['user_id'];
    $tier_id = $payment_data['tier_id'];
    
    // Upgrade user tier
    $userService->setUserTier($user_id, $tier_id);
    
    // Grant tier-specific permissions
    $tier_permissions = $userService->getTierPermissions($tier_id);
    foreach ($tier_permissions as $permission) {
        $userService->setUserPermission($user_id, $permission, null, true);
    }
    
    // Send confirmation email
    send_user_email($user_id, 'tier_upgraded', [
        'tier' => $tier_id
    ]);
});

// On payment failure
register_hook('payment_failed', function($payment_data) {
    $user_id = $payment_data['user_id'];
    
    // Downgrade tier
    $userService->setUserTier($user_id, 'free');
    
    // Revoke premium permissions
    // ...
});
?>
```

---

### Pattern 2: User Plugin + Notification Plugin

```php
<?php
// User plugin triggers notifications
register_hook('after_user_register', function($user_id, $user_data) {
    do_action('send_notification', [
        'type' => 'user_registered',
        'user_id' => $user_id,
        'recipients' => ['admin'],
        'data' => $user_data
    ]);
});

register_hook('user_tier_changed', function($user_id, $old_tier, $new_tier) {
    do_action('send_notification', [
        'type' => 'tier_changed',
        'user_id' => $user_id,
        'recipients' => [$user_id],
        'data' => ['old_tier' => $old_tier, 'new_tier' => $new_tier]
    ]);
});
?>
```

---

### Pattern 3: User Plugin + Analytics Plugin

```php
<?php
// Track user actions
register_hook('user_login', function($user_id) {
    do_action('track_event', [
        'event' => 'user_login',
        'user_id' => $user_id,
        'timestamp' => time()
    ]);
});

register_hook('after_user_register', function($user_id, $user_data) {
    do_action('track_event', [
        'event' => 'user_registered',
        'user_id' => $user_id,
        'source' => $user_data['source'] ?? 'website'
    ]);
});
?>
```

---

## 17. Admin UI Mockups (Conceptual)

### User List Page
```
┌─────────────────────────────────────────────────────────┐
│ Users                                    [+ Add New]     │
├─────────────────────────────────────────────────────────┤
│ [Search users...]  [Filter: Role ▼] [Filter: Status ▼] │
├─────────────────────────────────────────────────────────┤
│ ☐ Name          Email           Role    Status  Actions│
│ ☐ John Doe      john@...        Author  Active  [Edit]  │
│ ☐ Jane Smith    jane@...        Editor  Active  [Edit]  │
│ ☐ Bob Wilson    bob@...         User    Pending [Edit]  │
│                                                          │
│ [Bulk Actions ▼] [Apply]                                │
└─────────────────────────────────────────────────────────┘
```

### User Detail Page
```
┌─────────────────────────────────────────────────────────┐
│ John Doe                                    [Edit] [Delete]│
├─────────────────────────────────────────────────────────┤
│ [Profile] [Permissions] [Activity] [Content]           │
├─────────────────────────────────────────────────────────┤
│ Name: John Doe                                           │
│ Email: john@example.com                                 │
│ Role: Author                                            │
│ Tier: Premium                                           │
│ Status: Active                                          │
│                                                          │
│ Bio: Content creator and blogger...                     │
│                                                          │
│ Groups: Content Team, Writers                           │
│                                                          │
│ Registered: 2024-01-15                                  │
│ Last Active: 2024-01-20 14:30                          │
└─────────────────────────────────────────────────────────┘
```

### Permission Editor
```
┌─────────────────────────────────────────────────────────┐
│ Edit Permissions: John Doe                               │
├─────────────────────────────────────────────────────────┤
│ Collection: Blog Posts                                   │
│ ☑ Create Entries                                        │
│ ☑ Edit Own Entries                                      │
│ ☐ Edit All Entries                                      │
│ ☑ Delete Own Entries                                    │
│ ☐ Delete All Entries                                    │
│ ☐ Publish Entries                                       │
│                                                          │
│ Collection: Products                                     │
│ ☐ Create Entries                                        │
│ ☑ Read Entries                                          │
│                                                          │
│ [Save Permissions]                                       │
└─────────────────────────────────────────────────────────┘
```

---

This comprehensive expansion covers:
- ✅ Detailed feature walkthroughs with step-by-step flows
- ✅ Real-world use cases from different industries
- ✅ Advanced scenarios and edge cases
- ✅ User journeys from different perspectives
- ✅ Practical code examples
- ✅ Integration patterns
- ✅ UI mockups

The document now provides a complete picture of how the user plugin would work in practice!

---

## 14. Questions to Consider

1. **User Storage**: Collection-based vs. separate storage vs. hybrid?
2. **Username vs. Email**: Support both? Username for display, email for login?
3. **User IDs**: UUID vs. sequential vs. hash-based?
4. **Avatar Storage**: Media library integration?
5. **Password Reset**: Email-based or admin-only?
6. **User Deletion**: Soft delete (archive) vs. hard delete?
7. **Multi-site**: Support multiple sites with shared users?
8. **API Access**: REST API for user management?
9. **Webhooks**: Notify external systems on user events?

---

## 15. Technical Considerations

### Self-Contained Plugin Requirements

**Critical: Plugin must work with just copy/paste**

1. **All Code in Plugin:**
   - No dependencies on core functions (except hooks/filters)
   - All classes, functions, helpers defined in plugin
   - Plugin can be copied to any Birkly installation

2. **Self-Registration:**
   ```php
   // plugin.php automatically registers everything
   UserManagementPlugin::init();
   ```

3. **No External Dependencies:**
   - Plugin doesn't require other plugins
   - Plugin doesn't require specific core versions (just hooks/filters)
   - Plugin works standalone

4. **Installation:**
   ```
   1. Copy plugins/user_management/ folder
   2. Plugin auto-loads on next page load
   3. Plugin registers itself via hooks
   4. Done!
   ```

### Template Function Integration

**How Template Functions Work:**

1. **Plugin Registers Functions:**
   ```php
   register_hook('template_functions', function() {
       return [
           'user_current' => [UserManagementPlugin::class, 'getCurrentUser'],
           'user_can' => [UserManagementPlugin::class, 'userCan'],
       ];
   });
   ```

2. **Template Parser Calls Functions:**
   ```html
   {if user_current exist}
       {user_current.name}
   {endif}
   ```
   → Parser calls `UserManagementPlugin::getCurrentUser()`
   → Returns user data
   → Template renders with data

3. **Functions Must Return Template-Compatible Data:**
   - Strings, numbers, arrays, objects
   - No complex objects (convert to arrays)
   - Functions should handle errors gracefully

### Data Isolation & Safety

**Critical Requirements:**
1. **All plugin data in `data/plugins/user_management/`**
   - Never store in `core/`, `content/`, or `settings/` (except plugin state)
   - System updates will NEVER touch `data/` directory
   - Plugin can be safely disabled/removed

2. **Data Backup Strategy:**
   ```
   Backup these directories:
   - data/plugins/user_management/  (all user data)
   - content/collections/users/     (if using collection for profiles)
   - settings/plugins.json          (plugin states)
   ```

3. **Migration Safety:**
   - Plugin data structure can change (with migrations)
   - Core system updates won't affect plugin data
   - Plugin updates handle their own data migrations

### Template-First Architecture (Primary Interface)

**Plugin Provides:**
- **Template functions** (PRIMARY) - Work directly in templates
- Service classes for programmatic access
- Hooks and filters for extensibility
- Optional REST API endpoints (for AJAX, SPAs)
- Optional admin UI templates (can be overridden)

**User Provides:**
- Templates using template functions
- Custom HTML/CSS/JS
- Form handling (can use template functions or REST API)

**Primary Communication (Template Functions):**
```
User's Template
    ↓ Template Function Call
Plugin Template Function
    ↓ Business Logic
Plugin Service Class
    ↓ Data Access
data/plugins/user_management/ (Isolated Data)
    ↓ Return Data
Template Rendered with Data
```

**Optional Communication (REST API):**
```
User's Frontend (AJAX/SPA)
    ↓ HTTP Request
Plugin API Endpoint (Optional)
    ↓ Business Logic
Plugin Service Class
    ↓ Data Access
data/plugins/user_management/ (Isolated Data)
    ↓ JSON Response
Frontend Updates UI
```

### Performance
- Index user lookups (email, username)
- Cache user permissions
- Lazy load user data
- Pagination for user lists
- API response caching (optional)

### Scalability
- Support thousands of users
- Efficient permission checking
- Optimize queries
- API rate limiting

### Compatibility
- Work with existing auth system
- Don't break existing plugins
- Backward compatible
- Data isolation ensures system updates don't break plugin

---

## 18. Complete Example: Self-Contained Plugin Structure

### Plugin File Structure

```
plugins/user_management/
├── plugin.php                    # Bootstrap - self-registers
├── plugin.json                   # Metadata
├── includes/                     # ALL plugin code (self-contained)
│   ├── UserService.php          # Main service class
│   ├── UserStorage.php          # Data storage (isolated)
│   ├── UserAuth.php             # Authentication
│   ├── UserPermissions.php      # Permissions
│   ├── UserTiers.php            # Tiers
│   └── TemplateHelpers.php      # Template functions
├── api/                          # Optional REST APIs
│   └── user_registration.php
└── examples/                     # Example templates
    └── register.html
```

### Complete Plugin Bootstrap Example

```php
<?php
// plugins/user_management/plugin.php
// Fully self-contained plugin bootstrap

// Check if plugin system is available
if (!function_exists('register_hook')) {
    return; // System not ready
}

// Load all plugin code (self-contained, no external dependencies)
$plugin_dir = __DIR__;
require_once $plugin_dir . '/includes/UserStorage.php';
require_once $plugin_dir . '/includes/UserService.php';
require_once $plugin_dir . '/includes/UserAuth.php';
require_once $plugin_dir . '/includes/UserPermissions.php';
require_once $plugin_dir . '/includes/UserTiers.php';
require_once $plugin_dir . '/includes/TemplateHelpers.php';

// Initialize plugin (self-registers everything)
UserManagementPlugin::init();
```

### Template Function Usage Example

```html
<!-- User's template: templates/blog.html -->
<!-- Uses plugin's template functions directly -->

{if user_logged_in}
    <div class="user-info">
        <p>Logged in as: {user_current.name}</p>
        <p>Role: {user_current.role}</p>
        <p>Tier: {user_tier}</p>
        
        {if user_can 'create_entries' 'blog'}
            <a href="/create-post">Create New Post</a>
        {endif}
    </div>
{else}
    <a href="/login">Login</a>
{endif}

{for each entry in blog}
    <article>
        <h2>{entry.title}</h2>
        
        {if user_can 'edit_entry' entry.id}
            <a href="/edit-post?id={entry.id}">Edit</a>
        {endif}
        
        {if user_tier == 'premium' || entry.public == true}
            <div>{entry.content}</div>
        {else}
            <p>Upgrade to premium to view this content</p>
        {endif}
    </article>
{endfor}
```

### How It All Works Together

1. **Installation:**
   - Copy `plugins/user_management/` folder
   - Plugin auto-loads on next request
   - `plugin.php` runs → loads all includes → registers hooks

2. **Template Usage:**
   - User writes template with `{user_current}`, `{user_can}`, etc.
   - Template parser calls registered functions
   - Functions return data → template renders

3. **Data Storage:**
   - All data in `data/plugins/user_management/`
   - Completely isolated from core
   - Safe from system updates

4. **Optional REST API:**
   - Available for AJAX, SPAs, mobile apps
   - Not required for basic functionality
   - Template functions are primary interface

---

## Conclusion

This user plugin should be:

### Core Principles
- **Self-Contained**: All code in plugin, copy/paste to install
- **Template-First**: Primary interface via template functions
- **Secure**: Privacy-first, encrypted sensitive data, isolated storage
- **Flexible**: Granular permissions, extensible via hooks/filters
- **Integrated**: Deep collection/entry integration
- **Simple**: Easy to use, but powerful
- **Extensible**: Hooks, filters, services for other plugins

### Key Differentiators
1. **Template Integration**: Users build websites using template language, not REST APIs
2. **Self-Contained**: Plugin works standalone, no dependencies
3. **Data Isolation**: All data safe from system updates
4. **Deep Integration**: Users are part of content system with fine-grained permissions

### Installation
```
1. Copy plugins/user_management/ folder
2. Done! Plugin auto-registers and works
```

### Usage
```html
<!-- In templates -->
{if user_logged_in}
    Welcome {user_current.name}!
{endif}
```

The plugin is **template-first, self-contained, and completely isolated** - perfect for a flexible, maintainable system.
