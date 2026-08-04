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

    function icon(name, opts) {
        return (window.Birkly && typeof Birkly.icon === 'function') ? Birkly.icon(name, opts) : '';
    }

    function createDragHandle(label, className) {
        const handle = document.createElement('span');
        handle.className = className || 'drag-handle';
        handle.setAttribute('role', 'img');
        handle.setAttribute('aria-label', label);
        handle.innerHTML = icon('dots-six-vertical', { size: 16 });
        return handle;
    }

    let groups = [];
    let collections = [];
    let starterTemplates = [];
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
                if (data && typeof data === 'object') {
                    collections = data.collections || [];
                    groups = normalizeGroups(data.groups || []);
                    starterTemplates = Array.isArray(data.starter_templates) ? data.starter_templates : [];
                } else {
                    collections = [];
                    groups = [];
                    starterTemplates = [];
                }
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

        document.addEventListener('click', async function (e) {
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
            button.addEventListener('click', async () => {
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

        document.querySelectorAll('.collection-card').forEach((card) => {
            card.classList.toggle('collection-card--list-row', currentView === 'list');
        });

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

        if (ungroupedCollections.length > 0) {
            container.appendChild(createUngroupedSection(ungroupedCollections));
        } else if (collections.length === 0) {
            container.appendChild(renderEmptyCollectionsState());
        } else if (groups.filter((g) => g.id !== 'ungrouped').length > 0) {
            container.appendChild(createUngroupedSection(ungroupedCollections));
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
        collapseBtn.innerHTML = icon('caret-down', { size: 14 });
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
        editBtn.innerHTML = icon('pencil-simple', { size: 14 });
        editBtn.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            showEditGroupModal(group);
        };

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn-icon';
        deleteBtn.title = 'Delete Group';
        deleteBtn.innerHTML = icon('trash', { size: 14 });
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
            addSubBtn.innerHTML = icon('plus', { size: 14 });
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
        grid.className = 'u-grid-auto group-collections drop-zone';
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

    function renderEmptyCollectionsState() {
        const wrap = document.createElement('div');
        wrap.className = 'u-stack-6';

        const empty = document.createElement('div');
        empty.className = 'empty-state';

        const iconWrap = document.createElement('div');
        iconWrap.className = 'empty-state-icon';
        iconWrap.setAttribute('aria-hidden', 'true');
        iconWrap.innerHTML = icon('stack', { size: 32 });

        const title = document.createElement('h2');
        title.className = 'empty-state-title';
        title.textContent = 'Create your first collection';

        const message = document.createElement('p');
        message.className = 'empty-state-text';
        message.textContent = 'Collections hold your site content — blog posts, products, pages, and more. Start from scratch or install a starter template.';

        const actions = document.createElement('div');
        actions.className = 'empty-state-actions';
        const createBtn = document.createElement('a');
        createBtn.className = 'btn btn-primary';
        createBtn.href = adminUrl('collection-editor');
        createBtn.textContent = 'New collection';
        actions.appendChild(createBtn);

        empty.appendChild(iconWrap);
        empty.appendChild(title);
        empty.appendChild(message);
        empty.appendChild(actions);
        wrap.appendChild(empty);

        const selectable = (starterTemplates || []).filter((t) => t && t.id && t.id !== 'blank' && t.selectable !== false);
        if (selectable.length > 0) {
            const templates = document.createElement('section');
            templates.className = 'u-stack-3';

            const templatesHeading = document.createElement('h3');
            templatesHeading.className = 'section-title';
            templatesHeading.textContent = 'Or start with a template';

            const grid = document.createElement('div');
            grid.className = 'u-grid-auto';

            selectable.slice(0, 4).forEach((template) => {
                const card = document.createElement('button');
                card.type = 'button';
                card.className = 'card card--interactive card--compact starter-template-card';
                card.innerHTML = '<div class="card-body u-stack-1">'
                    + '<strong class="card-title">' + escapeHtml(template.label || template.id) + '</strong>'
                    + '<span class="u-text-sm u-fg-secondary">' + escapeHtml(template.description || '') + '</span>'
                    + '</div>';
                card.addEventListener('click', function () {
                    installStarterTemplate(template.id, card);
                });
                grid.appendChild(card);
            });

            templates.appendChild(templatesHeading);
            templates.appendChild(grid);
            wrap.appendChild(templates);
        }

        return wrap;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text ?? '');
        return div.innerHTML;
    }

    async function installStarterTemplate(templateId, buttonEl) {
        if (!templateId || buttonEl?.disabled) return;
        const originalMarkup = buttonEl?.innerHTML;
        if (buttonEl) {
            buttonEl.disabled = true;
            buttonEl.setAttribute('aria-busy', 'true');
        }
        try {
            const response = await fetch(apiUrl('/api/demo.php?action=install_starter'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    action: 'install_starter',
                    template: templateId,
                    starter_template: templateId,
                    _csrf: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                })
            });
            const data = await response.json();
            if (data.status === 'ok' || data.success) {
                window.location.reload();
                return;
            }
            throw new Error(data.message || 'Install failed');
        } catch (error) {
            if (window.BirklyFeedback && typeof BirklyFeedback.toast === 'function') {
                BirklyFeedback.toast(error.message || 'Failed to install template', 'error');
            } else {
                console.error(error);
            }
            if (buttonEl) {
                buttonEl.disabled = false;
                buttonEl.removeAttribute('aria-busy');
                if (typeof originalMarkup === 'string') {
                    buttonEl.innerHTML = originalMarkup;
                }
            }
        }
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
            ? icon('caret-up', { size: 14 })
            : icon('caret-down', { size: 14 });
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
        grid.className = 'u-grid-auto ungrouped-collections drop-zone';
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

    function renderCollectionIcon(collection, header) {
        if (collection.icon) {
            const icon = document.createElement('span');
            icon.className = 'collection-icon';
            icon.textContent = collection.icon;
            icon.setAttribute('aria-hidden', 'true');
            header.appendChild(icon);
        } else if (collection.icon_key) {
            const icon = document.createElement('span');
            icon.className = 'collection-icon-key';
            icon.textContent = collection.icon_key.slice(0, 2).toUpperCase();
            icon.title = collection.icon_key;
            header.appendChild(icon);
        }
    }

    function createCollectionCard(collection, groupId) {
        const card = document.createElement('div');
        card.className = 'card collection-card' + (currentView === 'list' ? ' collection-card--list-row' : '');
        card.dataset.collectionSlug = collection.slug;
        card.dataset.groupId = groupId || 'ungrouped';

        const header = document.createElement('div');
        header.className = 'collection-header';

        header.appendChild(createDragHandle('Drag to reorder'));
        renderCollectionIcon(collection, header);

        const title = document.createElement('h3');
        title.className = 'card-title collection-title';
        title.textContent = collection.name || 'Untitled';
        header.appendChild(title);

        const descText = (collection.description || '').trim();
        let description = null;
        if (descText) {
            description = document.createElement('p');
            description.className = 'collection-description';
            description.textContent = descText;
        }

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
        menuButton.innerHTML = icon('dots-three-vertical', { size: 16 });
        menuButton.addEventListener('dragstart', (e) => {
            e.stopPropagation();
            e.preventDefault();
        });

        const menuDropdown = document.createElement('div');
        menuDropdown.className = 'dropdown-menu dropdown-menu-right collection-menu-dropdown';

        const viewEntriesItem = document.createElement('a');
        viewEntriesItem.className = 'dropdown-item';
        viewEntriesItem.href = adminUrl('entries', { collection: collection.slug });
        viewEntriesItem.innerHTML = icon('file-text', { size: 16, class: 'dropdown-item-icon' }) + '<span class="dropdown-item-label">View Entries</span>';

        const editItem = document.createElement('a');
        editItem.className = 'dropdown-item';
        editItem.href = adminUrl('collection-editor', { collection: collection.slug });
        editItem.innerHTML = icon('pencil-simple', { size: 16, class: 'dropdown-item-icon' }) + '<span class="dropdown-item-label">Edit Collection</span>';

        const duplicateItem = document.createElement('button');
        duplicateItem.type = 'button';
        duplicateItem.className = 'dropdown-item';
        duplicateItem.innerHTML = icon('copy', { size: 16, class: 'dropdown-item-icon' }) + '<span class="dropdown-item-label">Duplicate Collection</span>';
        duplicateItem.onclick = (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeDropdown(duplicateItem);
            duplicateCollection(collection);
        };

        const deleteItem = document.createElement('button');
        deleteItem.type = 'button';
        deleteItem.className = 'dropdown-item dropdown-item-danger';
        deleteItem.innerHTML = icon('trash', { size: 16, class: 'dropdown-item-icon' }) + '<span class="dropdown-item-label">Delete Collection</span>';
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
        if (description) {
            card.appendChild(description);
        }
        card.appendChild(footer);

        card.addEventListener('click', async function (e) {
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
            BirklyFeedback.toast('Error: Collection ID not found. Cannot duplicate collection.', 'error')
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
                BirklyFeedback.toast('Failed to duplicate collection: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch((error) => {
            console.error('Error duplicating collection:', error);
            BirklyFeedback.toast('Error duplicating collection. Please try again.', 'error')
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
            BirklyFeedback.toast('Please enter a group name', 'warning')
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

    async function deleteGroup(groupId) {
        if (!(await BirklyFeedback.confirm('Are you sure you want to delete this group? Collections will be moved to ungrouped.'))) {
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

    async function deleteCollection(collection) {
        const collectionId = collection.id;
        if (!collectionId) {
            BirklyFeedback.toast('Error: Collection ID not found. Cannot delete collection.', 'error')
            return;
        }

        const confirmMessage = `Are you sure you want to delete "${collection.name || 'this collection'}"? This will also delete all ${collection.entries_count || 0} entries in this collection. This action cannot be undone.`;
        if (!(await BirklyFeedback.confirm(confirmMessage))) return;

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
                BirklyFeedback.toast('Failed to delete collection: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch((error) => {
            console.error('Error deleting collection:', error);
            BirklyFeedback.toast('Error deleting collection. Please try again.', 'error')
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
                BirklyFeedback.toast('Failed to save groups: ' + (data.message || 'Unknown error'), 'error');
            }
        })
        .catch((error) => {
            console.error('Error saving groups:', error);
            BirklyFeedback.toast('Error saving groups. Please try again.', 'error')
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
                collapseBtn.innerHTML = icon('caret-down', { size: 14 });
            }
            localStorage.setItem(`group-collapsed-${groupId}`, 'false');
        } else {
            groupSection.classList.add('collapsed');
            if (grid) grid.style.display = 'none';
            if (collapseBtn) {
                collapseBtn.title = 'Expand Group';
                collapseBtn.innerHTML = icon('caret-up', { size: 14 });
            }
            localStorage.setItem(`group-collapsed-${groupId}`, 'true');
        }
    }
})();
