/**
 * Collection Groups Management
 * Handles drag-and-drop, group creation, nested groups, and view toggle
 */

(function() {
    'use strict';

    const VIEW_STORAGE_KEY = 'birkly_content_view';
    const MAX_GROUP_DEPTH = 3;

    function adminUrl(page, params) {
        if (window.Birkly && typeof Birkly.adminUrl === 'function') {
            return Birkly.adminUrl(page, params || {});
        }
        const q = Object.assign({ page: page }, params || {});
        const query = Object.keys(q)
            .filter((k) => q[k] !== undefined && q[k] !== null && q[k] !== '')
            .map((k) => encodeURIComponent(k) + '=' + encodeURIComponent(String(q[k])))
            .join('&');
        return '/admin/index.php' + (query ? '?' + query : '');
    }

    function apiUrl(path) {
        return (window.Birkly && typeof Birkly.url === 'function') ? Birkly.url(path) : path;
    }

    const DRAG_HANDLE_SVG = '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><circle cx="5" cy="3" r="1.25"/><circle cx="11" cy="3" r="1.25"/><circle cx="5" cy="8" r="1.25"/><circle cx="11" cy="8" r="1.25"/><circle cx="5" cy="13" r="1.25"/><circle cx="11" cy="13" r="1.25"/></svg>';

    function createDragHandle(label, className) {
        const handle = document.createElement('span');
        handle.className = className || 'drag-handle';
        handle.setAttribute('role', 'img');
        handle.setAttribute('aria-label', label);
        handle.innerHTML = DRAG_HANDLE_SVG;
        return handle;
    }

    let groups = [];
    let collections = [];
    let dragDropManager = null;
    let subgroupSorters = [];
    let currentView = 'grid';

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        currentView = localStorage.getItem(VIEW_STORAGE_KEY) === 'list' ? 'list' : 'grid';

        const dataScript = document.getElementById('content-data');
        if (dataScript) {
            try {
                let dataText = (dataScript.textContent || dataScript.innerText || '').trim();
                if (dataText.includes('<?php')) {
                    dataText = dataScript.innerHTML.trim();
                }
                const data = JSON.parse(dataText);
                collections = data.collections || [];
                groups = normalizeGroups(data.groups || []);
                renderGroupsAndCollections();
            } catch (e) {
                console.error('Failed to parse content data:', e);
                renderGroupsAndCollections();
            }
        } else {
            renderGroupsAndCollections();
        }

        setupEventListeners();
        setupViewToggle();
        setView(currentView, false);
        setupDragDrop();
    }

    function normalizeGroups(rawGroups) {
        return (rawGroups || []).map((group) => {
            if (!group || group.id === 'ungrouped') {
                return {
                    id: 'ungrouped',
                    name: group?.name || 'Ungrouped',
                    collections: Array.isArray(group?.collections) ? group.collections.slice() : []
                };
            }
            return normalizeGroupNode(group);
        });
    }

    function normalizeGroupNode(node) {
        const normalized = {
            id: node.id || ('group-' + Date.now()),
            name: node.name || node.label || 'Untitled Group',
            collections: Array.isArray(node.collections) ? node.collections.slice() : []
        };
        if (node.icon) {
            normalized.icon = node.icon;
        }
        if (Array.isArray(node.children) && node.children.length > 0) {
            normalized.children = node.children.map((child) => normalizeGroupNode(child));
        }
        return normalized;
    }

    function getGroupLabel(group) {
        return group?.name || group?.label || 'Untitled Group';
    }

    function findGroupInTree(groupList, id, parent = null) {
        for (let i = 0; i < groupList.length; i++) {
            const group = groupList[i];
            if (group.id === id) {
                return { group, parent, index: i, list: groupList };
            }
            if (group.children) {
                const found = findGroupInTree(group.children, id, group);
                if (found) return found;
            }
        }
        return null;
    }

    function getAllAssignedSlugs(groupList) {
        const slugs = new Set();
        function walk(list) {
            (list || []).forEach((group) => {
                if (group.id === 'ungrouped') return;
                (group.collections || []).forEach((slug) => slugs.add(slug));
                if (group.children) walk(group.children);
            });
        }
        walk(groupList);
        return slugs;
    }

    function setupEventListeners() {
        const newGroupBtn = document.getElementById('new-group-btn');
        if (newGroupBtn) {
            newGroupBtn.addEventListener('click', showCreateGroupModal);
        }

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-backdrop')) {
                closeGroupModal();
            }
            if (e.target.classList.contains('modal-close') || e.target.closest('.modal-close')) {
                closeGroupModal();
            }
        });

        const groupForm = document.getElementById('group-form');
        if (groupForm) {
            groupForm.addEventListener('submit', handleGroupFormSubmit);
        }
    }

    function setupViewToggle() {
        document.querySelectorAll('[data-content-view]').forEach((button) => {
            button.addEventListener('click', () => {
                setView(button.getAttribute('data-content-view') || 'grid', true);
            });
        });
    }

    function setView(view, persist) {
        currentView = view === 'list' ? 'list' : 'grid';
        const container = document.getElementById('collections-container');
        if (container) {
            container.dataset.view = currentView;
        }

        document.querySelectorAll('[data-content-view]').forEach((button) => {
            const active = button.getAttribute('data-content-view') === currentView;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        if (persist) {
            localStorage.setItem(VIEW_STORAGE_KEY, currentView);
        }
    }

    function getSlugsFromGrid(grid) {
        if (!grid) return [];
        return Array.from(grid.querySelectorAll('.collection-card'))
            .map((card) => card.dataset.collectionSlug)
            .filter(Boolean);
    }

    function buildGroupFromSection(section) {
        const titleEl = section.querySelector(':scope > .group-header .group-title, :scope > .subgroup-header .subgroup-title');
        const group = {
            id: section.dataset.groupId,
            name: titleEl?.textContent?.trim() || 'Untitled Group',
            collections: getSlugsFromGrid(section.querySelector(':scope > .group-collections')),
            children: []
        };
        if (section.dataset.groupIcon) {
            group.icon = section.dataset.groupIcon;
        }

        const subgroupsContainer = section.querySelector(':scope > .collection-subgroups');
        if (subgroupsContainer) {
            subgroupsContainer.querySelectorAll(':scope > .collection-subgroup').forEach((sub) => {
                group.children.push(buildGroupFromSection(sub));
            });
        }
        if (!group.children.length) {
            delete group.children;
        }
        return group;
    }

    function syncAllGroupsFromDom() {
        const nextGroups = [];
        document.querySelectorAll('#collections-container > .collection-group:not(.ungrouped)').forEach((section) => {
            nextGroups.push(buildGroupFromSection(section));
        });

        const ungroupedSection = document.querySelector('#collections-container > .collection-group.ungrouped');
        if (ungroupedSection) {
            nextGroups.push({
                id: 'ungrouped',
                name: 'Ungrouped',
                collections: getSlugsFromGrid(ungroupedSection.querySelector('.ungrouped-collections'))
            });
        } else {
            const existing = groups.find((g) => g.id === 'ungrouped');
            if (existing) {
                nextGroups.push(existing);
            }
        }

        groups = nextGroups;
    }

    function setupDragDrop() {
        const container = document.getElementById('collections-container');
        if (!container) return;

        if (dragDropManager) {
            dragDropManager.destroy();
        }
        subgroupSorters.forEach((sorter) => sorter.destroy());
        subgroupSorters = [];

        dragDropManager = new MouseDragDropManager({
            container: container,
            groupSelector: '.collection-group',
            groupHeaderSelector: '.group-header',
            collectionSelector: '.collection-card',
            collectionContainerSelector: '.group-collections, .ungrouped-collections',

            onGroupReorder: (fromIndex, toIndex) => {
                const reorderableGroups = groups.filter((g) => g.id !== 'ungrouped');
                const draggedGroup = reorderableGroups[fromIndex];
                if (!draggedGroup) return;

                reorderableGroups.splice(fromIndex, 1);
                reorderableGroups.splice(toIndex, 0, draggedGroup);

                const ungroupedGroup = groups.find((g) => g.id === 'ungrouped');
                groups = [...reorderableGroups];
                if (ungroupedGroup) {
                    groups.push(ungroupedGroup);
                }
                saveGroups();
            },

            onCollectionReorder: (groupId, fromIndex, toIndex, placeholder, draggedItem) => {
                syncAllGroupsFromDom();
                saveGroups();
            },

            onCollectionMove: () => {
                syncAllGroupsFromDom();
                saveGroups();
            }
        });

        container.querySelectorAll('.collection-subgroups').forEach((subgroupsContainer) => {
            const sorter = new MouseDragDrop({
                container: subgroupsContainer,
                itemSelector: '.collection-subgroup',
                handleSelector: '.subgroup-header',
                onReorder: () => {
                    syncAllGroupsFromDom();
                    saveGroups();
                }
            });
            subgroupSorters.push(sorter);
        });
    }

    function renderGroupsAndCollections() {
        const container = document.getElementById('collections-container');
        if (!container) return;

        container.innerHTML = '';
        container.dataset.view = currentView;

        groups.forEach((group) => {
            if (group.id === 'ungrouped') return;
            container.appendChild(createGroupSection(group, 1));
        });

        const assignedSlugs = getAllAssignedSlugs(groups);
        let ungroupedCollections = collections.filter((col) => !assignedSlugs.has(col.slug));

        const ungroupedGroup = groups.find((g) => g.id === 'ungrouped');
        if (ungroupedGroup?.collections?.length) {
            const order = ungroupedGroup.collections;
            ungroupedCollections.sort((a, b) => {
                const indexA = order.indexOf(a.slug);
                const indexB = order.indexOf(b.slug);
                if (indexA === -1 && indexB === -1) return 0;
                if (indexA === -1) return 1;
                if (indexB === -1) return -1;
                return indexA - indexB;
            });
        }

        if (ungroupedCollections.length > 0 || groups.length > 0) {
            container.appendChild(createUngroupedSection(ungroupedCollections));
        } else if (collections.length === 0 && groups.filter((g) => g.id !== 'ungrouped').length === 0) {
            container.innerHTML = '<div class="empty-state card" style="padding: 2rem; text-align: center;">'
                + '<p style="margin: 0 0 1rem; color: var(--text-secondary);">No collections yet.</p>'
                + '<a class="btn btn-primary" href="' + adminUrl('collection-editor') + '">Create your first collection</a>'
                + '</div>';
        }

        if (dragDropManager) {
            dragDropManager.update();
        }
        setupDragDrop();

        if (window.initDropdowns) {
            setTimeout(() => window.initDropdowns(), 100);
        }
    }

    function createGroupActions(group, depth, isSubgroup) {
        const actions = document.createElement('div');
        actions.className = isSubgroup ? 'subgroup-actions group-actions' : 'group-actions';

        const collapseBtn = document.createElement('button');
        collapseBtn.type = 'button';
        collapseBtn.className = 'btn-icon group-collapse-btn';
        collapseBtn.title = 'Collapse Group';
        collapseBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"></path></svg>';
        collapseBtn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            const section = collapseBtn.closest('.collection-group, .collection-subgroup');
            toggleGroupCollapse(section, group.id);
        };

        const editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'btn-icon';
        editBtn.title = 'Edit Group';
        editBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';
        editBtn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            showEditGroupModal(group);
        };

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn-icon';
        deleteBtn.title = 'Delete Group';
        deleteBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>';
        deleteBtn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            deleteGroup(group.id);
        };

        [collapseBtn, editBtn, deleteBtn].forEach((btn) => {
            btn.addEventListener('dragstart', (e) => {
                e.stopPropagation();
                e.preventDefault();
            });
        });

        actions.appendChild(collapseBtn);
        actions.appendChild(editBtn);
        if (!isSubgroup) {
            actions.appendChild(deleteBtn);
        } else {
            actions.appendChild(deleteBtn);
        }

        if (depth < MAX_GROUP_DEPTH) {
            const addSubBtn = document.createElement('button');
            addSubBtn.type = 'button';
            addSubBtn.className = 'btn-icon';
            addSubBtn.title = 'Add subgroup';
            addSubBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"></path></svg>';
            addSubBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                addSubgroup(group.id);
            };
            addSubBtn.addEventListener('dragstart', (e) => {
                e.stopPropagation();
                e.preventDefault();
            });
            actions.appendChild(addSubBtn);
        }

        return actions;
    }

    function appendCollectionsGrid(parent, group, groupId) {
        const grid = document.createElement('div');
        grid.className = 'grid group-collections drop-zone';
        grid.dataset.groupId = groupId;

        let groupCollections = collections.filter((col) =>
            group.collections && group.collections.includes(col.slug)
        );

        if (group.collections?.length) {
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
            groupCollections.forEach((col) => {
                grid.appendChild(createCollectionCard(col, groupId));
            });
        }

        parent.appendChild(grid);
    }

    function createSubgroupSection(subgroup, depth) {
        const section = document.createElement('div');
        section.className = 'collection-subgroup';
        section.dataset.groupId = subgroup.id;
        if (subgroup.icon) {
            section.dataset.groupIcon = subgroup.icon;
        }

        const isCollapsed = localStorage.getItem(`group-collapsed-${subgroup.id}`) === 'true';
        if (isCollapsed) {
            section.classList.add('collapsed');
        }

        const header = document.createElement('div');
        header.className = 'subgroup-header';

        const dragHandleWrapper = document.createElement('div');
        dragHandleWrapper.className = 'group-drag-handle';
        dragHandleWrapper.appendChild(createDragHandle('Drag to reorder subgroup', 'drag-handle'));

        if (subgroup.icon) {
            const iconSpan = document.createElement('span');
            iconSpan.className = 'subgroup-icon';
            iconSpan.textContent = subgroup.icon;
            header.appendChild(iconSpan);
        }

        const title = document.createElement('h4');
        title.className = 'subgroup-title';
        title.textContent = getGroupLabel(subgroup);

        header.appendChild(dragHandleWrapper);
        header.appendChild(title);
        header.appendChild(createGroupActions(subgroup, depth, true));

        section.appendChild(header);
        appendCollectionsGrid(section, subgroup, subgroup.id);

        if (subgroup.children?.length && depth < MAX_GROUP_DEPTH) {
            const subgroupsContainer = document.createElement('div');
            subgroupsContainer.className = 'collection-subgroups';
            subgroup.children.forEach((child) => {
                subgroupsContainer.appendChild(createSubgroupSection(child, depth + 1));
            });
            section.appendChild(subgroupsContainer);
        }

        return section;
    }

    function createGroupSection(group, depth) {
        const section = document.createElement('div');
        section.className = 'collection-group';
        section.dataset.groupId = group.id;
        if (group.icon) {
            section.dataset.groupIcon = group.icon;
        }

        const isCollapsed = localStorage.getItem(`group-collapsed-${group.id}`) === 'true';
        if (isCollapsed) {
            section.classList.add('collapsed');
        }

        const header = document.createElement('div');
        header.className = 'group-header';
        header.style.cursor = 'grab';

        const dragHandleWrapper = document.createElement('div');
        dragHandleWrapper.className = 'group-drag-handle';
        dragHandleWrapper.appendChild(createDragHandle('Drag to reorder group', 'drag-handle'));

        const title = document.createElement('h3');
        title.className = 'group-title';
        if (group.icon) {
            const iconSpan = document.createElement('span');
            iconSpan.className = 'group-title-icon';
            iconSpan.textContent = group.icon;
            title.appendChild(iconSpan);
        }
        title.appendChild(document.createTextNode(getGroupLabel(group)));

        header.appendChild(dragHandleWrapper);
        header.appendChild(title);
        header.appendChild(createGroupActions(group, depth, false));

        section.appendChild(header);
        appendCollectionsGrid(section, group, group.id);

        if (group.children?.length && depth < MAX_GROUP_DEPTH) {
            const subgroupsContainer = document.createElement('div');
            subgroupsContainer.className = 'collection-subgroups';
            group.children.forEach((child) => {
                subgroupsContainer.appendChild(createSubgroupSection(child, depth + 1));
            });
            section.appendChild(subgroupsContainer);
        }

        return section;
    }

    function createUngroupedSection(collectionsList) {
        const section = document.createElement('div');
        section.className = 'collection-group ungrouped';
        section.dataset.groupId = 'ungrouped';

        const isCollapsed = localStorage.getItem('group-collapsed-ungrouped') === 'true';
        if (isCollapsed) {
            section.classList.add('collapsed');
        }

        const header = document.createElement('div');
        header.className = 'group-header';

        const dragHandleWrapper = document.createElement('div');
        dragHandleWrapper.className = 'group-drag-handle';
        dragHandleWrapper.style.opacity = '0.3';
        dragHandleWrapper.style.cursor = 'default';

        const title = document.createElement('h3');
        title.className = 'group-title';
        title.textContent = 'Ungrouped Collections';

        const actions = document.createElement('div');
        actions.className = 'group-actions';
        const collapseBtn = document.createElement('button');
        collapseBtn.type = 'button';
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
        grid.className = 'grid ungrouped-collections drop-zone';
        grid.dataset.groupId = 'ungrouped';

        if (collectionsList.length === 0) {
            grid.classList.add('empty-drop-zone');
            const emptyMsg = document.createElement('div');
            emptyMsg.className = 'empty-drop-message';
            emptyMsg.textContent = 'Drop collections here';
            grid.appendChild(emptyMsg);
        } else {
            collectionsList.forEach((col) => {
                grid.appendChild(createCollectionCard(col, 'ungrouped'));
            });
        }

        section.appendChild(header);
        section.appendChild(grid);
        return section;
    }

    function renderCollectionIcon(collection, title) {
        if (collection.icon) {
            const icon = document.createElement('span');
            icon.className = 'collection-icon';
            icon.textContent = collection.icon;
            icon.setAttribute('aria-hidden', 'true');
            title.insertBefore(icon, title.firstChild);
        } else if (collection.icon_key) {
            const icon = document.createElement('span');
            icon.className = 'collection-icon-key';
            icon.textContent = collection.icon_key.slice(0, 2).toUpperCase();
            icon.title = collection.icon_key;
            title.insertBefore(icon, title.firstChild);
        }
    }

    function createCollectionCard(collection, groupId) {
        const card = document.createElement('div');
        card.className = 'card collection-card';
        card.dataset.collectionSlug = collection.slug;
        card.dataset.groupId = groupId || 'ungrouped';

        const header = document.createElement('div');
        header.className = 'collection-header';

        const title = document.createElement('h3');
        title.className = 'title';

        const dragHandle = createDragHandle('Drag to reorder');
        const titleText = document.createElement('span');
        titleText.className = 'collection-title';
        titleText.textContent = collection.name || 'Untitled';

        title.appendChild(dragHandle);
        renderCollectionIcon(collection, title);
        title.appendChild(titleText);

        const description = document.createElement('div');
        description.className = 'collection-description';
        const descP = document.createElement('p');
        const descText = (collection.description || '').trim();
        descP.textContent = descText;
        if (!descText) {
            description.style.display = 'none';
        }
        description.appendChild(descP);

        header.appendChild(title);
        header.appendChild(description);

        const footer = document.createElement('div');
        footer.className = 'collection-footer';

        const entryCount = document.createElement('a');
        entryCount.className = 'entry-count entry-count-link';
        entryCount.href = adminUrl('entries', { collection: collection.slug });
        entryCount.title = 'View entries';
        const countBold = document.createElement('b');
        countBold.textContent = collection.entries_count || 0;
        entryCount.appendChild(countBold);
        entryCount.appendChild(document.createTextNode(' entries'));

        if (collection.sub_entries_count > 0) {
            const subEntriesSpan = document.createElement('span');
            subEntriesSpan.className = 'sub-entries-count';
            subEntriesSpan.textContent = ' +' + collection.sub_entries_count;
            entryCount.appendChild(subEntriesSpan);
        }

        const menuWrapper = document.createElement('div');
        menuWrapper.className = 'dropdown collection-menu-wrapper';

        const menuButton = document.createElement('button');
        menuButton.type = 'button';
        menuButton.className = 'dropdown-trigger collection-menu-btn';
        menuButton.setAttribute('aria-label', 'Collection options');
        menuButton.setAttribute('aria-expanded', 'false');
        menuButton.setAttribute('aria-haspopup', 'true');
        menuButton.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="1.5"></circle><circle cx="12" cy="12" r="1.5"></circle><circle cx="12" cy="19" r="1.5"></circle></svg>';
        menuButton.addEventListener('dragstart', (e) => {
            e.stopPropagation();
            e.preventDefault();
        });

        const menuDropdown = document.createElement('div');
        menuDropdown.className = 'dropdown-menu dropdown-menu-right collection-menu-dropdown';

        const viewEntriesItem = document.createElement('a');
        viewEntriesItem.className = 'dropdown-item collection-menu-item';
        viewEntriesItem.href = adminUrl('entries', { collection: collection.slug });
        viewEntriesItem.innerHTML = '<svg class="dropdown-item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg><span class="dropdown-item-label">View Entries</span>';

        const editItem = document.createElement('a');
        editItem.className = 'dropdown-item collection-menu-item';
        editItem.href = adminUrl('collection-editor', { collection: collection.slug });
        editItem.innerHTML = '<svg class="dropdown-item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg><span class="dropdown-item-label">Edit Collection</span>';

        const duplicateItem = document.createElement('button');
        duplicateItem.type = 'button';
        duplicateItem.className = 'dropdown-item collection-menu-item';
        duplicateItem.innerHTML = '<svg class="dropdown-item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg><span class="dropdown-item-label">Duplicate Collection</span>';
        duplicateItem.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeDropdown(duplicateItem);
            duplicateCollection(collection);
        };

        const deleteItem = document.createElement('button');
        deleteItem.type = 'button';
        deleteItem.className = 'dropdown-item dropdown-item-danger collection-menu-item collection-menu-item-danger';
        deleteItem.innerHTML = '<svg class="dropdown-item-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg><span class="dropdown-item-label">Delete Collection</span>';
        deleteItem.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeDropdown(deleteItem);
            deleteCollection(collection);
        };

        menuDropdown.appendChild(viewEntriesItem);
        menuDropdown.appendChild(editItem);
        menuDropdown.appendChild(duplicateItem);
        menuDropdown.appendChild(deleteItem);
        menuWrapper.appendChild(menuButton);
        menuWrapper.appendChild(menuDropdown);

        footer.appendChild(entryCount);
        footer.appendChild(menuWrapper);

        card.appendChild(header);
        card.appendChild(footer);

        card.addEventListener('click', function(e) {
            if (e.defaultPrevented) return;
            if (e.target.closest('a, button, .drag-handle, .dropdown-trigger, .collection-menu-wrapper, .entry-count-link')) {
                return;
            }
            window.location.href = adminUrl('entries', { collection: collection.slug });
        });

        return card;
    }

    function closeDropdown(el) {
        const dropdown = el.closest('.dropdown');
        if (!dropdown) return;
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

    function duplicateCollection(collection) {
        const collectionId = collection.id;
        if (!collectionId) {
            alert('Error: Collection ID not found. Cannot duplicate collection.');
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fetch(apiUrl('/api/content.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'duplicate_collection',
                id: collectionId,
                _csrf: csrfToken
            })
        })
        .then((response) => response.json())
        .then((data) => {
            if (data.status === 'ok') {
                window.location.reload();
            } else {
                alert('Failed to duplicate collection: ' + (data.message || 'Unknown error'));
            }
        })
        .catch((error) => {
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
        const iconInput = document.getElementById('group-icon');
        const submitBtn = document.getElementById('group-submit');

        if (!modal || !form || !title || !nameInput) return;

        if (group) {
            title.textContent = 'Edit Group';
            nameInput.value = getGroupLabel(group);
            if (iconInput) iconInput.value = group.icon || '';
            submitBtn.textContent = 'Update Group';
            form.dataset.groupId = group.id;
        } else {
            title.textContent = 'New Group';
            nameInput.value = '';
            if (iconInput) iconInput.value = '';
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
        const iconInput = document.getElementById('group-icon');
        const groupId = form.dataset.groupId;

        if (!nameInput || !nameInput.value.trim()) {
            alert('Please enter a group name');
            return;
        }

        const groupName = nameInput.value.trim();
        const groupIcon = iconInput ? iconInput.value.trim() : '';

        if (groupId) {
            const found = findGroupInTree(groups, groupId);
            if (found) {
                found.group.name = groupName;
                if (groupIcon) {
                    found.group.icon = groupIcon;
                } else {
                    delete found.group.icon;
                }
            }
        } else {
            const newGroup = {
                id: 'group-' + Date.now(),
                name: groupName,
                collections: []
            };
            if (groupIcon) {
                newGroup.icon = groupIcon;
            }
            groups.push(newGroup);
        }

        saveGroups();
        closeGroupModal();
    }

    function addSubgroup(parentGroupId) {
        const found = findGroupInTree(groups, parentGroupId);
        if (!found) return;

        if (!found.group.children) {
            found.group.children = [];
        }

        found.group.children.push({
            id: 'group-' + Date.now(),
            name: 'New Subgroup',
            collections: []
        });

        saveGroups();
    }

    function deleteGroupFromTree(groupList, groupId) {
        for (let i = 0; i < groupList.length; i++) {
            if (groupList[i].id === groupId) {
                groupList.splice(i, 1);
                return true;
            }
            if (groupList[i].children && deleteGroupFromTree(groupList[i].children, groupId)) {
                return true;
            }
        }
        return false;
    }

    function deleteGroup(groupId) {
        if (!confirm('Are you sure you want to delete this group? Collections will be moved to ungrouped.')) {
            return;
        }

        deleteGroupFromTree(groups, groupId);
        saveGroups();
    }

    function removeSlugFromGroups(groupList, slug) {
        groupList.forEach((group) => {
            if (group.collections) {
                group.collections = group.collections.filter((s) => s !== slug);
            }
            if (group.children) {
                removeSlugFromGroups(group.children, slug);
            }
        });
    }

    function deleteCollection(collection) {
        const collectionId = collection.id;
        if (!collectionId) {
            alert('Error: Collection ID not found. Cannot delete collection.');
            return;
        }

        const confirmMessage = `Are you sure you want to delete "${collection.name || 'this collection'}"? This will also delete all ${collection.entries_count || 0} entries in this collection. This action cannot be undone.`;
        if (!confirm(confirmMessage)) return;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fetch(apiUrl('/api/content.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_collection',
                id: collectionId,
                _csrf: csrfToken
            })
        })
        .then((response) => response.json())
        .then((data) => {
            if (data.status === 'ok') {
                removeSlugFromGroups(groups, collection.slug);
                collections = collections.filter((col) => col.slug !== collection.slug);
                saveGroups();
                renderGroupsAndCollections();
            } else {
                alert('Failed to delete collection: ' + (data.message || 'Unknown error'));
            }
        })
        .catch((error) => {
            console.error('Error deleting collection:', error);
            alert('Error deleting collection. Please try again.');
        });
    }

    function saveGroups() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch(apiUrl('/api/content.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_groups',
                groups: groups,
                _csrf: csrfToken
            })
        })
        .then((response) => response.json())
        .then((data) => {
            if (data.status === 'ok') {
                if (data.data?.groups) {
                    groups = normalizeGroups(data.data.groups);
                }
                renderGroupsAndCollections();
            } else {
                alert('Failed to save groups: ' + (data.message || 'Unknown error'));
            }
        })
        .catch((error) => {
            console.error('Error saving groups:', error);
            alert('Error saving groups. Please try again.');
        });
    }

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
