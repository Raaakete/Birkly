# Drag and Drop Migration Example

This shows how to migrate from the old custom implementation to `SimpleDragDrop`.

## Before: Custom Implementation (entries.js)

**~100 lines of code** with manual event handling:

```javascript
// OLD WAY - Complex and error-prone
let draggedRow = null;
let draggedEntryId = null;

const tableBody = document.querySelector('.table-body');
if (tableBody) {
    tableBody.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.dataTransfer.dropEffect = 'move';
        
        if (!draggedRow || !draggedEntryId) return;
        
        const targetRow = e.target.closest('.table-row');
        if (targetRow && targetRow !== draggedRow) {
            showEntryDropIndicator(e, targetRow);
        } else {
            removeEntryDropIndicator();
        }
    });
    
    tableBody.addEventListener('dragleave', function(e) {
        if (!tableBody.contains(e.relatedTarget)) {
            removeEntryDropIndicator();
        }
    });
    
    tableBody.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        removeEntryDropIndicator();
        
        if (!draggedRow || !draggedEntryId) return;
        
        const dropIndicator = document.querySelector('.entry-drop-indicator');
        let targetEntryId = null;
        let insertBefore = false;
        
        if (dropIndicator && dropIndicator.dataset.targetEntryId) {
            targetEntryId = dropIndicator.dataset.targetEntryId;
            insertBefore = dropIndicator.dataset.insertBefore === 'true';
        } else {
            const targetRow = e.target.closest('.table-row');
            if (targetRow && targetRow !== draggedRow) {
                targetEntryId = targetRow.dataset.entryId;
                const dropY = e.clientY;
                const rect = targetRow.getBoundingClientRect();
                insertBefore = dropY < rect.top + rect.height / 2;
            }
        }
        
        if (targetEntryId && targetEntryId !== draggedEntryId) {
            reorderEntries(draggedEntryId, targetEntryId, insertBefore);
        }
    });
}

function showEntryDropIndicator(e, targetRow) {
    removeEntryDropIndicator();
    
    const dropY = e.clientY;
    const rect = targetRow.getBoundingClientRect();
    const insertBefore = dropY < rect.top + rect.height / 2;
    
    const indicator = document.createElement('div');
    indicator.className = 'entry-drop-indicator';
    indicator.dataset.targetEntryId = targetRow.dataset.entryId;
    indicator.dataset.insertBefore = insertBefore;
    
    const tableBody = document.querySelector('.table-body');
    const tableBodyRect = tableBody.getBoundingClientRect();
    
    if (insertBefore) {
        indicator.style.top = (rect.top - tableBodyRect.top - 1) + 'px';
    } else {
        indicator.style.top = (rect.bottom - tableBodyRect.top + 1) + 'px';
    }
    
    indicator.style.left = '0';
    indicator.style.width = '100%';
    indicator.style.height = '3px';
    indicator.style.position = 'absolute';
    
    tableBody.style.position = 'relative';
    tableBody.appendChild(indicator);
}

function removeEntryDropIndicator() {
    const indicator = document.querySelector('.entry-drop-indicator');
    if (indicator) {
        indicator.remove();
    }
}

async function reorderEntries(entryId, targetEntryId, insertBefore) {
    // ... complex index calculation ...
}
```

## After: Using SimpleDragDrop

**~15 lines of code** - much simpler:

```javascript
// NEW WAY - Simple and reliable
const tableBody = document.querySelector('.table-body');
if (tableBody) {
    const entrySorter = new SimpleDragDrop({
        container: tableBody,
        itemSelector: '.table-row',
        handleSelector: '.drag-handle', // Optional - if you have a drag handle
        onReorder: async (fromIndex, toIndex) => {
            const rows = Array.from(tableBody.querySelectorAll('.table-row'));
            const entryId = rows[fromIndex].dataset.entryId;
            const targetEntryId = rows[toIndex].dataset.entryId;
            
            // Call your existing reorder function
            await reorderEntries(entryId, targetEntryId);
        }
    });
    entrySorter.init();
    
    // Store reference for updates
    window.entrySorter = entrySorter;
}

// When entries are reloaded via AJAX:
if (window.entrySorter) {
    window.entrySorter.update();
}
```

## Benefits

1. **90% less code** - from ~100 lines to ~15 lines
2. **No manual event handling** - all handled by SimpleDragDrop
3. **No manual drop indicator** - handled automatically
4. **No manual index calculation** - handled automatically
5. **Consistent behavior** - same system everywhere
6. **Easier to debug** - clear, simple code

## Step-by-Step Migration

### Step 1: Include the script

Add to your HTML template (e.g., `admin/index.php`):

```html
<script src="/admin/js/simple-drag-drop.js"></script>
```

### Step 2: Replace the old code

Find your old drag-and-drop code and replace it with:

```javascript
const sorter = new SimpleDragDrop({
    container: yourContainer,
    itemSelector: '.your-item-selector',
    onReorder: (fromIndex, toIndex) => {
        // Your reorder logic
    }
});
sorter.init();
```

### Step 3: Update on DOM changes

If you load items dynamically, call `update()`:

```javascript
// After AJAX load
sorter.update();
```

### Step 4: Remove old code

Delete:
- Manual event listeners (`addEventListener('dragstart')`, etc.)
- Drop indicator functions
- Manual index calculation code
- Global state variables (`draggedRow`, `draggedEntryId`, etc.)

## Complete Migration Example

Here's how `entries.js` would look after migration:

```javascript
// ============================================
// Drag and Drop Reordering for Entries
// ============================================

let entrySorter = null;

function setupEntryDragDrop() {
    const tableBody = document.querySelector('.table-body');
    if (!tableBody) return;
    
    // Destroy existing sorter if any
    if (entrySorter) {
        entrySorter.destroy();
    }
    
    // Create new sorter
    entrySorter = new SimpleDragDrop({
        container: tableBody,
        itemSelector: '.table-row',
        onReorder: async (fromIndex, toIndex) => {
            const rows = Array.from(tableBody.querySelectorAll('.table-row'));
            const entryId = rows[fromIndex].dataset.entryId;
            const targetEntryId = rows[toIndex].dataset.entryId;
            
            if (entryId && targetEntryId && entryId !== targetEntryId) {
                await reorderEntries(entryId, targetEntryId);
            }
        }
    });
    entrySorter.init();
}

// Call on page load
setupEntryDragDrop();

// Call after entries are reloaded
function reloadEntries() {
    // ... your reload logic ...
    
    // Update the sorter
    if (entrySorter) {
        entrySorter.update();
    }
}

// Keep your existing reorderEntries function - it still works!
async function reorderEntries(entryId, targetEntryId) {
    // Your existing API call logic
    // ...
}
```

## Testing Checklist

After migration, test:

- [ ] Items can be dragged
- [ ] Drop indicator shows correctly
- [ ] Items reorder correctly
- [ ] Works after AJAX reload
- [ ] No console errors
- [ ] Works with multiple items
- [ ] Works with empty lists
- [ ] Works with single item

## Common Pitfalls

1. **Forgetting to call `update()`** - If items are added/removed, call `update()`
2. **Wrong selector** - Make sure `itemSelector` matches your items
3. **Container not found** - Check that container exists before creating sorter
4. **Multiple sorters** - Don't create multiple sorters for the same container

