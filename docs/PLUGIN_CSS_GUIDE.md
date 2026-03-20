# Plugin CSS Guide
## Using Birkly Design System in Your Plugins

This guide explains how to use the Birkly CSS design system in your third-party plugins to ensure consistency with the admin panel.

---

## 🎯 Overview

Birkly provides a unified CSS design system that plugin developers can use to create consistent, beautiful interfaces that match the admin panel. All styles use CSS variables, so they automatically adapt to theme changes.

---

## 📦 Available Components

### Buttons

Use the unified button system:

```html
<!-- Primary Button -->
<button class="btn btn--primary">Save Settings</button>

<!-- Secondary Button -->
<button class="btn btn--secondary">Cancel</button>

<!-- Danger Button -->
<button class="btn btn--danger">Delete</button>

<!-- Button Sizes -->
<button class="btn btn--primary btn--sm">Small</button>
<button class="btn btn--primary btn--lg">Large</button>

<!-- Icon Button -->
<button class="btn btn--icon">
  <svg>...</svg>
</button>
```

**Available Classes:**
- `.btn` - Base button
- `.btn--primary` - Primary action
- `.btn--secondary` - Secondary action
- `.btn--danger` - Destructive action
- `.btn--success` - Success action
- `.btn--warning` - Warning action
- `.btn--sm` - Small size
- `.btn--lg` - Large size
- `.btn--icon` - Icon-only button
- `.btn--disabled` - Disabled state
- `.btn--loading` - Loading state

---

### Forms

Use the unified form system:

```html
<!-- Basic Form -->
<div class="form-group">
  <label for="plugin-setting" class="required">Setting Name</label>
  <input type="text" id="plugin-setting" class="input" />
  <small class="form-help">Help text goes here</small>
</div>

<!-- Form with Validation -->
<div class="form-group form-group--error">
  <label>Email</label>
  <input type="email" class="input input--error" />
  <small class="form-feedback error">Invalid email address</small>
</div>

<!-- Form Row (Horizontal Layout) -->
<div class="form-row">
  <div class="form-group">
    <label>First Name</label>
    <input type="text" class="input" />
  </div>
  <div class="form-group">
    <label>Last Name</label>
    <input type="text" class="input" />
  </div>
</div>

<!-- Form Actions -->
<div class="form-actions">
  <button class="btn btn--secondary">Cancel</button>
  <button class="btn btn--primary">Save</button>
</div>
```

**Available Classes:**
- `.form-group` - Form field container
- `.form-group--required` - Required field indicator
- `.form-group--error` - Error state
- `.form-group--success` - Success state
- `.input` - Text input, select, textarea
- `.input--error` - Input error state
- `.input--success` - Input success state
- `.form-row` - Horizontal form layout
- `.form-grid` - Grid form layout
- `.form-actions` - Form action buttons container

---

### Tables

Use the unified table system:

```html
<!-- Basic Table -->
<table class="table">
  <thead>
    <tr>
      <th>Name</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Item 1</td>
      <td>Active</td>
      <td>
        <div class="actions">
          <button class="btn btn--sm btn--secondary">Edit</button>
        </div>
      </td>
    </tr>
  </tbody>
</table>

<!-- Table Variants -->
<table class="table table--striped table--hover table--bordered">
  <!-- content -->
</table>
```

**Available Classes:**
- `.table` - Base table
- `.table--striped` - Striped rows
- `.table--bordered` - Bordered table
- `.table--hover` - Hover effect on rows

---

### Modals

Use the unified modal system:

```html
<!-- Basic Modal -->
<div class="modal modal--md">
  <div class="modal-backdrop"></div>
  <div class="modal-content">
    <div class="modal-header">
      <h3>Plugin Settings</h3>
      <button class="modal-close">&times;</button>
    </div>
    <div class="modal-body">
      <p>Modal content goes here</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn--secondary">Cancel</button>
      <button class="btn btn--primary">Save</button>
    </div>
  </div>
</div>
```

**Available Classes:**
- `.modal` - Modal container
- `.modal--sm` - Small modal (400px)
- `.modal--md` - Medium modal (600px, default)
- `.modal--lg` - Large modal (900px)
- `.modal--xl` - Extra large modal (1200px)
- `.modal-backdrop` - Modal backdrop
- `.modal-content` - Modal content wrapper
- `.modal-header` - Modal header
- `.modal-body` - Modal body
- `.modal-footer` - Modal footer
- `.modal-close` - Close button

**To show modal:**
```javascript
document.querySelector('.modal').classList.add('show');
// or
document.querySelector('.modal').style.display = 'flex';
```

---

### Switches/Toggles

Use the unified toggle switch:

```html
<!-- Basic Switch -->
<label class="switch">
  <input type="checkbox" />
  <span class="switch__slider"></span>
  Enable Feature
</label>

<!-- Switch Sizes -->
<label class="switch switch--sm">
  <input type="checkbox" />
  <span class="switch__slider"></span>
</label>

<label class="switch switch--lg">
  <input type="checkbox" />
  <span class="switch__slider"></span>
</label>
```

**Available Classes:**
- `.switch` - Toggle switch container
- `.switch--sm` - Small size
- `.switch--lg` - Large size
- `.switch--disabled` - Disabled state
- `.switch__slider` - Switch slider element

**Note:** Also supports `.toggle-switch` and `.toggle-slider` for backward compatibility.

---

### Cards

Use the card component:

```html
<!-- Basic Card -->
<div class="card">
  <div class="card__header">
    <h3>Card Title</h3>
  </div>
  <div class="card__body">
    <p>Card content</p>
  </div>
  <div class="card__footer">
    <button class="btn btn--primary">Action</button>
  </div>
</div>
```

**Available Classes:**
- `.card` - Base card
- `.card--elevated` - Elevated/shadowed card
- `.card--interactive` - Clickable card
- `.card__header` - Card header
- `.card__body` - Card body
- `.card__footer` - Card footer

---

## 🎨 Plugin-Specific Classes

### Plugin Container

```html
<div class="plugin-container">
  <!-- Your plugin content -->
</div>
```

### Plugin Section

```html
<div class="plugin-section">
  <div class="plugin-section__header">
    <h2 class="plugin-section__title">Section Title</h2>
    <p class="plugin-section__description">Section description</p>
  </div>
  <!-- Section content -->
</div>
```

### Plugin Card

```html
<div class="plugin-card">
  <div class="plugin-card__header">
    <h3 class="plugin-card__title">Card Title</h3>
  </div>
  <div class="plugin-card__body">
    Card content
  </div>
  <div class="plugin-card__footer">
    <button class="btn btn--primary">Action</button>
  </div>
</div>
```

### Plugin Status Indicators

```html
<span class="plugin-status plugin-status--active">Active</span>
<span class="plugin-status plugin-status--inactive">Inactive</span>
<span class="plugin-status plugin-status--error">Error</span>
<span class="plugin-status plugin-status--warning">Warning</span>
```

### Plugin Badges

```html
<span class="plugin-badge plugin-badge--primary">New</span>
<span class="plugin-badge plugin-badge--success">Success</span>
<span class="plugin-badge plugin-badge--error">Error</span>
```

### Plugin Empty State

```html
<div class="plugin-empty-state">
  <div class="plugin-empty-state__icon">📦</div>
  <h3 class="plugin-empty-state__title">No Items Found</h3>
  <p class="plugin-empty-state__description">
    Get started by creating your first item.
  </p>
  <div class="plugin-empty-state__action">
    <button class="btn btn--primary">Create Item</button>
  </div>
</div>
```

### Plugin Loading State

```html
<div class="plugin-loading">
  Loading...
</div>
```

### Plugin Messages

```html
<div class="plugin-message plugin-message--info">
  This is an info message
</div>

<div class="plugin-message plugin-message--success">
  Operation successful!
</div>

<div class="plugin-message plugin-message--warning">
  Warning: Please review your settings
</div>

<div class="plugin-message plugin-message--error">
  Error: Something went wrong
</div>
```

### Plugin Grid

```html
<div class="plugin-grid">
  <div class="plugin-card">Item 1</div>
  <div class="plugin-card">Item 2</div>
  <div class="plugin-card">Item 3</div>
</div>

<!-- Specific column counts -->
<div class="plugin-grid plugin-grid--2">
  <!-- 2 columns -->
</div>

<div class="plugin-grid plugin-grid--3">
  <!-- 3 columns -->
</div>
```

---

## 🎨 CSS Variables

All components use CSS variables. You can customize them in your plugin:

```css
/* In your plugin CSS file */
.my-plugin {
  /* Override variables for your plugin only */
  --color-primary: #custom-color;
}

.my-plugin .btn--primary {
  /* Will use your custom color */
}
```

### Available Variables

```css
/* Colors */
--color-primary: #7CE3BF;
--color-primary-hover: #8CECCA;
--text-primary: #20221D;
--text-secondary: #72746F;
--text-tertiary: #C4C6C1;

/* Backgrounds */
--bg-primary: #ffffff;
--bg-secondary: #fafafa;
--bg-elevated: #ffffff;

/* Spacing */
--space-1: 0.25rem;  /* 4px */
--space-2: 0.5rem;   /* 8px */
--space-3: 0.75rem;  /* 12px */
--space-4: 1rem;     /* 16px */
--space-6: 1.5rem;   /* 24px */
--space-8: 2rem;     /* 32px */

/* Border Radius */
--radius-sm: 0.375rem;  /* 6px */
--radius-md: 0.5rem;    /* 8px */
--radius-lg: 0.75rem;   /* 12px */

/* Shadows */
--shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
--shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
--shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
```

See `admin/css/core/variables.css` for the complete list.

---

## 📝 Complete Plugin Example

Here's a complete example of a plugin settings page:

```html
<div class="plugin-container">
  <div class="plugin-section">
    <div class="plugin-section__header">
      <h2 class="plugin-section__title">Plugin Settings</h2>
      <p class="plugin-section__description">
        Configure your plugin options below
      </p>
    </div>

    <form class="plugin-form">
      <div class="form-group">
        <label for="api-key" class="required">API Key</label>
        <input 
          type="text" 
          id="api-key" 
          class="input" 
          placeholder="Enter your API key"
        />
        <small class="form-help">
          You can find your API key in your account settings
        </small>
      </div>

      <div class="form-group">
        <label class="switch">
          <input type="checkbox" />
          <span class="switch__slider"></span>
          Enable automatic updates
        </label>
      </div>

      <div class="form-actions">
        <button type="button" class="btn btn--secondary">Cancel</button>
        <button type="submit" class="btn btn--primary">Save Settings</button>
      </div>
    </form>
  </div>

  <div class="plugin-section">
    <div class="plugin-section__header">
      <h2 class="plugin-section__title">Status</h2>
    </div>

    <div class="plugin-card">
      <div class="plugin-card__header">
        <h3 class="plugin-card__title">Connection Status</h3>
        <span class="plugin-status plugin-status--active">Active</span>
      </div>
      <div class="plugin-card__body">
        <p>Your plugin is connected and working correctly.</p>
      </div>
    </div>
  </div>
</div>
```

---

## 🚀 Best Practices

1. **Use System Components**: Always use the provided component classes instead of creating your own
2. **Use CSS Variables**: Reference CSS variables instead of hardcoding colors/spacing
3. **Follow BEM Naming**: If you need custom classes, follow BEM convention (`.plugin-name__element--modifier`)
4. **Responsive Design**: Use the grid system and responsive utilities
5. **Accessibility**: Use semantic HTML and proper form labels
6. **Consistency**: Match the admin panel's visual style

---

## 📚 Additional Resources

- **Component Quick Reference**: See `docs/CSS_COMPONENT_QUICK_REFERENCE.md`
- **CSS Variables**: See `admin/css/core/variables.css`
- **Component Files**: See `admin/css/components/` directory

---

## ❓ Need Help?

If you have questions about using the CSS system in your plugin:
1. Check the component quick reference
2. Look at existing plugin examples
3. Review the component CSS files for available classes

---

**Last Updated:** [Current Date]

