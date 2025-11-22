# New Editor Fieldtype - Brainstorming & Design

## Current Problems with Advanced Editor

1. **Too Complex**: ~4,800 lines of code, hard to maintain
2. **Fragile**: Selection handling breaks easily, requires multiple workarounds
3. **execCommand Dependency**: Uses deprecated API that's unreliable
4. **State Management**: Complex state tracking across multiple modes
5. **Paragraph Spacing**: Constant CSS conflicts despite inline styles
6. **Selection Issues**: Only works on first paragraph, requires complex saving/restoring

## Goals for New Editor

1. **Simple & Robust**: Minimal code, easy to understand and maintain
2. **Self-Contained**: All code in fieldtype folder, no core dependencies
3. **Standard HTML**: Use native HTML tags, no custom attributes
4. **Reliable**: Selection and formatting work consistently
5. **Clean Output**: Standard HTML that's easy to parse and style
6. **Maintainable**: Clear, straightforward implementation

## Chosen Approach: Simple HTML Editor

**Core Concept:**
- Basic contentEditable area that renders standard HTML
- Blocks are standard HTML block elements (`<p>`, `<div>`, `<h1>`, etc.)
- Formatting uses standard HTML tags (`<b>`, `<i>`, `<u>`, `<s>`, `<h1>`, etc.)
- No custom attributes or data-types
- Clean, semantic HTML output

**Why This Approach:**
1. **Maximum Simplicity**: Just wrap/unwrap HTML tags
2. **Standard Output**: Clean HTML that any system can understand
3. **No Custom Logic**: Use native HTML behavior
4. **Easy to Maintain**: Simple DOM manipulation
5. **Predictable**: Standard HTML is well-understood

### Core Architecture

```
Editor
├── ContentEditable Area (single div with contenteditable="true")
├── Toolbar (formatting buttons)
├── CommandSystem (wrap/unwrap HTML tags)
└── SelectionManager (simple selection handling)
```

### Key Principles

1. **Standard HTML Blocks**
   - Blocks are standard HTML: `<p>`, `<div>`, `<h1>`, `<h2>`, etc.
   - Any element with `display: block` is a block
   - No custom attributes or data-types

2. **Standard HTML Formatting**
   - Bold: `<b>` or `<strong>`
   - Italic: `<i>` or `<em>`
   - Underline: `<u>`
   - Strikethrough: `<s>` or `<del>`
   - Headings: `<h1>`, `<h2>`, `<h3>`, etc.
   - Lists: `<ul>`, `<ol>`, `<li>`
   - No custom wrappers

3. **Simple Command Logic**
   - **With Selection**: Wrap selected text with tag (e.g., `<b>selected</b>`)
   - **Without Selection**: Wrap entire paragraph/block with tag
   - **Toggle**: If already wrapped, unwrap it

4. **Enter Behavior**
   - **Enter**: Create new `<p>` block
   - **Shift+Enter**: Insert `<br>` line break

5. **No execCommand**
   - Custom DOM manipulation
   - Wrap/unwrap tags directly
   - Full control over HTML structure

## Feature Set (MVP)

### Core Features
- ✅ Text editing (typing, selection in contentEditable)
- ✅ Inline formatting (bold, italic, underline, strikethrough)
- ✅ Block formatting (headings H1-H6, paragraph)
- ✅ Lists (ordered `<ol>`, unordered `<ul>`)
- ✅ **Nested lists** (Tab to indent, Shift+Tab to outdent)
- ✅ Enter = new `<p>`, Shift+Enter = `<br>`
- ✅ Selection + format = wrap selected text
- ✅ No selection + format = format entire block/paragraph
- ✅ Keyboard shortcuts (Ctrl+B, Ctrl+I, etc.)
- ✅ Minimal, modern toolbar

### Nice to Have (Phase 2)
- Links (`<a>`)
- Quotes (`<blockquote>`)
- Code blocks (`<pre><code>`)
- Tables (`<table>`, `<tr>`, `<td>`)
- Horizontal rule (`<hr>`)
- Text alignment (left, center, right, justify)

## Implementation Strategy

### Phase 1: Foundation
1. ContentEditable area setup
2. Basic text editing and selection
3. Enter/Shift+Enter handling
4. Simple formatting (bold, italic) - wrap/unwrap tags
5. Keyboard shortcuts (Ctrl+B, Ctrl+I)

### Phase 2: Formatting Commands
1. All inline formats (bold, italic, underline, strikethrough)
2. Block formats (headings H1-H6)
3. Lists (ordered, unordered)
4. **Nested lists** (Tab/Shift+Tab handling)
5. Toggle logic (wrap if not wrapped, unwrap if wrapped)
6. Toolbar with active state indicators

### Phase 3: Advanced Features
1. Links
2. Quotes
3. Code blocks
4. Tables
5. Text alignment

### Phase 4: Polish
1. HTML normalization on save
2. Empty block handling
3. Better UX (visual feedback, animations)
4. Mobile support
5. Accessibility improvements

## Technical Decisions

### 1. Selection Handling
- **Standard Selection API**: Use `window.getSelection()` and `Range`
- **Save Selection**: Save range before toolbar clicks
- **Restore Selection**: Restore after DOM manipulation
- **Simple**: Just get current selection, wrap/unwrap tags

### 2. Formatting Logic
- **With Selection**: 
  ```javascript
  // Get selected text
  // Wrap with tag: <b>selected</b>
  // Replace selection with wrapped content
  ```
- **Without Selection**:
  ```javascript
  // Find current block (p, div, h1, etc.)
  // Wrap entire block with tag
  // Or change block tag (p → h1)
  ```
- **Toggle**: Check if selection/block already has tag, remove if yes, add if no

### 3. Enter/Shift+Enter Handling
- **Enter**: 
  ```javascript
  // Find current block
  // Split at cursor position
  // Create new <p> after current block
  // Move cursor to new <p>
  ```
- **Shift+Enter**:
  ```javascript
  // Insert <br> at cursor position
  ```

### 4. Content Storage
- **HTML Format**: Store innerHTML of contentEditable area
- **Normalize on Save**: 
  - Remove empty formatting tags (`<b></b>`)
  - Normalize whitespace (multiple spaces → single space)
  - Convert empty blocks to `<p><br></p>` for consistency
  - Remove redundant nested tags
- **Standard HTML**: Easy to parse, style, and export

### 5. CSS
- **Simple Styles**: Just style the contentEditable area
- **Block Spacing**: Use standard CSS for `<p>`, `<h1>`, etc.
- **No Custom Classes**: Rely on standard HTML tags

## Code Structure

```
ContentEditor/
├── ContentEditor.php (main fieldtype)
├── editor.js (main entry point, ~200-300 lines)
├── commands.js (formatting commands - wrap/unwrap tags)
├── selection.js (simple selection save/restore)
└── editor.css (basic styling)
```

**Estimated Total**: ~500-800 lines of code (vs 4,800 current)

**Note**: Named "ContentEditor" to avoid conflicts with existing "Editor" fieldtype

## Advantages of This Approach

1. **Extremely Simple**: Just wrap/unwrap HTML tags, ~500-800 lines total
2. **Standard HTML**: Output is clean, semantic HTML
3. **No Custom Logic**: Use standard HTML behavior
4. **Easy to Debug**: Standard HTML is easy to inspect
5. **Maintainable**: Simple DOM manipulation, clear code
6. **No execCommand**: Full control with custom DOM manipulation
7. **Predictable**: Standard HTML tags behave consistently

## Implementation Details

### Formatting Command Example (Bold)

```javascript
function applyBold() {
  const selection = window.getSelection();
  
  if (selection.rangeCount === 0) {
    // No selection: format entire block
    const block = getCurrentBlock();
    if (block.tagName === 'B' || block.tagName === 'STRONG') {
      // Already bold, unwrap
      unwrapTag(block);
    } else {
      // Wrap entire block
      wrapBlock(block, 'b');
    }
  } else {
    // Has selection: wrap selected text
    const range = selection.getRangeAt(0);
    const selectedText = range.toString();
    
    if (isWrappedInTag(range, 'b') || isWrappedInTag(range, 'strong')) {
      // Already bold, unwrap
      unwrapTag(range);
    } else {
      // Wrap selection
      wrapSelection(range, 'b');
    }
  }
}
```

### Enter Handler Example

```javascript
function handleEnter(e) {
  if (e.shiftKey) {
    // Shift+Enter: insert <br>
    insertLineBreak();
  } else {
    // Enter: create new <p>
    e.preventDefault();
    const currentBlock = getCurrentBlock();
    const newP = document.createElement('p');
    newP.innerHTML = '<br>';
    
    // Split current block at cursor
    const range = window.getSelection().getRangeAt(0);
    const afterContent = range.extractContents();
    newP.appendChild(afterContent);
    
    // Insert new paragraph after current
    currentBlock.parentNode.insertBefore(newP, currentBlock.nextSibling);
    
    // Move cursor to new paragraph
    setCursorAt(newP, 0);
  }
}
```

## Questions to Consider

1. **Backward Compatibility**: How to handle existing content?
   - Option: Load existing HTML as-is (should work fine)
   - Option: Clean/normalize HTML on load

2. **Mode Switching**: Do we still need multiple modes?
   - Option: Single mode only (simplest)
   - Option: Add HTML source view later if needed

3. **Rich Formatting**: How much formatting do we need?
   - MVP: Bold, italic, underline, strikethrough, headings, lists
   - Later: Colors, alignment, custom styles

4. **Nested Formatting**: How to handle nested tags?
   - Example: Bold text inside italic?
   - Solution: Just wrap normally, HTML handles nesting

## Next Steps

1. **Review & Confirm**: Does this approach match your vision?
2. **Define MVP Features**: Confirm what's essential for first version
3. **Create Prototype**: Build minimal working version (~200-300 lines)
4. **Test & Iterate**: Refine based on usage
5. **Add Features**: Gradually add more formatting options

## Key Decisions Made

1. ✅ **Name**: "ContentEditor" (to avoid conflicts)
2. ✅ **Standard HTML Only**: No custom attributes or data-types
3. ✅ **Simple Wrap/Unwrap**: Formatting is just wrapping tags
4. ✅ **Enter = New Paragraph**: Shift+Enter = Line break
5. ✅ **Selection Logic**: With selection = format selection, without = format block
6. ✅ **No execCommand**: Custom DOM manipulation for full control
7. ✅ **Keyboard Shortcuts**: Full support for standard shortcuts
8. ✅ **Nested Lists**: Tab/Shift+Tab for indenting/outdenting
9. ✅ **HTML Normalization**: Clean HTML on save (remove empty tags, normalize)
10. ✅ **Empty Blocks**: Keep as `<p><br></p>` for structure
11. ✅ **Toolbar**: Minimal and modern design

## HTML Normalization on Save

**What it means:**
- Remove empty tags: `<b></b>`, `<i></i>`, etc.
- Remove redundant tags: `<b><b>text</b></b>` → `<b>text</b>`
- Normalize whitespace: Multiple spaces → single space
- Clean up empty blocks: `<p></p>` → `<p><br></p>` or remove
- Standardize tag names: `<b>` vs `<strong>` → choose one consistently

**Recommendation:**
- **Yes, normalize on save** - Ensures clean, consistent HTML output
- Keep it simple: Remove empty tags, normalize whitespace
- Don't be too aggressive: Preserve intentional structure

## Empty Blocks Handling

**Recommendation:**
- **Keep empty blocks as `<p><br></p>`** - This is standard HTML practice
- Empty blocks are useful for:
  - Maintaining structure when user presses Enter
  - Providing visual spacing
  - Allowing cursor placement
- On save, we can optionally:
  - Keep them (preserves structure)
  - Remove trailing empty blocks (cleaner output)
  - Convert to `<p><br></p>` (standard format)

**Implementation:**
```javascript
// When user presses Enter in empty block, create new empty block
// Empty block = <p><br></p> or <p></p> (browsers handle differently)
// On save: normalize to <p><br></p> for consistency
```

## Keyboard Shortcuts

**Standard Shortcuts to Support:**
- `Ctrl+B` / `Cmd+B`: Bold
- `Ctrl+I` / `Cmd+I`: Italic
- `Ctrl+U` / `Cmd+U`: Underline
- `Ctrl+Shift+S` / `Cmd+Shift+S`: Strikethrough
- `Ctrl+1-6` / `Cmd+1-6`: Headings (H1-H6)
- `Ctrl+Shift+L` / `Cmd+Shift+L`: Unordered list
- `Ctrl+Shift+O` / `Cmd+Shift+O`: Ordered list
- `Tab`: Indent (in lists: create nested list)
- `Shift+Tab`: Outdent (in lists: unnest list)
- `Enter`: New paragraph
- `Shift+Enter`: Line break

## Toolbar Design

**Minimal & Modern:**
- Clean, icon-based buttons
- Group related tools (formatting, headings, lists)
- Show active state (highlight when format is applied)
- Responsive design
- No dropdowns for MVP (keep it simple)

**Suggested Toolbar Layout:**
```
[B] [I] [U] [S] | [H] | [•] [1.] | [Link] [Code]
```

Where:
- `[B]` = Bold, `[I]` = Italic, `[U]` = Underline, `[S]` = Strikethrough
- `[H]` = Headings dropdown (H1, H2, H3, H4, H5, H6, Paragraph)
- `[•]` = Unordered list, `[1.]` = Ordered list
- `[Link]` = Insert link, `[Code]` = Code block

**Alternative Layout (if space allows):**
```
[B] [I] [U] [S] | [H1] [H2] [H3] [H4] [H5] [H6] | [•] [1.] | [Link] [Code]
```

Note: For minimal design, dropdown is preferred. For power users, all buttons visible.

## Nested Lists Implementation

**How it works:**
- User presses `Tab` inside a list item → Create nested list
- User presses `Shift+Tab` inside nested list → Move to parent list
- Standard HTML structure:
  ```html
  <ul>
    <li>Item 1
      <ul>
        <li>Nested item</li>
      </ul>
    </li>
  </ul>
  ```

**Implementation Strategy:**
1. Detect if cursor is in `<li>` element
2. On `Tab`: Check if parent is list, create nested list if needed
3. On `Shift+Tab`: Move list item to parent list level
4. Handle edge cases (first item, last item, empty lists)

