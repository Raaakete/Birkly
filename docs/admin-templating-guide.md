# Birkly Admin Templating Guide

## Overview

The Birkly admin panel now supports a pure templating approach, eliminating the need for embedded PHP code in templates. This guide explains all available directives and best practices for creating admin pages.

## Philosophy

- **Clean Separation**: Logic lives in PHP, presentation in HTML templates
- **Simple Syntax**: Natural, readable template directives
- **Component-Based**: Reusable UI elements
- **Backward Compatible**: Old PHP-based templates still work

## Core Directives

### 1. Variable Output

#### Simple Variables
```html
{variable}                  <!-- Escaped by default -->
{entry.title}              <!-- Dot notation for nested data -->
{entry.__cells.__title}    <!-- Deep nesting supported -->
```

#### Raw Output (Unescaped)
```html
{{variable | raw}}         <!-- For HTML content -->
{{entry.content | raw}}    <!-- Editor content, etc. -->
```

#### With Filters
```html
{{entry.created_at | date:"F j, Y"}}  <!-- Date formatting -->
```

### 2. Conditionals

#### Existence Checks
```html
{if entries exist}
    <div>We have entries!</div>
{endif}

{if collection.sub_collections exist}
    <!-- Sub-collections UI -->
{endif}
```

#### Value Comparisons
```html
{if entry.status == 'published'}
    <span class="published">Live</span>
{else}
    <span class="draft">Draft</span>
{endif}

{if entry.status != 'deleted'}
    <!-- Show entry -->
{endif}
```

#### Boolean Checks
```html
{if collection.settings.allow_comments == true}
    <!-- Comments enabled -->
{endif}
```

#### Multiple Conditions
```html
{if entry.status == 'published' && entry.featured == true}
    <!-- Published and featured -->
{endif}

{if entry.status == 'draft' || entry.status == 'pending'}
    <!-- Draft or pending -->
{endif}
```

### 3. Loops

#### Basic Loop
```html
{for each entry in entries}
    <div>{entry.title}</div>
{endfor}
```

#### Nested Loops
```html
{for each collection in collections}
    <h2>{collection.title}</h2>
    {for each entry in collection.entries}
        <div>{entry.title}</div>
    {endfor}
{endfor}
```

#### Alternative Syntax
```html
{foreach entries as entry}
    <div>{entry.title}</div>
{endforeach}
```

### 4. Admin-Specific Directives

#### Admin URLs
```html
<!-- Simple page link -->
{admin_url 'dashboard'}
<!-- Output: /admin/index.php?page=dashboard -->

<!-- With static parameters -->
{admin_url 'entries' collection='blog'}
<!-- Output: /admin/index.php?page=entries&collection=blog -->

<!-- With dynamic variables -->
{admin_url 'entry-editor' collection='collection.slug' entry='entry.id'}
<!-- Output: /admin/index.php?page=entry-editor&collection=blog&entry=123 -->
```

**Important:** Variable paths in parameters don't need extra braces!

#### URL Parameters
```html
<!-- Get URL parameter -->
{url_param 'collection'}

<!-- With default value -->
{url_param 'page' default='1'}
```

#### CSRF Protection
```html
<!-- In forms -->
<form method="post">
    {csrf_field}
    <!-- Generates: <input type="hidden" name="csrf_token" value="..."> -->
</form>

<!-- Just the token -->
<meta name="csrf-token" content="{csrf_token}">
```

#### Translations
```html
{translate 'admin.save'}
{__ 'admin.cancel'}
```

#### Array Count
```html
<span>Total: {count entries} entries</span>
```

#### JSON Output
```html
<script>
window.collectionData = {json collection};
window.entriesData = {json entries};
</script>
```

#### Include Scripts and Styles
```html
{include_script 'entries.js'}
{include_style 'custom.css'}
```

### 5. Components

Components are reusable template fragments stored in `admin/templates/components/`.

#### Basic Usage
```html
{component 'status-badge' status='entry.status'}
```

#### With Multiple Parameters
```html
{component 'status-badge' status='entry.status' reviewer='entry.reviewer' reviewed_by='entry.reviewed_by'}
```

#### Creating a Component

**File:** `admin/templates/components/status-badge.html`
```html
{if status exist}
    <?php
    $statusClass = $status;
    $statusLabel = __('admin.' . $status);
    // ... more logic ...
    ?>
    <span class="status-badge {$statusClass}">{$statusLabel}</span>
{endif}
```

Components can contain PHP for complex logic but keep the interface clean.

### 6. Dynamic Field Access

For accessing fields dynamically in loops:

```html
{for each field in fields}
    {field_value entry.__cells fieldname='field.name' suffix='_html'}
{endfor}
```

This handles cases where the field name comes from a variable.

## Complete Example: Entry List Page

```html
<div class="entries-page">
    <!-- Page Header -->
    <div class="page-header pt-m pb-m">
        <div class="breadcrumbs">
            <a href="{admin_url 'content'}">{translate 'admin.content'}</a>
            <span class="separator">›</span>
            <span class="current">{collection.title}</span>
        </div>
    </div>
    
    <!-- Actions Bar -->
    <div class="main-actions-bar">
        <a class="btn btn-primary" href="{admin_url 'entry-editor' collection='collection.slug'}">
            {translate 'admin.new_entry'}
        </a>
    </div>
    
    <!-- Entries Table -->
    {if entries exist}
        <div class="entries-table">
            {for each entry in entries}
                <div class="entry-row">
                    <div class="entry-title">
                        <a href="{admin_url 'entry-editor' collection='collection.slug' entry='entry.id'}">
                            {entry.__cells.__title}
                        </a>
                    </div>
                    <div class="entry-status">
                        {component 'status-badge' status='entry.status'}
                    </div>
                </div>
            {endfor}
        </div>
    {else}
        <div class="empty-state">
            <p>{translate 'admin.no_entries'}</p>
        </div>
    {endif}
</div>

<!-- Initialize JavaScript -->
<script>
window.collectionData = {json collection};
</script>
{include_script 'entries.js'}
```

## Migration Checklist

When converting an existing page from PHP to pure templating:

- [ ] Replace `<?php ... ?>` blocks with template directives
- [ ] Convert `<?= htmlspecialchars($var) ?>` to `{var}`
- [ ] Convert `<?= $var ?>` to `{{var | raw}}` (if intentionally unescaped)
- [ ] Replace `foreach` loops with `{for each}`
- [ ] Replace `if` statements with `{if}`
- [ ] Extract complex logic into components
- [ ] Use `{admin_url}` instead of manual URL building
- [ ] Use `{translate}` instead of `__()`
- [ ] Move JavaScript initialization to `<script>` blocks with `{json}`
- [ ] Test with `?use_new_template=1` parameter

## Common Patterns

### Conditional Links
```html
{if entry.__sub_entry_count}
    <a href="{admin_url 'entry-detail' collection='collection.slug' entry='entry.id'}">
        {entry.title}
    </a>
{else}
    <a href="{admin_url 'entry-editor' collection='collection.slug' entry='entry.id'}">
        {entry.title}
    </a>
{endif}
```

### Optional Elements
```html
{if collection.settings.show_author exist}
    {if collection.settings.show_author == true}
        <div class="author">{entry.author}</div>
    {endif}
{endif}
```

### Status Indicators
```html
{if entry.status == 'published'}
    <span class="badge badge-success">{translate 'admin.published'}</span>
{endif}
{if entry.status == 'draft'}
    <span class="badge badge-secondary">{translate 'admin.draft'}</span>
{endif}
```

### Form Fields
```html
<form method="post">
    {csrf_field}
    
    <label for="title">{translate 'admin.title'}</label>
    <input type="text" name="title" value="{entry.title}" required>
    
    <label for="content">{translate 'admin.content'}</label>
    <textarea name="content">{{entry.content | raw}}</textarea>
    
    <button type="submit">{translate 'admin.save'}</button>
</form>
```

## Best Practices

### 1. Keep Templates Simple
Move complex logic to:
- Data preparation in `api/admin_templates.php`
- Reusable components
- JavaScript for dynamic behavior

### 2. Use Components for Repeated UI
Create components for:
- Status badges
- Action buttons
- Empty states
- Modal dialogs
- Form fields

### 3. Always Escape Output
Default `{variable}` is escaped. Use `{{variable | raw}}` only for:
- Editor content
- Pre-formatted HTML
- Component output

### 4. Consistent Naming
- Use snake_case for data keys
- Use kebab-case for component names
- Use dot notation for nested access

### 5. Document Your Components
Add comments at the top of each component:
```html
<!-- Status Badge Component
     Parameters:
     - status: Entry status (required)
     - reviewer: Reviewer name (optional)
     - reviewed_by: Reviewed by name (optional)
-->
{if status exist}
    ...
{endif}
```

## Performance Notes

- Templates are parsed and evaluated once per request
- No caching by default (caching can be added if needed)
- Component includes are evaluated in context
- JSON encoding happens at render time

## Debugging

### Enable Error Display
In `admin/template_parser.php`, errors are captured. For debugging:
```php
// Temporarily set in template_parser.php
ini_set('display_errors', '1');
```

### Check Parsed Output
Add debug output in `renderWithData()`:
```php
// Before eval
file_put_contents('/tmp/template_debug.php', $parsed);
```

### Test Templates in Isolation
Use the test script:
```bash
php test-new-template.php
```

## Next Steps

1. Migrate high-priority pages: entries, entry-editor, collection-editor
2. Build component library for common UI patterns
3. Document custom directives for specific use cases
4. Add template validation/linting tools
5. Consider template caching for production

## Support

For questions or issues with the templating system:
- Check this guide first
- Review `admin/template_parser.php` for implementation details
- Look at `entries.new.html` as a complete example
- Test with the test script before deploying
