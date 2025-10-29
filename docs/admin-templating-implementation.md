# Admin Templating System - Implementation Guide

## Overview

This guide walks through implementing the new admin templating system in HaruCMS. The system provides complete separation between admin frontend appearance and functionality.

## Files Created

### 1. Template Structure
```
admin/
├── templates/
│   ├── base.html                    # Base admin layout
│   ├── dashboard.html               # Dashboard template
│   ├── content.html                 # Content management template
│   ├── media.html                   # Media management template
│   ├── settings.html                # Settings template
│   └── components/
│       ├── sidebar.html             # Navigation sidebar
│       ├── table.html               # Reusable data table
│       ├── form.html                # Reusable form
│       ├── modal.html               # Reusable modal
│       └── media-grid.html          # Media grid component
├── js/
│   └── admin-engine.js             # Admin template engine
├── index-new.html                   # New admin entry point
└── test-templating.html            # Test page
```

### 2. API Endpoints
```
api/
└── admin_templates.php              # Template and data API
```

### 3. Documentation
```
docs/
├── admin-templating.md              # System documentation
└── admin-templating-implementation.md # This guide
```

## Implementation Steps

### Step 1: Test the Current System

1. **Start the server:**
   ```bash
   php -S localhost:8001
   ```

2. **Test the new admin:**
   - Visit: `http://localhost:8001/admin/test-templating.html`
   - Check browser console for template loading logs
   - Verify templates are loading correctly

### Step 2: Migrate Existing Pages

#### Dashboard Migration
1. **Current:** `admin/dashboard.html` + `admin/js/dashboard.js`
2. **New:** `admin/templates/dashboard.html`
3. **Benefits:** No script cleanup, easier customization

#### Content Page Migration
1. **Current:** `admin/content.html` + `admin/js/content.js`
2. **New:** `admin/templates/content.html`
3. **Benefits:** Template-driven, reusable components

### Step 3: Add Component Library

#### Table Component
```html
<!-- components/table.html -->
<div class="data-table">
  {{ for entry in entries }}
  <tr>
    <td>{{ entry.title }}</td>
    <td>{{ formatDate entry.created_at 'relative' }}</td>
  </tr>
  {{ endfor }}
</div>
```

#### Form Component
```html
<!-- components/form.html -->
<form id="{{ form_id }}" class="admin-form">
  {{ for field in fields }}
  <div class="form-group">
    <label>{{ field.label }}</label>
    <input type="{{ field.type }}" value="{{ field.value }}">
  </div>
  {{ endfor }}
</form>
```

### Step 4: Update API Endpoints

#### Current API Structure
```php
// api/admin_templates.php
function getDashboardData() {
    return [
        'total_collections' => count($collections),
        'total_entries' => $totalEntries,
        'recent_entries' => $recentEntries,
        'plugins' => $plugins
    ];
}
```

#### Template Usage
```html
<!-- templates/dashboard.html -->
<div class="stat-card">
  <h3>{{ total_collections }}</h3>
  <p>Collections</p>
</div>
```

## Testing the System

### 1. Template Loading Test
```javascript
// Test template loading
const template = await engine.loadTemplate('dashboard.html');
console.log('Template loaded:', template);
```

### 2. Data Fetching Test
```javascript
// Test data fetching
const data = await engine.fetchDashboardData();
console.log('Dashboard data:', data);
```

### 3. Template Rendering Test
```javascript
// Test template rendering
const rendered = await engine.renderTemplate(template, data);
console.log('Rendered HTML:', rendered);
```

## Migration Strategy

### Phase 1: Parallel Implementation
- Keep existing SPA admin working
- Implement new templating system alongside
- Test both systems simultaneously

### Phase 2: Gradual Migration
- Migrate one page at a time
- Start with dashboard (simplest)
- Move to content, media, settings

### Phase 3: Complete Switch
- Remove old SPA system
- Use only templating system
- Update all references

## Benefits Achieved

### ✅ Problem Solved
- **No more script conflicts** - Each page load is fresh
- **No cleanup needed** - Templates are stateless
- **Easier debugging** - Isolated template rendering

### ✅ New Capabilities
- **Easy customization** - Edit HTML templates
- **Consistent syntax** - Same as main CMS
- **Component reuse** - Shared components
- **Better separation** - Templates vs logic

### ✅ User Experience
- **Same navigation** - Hash-based routing
- **Same performance** - No full page reloads
- **Better reliability** - No script interference

## Next Steps

### Immediate
1. Test the current implementation
2. Fix any API endpoint issues
3. Add missing data functions

### Short Term
1. Migrate dashboard page
2. Add more components
3. Improve error handling

### Long Term
1. Migrate all admin pages
2. Add plugin template support
3. Create template editor

## Troubleshooting

### Common Issues

#### Template Not Loading
```javascript
// Check template path
const response = await fetch('/admin/templates/dashboard.html');
console.log('Response status:', response.status);
```

#### Data Not Fetching
```javascript
// Check API endpoint
const response = await fetch('/api/admin_templates.php?action=get_page_data&page=dashboard');
console.log('API response:', await response.json());
```

#### Template Not Rendering
```javascript
// Check template syntax
console.log('Template content:', template);
console.log('Data:', data);
```

### Debug Mode
```javascript
// Enable debug logging
HaruCMS.AdminEngine.debug = true;
```

## Conclusion

The admin templating system provides a robust, maintainable solution that:
- ✅ Solves the original SPA problems
- ✅ Maintains all SPA benefits
- ✅ Adds significant new capabilities
- ✅ Aligns with HaruCMS philosophy

The system is ready for implementation and will provide a much better foundation for the admin interface. 