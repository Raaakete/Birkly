/**
 * Simple Mouse-Based Drag and Drop
 * No dependencies - pure vanilla JavaScript
 * Uses mousedown/mousemove/mouseup for better control
 * 
 * Basic Usage:
 *   const sorter = new MouseDragDrop({
 *     container: document.getElementById('my-list'),
 *     itemSelector: '.list-item',
 *     handleSelector: '.drag-handle', // Optional: only allow drag from handle
 *     onReorder: (fromIndex, toIndex) => {
 *       // Handle reorder
 *     }
 *   });
 * 
 * For nested drag and drop (groups + items):
 *   const manager = new MouseDragDropManager({
 *     container: document.getElementById('container'),
 *     groupSelector: '.group',
 *     groupHeaderSelector: '.group-header',
 *     collectionSelector: '.item',
 *     collectionContainerSelector: '.group-items',
 *     onGroupReorder: (fromIndex, toIndex) => { ... },
 *     onCollectionReorder: (groupId, fromIndex, toIndex) => { ... },
 *     onCollectionMove: (itemId, fromGroupId, toGroupId, toIndex) => { ... }
 *   });
 */

(function() {
    'use strict';

    class MouseDragDrop {
        constructor(options) {
            this.container = options.container;
            this.itemSelector = options.itemSelector;
            this.handleSelector = options.handleSelector || null;
            this.excludeSelector = options.excludeSelector || null;
            this.useCapture = options.useCapture === true;
            this.onReorder = options.onReorder || function() {};
            this.onCrossContainerMove = options.onCrossContainerMove || null;
            this.allContainers = options.allContainers || null; // For cross-container moves
            
            this.dragging = false;
            this.draggedItem = null;
            this.draggedIndex = null;
            this.placeholder = null;
            this.currentTarget = null;
            this.currentTargetContainer = null;
            this.mouseOffset = { x: 0, y: 0 };
            this.isProcessingDrop = false;
            this.lastPlaceholderUpdate = 0;
            this.placeholderUpdateThrottle = 16; // ~60fps
            this.lastTargetItem = null; // Track last target to prevent unnecessary updates
            
            this.init();
        }

        init() {
            if (!this.container) return;
            
            // Bind handlers once and store them
            this.boundMouseDown = this.handleMouseDown.bind(this);
            this.boundMouseMove = this.handleMouseMove.bind(this);
            this.boundMouseUp = this.handleMouseUp.bind(this);
            
            // Use event delegation on container (capture for collection cards)
            this.container.addEventListener('mousedown', this.boundMouseDown, this.useCapture);
        }

        handleMouseDown(e) {
            // Prevent multiple drags
            if (this.dragging) return;

            // Group sorter must not intercept collection card interactions
            if (this.itemSelector.includes('collection-group') && e.target.closest('.collection-card')) {
                return;
            }
            
            // Check if this is a handle or the item itself
            let item = e.target.closest(this.itemSelector);
            if (!item || !this.container.contains(item)) return;
            
            // Ignore menu controls on collection cards
            if (e.target.closest('.collection-menu-wrapper, .collection-menu-btn, .dropdown-menu, .dropdown-trigger')) {
                return;
            }

            const exclude = this.excludeSelector || (
                this.itemSelector.includes('collection-card')
                    ? 'a, button, input, select, textarea, .dropdown-trigger'
                    : null
            );
            if (exclude && e.target.closest(exclude)) {
                return;
            }

            // If handleSelector is specified, only allow drag from handle
            if (this.handleSelector) {
                const handle = e.target.closest(this.handleSelector);
                // Check if click is on the handle or inside the handle element
                if (!handle || !item.contains(handle)) {
                    return; // Not clicking on the drag handle
                }
                // Check if we're clicking on an interactive element - if so, don't start drag
                // This allows buttons, links, etc. to work normally
                const interactiveElements = ['button', 'a', 'input', 'select', 'textarea'];
                const clickedElement = e.target;
                if (interactiveElements.includes(clickedElement.tagName.toLowerCase())) {
                    // If clicking directly on an interactive element, don't start drag
                    return;
                }
                // Also check if the clicked element is inside an interactive element
                if (clickedElement.closest('button, a, input, select, textarea')) {
                    return; // Clicking inside an interactive element
                }
            }
            
            // Prevent text selection
            e.preventDefault();
            e.stopPropagation(); // Prevent event from bubbling to other handlers
            
            this.dragging = true;
            this.draggedItem = item;
            
            // Store original parent and next sibling for restoration
            this.originalParent = item.parentNode;
            this.originalNextSibling = item.nextSibling;
            
            // Calculate initial index, excluding ungrouped group for groups
            const allItems = this.getItems();
            if (this.itemSelector.includes('.collection-group')) {
                // For groups, count only non-ungrouped groups
                let index = 0;
                for (let i = 0; i < allItems.length; i++) {
                    if (allItems[i] === item) break;
                    const groupId = allItems[i].dataset?.groupId;
                    if (groupId && groupId !== 'ungrouped') {
                        index++;
                    }
                }
                this.draggedIndex = index;
            } else {
                this.draggedIndex = allItems.indexOf(item);
            }
            
            // Calculate mouse offset from item
            const rect = item.getBoundingClientRect();
            this.mouseOffset.x = e.clientX - rect.left;
            this.mouseOffset.y = e.clientY - rect.top;
            
            // Remove any existing placeholder first
            if (this.placeholder && this.placeholder.parentNode) {
                this.placeholder.remove();
                this.placeholder = null;
            }
            
            // Create placeholder
            this.createPlaceholder(item);
            
            // Add dragging class with fast transition
            item.style.transition = 'opacity 0.1s ease-out, transform 0.1s ease-out, box-shadow 0.1s ease-out';
            item.classList.add('dragging');
            
            // Trigger reflow to ensure transition applies
            void item.offsetHeight;
            
            // Set cursor
            document.body.style.cursor = 'grabbing';
            
            // Add global listeners (using pre-bound handlers)
            document.addEventListener('mousemove', this.boundMouseMove);
            document.addEventListener('mouseup', this.boundMouseUp);
        }

        handleMouseMove(e) {
            if (!this.dragging || !this.draggedItem) return;
            
            e.preventDefault();
            
            // Update dragged item position with smooth movement
            if (!this.draggedItem.style.position || this.draggedItem.style.position !== 'fixed') {
                this.draggedItem.style.position = 'fixed';
                this.draggedItem.style.pointerEvents = 'none';
                this.draggedItem.style.zIndex = '10000';
                this.draggedItem.style.transition = 'none'; // Disable transition during drag for immediate response
            }
            
            // Update position directly for immediate response (requestAnimationFrame would add lag)
            this.draggedItem.style.left = (e.clientX - this.mouseOffset.x) + 'px';
            this.draggedItem.style.top = (e.clientY - this.mouseOffset.y) + 'px';
            
            // Throttle placeholder updates for smoother performance (but not item movement)
            const now = performance.now();
            if (now - this.lastPlaceholderUpdate >= this.placeholderUpdateThrottle) {
                this.lastPlaceholderUpdate = now;
                
                // Find target item (check all containers if cross-container is enabled)
                const result = this.findTargetItem(e.clientX, e.clientY);
                
                if (result && result.item !== this.draggedItem && result.item !== this.placeholder) {
                    // Check if we're hovering above the first item (for top insertion)
                    if (!this.onCrossContainerMove && this.itemSelector.includes('.collection-group')) {
                        // For groups, check if hovering above first reorderable item
                        const items = this.getItems().filter(item => {
                            if (item === this.draggedItem || item === this.placeholder) {
                                return false;
                            }
                            const groupId = item.dataset?.groupId;
                            return groupId && groupId !== 'ungrouped';
                        });
                        const firstItem = items[0];
                        
                        if (firstItem === result.item) {
                            const rect = result.item.getBoundingClientRect();
                            // If mouse is above the top edge of first item, insert at top
                            if (e.clientY < rect.top) {
                                this.insertPlaceholderAtTop();
                                this.currentTargetContainer = this.container;
                                this.lastTargetItem = null;
                                return; // Skip normal insertion
                            }
                        }
                    }
                    
                    // Only update if we're over a different item or container
                    const targetChanged = this.lastTargetItem !== result.item || 
                                         this.currentTargetContainer !== result.container;
                    
                    if (targetChanged) {
                        this.currentTarget = result.item;
                        this.currentTargetContainer = result.container;
                        this.lastTargetItem = result.item;
                        
                        // For grid layouts (cards), use both X and Y for positioning
                        // For list layouts (groups), use only Y
                        const isGrid = result.container && 
                                      (result.container.classList.contains('group-collections') || 
                                       result.container.classList.contains('ungrouped-collections'));
                        
                        if (isGrid) {
                            // Grid layout: check X position for column, Y for row
                            this.insertPlaceholderGrid(result.item, e.clientX, e.clientY, result.container);
                        } else {
                            // List layout: use Y position only
                            this.insertPlaceholder(result.item, e.clientY, result.container);
                        }
                    }
                } else if (!result) {
                    // No target item found - check if we're above the container (for top insertion)
                    if (!this.onCrossContainerMove) {
                        // For groups (list layout), check if mouse is above the container or first item
                        const containerRect = this.container.getBoundingClientRect();
                        const items = this.getItems().filter(item => item !== this.draggedItem && item !== this.placeholder);
                        const firstItem = items[0];
                        
                        let shouldInsertAtTop = false;
                        
                        if (firstItem) {
                            const firstItemRect = firstItem.getBoundingClientRect();
                            // Check if mouse is above the first item's top edge
                            if (e.clientY < firstItemRect.top && e.clientX >= firstItemRect.left && e.clientX <= firstItemRect.right) {
                                shouldInsertAtTop = true;
                            }
                        }
                        
                        // Also check if mouse is above the container itself
                        if (!shouldInsertAtTop && e.clientY < containerRect.top && e.clientX >= containerRect.left && e.clientX <= containerRect.right) {
                            shouldInsertAtTop = true;
                        }
                        
                        if (shouldInsertAtTop) {
                            // Mouse is above the container or first item - insert placeholder at the top
                            this.insertPlaceholderAtTop();
                            this.currentTargetContainer = this.container;
                            this.lastTargetItem = null; // Clear last target since we're at top
                        } else {
                            // Not over container - clear target
                            this.currentTarget = null;
                            this.currentTargetContainer = null;
                            this.lastTargetItem = null;
                        }
                    } else {
                        // For collections, clear target if not found
                        this.currentTarget = null;
                        this.currentTargetContainer = null;
                        this.lastTargetItem = null;
                    }
                }
            }
        }

        handleMouseUp(e) {
            if (!this.dragging || this.isProcessingDrop) return;
            
            this.isProcessingDrop = true;
            this.dragging = false;
            
            // Remove global listeners immediately to prevent duplicate calls
            document.removeEventListener('mousemove', this.boundMouseMove);
            document.removeEventListener('mouseup', this.boundMouseUp);
            
            // Reset cursor
            document.body.style.cursor = '';
            
            // Find where placeholder actually is
            if (!this.placeholder || !this.placeholder.parentNode) {
                // No valid drop target - just cleanup and return
                this.cleanup();
                this.isProcessingDrop = false;
                return;
            }
            
            // For groups (no onCrossContainerMove), placeholder is directly in this.container
            // For collections (has onCrossContainerMove), we need to find the actual collection container
            let placeholderContainer = this.placeholder.parentNode;
            
            // Only search for collection containers if we're dealing with collections
            if (this.onCrossContainerMove) {
                // Walk up to find the actual collection container
                while (placeholderContainer && 
                       placeholderContainer !== this.container &&
                       placeholderContainer.parentNode &&
                       placeholderContainer.classList) {
                    if (placeholderContainer.classList.contains('group-collections') || 
                        placeholderContainer.classList.contains('ungrouped-collections')) {
                        break; // Found it!
                    }
                    placeholderContainer = placeholderContainer.parentNode;
                }
            } else {
                // For groups, placeholder is directly in this.container
                placeholderContainer = this.container;
            }
            
            // Check if this is a cross-container move (only for collections)
            const isCrossContainer = this.onCrossContainerMove && 
                                     placeholderContainer && 
                                     placeholderContainer !== this.container;
            
            // Debug logging (can be removed later)
            // console.log('Drop detected:', {
            //     placeholderContainer: placeholderContainer?.className,
            //     thisContainer: this.container?.className,
            //     isCrossContainer,
            //     hasCrossContainerMove: !!this.onCrossContainerMove
            // });
            
            if (isCrossContainer) {
                // Cross-container move
                const targetItems = Array.from(placeholderContainer.querySelectorAll(this.itemSelector));
                // Exclude placeholder from count - get index by counting items before placeholder
                let placeholderIndex = 0;
                let node = placeholderContainer.firstChild;
                while (node) {
                    if (node === this.placeholder) break;
                    if (node.nodeType === 1 && node.matches && node.matches(this.itemSelector)) {
                        placeholderIndex++;
                    }
                    node = node.nextSibling;
                }
                
                // console.log('Cross-container move:', placeholderIndex);
                this.onCrossContainerMove(
                    this.draggedItem,
                    this.container,
                    placeholderContainer,
                    placeholderIndex
                );
            } else {
                // Same container - reorder
                // Calculate index by counting reorderable items before placeholder
                // For groups, exclude ungrouped group from count
                let placeholderIndex = 0;
                let node = this.container.firstChild;
                
                while (node) {
                    if (node === this.placeholder) {
                        // Found placeholder - index is the count of reorderable items before it
                        break;
                    }
                    if (node.nodeType === 1 && node.matches && node.matches(this.itemSelector) && node !== this.draggedItem) {
                        // For groups, exclude ungrouped group
                        if (this.itemSelector.includes('.collection-group')) {
                            const groupId = node.dataset?.groupId;
                            if (groupId && groupId !== 'ungrouped') {
                                placeholderIndex++;
                            }
                        } else {
                            placeholderIndex++;
                        }
                    }
                    node = node.nextSibling;
                }
                
                // Double-check: verify placeholder is actually before the first reorderable item
                if (placeholderIndex === 0 && this.placeholder.parentNode === this.container) {
                    // Find first reorderable item after placeholder
                    let checkNode = this.placeholder.nextSibling;
                    while (checkNode) {
                        if (checkNode.nodeType === 1 && checkNode.matches && checkNode.matches(this.itemSelector) && checkNode !== this.draggedItem) {
                            // For groups, exclude ungrouped group
                            if (this.itemSelector.includes('.collection-group')) {
                                const groupId = checkNode.dataset?.groupId;
                                if (groupId && groupId !== 'ungrouped') {
                                    // Found a reorderable item after placeholder - index should be 0 (before this item)
                                    break;
                                }
                            } else {
                                // Found an item after placeholder - index should be 0 (before this item)
                                break;
                            }
                        }
                        checkNode = checkNode.nextSibling;
                    }
                    // If we found a reorderable item after placeholder, index is correctly 0
                }
                
                if (placeholderIndex !== this.draggedIndex) {
                    // Call reorder callback with placeholder and draggedItem for convenience
                    if (this.onReorder) {
                        this.onReorder(this.draggedIndex, placeholderIndex, this.placeholder, this.draggedItem);
                    }
                }
            }
            
            // Always cleanup, even if no reorder happened
            this.cleanup();
            this.isProcessingDrop = false;
        }

        findTargetItem(x, y) {
            // If cross-container is enabled, check all containers
            const containersToCheck = this.allContainers || [this.container];
            
            for (const container of containersToCheck) {
                const items = Array.from(container.querySelectorAll(this.itemSelector));
                
                // Find item under mouse (excluding dragged and placeholder)
                for (const item of items) {
                    if (item === this.draggedItem || item === this.placeholder) continue;
                    
                    const rect = item.getBoundingClientRect();
                    if (x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom) {
                        return { item, container };
                    }
                }
            }
            
            // If not found, find closest item across all containers
            let closestItem = null;
            let closestContainer = null;
            let minDist = Infinity;
            
            for (const container of containersToCheck) {
                const items = Array.from(container.querySelectorAll(this.itemSelector));
                
                for (const item of items) {
                    if (item === this.draggedItem || item === this.placeholder) continue;
                    
                    const rect = item.getBoundingClientRect();
                    const centerX = rect.left + rect.width / 2;
                    const centerY = rect.top + rect.height / 2;
                    const dist = Math.sqrt(Math.pow(x - centerX, 2) + Math.pow(y - centerY, 2));
                    
                    if (dist < minDist) {
                        minDist = dist;
                        closestItem = item;
                        closestContainer = container;
                    }
                }
            }
            
            return closestItem ? { item: closestItem, container: closestContainer || this.container } : null;
        }

        insertPlaceholder(targetItem, mouseY, targetContainer) {
            if (!this.placeholder || !targetItem) return;
            
            const container = targetContainer || this.container;
            
            // Make sure targetItem is still in the DOM
            if (!targetItem.parentNode) return;
            
            const rect = targetItem.getBoundingClientRect();
            const centerY = rect.top + rect.height / 2;
            const insertBefore = mouseY < centerY;
            
            // Special case: if inserting before the first item, make sure we're actually at the top
            const targetParent = targetItem.parentNode;
            let items = Array.from(targetParent.children).filter(child => 
                child.nodeType === 1 && 
                child.matches && 
                child.matches(this.itemSelector) && 
                child !== this.draggedItem && 
                child !== this.placeholder
            );
            
            // For groups, exclude ungrouped group from first item detection
            if (this.itemSelector.includes('.collection-group')) {
                items = items.filter(item => {
                    const groupId = item.dataset?.groupId;
                    return groupId && groupId !== 'ungrouped';
                });
            }
            
            const isFirstItem = items.length > 0 && items[0] === targetItem;
            
            // If hovering over top half of first item, always insert before it
            if (isFirstItem && insertBefore) {
                // Check if placeholder is already at the top
                const currentParent = this.placeholder.parentNode;
                if (currentParent !== targetParent || this.placeholder.nextSibling !== targetItem) {
                    if (currentParent) {
                        this.placeholder.remove();
                    }
                    try {
                        targetParent.insertBefore(this.placeholder, targetItem);
                        void this.placeholder.offsetHeight;
                    } catch (e) {
                        console.warn('Failed to insert placeholder at top:', e);
                    }
                }
                return;
            }
            
            // Check if we need to move the placeholder
            const currentParent = this.placeholder.parentNode;
            const shouldMove = currentParent !== targetParent || 
                              (insertBefore && this.placeholder.nextSibling !== targetItem) ||
                              (!insertBefore && this.placeholder.previousSibling !== targetItem);
            
            if (shouldMove) {
                // Remove placeholder from current position if it exists
                if (currentParent) {
                    this.placeholder.remove();
                }
                
                // Insert placeholder in the correct position with smooth transition
                try {
                    if (insertBefore) {
                        targetParent.insertBefore(this.placeholder, targetItem);
                    } else {
                        targetParent.insertBefore(this.placeholder, targetItem.nextSibling);
                    }
                    
                    // Trigger reflow for smooth animation
                    void this.placeholder.offsetHeight;
                } catch (e) {
                    // If insertion fails, just append to container
                    console.warn('Failed to insert placeholder:', e);
                    if (container) {
                        container.appendChild(this.placeholder);
                    }
                }
            }
        }
        
        insertPlaceholderAtTop() {
            if (!this.placeholder) return;
            
            // Remove placeholder from current position if it exists
            if (this.placeholder.parentNode) {
                this.placeholder.remove();
            }
            
            // For groups, find the first reorderable group (excluding ungrouped)
            // We need to insert before the first reorderable group, not just any first child
            let firstReorderableItem = null;
            
            // Walk through container children to find first reorderable item
            let node = this.container.firstChild;
            while (node) {
                if (node.nodeType === 1 && node.matches && node.matches(this.itemSelector)) {
                    // For groups, skip ungrouped group
                    if (this.itemSelector.includes('.collection-group')) {
                        const groupId = node.dataset?.groupId;
                        if (groupId && groupId !== 'ungrouped' && node !== this.draggedItem) {
                            firstReorderableItem = node;
                            break;
                        }
                    } else if (node !== this.draggedItem) {
                        firstReorderableItem = node;
                        break;
                    }
                }
                node = node.nextSibling;
            }
            
            // Insert placeholder at the absolute top (before first reorderable item)
            try {
                if (firstReorderableItem && firstReorderableItem.parentNode === this.container) {
                    // Insert before the first reorderable item
                    this.container.insertBefore(this.placeholder, firstReorderableItem);
                } else {
                    // No reorderable items found - insert as absolute first child
                    if (this.container.firstChild && this.container.firstChild !== this.placeholder) {
                        this.container.insertBefore(this.placeholder, this.container.firstChild);
                    } else if (!this.placeholder.parentNode) {
                        this.container.appendChild(this.placeholder);
                    }
                }
                
                // Trigger reflow for smooth animation
                void this.placeholder.offsetHeight;
            } catch (e) {
                console.error('Failed to insert placeholder at top:', e);
            }
        }
        
        insertPlaceholderGrid(targetItem, mouseX, mouseY, targetContainer) {
            if (!this.placeholder || !targetItem) return;
            
            const container = targetContainer || this.container;
            
            // Make sure targetItem is still in the DOM
            if (!targetItem.parentNode) return;
            
            const rect = targetItem.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            
            // Determine insertion position based on mouse position
            // Left half of card = insert before, right half = insert after
            const insertBeforeX = mouseX < centerX;
            const insertBeforeY = mouseY < centerY;
            
            // For grid layouts, prioritize horizontal (X) positioning since cards are in columns
            // Left half of card = insert before (placeholder on left)
            // Right half of card = insert after (placeholder on right)
            // Only use vertical (Y) if mouse is very close to top or bottom edge
            const distX = Math.abs(mouseX - centerX);
            const distY = Math.abs(mouseY - centerY);
            
            // Use Y position only if mouse is much closer to top/bottom than left/right
            // Otherwise, always use X position (left/right) - this is the primary behavior
            let insertBefore;
            if (distY < distX * 0.3) {
                // Mouse is very close to top or bottom edge - use vertical positioning
                insertBefore = insertBeforeY;
            } else {
                // Normal case - use horizontal positioning (left/right)
                insertBefore = insertBeforeX;
            }
            
            // Check if we need to move the placeholder
            const currentParent = this.placeholder.parentNode;
            const targetParent = targetItem.parentNode;
            
            // Get current placeholder position relative to target
            let currentPosition = null;
            if (currentParent === targetParent && this.placeholder.parentNode) {
                const placeholderIndex = Array.from(targetParent.children).indexOf(this.placeholder);
                const targetIndex = Array.from(targetParent.children).indexOf(targetItem);
                if (placeholderIndex !== -1 && targetIndex !== -1) {
                    currentPosition = placeholderIndex < targetIndex ? 'before' : 'after';
                }
            }
            
            const newPosition = insertBefore ? 'before' : 'after';
            const shouldMove = currentParent !== targetParent || currentPosition !== newPosition;
            
            if (shouldMove) {
                // Remove placeholder from current position if it exists
                if (currentParent) {
                    this.placeholder.remove();
                }
                
                // Insert placeholder in the correct position
                try {
                    if (insertBefore) {
                        targetParent.insertBefore(this.placeholder, targetItem);
                    } else {
                        targetParent.insertBefore(this.placeholder, targetItem.nextSibling);
                    }
                    
                    // Trigger reflow for smooth animation
                    void this.placeholder.offsetHeight;
                } catch (e) {
                    // If insertion fails, just append to container
                    console.warn('Failed to insert placeholder in grid:', e);
                    if (container) {
                        container.appendChild(this.placeholder);
                    }
                }
            }
        }

        createPlaceholder(item) {
            // Remove ALL existing placeholders first (safety check)
            const existingPlaceholders = document.querySelectorAll('.drag-placeholder');
            existingPlaceholders.forEach(ph => {
                if (ph.parentNode) {
                    ph.remove();
                }
            });
            
            // Create new placeholder
            this.placeholder = document.createElement('div');
            this.placeholder.className = 'drag-placeholder';
            const computedStyle = window.getComputedStyle(item);
            this.placeholder.style.height = item.offsetHeight + 'px';
            this.placeholder.style.width = item.offsetWidth + 'px';
            this.placeholder.style.margin = computedStyle.margin;
            this.placeholder.style.border = '2px dashed #3b82f6';
            this.placeholder.style.borderRadius = '4px';
            this.placeholder.style.backgroundColor = 'rgba(59, 130, 246, 0.1)';
            
            // Insert at dragged item's position
            if (item.parentNode) {
                item.parentNode.insertBefore(this.placeholder, item);
                
                // Trigger animation by forcing reflow
                void this.placeholder.offsetHeight;
            }
        }

        cleanup() {
            if (this.draggedItem) {
                // CRITICAL: Immediately reset fixed positioning to prevent floating
                // This must happen synchronously, not in a timeout
                this.draggedItem.style.position = '';
                this.draggedItem.style.left = '';
                this.draggedItem.style.top = '';
                this.draggedItem.style.zIndex = '';
                this.draggedItem.style.pointerEvents = '';
                
                // Ensure item is in the correct DOM position (restore if needed)
                if (this.originalParent && !this.draggedItem.parentNode) {
                    // Item was removed from DOM - restore it
                    if (this.originalNextSibling && this.originalNextSibling.parentNode === this.originalParent) {
                        this.originalParent.insertBefore(this.draggedItem, this.originalNextSibling);
                    } else {
                        this.originalParent.appendChild(this.draggedItem);
                    }
                }
                
                // Faster transition back to normal state for visual effects
                this.draggedItem.style.transition = 'opacity 0.1s ease-out, transform 0.1s ease-out, box-shadow 0.1s ease-out';
                this.draggedItem.classList.remove('dragging');
                
                // Reset remaining styles after a brief delay to allow transition
                setTimeout(() => {
                    if (this.draggedItem) {
                        this.draggedItem.style.opacity = '';
                        this.draggedItem.style.transform = '';
                        this.draggedItem.style.transition = '';
                        this.draggedItem.style.boxShadow = '';
                    }
                }, 100);
            }
            
            // Remove ALL placeholders (safety check for duplicates)
            const allPlaceholders = document.querySelectorAll('.drag-placeholder');
            allPlaceholders.forEach(ph => {
                if (ph.parentNode) {
                    ph.remove();
                }
            });
            
            this.placeholder = null;
            this.draggedItem = null;
            this.draggedIndex = null;
            this.currentTarget = null;
            this.currentTargetContainer = null;
            this.lastTargetItem = null;
            this.originalParent = null;
            this.originalNextSibling = null;
        }

        getItems() {
            return Array.from(this.container.querySelectorAll(this.itemSelector));
        }

        update() {
            // Recalculate items if DOM changed
            // No need to do anything - getItems() is dynamic
        }

        destroy() {
            this.cleanup();
            
            // Remove mousedown listener
            if (this.boundMouseDown && this.container) {
                this.container.removeEventListener('mousedown', this.boundMouseDown, this.useCapture);
            }
            
            // Remove global listeners
            if (this.boundMouseMove) {
                document.removeEventListener('mousemove', this.boundMouseMove);
            }
            if (this.boundMouseUp) {
                document.removeEventListener('mouseup', this.boundMouseUp);
            }
        }
    }

    /**
     * Manager for nested drag and drop (groups and collections)
     */
    class MouseDragDropManager {
        constructor(options) {
            this.container = options.container;
            this.groupSelector = options.groupSelector || '.collection-group';
            this.groupHeaderSelector = options.groupHeaderSelector || '.group-header';
            this.collectionSelector = options.collectionSelector || '.collection-card';
            this.collectionContainerSelector = options.collectionContainerSelector || '.group-collections, .ungrouped-collections';
            
            this.onGroupReorder = options.onGroupReorder || function() {};
            this.onCollectionReorder = options.onCollectionReorder || function() {};
            this.onCollectionMove = options.onCollectionMove || function() {};
            
            this.groupSorter = null;
            this.collectionSorters = new Map();
            
            this.init();
        }

        init() {
            this.setupGroupSorting();
            this.setupCollectionSorting();
        }

        setupGroupSorting() {
            if (this.groupSorter) {
                this.groupSorter.destroy();
            }

            const container = this.container;
            if (!container) return;

            this.groupSorter = new MouseDragDrop({
                container: container,
                itemSelector: this.groupSelector + ':not(.ungrouped)',
                handleSelector: this.groupHeaderSelector,
                onReorder: (fromIndex, toIndex) => {
                    this.onGroupReorder(fromIndex, toIndex);
                }
            });
        }

        setupCollectionSorting() {
            // Destroy existing collection sorters
            this.collectionSorters.forEach(sorter => sorter.destroy());
            this.collectionSorters.clear();

            const containers = Array.from(this.container.querySelectorAll(this.collectionContainerSelector));
            
            containers.forEach(container => {
                const cardCount = container.querySelectorAll(this.collectionSelector).length;
                if (cardCount === 0) {
                    return;
                }

                const sorter = new MouseDragDrop({
                    container: container,
                    itemSelector: this.collectionSelector,
                    handleSelector: null,
                    excludeSelector: 'a, button, input, select, textarea, .dropdown-trigger, .collection-menu-wrapper',
                    useCapture: true,
                    allContainers: containers, // Enable cross-container moves
                    onReorder: (fromIndex, toIndex) => {
                        const groupId = container.closest(this.groupSelector)?.dataset.groupId || 'ungrouped';
                        this.onCollectionReorder(groupId, fromIndex, toIndex);
                    },
                    // Custom handler for cross-container detection
                    onCrossContainerMove: (item, fromContainer, toContainer, toIndex) => {
                        const collectionSlug = item.dataset.collectionSlug;
                        if (!collectionSlug) {
                            console.error('No collection slug found on item:', item);
                            return;
                        }
                        const fromGroupId = fromContainer.closest(this.groupSelector)?.dataset.groupId || 'ungrouped';
                        const toGroupId = toContainer.closest(this.groupSelector)?.dataset.groupId || 'ungrouped';
                        
                        // console.log('Cross-container move detected:', collectionSlug, fromGroupId, '->', toGroupId, 'at', toIndex);
                        this.onCollectionMove(collectionSlug, fromGroupId, toGroupId, toIndex);
                    }
                });
                
                this.collectionSorters.set(container, sorter);
            });
        }

        update() {
            this.setupGroupSorting();
            this.setupCollectionSorting();
        }

        destroy() {
            if (this.groupSorter) {
                this.groupSorter.destroy();
                this.groupSorter = null;
            }
            
            this.collectionSorters.forEach(sorter => sorter.destroy());
            this.collectionSorters.clear();
        }
    }

    // Export
    window.MouseDragDrop = MouseDragDrop;
    window.MouseDragDropManager = MouseDragDropManager;
})();
