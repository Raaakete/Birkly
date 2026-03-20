# Fieldtype CSS Development Guide

## Philosophy

Fieldtypes should be **self-contained** but **system-integrated**. This means:

1. **Use system CSS whenever possible** - Don't reinvent the wheel
2. **Only add custom CSS when system doesn't provide what you need** - Be minimal
3. **Always use CSS variables** - Never hardcode colors, spacing, or other design tokens
4. **Follow system patterns** - Use existing classes and components

## System CSS Available to Fieldtypes

### CSS Variables (Always Available)

All fieldtypes have access to the complete CSS variable system from `core/variables.css`:

```css
/* Colors */
--color-primary, --color-primary-hover
--text-primary, --text-secondary, --text-tertiary
--bg-primary, --bg-secondary, --bg-elevated
--border-light, --border-medium, --border-strong

/* Spacing */
--space-1 through --space-16
--spacing-xs through --spacing-xxl (legacy)

/* Semantic Colors */
--success-500, --error-500, --warning-500
--primary-500, --primary-600, etc.

/* Border Radius */
--border-radius-s, --border-radius-m, --border-radius-l
--radius-sm, --radius-md, --radius-lg

/* Shadows */
--shadow-sm, --shadow-md, --shadow-lg, --shadow-xl

/* Transitions */
--transition-fast, --transition-base, --transition-slow
```

### System Classes You Should Use

#### Form Elements
- `.input` or `.form-control` - For text inputs, selects, textareas
- `.form-group` - For form field containers
- `.form-help` or `.form-text` - For help text
- `.required` - For required field indicators

#### Buttons
- `.btn` - Base button class
- `.btn-primary`, `.btn-secondary`, `.btn-danger` - Button variants
- `.btn-sm` - Small button size
- `.btn-icon` - Icon-only buttons

#### Layout
- `.card` - Card containers
- `.d-flex`, `.flex-row`, `.flex-column` - Flexbox utilities
- `.gap-*` - Gap utilities
- `.m-*`, `.p-*` - Margin/padding utilities

#### Status Indicators
- `.error-message` - Error messages
- `.success-message` - Success messages

## Fieldtype CSS Best Practices

### ✅ DO

1. **Use CSS variables for all colors, spacing, and design tokens**
   ```css
   .my-fieldtype-container {
     background: var(--bg-elevated);
     border: 1px solid var(--border-light);
     padding: var(--space-4);
     color: var(--text-primary);
   }
   ```

2. **Use system classes when possible**
   ```html
   <!-- Good: Use system input class -->
   <input type="text" class="input" />
   
   <!-- Good: Use system button class -->
   <button class="btn btn-primary">Save</button>
   ```

3. **Scope your styles to your fieldtype**
   ```css
   /* Good: Scoped to your fieldtype */
   .my-fieldtype-wrapper {
     /* Your styles here */
   }
   
   .my-fieldtype-wrapper .toolbar {
     /* Toolbar specific to your fieldtype */
   }
   ```

4. **Use semantic variable names**
   ```css
   /* Good */
   background: var(--bg-elevated);
   color: var(--text-primary);
   
   /* Bad */
   background: #ffffff;
   color: #20221D;
   ```

### ❌ DON'T

1. **Don't hardcode colors**
   ```css
   /* Bad */
   background: #f7f8fa;
   color: #222;
   
   /* Good */
   background: var(--bg-secondary);
   color: var(--text-primary);
   ```

2. **Don't override system classes globally**
   ```css
   /* Bad: Affects all buttons in admin */
   .btn {
     background: red;
   }
   
   /* Good: Only affects buttons in your fieldtype */
   .my-fieldtype-wrapper .btn {
     /* Custom button style if needed */
   }
   ```

3. **Don't duplicate system functionality**
   ```css
   /* Bad: Recreating input styles */
   .my-input {
     padding: 8px 12px;
     border: 1px solid #ccc;
     border-radius: 4px;
   }
   
   /* Good: Use system class */
   /* Just use class="input" in HTML */
   ```

4. **Don't use fixed pixel values for spacing**
   ```css
   /* Bad */
   padding: 10px;
   margin: 15px;
   
   /* Good */
   padding: var(--space-3);
   margin: var(--space-4);
   ```

## Fieldtype CSS Structure

### Recommended Pattern

```css
/* ============================================
   MyFieldtype Styles
   ============================================ */

/* Main container - use system variables */
.my-fieldtype-wrapper {
  background: var(--bg-elevated);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  padding: var(--space-4);
}

/* Toolbar - reuse system patterns */
.my-fieldtype-toolbar {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3);
  border-bottom: 1px solid var(--border-light);
  background: var(--bg-secondary);
}

/* Toolbar buttons - use system button classes in HTML */
/* If you need custom styling, scope it */
.my-fieldtype-toolbar .btn {
  /* Only if you need fieldtype-specific button styling */
}

/* Content area */
.my-fieldtype-content {
  padding: var(--space-4);
  min-height: 300px;
  background: var(--bg-primary);
}

/* Custom elements that don't exist in system */
.my-fieldtype-special-element {
  /* Only add styles for truly unique elements */
  /* Use system variables for colors, spacing, etc. */
}
```

## Common Fieldtype Patterns

### Simple Input Fieldtype

```css
/* Minimal - system handles most styling */
.my-fieldtype-input {
  /* Only add styles if system doesn't provide what you need */
  /* Usually just use class="input" in HTML */
}
```

### Rich Editor Fieldtype

```css
/* Editor-specific container */
.editor-fieldtype-wrapper {
  background: var(--bg-elevated);
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
}

/* Toolbar */
.editor-fieldtype-toolbar {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3);
  border-bottom: 1px solid var(--border-light);
  background: var(--bg-secondary);
}

/* Use system button classes in HTML for toolbar buttons */
/* Only add custom styles if needed */
.editor-fieldtype-toolbar .btn {
  /* Custom styling only if necessary */
}

/* Editor content area */
.editor-fieldtype-content {
  padding: var(--space-4);
  min-height: 300px;
  background: var(--bg-primary);
  color: var(--text-primary);
}
```

### Media/File Picker Fieldtype

```css
/* Media picker container */
.media-fieldtype-wrapper {
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  padding: var(--space-4);
  background: var(--bg-elevated);
}

/* Preview grid */
.media-fieldtype-preview {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: var(--space-3);
  margin-top: var(--space-4);
}

/* Preview item */
.media-fieldtype-item {
  border: 1px solid var(--border-light);
  border-radius: var(--radius-sm);
  padding: var(--space-2);
  background: var(--bg-primary);
  transition: border-color var(--transition-fast);
}

.media-fieldtype-item:hover {
  border-color: var(--primary-500);
}
```

## Migration Checklist

When updating an existing fieldtype:

- [ ] Replace all hardcoded colors with CSS variables
- [ ] Replace all hardcoded spacing with spacing variables
- [ ] Replace custom input/button styles with system classes
- [ ] Scope all styles to your fieldtype wrapper
- [ ] Remove duplicate styles that system already provides
- [ ] Test in both light and dark themes (if applicable)
- [ ] Verify responsive behavior

## Examples

### Before (Bad)
```css
.wysiwyg-toolbar {
  background: #f7f8fa;
  border: 1px solid #e0e2e6;
  padding: 0.5em;
}

.wysiwyg-toolbar button {
  background: none;
  border: none;
  padding: 0.3em 0.6em;
  color: #222;
}
```

### After (Good)
```css
.wysiwyg-toolbar {
  background: var(--bg-secondary);
  border: 1px solid var(--border-light);
  padding: var(--space-3);
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

/* Use system button classes in HTML instead */
/* Or if you need custom styling: */
.wysiwyg-toolbar .btn {
  /* Only add what system doesn't provide */
}
```

## Getting Help

If you're unsure whether to use system CSS or add your own:

1. **Check `admin/css/components/`** - See if a component exists
2. **Check `admin/css/core/variables.css`** - See available variables
3. **Check `docs/CSS_COMPONENT_QUICK_REFERENCE.md`** - Component reference
4. **Ask in development discussions** - Get feedback before implementing

## Summary

**Golden Rule**: If the system provides it, use it. Only add custom CSS for truly unique fieldtype-specific functionality.

