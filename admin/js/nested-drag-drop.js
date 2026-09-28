/**
 * Nested Drag and Drop for Content Groups
 * 
 * Handles:
 * 1. Reordering groups
 * 2. Reordering collections within a group
 * 3. Moving collections between groups
 * 
 * Usage:
 *   const manager = new NestedDragDropManager({
 *     container: document.getElementById('collections-container'),
 *     groupSelector: '.collection-group',
 *     groupHeaderSelector: '.group-header',
 *     collectionSelector: '.collection-card',
 *     collectionContainerSelector: '.group-collections, .ungrouped-collections',
 *     onGroupReorder: (fromIndex, toIndex) => { ... },
 *     onCollectionReorder: (collectionSlug, fromGroupId, toGroupId, toIndex) => { ... },
 *     onCollectionMove: (collectionSlug, fromGroupId, toGroupId, toIndex) => { ... }
 *   });
 *   manager.init();
 */

(function() {
    'use strict';

    class NestedDragDropManager {
        constructor(options) {
            // Required
            this.container = options.container;
            this.groupSelector = options.groupSelector;
            this.groupHeaderSelector = options.groupHeaderSelector;
            this.collectionSelector = options.collectionSelector;
            this.collectionContainerSelector = options.collectionContainerSelector;
            
            // Callbacks
            this.onGroupReorder = options.onGroupReorder || function() {};
            this.onCollectionReorder = options.onCollectionReorder || function() {};
            this.onCollectionMove = options.onCollectionMove || function() {};
            
            // Internal state
            this.groupSorter = null;
            this.collectionSorters = new Map(); // Map<groupElement, SimpleDragDrop>
            this.isInitialized = false;
            this.currentDragType = null; // 'group' or 'collection'
            this.draggedCollection = null;
            this.draggedCollectionGroupId = null;
            
            // Store event listener references for cleanup
            this.groupDragStartHandler = null;
            this.groupDragEndHandler = null;
            this.groupDragLeaveHandler = null;
            this.documentDragOverHandler = null;
        }

        /**
         * Normalize group id — empty/null → ungrouped
         */
        normalizeGroupId(groupId) {
            if (groupId === null || groupId === undefined || groupId === '') {
                return 'ungrouped';
            }
            return String(groupId);
        }

        findGroupElement(groupId) {
            const normalized = this.normalizeGroupId(groupId);
            return this.container.querySelector(`${this.groupSelector}[data-group-id="${normalized}"]`);
        }

        expandGroupIfCollapsed(groupElement) {
            if (!groupElement || !groupElement.classList.contains('collapsed')) {
                return;
            }
            groupElement.classList.remove('collapsed');
            const groupId = groupElement.dataset.groupId;
            if (groupId) {
                localStorage.setItem(`group-collapsed-${groupId}`, 'false');
            }
            const collapseBtn = groupElement.querySelector('.group-collapse-btn');
            if (collapseBtn) {
                collapseBtn.title = 'Collapse Group';
                collapseBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg>';
            }
        }

        init() {
            if (this.isInitialized) {
                console.warn('NestedDragDropManager already initialized');
                return;
            }

            if (!this.container) {
                console.error('NestedDragDropManager: container is required');
                return;
            }

            // Set up group reordering
            this.setupGroupReordering();

            // Set up collection reordering for each group
            this.setupCollectionReordering();

            this.isInitialized = true;
        }

        /**
         * Set up group reordering
         */
        setupGroupReordering() {
            // Destroy existing if any
            if (this.groupSorter) {
                this.groupSorter.destroy();
            }
            
            // Remove old event listeners if they exist
            if (this.groupDragStartHandler) {
                this.container.removeEventListener('dragstart', this.groupDragStartHandler, true);
            }
            if (this.groupDragEndHandler) {
                this.container.removeEventListener('dragend', this.groupDragEndHandler, false);
                document.removeEventListener('dragend', this.groupDragEndHandler, false);
            }
            if (this.groupDragLeaveHandler) {
                document.removeEventListener('dragleave', this.groupDragLeaveHandler, false);
            }
            if (this.documentDragOverHandler) {
                document.removeEventListener('dragover', this.documentDragOverHandler, true);
            }

            // Track group drags at manager level - ONLY from headers, not collection cards
            // Use capture phase with HIGH priority (runs FIRST) so we can detect group drags
            // before SimpleDragDrop processes them
            this.groupDragStartHandler = (e) => {
                // CRITICAL: Check if this is a collection card FIRST - if so, ignore completely
                const card = e.target.closest(this.collectionSelector);
                if (card) {
                    // This is a collection drag, don't process as group drag
                    // But make sure we clear any previous group drag state
                    if (this.currentDragType === 'group') {
                        this.currentDragType = null;
                    }
                    return;
                }
                
                // Check if this is a group being dragged (either from header or the group itself)
                const group = e.target.closest(this.groupSelector);
                if (group) {
                    // Double-check: make sure we're not inside a collection card
                    const collectionCard = group.closest(this.collectionSelector);
                    if (!collectionCard) {
                        // Check if dragging from header OR if the group itself is draggable
                        const header = e.target.closest(this.groupHeaderSelector);
                        if (header || (group.draggable === true || group.getAttribute('draggable') === 'true')) {
                            // Clear any previous drag state
                            this.currentDragType = 'group';
                        }
                    }
                } else {
                    // Not a group drag - clear group drag state if it was set
                    if (this.currentDragType === 'group') {
                        this.currentDragType = null;
                    }
                }
            };
            // Use capture phase with HIGH priority (runs BEFORE SimpleDragDrop)
            // This allows us to detect group drags before SimpleDragDrop stops propagation
            this.container.addEventListener('dragstart', this.groupDragStartHandler, true);

            // Clean up group drags - always clean up if it was a group drag
            this.groupDragEndHandler = (e) => {
                // Always clean up if this was a group drag, regardless of target
                if (this.currentDragType === 'group') {
                    // Remove dragging class from all groups
                    this.getGroups().forEach(g => {
                        g.classList.remove('dragging');
                        g.style.opacity = '';
                    });
                    // Also reset the group sorter's state if it exists
                    if (this.groupSorter && this.groupSorter.draggedItem) {
                        this.groupSorter.draggedItem = null;
                        this.groupSorter.draggedIndex = null;
                    }
                    this.currentDragType = null;
                }
            };
            
            // Listen on both container and document to ensure cleanup always happens
            this.container.addEventListener('dragend', this.groupDragEndHandler, false);
            document.addEventListener('dragend', this.groupDragEndHandler, false);
            
            // Also listen for dragleave on document to catch cancelled drags
            this.groupDragLeaveHandler = (e) => {
                // If leaving the document and it was a group drag, clean up
                if (!e.relatedTarget && this.currentDragType === 'group') {
                    this.groupDragEndHandler(e);
                }
            };
            document.addEventListener('dragleave', this.groupDragLeaveHandler, false);

            // Create sorter for groups (only draggable from header)
            this.groupSorter = new SimpleDragDrop({
                container: this.container,
                itemSelector: this.groupSelector + ':not(.ungrouped)',
                handleSelector: '.group-header',
                dropIndicatorClass: 'group-drop-indicator',
                onReorder: (fromIndex, toIndex) => {
                    this.onGroupReorder(fromIndex, toIndex);
                }
            });
            this.groupSorter.init();
            
            // Add document-level dragover handler to keep group drags alive
            // This ensures the drag isn't cancelled even if mouse leaves the container
            this.documentDragOverHandler = (e) => {
                // Only handle if this is a group drag
                if (this.currentDragType === 'group') {
                    // Check if there's a dragging group
                    const draggingGroup = document.querySelector(this.groupSelector + '.' + this.groupSorter.draggingClass);
                    if (draggingGroup) {
                        // Always prevent default to keep the drag alive
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';
                    }
                }
            };
            document.addEventListener('dragover', this.documentDragOverHandler, true);
        }

        /**
         * Set up collection reordering for all groups
         */
        setupCollectionReordering() {
            // Clean up old sorters for removed groups
            const currentGroups = this.getGroups();
            const currentGroupSet = new Set(currentGroups);

            // Remove sorters for groups that no longer exist
            this.collectionSorters.forEach((sorter, groupElement) => {
                if (!currentGroupSet.has(groupElement)) {
                    sorter.destroy();
                    this.collectionSorters.delete(groupElement);
                }
            });

            // Set up sorters for each group
            currentGroups.forEach(groupElement => {
                // Skip if already has a sorter
                if (this.collectionSorters.has(groupElement)) {
                    // Just update it
                    this.collectionSorters.get(groupElement).update();
                    return;
                }

                // Find the collection container (grid) within this group
                const collectionContainer = groupElement.querySelector(this.collectionContainerSelector);
                if (!collectionContainer) {
                    return;
                }

                // Get group ID
                const groupId = this.normalizeGroupId(groupElement.dataset.groupId);

                // Create sorter for collections in this group
                const sorter = new SimpleDragDrop({
                    container: collectionContainer,
                    itemSelector: this.collectionSelector,
                    onReorder: (fromIndex, toIndex) => {
                        const collections = this.getCollectionsInGroup(groupElement);
                        if (fromIndex >= 0 && fromIndex < collections.length) {
                            const collectionSlug = collections[fromIndex].dataset.collectionSlug;
                            this.onCollectionReorder(collectionSlug, groupId, groupId, toIndex);
                        }
                    }
                });
                const itemCount = collectionContainer.querySelectorAll(this.collectionSelector).length;
                if (itemCount > 0) {
                    sorter.init();
                } else {
                    console.warn('No items found for SimpleDragDrop in group:', groupId);
                }

                // Store reference
                this.collectionSorters.set(groupElement, sorter);

                // Set up cross-group dropping
                this.setupCrossGroupDropping(groupElement, collectionContainer, groupId);
            });
            
            // Set up global drag tracking (once, not per group)
            if (!this.globalDragTrackingSetup) {
                this.setupGlobalDragTracking();
                this.globalDragTrackingSetup = true;
            }
        }
        
        /**
         * Set up global drag tracking (once for all collections)
         */
        setupGlobalDragTracking() {
            // Track collection drags at document level - use capture phase to run FIRST
            document.addEventListener('dragstart', (e) => {
                // Make sure this is NOT a group header drag
                const header = e.target.closest(this.groupHeaderSelector);
                if (header && !e.target.closest(this.collectionSelector)) {
                    // Check if header is draggable (group drag) vs not draggable (ungrouped)
                    if (header.draggable === true || header.getAttribute('draggable') === 'true') {
                        return; // This is a group drag, not a collection drag
                    }
                }
                
                const card = e.target.closest(this.collectionSelector);
                if (card) {
                    // Set collection drag type FIRST (before group handler can override)
                    this.currentDragType = 'collection';
                    this.draggedCollection = card;
                    this.draggedCollectionGroupId = this.normalizeGroupId(card.dataset.groupId);
                }
            }, true); // Capture phase - runs FIRST

            // Clean up on dragend - always fire to ensure cleanup
            // Use both capture and bubble phases to ensure we catch it
            const cleanupDrag = (e) => {
                const card = e.target.closest(this.collectionSelector);
                if (card) {
                    // Always clean up dragging state
                    card.classList.remove('dragging');
                    card.style.opacity = '';
                    
                    // Clear manager state if this was our drag
                    if (this.draggedCollection === card) {
                        this.currentDragType = null;
                        this.draggedCollection = null;
                        this.draggedCollectionGroupId = null;
                    }
                }
                
                // Also clean up any drag-over classes
                document.querySelectorAll('.drag-over').forEach(el => {
                    el.classList.remove('drag-over');
                });
                this.hideCrossGroupIndicator();
            };
            
            document.addEventListener('dragend', cleanupDrag, true);
            document.addEventListener('dragend', cleanupDrag, false);
            
            // Also listen for drop to ensure cleanup
            document.addEventListener('drop', cleanupDrag, true);
        }

        /**
         * Set up cross-group dropping (moving collections between groups)
         */
        setupCrossGroupDropping(groupElement, collectionContainer, groupId) {
            const normalizedGroupId = this.normalizeGroupId(groupId);

            // Listen for drops from other groups - use BUBBLING phase so SimpleDragDrop (capture) handles same-group first
            collectionContainer.addEventListener('dragover', (e) => {
                // Only handle if we're dragging a collection (not a group)
                if (this.currentDragType === 'group') {
                    return;
                }

                // Check if this is a collection being dragged (use manager state)
                if (this.currentDragType !== 'collection' || !this.draggedCollection) {
                    return;
                }

                this.expandGroupIfCollapsed(groupElement);

                // Check if dragged collection is from a different group
                const draggedGroupId = this.normalizeGroupId(this.draggedCollectionGroupId);
                if (draggedGroupId === normalizedGroupId) {
                    // Same group - let the SimpleDragDrop handle it (don't prevent default, don't stop)
                    return;
                }

                // Different group - allow drop
                e.preventDefault();
                e.stopPropagation();
                e.dataTransfer.dropEffect = 'move';

                // Hide any SimpleDragDrop indicators from both source and target groups
                const sourceGroup = this.findGroupElement(draggedGroupId);
                if (sourceGroup) {
                    const sourceContainer = sourceGroup.querySelector(this.collectionContainerSelector);
                    if (sourceContainer) {
                        const sourceSorter = this.collectionSorters.get(sourceGroup);
                        if (sourceSorter && sourceSorter.dropIndicator) {
                            sourceSorter.hideDropIndicator();
                        }
                    }
                }
                
                // Hide target group's SimpleDragDrop indicator too
                const targetGroup = collectionContainer.closest(this.groupSelector);
                if (targetGroup) {
                    const targetSorter = this.collectionSorters.get(targetGroup);
                    if (targetSorter && targetSorter.dropIndicator) {
                        targetSorter.hideDropIndicator();
                    }
                }

                // Show visual feedback
                collectionContainer.classList.add('drag-over');

                // Show drop indicator
                this.showCrossGroupIndicator(e, collectionContainer, normalizedGroupId);
            }, false); // Use bubbling phase so SimpleDragDrop can handle same-group first

            collectionContainer.addEventListener('dragleave', (e) => {
                if (!collectionContainer.contains(e.relatedTarget)) {
                    collectionContainer.classList.remove('drag-over');
                    this.hideCrossGroupIndicator();
                }
            }, false);

            collectionContainer.addEventListener('drop', (e) => {
                // Only handle if we're dragging a collection (not a group)
                if (this.currentDragType === 'group') {
                    return;
                }

                // Use manager state
                if (this.currentDragType !== 'collection' || !this.draggedCollection) {
                    return;
                }

                const draggedSlug = this.draggedCollection.dataset.collectionSlug;
                const draggedGroupId = this.normalizeGroupId(this.draggedCollectionGroupId);

                // Check if moving to different group
                if (draggedGroupId === normalizedGroupId) {
                    // Same group - let SimpleDragDrop handle it (don't prevent default, don't stop)
                    // SimpleDragDrop uses capture phase, so it will handle this first
                    return;
                }

                e.preventDefault();
                e.stopPropagation();
                collectionContainer.classList.remove('drag-over');
                this.hideCrossGroupIndicator();

                // Calculate target index
                const collections = this.getCollectionsInGroup(groupElement);
                const targetIndex = this.getDropIndex(e, collectionContainer, collections);

                // Call move callback
                this.onCollectionMove(draggedSlug, draggedGroupId, normalizedGroupId, targetIndex);
            }, false); // Use bubbling phase so SimpleDragDrop can handle same-group first
        }

        /**
         * Get drop index for cross-group move
         */
        getDropIndex(e, container, collections) {
            if (collections.length === 0) {
                return 0;
            }

            // Find closest collection
            let closestIndex = collections.length;
            let minDistance = Infinity;

            collections.forEach((card, index) => {
                const rect = card.getBoundingClientRect();
                const centerY = rect.top + rect.height / 2;
                const distance = Math.abs(e.clientY - centerY);

                if (distance < minDistance) {
                    minDistance = distance;
                    const insertBefore = e.clientY < centerY;
                    closestIndex = insertBefore ? index : index + 1;
                }
            });

            return closestIndex;
        }

        /**
         * Show drop indicator for cross-group move
         */
        showCrossGroupIndicator(e, container, groupId) {
            this.hideCrossGroupIndicator();

            const groupElement = container.closest(this.groupSelector);
            const collections = this.getCollectionsInGroup(groupElement);

            if (collections.length === 0) {
                container.classList.add('drag-over');
                return;
            }

            // Detect if container is a grid
            const containerStyle = window.getComputedStyle(container);
            const isGrid = containerStyle.display === 'grid' || 
                          containerStyle.display === 'inline-grid' ||
                          container.classList.contains('grid');

            // Find closest collection
            let closestCard = null;
            let minDistance = Infinity;
            let insertBefore = false;
            let isVertical = false;

            collections.forEach(card => {
                const rect = card.getBoundingClientRect();
                
                if (isGrid) {
                    // Grid layout - ALWAYS use vertical indicator for collection cards
                    const centerX = rect.left + rect.width / 2;
                    const distX = Math.abs(e.clientX - centerX);
                    const distance = distX;

                    if (distance < minDistance) {
                        minDistance = distance;
                        closestCard = card;
                        // Always vertical for collection cards in grid
                        isVertical = true;
                        insertBefore = e.clientX < centerX;
                    }
                } else {
                    // List layout - only check Y distance
                    const centerY = rect.top + rect.height / 2;
                    const distance = Math.abs(e.clientY - centerY);

                    if (distance < minDistance) {
                        minDistance = distance;
                        closestCard = card;
                        isVertical = false;
                        insertBefore = e.clientY < centerY;
                    }
                }
            });

            if (!closestCard) return;

            // Create indicator
            const indicator = document.createElement('div');
            indicator.className = 'cross-group-drop-indicator';
            
            const containerRect = container.getBoundingClientRect();
            const cardRect = closestCard.getBoundingClientRect();

            if (isVertical) {
                // Vertical indicator (between columns in grid)
                const x = insertBefore 
                    ? cardRect.left - containerRect.left
                    : cardRect.right - containerRect.left;
                indicator.style.cssText = `
                    position: absolute;
                    left: ${x}px;
                    top: ${cardRect.top - containerRect.top}px;
                    width: 3px;
                    height: ${cardRect.height}px;
                    background: var(--mint-500);
                    pointer-events: none;
                    z-index: 1000;
                    box-shadow: 0 0 8px rgba(59, 130, 246, 0.6);
                `;
            } else {
                // Horizontal indicator (between rows)
                const y = insertBefore 
                    ? cardRect.top - containerRect.top - 1
                    : cardRect.bottom - containerRect.top + 1;
                indicator.style.cssText = `
                    position: absolute;
                    left: 0;
                    top: ${y}px;
                    width: 100%;
                    height: 3px;
                    background: var(--mint-500);
                    pointer-events: none;
                    z-index: 1000;
                    box-shadow: 0 0 8px rgba(59, 130, 246, 0.6);
                `;
            }

            // Ensure container has relative positioning
            if (containerStyle.position === 'static') {
                container.style.position = 'relative';
            }

            container.appendChild(indicator);
        }

        /**
         * Hide cross-group drop indicator
         */
        hideCrossGroupIndicator() {
            const indicator = document.querySelector('.cross-group-drop-indicator');
            if (indicator) {
                indicator.remove();
            }
        }

        /**
         * Get all groups
         */
        getGroups() {
            return Array.from(this.container.querySelectorAll(this.groupSelector));
        }

        /**
         * Get all collections in a group
         */
        getCollectionsInGroup(groupElement) {
            const container = groupElement.querySelector(this.collectionContainerSelector);
            if (!container) {
                return [];
            }
            return Array.from(container.querySelectorAll(this.collectionSelector));
        }

        /**
         * Update - call this when DOM changes (groups/collections added/removed)
         */
        update() {
            // Clear any stuck drag state first
            this.currentDragType = null;
            this.draggedCollection = null;
            this.draggedCollectionGroupId = null;
            
            // Re-setup group reordering to ensure fresh state
            this.setupGroupReordering();

            // Re-setup collection reordering (will clean up old and create new)
            this.setupCollectionReordering();

            // Update existing collection sorters
            this.collectionSorters.forEach(sorter => {
                sorter.update();
            });
        }

        /**
         * Destroy - clean up all event listeners
         */
        destroy() {
            if (!this.isInitialized) return;

            // Remove event listeners
            if (this.groupDragStartHandler) {
                this.container.removeEventListener('dragstart', this.groupDragStartHandler, true);
                this.groupDragStartHandler = null;
            }
            if (this.groupDragEndHandler) {
                this.container.removeEventListener('dragend', this.groupDragEndHandler, false);
                document.removeEventListener('dragend', this.groupDragEndHandler, false);
                this.groupDragEndHandler = null;
            }
            if (this.groupDragLeaveHandler) {
                document.removeEventListener('dragleave', this.groupDragLeaveHandler, false);
                this.groupDragLeaveHandler = null;
            }
            if (this.documentDragOverHandler) {
                document.removeEventListener('dragover', this.documentDragOverHandler, true);
                this.documentDragOverHandler = null;
            }

            if (this.groupSorter) {
                this.groupSorter.destroy();
                this.groupSorter = null;
            }

            this.collectionSorters.forEach(sorter => {
                sorter.destroy();
            });
            this.collectionSorters.clear();

            this.isInitialized = false;
        }
    }

    // Export to global scope
    window.NestedDragDropManager = NestedDragDropManager;

})();

