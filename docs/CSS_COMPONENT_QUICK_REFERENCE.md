# CSS Component Quick Reference
## For Plugin Developers & Internal Use

This is a quick reference guide for the unified CSS component system. For detailed documentation, see `CSS_COMPONENT_GUIDE.md`.

---

## 🎨 Component Classes

### Buttons

```html
<!-- Primary Button -->
<button class="btn btn--primary">Save</button>

<!-- Secondary Button -->
<button class="btn btn--secondary">Cancel</button>

<!-- Danger Button -->
<button class="btn btn--danger">Delete</button>

<!-- Sizes -->
<button class="btn btn--primary btn--sm">Small</button>
<button class="btn btn--primary btn--lg">Large</button>

<!-- Icon Button -->
<button class="btn btn--icon">
  <svg>...</svg>
</button>

<!-- States -->
<button class="btn btn--primary" disabled>Disabled</button>
<button class="btn btn--primary btn--loading">Loading...</button>
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

```html
<!-- Basic Form Group -->
<div class="form-group">
  <label for="name">Name</label>
  <input type="text" id="name" class="input" />
</div>

<!-- Required Field -->
<div class="form-group form-group--required">
  <label for="email">Email</label>
  <input type="email" id="email" class="input" required />
</div>

<!-- Error State -->
<div class="form-group form-group--error">
  <label for="password">Password</label>
  <input type="password" id="password" class="input input--error" />
  <small class="form-error">Password is required</small>
</div>

<!-- Success State -->
<div class="form-group form-group--success">
  <label for="username">Username</label>
  <input type="text" id="username" class="input input--success" />
</div>

<!-- Form Row (Horizontal Layout) -->
<div class="form-row">
  <div class="form-group">
    <label>First Name</label>
  </div>
  <div class="form-group">
    <label>Last Name</label>
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

```html
<!-- Basic Table -->
<table class="table">
  <thead>
    <tr>
      <th>Name</th>
      <th>Email</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>John Doe</td>
      <td>john@example.com</td>
    </tr>
  </tbody>
</table>

<!-- Table Variants -->
<table class="table table--striped table--hover table--bordered">
  <!-- content -->
</table>

<!-- Smart Table (for entries) -->
<div class="smart-table">
  <div class="smart-table__head">
    <div class="smart-table__row">
      <div class="smart-table__cell">Title</div>
      <div class="smart-table__cell">Status</div>
    </div>
  </div>
  <div class="smart-table__body">
    <div class="smart-table__row">
      <div class="smart-table__cell">Entry Title</div>
      <div class="smart-table__cell">Published</div>
    </div>
  </div>
</div>
```

**Available Classes:**
- `.table` - Base table
- `.table--striped` - Striped rows
- `.table--bordered` - Bordered table
- `.table--hover` - Hover effect on rows
- `.smart-table` - Smart table container
- `.smart-table__head` - Table header
- `.smart-table__body` - Table body
- `.smart-table__row` - Table row
- `.smart-table__cell` - Table cell

---

### Modals

```html
<!-- Basic Modal -->
<div class="modal modal--md">
  <div class="modal__backdrop"></div>
  <div class="modal__content">
    <div class="modal__header">
      <h3>Modal Title</h3>
      <button class="modal__close">&times;</button>
    </div>
    <div class="modal__body">
      <p>Modal content goes here</p>
    </div>
    <div class="modal__footer">
      <button class="btn btn--secondary">Cancel</button>
      <button class="btn btn--primary">Save</button>
    </div>
  </div>
</div>

<!-- Modal Sizes -->
<div class="modal modal--sm">Small Modal</div>
<div class="modal modal--md">Medium Modal (default)</div>
<div class="modal modal--lg">Large Modal</div>
<div class="modal modal--xl">Extra Large Modal</div>
```

**Available Classes:**
- `.modal` - Modal container
- `.modal--sm` - Small modal
- `.modal--md` - Medium modal (default)
- `.modal--lg` - Large modal
- `.modal--xl` - Extra large modal
- `.modal__backdrop` - Modal backdrop
- `.modal__content` - Modal content wrapper
- `.modal__header` - Modal header
- `.modal__body` - Modal body
- `.modal__footer` - Modal footer
- `.modal__close` - Close button

---

### Switches (Toggle)

```html
<!-- Basic Switch -->
<label class="switch">
  <input type="checkbox" />
  <span class="switch__slider"></span>
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

<!-- Disabled Switch -->
<label class="switch switch--disabled">
  <input type="checkbox" disabled />
  <span class="switch__slider"></span>
</label>
```

**Available Classes:**
- `.switch` - Toggle switch container (UNIFIED - use this for all toggles)
- `.switch--sm` - Small size
- `.switch--lg` - Large size
- `.switch--disabled` - Disabled state
- `.switch__slider` - Switch slider element

**Note:** This is the ONLY toggle switch style. All other toggle/switch classes will be deprecated.

---

### Cards

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

<!-- Card Variants -->
<div class="card card--elevated">Elevated Card</div>
<div class="card card--interactive">Clickable Card</div>
```

**Available Classes:**
- `.card` - Base card
- `.card--elevated` - Elevated/shadowed card
- `.card--interactive` - Clickable card
- `.card__header` - Card header
- `.card__body` - Card body
- `.card__footer` - Card footer

---

### Badges

```html
<!-- Status Badges -->
<span class="badge badge--success">Published</span>
<span class="badge badge--warning">Draft</span>
<span class="badge badge--error">Error</span>
<span class="badge badge--info">Info</span>
```

**Available Classes:**
- `.badge` - Base badge
- `.badge--success` - Success badge
- `.badge--warning` - Warning badge
- `.badge--error` - Error badge
- `.badge--info` - Info badge
- `.badge--primary` - Primary badge

---

### Dropdowns

```html
<!-- Dropdown Menu -->
<div class="dropdown">
  <button class="btn btn--secondary dropdown__trigger">
    Options
  </button>
  <div class="dropdown__menu">
    <a href="#" class="dropdown__item">Option 1</a>
    <a href="#" class="dropdown__item">Option 2</a>
    <div class="dropdown__divider"></div>
    <a href="#" class="dropdown__item">Option 3</a>
  </div>
</div>
```

**Available Classes:**
- `.dropdown` - Dropdown container
- `.dropdown__trigger` - Dropdown trigger button
- `.dropdown__menu` - Dropdown menu
- `.dropdown__item` - Dropdown menu item
- `.dropdown__divider` - Dropdown divider

---

### Empty States

```html
<!-- Empty State -->
<div class="empty-state">
  <div class="empty-state__icon">
    <svg>...</svg>
  </div>
  <h3 class="empty-state__title">No items found</h3>
  <p class="empty-state__description">Get started by creating your first item.</p>
  <button class="btn btn--primary">Create Item</button>
</div>
```

**Available Classes:**
- `.empty-state` - Empty state container
- `.empty-state__icon` - Icon container
- `.empty-state__title` - Title
- `.empty-state__description` - Description
- `.empty-state__action` - Action button container

---

## 🎨 CSS Variables

### Colors

```css
/* Brand Colors */
--color-primary: #7CE3BF;
--color-primary-hover: #8CECCA;

/* Neutral Colors */
--color-neutral-100 through --color-neutral-900

/* Semantic Colors */
--color-success: #15803d;
--color-error: #b91c1c;
--color-warning: #92400e;
```

### Spacing

```css
--space-1: 0.25rem;  /* 4px */
--space-2: 0.5rem;   /* 8px */
--space-3: 0.75rem;  /* 12px */
--space-4: 1rem;     /* 16px */
--space-6: 1.5rem;   /* 24px */
--space-8: 2rem;     /* 32px */
```

### Border Radius

```css
--radius-sm: 0.25rem;  /* 4px */
--radius-md: 0.5rem;    /* 8px */
--radius-lg: 0.75rem;   /* 12px */
```

### Shadows

```css
--shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
--shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
--shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
```

---

## 📦 Container Components

### Page Container

```html
<div class="page-container">
  <div class="page-header">
    <h1>Page Title</h1>
  </div>
  <div class="page-content">
    <!-- Content -->
  </div>
</div>
```

### Section Container

```html
<div class="section">
  <div class="section__header">
    <h2>Section Title</h2>
  </div>
  <div class="section__body">
    <!-- Content -->
  </div>
</div>
```

---

## 🔧 Utility Classes

### Spacing

```html
<!-- Margin -->
<div class="m-1">margin: var(--space-1)</div>
<div class="m-2">margin: var(--space-2)</div>
<div class="m-4">margin: var(--space-4)</div>

<!-- Padding -->
<div class="p-1">padding: var(--space-1)</div>
<div class="p-2">padding: var(--space-2)</div>
<div class="p-4">padding: var(--space-4)</div>
```

### Display

```html
<div class="d-flex">display: flex</div>
<div class="d-grid">display: grid</div>
<div class="d-none">display: none</div>
```

### Text

```html
<p class="text-primary">Primary text color</p>
<p class="text-secondary">Secondary text color</p>
<p class="text-center">Centered text</p>
<p class="text-bold">Bold text</p>
```

---

## 🚀 Plugin Development

### Using System Styles in Plugins

```html
<!-- In your plugin template -->
<div class="card">
  <div class="card__header">
    <h3>Plugin Settings</h3>
  </div>
  <div class="card__body">
    <div class="form-group">
      <label>Setting Name</label>
      <input type="text" class="input" />
    </div>
    <div class="form-group">
      <label class="switch">
        <input type="checkbox" />
        <span class="switch__slider"></span>
        Enable Feature
      </label>
    </div>
  </div>
  <div class="card__footer">
    <div class="form-actions">
      <button class="btn btn--secondary">Cancel</button>
      <button class="btn btn--primary">Save</button>
    </div>
  </div>
</div>
```

### Customization

You can customize components using CSS variables:

```css
/* In your plugin CSS */
.my-plugin {
  --color-primary: #custom-color;
}

.my-plugin .btn--primary {
  /* Will use custom color */
}
```

---

## 📚 Full Documentation

For complete documentation, see:
- `CSS_COMPONENT_GUIDE.md` - Detailed component documentation
- `CSS_VARIABLES.md` - Complete variable reference
- `PLUGIN_CSS_GUIDE.md` - Plugin developer guide

---

**Last Updated:** [Current Date]

