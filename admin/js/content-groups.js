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
        card.className = 'card draggable';
        card.dataset.collectionSlug = collection.slug;
        card.draggable = true;
        card.dataset.groupId = groupId || '';
        
        const link = document.createElement('a');
        link.href = '/admin/index.php?page=entries&collection=' + encodeURIComponent(collection.slug);
        link.setAttribute('aria-label', 'Open collection ' + collection.name);
        link.draggable = false; // Prevent link from being draggable
        
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
        link.appendChild(header);
        
        const footer = document.createElement('div');
        footer.className = 'collection-footer';
        
        const entryCount = document.createElement('div');
        entryCount.className = 'entry-count';
        const countBold = document.createElement('b');
        countBold.textContent = collection.entries_count || 0;
        entryCount.appendChild(countBold);
        entryCount.appendChild(document.createTextNode(' entries'));
        
        const actions = document.createElement('div');
        actions.className = 'collection-actions';
        
        const editLink = document.createElement('a');
        editLink.className = 'btn';
        editLink.href = '/admin/index.php?page=collection-editor&collection=' + encodeURIComponent(collection.slug);
        editLink.setAttribute('aria-label', 'Edit collection ' + collection.name);
        editLink.innerHTML = '<img src="/admin/assets/PencilSimple.svg" alt="Edit">';
        
        const deleteBtn = document.createElement('button');
        deleteBtn.className = 'btn';
        deleteBtn.setAttribute('aria-label', 'Delete collection ' + collection.name);
        deleteBtn.innerHTML = '<img src="/admin/assets/Trash.svg" alt="Delete">';
        deleteBtn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            deleteCollection(collection);
        };
        
        actions.appendChild(editLink);
        actions.appendChild(deleteBtn);
        
        footer.appendChild(entryCount);
        footer.appendChild(actions);
        
        card.appendChild(link);
        card.appendChild(footer);
        
        // Drag event listeners
        setupDragEvents(card, collection, groupId);
        
        return card;
    }
    
    function setupDragEvents(card, collection, groupId) {
        let dragStarted = false;
        let clickBlocked = false;
        
        // Handle dragstart
        card.addEventListener('dragstart', function(e) {
            // Don't start drag from action buttons
            if (e.target.closest('.collection-actions')) {
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
            clickBlocked = dragStarted;
            
            // Clear flags after a short delay to allow click handling
            setTimeout(() => {
                dragStarted = false;
                if (clickBlocked) {
                    clickBlocked = false;
                }
            }, 100);
            
            document.querySelectorAll('.drop-zone').forEach(zone => {
                zone.classList.remove('drag-over');
            });
            console.log('Drag ended');
            draggedElement = null;
            draggedCollection = null;
            draggedGroupId = null;
        });
        
        // Prevent link navigation if we just dragged
        const link = card.querySelector('a');
        if (link) {
            link.addEventListener('click', function(e) {
                if (clickBlocked) {
                    e.preventDefault();
                    e.stopPropagation();
                    clickBlocked = false;
                    return false;
                }
            }, true); // Use capture phase
            
            // Prevent default drag behavior on link
            link.addEventListener('dragstart', function(e) {
                e.stopPropagation();
            });
        }
        
        // Prevent clicks on action buttons from starting drag
        const actionButtons = card.querySelectorAll('.collection-actions a, .collection-actions button');
        actionButtons.forEach(btn => {
            btn.addEventListener('mousedown', function(e) {
                e.stopPropagation();
            });
            btn.addEventListener('dragstart', function(e) {
                e.stopPropagation();
                e.preventDefault();
                return false;
            });
        });
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
            
            // If dropping in same group/zone, do nothing
            if (targetGroupIdStr === draggedGroupIdStr) {
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
        if (!collection.id) {
            alert('Error: Collection ID not found. Cannot delete collection.');
            console.error('Collection data:', collection);
            return;
        }
        
        const confirmMessage = `Are you sure you want to delete "${collection.name}"? This will also delete all ${collection.entries_count || 0} entries in this collection. This action cannot be undone.`;
        
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
                id: collection.id,
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
