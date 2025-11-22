# Editor Fieldtype Analysis & Improvement Plan

## Current State Analysis

### Architecture Overview
- **5 Modes**: Advanced, WYSIWYG, Markdown, HTML, Block
- **Core System**: DOM-based model with HTML serialization
- **Modular Structure**: Separate files for modes, UI, and core functionality
- **Self-contained**: All assets bundled within fieldtype folder

### Critical Issues Found

#### 1. **Initialization Problems**
- **Retry Logic**: Uses polling with `setTimeout` to wait for modes to load (unreliable)
- **Race Conditions**: Multiple initialization attempts can conflict
- **Missing Error Handling**: Silent failures when modes don't load
- **Location**: `fieldtype.js` lines 4-22

#### 2. **Code Quality Issues**
- **265 console.log statements**: Debug code left in production
- **Inconsistent Error Handling**: Many try-catch blocks that silently fail
- **Code Duplication**: Similar logic repeated across modes
- **Location**: Throughout all files, especially `modes/advanced.js`

#### 3. **Content Synchronization Issues**
- **Multiple Sync Points**: Hidden input synced from multiple places
- **Mode Switching**: Content can be lost when switching modes
- **Sanitization**: Basic HTML sanitization only removes `<script>` tags
- **Location**: `fieldtype.js` lines 133-151

#### 4. **Block Mode Issues** (Most Notion-like, but problematic)
- **Complex State Management**: `__blocks`, `__history`, `__selectedIds` stored on DOM
- **Selection Handling**: Fragile selection tracking across events
- **Performance**: Re-renders entire surface on many operations
- **Location**: `modes/block.js` (2643 lines - very large)

#### 5. **Advanced Mode Issues**
- **Contenteditable Problems**: Browser inconsistencies with contenteditable
- **execCommand Deprecation**: Uses deprecated `document.execCommand`
- **Selection Loss**: Selection lost when clicking toolbar buttons
- **Location**: `modes/advanced.js`

#### 6. **Markdown Conversion**
- **Basic Implementation**: Very simple regex-based conversion
- **Missing Features**: No support for code blocks, tables, links with titles
- **Location**: `fieldtype.js` lines 26-49

#### 7. **UI/UX Issues**
- **Toolbar Visibility**: Toolbars show/hide inconsistently
- **Fullscreen Mode**: Basic implementation, could be improved
- **No Keyboard Shortcuts**: Limited keyboard support
- **No Undo/Redo**: Only block mode has history

#### 8. **Security Concerns**
- **XSS Vulnerabilities**: Insufficient HTML sanitization
- **Script Injection**: Only removes `<script>` tags, not other dangerous elements
- **Location**: `fieldtype.js` line 24

## Improvement Plan

### Phase 1: Foundation & Cleanup (Priority: HIGH)

#### 1.1 Remove Debug Code
- Remove all `console.log` statements
- Add proper error logging (optional, can be disabled)
- Clean up commented code

#### 1.2 Fix Initialization
- Replace polling with proper event-based initialization
- Ensure modes load in correct order
- Add proper error handling and fallbacks

#### 1.3 Improve Content Synchronization
- Single source of truth for content
- Proper mode switching with content preservation
- Better sanitization (DOMPurify-like, but self-contained)

#### 1.4 Fix Selection Handling
- Save/restore selection properly across all modes
- Handle selection loss when clicking toolbars
- Improve selection tracking in block mode

### Phase 2: Core Improvements (Priority: HIGH)

#### 2.1 Replace execCommand
- Implement custom command system
- Better browser compatibility
- More control over formatting

#### 2.2 Improve Block Mode
- Optimize rendering (only update changed blocks)
- Better state management (consider data structure instead of DOM)
- Improve drag & drop
- Better keyboard navigation

#### 2.3 Enhanced Markdown Support
- Proper markdown parser (self-contained)
- Support for: code blocks, tables, task lists, links with titles
- Better HTML to Markdown conversion

#### 2.4 Better Sanitization
- Self-contained HTML sanitizer
- Whitelist approach for allowed tags/attributes
- Remove dangerous elements and attributes

### Phase 3: Notion-like Features (Priority: MEDIUM)

#### 3.1 Slash Commands
- Typing "/" opens command palette
- Quick insertion of blocks/elements
- Searchable commands

#### 3.2 Better Block Types
- More block types (callouts, toggles, columns)
- Customizable block properties
- Block templates

#### 3.3 Improved Keyboard Navigation
- Arrow keys for block navigation
- Enter to create new block
- Backspace at start of block to merge/delete
- Tab for indentation

#### 3.4 Inline Formatting
- Floating toolbar on text selection
- Better formatting options
- Link editing inline

#### 3.5 Collaboration Features (Future)
- Cursor positions (if multi-user)
- Change tracking
- Comments

### Phase 4: Polish & Performance (Priority: LOW)

#### 4.1 Performance Optimization
- Virtual scrolling for large documents
- Debounce/throttle expensive operations
- Lazy loading of modes

#### 4.2 Better UI
- Modern, clean design
- Better tooltips
- Keyboard shortcut hints
- Better mobile support

#### 4.3 Accessibility
- ARIA labels
- Keyboard navigation
- Screen reader support

## Recommended Implementation Order

1. **Week 1**: Phase 1 (Foundation & Cleanup)
   - Remove debug code
   - Fix initialization
   - Improve sanitization
   - Fix selection handling

2. **Week 2**: Phase 2 (Core Improvements)
   - Replace execCommand
   - Improve block mode performance
   - Enhanced markdown support

3. **Week 3-4**: Phase 3 (Notion-like Features)
   - Slash commands
   - Better keyboard navigation
   - Improved block types

4. **Week 5**: Phase 4 (Polish)
   - Performance optimization
   - UI improvements
   - Accessibility

## Technical Recommendations

### Self-Contained HTML Sanitizer
Create a simple but effective sanitizer that:
- Whitelists allowed tags/attributes
- Removes dangerous elements
- Preserves formatting
- No external dependencies

### Better State Management
For block mode, consider:
- Data structure for blocks (not just DOM)
- Immutable updates
- Better history management

### Event System
- Centralized event bus
- Better mode communication
- Cleaner plugin API

## Files to Focus On

1. **fieldtype.js** - Main initialization and coordination
2. **modes/advanced.js** - Most used mode, needs cleanup
3. **modes/block.js** - Most complex, needs optimization
4. **modes/markdown.js** - Needs better parser
5. **core/model.js** - Very minimal, needs expansion

## Quick Wins

1. Remove all console.log statements (1 hour)
2. Fix initialization race conditions (2 hours)
3. Improve HTML sanitization (3 hours)
4. Fix selection handling in advanced mode (4 hours)
5. Add proper error handling (2 hours)

Total: ~12 hours for significant improvements

