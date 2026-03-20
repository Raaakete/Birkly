# Drag and Drop Guide

## Why Drag and Drop is Complex in Our System

The tutorial you found (https://www.javascripttutorial.net/web-apis/javascript-drag-and-drop/) makes drag and drop look simple because it demonstrates **one draggable item** and **one drop target**. Our system is more complex because:

### 1. **Multiple Drag Zones on Same Page**
- Entry lists
- Sub-entry lists  
- Content groups
- Field reordering
- All can be active simultaneously

### 2. **Dynamic Content**
- Items are added/removed via AJAX
- DOM structure changes during drag operations
- Need to handle items that disappear during drag

### 3. **Mixed Implementations**
- We had a reusable `DragDropSorter` class
- But also custom implementations in `entries.js` and `entry-detail.html`
- These compete for the same events, causing conflicts

### 4. **Nested Scenarios** (Previous Attempt)
- Tried to handle nested drag-and-drop (groups containing items)
- Added complex global state management
- Created event conflicts and race conditions

## The Solution: SimpleDragDrop

We've created a **simpler, more robust** drag-and-drop system that:
- ✅ Works reliably across the system
- ✅ Handles one drag at a time (no nested complexity)
- ✅ Uses event delegation (works with dynamic content)
- ✅ Easy to use and understand
- ✅ Based on the tutorial you found, but adapted for our needs

## Basic Usage

```javascript
// 1. Include the script
<script src="/admin/js/simple-drag-drop.js"></script>

// 2. Initialize
const sorter = new SimpleDragDrop({
    container: document.querySelector('.my-list'),
    itemSelector: '.list-item',
    onReorder: (fromIndex, toIndex) => {
        // Handle the reorder
        console.log(`Moved from ${fromIndex} to ${toIndex}`);
        // Update your data, save to server, etc.
    }
});
sorter.init();
```

## Examples

### Example 1: Simple List (Whole Item Draggable)

```html
<ul class="my-list">
    <li class="list-item">Item 1</li>
    <li class="list-item">Item 2</li>
    <li class="list-item">Item 3</li>
</ul>

<script>
const sorter = new SimpleDragDrop({
    container: document.querySelector('.my-list'),
    itemSelector: '.list-item',
    onReorder: (fromIndex, toIndex) => {
        // Reorder your data array
        const item = myArray[fromIndex];
        myArray.splice(fromIndex, 1);
        myArray.splice(toIndex, 0, item);
        
        // Save to server
        saveOrder(myArray);
    }
});
sorter.init();
</script>
```

### Example 2: With Drag Handle

```html
<ul class="my-list">
    <li class="list-item">
        <span class="drag-handle">☰</span>
        <span>Item 1</span>
    </li>
    <li class="list-item">
        <span class="drag-handle">☰</span>
        <span>Item 2</span>
    </li>
</ul>

<script>
const sorter = new SimpleDragDrop({
    container: document.querySelector('.my-list'),
    itemSelector: '.list-item',
    handleSelector: '.drag-handle', // Only draggable from handle
    onReorder: (fromIndex, toIndex) => {
        // Handle reorder
    }
});
sorter.init();
</script>
```

### Example 3: Entry List Reordering

```javascript
// In entries.js or similar
function setupEntryDragDrop(tableBody) {
    const sorter = new SimpleDragDrop({
        container: tableBody,
        itemSelector: '.table-row',
        handleSelector: '.drag-handle', // Optional
        onReorder: async (fromIndex, toIndex) => {
            const rows = Array.from(tableBody.querySelectorAll('.table-row'));
            const entryId = rows[fromIndex].dataset.entryId;
            const targetEntryId = rows[toIndex].dataset.entryId;
            
            // Call your API
            await reorderEntries(entryId, targetEntryId);
        }
    });
    sorter.init();
    
    // When new entries are loaded, update the sorter
    return sorter;
}

// After AJAX load:
sorter.update(); // Call this when DOM changes
```

## When DOM Changes

If you add/remove items dynamically (via AJAX, etc.), call `update()`:

```javascript
// After adding new items
sorter.update();
```

## Migration Guide

### Before (Custom Implementation)

```javascript
// Old way - custom event handlers
tableBody.addEventListener('dragstart', function(e) {
    // Complex logic...
});

tableBody.addEventListener('dragover', function(e) {
    // Complex logic...
});

tableBody.addEventListener('drop', function(e) {
    // Complex logic...
});
```

### After (SimpleDragDrop)

```javascript
// New way - simple and clean
const sorter = new SimpleDragDrop({
    container: tableBody,
    itemSelector: '.table-row',
    onReorder: (fromIndex, toIndex) => {
        // Your reorder logic
    }
});
sorter.init();
```

## Key Differences from Tutorial

1. **Event Delegation**: We listen on the container, not individual items
2. **Dynamic Content**: Works with items added/removed dynamically
3. **Drop Indicator**: Visual feedback showing where item will drop
4. **Index Calculation**: Handles the math for before/after positioning
5. **Cleanup**: Proper event listener removal

## Why This is Better

| Old System | New System |
|-----------|------------|
| Complex nested drag handling | One drag at a time (simpler) |
| Global state management | Local state per instance |
| MutationObserver overhead | Manual `update()` when needed |
| Event conflicts | Clear event handling |
| Hard to debug | Easy to understand |

## Common Issues and Solutions

### Issue: Items not draggable after AJAX load
**Solution**: Call `sorter.update()` after loading new items

### Issue: Drop indicator not showing
**Solution**: Make sure container has `position: relative` (handled automatically)

### Issue: Drag starts but nothing happens
**Solution**: Check that `onReorder` callback is working and not throwing errors

### Issue: Multiple sorters on same page
**Solution**: Each sorter is independent - no conflicts! Just create separate instances.

## Best Practices

1. **One sorter per container**: Don't create multiple sorters for the same container
2. **Call update() after DOM changes**: When items are added/removed
3. **Handle errors in onReorder**: Wrap your reorder logic in try/catch
4. **Destroy when done**: If you remove a container, call `sorter.destroy()`

## CSS Styling

Add these styles for better UX:

```css
.dragging {
    opacity: 0.5;
    cursor: grabbing !important;
}

.drop-indicator {
    background: var(--primary-500, #3b82f6);
    height: 3px;
}

.drag-handle {
    cursor: grab;
}

.drag-handle:active {
    cursor: grabbing;
}
```

