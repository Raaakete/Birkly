# Birkly Client Modules

This directory contains the modular components of the Birkly client library.

## Module Structure

### Core Modules (Load Order)

1. **birkly-utils.js** - Core utilities
   - `getNestedValue()` - Nested object access
   - `escapeHtml()` - HTML escaping
   - `simpleHash()` - String hashing
   - `parseQuotedArgs()` - Argument parsing
   - `tfCacheKey()` - Template function cache keys

2. **birkly-date.js** - Date formatting
   - `formatDate()` - Custom date formatting with format codes
   - `formatValueForOutput()` - Smart value formatting (dates, content, etc.)
   - `isDateField()` - Date field detection

3. **birkly-conditionals.js** - Conditional processing
   - `renderConditionals()` - If/else/endif processing
   - `isTruthyTemplateValue()` - Truthiness checks
   - `resolveCollectionEntryRef()` - Collection/entry resolution

4. **birkly-loops.js** - Loop processing
   - `processNestedLoops()` - Nested loop handling
   - `extractLoopContent()` - Loop content extraction

5. **birkly-bundle.js** - Page bundle (M4)
   - `getTemplateCollectionSlugs()` - Collection slugs from template source
   - `collectBundleRequests()` - Build batch read list from DOM + templates
   - `applyBundleResponse()` - Populate `Birkly.pageBundle` cache
   - `loadPageBundle()` - POST to `api/page_bundle.php`
   - `tryLoadPageSnapshot()` - Load publish-time snapshot (inline or `page_snapshot.php`)

### Hydrate script (baked pages)

**birkly-hydrate.js** — still supported for baked/publish-time pages. Use when HTML is pre-rendered at deploy and you only need interactivity (forms, cart, auth). See file header and [Production optimization](../../admin/docs/snapshots/production-optimization.md) in admin help.

## Usage

### Option 1: Separate Module Files (Development)

Load modules in order before the main file:

```html
<script src="birkly-client-modules/birkly-utils.js"></script>
<script src="birkly-client-modules/birkly-date.js"></script>
<script src="birkly-client-modules/birkly-conditionals.js"></script>
<script src="birkly-client-modules/birkly-loops.js"></script>
<script src="birkly-client-modules/birkly-bundle.js"></script>
<script src="birkly-client.js"></script>
```

### Option 2: Bundled File (Production)

The main `birkly-client.js` file includes all modules inline for backward compatibility.

## Module Pattern

Each module follows this pattern:

```javascript
(function(Birkly) {
    'use strict';
    
    if (!Birkly) Birkly = {};
    
    // Check dependencies
    if (!Birkly.utils) {
        console.error('Birkly: utils module must be loaded before this module');
        return;
    }
    
    // Module code
    Birkly.moduleName = {
        // Exported functions
    };
    
})(window.Birkly || (window.Birkly = {}));
```

## Benefits

- **Maintainability**: Clear separation of concerns
- **Testability**: Each module can be tested independently
- **Readability**: Smaller, focused files
- **Reusability**: Modules can be used independently if needed
- **Backward Compatibility**: Main file includes modules inline
