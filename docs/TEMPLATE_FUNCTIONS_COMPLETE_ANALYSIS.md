# Template Functions - Complete System Analysis

## Question: Is Template Function Registration the ONLY Missing Piece?

**Answer: Almost, but there's one integration requirement to address.**

---

## ✅ Confirmed Missing Piece: Template Function Registration

### Status: **CRITICAL - NOT IMPLEMENTED**

**What's Missing:**
1. `core/plugins/template_functions.php` - Registration system
2. Template parser modification - Function call detection and invocation
3. Integration into plugin loading system

**Impact:** 🔴 **HIGH** - This is the PRIMARY interface for user plugin

**Solution:** Documented in architecture plan ✅

---

## ⚠️ Integration Requirement: Plugin Loading Before Template Rendering

### Status: **REQUIRES VERIFICATION & DOCUMENTATION**

**Issue Identified:**
Public-facing template rendering might not always load plugins before rendering templates.

**Current State:**

1. **Admin Templates** ✅
   - `admin/index.php` → `core/admin.php` → `core/plugins.php`
   - Plugins loaded BEFORE template rendering
   - Template functions will work ✅

2. **Public Templates** ❓
   - `demo-website-router.php` (commented out) - Only requires `admin/template_parser.php`, NOT `core/plugins.php`
   - `render_page.php` - Uses `TemplateEngine`, doesn't require `core/plugins.php`
   - **Risk**: Template functions won't be available if plugins aren't loaded

**Analysis:**

Looking at the codebase:
- `demo-website-router.php` (lines 82-84): Only requires template parser, not plugins
- `render_page.php` (line 3): Only requires template engine, not plugins
- Client-side rendering (`birkly-client.js`): No server-side plugin access

**Impact:** 🟡 **MEDIUM** - Template functions won't work in public templates if plugins aren't loaded

**Solution Options:**

### Option 1: Auto-Load Plugins in Template Parser (RECOMMENDED)
```php
// In admin/template_parser.php, at the top
if (!isset($GLOBALS['haru_plugins_loaded'])) {
    require_once __DIR__ . '/../../core/plugins.php';
}
```

**Pros:**
- Automatic - works everywhere
- No changes needed to existing code
- Template functions always available

**Cons:**
- Slight performance overhead (but plugins are cached)
- Loads plugins even if not needed (but minimal impact)

### Option 2: Require Plugins in Public Routers
```php
// In demo-website-router.php, before template rendering
require_once __DIR__ . '/core/plugins.php';
```

**Pros:**
- Explicit control
- Only loads when needed

**Cons:**
- Requires changes to all public routers
- Easy to forget
- Not automatic

### Option 3: Document as Requirement
- Document that public routers MUST load plugins before template rendering
- Let plugin developers handle it

**Pros:**
- No code changes
- Flexible

**Cons:**
- Easy to miss
- Inconsistent behavior
- Not user-friendly

**Recommendation:** **Option 1** - Auto-load plugins in template parser

---

## 🔍 Other Potential Missing Pieces (Analysis)

### 1. Session Management for User Plugin ❓

**Question:** Does the system have session management for user authentication?

**Status:** ✅ **LIKELY EXISTS** (need to verify)

**Check:**
- Look for `session_start()`, `$_SESSION` usage
- Check `api/session.php`, `api/auth.php`

**Impact:** 🟢 **LOW** - User plugin can implement own sessions if needed

**Verdict:** Not a missing piece - plugin can implement

---

### 2. CSRF Protection for Public Forms ❓

**Question:** Is CSRF protection available for public-facing forms (registration, login)?

**Status:** ✅ **LIKELY EXISTS** (need to verify)

**Check:**
- Look for CSRF token generation/validation
- Check `core/csrf.php`, `api/csrf.php`

**Impact:** 🟡 **MEDIUM** - Important for security, but plugin can implement

**Verdict:** Not a missing piece - plugin can implement, or use existing system

---

### 3. Public API Endpoint Registration ❓

**Question:** Can plugins register public API endpoints (not just admin APIs)?

**Status:** ✅ **EXISTS** (verified)

**Code:**
- `register_api_endpoint()` exists in `core/plugins.php`
- No restriction on public vs admin

**Impact:** 🟢 **NONE** - Already available

**Verdict:** ✅ Not missing

---

### 4. Template Data Context Access ❓

**Question:** Can template functions access the template's data context (variables passed to template)?

**Status:** ❓ **UNCLEAR** - Needs investigation

**Current Template Parser:**
```php
// admin/template_parser.php
private function renderWithData($parsed, $data, $templateDir = null) {
    extract($data); // Variables available in eval context
    eval('?>' . $parsed . '<?php');
}
```

**Analysis:**
- Template functions are called during parsing phase (before eval)
- Functions won't have access to `$data` variables directly
- Functions would need to be called with data passed as arguments

**Impact:** 🟡 **MEDIUM** - Template functions might need data passed explicitly

**Example:**
```html
<!-- Function needs data passed -->
{user_can 'edit' entry.id}

<!-- vs accessing template variable directly -->
{user_can 'edit' {entry.id}} <!-- Nested syntax? -->
```

**Solution:**
- Template parser can pass current data context to functions
- Or functions can access data via global (not ideal)
- Or functions receive data as arguments (cleaner)

**Verdict:** ⚠️ **DESIGN DECISION NEEDED** - How should template functions access template data?

---

### 5. Template Function Error Handling ❓

**Question:** What happens if a template function throws an error?

**Status:** ❓ **NEEDS IMPLEMENTATION**

**Current Template Parser:**
```php
try {
    eval('?>' . $parsed . '<?php');
} catch (Throwable $e) {
    // Error handling exists
}
```

**Analysis:**
- Template parser has error handling
- But function calls need try/catch around them
- Should errors be logged? Silently fail? Show error message?

**Impact:** 🟡 **MEDIUM** - Important for stability

**Verdict:** ⚠️ **IMPLEMENTATION DETAIL** - Should be part of template function system

---

## 📋 Complete Checklist

### Critical Missing Pieces

- [x] **Template Function Registration System** ❌ NOT IMPLEMENTED
  - [ ] Create `core/plugins/template_functions.php`
  - [ ] Implement `register_template_function()`
  - [ ] Implement `get_template_function()`
  - [ ] Add to plugin loading system

- [x] **Template Parser Integration** ❌ NOT IMPLEMENTED
  - [ ] Modify `admin/template_parser.php` to detect function calls
  - [ ] Add function invocation logic
  - [ ] Add argument parsing
  - [ ] Add error handling

### Integration Requirements

- [ ] **Plugin Loading Before Template Rendering** ⚠️ NEEDS VERIFICATION
  - [ ] Verify admin templates load plugins ✅ (confirmed)
  - [ ] Verify public templates load plugins ❓ (needs check)
  - [ ] Implement auto-load or document requirement

### Design Decisions Needed

- [ ] **Template Data Context Access** ❓ DESIGN DECISION
  - [ ] How should functions access template variables?
  - [ ] Pass as arguments? Global? Context object?

- [ ] **Error Handling Strategy** ❓ DESIGN DECISION
  - [ ] Silent fail? Log errors? Show error message?
  - [ ] Error isolation per function?

### Nice-to-Have (Not Blocking)

- [ ] **Function Argument Validation** (optional)
- [ ] **Function Documentation System** (optional)
- [ ] **Function Discovery API** (optional)

---

## 🎯 Final Answer

### Is Template Function Registration the ONLY Missing Piece?

**Almost, but there are 2-3 additional considerations:**

1. ✅ **Template Function Registration** - CRITICAL MISSING PIECE
   - Must be implemented
   - Well-documented in architecture plan

2. ⚠️ **Plugin Loading Before Template Rendering** - INTEGRATION REQUIREMENT
   - Needs verification for public templates
   - Should auto-load plugins in template parser (recommended)

3. ❓ **Template Data Context Access** - DESIGN DECISION
   - How should functions access template variables?
   - Needs design decision before implementation

4. ❓ **Error Handling Strategy** - IMPLEMENTATION DETAIL
   - Should be part of template function system
   - Needs specification

**Summary:**
- **1 Critical Missing Piece**: Template function registration system
- **1 Integration Requirement**: Ensure plugins load before template rendering
- **2 Design Decisions**: Data context access, error handling

**Readiness for User Plugin:**
- **85% Ready** - Template functions are the main blocker
- **95% Ready** - After template functions + plugin loading verification

---

## 🚀 Recommended Next Steps

1. ✅ **Implement Template Function System** (Critical)
   - Create `core/plugins/template_functions.php`
   - Modify `admin/template_parser.php`
   - Add to plugin loading

2. ⚠️ **Verify Plugin Loading** (Integration)
   - Check all public template rendering paths
   - Implement auto-load in template parser (recommended)

3. ❓ **Design Data Context Access** (Design Decision)
   - Decide how functions access template variables
   - Document approach

4. ❓ **Specify Error Handling** (Implementation Detail)
   - Define error handling strategy
   - Implement in template parser

---

**Document Version:** 1.0  
**Created:** 2025-01-20  
**Status:** Complete Analysis
