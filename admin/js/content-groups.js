/**
 * Collection Groups Management
 * Handles drag-and-drop, group creation, and group management
 */

(function() {
    'use strict';
    
    let groups = [];
    let collections = [];
    let dragDropManager = null;
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        // Load initial data from page
        const dataScript = document.getElementById('content-data');
        if (dataScript) {
            try {
                let dataText = dataScript.textContent || dataScript.innerText || '';
                // Remove any leading/trailing whitespace
                dataText = dataText.trim();
                // If it starts with <?php, we need to get the actual data differently
                if (dataText.includes('<?php')) {
                    // Fallback: try to get data from window or from the PHP-rendered JSON
                    console.warn('PHP tag found in script, using fallback data loading');
                    // The PHP should have already rendered it, so we just need to get the JSON
                    dataText = dataScript.innerHTML.trim();
                }
                const data = JSON.parse(dataText);
                collections = data.collections || [];
                groups = data.groups || [];
                renderGroupsAndCollections();
            } catch (e) {
                console.error('Failed to parse content data:', e, 'Script content:', dataScript?.textContent?.substring(0, 100));
                // Fallback: render empty state
                renderGroupsAndCollections();
            }
        } else {
            // No data script, render empty state
            renderGroupsAndCollections();
        }
        
        // Set up event listeners
        setupEventListeners();
        
        // Set up drag and drop
        setupDragDrop();
    }
    
    function setupEventListeners() {
        // New Group button
        const newGroupBtn = document.getElementById('new-group-btn');
        if (newGroupBtn) {
            newGroupBtn.addEventListener('click', showCreateGroupModal);
        }
        
        // Close modal buttons
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-backdrop')) {
                closeGroupModal();
            }
            if (e.target.classList.contains('modal-close') || e.target.closest('.modal-close')) {
                closeGroupModal();
            }
        });
        
        // Save group form
        const groupForm = document.getElementById('group-form');
        if (groupForm) {
            groupForm.addEventListener('submit', handleGroupFormSubmit);
        }
    }
    
    function setupDragDrop() {
        const container = document.getElementById('collections-container');
        if (!container) return;
        
        // Destroy existing manager if any
        if (dragDropManager) {
            dragDropManager.destroy();
        }
        
        // Create new manager (HTML5 drag; excludes ungrouped from group reorder)
        dragDropManager = new NestedDragDropManager({
            container: container,
            groupSelector: '.collection-group',
            groupHeaderSelector: '.group-header',
            collectionSelector: '.collection-card',
            collectionContainerSelector: '.group-collections, .ungrouped-collections',
            
            onGroupReorder: (fromIndex, toIndex) => {
                const reorderableGroups = groups.filter(g => g.id !== 'ungrouped');
                const draggedGroup = reorderableGroups[fromIndex];
                
                if (draggedGroup) {
                    reorderableGroups.splice(fromIndex, 1);
                    reorderableGroups.splice(toIndex, 0, draggedGroup);
                    
                    const ungroupedGroup = groups.find(g => g.id === 'ungrouped');
                    groups = [...reorderableGroups];
                    if (ungroupedGroup) {
                        groups.push(ungroupedGroup);
                    }
                    
                    saveGroups();
                }
            },
            
            onCollectionReorder: (collectionSlug, fromGroupId, toGroupId, toIndex) => {
                const groupId = toGroupId || 'ungrouped';
                let group = groups.find(g => g.id === groupId);
                if (!group && groupId === 'ungrouped') {
                    group = { id: 'ungrouped', name: 'Ungrouped', collections: [] };
                    groups.push(group);
                }
                if (!group) return;
                if (!group.collections) {
                    group.collections = [];
                }

                const fromIndex = group.collections.indexOf(collectionSlug);
                if (fromIndex === -1) {
                    group.collections.splice(toIndex, 0, collectionSlug);
                } else {
                    group.collections.splice(fromIndex, 1);
                    group.collections.splice(toIndex, 0, collectionSlug);
                }

                saveGroups();
            },
            
            onCollectionMove: (collectionSlug, fromGroupId, toGroupId, toIndex) => {
                const normalizedTo = toGroupId || 'ungrouped';

                groups.forEach(group => {
                    if (!group.collections) return;
                    group.collections = group.collections.filter(slug => slug !== collectionSlug);
                });

                if (normalizedTo !== 'ungrouped') {
                    const toGroup = groups.find(g => g.id === normalizedTo);
                    if (toGroup) {
                        if (!toGroup.collections) {
                            toGroup.collections = [];
                        }
                        toGroup.collections.splice(toIndex, 0, collectionSlug);
                    }
                } else {
                    let ungroupedGroup = groups.find(g => g.id === 'ungrouped');
                    if (!ungroupedGroup) {
                        ungroupedGroup = { id: 'ungrouped', name: 'Ungrouped', collections: [] };
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
        dragDropManager.init();
    }
    
    function renderGroupsAndCollections() {
        const container = document.getElementById('collections-container');
        if (!container) {
            console.error('Collections container not found');
            return;
        }
        
        container.innerHTML = '';
        
        // Render groups (skip ungrouped, handled separately)
        groups.forEach(group => {
            if (group.id === 'ungrouped') return;
            const groupSection = createGroupSection(group);
            container.appendChild(groupSection);
        });
        
        // Render ungrouped collections
        // Check if there's an ungrouped group with order
        const ungroupedGroup = groups.find(g => g.id === 'ungrouped');
        let ungroupedCollections = collections.filter(col => {
            return !groups.some(group => 
                group.id !== 'ungrouped' && group.collections && group.collections.includes(col.slug)
            );
        });
        
        // If ungrouped group exists and has order, sort by that order
        if (ungroupedGroup && ungroupedGroup.collections && ungroupedGroup.collections.length > 0) {
            const order = ungroupedGroup.collections;
            ungroupedCollections.sort((a, b) => {
                const indexA = order.indexOf(a.slug);
                const indexB = order.indexOf(b.slug);
                // If not in order, put at end
                if (indexA === -1 && indexB === -1) return 0;
                if (indexA === -1) return 1;
                if (indexB === -1) return -1;
                return indexA - indexB;
            });
        }
        
        // Always show ungrouped section if there are collections or groups
        if (ungroupedCollections.length > 0 || groups.length > 0) {
            const ungroupedSection = createUngroupedSection(ungroupedCollections);
            container.appendChild(ungroupedSection);
        }
        
        // Update drag and drop after render
        if (dragDropManager) {
            dragDropManager.update();
        }
        
        // Re-initialize dropdowns for newly created collection cards
        // The MutationObserver should handle this, but we'll do it explicitly to be sure
        if (window.initDropdowns) {
            setTimeout(() => {
                window.initDropdowns();
            }, 100);
        }
    }
    
    function createGroupSection(group) {
        const section = document.createElement('div');
        section.className = 'collection-group';
        section.dataset.groupId = group.id;
        // Make section draggable (SimpleDragDrop needs the item itself to be draggable)
        // The handleSelector will ensure drag only starts from header
        section.draggable = true;
        section.setAttribute('draggable', 'true');
        
        // Check if group is collapsed (stored in localStorage)
        const isCollapsed = localStorage.getItem(`group-collapsed-${group.id}`) === 'true';
        if (isCollapsed) {
            section.classList.add('collapsed');
        }
        
        // Header contains the drag handle
        const header = document.createElement('div');
        header.className = 'group-header';
        header.style.cursor = 'move'; // Show move cursor
        // Make header draggable - when dragging from header, the browser will drag
        // the header, but SimpleDragDrop will find the group section (item) via closest()
        // and use that for all drag logic
        header.draggable = true;
        header.setAttribute('draggable', 'true');
        
        // Add drag handle for group
        const dragHandleWrapper = document.createElement('div');
        dragHandleWrapper.className = 'group-drag-handle';
        const dragHandle = document.createElement('img');
        dragHandle.src = '/admin/assets/DotsSixVertical.svg';
        dragHandle.alt = 'Drag to reorder group';
        dragHandle.draggable = false;
        dragHandleWrapper.appendChild(dragHandle);
        
        const title = document.createElement('h3');
        title.className = 'group-title';
        title.textContent = group.name || 'Untitled Group';
        
        const actions = document.createElement('div');
        actions.className = 'group-actions';
        
        // Collapse/expand button
        const collapseBtn = document.createElement('button');
        collapseBtn.className = 'btn-icon group-collapse-btn';
        collapseBtn.title = isCollapsed ? 'Expand Group' : 'Collapse Group';
        collapseBtn.innerHTML = isCollapsed 
            ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 15l-6-6-6 6"></path></svg>'
            : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg>';
        collapseBtn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            toggleGroupCollapse(section, group.id);
        };
        
        const editBtn = document.createElement('button');
        editBtn.className = 'btn-icon';
        editBtn.title = 'Edit Group';
        editBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
        editBtn.onclick = () => showEditGroupModal(group);
        
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'btn-icon';
        deleteBtn.title = 'Delete Group';
        deleteBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
        deleteBtn.onclick = () => deleteGroup(group.id);
        
        // Prevent drag from buttons
        [collapseBtn, editBtn, deleteBtn].forEach(btn => {
            btn.addEventListener('dragstart', (e) => {
                e.stopPropagation();
                e.preventDefault();
                return false;
            });
        });
        
        actions.appendChild(collapseBtn);
        actions.appendChild(editBtn);
        actions.appendChild(deleteBtn);
        header.appendChild(dragHandleWrapper);
        header.appendChild(title);
        header.appendChild(actions);
        
        const grid = document.createElement('div');
        grid.className = 'grid group-collections';
        grid.dataset.groupId = group.id;
        
        // Render collections in this group - respect order from group.collections
        let groupCollections = collections.filter(col => 
            group.collections && group.collections.includes(col.slug)
        );
        
        // Sort by the order in group.collections
        if (group.collections && group.collections.length > 0) {
            groupCollections.sort((a, b) => {
                const indexA = group.collections.indexOf(a.slug);
                const indexB = group.collections.indexOf(b.slug);
                if (indexA === -1 && indexB === -1) return 0;
                if (indexA === -1) return 1;
                if (indexB === -1) return -1;
                return indexA - indexB;
            });
        }
        
        if (groupCollections.length === 0) {
            grid.classList.add('empty-drop-zone');
            const emptyMsg = document.createElement('div');
            emptyMsg.className = 'empty-drop-message';
            emptyMsg.textContent = 'Drop collections here';
            grid.appendChild(emptyMsg);
        } else {
            groupCollections.forEach(col => {
                const card = createCollectionCard(col, group.id);
                grid.appendChild(card);
            });
        }
        
        section.appendChild(header);
        section.appendChild(grid);
        
        return section;
    }
    
    function createUngroupedSection(collectionsList) {
        const section = document.createElement('div');
        section.className = 'collection-group ungrouped';
        section.dataset.groupId = 'ungrouped';
        
        // Check if ungrouped is collapsed
        const isCollapsed = localStorage.getItem('group-collapsed-ungrouped') === 'true';
        if (isCollapsed) {
            section.classList.add('collapsed');
        }
        
        const header = document.createElement('div');
        header.className = 'group-header';
        // Note: ungrouped header is NOT draggable (it's always last)
        
        // Add drag handle for ungrouped section (but make it non-draggable since it's always last)
        const dragHandleWrapper = document.createElement('div');
        dragHandleWrapper.className = 'group-drag-handle';
        dragHandleWrapper.style.opacity = '0.3';
        dragHandleWrapper.style.cursor = 'default';
        
        const title = document.createElement('h3');
        title.className = 'group-title';
        title.textContent = 'Ungrouped Collections';
        
        const actions = document.createElement('div');
        actions.className = 'group-actions';
        
        // Collapse/expand button for ungrouped
        const collapseBtn = document.createElement('button');
        collapseBtn.className = 'btn-icon group-collapse-btn';
        collapseBtn.title = isCollapsed ? 'Expand Group' : 'Collapse Group';
        collapseBtn.innerHTML = isCollapsed 
            ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 15l-6-6-6 6"></path></svg>'
            : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg>';
        collapseBtn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            toggleGroupCollapse(section, 'ungrouped');
        };
        
        actions.appendChild(collapseBtn);
        header.appendChild(dragHandleWrapper);
        header.appendChild(title);
        header.appendChild(actions);
        
        const grid = document.createElement('div');
        grid.className = 'grid ungrouped-collections';
        
        collectionsList.forEach(col => {
            const card = createCollectionCard(col, null);
            grid.appendChild(card);
        });
        
        section.appendChild(header);
        section.appendChild(grid);
        
        return section;
    }
    
    function createCollectionCard(collection, groupId) {
        const card = document.createElement('div');
        card.className = 'card draggable collection-card';
        card.dataset.collectionSlug = collection.slug;
        card.draggable = true;
        card.dataset.groupId = groupId || '';
        
        // Make entire card clickable (will be handled by click event)
        card.style.cursor = 'pointer';
        
        const header = document.createElement('div');
        header.className = 'collection-header';
        
        const title = document.createElement('h3');
        title.className = 'title';
        
        const dragHandle = document.createElement('img');
        dragHandle.src = '/admin/assets/DotsSixVertical.svg';
        dragHandle.alt = 'Drag to reorder';
        dragHandle.className = 'drag-handle';
        dragHandle.draggable = false;
        dragHandle.style.cursor = 'grab';
        
        const titleText = document.createTextNode(collection.name || 'Untitled');
        
        title.appendChild(dragHandle);
        title.appendChild(titleText);
        
        const description = document.createElement('div');
        description.className = 'collection-description';
        const descP = document.createElement('p');
        descP.textContent = collection.description || '';
        description.appendChild(descP);
        
        header.appendChild(title);
        header.appendChild(description);
        
        const footer = document.createElement('div');
        footer.className = 'collection-footer';
        
        const entryCount = document.createElement('div');
        entryCount.className = 'entry-count';
        const countBold = document.createElement('b');
        countBold.textContent = collection.entries_count || 0;
        entryCount.appendChild(countBold);
        entryCount.appendChild(document.createTextNode(' entries'));
        
        // Add sub-entries count if available
        const subEntriesCount = collection.sub_entries_count || 0;
        if (subEntriesCount > 0) {
            const subEntriesSpan = document.createElement('span');
            subEntriesSpan.className = 'sub-entries-count';
            subEntriesSpan.textContent = ' +' + subEntriesCount;
            entryCount.appendChild(subEntriesSpan);
        }
        
        // 3-dot menu instead of separate edit/delete buttons - Unified Dropdown Component
        const menuWrapper = document.createElement('div');
        menuWrapper.className = 'dropdown collection-menu-wrapper';
        
        const menuButton = document.createElement('button');
        menuButton.className = 'dropdown-trigger collection-menu-btn';
        menuButton.setAttribute('aria-label', 'Collection options');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.setAttribute('aria-haspopup', 'true');
        menuButton.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1.5"></circle><circle cx="12" cy="12" r="1.5"></circle><circle cx="12" cy="19" r="1.5"></circle></svg>';
        // Let the unified dropdown system handle the click
        // No custom onclick handler needed
        
        // Prevent drag from menu button
        menuButton.addEventListener('dragstart', (e) => {
            e.stopPropagation();
            e.preventDefault();
            return false;
        });
        
        const menuDropdown = document.createElement('div');
        menuDropdown.className = 'dropdown-menu dropdown-menu-right collection-menu-dropdown';
        
        const editItem = document.createElement('a');
        editItem.className = 'dropdown-item collection-menu-item';
        editItem.href = '/admin/index.php?page=collection-editor&collection=' + encodeURIComponent(collection.slug);
        editItem.innerHTML = '<svg class="dropdown-item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg><span class="dropdown-item-label">Edit Collection</span>';
        editItem.onclick = (e) => {
            e.stopPropagation();
            // Let the link navigate normally
        };
        
        const duplicateItem = document.createElement('button');
        duplicateItem.className = 'dropdown-item collection-menu-item';
        duplicateItem.innerHTML = '<svg class="dropdown-item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg><span class="dropdown-item-label">Duplicate Collection</span>';
        duplicateItem.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            // Close dropdown by removing show/active classes
            const dropdown = duplicateItem.closest('.dropdown');
            if (dropdown) {
                const menu = dropdown.querySelector('.dropdown-menu');
                if (menu) {
                    menu.classList.remove('show', 'active');
                    menu.style.display = 'none';
                }
                const trigger = dropdown.querySelector('.dropdown-trigger');
                if (trigger) {
                    trigger.setAttribute('aria-expanded', 'false');
                }
            }
            duplicateCollection(collection);
        };
        
        const deleteItem = document.createElement('button');
        deleteItem.className = 'dropdown-item dropdown-item-danger collection-menu-item collection-menu-item-danger';
        deleteItem.innerHTML = '<svg class="dropdown-item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg><span class="dropdown-item-label">Delete Collection</span>';
        deleteItem.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            // Close dropdown by removing show/active classes
            const dropdown = deleteItem.closest('.dropdown');
            if (dropdown) {
                const menu = dropdown.querySelector('.dropdown-menu');
                if (menu) {
                    menu.classList.remove('show', 'active');
                    menu.style.display = 'none';
                }
                const trigger = dropdown.querySelector('.dropdown-trigger');
                if (trigger) {
                    trigger.setAttribute('aria-expanded', 'false');
                }
            }
            deleteCollection(collection);
        };
        
        menuDropdown.appendChild(editItem);
        menuDropdown.appendChild(duplicateItem);
        menuDropdown.appendChild(deleteItem);
        
        menuWrapper.appendChild(menuButton);
        menuWrapper.appendChild(menuDropdown);
        
        footer.appendChild(entryCount);
        footer.appendChild(menuWrapper);
        
        card.appendChild(header);
        card.appendChild(footer);
        
        // Make card clickable to navigate to entries
        card.addEventListener('click', function(e) {
            // Don't navigate if clicking on menu or drag handle
            if (e.target.closest('.collection-menu-wrapper') || e.target.closest('.drag-handle')) {
                return;
            }
            // Don't navigate if we just dragged
            if (card.classList.contains('just-dragged')) {
                card.classList.remove('just-dragged');
                return;
            }
            window.location.href = '/admin/index.php?page=entries&collection=' + encodeURIComponent(collection.slug);
        });
        
        // Prevent drag from menu
        card.addEventListener('dragstart', function(e) {
            if (e.target.closest('.collection-menu-wrapper')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Mark as just-dragged after drag ends (to prevent click navigation)
        card.addEventListener('dragend', function() {
            this.classList.add('just-dragged');
            setTimeout(() => {
                this.classList.remove('just-dragged');
            }, 100);
        });
        
        return card;
    }
    
    // Removed toggleCollectionMenu and closeAllMenus - now using unified dropdown system
    // The unified dropdown.js handles all dropdown functionality
    
    function duplicateCollection(collection) {
        // Use id if available, otherwise try to find collection by slug
        const collectionId = collection.id;
        if (!collectionId) {
            console.error('Collection data:', collection);
            alert('Error: Collection ID not found. Cannot duplicate collection.');
            return;
        }
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        fetch('/api/content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'duplicate_collection',
                id: collectionId,
                _csrf: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                // Reload page to show new collection
                window.location.reload();
            } else {
                alert('Failed to duplicate collection: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error duplicating collection:', error);
            alert('Error duplicating collection. Please try again.');
        });
    }
    
    function showCreateGroupModal() {
        showGroupModal(null);
    }
    
    function showEditGroupModal(group) {
        showGroupModal(group);
    }
    
    function showGroupModal(group) {
        const modal = document.getElementById('group-modal');
        const form = document.getElementById('group-form');
        const title = document.getElementById('group-modal-title');
        const nameInput = document.getElementById('group-name');
        const submitBtn = document.getElementById('group-submit');
        
        if (!modal || !form || !title || !nameInput) return;
        
        if (group) {
            title.textContent = 'Edit Group';
            nameInput.value = group.name || '';
            submitBtn.textContent = 'Update Group';
            form.dataset.groupId = group.id;
        } else {
            title.textContent = 'New Group';
            nameInput.value = '';
            submitBtn.textContent = 'Create Group';
            delete form.dataset.groupId;
        }
        
        modal.style.display = 'flex';
        nameInput.focus();
    }
    
    function closeGroupModal() {
        const modal = document.getElementById('group-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
    function handleGroupFormSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const nameInput = document.getElementById('group-name');
        const groupId = form.dataset.groupId;
        
        if (!nameInput || !nameInput.value.trim()) {
            alert('Please enter a group name');
            return;
        }
        
        const groupName = nameInput.value.trim();
        
        if (groupId) {
            // Update existing group
            const group = groups.find(g => g.id === groupId);
            if (group) {
                group.name = groupName;
            }
        } else {
            // Create new group
            const newGroup = {
                id: 'group-' + Date.now(),
                name: groupName,
                collections: []
            };
            groups.push(newGroup);
        }
        
        saveGroups();
        closeGroupModal();
    }
    
    function deleteGroup(groupId) {
        if (!confirm('Are you sure you want to delete this group? Collections will be moved to ungrouped.')) {
            return;
        }
        
        groups = groups.filter(g => g.id !== groupId);
        saveGroups();
    }
    
    function deleteCollection(collection) {
        // Use id if available
        const collectionId = collection.id;
        if (!collectionId) {
            console.error('Collection data:', collection);
            alert('Error: Collection ID not found. Cannot delete collection.');
            return;
        }
        
        const confirmMessage = `Are you sure you want to delete "${collection.name || collection.title || 'this collection'}"? This will also delete all ${collection.entries_count || 0} entries in this collection. This action cannot be undone.`;
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        fetch('/api/content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete_collection',
                id: collectionId,
                _csrf: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                // Remove collection from groups
                groups.forEach(group => {
                    if (group.collections && group.collections.includes(collection.slug)) {
                        group.collections = group.collections.filter(slug => slug !== collection.slug);
                    }
                });
                
                // Remove collection from local array
                collections = collections.filter(col => col.slug !== collection.slug);
                
                // Save updated groups
                saveGroups();
                
                // Re-render to reflect deletion
                renderGroupsAndCollections();
            } else {
                alert('Failed to delete collection: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error deleting collection:', error);
            alert('Error deleting collection. Please try again.');
        });
    }
    
    function saveGroups() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        
        fetch('/api/content.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'save_groups',
                groups: groups,
                _csrf: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                renderGroupsAndCollections();
            } else {
                alert('Failed to save groups: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error saving groups:', error);
            alert('Error saving groups. Please try again.');
        });
    }
    
    // Toggle group collapse/expand
    function toggleGroupCollapse(groupSection, groupId) {
        const isCollapsed = groupSection.classList.contains('collapsed');
        const grid = groupSection.querySelector('.group-collections, .ungrouped-collections');
        const collapseBtn = groupSection.querySelector('.group-collapse-btn');
        
        if (isCollapsed) {
            groupSection.classList.remove('collapsed');
            if (grid) grid.style.display = '';
            if (collapseBtn) {
                collapseBtn.title = 'Collapse Group';
                collapseBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg>';
            }
            localStorage.setItem(`group-collapsed-${groupId}`, 'false');
        } else {
            groupSection.classList.add('collapsed');
            if (grid) grid.style.display = 'none';
            if (collapseBtn) {
                collapseBtn.title = 'Expand Group';
                collapseBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 15l-6-6-6 6"></path></svg>';
            }
            localStorage.setItem(`group-collapsed-${groupId}`, 'true');
        }
    }
    
})();
