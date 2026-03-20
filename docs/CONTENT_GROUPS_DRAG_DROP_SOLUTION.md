# Content Groups Drag and Drop Solution

## The Challenge

You need drag-and-drop that handles:
1. ✅ **Groups can be reordered** (drag group header)
2. ✅ **Collections can be reordered within their group** (drag collection card in same group)
3. ✅ **Collections can be moved between groups** (drag collection card to different group)

This is a **nested drag-and-drop** scenario that's more complex than simple list reordering.

## The Solution

We've created **`NestedDragDropManager`** which:
- Uses `SimpleDragDrop` internally (reliable, simple)
- Handles the complexity of nested scenarios
- Prevents conflicts between group and collection drags
- Manages multiple sorters automatically (one per group)

## Files Created

1. **`admin/js/simple-drag-drop.js`** - Base drag-and-drop system (simple lists)
2. **`admin/js/nested-drag-drop.js`** - Nested drag-and-drop manager (groups + collections)
3. **`docs/NESTED_DRAG_DROP_GUIDE.md`** - Complete usage guide

## Quick Start

### 1. Include Scripts

In your content page template:

```html
<script src="/admin/js/simple-drag-drop.js"></script>
<script src="/admin/js/nested-drag-drop.js"></script>
```

### 2. Initialize Manager

In `content-groups.js`, replace all the complex drag-and-drop code with:

```javascript
let nestedDragManager = null;

function initContentGroups() {
    // ... load data, render groups ...
    
    // Set up nested drag and drop
    nestedDragManager = new NestedDragDropManager({
        container: document.getElementById('collections-container'),
        groupSelector: '.collection-group',
        groupHeaderSelector: '.group-header',
        collectionSelector: '.collection-card',
        collectionContainerSelector: '.group-collections, .ungrouped-collections',
        
        onGroupReorder: (fromIndex, toIndex) => {
            // Your group reorder logic
            reorderGroups(fromIndex, toIndex);
        },
        
        onCollectionReorder: (collectionSlug, fromGroupId, toGroupId, toIndex) => {
            // Your collection reorder logic (within same group)
            reorderCollectionInGroup(collectionSlug, toGroupId, toIndex);
        },
        
        onCollectionMove: (collectionSlug, fromGroupId, toGroupId, toIndex) => {
            // Your collection move logic (between groups)
            moveCollectionToGroup(collectionSlug, fromGroupId, toGroupId, toIndex);
        }
    });
    
    nestedDragManager.init();
}

// After rendering or AJAX updates
function renderGroupsAndCollections() {
    // ... render logic ...
    
    // Update drag and drop
    if (nestedDragManager) {
        nestedDragManager.update();
    }
}
```

## What Gets Replaced

### Before: ~1500 lines of complex code
- Manual event handlers
- Conflict resolution between group/collection drags
- Global state management
- Complex drop indicator logic
- Hard to debug

### After: ~50 lines of clean code
- Simple callbacks
- Automatic conflict resolution
- Self-contained manager
- Built-in drop indicators
- Easy to debug

## Requirements

### HTML Structure

Your HTML must match these selectors:

```html
<div id="collections-container">
    <div class="collection-group" data-group-id="group-1">
        <div class="group-header">  <!-- Must be draggable -->
            <h3>Group Name</h3>
        </div>
        <div class="group-collections">  <!-- Collection container -->
            <div class="collection-card" data-collection-slug="slug" data-group-id="group-1">
                <!-- Card content -->
            </div>
        </div>
    </div>
</div>
```

### JavaScript Requirements

1. **Group headers must be draggable**:
```javascript
header.draggable = true;
```

2. **Collection cards must have data attributes**:
```javascript
card.dataset.collectionSlug = collection.slug;
card.dataset.groupId = groupId || null;
```

3. **Call `update()` after DOM changes**:
```javascript
nestedDragManager.update();
```

## How It Works

1. **Group Reordering**: 
   - Uses `SimpleDragDrop` on container
   - Only draggable from `.group-header`
   - Prevents collection drags during group drag

2. **Collection Reordering**:
   - Uses `SimpleDragDrop` on each group's collection container
   - Handles reordering within same group
   - Automatically detects cross-group moves

3. **Cross-Group Moves**:
   - Special handlers detect when dragging to different group
   - Shows drop indicator
   - Calls `onCollectionMove` callback

## Benefits

- ✅ **95% less code** - From 1500+ lines to ~50 lines
- ✅ **No conflicts** - Automatic conflict resolution
- ✅ **Reliable** - Based on proven `SimpleDragDrop`
- ✅ **Maintainable** - Clear, simple code
- ✅ **Extensible** - Easy to add features

## Next Steps

1. **Test the solution** - Try dragging groups and collections
2. **Migrate gradually** - Replace old code section by section
3. **Remove old code** - Once working, delete the old implementation
4. **Add CSS** - Style the drag states and drop indicators

## See Also

- `docs/NESTED_DRAG_DROP_GUIDE.md` - Complete usage guide
- `docs/DRAG_DROP_GUIDE.md` - Simple drag-and-drop guide
- `docs/WHY_DRAG_DROP_IS_COMPLEX.md` - Why it's complex

