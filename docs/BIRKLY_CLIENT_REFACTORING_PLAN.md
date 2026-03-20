# Birkly Client Refactoring Plan

## Current State
- Single file: `birkly-client.js` (~4700 lines)
- All functionality in one IIFE (Immediately Invoked Function Expression)
- Mixed concerns: API, templating, DOM manipulation, utilities

## Target Architecture

### Module Structure

1. **birkly-utils.js** - Core utilities
   - `escapeHtml()` - HTML escaping
   - `getNestedValue()` - Nested object access
   - `simpleHash()` - String hashing
   - `parseQuotedArgs()` - Argument parsing
   - `tfCacheKey()` - Template function cache keys

2. **birkly-date.js** - Date formatting
   - `formatDate()` - Custom date formatting with format codes
   - `formatValueForOutput()` - Smart value formatting (dates, content, etc.)

3. **birkly-variables.js** - Variable processing
   - Variable replacement logic
   - Format filter support
   - Fallback operator handling
   - Date field detection and formatting

4. **birkly-loops.js** - Loop processing
   - `processNestedLoops()` - Nested loop handling
   - `extractLoopContent()` - Loop content extraction
   - Collection detection and filtering
   - Ordering logic

5. **birkly-conditionals.js** - Conditional processing
   - `renderConditionals()` - If/else/endif processing
   - `isTruthyTemplateValue()` - Truthiness checks
   - `resolveCollectionEntryRef()` - Collection/entry resolution
   - `has` and `exist` checks

6. **birkly-template-engine.js** - Main template engine
   - `renderTemplate()` - Main rendering function
   - Template function hydration
   - `from ... get ...` block and inline processing
   - Orchestrates variable, loop, and conditional processing

7. **birkly-api.js** - API client
   - `fetchContent()` - Content fetching
   - `detectApiEndpoint()` - Endpoint detection
   - CSRF token handling
   - Domain authentication

8. **birkly-dom.js** - DOM utilities
   - Template detection in DOM
   - Form binding (`bindActionForms`, `bindExistingForms`)
   - Analytics initialization
   - Language selector
   - Entry block processing

9. **birkly-client.js** - Main orchestrator
   - Configuration object (`Birkly`)
   - Initialization (`init()`)
   - Public API exposure
   - Module coordination

## Implementation Strategy

1. **Phase 1**: Extract utilities (utils, date) - no dependencies
2. **Phase 2**: Extract API client - minimal dependencies
3. **Phase 3**: Extract variable processor - depends on utils, date
4. **Phase 4**: Extract loop processor - depends on utils, variables
5. **Phase 5**: Extract conditional processor - depends on utils, variables
6. **Phase 6**: Extract template engine - depends on all above
7. **Phase 7**: Extract DOM utilities - depends on template engine, API
8. **Phase 8**: Refactor main file to orchestrate modules

## Module Pattern

Each module will be wrapped in an IIFE that attaches to the `Birkly` object:

```javascript
(function(Birkly) {
    'use strict';
    
    // Module code here
    Birkly.dateFormatter = {
        formatDate: function(dateString, format) { ... },
        formatValueForOutput: function(value, fieldName) { ... }
    };
    
})(window.Birkly || (window.Birkly = {}));
```

## Benefits

- **Maintainability**: Clear separation of concerns
- **Testability**: Each module can be tested independently
- **Readability**: Smaller, focused files
- **Reusability**: Modules can be used independently if needed
- **Debugging**: Easier to locate issues in specific modules

## Backward Compatibility

- Main `Birkly` object API remains the same
- All public methods remain accessible
- No breaking changes to external API
