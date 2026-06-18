# Birkly Admin Templating System

## Overview

The Birkly Admin Templating System provides a complete separation between admin frontend appearance and functionality, following the same philosophy as the main CMS content delivery system. This allows users to easily customize the admin panel's appearance without touching any PHP code or complex JavaScript.

## Architecture

### Current vs New Approach

**Current Approach:**
```
Admin HTML → Direct JS → API → PHP Backend
```

**New Approach:**
```
Admin Template → Template Engine → API → PHP Backend
```

### Key Benefits

1. **Complete Separation of Concerns**: Frontend appearance is completely decoupled from backend functionality
2. **Easy Customization**: Users can modify HTML/CSS templates without touching PHP or JavaScript
3. **Consistency**: Uses the same templating syntax as the main CMS template engine
4. **Simplicity**: Aligns with Birkly's philosophy of simple, elegant solutions
5. **Maintainability**: Changes to appearance don't affect functionality

## File Structure

```
admin/
├── templates/
│   ├── base.html              # Base admin layout
│   ├── dashboard.html         # Dashboard template
│   ├── content.html           # Content management template
│   ├── media.html             # Media management template
│   ├── settings.html          # Settings template
│   └── components/
│       ├── sidebar.html       # Navigation sidebar
│       ├── table.html         # Reusable data table
│       ├── form.html          # Reusable form
│       └── modal.html         # Reusable modal
├── js/
│   └── admin-engine.js       # Admin template engine
└── index-new.html            # New admin entry point
```

## Template Syntax

**Canonical syntax** uses single braces and plain English — `{for each entry in entries}`, `{if x exist}`, `{collection.title}`. This matches the public site templating language documented in [Birkly-Docs canonical syntax](https://github.com/Raaakete/Birkly-Docs/blob/main/03-templating/canonical-syntax.md).

Legacy double-brace `{{ variable }}` is still parsed for backward compatibility but should not be used in new admin templates.

### Variables
```html
{collection.title}
{entry.created_at}
```

### Conditionals
```html
{if entries exist}
  <div>Entries found</div>
{else}
  <div>No entries</div>
{endif}
```

### Loops
```html
{for each entry in entries}
  <div class="entry">
    <h3>{entry.title}</h3>
  </div>
{endfor}
```

### Admin helpers
```html
<a href="{admin_url 'entries'}">Entries</a>
<a href="{admin_url 'entry-editor' collection='collection.slug'}">Edit</a>
{translate 'admin.dashboard'}
{include_script 'activity.js'}
Total: {count entries}
```

## Reference page pattern (M3-T4)

`admin/templates/activity.html` is the template-driven reference:

- **Template:** markup + `{translate}` + `{include_script}` only — no inline `<script>` business logic
- **Behavior:** `admin/js/activity.js` fetches from API, binds DOM events, renders
- **CSRF:** read from `<meta name="csrf-token">` in layout (not inline `window.*Data`)

Do not add fetch/API logic inside admin HTML templates.

## Legacy syntax (deprecated)

The following double-brace examples are **deprecated** — kept for migration reference only:

```html
{{ page_title }}
{{ for collection in collections }} ... {{ endfor }}
```

## How It Works

### 1. Template Engine
The `admin-engine.js` handles:
- Template loading and caching
- Template inheritance and includes
- Variable replacement
- Navigation management
- Data fetching from API endpoints

### 2. API Integration
The `api/admin_templates.php` provides:
- Template serving
- Page data fetching
- Server-side template rendering (optional)

### 3. Data Flow
1. User navigates to admin page
2. Admin engine loads appropriate template
3. Engine fetches data from API endpoints
4. Template is rendered with data
5. Content is injected into the page

## Creating Custom Templates

### Basic Template Structure
```html
{{ extends "base.html" }}

{{ block "content" }}
<div class="custom-page">
  <h1>{{ page_title }}</h1>
  
  {{ if data }}
    <div class="data-section">
      {{ for item in data }}
        <div class="item">
          <h3>{{ item.title }}</h3>
          <p>{{ item.description }}</p>
        </div>
      {{ endfor }}
    </div>
  {{ endif }}
</div>
{{ endblock }}
```

### Custom Components
```html
<!-- components/custom-widget.html -->
<div class="custom-widget">
  <h3>{{ widget_title }}</h3>
  <div class="widget-content">
    {{ widget_content }}
  </div>
</div>
```

### Using Components
```html
{{ include "components/custom-widget.html" with widget_title="My Widget", widget_content="Widget content here" }}
```

## API Endpoints

### Template Endpoints
- `GET /api/admin_templates.php?action=get_template&template=dashboard.html`
- `POST /api/admin_templates.php?action=render_template`

### Data Endpoints
- `GET /api/admin_templates.php?action=get_page_data&page=dashboard`
- `GET /api/admin_templates.php?action=get_page_data&page=content`
- `GET /api/admin_templates.php?action=get_page_data&page=media`
- `GET /api/admin_templates.php?action=get_page_data&page=settings`

## Customization Examples

### Custom Dashboard
```html
<!-- templates/dashboard.html -->
{{ extends "base.html" }}

{{ block "content" }}
<div class="custom-dashboard">
  <div class="welcome-section">
    <h1>Welcome to {{ site_name }}</h1>
    <p>Today is {{ current_date }}</p>
  </div>
  
  <div class="stats-grid">
    {{ for stat in stats }}
      <div class="stat-card">
        <div class="stat-icon">{{ stat.icon }}</div>
        <div class="stat-content">
          <h3>{{ stat.value }}</h3>
          <p>{{ stat.label }}</p>
        </div>
      </div>
    {{ endfor }}
  </div>
</div>
{{ endblock }}
```

### Custom Collection Display
```html
<!-- templates/content.html -->
{{ extends "base.html" }}

{{ block "content" }}
<div class="collections-page">
  <div class="page-header">
    <h1>Content Collections</h1>
    <button class="btn btn-primary" onclick="createCollection()">New Collection</button>
  </div>
  
  <div class="collections-grid">
    {{ for collection in collections }}
      <div class="collection-card">
        <div class="collection-icon">📦</div>
        <div class="collection-info">
          <h3>{{ collection.name }}</h3>
          <p>{{ collection.description }}</p>
          <div class="collection-meta">
            <span>{{ collection.entry_count }} entries</span>
            <span>{{ collection.field_count }} fields</span>
          </div>
        </div>
        <div class="collection-actions">
          <button onclick="viewCollection('{{ collection.slug }}')">View</button>
          <button onclick="editCollection('{{ collection.slug }}')">Edit</button>
        </div>
      </div>
    {{ endfor }}
  </div>
</div>
{{ endblock }}
```

## Migration Path

### Phase 1: Template System
1. Create template structure
2. Implement admin engine
3. Create API endpoints
4. Test with basic templates

### Phase 2: Component Library
1. Create reusable components
2. Build form components
3. Create table components
4. Add modal components

### Phase 3: Plugin Integration
1. Plugin template support
2. Plugin data integration
3. Plugin component system

### Phase 4: Advanced Features
1. Template caching
2. Server-side rendering
3. Template validation
4. Template debugging tools

## Benefits for Users

1. **Easy Customization**: Change admin appearance by editing HTML templates
2. **No PHP Knowledge Required**: Frontend changes don't require PHP understanding
3. **Consistent Experience**: Same templating syntax across the entire system
4. **Future-Proof**: Template changes survive CMS updates
5. **Plugin-Friendly**: Plugins can provide their own templates

## Technical Advantages

1. **Separation of Concerns**: Clear boundary between presentation and logic
2. **Maintainability**: Changes to appearance don't affect functionality
3. **Performance**: Template caching and efficient rendering
4. **Extensibility**: Easy to add new templates and components
5. **Testing**: Templates can be tested independently

## Conclusion

This templating system transforms the Birkly admin from a monolithic SPA into a flexible, template-driven interface that maintains the same simplicity and elegance as the main CMS while providing unprecedented customization capabilities.

The system follows Birkly's core principles:
- **Simplicity**: Easy to understand and use
- **Separation of Concerns**: Clear boundaries between layers
- **No External Dependencies**: Everything works within the CMS
- **Complete Freedom**: Users can customize without limitations 