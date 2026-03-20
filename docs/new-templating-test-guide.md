# New Admin Templating System - Test Guide

## Overview

We've extended the admin template parser to support building admin pages with pure HTML and template syntax, eliminating the need for embedded PHP code.

## What's New

### Extended Template Directives

#### 1. URL Parameters
```html
{url_param 'collection'}
{url_param 'page' default='1'}
```

#### 2. Admin URLs
```html
{admin_url 'entries'}
{admin_url 'entry-editor' collection='collection.slug'}
{admin_url 'entry-detail' collection='collection.slug' entry='entry.id'}
```
**Note:** Variable paths in parameters don't need extra braces - just write the path directly.

#### 3. CSRF Protection
```html
{csrf_field}  <!-- Generates hidden input -->
{csrf_token}  <!-- Just the token value -->
```

#### 4. Scripts and Styles
```html
{include_script 'entries.js'}
{include_style 'custom.css'}
```

#### 5. Reusable Components
```html
{component 'status-badge' status='entry.status' reviewer='entry.reviewer'}
```
**Note:** Component parameters use the same syntax as admin_url - no extra braces needed for variables.

#### 6. Translations
```html
{translate 'admin.title'}
{__ 'admin.save'}
```

#### 7. JSON Output
```html
<script>
window.collectionData = {json collection};
</script>
```

#### 8. Array Count
```html
<span>Total: {count entries}</span>
```

#### 9. Dynamic Field Access
```html
{field_value entry.__cells fieldname='field.name' suffix='_html'}
```

## Testing the New Entries Page

### 1. Access the Old Version (Current)
```
http://localhost:8001/admin/index.php?page=entries&collection=your-collection
```

### 2. Access the New Template Version
```
http://localhost:8001/admin/index.php?page=entries&collection=your-collection&use_new_template=1
```

### 3. Compare Functionality

Test these features in both versions:

- [ ] Page loads without errors
- [ ] Breadcrumbs display correctly
- [ ] "New Entry" button works
- [ ] Status filter dropdown functions
- [ ] Collection actions dropdown works
- [ ] Entries table displays all data
- [ ] Sub-entry count badges show (if applicable)
- [ ] Status badges render with correct colors
- [ ] Field values display correctly
- [ ] Edit and Delete buttons work
- [ ] Bulk selection works
- [ ] JavaScript initializes properly (window.collectionData)

## File Structure

```
admin/
├── template_parser.php          # Extended with new directives
├── templates/
│   ├── entries.html            # Old version (PHP-based)
│   ├── entries.new.html        # New version (template-based)
│   └── components/             # Reusable components
│       └── status-badge.html   # Status badge component
└── index.php                   # Updated to support ?use_new_template=1
```

## Migration Strategy

### For Each Admin Page:

1. **Create** `page-name.new.html` alongside existing `page-name.html`
2. **Convert** PHP code to template directives
3. **Extract** complex logic into components
4. **Test** with `?use_new_template=1` parameter
5. **Compare** functionality side-by-side
6. **Replace** old template when confident
7. **Delete** old template file

### Pages to Migrate (Priority Order):

1. ✅ **entries.html** - Currently in testing
2. **entry-editor.html** - Form-heavy page
3. **entry-detail.html** - Complex nested data
4. **collection-editor.html** - Schema builder
5. **content.html** - Collection list
6. **settings.html** - Settings forms
7. **plugins.html** - Plugin management
8. **dashboard.html** - Already uses template parser
9. Other pages as needed

## Benefits

- ✅ **Clean separation** of logic and presentation
- ✅ **No PHP tags** in templates
- ✅ **Reusable components** for common UI patterns
- ✅ **Consistent syntax** across frontend and admin
- ✅ **Easier for designers** to work with templates
- ✅ **Better maintainability** and testability

## Backward Compatibility

- Old templates continue to work without changes
- New directives are opt-in
- Can migrate pages one at a time
- No breaking changes to existing functionality

## Next Steps

1. Test entries.new.html thoroughly
2. Create additional components as needed
3. Migrate next priority page
4. Document any new patterns discovered
5. Build component library for common UI elements
