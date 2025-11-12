/**
 * Collection Groups Management
 * Handles drag-and-drop, group creation, and group management
 */

(function() {
    'use strict';
    
    let groups = [];
    let collections = [];
    let draggedElement = null;
    let draggedCollection = null;
    let draggedGroupId = null;
    let dropZonesSetup = false;
    
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
                // Set up drop zones after rendering (only once)
                setTimeout(function() {
                    if (!dropZonesSetup) {
                        setupDropZones();
                    }
                }, 100);
            } catch (e) {
                console.error('Failed to parse content data:', e, 'Script content:', dataScript?.textContent?.substring(0, 100));
                // Fallback: render empty state
                renderGroupsAndCollections();
                setTimeout(function() {
                    setupDropZones();
                }, 100);
            }
        } else {
            // No data script, render empty state
            renderGroupsAndCollections();
            setTimeout(function() {
                setupDropZones();
            }, 100);
        }
        
        // Set up event listeners
        setupEventListeners();
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
    
    function renderGroupsAndCollections() {
        const container = document.getElementById('collections-container');
        if (!container) {
            console.error('Collections container not found');
            return;
        }
        
        container.innerHTML = '';
        
        console.log('Rendering:', { groupsCount: groups.length, collectionsCount: collections.length });
        
        // Render groups
        groups.forEach(group => {
            const groupSection = createGroupSection(group);
            container.appendChild(groupSection);
        });
        
        // Render ungrouped collections
        const ungroupedCollections = collections.filter(col => {
            return !groups.some(group => 
                group.collections && group.collections.includes(col.slug)
            );
        });
        
        console.log('Ungrouped collections:', ungroupedCollections.length);
        
        // Always show ungrouped section if there are collections or groups
        if (ungroupedCollections.length > 0 || groups.length > 0) {
            const ungroupedSection = createUngroupedSection(ungroupedCollections);
            container.appendChild(ungroupedSection);
        }
    }
    
    function createGroupSection(group) {
        const section = document.createElement('div');
        section.className = 'collection-group';
        section.dataset.groupId = group.id;
        
        const header = document.createElement('div');
        header.className = 'group-header';
        
        const title = document.createElement('h3');
        title.className = 'group-title';
        title.textContent = group.name || 'Untitled Group';
        
        const actions = document.createElement('div');
        actions.className = 'group-actions';
        
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
        
        actions.appendChild(editBtn);
        actions.appendChild(deleteBtn);
        header.appendChild(title);
        header.appendChild(actions);
        
        const grid = document.createElement('div');
        grid.className = 'grid group-collections';
        grid.dataset.groupId = group.id;
        grid.dataset.dropZone = 'group';
        
        // Add drop zone class
        grid.classList.add('drop-zone');
        
        // Render collections in this group
        const groupCollections = collections.filter(col => 
            group.collections && group.collections.includes(col.slug)
        );
        
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
        
        const header = document.createElement('div');
        header.className = 'group-header';
        
        const title = document.createElement('h3');
        title.className = 'group-title';
        title.textContent = 'Ungrouped Collections';
        
        header.appendChild(title);
        
        const grid = document.createElement('div');
        grid.className = 'grid ungrouped-collections';
        grid.dataset.dropZone = 'ungrouped';
        grid.classList.add('drop-zone');
        
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
        
        // 3-dot menu instead of separate edit/delete buttons
        const menuWrapper = document.createElement('div');
        menuWrapper.className = 'collection-menu-wrapper';
        
        const menuButton = document.createElement('button');
        menuButton.className = 'collection-menu-btn';
        menuButton.setAttribute('aria-label', 'Collection options');
        menuButton.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1.5"></circle><circle cx="12" cy="12" r="1.5"></circle><circle cx="12" cy="19" r="1.5"></circle></svg>';
        menuButton.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            toggleCollectionMenu(menuButton, collection);
        };
        
        const menuDropdown = document.createElement('div');
        menuDropdown.className = 'collection-menu-dropdown';
        menuDropdown.style.display = 'none';
        
        const editItem = document.createElement('a');
        editItem.className = 'collection-menu-item';
        editItem.href = '/admin/index.php?page=collection-editor&collection=' + encodeURIComponent(collection.slug);
        editItem.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg><span>Edit Collection</span>';
        editItem.onclick = (e) => {
            e.stopPropagation();
            // Let the link navigate normally
        };
        
        const duplicateItem = document.createElement('button');
        duplicateItem.className = 'collection-menu-item';
        duplicateItem.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg><span>Duplicate Collection</span>';
        duplicateItem.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeAllMenus();
            duplicateCollection(collection);
        };
        
        const deleteItem = document.createElement('button');
        deleteItem.className = 'collection-menu-item collection-menu-item-danger';
        deleteItem.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg><span>Delete Collection</span>';
        deleteItem.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeAllMenus();
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
        
        // Drag event listeners
        setupDragEvents(card, collection, groupId);
        
        return card;
    }
    
    function toggleCollectionMenu(button, collection) {
        // Close all other menus first
        closeAllMenus();
        
        const dropdown = button.nextElementSibling;
        if (dropdown && dropdown.classList.contains('collection-menu-dropdown')) {
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
            button.classList.toggle('active');
        }
    }
    
    function closeAllMenus() {
        document.querySelectorAll('.collection-menu-dropdown').forEach(menu => {
            menu.style.display = 'none';
        });
        document.querySelectorAll('.collection-menu-btn').forEach(btn => {
            btn.classList.remove('active');
        });
    }
    
    // Close menus when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.collection-menu-wrapper')) {
            closeAllMenus();
        }
    });
    
    function setupDragEvents(card, collection, groupId) {
        let dragStarted = false;
        
        // Handle dragstart - only from drag handle or card itself (not menu)
        card.addEventListener('dragstart', function(e) {
            // Don't start drag from menu
            if (e.target.closest('.collection-menu-wrapper')) {
                e.preventDefault();
                return false;
            }
            
            dragStarted = true;
            draggedElement = this;
            draggedCollection = collection;
            draggedGroupId = groupId;
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', collection.slug);
            e.dataTransfer.setData('application/json', JSON.stringify({
                slug: collection.slug,
                groupId: groupId || null
            }));
            console.log('Drag started:', collection.slug, 'from group:', groupId || 'ungrouped');
        });
        
        // Handle dragend
        card.addEventListener('dragend', function(e) {
            this.classList.remove('dragging');
            
            // Mark as just dragged to prevent click navigation
            this.classList.add('just-dragged');
            setTimeout(() => {
                this.classList.remove('just-dragged');
                dragStarted = false;
            }, 100);
            
            document.querySelectorAll('.drop-zone').forEach(zone => {
                zone.classList.remove('drag-over');
            });
            console.log('Drag ended');
            draggedElement = null;
            draggedCollection = null;
            draggedGroupId = null;
        });
        
        // Prevent drag from menu button
        const menuButton = card.querySelector('.collection-menu-btn');
        if (menuButton) {
            menuButton.addEventListener('dragstart', function(e) {
                e.stopPropagation();
                e.preventDefault();
                return false;
            });
        }
    }
    
    // Set up drop zones using event delegation
    function setupDropZones() {
        const container = document.getElementById('collections-container');
        if (!container) return;
        
        // Only set up once to avoid duplicate listeners
        if (dropZonesSetup) {
            console.log('Drop zones already set up, skipping');
            return;
        }
        
        dropZonesSetup = true;
        
        // Use event delegation on the container to handle all drop zones
        container.addEventListener('dragover', function(e) {
            const dropZone = e.target.closest('.drop-zone');
            if (!dropZone) return;
            
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'move';
            dropZone.classList.add('drag-over');
        }, false);
        
        container.addEventListener('dragleave', function(e) {
            const dropZone = e.target.closest('.drop-zone');
            if (!dropZone) return;
            
            // Only remove if we're actually leaving the zone
            if (!dropZone.contains(e.relatedTarget)) {
                dropZone.classList.remove('drag-over');
            }
        }, false);
        
        container.addEventListener('drop', function(e) {
            const dropZone = e.target.closest('.drop-zone');
            if (!dropZone) return;
            
            e.preventDefault();
            e.stopPropagation();
            dropZone.classList.remove('drag-over');
            
            if (!draggedElement || !draggedCollection) return;
            
            const targetGroupId = dropZone.dataset.groupId || null;
            const targetGroupIdStr = targetGroupId || '';
            const draggedGroupIdStr = draggedGroupId || '';
            
            // Check if we're reordering within the same group
            if (targetGroupIdStr === draggedGroupIdStr) {
                // Find the target card - check if we're dropping on a card or between cards
                let targetCard = e.target.closest('.collection-card');
                
                // If we didn't find a card, try to find the nearest card in the drop zone
                if (!targetCard) {
                    const cards = Array.from(dropZone.querySelectorAll('.collection-card'));
                    // Find the card that's closest to the drop point
                    const dropY = e.clientY;
                    let closestCard = null;
                    let closestDistance = Infinity;
                    
                    cards.forEach(card => {
                        if (card === draggedElement) return;
                        const rect = card.getBoundingClientRect();
                        const cardCenterY = rect.top + rect.height / 2;
                        const distance = Math.abs(dropY - cardCenterY);
                        if (distance < closestDistance) {
                            closestDistance = distance;
                            closestCard = card;
                        }
                    });
                    
                    targetCard = closestCard;
                }
                
                if (targetCard && targetCard !== draggedElement) {
                    // Reorder within same group
                    reorderCollectionInGroup(draggedCollection.slug, draggedGroupId, targetCard.dataset.collectionSlug);
                } else {
                    // Dropped in empty space, just refresh
                    saveGroups();
                }
                return;
            }
            
            // Move collection to new group
            moveCollectionToGroup(draggedCollection.slug, draggedGroupId, targetGroupId);
        }, false);
    }
    
    function moveCollectionToGroup(collectionSlug, fromGroupId, toGroupId) {
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
                if (!toGroup.collections.includes(collectionSlug)) {
                    toGroup.collections.push(collectionSlug);
                }
            }
        }
        
        // Save and re-render
        saveGroups();
    }
    
    function reorderCollectionInGroup(collectionSlug, groupId, targetSlug) {
        if (!groupId) {
            // Reordering in ungrouped - we need to track order differently
            // For now, just save groups to trigger re-render
            saveGroups();
            return;
        }
        
        const group = groups.find(g => g.id === groupId);
        if (!group || !group.collections) return;
        
        // Remove from current position
        group.collections = group.collections.filter(slug => slug !== collectionSlug);
        
        // Find target position and insert
        const targetIndex = group.collections.indexOf(targetSlug);
        if (targetIndex !== -1) {
            group.collections.splice(targetIndex, 0, collectionSlug);
        } else {
            // Target not found, just append
            group.collections.push(collectionSlug);
        }
        
        // Save and re-render
        saveGroups();
    }
    
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
                        // Re-setup drop zones after render
                setTimeout(function() {
                    setupDropZones();
                }, 100);
            } else {
                alert('Failed to save groups: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error saving groups:', error);
            alert('Error saving groups. Please try again.');
        });
    }
    
    // Expose setupDropZones to be called after render
    window.setupContentGroups = function() {
        setupDropZones();
    };
    
})();
