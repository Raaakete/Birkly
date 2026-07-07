/**
 * DragDropSorter Usage Examples
 * 
 * This file shows how to use the DragDropSorter system in different scenarios.
 * These are examples only - not meant to be included in production.
 */

// ============================================
// Example 1: Simple List Reordering
// ============================================
function exampleSimpleList() {
    const listContainer = document.querySelector('.my-list');
    
    const sorter = new DragDropSorter({
        container: listContainer,
        itemSelector: '.list-item',
        onReorder: (fromIndex, toIndex, insertBefore, context) => {
            
            // Update your data array
            const item = myDataArray[fromIndex];
            myDataArray.splice(fromIndex, 1);
            myDataArray.splice(toIndex, 0, item);
            
            // Save to server or update UI
            saveOrder(myDataArray);
        }
    });
    
    sorter.init();
}

// ============================================
// Example 2: List with Drag Handle Only
// ============================================
function exampleWithHandle() {
    const sorter = new DragDropSorter({
        container: document.querySelector('.entries-list'),
        itemSelector: '.entry-row',
        handleSelector: '.drag-handle', // Only drag from handle
        onReorder: (fromIndex, toIndex, insertBefore) => {
            reorderEntries(fromIndex, toIndex, insertBefore);
        },
        constraints: {
            onlyFromHandle: true,
            excludeSelector: '.no-drag' // Can't drag items with this class
        }
    });
    
    sorter.init();
}

// ============================================
// Example 3: Nested Drag-and-Drop (Groups + Items)
// ============================================
function exampleNested() {
    const nestedSorter = new NestedDragDropSorter({
        container: document.getElementById('collections-container'),
        groupSelector: '.collection-group',
        itemSelector: '.collection-card',
        onGroupReorder: (fromIndex, toIndex, insertBefore, context) => {
            // Reorder groups
            const group = groups[fromIndex];
            groups.splice(fromIndex, 1);
            groups.splice(toIndex, 0, group);
            saveGroups();
        },
        onItemReorder: (groupIndex, fromIndex, toIndex, insertBefore, context) => {
            // Reorder items within a group
            const group = groups[groupIndex];
            const item = group.collections[fromIndex];
            group.collections.splice(fromIndex, 1);
            group.collections.splice(toIndex, 0, item);
            saveGroups();
        }
    });
    
    nestedSorter.init();
}

// ============================================
// Example 4: Custom Constraints
// ============================================
function exampleCustomConstraints() {
    const sorter = new DragDropSorter({
        container: document.querySelector('.sortable-list'),
        itemSelector: '.item',
        onReorder: (fromIndex, toIndex) => {
            // Handle reorder
        },
        constraints: {
            // Custom function to determine if item can be dragged
            canDrag: (item, element) => {
                // Example: Can't drag locked items
                if (item.dataset.locked === 'true') {
                    return false;
                }
                
                // Example: Can't drag from certain areas
                if (element.closest('.no-drag-area')) {
                    return false;
                }
                
                return true;
            },
            
            // Callback when drag starts
            onDragStart: (item, index, event) => {
            },
            
            // Callback when drag ends
            onDragEnd: (event) => {
            }
        }
    });
    
    sorter.init();
}

// ============================================
// Example 5: Horizontal List
// ============================================
function exampleHorizontal() {
    const sorter = new DragDropSorter({
        container: document.querySelector('.horizontal-list'),
        itemSelector: '.item',
        axis: 'horizontal', // Horizontal instead of vertical
        onReorder: (fromIndex, toIndex) => {
            // Handle horizontal reorder
        }
    });
    
    sorter.init();
}

// ============================================
// Example 6: Migration from Existing Code
// ============================================
function migrateEntriesList() {
    // OLD CODE (entries.js):
    // - Manual dragstart, dragover, drop handlers
    // - Manual drop indicator management
    // - Manual position calculation
    
    // NEW CODE:
    const sorter = new DragDropSorter({
        container: document.querySelector('.table-body'),
        itemSelector: '.table-row',
        handleSelector: '.drag-handle',
        onReorder: (fromIndex, toIndex, insertBefore) => {
            const items = Array.from(document.querySelectorAll('.table-row'));
            const draggedId = items[fromIndex].dataset.entryId;
            const targetId = items[toIndex].dataset.entryId;
            
            // Use existing reorder function
            reorderEntries(draggedId, targetId, insertBefore);
        },
        constraints: {
            onlyFromHandle: true
        }
    });
    
    sorter.init();
}

