/**
 * Simple Drag and Drop Reordering
 * 
 * A minimal, robust drag-and-drop system based on HTML5 Drag and Drop API.
 * Designed to be simple, reliable, and easy to use across the entire system.
 * 
 * Why this is simpler than the previous version:
 * - No nested drag handling (one drag at a time)
 * - No complex global state management
 * - No MutationObserver (call update() manually when DOM changes)
 * - Clear separation of concerns
 * 
 * Usage:
 *   const sorter = new SimpleDragDrop({
 *     container: document.querySelector('.my-list'),
 *     itemSelector: '.list-item',
 *     handleSelector: '.drag-handle', // optional - if not provided, whole item is draggable
 *     onReorder: (fromIndex, toIndex) => {
 *       // Handle the reorder
 *       console.log(`Moved from ${fromIndex} to ${toIndex}`);
 *     }
 *   });
 *   sorter.init();
 * 
 * When DOM changes (items added/removed), call:
 *   sorter.update();
 */

(function() {
    'use strict';

    class SimpleDragDrop {
        constructor(options) {
            // Required
            this.container = options.container;
            this.itemSelector = options.itemSelector;
            this.onReorder = options.onReorder || function() {};
            
            // Optional
            this.handleSelector = options.handleSelector || null;
            this.draggingClass = options.draggingClass || 'dragging';
            this.dropIndicatorClass = options.dropIndicatorClass || 'drop-indicator';
            
            // Internal state
            this.draggedItem = null;
            this.draggedIndex = null;
            this.dropIndicator = null;
            this.isInitialized = false;
            this.isProcessingDrop = false; // Flag to prevent duplicate drop processing
            
            // Bind methods
            this.handleDragStart = this.handleDragStart.bind(this);
            this.handleDragOver = this.handleDragOver.bind(this);
            this.handleDragLeave = this.handleDragLeave.bind(this);
            this.handleDrop = this.handleDrop.bind(this);
            this.handleDragEnd = this.handleDragEnd.bind(this);
        }

        /**
         * Initialize the drag-and-drop system
         */
        init() {
            if (this.isInitialized) {
                console.warn('SimpleDragDrop already initialized');
                return;
            }

            if (!this.container) {
                console.error('SimpleDragDrop: container is required');
                return;
            }


            // Set up event listeners on container (event delegation)
            // Use capture phase to ensure we handle events before other handlers
            this.container.addEventListener('dragstart', this.handleDragStart, true);
            this.container.addEventListener('dragover', this.handleDragOver, true);
            this.container.addEventListener('dragleave', this.handleDragLeave, true);
            this.container.addEventListener('drop', this.handleDrop, true);
            this.container.addEventListener('dragend', this.handleDragEnd, true);

            // Make items draggable
            this.updateDraggableItems();

            // Create drop indicator
            this.createDropIndicator();

            this.isInitialized = true;
        }

        /**
         * Update draggable attributes on items
         * Call this when items are added/removed from DOM
         */
        updateDraggableItems() {
            const items = this.getItems();
            items.forEach(item => {
                // Check if we should make this item draggable
                if (this.handleSelector) {
                    // Only draggable if has handle
                    const handle = item.querySelector(this.handleSelector);
                    if (handle) {
                        // Make the ITEM draggable (not just the handle)
                        // The handle selector is just to check if drag started from handle
                        item.setAttribute('draggable', 'true');
                        // Make handle visually indicate it's draggable
                        handle.style.cursor = 'grab';
                    } else {
                        item.setAttribute('draggable', 'false');
                    }
                } else {
                    // Whole item is draggable
                    item.setAttribute('draggable', 'true');
                }
            });
        }

        /**
         * Get all items
         */
        getItems() {
            return Array.from(this.container.querySelectorAll(this.itemSelector));
        }

        /**
         * Create drop indicator element
         */
        createDropIndicator() {
            // Remove existing indicator if any
            const existing = this.container.querySelector('.' + this.dropIndicatorClass);
            if (existing) {
                existing.remove();
            }

            this.dropIndicator = document.createElement('div');
            this.dropIndicator.className = this.dropIndicatorClass;
            // Base styles - will be adjusted in showDropIndicator based on layout
            // For groups, use higher z-index to ensure visibility
            const baseZIndex = this.itemSelector === '.collection-group' ? 10000 : 1000;
            this.dropIndicator.style.cssText = `
                position: absolute;
                background: var(--primary-500, #3b82f6);
                pointer-events: none;
                z-index: ${baseZIndex};
                display: none;
                box-shadow: 0 0 8px rgba(59, 130, 246, 0.6);
            `;
            
            // Ensure container has relative positioning
            const containerStyle = window.getComputedStyle(this.container);
            if (containerStyle.position === 'static') {
                this.container.style.position = 'relative';
            }
            
            // Append to container
            this.container.appendChild(this.dropIndicator);
        }

        /**
         * Handle drag start
         */
        handleDragStart(e) {
            // Prevent duplicate processing - if we already have this item as draggedItem, ignore
            const item = e.target.closest(this.itemSelector);
            if (!item) {
                return; // Not our item - let other handlers process it
            }
            
            // CRITICAL: If we're already processing this item, stop propagation and return
            // This prevents the event from bubbling and being caught by other handlers
            if (this.draggedItem === item) {
                e.stopPropagation();
                return;
            }
            
            // CRITICAL: Check if item already has dragging class - means we already processed this
            if (item.classList.contains(this.draggingClass)) {
                e.stopPropagation();
                return;
            }

            // If using handle selector, only allow drag from handle
            if (this.handleSelector) {
                const handle = e.target.closest(this.handleSelector);
                if (!handle) {
                    // Not dragging from handle - but don't prevent default, just ignore
                    return;
                }
                
                // CRITICAL: If the handle itself is draggable and matches itemSelector, use it
                // Otherwise, find the item (parent of handle) - this is the actual item to drag
                if (handle.matches(this.itemSelector)) {
                    // Handle is the item - make sure it's draggable
                    if (handle.getAttribute('draggable') !== 'true') {
                        return;
                    }
                    // Update item to be the handle (since handle IS the item)
                    item = handle;
                } else {
                    // Item is parent of handle - make sure item is draggable
                    // But also check: if handle is draggable, we might be dragging the handle
                    // In that case, we need to find the actual item (group section)
                    if (handle.getAttribute('draggable') === 'true' && !item.matches(this.itemSelector)) {
                        // Handle is draggable but item doesn't match - find the actual item
                        item = handle.closest(this.itemSelector);
                        if (!item) {
                            return; // Can't find the item
                        }
                    }
                    // Make sure the item (group section) is draggable
                    if (item.getAttribute('draggable') !== 'true') {
                        return;
                    }
                }
            } else {
                // No handle selector - check if item is draggable
                if (item.getAttribute('draggable') !== 'true') {
                    return; // Not draggable - don't prevent, just ignore
                }
            }

            
            // ALWAYS clean up any previous drag state first (important!)
            if (this.draggedItem && this.draggedItem !== item) {
                this.draggedItem.classList.remove(this.draggingClass);
                this.draggedItem.style.opacity = '';
            }
            
            // Also clean up any items that might be stuck in dragging state
            this.getItems().forEach(i => {
                if (i !== item && i.classList.contains(this.draggingClass)) {
                    i.classList.remove(this.draggingClass);
                    i.style.opacity = '';
                }
            });
            
            // Clear any stuck opacity on the current item
            if (item.style.opacity === '0.5') {
                item.style.opacity = '';
            }
            
            this.draggedItem = item;
            this.draggedIndex = this.getItems().indexOf(item);

            // Mark as dragging
            item.classList.add(this.draggingClass);

            // Set drag data
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', String(this.draggedIndex));

            // Hide the item during drag (optional - comment out if you want to see it)
            setTimeout(() => {
                item.style.opacity = '0.5';
            }, 0);
            
            // CRITICAL: Check if this is a group drag (for nested drag-drop manager)
            // If it is, we should NOT stop propagation to allow the manager to track it
            // We can detect this by checking if the item matches .collection-group selector
            const isGroupDrag = item.matches('.collection-group');
            
            // Stop propagation to prevent duplicate processing
            // BUT: For group drags, we still stop propagation because the nested handler
            // should have already processed it in capture phase before us
            e.stopPropagation();
        }

        /**
         * Handle drag over
         */
        handleDragOver(e) {
            // Log for groups to debug
            if (this.itemSelector === '.collection-group') {
                if (!this.draggedItem) {
                }
            } else {
                // Reduced logging for other items
                if (Math.random() < 0.01) {
                }
            }
            
            // CRITICAL: Only process if we have a draggedItem OR if there's a dragging item in our container
            // that matches our selector AND is actually being dragged (has dragging class)
            const draggingItem = this.container.querySelector('.' + this.draggingClass);
            const hasDraggingItem = draggingItem && 
                                   draggingItem.matches(this.itemSelector) && 
                                   this.container.contains(draggingItem);
            
            // If we don't have draggedItem, try to recover it
            if (!this.draggedItem && hasDraggingItem) {
                this.draggedItem = draggingItem;
                this.draggedIndex = this.getItems().indexOf(draggingItem);
            }
            
            // CRITICAL: Only process if we have a valid draggedItem that matches our selector
            // This prevents group sorter from processing collection card drags and vice versa
            if (!this.draggedItem || 
                !this.draggedItem.matches(this.itemSelector)) {
                // Not our drag - let other handlers process it
                // Don't prevent default - let the correct handler process it
                return;
            }
            
            // Verify dragged item is in our container (or allow cross-group for collections)
            const isCrossGroupMove = this.itemSelector === '.collection-card' && 
                                    !this.container.contains(this.draggedItem);
            if (!isCrossGroupMove && !this.container.contains(this.draggedItem)) {
                // Not in our container and not a cross-group move - ignore
                return;
            }
            
            // We have a valid draggedItem - always prevent default to keep drag alive
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'move';

            // Find target item - simplified approach
            let targetItem = null;
            const items = this.getItems();
            const mouseX = e.clientX;
            const mouseY = e.clientY;
            
            // First, try e.target.closest for quick check
            const closestItem = e.target.closest(this.itemSelector);
            if (closestItem && closestItem !== this.draggedItem && this.container.contains(closestItem)) {
                targetItem = closestItem;
            } else {
                // Use mouse position to find the item
                // Find the item that contains the mouse position
                for (const item of items) {
                    if (item === this.draggedItem) continue;
                    const rect = item.getBoundingClientRect();
                    // Check if mouse is within the item's bounds
                    if (mouseX >= rect.left && mouseX <= rect.right &&
                        mouseY >= rect.top && mouseY <= rect.bottom) {
                        targetItem = item;
                        break;
                    }
                }
                
                // If still not found, find the closest item by distance
                if (!targetItem && items.length > 0) {
                    let minDist = Infinity;
                    for (const item of items) {
                        if (item === this.draggedItem) continue;
                        const rect = item.getBoundingClientRect();
                        const centerX = rect.left + rect.width / 2;
                        const centerY = rect.top + rect.height / 2;
                        const dist = Math.sqrt(Math.pow(mouseX - centerX, 2) + Math.pow(mouseY - centerY, 2));
                        if (dist < minDist) {
                            minDist = dist;
                            targetItem = item;
                        }
                    }
                }
            }

            if (!targetItem || targetItem === this.draggedItem) {
                // No valid target, but keep the drag alive by not returning
                // Just hide the indicator
                this.hideDropIndicator();
                // Log for groups to debug
                if (this.itemSelector === '.collection-group') {
                }
                // Don't return - we've already prevented default, so keep the drag alive
                return;
            }

            // Verify target item is in our container
            if (!this.container.contains(targetItem)) {
                this.hideDropIndicator();
                if (this.itemSelector === '.collection-group') {
                }
                return;
            }

            // Show drop indicator
            this.showDropIndicator(targetItem, e);
        }

        /**
         * Show drop indicator
         */
        showDropIndicator(targetItem, e) {
            if (!this.dropIndicator) return;
            
            const containerRect = this.container.getBoundingClientRect();
            const targetRect = targetItem.getBoundingClientRect();
            
            // Detect if container is a grid (cards side by side)
            const containerStyle = window.getComputedStyle(this.container);
            const isGrid = containerStyle.display === 'grid' || 
                          containerStyle.display === 'inline-grid' ||
                          this.container.classList.contains('grid');
            
            // For collection cards, ALWAYS use vertical indicators (between columns)
            // For groups, ALWAYS use horizontal indicators (between rows)
            const isCollectionCard = this.itemSelector === '.collection-card';
            const isGroup = this.itemSelector === '.collection-group';
            let isVertical = false;
            let insertBefore = false;
            
            if (isCollectionCard && isGrid) {
                // Collection cards in grid - ALWAYS vertical
                isVertical = true;
                const mouseX = e.clientX;
                const centerX = targetRect.left + targetRect.width / 2;
                insertBefore = mouseX < centerX;
            } else if (isGroup) {
                // Groups - ALWAYS horizontal (between rows)
                isVertical = false;
                const mouseY = e.clientY;
                const centerY = targetRect.top + targetRect.height / 2;
                insertBefore = mouseY < centerY;
            } else if (isGrid) {
                // Other grid items - check both horizontal and vertical distances
                const mouseX = e.clientX;
                const mouseY = e.clientY;
                const centerX = targetRect.left + targetRect.width / 2;
                const centerY = targetRect.top + targetRect.height / 2;
                
                const distX = Math.abs(mouseX - centerX);
                const distY = Math.abs(mouseY - centerY);
                
                // If closer to left/right edge, show vertical indicator
                if (distX < distY) {
                    isVertical = true;
                    insertBefore = mouseX < centerX;
                } else {
                    isVertical = false;
                    insertBefore = mouseY < centerY;
                }
            } else {
                // List layout - always horizontal
                const mouseY = e.clientY;
                const centerY = targetRect.top + targetRect.height / 2;
                insertBefore = mouseY < centerY;
            }

            // Position indicator
            if (isVertical) {
                // Vertical indicator (between columns)
                const x = insertBefore 
                    ? targetRect.left - containerRect.left
                    : targetRect.right - containerRect.left;
                this.dropIndicator.style.left = x + 'px';
                this.dropIndicator.style.top = (targetRect.top - containerRect.top) + 'px';
                this.dropIndicator.style.width = '3px';
                this.dropIndicator.style.height = targetRect.height + 'px';
                this.dropIndicator.style.right = 'auto'; // Clear right if set by CSS
            } else {
                // Horizontal indicator (between rows)
                const y = insertBefore 
                    ? targetRect.top - containerRect.top
                    : targetRect.bottom - containerRect.top;
                this.dropIndicator.style.top = y + 'px';
                this.dropIndicator.style.left = '0';
                this.dropIndicator.style.right = '0'; // Ensure it spans full width
                this.dropIndicator.style.width = '100%';
                this.dropIndicator.style.height = '3px';
            }

            // Ensure indicator is visible with high z-index for groups
            if (this.itemSelector === '.collection-group') {
                this.dropIndicator.style.zIndex = '10000';
            }
            this.dropIndicator.style.display = 'block';
            this.dropIndicator.style.visibility = 'visible';
            this.dropIndicator.style.opacity = '1';
            this.dropIndicator.dataset.insertBefore = insertBefore;
            this.dropIndicator.dataset.targetIndex = String(this.getItems().indexOf(targetItem));
        }

        /**
         * Hide drop indicator
         */
        hideDropIndicator() {
            if (this.dropIndicator) {
                this.dropIndicator.style.display = 'none';
            }
        }

        /**
         * Handle drag leave
         */
        handleDragLeave(e) {
            // Only hide if we're actually leaving the container
            if (!this.container.contains(e.relatedTarget)) {
                this.hideDropIndicator();
            }
        }

        /**
         * Handle drop
         */
        handleDrop(e) {
            // CRITICAL: Prevent duplicate processing - check and set flag atomically
            if (this.isProcessingDrop) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation(); // Prevent other handlers on same element
                return;
            }
            
            // Also check if this event has already been processed by marking it
            if (e._simpleDragDropProcessed) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                return;
            }
            
            // Mark event as processed
            e._simpleDragDropProcessed = true;
            
            // Set flag IMMEDIATELY before any async operations
            this.isProcessingDrop = true;
            
            // Also stop propagation immediately to prevent other handlers
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation(); // Prevent other handlers on same element
            
            // If no draggedItem, try to find it from the dragging class
            // CRITICAL: Only recover if it matches our selector AND is in our container
            if (!this.draggedItem) {
                // First check our container
                let draggingItem = this.container.querySelector('.' + this.draggingClass);
                
                // If not found in our container, check document (for cross-group moves)
                if (!draggingItem) {
                    draggingItem = document.querySelector('.' + this.draggingClass);
                }
                
                if (draggingItem && 
                    draggingItem.matches(this.itemSelector)) {
                    // Check if it's in our container OR if we're doing a cross-group move
                    if (this.container.contains(draggingItem) || this.itemSelector === '.collection-card') {
                        this.draggedItem = draggingItem;
                        this.draggedIndex = this.getItems().indexOf(draggingItem);
                        // If not in container, index might be -1, that's OK for cross-group moves
                    } else {
                        // Not our item - ignore this drop
                        this.isProcessingDrop = false;
                        return;
                    }
                } else {
                    // Not our item - ignore this drop
                    this.isProcessingDrop = false;
                    return;
                }
            }

            // CRITICAL: Verify the dragged item matches our itemSelector
            // This prevents group sorter from handling collection drops and vice versa
            if (!this.draggedItem || !this.draggedItem.matches(this.itemSelector)) {
                this.isProcessingDrop = false; // Reset flag on early return
                return; // Not our item - ignore
            }

            // Verify dragged item is actually in our container
            if (!this.container.contains(this.draggedItem)) {
                this.isProcessingDrop = false; // Reset flag on early return
                return; // Item not in our container - ignore
            }

            // Log only occasionally to reduce spam
            if (Math.random() < 0.1) {
            }

            // Get target index from indicator or calculate it
            let targetIndex = null;
            let insertBefore = false;

            // CRITICAL: Always prefer the drop indicator's target index if available
            // This is more reliable than recalculating
            if (this.dropIndicator && this.dropIndicator.style.display !== 'none' && this.dropIndicator.dataset.targetIndex) {
                targetIndex = parseInt(this.dropIndicator.dataset.targetIndex, 10);
                insertBefore = this.dropIndicator.dataset.insertBefore === 'true';
            } else {
                // Fallback: find target item using mouse position (same logic as dragover)
                const items = this.getItems();
                const mouseX = e.clientX;
                const mouseY = e.clientY;
                let targetItem = null;
                
                // First try closest
                const closestItem = e.target.closest(this.itemSelector);
                if (closestItem && closestItem !== this.draggedItem && this.container.contains(closestItem)) {
                    targetItem = closestItem;
                } else {
                    // Use mouse position
                    for (const item of items) {
                        if (item === this.draggedItem) continue;
                        const rect = item.getBoundingClientRect();
                        if (mouseX >= rect.left && mouseX <= rect.right &&
                            mouseY >= rect.top && mouseY <= rect.bottom) {
                            targetItem = item;
                            break;
                        }
                    }
                    
                    // If still not found, find closest by distance
                    if (!targetItem && items.length > 0) {
                        let minDist = Infinity;
                        for (const item of items) {
                            if (item === this.draggedItem) continue;
                            const rect = item.getBoundingClientRect();
                            const centerX = rect.left + rect.width / 2;
                            const centerY = rect.top + rect.height / 2;
                            const dist = Math.sqrt(Math.pow(mouseX - centerX, 2) + Math.pow(mouseY - centerY, 2));
                            if (dist < minDist) {
                                minDist = dist;
                                targetItem = item;
                            }
                        }
                    }
                }
                
                if (targetItem && targetItem !== this.draggedItem) {
                    targetIndex = this.getItems().indexOf(targetItem);
                    const rect = targetItem.getBoundingClientRect();
                    // For groups, always use vertical position (horizontal indicator)
                    if (this.itemSelector === '.collection-group') {
                        insertBefore = e.clientY < rect.top + rect.height / 2;
                    } else {
                        // Check if grid layout
                        const containerStyle = window.getComputedStyle(this.container);
                        const isGrid = containerStyle.display === 'grid' || 
                                      containerStyle.display === 'inline-grid' ||
                                      this.container.classList.contains('grid');
                        if (isGrid) {
                            const centerX = rect.left + rect.width / 2;
                            insertBefore = e.clientX < centerX;
                        } else {
                            insertBefore = e.clientY < rect.top + rect.height / 2;
                        }
                    }
                }
            }

            // Validate indices
            if (targetIndex === null || targetIndex < 0 || this.draggedIndex < 0) {
                this.hideDropIndicator();
                this.isProcessingDrop = false; // Reset flag on early return
                return;
            }

            if (targetIndex === this.draggedIndex) {
                this.hideDropIndicator();
                this.isProcessingDrop = false; // Reset flag on early return
                return;
            }

            // Calculate final index
            let finalIndex = targetIndex;
            if (insertBefore) {
                // If dragging forward, adjust for removal
                if (this.draggedIndex < targetIndex) {
                    finalIndex = targetIndex - 1;
                }
            } else {
                // If dragging backward, adjust for removal
                if (this.draggedIndex > targetIndex) {
                    finalIndex = targetIndex + 1;
                } else {
                    finalIndex = targetIndex + 1;
                }
            }

            // Validate final index
            const items = this.getItems();
            if (finalIndex < 0 || finalIndex > items.length) {
                this.hideDropIndicator();
                this.isProcessingDrop = false; // Reset flag on early return
                return;
            }

            // Log only occasionally
            if (Math.random() < 0.1) {
            }
            
            // Call reorder callback
            this.onReorder(this.draggedIndex, finalIndex);

            this.hideDropIndicator();
            
            // Call cleanup after drop completes
            // Use a longer delay to ensure DOM updates complete
            setTimeout(() => {
                this.isProcessingDrop = false;
                // Clean up drag state after drop is fully processed
                this.cleanupDragState();
            }, 150);
        }

        /**
         * Handle drag end
         */
        handleDragEnd(e) {
            // Don't log every dragend - too spammy
            
            // CRITICAL: If we're processing a drop, delay cleanup until after drop completes
            // This prevents clearing draggedItem before the drop handler finishes
            if (this.isProcessingDrop) {
                // Wait a bit longer to ensure drop processing completes
                setTimeout(() => {
                    this.cleanupDragState();
                }, 200);
                return;
            }
            
            // Normal cleanup
            this.cleanupDragState();
        }
        
        /**
         * Clean up drag state (extracted for reuse)
         */
        cleanupDragState() {
            // Prevent duplicate cleanup - if we've already cleaned up, ignore
            if (!this.draggedItem && !this.container.querySelector('.' + this.draggingClass)) {
                return;
            }
            
            // Always clean up, even if draggedItem is null (in case of errors)
            if (this.draggedItem) {
                this.draggedItem.classList.remove(this.draggingClass);
                this.draggedItem.style.opacity = '';
                this.draggedItem = null;
            } else {
                // Clean up any items that might have the dragging class
                this.getItems().forEach(item => {
                    item.classList.remove(this.draggingClass);
                    item.style.opacity = '';
                });
            }
            
            // Also clear draggedIndex
            this.draggedIndex = null;
            
            // Reset drop processing flag
            this.isProcessingDrop = false;
            
            // Hide drop indicator
            this.hideDropIndicator();
        }

        /**
         * Update - call this when DOM changes (items added/removed)
         */
        update() {
            // Clear any stuck drag state
            if (this.draggedItem) {
                this.draggedItem.classList.remove(this.draggingClass);
                this.draggedItem.style.opacity = '';
                this.draggedItem = null;
            }
            this.draggedIndex = null;
            this.hideDropIndicator();
            
            // Update draggable items
            this.updateDraggableItems();
        }

        /**
         * Destroy - clean up event listeners
         */
        destroy() {
            if (!this.isInitialized) return;

            this.container.removeEventListener('dragstart', this.handleDragStart);
            this.container.removeEventListener('dragover', this.handleDragOver);
            this.container.removeEventListener('dragleave', this.handleDragLeave);
            this.container.removeEventListener('drop', this.handleDrop);
            this.container.removeEventListener('dragend', this.handleDragEnd);

            if (this.dropIndicator) {
                this.dropIndicator.remove();
                this.dropIndicator = null;
            }

            this.isInitialized = false;
        }
    }

    // Export to global scope
    window.SimpleDragDrop = SimpleDragDrop;

})();

