# Complex Pages Migration Guide

## Overview

This guide explains how to migrate the complex admin pages (entry-editor, entry-detail, collection-editor, settings) using the new templating system and component architecture.

## Component-First Approach

### Why Components?

Complex pages have:
- **Repeated patterns** - Status badges, breadcrumbs, forms
- **Nested logic** - Sub-entries, nested sub-entries
- **Dynamic content** - Field types, schemas
- **Context-dependent behavior** - URLs, actions, state

Components solve these by encapsulating complexity into reusable, tested units.

## Advanced Components Created

### 1. Breadcrumb Builder (`breadcrumb-builder.html`)

**Purpose**: Handle complex navigation for entries with sub-entries and nested sub-entries

**Parameters**:
- `page` - Base page name
- `collection` - Collection object
- `entry` - Entry object
- `is_sub_entry` - Boolean flag
- `parent_entry_id` - Parent entry ID
- `sub_collection` - Sub-collection slug
- `is_nested_sub_entry` - Boolean for nesting
- `parent_sub_collection` - Parent sub-collection
- `parent_sub_entry_id` - Parent sub-entry ID

**Usage**:
```html
{component 'breadcrumb-builder' 
    page='entries' 
    collection='collection' 
    entry='entry' 
    is_sub_entry='is_sub_entry' 
    parent_entry_id='parent_entry_id'}
```

**Handles**:
- Simple entry: Content → Collection → Entry
- Sub-entry: Content → Collection → Parent Entry → Sub-Entry
- Nested: Content → Collection → Parent → Sub-Parent → Nested Sub-Entry

### 2. Cancel Button (`cancel-button.html`)

**Purpose**: Smart cancel button that knows where to return based on context

**Parameters**:
- `return_url` - Explicit return URL (priority)
- `collection_slug` - Collection for default return
- `is_sub_entry` - Boolean flag
- `parent_entry_id` - Parent entry ID
- `sub_collection` - Sub-collection slug
- `label` - Button text (optional)

**Usage**:
```html
{component 'cancel-button' 
    collection_slug='collection.slug'
    is_sub_entry='is_sub_entry'
    parent_entry_id='parent_entry_id'}
```

**Logic**:
1. If `return_url` exists, use it
2. Else if sub-entry, return to entry-detail
3. Else return to entries list

### 3. Form Field (`form-field.html`)

**Purpose**: Dynamic form field rendering with fieldtype integration

**Parameters**:
- `field` - Field definition object
- `value` - Current field value
- `entry` - Entry context

**Usage**:
```html
{for each field in schema.fields}
    {component 'form-field' 
        field='field' 
        value='entry.{field.name}'}
{endfor}
```

**Features**:
- Renders field container
- Shows label, description, validation
- Initializes fieldtype JavaScript
- Queues for lazy loading

### 4. Sub-Entry List (`sub-entry-list.html`)

**Purpose**: Display and manage sub-entries for a collection

**Parameters**:
- `sub_collection` - Sub-collection definition
- `sub_entries` - Array of sub-entries
- `parent_entry_id` - Parent entry ID
- `collection_slug` - Parent collection

**Usage**:
```html
{for each sub_collection in collection.sub_collections}
    {component 'sub-entry-list'
        sub_collection='sub_collection'
        sub_entries='subEntriesData'
        parent_entry_id='entry.id'
        collection_slug='collection.slug'}
{endfor}
```

**Features**:
- Add new sub-entry button
- List with status badges
- Edit/delete actions
- Empty state handling
- Nested count indicators

### 5. Activity Timeline (`activity-timeline.html`)

**Purpose**: Display entry activity history

**Parameters**:
- `activities` - Array of activity items
- `show_limit` - Optional limit

**Usage**:
```html
{component 'activity-timeline' activities='entry.activity'}
```

**Shows**:
- Activity type icon
- Description
- User and timestamp
- Empty state

## Simplified Templates

### Entry Editor (`entry-editor.simplified.html`)

**Reduces**: 1600+ lines → ~150 lines (90% reduction)

**Key Simplifications**:
1. **Breadcrumbs** → `breadcrumb-builder` component
2. **Cancel button** → `cancel-button` component
3. **Form fields** → `form-field` component loop
4. **Complex logic** → Moved to components/JavaScript

**Template Structure**:
```html
<!-- Breadcrumbs -->
{component 'breadcrumb-builder' ...}

<!-- Actions -->
<button type="submit">Save</button>
{component 'cancel-button' ...}

<!-- Form -->
<form>
    {for each field in schema.fields}
        {component 'form-field' field='field' value='entry.{field.name}'}
    {endfor}
</form>

<!-- Initialize JavaScript -->
<script>
window.entryEditorData = {json data};
</script>
```

### Entry Detail (`entry-detail.simplified.html`)

**Reduces**: 1668 lines → ~180 lines (89% reduction)

**Key Simplifications**:
1. **Breadcrumbs** → `breadcrumb-builder` component
2. **Status display** → `status-badge` component
3. **Sub-entries** → `sub-entry-list` component loop
4. **Activity** → `activity-timeline` component
5. **Delete handlers** → Inline JavaScript

**Template Structure**:
```html
<!-- Breadcrumbs -->
{component 'breadcrumb-builder' ...}

<!-- Actions -->
<a href="edit">Edit</a>
<button id="delete">Delete</button>

<!-- Entry Info -->
<div>
    <h1>{entry.title}</h1>
    {component 'status-badge' status='entry.status'}
    
    {for each field in schema.fields}
        <div>{field.label}: {entry.{field.name}}</div>
    {endfor}
</div>

<!-- Sub-Collections -->
{for each sub_collection in collection.sub_collections}
    {component 'sub-entry-list' ...}
{endfor}

<!-- Activity -->
{component 'activity-timeline' activities='entry.activity'}
```

## Migration Strategy

### Phase 1: Simplified Versions (Current)
- ✅ Create component library
- ✅ Create simplified templates
- ⏳ Test simplified templates
- ⏳ Iterate on components

### Phase 2: Enhanced Features
- Add missing edge cases
- Handle all sub-entry scenarios
- Complete fieldtype integration
- Add validation displays

### Phase 3: Full Feature Parity
- Match all original functionality
- Handle all error states
- Complete JavaScript integration
- Performance optimization

## Testing Simplified Templates

### Entry Editor Test
```bash
# Simple entry
http://localhost:8001/admin/index.php?page=entry-editor&collection=01_Blog&use_simplified=1

# Edit existing
http://localhost:8001/admin/index.php?page=entry-editor&collection=01_Blog&entry=123&use_simplified=1

# Sub-entry
http://localhost:8001/admin/index.php?page=entry-editor&collection=01_Blog&entry=123&sub_collection=comments&use_simplified=1
```

### Entry Detail Test
```bash
# Simple entry
http://localhost:8001/admin/index.php?page=entry-detail&collection=01_Blog&entry=123&use_simplified=1

# With sub-entries
http://localhost:8001/admin/index.php?page=entry-detail&collection=01_Blog&entry=123&use_simplified=1
```

## Benefits Achieved

### Code Reduction
- **Entry Editor**: 1600+ → ~150 lines (90% less)
- **Entry Detail**: 1668 → ~180 lines (89% less)
- **Total Saved**: ~3000 lines of template code

### Maintainability
- ✅ Components are testable units
- ✅ Changes propagate to all pages
- ✅ Logic centralized
- ✅ Easier debugging

### Readability
- ✅ Clean template structure
- ✅ Self-documenting components
- ✅ Clear data flow
- ✅ No nested PHP logic

## Known Limitations (Simplified Versions)

### Entry Editor
- ⚠️ Field validation display incomplete
- ⚠️ Some edge cases not handled
- ⚠️ Draft autosave not implemented
- ⚠️ Media uploads need testing

### Entry Detail
- ⚠️ Nested sub-entries need deeper testing
- ⚠️ Some activity types not shown
- ⚠️ Permissions not fully integrated

### How to Address
1. Use simplified version as base
2. Add features incrementally
3. Test each addition
4. Keep component-based approach

## Router Integration

### Adding Simplified Template Support

In `admin/index.php`, add after `use_new_template` check:

```php
// Support testing simplified complex pages
$useSimplified = isset($_GET['use_simplified']) && $_GET['use_simplified'] == '1';
if ($useSimplified && $templateFile) {
    $simplifiedFile = str_replace('.html', '.simplified.html', $templateFile);
    $simplifiedPath = __DIR__ . '/templates/' . $simplifiedFile;
    if (file_exists($simplifiedPath)) {
        $templateFile = $simplifiedFile;
    }
}
```

## Next Steps

### Immediate
1. Test simplified templates thoroughly
2. Document any issues found
3. Enhance components as needed

### Short Term
1. Add missing features to components
2. Create additional helper components
3. Test all sub-entry scenarios
4. Complete fieldtype integration

### Long Term
1. Achieve full feature parity
2. Replace original templates
3. Optimize performance
4. Build developer tools

## Component Library Status

| Component | Status | Lines | Purpose |
|-----------|--------|-------|---------|
| breadcrumb-builder | ✅ | ~60 | Complex navigation |
| cancel-button | ✅ | ~35 | Smart cancel |
| form-field | ✅ | ~50 | Dynamic fields |
| sub-entry-list | ✅ | ~60 | Sub-entry display |
| activity-timeline | ✅ | ~50 | Activity history |
| status-badge | ✅ | ~40 | Status display |
| page-header | ✅ | ~20 | Simple breadcrumbs |
| action-bar | ✅ | ~30 | Action buttons |
| empty-state | ✅ | ~15 | Empty messaging |
| modal | ✅ | ~40 | Dialogs |

**Total**: 10 components, ~400 lines

**Saved**: ~3000 lines in complex pages

**Ratio**: 7.5x code reduction through componentization

## Conclusion

The component-first approach successfully:
- ✅ Reduces complexity by 90%
- ✅ Improves maintainability
- ✅ Enables incremental migration
- ✅ Provides reusable patterns
- ✅ Keeps templates clean

The simplified versions are ready for testing and iteration!

---

**Last Updated**: December 19, 2025  
**Status**: Simplified Templates Created  
**Next**: Testing & Enhancement Phase
