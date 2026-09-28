/**
 * Reusable Drag and Drop System
 * 
 * A lightweight, dependency-free drag-and-drop system for reordering items.
 * Supports simple lists, nested structures, direct DOM manipulation, and custom constraints.
 * 
 * Features:
 * - Handles dynamic DOM changes (items added/removed)
 * - Supports nested drag-and-drop (parent and child elements can both be draggable)
 * - Event delegation for performance
 * - Automatic cleanup and re-initialization
 * 
 * Usage:
 *   const sorter = new DragDropSorter({
 *     container: document.querySelector('.my-list'),
 *     itemSelector: '.list-item',
 *     handleSelector: '.drag-handle', // optional
 *     onReorder: (fromIndex, toIndex, insertBefore) => { ... },
 *     constraints: {
 *       excludeSelector: '.no-drag', // optional
 *       onlyFromHandle: true, // optional
 *       nestedDragType: 'item' // 'item' or 'group' - for nested scenarios
 *     }
 *   });
 *   sorter.init();
 */

(function() {
    'use strict';

    // Global registry to track active sorters and handle nested drag conflicts
    const activeSorters = new WeakMap();
    const globalDragState = {
        activeSorter: null,
        dragType: null,
        draggedElement: null
    };

    /**
     * DragDropSorter - Main class for drag-and-drop reordering
     */
    class DragDropSorter {
        constructor(options) {
            // Required options
            this.container = options.container;
            this.itemSelector = options.itemSelector;
            this.onReorder = options.onReorder || function() {};
            
            // Optional options
            this.handleSelector = options.handleSelector || null;
            this.constraints = options.constraints || {};
            this.dropIndicatorClass = options.dropIndicatorClass || 'drop-indicator';
            this.draggingClass = options.draggingClass || 'dragging';
            this.dragOverClass = options.dragOverClass || 'drag-over';
            this.axis = options.axis || 'vertical'; // 'vertical' or 'horizontal'
            this.threshold = options.threshold || 0.5; // 0-1, position threshold for before/after
            this.dragType = options.constraints?.nestedDragType || 'item'; // 'item' or 'group'
            this.observeDOM = options.observeDOM !== false; // Default: true, watch for DOM changes
            
            // Internal state
            this.draggedItem = null;
            this.draggedIndex = null;
            this.dropIndicator = null;
            this.isInitialized = false;
            this.mutationObserver = null;
            this.itemCache = null;
            
            // Bind methods
            this.handleDragStart = this.handleDragStart.bind(this);
            this.handleDragOver = this.handleDragOver.bind(this);
            this.handleDragLeave = this.handleDragLeave.bind(this);
            this.handleDrop = this.handleDrop.bind(this);
            this.handleDragEnd = this.handleDragEnd.bind(this);
            this.handleDOMChange = this.handleDOMChange.bind(this);
        }

        /**
         * Initialize the drag-and-drop system
         */
        init() {
            if (this.isInitialized) {
                console.warn('DragDropSorter already initialized');
                return;
            }

            if (!this.container) {
                console.error('DragDropSorter: container is required');
                return;
            }

            // Register this sorter
            activeSorters.set(this.container, this);

            // Set up event listeners on container (event delegation)
            // Use capture phase for dragstart/dragend to handle nested scenarios
            this.container.addEventListener('dragstart', this.handleDragStart, true);
            this.container.addEventListener('dragover', this.handleDragOver, false);
            this.container.addEventListener('dragleave', this.handleDragLeave, false);
            this.container.addEventListener('drop', this.handleDrop, false);
            this.container.addEventListener('dragend', this.handleDragEnd, true);

            // Make items draggable (needed for HTML5 drag-and-drop)
            this.setupDraggableItems();

            // Create drop indicator element
            this.createDropIndicator();

            // Set up DOM observation for dynamic changes
            if (this.observeDOM) {
                this.setupDOMObserver();
            }

            this.isInitialized = true;
        }

        /**
         * Set up draggable attribute on items
         */
        setupDraggableItems() {
            this.getItems().forEach(item => {
                // Only set draggable if not already set and item is not excluded
                if (item.getAttribute('draggable') === null && this.canDrag(item)) {
                    item.setAttribute('draggable', 'true');
                }
            });
        }

        /**
         * Set up MutationObserver to watch for DOM changes
         */
        setupDOMObserver() {
            if (!window.MutationObserver) {
                // Fallback for older browsers
                return;
            }

            this.mutationObserver = new MutationObserver((mutations) => {
                let shouldUpdate = false;

                mutations.forEach((mutation) => {
                    // Check if items were added or removed
                    if (mutation.type === 'childList') {
                        const addedNodes = Array.from(mutation.addedNodes);
                        const removedNodes = Array.from(mutation.removedNodes);

                        // Check if any added/removed nodes match our item selector
                        const hasRelevantChanges = 
                            addedNodes.some(node => 
                                node.nodeType === 1 && (
                                    node.matches && node.matches(this.itemSelector) ||
                                    node.querySelector && node.querySelector(this.itemSelector)
                                )
                            ) ||
                            removedNodes.some(node => 
                                node.nodeType === 1 && (
                                    node.matches && node.matches(this.itemSelector) ||
                                    node.querySelector && node.querySelector(this.itemSelector)
                                )
                            );

                        if (hasRelevantChanges) {
                            shouldUpdate = true;
                        }
                    }

                    // Check if draggable attribute was changed
                    if (mutation.type === 'attributes' && 
                        mutation.attributeName === 'draggable' &&
                        mutation.target.matches && mutation.target.matches(this.itemSelector)) {
                        shouldUpdate = true;
                    }
                });

                if (shouldUpdate && !this.draggedItem) {
                    // Only update if not currently dragging
                    this.handleDOMChange();
                }
            });

            this.mutationObserver.observe(this.container, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['draggable', 'class']
            });
        }

        /**
         * Handle DOM changes
         */
        handleDOMChange() {
            // Clear cache
            this.itemCache = null;

            // Update draggable attributes on new items
            this.setupDraggableItems();

            // Call custom callback if provided
            if (this.constraints.onDOMChange) {
                this.constraints.onDOMChange();
            }
        }

        /**
         * Destroy the drag-and-drop system
         */
        destroy() {
            if (!this.isInitialized) return;

            // Unregister sorter
            activeSorters.delete(this.container);

            this.container.removeEventListener('dragstart', this.handleDragStart, true);
            this.container.removeEventListener('dragover', this.handleDragOver);
            this.container.removeEventListener('dragleave', this.handleDragLeave);
            this.container.removeEventListener('drop', this.handleDrop);
            this.container.removeEventListener('dragend', this.handleDragEnd, true);

            // Stop DOM observation
            if (this.mutationObserver) {
                this.mutationObserver.disconnect();
                this.mutationObserver = null;
            }

            if (this.dropIndicator) {
                this.dropIndicator.remove();
                this.dropIndicator = null;
            }

            // Clear state
            this.draggedItem = null;
            this.draggedIndex = null;
            this.itemCache = null;

            // Clear global state if this was the active sorter
            if (globalDragState.activeSorter === this) {
                globalDragState.activeSorter = null;
                globalDragState.dragType = null;
                globalDragState.draggedElement = null;
            }

            this.isInitialized = false;
        }

        /**
         * Get all sortable items (with caching for performance)
         */
        getItems() {
            // Use cache if available and not dragging
            if (this.itemCache && !this.draggedItem) {
                return this.itemCache;
            }

            const items = Array.from(this.container.querySelectorAll(this.itemSelector));
            
            // Cache items when not dragging
            if (!this.draggedItem) {
                this.itemCache = items;
            }

            return items;
        }

        /**
         * Get the index of an item
         */
        getItemIndex(item) {
            return this.getItems().indexOf(item);
        }

        /**
         * Check if an element can be dragged
         */
        canDrag(element) {
            // Check if element matches item selector
            const item = element.closest(this.itemSelector);
            if (!item) return false;

            // Check exclude constraints
            if (this.constraints.excludeSelector) {
                if (element.closest(this.constraints.excludeSelector)) {
                    return false;
                }
            }

            // Check if dragging only from handle is required
            if (this.constraints.onlyFromHandle && this.handleSelector) {
                if (!element.closest(this.handleSelector) && 
                    !element.matches(this.handleSelector)) {
                    return false;
                }
            }

            // Check custom constraint function
            if (this.constraints.canDrag && typeof this.constraints.canDrag === 'function') {
                return this.constraints.canDrag(item, element);
            }

            return true;
        }

        /**
         * Handle drag start event
         */
        handleDragStart(e) {
            // Check if another sorter is already handling a drag
            // This prevents conflicts in nested scenarios
            if (globalDragState.activeSorter && globalDragState.activeSorter !== this) {
                // Check if this is a nested drag (child dragging while parent is dragging)
                const draggedElement = e.target.closest(this.itemSelector);
                const parentDragElement = globalDragState.draggedElement;
                
                if (parentDragElement && draggedElement && parentDragElement.contains(draggedElement)) {
                    // This is a nested drag - check if we should allow it
                    if (this.dragType === 'item' && globalDragState.dragType === 'group') {
                        // Allow item drags within a dragging group
                        // But we need to prevent the parent drag from interfering
                        e.stopPropagation();
                    } else {
                        // Prevent nested drags of same type
                        e.preventDefault();
                        e.stopPropagation();
                        return false;
                    }
                } else {
                    // Different drag contexts - allow
                    e.stopPropagation();
                }
            }

            if (!this.canDrag(e.target)) {
                e.preventDefault();
                return false;
            }

            const item = e.target.closest(this.itemSelector);
            if (!item) {
                // Not our item - let other handlers process it
                return;
            }

            // Set as active sorter
            globalDragState.activeSorter = this;
            globalDragState.dragType = this.dragType;
            globalDragState.draggedElement = item;

            this.draggedItem = item;
            this.draggedIndex = this.getItemIndex(item);

            // Clear cache during drag
            this.itemCache = null;

            // Mark item as dragging
            item.classList.add(this.draggingClass);
            item.setAttribute('draggable', 'true');

            // Set drag data
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', String(this.draggedIndex));
            e.dataTransfer.setData('application/json', JSON.stringify({
                index: this.draggedIndex,
                type: 'drag-drop-sorter',
                dragType: this.dragType,
                sorterId: this.container.id || 'default'
            }));

            // Stop propagation to prevent parent handlers from interfering
            e.stopPropagation();

            // Call custom drag start callback
            if (this.constraints.onDragStart) {
                this.constraints.onDragStart(item, this.draggedIndex, e);
            }
        }

        /**
         * Handle drag over event
         */
        handleDragOver(e) {
            // Only handle if we're the active sorter
            if (globalDragState.activeSorter !== this || !this.draggedItem) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'move';

            // Find target item
            const targetItem = e.target.closest(this.itemSelector);
            
            if (!targetItem || targetItem === this.draggedItem) {
                this.hideDropIndicator();
                return;
            }

            // Determine position (before or after)
            const position = this.getDropPosition(e, targetItem);
            
            // Show drop indicator
            this.showDropIndicator(targetItem, position);
        }

        /**
         * Get drop position (before or after target)
         */
        getDropPosition(e, targetItem) {
            const rect = targetItem.getBoundingClientRect();
            
            if (this.axis === 'horizontal') {
                const mouseX = e.clientX;
                const centerX = rect.left + rect.width / 2;
                return mouseX < centerX ? 'before' : 'after';
            } else {
                const mouseY = e.clientY;
                const centerY = rect.top + rect.height / 2;
                return mouseY < centerY ? 'before' : 'after';
            }
        }

        /**
         * Handle drag leave event
         */
        handleDragLeave(e) {
            // Only hide indicator if we're actually leaving the container
            if (!this.container.contains(e.relatedTarget)) {
                this.hideDropIndicator();
            }
        }

        /**
         * Handle drop event
         */
        handleDrop(e) {
            // Only handle if we're the active sorter
            if (globalDragState.activeSorter !== this || !this.draggedItem) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            const targetItem = e.target.closest(this.itemSelector);
            if (!targetItem || targetItem === this.draggedItem) {
                this.hideDropIndicator();
                return;
            }

            // Recalculate indices in case DOM changed during drag
            const currentItems = this.getItems();
            const draggedIndex = currentItems.indexOf(this.draggedItem);
            const targetIndex = currentItems.indexOf(targetItem);

            if (draggedIndex === -1 || targetIndex === -1) {
                // Item was removed from DOM during drag
                this.hideDropIndicator();
                return;
            }

            const position = this.getDropPosition(e, targetItem);
            const insertBefore = position === 'before';

            // Calculate final target index
            let finalIndex = targetIndex;
            if (insertBefore) {
                // If dragging forward, adjust for removal
                if (draggedIndex < targetIndex) {
                    finalIndex = targetIndex - 1;
                }
            } else {
                // If dragging backward, adjust for removal
                if (draggedIndex > targetIndex) {
                    finalIndex = targetIndex + 1;
                } else {
                    finalIndex = targetIndex + 1;
                }
            }

            // Call reorder callback
            this.onReorder(draggedIndex, finalIndex, insertBefore, {
                draggedItem: this.draggedItem,
                targetItem: targetItem,
                originalIndex: draggedIndex,
                newIndex: finalIndex
            });

            this.hideDropIndicator();
        }

        /**
         * Handle drag end event
         */
        handleDragEnd(e) {
            // Only handle if we're the active sorter
            if (globalDragState.activeSorter !== this) {
                return;
            }

            if (this.draggedItem) {
                this.draggedItem.classList.remove(this.draggingClass);
                this.draggedItem = null;
            }

            this.draggedIndex = null;
            this.hideDropIndicator();

            // Remove drag-over classes from all items
            this.getItems().forEach(item => {
                item.classList.remove(this.dragOverClass);
            });

            // Clear global state
            globalDragState.activeSorter = null;
            globalDragState.dragType = null;
            globalDragState.draggedElement = null;

            // Clear cache and refresh items
            this.itemCache = null;
            this.setupDraggableItems();

            // Call custom drag end callback
            if (this.constraints.onDragEnd) {
                this.constraints.onDragEnd(e);
            }
        }

        /**
         * Create drop indicator element
         */
        createDropIndicator() {
            this.dropIndicator = document.createElement('div');
            this.dropIndicator.className = this.dropIndicatorClass;
            this.dropIndicator.style.position = 'absolute';
            this.dropIndicator.style.pointerEvents = 'none';
            this.dropIndicator.style.zIndex = '1000';
            
            if (this.axis === 'horizontal') {
                this.dropIndicator.style.width = '3px';
                this.dropIndicator.style.height = '100%';
                this.dropIndicator.style.background = 'var(--mint-500)';
            } else {
                this.dropIndicator.style.height = '3px';
                this.dropIndicator.style.width = '100%';
                this.dropIndicator.style.background = 'var(--mint-500)';
            }
            
            this.dropIndicator.style.display = 'none';
            this.container.appendChild(this.dropIndicator);
        }

        /**
         * Show drop indicator at target position
         */
        showDropIndicator(targetItem, position) {
            if (!this.dropIndicator) return;

            const containerRect = this.container.getBoundingClientRect();
            const targetRect = targetItem.getBoundingClientRect();
            
            // Ensure container has relative positioning
            const containerStyle = window.getComputedStyle(this.container);
            if (containerStyle.position === 'static') {
                this.container.style.position = 'relative';
            }

            if (this.axis === 'horizontal') {
                const x = position === 'before' 
                    ? targetRect.left - containerRect.left
                    : targetRect.right - containerRect.left;
                this.dropIndicator.style.left = x + 'px';
                this.dropIndicator.style.top = '0';
            } else {
                const y = position === 'before'
                    ? targetRect.top - containerRect.top
                    : targetRect.bottom - containerRect.top;
                this.dropIndicator.style.top = y + 'px';
                this.dropIndicator.style.left = '0';
            }

            this.dropIndicator.style.display = 'block';
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
         * Update items (call after DOM changes)
         * Useful when you manually manipulate DOM and want to refresh the sorter
         */
        update() {
            // Clear cache
            this.itemCache = null;

            // Remove drag-over classes
            this.getItems().forEach(item => {
                item.classList.remove(this.dragOverClass);
            });

            // Update draggable attributes
            this.setupDraggableItems();

            // Call DOM change handler
            this.handleDOMChange();
        }

        /**
         * Force refresh - useful after major DOM manipulations
         */
        refresh() {
            this.update();
        }
    }

    /**
     * NestedDragDropSorter - For nested drag-and-drop (e.g., groups containing items)
     * Handles both group-level and item-level dragging with proper conflict resolution
     */
    class NestedDragDropSorter {
        constructor(options) {
            this.container = options.container;
            this.groupSelector = options.groupSelector;
            this.itemSelector = options.itemSelector;
            this.onGroupReorder = options.onGroupReorder || function() {};
            this.onItemReorder = options.onItemReorder || function() {};
            
            this.groupSorter = null;
            this.itemSorters = new Map();
            this.isInitialized = false;
            this.groupObserver = null;
            this.itemObserver = null;
        }

        init() {
            if (this.isInitialized) return;

            // Set up group-level sorting with 'group' drag type
            this.groupSorter = new DragDropSorter({
                container: this.container,
                itemSelector: this.groupSelector,
                onReorder: (fromIndex, toIndex, insertBefore, context) => {
                    this.onGroupReorder(fromIndex, toIndex, insertBefore, context);
                },
                constraints: {
                    onlyFromHandle: true,
                    handleSelector: '.group-drag-handle, .group-header',
                    nestedDragType: 'group'
                },
                observeDOM: true
            });
            this.groupSorter.init();

            // Set up item-level sorting within each group
            this.setupItemSorters();

            // Watch for group changes
            this.setupGroupObserver();

            this.isInitialized = true;
        }

        setupItemSorters() {
            // Clean up old item sorters for removed groups
            const currentGroups = Array.from(this.container.querySelectorAll(this.groupSelector));
            const currentGroupSet = new Set(currentGroups);
            
            // Remove sorters for groups that no longer exist
            this.itemSorters.forEach((sorter, group) => {
                if (!currentGroupSet.has(group)) {
                    sorter.destroy();
                    this.itemSorters.delete(group);
                }
            });
            
            // Set up new item sorters
            currentGroups.forEach((group, groupIndex) => {
                // Skip if already has a sorter
                if (this.itemSorters.has(group)) {
                    return;
                }

                const itemContainer = group.querySelector('.group-collections, .group-items, .drop-zone') || group;
                
                const itemSorter = new DragDropSorter({
                    container: itemContainer,
                    itemSelector: this.itemSelector,
                    onReorder: (fromIndex, toIndex, insertBefore, context) => {
                        // Find current group index (may have changed)
                        const currentGroups = Array.from(this.container.querySelectorAll(this.groupSelector));
                        const currentGroupIndex = currentGroups.indexOf(group);
                        if (currentGroupIndex !== -1) {
                            this.onItemReorder(currentGroupIndex, fromIndex, toIndex, insertBefore, context);
                        }
                    },
                    constraints: {
                        nestedDragType: 'item'
                    },
                    observeDOM: true
                });
                itemSorter.init();
                
                this.itemSorters.set(group, itemSorter);
            });
        }

        setupGroupObserver() {
            if (!window.MutationObserver) return;

            this.groupObserver = new MutationObserver(() => {
                // Re-setup item sorters when groups change
                if (!globalDragState.activeSorter || globalDragState.activeSorter === this.groupSorter) {
                    // Only update if not dragging items
                    this.setupItemSorters();
                }
            });

            this.groupObserver.observe(this.container, {
                childList: true,
                subtree: false // Only watch direct children (groups)
            });
        }

        destroy() {
            if (this.groupSorter) {
                this.groupSorter.destroy();
            }
            
            this.itemSorters.forEach(sorter => sorter.destroy());
            this.itemSorters.clear();

            if (this.groupObserver) {
                this.groupObserver.disconnect();
            }

            this.isInitialized = false;
        }

        update() {
            if (this.groupSorter) {
                this.groupSorter.update();
            }
            
            this.itemSorters.forEach(sorter => sorter.update());
            
            // Re-setup item sorters if groups changed
            this.setupItemSorters();
        }

        refresh() {
            this.update();
        }
    }

    // Export to global scope
    window.DragDropSorter = DragDropSorter;
    window.NestedDragDropSorter = NestedDragDropSorter;

})();

