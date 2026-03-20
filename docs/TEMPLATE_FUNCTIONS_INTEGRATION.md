# Template Functions Integration - Analysis & Alignment

## Summary

Template function registration has been **integrated into the Plugin System Architecture Plan** as a new "Template Shelf" in the Rooms and Shelves model. This document explains why it was added, how it aligns with the existing architecture, and any differences from the original plan.

---

## Why Template Functions Were Added

### Discovery During User Plugin Design

While analyzing the plugin system's readiness for the user management plugin, we discovered that:

1. **Template Parser Limitation**: The template parser only handles variables from data arrays, not function calls
2. **User Plugin Requirement**: The user plugin needs template functions as its PRIMARY interface (`{user_current}`, `{user_can}`, etc.)
3. **Template-First Architecture**: Users should build websites using template language, not just REST APIs
4. **Missing Feature**: No mechanism existed for plugins to register template-callable functions

### Impact Assessment

**Priority**: 🔴 **CRITICAL**

- Without template functions, user plugin cannot provide template-first interface
- Users would be forced to use REST APIs + JavaScript (not ideal for template-based sites)
- This is the PRIMARY interface for user-facing plugins
- All other plugin systems are ready; this was the only missing piece

---

## How It Aligns with Initial Architecture

### Perfect Integration with "Rooms and Shelves" Model

**Template Functions as a New Shelf:**

```
The Core Room
  └─ Template Shelf (NEW)
      └─ Plugins place functions here
          └─ Template parser calls functions during rendering
```

**Conceptual Mapping:**
- **Shelf Name**: `template_functions`
- **Location**: Template parser (rendering phase)
- **Registry**: `$haru_template_functions` (in The Lobby)
- **Access Control**: Same Door Policy (capability checks, denylist, active plugin filtering)

### Alignment with Existing Systems

| System | Purpose | Template Functions |
|--------|---------|-------------------|
| **Hooks** | System events (before/after actions) | Different purpose - template rendering |
| **Services** | Plugin-to-plugin communication | Different purpose - template rendering |
| **UI Injection** | Admin panel components | Different purpose - template rendering |
| **Template Functions** | Template rendering (user-facing) | **NEW** - Fills gap |

**Key Point**: Template functions don't replace or conflict with existing systems. They serve a different purpose and complement them.

### Security Model Alignment

**Same Security Model:**
- ✅ Capability checks (optional per function)
- ✅ Denylist protection (applies to function access)
- ✅ Active plugin filtering (automatic)
- ✅ Output sanitization (automatic)
- ✅ Error isolation (try/catch)

**Example:**
```php
// Function can require capability
register_template_function('user_can', $callback, [
    'required_capability' => 'read_users'
]);

// Core checks capability before allowing function registration
// Template parser checks active plugin before calling function
```

### Registration Pattern Alignment

**Same Pattern as Other Systems:**

```php
// Services
register_plugin_service('user_service', $service, $options);

// UI Injection
register_ui_injection('user_management', 'collection_editor_sidebar', $component, $options);

// Template Functions (NEW)
register_template_function('user_current', $callback, $options);
```

**Consistency:**
- All use `register_*()` pattern
- All store in global registries (The Lobby)
- All check capabilities/permissions
- All filter by active plugins

---

## Differences from Initial Plan

### 1. New Feature Addition

**Original Plan**: Focused on:
- Admin UI injection
- System hooks
- Plugin-to-plugin services
- Capabilities & permissions

**New Addition**: Template function registration

**Why Not in Original Plan:**
- Original plan focused on admin/backend functionality
- User-facing template rendering wasn't considered
- Discovered during user plugin design phase
- Template-first architecture requirement emerged

**Impact**: **Enhancement, not breaking change**
- Adds new capability
- Doesn't modify existing systems
- No conflicts with original design

### 2. Template-First vs API-First Philosophy

**Original Plan**: API-first approach
- Plugins provide REST APIs
- Frontend calls APIs
- JavaScript-heavy approach

**New Requirement**: Template-first approach
- Plugins provide template functions
- Templates call functions directly
- Minimal JavaScript needed

**Resolution**: **Both are supported**
- Template functions for template-based sites (PRIMARY)
- REST APIs for SPAs, AJAX, mobile apps (OPTIONAL)
- Plugins can provide both

**Example:**
```php
// Template function (PRIMARY)
register_template_function('user_current', $callback);

// REST API (OPTIONAL)
register_api_endpoint('user_auth', __DIR__ . '/api/user_auth.php');
```

### 3. Public-Facing vs Admin-Only

**Original Plan**: Focused on admin panel
- UI injection for admin
- Hooks for admin operations
- Services for admin plugins

**New Addition**: Public-facing templates
- Template functions for user websites
- Functions called in public templates
- User-facing functionality

**Resolution**: **Extends scope**
- Original systems still work for admin
- Template functions add public-facing capability
- Both use same security model

---

## Implementation Details

### Files Required

1. **New File**: `core/plugins/template_functions.php`
   - Template function registration API
   - Function retrieval
   - Security checks

2. **Modification**: `admin/template_parser.php`
   - Add function call detection
   - Add function invocation
   - Integrate with existing parser

3. **Integration**: `core/plugins.php`
   - Load template functions system
   - After other plugin systems

### Code Structure

```php
// Registration (Plugin Room)
register_template_function('user_current', function() {
    // Plugin code
    return $user_data;
});

// Retrieval (Core Room - Template Parser)
$func = get_template_function('user_current');
$result = call_user_func($func);

// Template Usage (User's Template)
{user_current.name}
```

### Security Flow

```
Template: {user_current}
    ↓
Parser: Detects function call
    ↓
Core: get_template_function('user_current')
    ↓
Check 1: Is plugin active? ✓
    ↓
Check 2: Does function require capability? (if yes, check) ✓
    ↓
Check 3: Is resource on denylist? (if context provided) ✓
    ↓
Execute: Call function
    ↓
Sanitize: Escape output
    ↓
Render: Insert into template
```

---

## Integration Checklist

### Architecture Plan Updates ✅

- [x] Added "Template Shelves" to Shelves list
- [x] Added template functions to Core Room responsibilities
- [x] Added template functions to The Lobby registries
- [x] Added template functions to Core Principles
- [x] Added implementation section with code
- [x] Added security considerations
- [x] Added example usage
- [x] Added to implementation phases
- [x] Updated plugin examples

### Alignment Verification ✅

- [x] Follows "Rooms and Shelves" model
- [x] Uses same security model
- [x] Uses same registration pattern
- [x] Complements existing systems
- [x] No conflicts identified
- [x] No breaking changes

### Documentation ✅

- [x] Explained why it was added
- [x] Explained how it aligns
- [x] Noted differences from original plan
- [x] Provided implementation details
- [x] Included examples

---

## Conclusion

### Alignment Status: ✅ **PERFECT ALIGNMENT**

Template function registration:
- ✅ Fits perfectly into "Rooms and Shelves" model
- ✅ Uses same security model
- ✅ Follows same patterns
- ✅ Complements existing systems
- ✅ No conflicts or breaking changes

### Differences: ✅ **ENHANCEMENT, NOT CHANGE**

- **New Feature**: Adds template function capability
- **Scope Extension**: Adds public-facing template support
- **Philosophy Addition**: Template-first alongside API-first
- **No Breaking Changes**: All existing systems unchanged

### Implementation Priority: 🔴 **CRITICAL**

Must be implemented before user plugin development:
- User plugin's PRIMARY interface
- Template-first architecture requirement
- Only missing piece (all other systems ready)
- Estimated effort: 2-4 hours

### Next Steps

1. ✅ Architecture plan updated
2. ⏳ Implement template function system
3. ⏳ Test with simple plugin
4. ⏳ Start user plugin development

---

**Document Version:** 1.0  
**Created:** 2025-01-20  
**Status:** Analysis Complete - Ready for Implementation
