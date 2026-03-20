# Nested Drag and Drop Guide

## Problem

You need to handle:
1. **Groups can be reordered** (drag group header to move groups)
2. **Collections can be reordered within their group** (drag collection card within same group)
3. **Collections can be moved between groups** (drag collection card to different group)

This is a **nested drag-and-drop** scenario that requires special handling.

## Solution: NestedDragDropManager

The `NestedDragDropManager` uses `SimpleDragDrop` internally but handles the complexity of:
- Detecting whether you're dragging a group or collection
- Preventing conflicts between group and collection drags
- Handling cross-group moves
- Managing multiple sorters (one per group)

## Usage

### Basic Setup

```javascript
// Include both scripts
<script src="/admin/js/simple-drag-drop.js"></script>
<script src="/admin/js/nested-drag-drop.js"></script>

<script>
const manager = new NestedDragDropManager({
    container: document.getElementById('collections-container'),
    groupSelector: '.collection-group',
    groupHeaderSelector: '.group-header',
    collectionSelector: '.collection-card',
    collectionContainerSelector: '.group-collections, .ungrouped-collections',
    
    // Called when groups are reordered
    onGroupReorder: (fromIndex, toIndex) => {
        console.log(`Group moved from ${fromIndex} to ${toIndex}`);
        // Update your groups array and save
        reorderGroups(fromIndex, toIndex);
    },
    
    // Called when collections are reordered within the same group
    onCollectionReorder: (collectionSlug, fromGroupId, toGroupId, toIndex) => {
        console.log(`Collection ${collectionSlug} reordered in group ${toGroupId} to index ${toIndex}`);
        // Update collection order in group
        reorderCollectionInGroup(collectionSlug, toGroupId, toIndex);
    },
    
    // Called when collections are moved between groups
    onCollectionMove: (collectionSlug, fromGroupId, toGroupId, toIndex) => {
        console.log(`Collection ${collectionSlug} moved from group ${fromGroupId} to ${toGroupId} at index ${toIndex}`);
        // Move collection to new group
        moveCollectionToGroup(collectionSlug, fromGroupId, toGroupId, toIndex);
    }
});

manager.init();
</script>
```

## Migration Example: content-groups.js

### Before (Complex - 1500+ lines)

```javascript
// OLD WAY - Manual event handling, lots of conflicts
let draggedElement = null;
let draggedCollection = null;
let draggedGroupId = null;
let isDraggingGroup = false;

// Manual dragstart handlers
card.addEventListener('dragstart', function(e) {
    draggedElement = this;
    draggedCollection = collection;
    // ... 50+ lines of code
});

// Manual dragover handlers with conflict resolution
container.addEventListener('dragover', function(e) {
    // Check if dragging group or collection
    if (isDraggingGroup) {
        // ... group handling
    } else {
        // ... collection handling
    }
    // ... 100+ lines of conflict resolution
});

// Manual drop handlers
container.addEventListener('drop', function(e) {
    // ... 200+ lines of drop handling
});
```

### After (Simple - ~50 lines)

```javascript
// NEW WAY - Clean and simple
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
            // Reorder groups array
            const reorderableGroups = groups.filter(g => g.id !== 'ungrouped');
            const draggedGroup = reorderableGroups[fromIndex];
            reorderableGroups.splice(fromIndex, 1);
            reorderableGroups.splice(toIndex, 0, draggedGroup);
            
            // Keep ungrouped at end
            const ungroupedGroup = groups.find(g => g.id === 'ungrouped');
            groups = [...reorderableGroups];
            if (ungroupedGroup) {
                groups.push(ungroupedGroup);
            }
            
            saveGroups();
        },
        
        onCollectionReorder: (collectionSlug, fromGroupId, toGroupId, toIndex) => {
            // Reorder within same group
            const group = groups.find(g => g.id === toGroupId);
            if (!group || !group.collections) return;
            
            const currentIndex = group.collections.indexOf(collectionSlug);
            if (currentIndex === -1) return;
            
            // Remove and reinsert
            group.collections.splice(currentIndex, 1);
            group.collections.splice(toIndex, 0, collectionSlug);
            
            saveGroups();
        },
        
        onCollectionMove: (collectionSlug, fromGroupId, toGroupId, toIndex) => {
            // Remove from old group
            if (fromGroupId) {
                const fromGroup = groups.find(g => g.id === fromGroupId);
                if (fromGroup && fromGroup.collections) {
                    fromGroup.collections = fromGroup.collections.filter(slug => slug !== collectionSlug);
                }
            }
            
            // Add to new group
            if (toGroupId) {
                const toGroup = groups.find(g => g.id === toGroupId);
                if (toGroup) {
                    if (!toGroup.collections) {
                        toGroup.collections = [];
                    }
                    toGroup.collections.splice(toIndex, 0, collectionSlug);
                }
            } else {
                // Moving to ungrouped
                let ungroupedGroup = groups.find(g => g.id === 'ungrouped');
                if (!ungroupedGroup) {
                    ungroupedGroup = {
                        id: 'ungrouped',
                        name: 'Ungrouped Collections',
                        collections: []
                    };
                    groups.push(ungroupedGroup);
                }
                if (!ungroupedGroup.collections) {
                    ungroupedGroup.collections = [];
                }
                ungroupedGroup.collections.splice(toIndex, 0, collectionSlug);
            }
            
            saveGroups();
        }
    });
    
    nestedDragManager.init();
}

// When groups/collections are added/removed, update the manager
function renderGroupsAndCollections() {
    // ... render logic ...
    
    // Update drag and drop after render
    if (nestedDragManager) {
        nestedDragManager.update();
    }
}
```

## Key Benefits

1. **95% less code** - From 1500+ lines to ~50 lines
2. **No manual conflict resolution** - Handled automatically
3. **No global state management** - Each manager is self-contained
4. **Clear callbacks** - Easy to understand what happens when
5. **Easy to debug** - Simple, clear code

## How It Works

1. **Group Reordering**: Uses `SimpleDragDrop` on the container with `groupHeaderSelector` as handle
2. **Collection Reordering**: Uses `SimpleDragDrop` on each group's collection container
3. **Cross-Group Moves**: Special handlers detect when dragging from one group to another

## Important Notes

### 1. Group Headers Must Be Draggable

Make sure group headers have `draggable="true"`:

```javascript
const header = document.createElement('div');
header.className = 'group-header';
header.draggable = true; // Important!
```

### 2. Collection Cards Must Have Data Attributes

```javascript
const card = document.createElement('div');
card.className = 'collection-card';
card.dataset.collectionSlug = collection.slug; // Required!
card.dataset.groupId = groupId || null; // Required!
```

### 3. Call `update()` After DOM Changes

```javascript
// After rendering or AJAX updates
nestedDragManager.update();
```

### 4. Exclude Ungrouped from Group Reordering

If you don't want "ungrouped" to be draggable:

```javascript
// In createUngroupedSection, don't make header draggable
const header = document.createElement('div');
header.className = 'group-header';
// Don't set draggable for ungrouped
```

Or filter it out in `onGroupReorder`:

```javascript
onGroupReorder: (fromIndex, toIndex) => {
    const reorderableGroups = groups.filter(g => g.id !== 'ungrouped');
    // ... reorder logic
}
```

## CSS Styling

Add these styles for better UX:

```css
/* Group dragging */
.collection-group.dragging {
    opacity: 0.5;
}

/* Collection dragging */
.collection-card.dragging {
    opacity: 0.5;
    cursor: grabbing;
}

/* Drop zone feedback */
.group-collections.drag-over,
.ungrouped-collections.drag-over {
    background: var(--bg-hover, rgba(59, 130, 246, 0.1));
    border: 2px dashed var(--primary-500, #3b82f6);
}

/* Drop indicators */
.cross-group-drop-indicator {
    background: var(--primary-500, #3b82f6);
    height: 3px;
    border-radius: 2px;
}
```

## Testing Checklist

- [ ] Groups can be reordered by dragging header
- [ ] Collections can be reordered within same group
- [ ] Collections can be moved to different group
- [ ] Collections can be moved to ungrouped
- [ ] Collections can be moved from ungrouped to group
- [ ] Works after AJAX reload
- [ ] No console errors
- [ ] Drop indicators show correctly
- [ ] No conflicts between group and collection drags

## Troubleshooting

### Issue: Collections not draggable
**Solution**: Make sure cards have `draggable="true"` and correct data attributes

### Issue: Groups not draggable
**Solution**: Make sure headers have `draggable="true"` and match `groupHeaderSelector`

### Issue: Can't move collections between groups
**Solution**: Check that `collectionContainerSelector` matches your container class

### Issue: Drop indicator not showing
**Solution**: Make sure container has `position: relative` (handled automatically)

### Issue: Conflicts between group and collection drags
**Solution**: `NestedDragDropManager` handles this automatically - make sure you're using it correctly

