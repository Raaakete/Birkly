/**
 * Settings page — tabs, global save, roles, users, domains, audit log, storage helpers.
 */

function apiUrl(path) {
    return (window.Birkly && typeof Birkly.url === 'function') ? Birkly.url(path) : path;
}

function getCsrfToken() {
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    if (metaToken) {
        return metaToken.getAttribute('content');
    }
    return window.csrfToken || '';
}

function escapeHtml(text) {
    if (text == null) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function showMessage(message, type) {
    if (globalThis.BirklyFeedback && typeof BirklyFeedback.toast === 'function') {
        BirklyFeedback.toast(message, type === 'success' ? 'success' : type === 'error' ? 'error' : type || 'info');
        return;
    }
    const messageEl = document.getElementById('settings-message');
    if (!messageEl) return;
    messageEl.textContent = message;
    messageEl.className = 'main-actions-message message settings-toast message-'
        + (type === 'success' ? 'success' : type === 'error' ? 'error' : type || 'info');
    messageEl.hidden = false;
    setTimeout(function () {
        messageEl.hidden = true;
    }, 5000);
}

function stT(key, fallback, params) {
    if ((typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function')) {
        return Birkly.i18n.t('admin.' + key, fallback, params);
    }
    let text = fallback != null ? fallback : key;
    if (params && typeof params === 'object') {
        Object.keys(params).forEach(function (name) {
            var value = String(params[name]);
            text = String(text).split('{{' + name + '}}').join(value);
            text = text.split('{' + name + '}').join(value);
        });
    }
    return text;
}

function getSettingsUsers() {
    const el = document.getElementById('settings-users-data');
    if (!el) return [];
    try {
        return JSON.parse(el.textContent || '[]');
    } catch (e) {
        console.error('Failed to parse settings users data:', e);
        return [];
    }
}

// Role management functions - MUST be defined first, before any IIFE
let availableResources = ['collections', 'media', 'users', 'settings'];
let availableActions = ['view', 'edit', 'create', 'delete'];

function createPermissionsGrid(containerId, permissions = {}) {
    const container = document.getElementById(containerId);
    if (!container) {
        console.error('Container not found:', containerId);
        return;
    }

    // Verify container is inside a form
    if (!container.closest('form')) {
        console.warn('Permissions grid container is not inside a form');
    }

    container.innerHTML = '';
    container.classList.add('permission-matrix');
    container.setAttribute('role', 'table');

    const actionLabel = action => stT('settings_js_perm_' + action, action.charAt(0).toUpperCase() + action.slice(1));

    const head = document.createElement('div');
    head.className = 'permission-head';
    head.setAttribute('role', 'row');
    const corner = document.createElement('span');
    corner.setAttribute('role', 'columnheader');
    corner.textContent = stT('settings_js_perm_resource', 'Resource');
    head.appendChild(corner);
    availableActions.forEach(action => {
        const cell = document.createElement('span');
        cell.setAttribute('role', 'columnheader');
        cell.textContent = actionLabel(action);
        head.appendChild(cell);
    });
    container.appendChild(head);

    availableResources.forEach(resource => {
        const resourceLabel = stT('settings_js_perm_' + resource, resource);
        const row = document.createElement('div');
        row.className = 'permission-row';
        row.setAttribute('role', 'row');

        const name = document.createElement('span');
        name.className = 'permission-name';
        name.setAttribute('role', 'rowheader');
        name.textContent = resourceLabel;
        row.appendChild(name);

        availableActions.forEach(action => {
            const cell = document.createElement('label');
            cell.className = 'permission-cell';
            cell.setAttribute('role', 'cell');

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'check';
            checkbox.name = `permissions[${resource}][]`;
            checkbox.value = action;
            checkbox.setAttribute('data-resource', resource);
            checkbox.setAttribute('aria-label', resourceLabel + ': ' + actionLabel(action));
            checkbox.checked = hasPermission(permissions, resource, action);
            checkbox.addEventListener('click', function (e) {
                e.stopPropagation();
            });

            cell.appendChild(checkbox);
            row.appendChild(cell);
        });

        container.appendChild(row);
    });
}

function hasPermission(permissions, resource, action) {
    if (!permissions) return false;
    if (permissions['*'] && permissions['*'].includes(action)) return true;
    if (permissions[resource] && permissions[resource].includes(action)) return true;
    return false;
}

async function populateRoleSelects() {
    const selectIds = ['invite-role', 'user-role', 'edit-user-role'];
    try {
        const response = await fetch(apiUrl('/api/roles.php?action=list_roles'));
        const result = await response.json();
        if (result.status !== 'ok' || !Array.isArray(result.roles)) {
            return;
        }
        selectIds.forEach(function (selectId) {
            const select = document.getElementById(selectId);
            if (!select) return;
            const currentValue = select.value;
            select.innerHTML = '';
            result.roles.forEach(function (role) {
                const opt = document.createElement('option');
                opt.value = role.id || role.name || '';
                opt.textContent = role.name || role.id || '';
                select.appendChild(opt);
            });
            if (currentValue) {
                select.value = currentValue;
            }
        });
    } catch (error) {
        console.error('Failed to load roles for selects:', error);
    }
}

document.addEventListener('DOMContentLoaded', populateRoleSelects);

function collectPermissions(form) {
    const permissions = {};
    if (!form) {
        console.error('Form is null in collectPermissions');
        return permissions;
    }
    
    availableResources.forEach(resource => {
        // Use attribute selector with escaped brackets
        const selector = `input[name="permissions[${resource}][]"]:checked`;
        const checkboxes = form.querySelectorAll(selector);
        
        if (checkboxes.length > 0) {
            permissions[resource] = Array.from(checkboxes).map(cb => cb.value);
        }
    });
    
    return permissions;
}

let activePluginsForRoles = [];

async function loadActivePluginsForRoles() {
    try {
        const response = await fetch(apiUrl('/api/roles.php?action=list_role_plugins'));
        const result = await response.json();
        if (result.status === 'ok' && Array.isArray(result.plugins)) {
            activePluginsForRoles = result.plugins;
        }
    } catch (error) {
        console.error('Failed to load plugins for role editor:', error);
    }
}

function populatePluginAccessGrid(gridId, role) {
    const grid = document.getElementById(gridId);
    if (!grid) {
        return;
    }
    const denyList = role && role.plugin_access && Array.isArray(role.plugin_access.deny)
        ? role.plugin_access.deny
        : [];
    if (!activePluginsForRoles.length) {
        grid.innerHTML = '<p class="u-text-sm u-fg-secondary">' + escapeHtml(stT('settings_js_no_active_plugins', 'No active plugins')) + '</p>';
        return;
    }
    grid.innerHTML = activePluginsForRoles.map(function (plugin) {
        const id = plugin.id || '';
        const name = plugin.name || id;
        const allowed = denyList.indexOf(id) === -1;
        return '<label class="check"><input type="checkbox" name="plugin_access_allow" value="'
            + escapeHtml(id) + '"' + (allowed ? ' checked' : '') + '> '
            + escapeHtml(name) + '</label>';
    }).join('');
}

function collectPluginAccess(form) {
    if (!form || !activePluginsForRoles.length) {
        return null;
    }
    const allowed = Array.from(form.querySelectorAll('input[name="plugin_access_allow"]:checked'))
        .map(function (input) { return input.value; });
    const deny = activePluginsForRoles
        .map(function (plugin) { return plugin.id; })
        .filter(function (id) { return id && allowed.indexOf(id) === -1; });
    if (!deny.length) {
        return null;
    }
    return { mode: 'all_except', deny: deny };
}

document.addEventListener('DOMContentLoaded', function () {
    loadActivePluginsForRoles();
});

function openAddRoleModal() {
    const modal = document.getElementById('add-role-modal');
    if (!modal) {
        console.error('Add role modal not found');
        showMessage(stT('settings_js_role_modal_missing', 'Role modal not found. Please refresh the page.'), 'error');
        return;
    }
    modal.style.display = 'flex';
    modal.classList.add('active');
    const form = document.getElementById('add-role-form');
    if (form) form.reset();
    createPermissionsGrid('add-permissions-grid');
    loadActivePluginsForRoles().then(function () {
        populatePluginAccessGrid('add-plugin-access-grid', null);
    });
}

function closeAddRoleModal() {
    const modal = document.getElementById('add-role-modal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 300);
    }
}

async function editRole(roleId) {
    const modal = document.getElementById('edit-role-modal');
    if (!modal) {
        console.error('Edit role modal not found');
        showMessage(stT('settings_js_edit_role_modal_missing', 'Edit role modal not found. Please refresh the page.'), 'error');
        return;
    }
    
    // Show modal
    modal.style.display = 'flex';
    modal.classList.add('active');
    
    // Load role data
    try {
        const response = await fetch(apiUrl(`/api/roles.php?action=get_role&id=${encodeURIComponent(roleId)}`));
        const result = await response.json();
        
        if (result.status === 'ok' && result.role) {
            const role = result.role;
            
            // Fill form
            document.getElementById('edit-role-id').value = role.id || roleId;
            document.getElementById('edit-role-name').value = role.name || '';
            document.getElementById('edit-role-description').value = role.description || '';
            
            // Create permissions grid with existing permissions
            createPermissionsGrid('edit-permissions-grid', role.permissions || {});
            await loadActivePluginsForRoles();
            populatePluginAccessGrid('edit-plugin-access-grid', role);
            
        } else {
            showMessage(stT('settings_js_err_loading_role', 'Error loading role:') + ' ' + (result.message || stT('ai_conn_js_unknown_error', 'Unknown error')), 'error');
            closeEditRoleModal();
        }
    } catch (error) {
        console.error('Error loading role:', error);
        showMessage(stT('settings_js_err_loading_role', 'Error loading role:') + ' ' + error.message, 'error');
        closeEditRoleModal();
    }
}

function closeEditRoleModal() {
    const modal = document.getElementById('edit-role-modal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => modal.style.display = 'none', 300);
    }
}

async function updateRole() {
    const form = document.getElementById('edit-role-form');
    if (!form) {
        console.error('Edit form not found');
        showMessage(stT('settings_js_err_form_missing', 'Error: Form not found'), 'error');
        return;
    }
    
    const roleId = document.getElementById('edit-role-id').value;
    const nameInput = form.querySelector('#edit-role-name');
    const descriptionInput = form.querySelector('#edit-role-description');
    
    if (!nameInput || !nameInput.value.trim()) {
        showMessage(stT('settings_js_err_role_name_required', 'Error: Role name is required'), 'error');
        nameInput?.focus();
        return;
    }
    
    const roleName = nameInput.value.trim();
    const roleDescription = descriptionInput ? descriptionInput.value.trim() : '';
    
    // Collect permissions
    const permissions = collectPermissions(form);
    const pluginAccess = collectPluginAccess(form);
    
    const rolePayload = {
        id: roleId,
        name: roleName,
        description: roleDescription,
        permissions: permissions,
    };
    if (pluginAccess) {
        rolePayload.plugin_access = pluginAccess;
    } else {
        rolePayload.plugin_access = { mode: 'all' };
    }

    const roleData = {
        role: rolePayload,
        _csrf: getCsrfToken()
    };
    
    
    try {
        const response = await fetch(apiUrl('/api/roles.php?action=update_role'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(roleData)
        });
        
        const result = await response.json();
        
        if (result.status === 'ok') {
            showMessage(stT('settings_js_role_updated', 'Role updated successfully!'), 'success');
            closeEditRoleModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('ai_conn_js_unknown_error', 'Unknown error')), 'error');
        }
    } catch (error) {
        console.error('Error updating role:', error);
        showMessage(stT('settings_js_err_updating_role', 'Error updating role:') + ' ' + error.message, 'error');
    }
}

async function createRole() {
    const form = document.getElementById('add-role-form');
    if (!form) {
        console.error('Form not found');
        showMessage(stT('settings_js_err_form_missing', 'Error: Form not found'), 'error');
        return;
    }
    
    // Get form values
    const nameInput = form.querySelector('#role-name');
    const descriptionInput = form.querySelector('#role-description');
    
    if (!nameInput || !nameInput.value.trim()) {
        showMessage(stT('settings_js_err_role_name_required', 'Error: Role name is required'), 'error');
        nameInput?.focus();
        return;
    }
    
    const roleName = nameInput.value.trim();
    const roleDescription = descriptionInput ? descriptionInput.value.trim() : '';
    
    // Collect permissions
    const permissions = collectPermissions(form);
    const pluginAccess = collectPluginAccess(form);

    const rolePayload = {
        id: roleName.toLowerCase().replace(/[^a-z0-9]/g, '-'),
        name: roleName,
        description: roleDescription,
        permissions: permissions,
    };
    if (pluginAccess) {
        rolePayload.plugin_access = pluginAccess;
    }

    const roleData = {
        action: 'create_role',
        role: rolePayload,
        _csrf: getCsrfToken()
    };
    
    
    try {
        // API expects action in query string, not in body
        const response = await fetch(apiUrl('/api/roles.php?action=create_role'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                role: roleData.role,
                _csrf: roleData._csrf
            })
        });
        
        const result = await response.json();
        
        if (result.status === 'ok') {
            showMessage(stT('settings_js_role_created', 'Role created successfully!'), 'success');
            closeAddRoleModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('ai_conn_js_unknown_error', 'Unknown error')), 'error');
        }
    } catch (error) {
        console.error('Error creating role:', error);
        showMessage(stT('settings_js_err_creating_role', 'Error creating role:') + ' ' + error.message, 'error');
    }
}

async function deleteRole(roleId) {
    if (!(await BirklyFeedback.confirm(stT('settings_js_confirm_delete_role', 'Are you sure you want to delete this role? This action cannot be undone.')))) {
        return;
    }
    
    // API expects action in query string, not in body
    fetch(apiUrl('/api/roles.php?action=delete_role'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: roleId,
            _csrf: getCsrfToken()
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.status === 'ok') {
            showMessage(stT('settings_js_role_deleted', 'Role deleted successfully!'), 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + result.message, 'error');
        }
    })
    .catch(error => {
        showMessage(stT('settings_js_err_deleting_role', 'Error deleting role:') + ' ' + error.message, 'error');
    });
}

// Ensure functions are available for delegated data-admin-action handlers
window.openAddRoleModal = openAddRoleModal;
window.closeAddRoleModal = closeAddRoleModal;
window.createRole = createRole;
window.editRole = editRole;
window.closeEditRoleModal = closeEditRoleModal;
window.updateRole = updateRole;
window.deleteRole = deleteRole;
window.openInviteUserModal = openInviteUserModal;
window.closeInviteUserModal = closeInviteUserModal;
window.sendInvitation = sendInvitation;
window.createInvitationLink = createInvitationLink;
window.copyInviteLink = copyInviteLink;
window.loadTeamInvitations = loadTeamInvitations;
window.copyTeamInvitationLink = copyTeamInvitationLink;
window.resendTeamInvitation = resendTeamInvitation;
window.cancelTeamInvitation = cancelTeamInvitation;
window.openAddUserModal = openAddUserModal;
window.closeAddUserModal = closeAddUserModal;
window.openEditUserModal = openEditUserModal;
window.closeEditUserModal = closeEditUserModal;
window.editUser = editUser;
window.createUser = createUser;
window.updateUser = updateUser;
window.confirmDeleteUser = confirmDeleteUser;
window.deleteUserFromModal = deleteUserFromModal;
window.revokeUserMcpSessions = revokeUserMcpSessions;
window.openAddDomainModal = openAddDomainModal;
window.closeAddDomainModal = closeAddDomainModal;
window.closeEditDomainModal = closeEditDomainModal;
window.registerDomain = registerDomain;
window.updateDomain = updateDomain;
window.addDenylistResource = addDenylistResource;
window.testDatabaseConnection = testDatabaseConnection;
window.migrateStorage = migrateStorage;

// Settings tabs, global save, dirty state, and settings search
(function () {
    var VALID_TABS = [
        'general', 'site-delivery', 'project', 'team-access', 'security', 'ai', 'connections', 'integrations', 'analytics', 'system'
    ];

    var TAB_ALIASES = {
        domains: 'site-delivery',
        hosting: 'site-delivery',
        redirects: 'site-delivery',
        'project-server-rendering': 'project',
        'project-overview': 'project',
        'project-files': 'project',
        'project-rendering': 'project',
        'project-git': 'project',
        'project-activity': 'project',
        webhooks: 'integrations',
        security: 'security',
        'users-roles': 'team-access',
        roles: 'team-access',
        monitoring: 'system',
        storage: 'system',
        api: 'integrations',
        'activity-log': 'system',
        'ai-integrations': 'ai',
        'ai-connections': 'ai',
        'mcp-connections': 'ai',
        'outbound-connections': 'ai',
        'inbound-mcp': 'ai',
        analytics: 'analytics',
        tracking: 'analytics',
        'analytics-presets': 'analytics',
        'analytics-report': 'analytics',
        developer: 'system',
        'developer-mode': 'system'
    };

    var LEGACY_AI_HASH_MAP = {
        'ai-kind-assistant': 'ai-kind-assistant',
        'ai-kind-builtin': 'ai-kind-assistant',
        'ai-kind-personal': 'ai-kind-personal',
        'ai-kind-service': 'ai-kind-service',
        'outbound-connections': 'ai-kind-assistant',
        'inbound-mcp': 'ai-kind-personal',
        'mcp-connections': 'ai-kind-personal',
        'ai-connections': 'ai-kind-personal',
        'ai-integrations': 'ai'
    };

    var INDEPENDENT_SAVE_TABS = [
        'site-delivery', 'project', 'team-access', 'ai', 'connections', 'integrations'
    ];

    var GLOBAL_SAVE_TABS = {
        general: { formIds: ['general-form'], label: 'Save general settings' },
        security: { formIds: ['security-form'], label: 'Save security settings' },
        analytics: { formIds: ['analytics-settings-form'], label: 'Save analytics settings' },
        system: {
            formIds: ['storage-form', 'monitoring-form'],
            label: 'Save system settings'
        }
    };

    var currentTab = 'general';
    var formSnapshots = {};
    var dirtyTabs = {};
    var beforeUnloadBound = false;

    function normalizeSearchValue(value) {
        return String(value || '').trim().toLowerCase();
    }

    function isTrackedGlobalTab(tabId) {
        return !!GLOBAL_SAVE_TABS[tabId] && INDEPENDENT_SAVE_TABS.indexOf(tabId) < 0;
    }

    function hasDirtyTrackedTabs() {
        return Object.keys(GLOBAL_SAVE_TABS).some(function (tabId) {
            return !!dirtyTabs[tabId];
        });
    }

    /** Unsaved badge / tab chrome — only the tab you are viewing (avoids hidden-tab false positives). */
    function isCurrentTabDirtyForUi() {
        return isTrackedGlobalTab(currentTab) && !!dirtyTabs[currentTab];
    }

    function scheduleCaptureTabBaseline(tabId, delayMs) {
        if (!isTrackedGlobalTab(tabId)) {
            return;
        }
        var wait = typeof delayMs === 'number' ? delayMs : 450;
        setTimeout(function () {
            captureFormSnapshots(tabId);
        }, wait);
    }

    function normalizeTabId(tabId) {
        if (!tabId) return null;
        if (TAB_ALIASES[tabId]) {
            return TAB_ALIASES[tabId];
        }
        return tabId;
    }

    function resolveSettingsHash(hash) {
        if (!hash) return { tab: null, scrollTo: null, legacyHash: null };

        var lower = String(hash).toLowerCase();
        if (LEGACY_AI_HASH_MAP[lower] || lower.indexOf('ai-kind-') === 0) {
            return {
                tab: 'ai',
                scrollTo: LEGACY_AI_HASH_MAP[lower] || lower,
                legacyHash: hash
            };
        }

        var normalized = normalizeTabId(hash);
        if (!normalized) {
            return { tab: null, scrollTo: null, legacyHash: null };
        }

        if (normalized !== hash) {
            return { tab: normalized, scrollTo: hash, legacyHash: hash };
        }

        return { tab: normalized, scrollTo: null, legacyHash: null };
    }

    function parseSettingsUrl() {
        var params = new URLSearchParams(window.location.search);
        var tab = params.get('tab');
        var scrollTo = null;
        var hash = window.location.hash.replace('#', '');

        var resolvedHash = resolveSettingsHash(hash);

        if (!tab) {
            if (resolvedHash.tab) {
                tab = resolvedHash.tab;
                scrollTo = resolvedHash.scrollTo;
            }
        } else {
            tab = normalizeTabId(tab);
            if (resolvedHash.tab) {
                if (resolvedHash.tab !== tab) {
                    tab = resolvedHash.tab;
                }
                scrollTo = resolvedHash.scrollTo;
            }
        }

        if (!tab || VALID_TABS.indexOf(tab) < 0) {
            tab = 'general';
        }

        return { tab: tab, scrollTo: scrollTo };
    }

    function buildSettingsUrl(tab) {
        var params = new URLSearchParams(window.location.search);
        params.set('page', 'settings');
        params.set('tab', tab);
        params.delete('area');
        return window.location.pathname + '?' + params.toString();
    }

    function normalizeSettingsUrl(parsed) {
        var params = new URLSearchParams(window.location.search);
        var rawTab = params.get('tab');
        var hash = window.location.hash.replace('#', '');
        var resolvedHash = resolveSettingsHash(hash);
        var needsRedirect = params.has('area')
            || (rawTab && TAB_ALIASES[rawTab])
            || (hash && (!params.get('tab') || !!resolvedHash.scrollTo || normalizeTabId(hash) !== hash));

        if (!needsRedirect) {
            return false;
        }

        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', buildSettingsUrl(parsed.tab));
        }
        return true;
    }

    function scrollToSettingsSection(sectionId) {
        if (!sectionId) return;
        var section = document.getElementById(sectionId);
        if (!section) return;
        setTimeout(function () {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 150);
    }

    function refreshTabsV2Picker() {
        var tabsRoot = document.querySelector('.settings-tabs.tabs-v2-root');
        if (tabsRoot && window.Birkly && window.Birkly.TabsV2 && typeof window.Birkly.TabsV2.refresh === 'function') {
            window.Birkly.TabsV2.refresh(tabsRoot);
        }
    }

    function getGlobalSaveFormIds(tabId) {
        var cfg = GLOBAL_SAVE_TABS[tabId];
        return cfg && cfg.formIds ? cfg.formIds.slice() : [];
    }

    function serializeForm(form) {
        if (!form) return '';

        var parts = [];
        Array.prototype.forEach.call(form.elements || [], function (field) {
            if (!field || !field.name || field.disabled) {
                return;
            }

            if (field.type === 'file') {
                var files = Array.prototype.map.call(field.files || [], function (file) {
                    return [file.name, file.size, file.lastModified].join(':');
                });
                parts.push(field.name + '=' + files.join(','));
                return;
            }

            if (field.type === 'password') {
                // Ignore until the user types — stops autofill / focus quirks from marking Settings dirty.
                if (field.dataset.settingsUserEdited !== '1') {
                    return;
                }
                parts.push(field.name + '=' + field.value);
                return;
            }

            if (field.type === 'checkbox') {
                parts.push(field.name + '=' + (field.checked ? '1' : '0'));
                return;
            }

            if (field.type === 'radio') {
                if (field.checked) {
                    parts.push(field.name + '=' + field.value);
                }
                return;
            }

            if (field.tagName === 'SELECT' && field.multiple) {
                var selectedValues = Array.prototype.map.call(field.selectedOptions || [], function (option) {
                    return option.value;
                }).sort();
                parts.push(field.name + '=' + selectedValues.join(','));
                return;
            }

            parts.push(field.name + '=' + field.value);
        });

        return parts.sort().join('&');
    }

    function serializeTabForms(tabId) {
        if (!isTrackedGlobalTab(tabId)) {
            return '';
        }

        var parts = [];
        getGlobalSaveFormIds(tabId).forEach(function (formId) {
            parts.push(serializeForm(document.getElementById(formId)));
        });
        return parts.join('||');
    }

    function updateTabDirtyIndicators() {
        var tabButtons = getSettingsTabButtons();
        for (var i = 0; i < tabButtons.length; i++) {
            var tabId = tabButtons[i].getAttribute('data-tab');
            tabButtons[i].classList.toggle('tab--dirty', !!dirtyTabs[tabId]);
        }
    }

    function updateSaveBar(tabId) {
        var bar = document.querySelector('.main-actions-bar--sticky');
        var saveBtn = document.getElementById('global-save-settings-btn');
        var dirtyBadge = document.getElementById('settings-dirty-badge');
        if (!bar || !saveBtn) return;

        bar.classList.remove('is-hidden');
        saveBtn.hidden = false;
        saveBtn.disabled = !hasDirtyTrackedTabs();
        saveBtn.textContent = stT('settings_js_save_settings_btn', 'Save changes');

        if (dirtyBadge) {
            dirtyBadge.hidden = !isCurrentTabDirtyForUi();
        }
    }

    function captureFormSnapshots(tabId) {
        if (tabId) {
            if (isTrackedGlobalTab(tabId)) {
                formSnapshots[tabId] = serializeTabForms(tabId);
                dirtyTabs[tabId] = false;
            }
            updateTabDirtyIndicators();
            updateSaveBar(currentTab);
            return;
        }

        Object.keys(GLOBAL_SAVE_TABS).forEach(function (trackedTabId) {
            formSnapshots[trackedTabId] = serializeTabForms(trackedTabId);
            dirtyTabs[trackedTabId] = false;
        });
        updateTabDirtyIndicators();
        updateSaveBar(currentTab);
    }

    function checkDirtyState(tabId) {
        tabId = normalizeTabId(tabId) || currentTab;
        if (!isTrackedGlobalTab(tabId)) {
            return false;
        }

        if (formSnapshots[tabId] === undefined) {
            formSnapshots[tabId] = serializeTabForms(tabId);
            dirtyTabs[tabId] = false;
            updateTabDirtyIndicators();
            updateSaveBar(currentTab);
            return false;
        }

        dirtyTabs[tabId] = serializeTabForms(tabId) !== formSnapshots[tabId];
        updateTabDirtyIndicators();
        updateSaveBar(currentTab);
        bindBeforeUnload();
        return dirtyTabs[tabId];
    }

    function bindBeforeUnload() {
        if (beforeUnloadBound) return;
        beforeUnloadBound = true;
        window.addEventListener('beforeunload', function (e) {
            if (!hasDirtyTrackedTabs()) return;
            e.preventDefault();
            e.returnValue = '';
        });
    }

    function bindDirtyTracking() {
        Object.keys(GLOBAL_SAVE_TABS).forEach(function (tabId) {
            getGlobalSaveFormIds(tabId).forEach(function (formId) {
                var form = document.getElementById(formId);
                if (!form || form.dataset.dirtyBound === '1') return;
                form.dataset.dirtyBound = '1';
                form.addEventListener('input', function (event) {
                    var target = event.target;
                    if (target && target.type === 'password') {
                        target.dataset.settingsUserEdited = '1';
                    }
                    checkDirtyState(tabId);
                });
                form.addEventListener('change', function (event) {
                    var target = event.target;
                    if (target && target.type === 'password') {
                        target.dataset.settingsUserEdited = '1';
                    }
                    checkDirtyState(tabId);
                });
            });
        });
    }

    function getSettingsTabButtons() {
        return document.querySelectorAll('.settings-tabs .tab[data-tab]');
    }

    function getSettingsTabPanes() {
        var panes = Array.prototype.slice.call(
            document.querySelectorAll('.settings-tab-panels > .tab-pane')
        );
        // Include known tab panes by id so tab switching still works when markup
        // places a pane outside .settings-tab-panels (legacy/cached templates).
        VALID_TABS.forEach(function (tabId) {
            var pane = document.getElementById(tabId);
            if (pane && panes.indexOf(pane) < 0) {
                panes.push(pane);
            }
        });
        return panes;
    }

    function setActiveSettingsPane(tabPane, tabPanes) {
        if (!tabPane) {
            return;
        }
        var paneList = Array.prototype.slice.call(tabPanes || []);
        if (paneList.indexOf(tabPane) < 0) {
            paneList.push(tabPane);
        }
        paneList.forEach(function (pane) {
            var isActive = pane === tabPane;
            pane.classList.toggle('active', isActive);
            if (window.Birkly && window.Birkly.TabsA11y) {
                window.Birkly.TabsA11y.syncTabPanel(pane, isActive);
            }
        });
        // If a pane was rendered inside another tab pane, keep ancestors visible.
        var parent = tabPane.parentElement;
        while (parent) {
            if (parent.classList && parent.classList.contains('tab-pane')) {
                parent.classList.add('active');
            }
            parent = parent.parentElement;
        }
    }

    function applySettingsSearch() {
        var searchInput = document.getElementById('settings-search-input');
        var query = normalizeSearchValue(searchInput && searchInput.value);
        var tabButtons = Array.prototype.slice.call(getSettingsTabButtons());
        var tabPanes = Array.prototype.slice.call(getSettingsTabPanes());

        if (!query) {
            tabButtons.forEach(function (button) {
                button.hidden = false;
            });
            tabPanes.forEach(function (pane) {
                Array.prototype.forEach.call(pane.querySelectorAll('[data-settings-search]'), function (section) {
                    section.hidden = false;
                });
                pane.dataset.searchVisible = '1';
            });
            refreshTabsV2Picker();
            return;
        }

        tabPanes.forEach(function (pane) {
            var searchableSections = Array.prototype.slice.call(pane.querySelectorAll('[data-settings-search]'));
            var paneHasMatch = !query;
            var isCurrentPane = pane.id === currentTab;

            searchableSections.forEach(function (section) {
                var haystack = normalizeSearchValue(section.getAttribute('data-settings-search'));
                var matches = !query || haystack.indexOf(query) >= 0;
                // Never hide every section in the tab the user is viewing.
                section.hidden = isCurrentPane ? false : !matches;
                if (matches) {
                    paneHasMatch = true;
                }
            });

            if (isCurrentPane && query) {
                paneHasMatch = true;
            }

            pane.dataset.searchVisible = paneHasMatch ? '1' : '0';
        });

        tabButtons.forEach(function (button) {
            var tabId = button.getAttribute('data-tab');
            var pane = document.getElementById(tabId);
            var buttonHaystack = normalizeSearchValue(button.getAttribute('data-settings-search') || button.textContent);
            var isDirtyCurrentTab = tabId === currentTab && !!dirtyTabs[tabId];
            var matches = isDirtyCurrentTab || !query || buttonHaystack.indexOf(query) >= 0 || !!(pane && pane.dataset.searchVisible === '1');
            button.hidden = !matches;
        });

        if (query) {
            if (dirtyTabs[currentTab]) {
                refreshTabsV2Picker();
                return;
            }
            var activeButton = document.querySelector('.settings-tabs .tab.active[data-tab="' + currentTab + '"]');
            if (!activeButton || activeButton.hidden) {
                var nextButton = null;
                for (var i = 0; i < tabButtons.length; i++) {
                    if (!tabButtons[i].hidden) {
                        nextButton = tabButtons[i];
                        break;
                    }
                }
                if (nextButton && nextButton.getAttribute('data-tab') !== currentTab) {
                    activateTab(nextButton.getAttribute('data-tab'), getSettingsTabButtons(), getSettingsTabPanes(), null, { force: true });
                }
            }
        }

        refreshTabsV2Picker();
    }

    function initSettingsSearch() {
        var searchInput = document.getElementById('settings-search-input');
        if (!searchInput || searchInput.dataset.searchBound === '1') {
            return;
        }
        searchInput.dataset.searchBound = '1';
        searchInput.addEventListener('input', applySettingsSearch);
        searchInput.addEventListener('search', applySettingsSearch);
        applySettingsSearch();
    }

    var AI_CONNECTIONS_INTRO_SEEN_KEY = 'birkly_ai_connections_intro_seen';

    function aiConnectionsIntroSeen() {
        try { return localStorage.getItem(AI_CONNECTIONS_INTRO_SEEN_KEY) === '1'; } catch (e) { return false; }
    }

    function markAiConnectionsIntroSeen() {
        try { localStorage.setItem(AI_CONNECTIONS_INTRO_SEEN_KEY, '1'); } catch (e) { /* ignore */ }
    }

    function closeAiConnectionsIntroModal() {
        var modal = document.getElementById('ai-connections-intro-modal');
        if (!modal) return;
        markAiConnectionsIntroSeen();
        modal.classList.remove('active');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
    }

    function maybeShowAiConnectionsIntro() {
        if (aiConnectionsIntroSeen()) return;
        var modal = document.getElementById('ai-connections-intro-modal');
        if (!modal) return;
        modal.classList.add('active');
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
    }

    function initAiConnectionsIntroModal() {
        var modal = document.getElementById('ai-connections-intro-modal');
        if (!modal || modal.dataset.introBound === '1') return;
        modal.dataset.introBound = '1';
        modal.querySelectorAll('[data-ai-conn-intro-close]').forEach(function (el) {
            el.addEventListener('click', closeAiConnectionsIntroModal);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            if (modal.classList.contains('active')) closeAiConnectionsIntroModal();
        });
    }

    function initMergedTabFeatures(tabId, scrollTo) {
        if (tabId === 'site-delivery') {
            if (typeof window.initDeliveryStatus === 'function') {
                window.initDeliveryStatus('#delivery-status-site-delivery');
            }
            if (typeof window.loadDeliveryMatrix === 'function') {
                window.loadDeliveryMatrix();
            }
            if (typeof window.loadDomains === 'function') {
                window.loadDomains();
            }
            if (typeof window.initHostingSettings === 'function') {
                window.initHostingSettings();
            }
            if (typeof window.initRedirectsManager === 'function') {
                window.initRedirectsManager();
            }
        } else if (tabId === 'project') {
            if (typeof window.initDeliveryStatus === 'function') {
                window.initDeliveryStatus('#delivery-status-project');
            }
            if (typeof window.initProjectSettings === 'function') {
                window.initProjectSettings();
            }
        } else if (tabId === 'security' && typeof window.loadDenylist === 'function') {
            window.loadDenylist();
        } else if (tabId === 'ai') {
            try {
                window.dispatchEvent(new CustomEvent('birkly:ai-integrations-tab'));
            } catch (e) { /* ignore */ }
            if (window.McpConnections && typeof window.McpConnections.init === 'function') {
                window.McpConnections.init();
            }
            if (window.McpConnections && typeof window.McpConnections.refreshDesktop === 'function') {
                window.McpConnections.refreshDesktop();
            }
            if (window.AiConnections && typeof window.AiConnections.init === 'function') {
                window.AiConnections.init();
            }
            maybeShowAiConnectionsIntro();
        } else if (tabId === 'integrations') {
            if (typeof window.initWebhooksManager === 'function') {
                window.initWebhooksManager(window.webhooksData || {});
            }
        } else if (tabId === 'analytics') {
            if (typeof window.initAnalyticsSettings === 'function') {
                window.initAnalyticsSettings();
            }
        } else if (tabId === 'system') {
            if (typeof window.initDeveloperModeSettings === 'function') {
                window.initDeveloperModeSettings();
            }
            if (typeof window.initUpdates === 'function') {
                // fall through to existing updates init below
            }
        }
        if (tabId === 'system' && typeof window.initUpdates === 'function') {
            window.initUpdates();
        }

        if (tabId === 'system' && typeof window.birklyCmsUpdateAttentionHideModal === 'function') {
            window.birklyCmsUpdateAttentionHideModal();
        }

        scrollToSettingsSection(scrollTo);

        scheduleCaptureTabBaseline(tabId, 500);
    }

    async function confirmLeaveDirtyTab(targetTabId) {
        if (!isTrackedGlobalTab(currentTab) || !dirtyTabs[currentTab] || targetTabId === currentTab) {
            return true;
        }

        var message = 'You have unsaved changes in ' + currentTab.replace(/-/g, ' ') + '. Leave this tab without saving?';
        if (globalThis.BirklyFeedback && typeof BirklyFeedback.confirm === 'function') {
            return BirklyFeedback.confirm(message);
        }
        return false;
    }

    async function activateTab(tabId, tabButtons, tabPanes, scrollTo, options) {
        options = options || {};
        if (!tabId) return false;
        tabId = normalizeTabId(tabId) || tabId;

        var tabButton = document.querySelector('.settings-tabs .tab[data-tab="' + tabId + '"]');
        var tabPane = document.getElementById(tabId);
        if (!tabButton || !tabPane) return false;

        if (!options.force && tabId !== currentTab) {
            var confirmed = await confirmLeaveDirtyTab(tabId);
            if (!confirmed) {
                if (options.fromHistory && window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', buildSettingsUrl(currentTab));
                }
                return false;
            }
        }

        currentTab = tabId;

        for (var j = 0; j < tabButtons.length; j++) {
            var isActive = tabButtons[j] === tabButton;
            tabButtons[j].classList.toggle('active', isActive);
            if (window.Birkly && window.Birkly.TabsA11y) {
                window.Birkly.TabsA11y.syncTabButton(tabButtons[j], isActive);
            }
        }
        setActiveSettingsPane(tabPane, tabPanes);

        updateSaveBar(tabId);
        applySettingsSearch();

        if (!options.fromHistory && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', buildSettingsUrl(tabId));
        }

        setTimeout(function () {
            initMergedTabFeatures(tabId, scrollTo);
        }, 100);

        refreshTabsV2Picker();
        return true;
    }

    function initTabs() {
        var tabNav = document.querySelector('.settings-tabs .tabs');
        var tabButtons = getSettingsTabButtons();
        var tabPanes = getSettingsTabPanes();

        if (!tabNav || !tabButtons.length) {
            console.error('Settings tab navigation not found');
            return;
        }

        tabNav.setAttribute('role', 'tablist');
        tabNav.setAttribute('aria-label', 'Settings sections');
        tabButtons.forEach(function (btn, index) {
            var tabId = btn.getAttribute('data-tab');
            if (!tabId) return;
            btn.setAttribute('role', 'tab');
            btn.setAttribute('id', 'settings-tab-' + tabId);
            btn.setAttribute('aria-controls', tabId);
            var pane = document.getElementById(tabId);
            if (pane) {
                pane.setAttribute('role', 'tabpanel');
                pane.setAttribute('aria-labelledby', 'settings-tab-' + tabId);
                if (!pane.hasAttribute('tabindex')) {
                    pane.setAttribute('tabindex', '0');
                }
            }
            if (index === 0 && !btn.classList.contains('active')) {
                btn.setAttribute('aria-selected', 'false');
                btn.tabIndex = -1;
            }
        });

        if (!tabPanes.length) {
            console.error('Settings tab panes not found');
            return;
        }

        var parsed = parseSettingsUrl();
        if (parsed.redirected) {
            return;
        }
        normalizeSettingsUrl(parsed);

        tabNav.addEventListener('click', async function (e) {
            var btn = e.target.closest('.tab[data-tab]');
            if (!btn || !tabNav.contains(btn)) {
                return;
            }
            e.preventDefault();
            var targetTab = btn.getAttribute('data-tab');
            if (!targetTab) return;
            await activateTab(targetTab, getSettingsTabButtons(), getSettingsTabPanes(), null);
        });

        activateTab(parsed.tab, getSettingsTabButtons(), getSettingsTabPanes(), parsed.scrollTo, { force: true });

        if (window.Birkly && window.Birkly.TabsA11y) {
            window.Birkly.TabsA11y.enhance(tabNav, {
                activateOnFocus: true,
                onActivate: function (targetTab) {
                    activateTab(targetTab, getSettingsTabButtons(), getSettingsTabPanes(), null);
                },
            });
        }

        window.addEventListener('popstate', async function () {
            var next = parseSettingsUrl();
            await activateTab(next.tab, getSettingsTabButtons(), getSettingsTabPanes(), next.scrollTo, { fromHistory: true });
        });

        bindDirtyTracking();
        initSettingsSearch();
        initAiConnectionsIntroModal();
        updateSaveBar(currentTab);

        scheduleCaptureTabBaseline(parsed.tab, 500);
    }

    window.initSettingsTabs = initTabs;
    window.captureSettingsSnapshot = captureFormSnapshots;
    window.getSettingsCurrentTab = function () { return currentTab; };
    window.markSettingsClean = function (tabIds) {
        var tabs = Array.isArray(tabIds) ? tabIds : [tabIds || currentTab];
        tabs.forEach(function (tabId) {
            tabId = normalizeTabId(tabId) || tabId;
            if (!isTrackedGlobalTab(tabId)) {
                return;
            }
            formSnapshots[tabId] = serializeTabForms(tabId);
            dirtyTabs[tabId] = false;
        });
        updateTabDirtyIndicators();
        updateSaveBar(currentTab);
    };
    window.checkSettingsDirtyState = checkDirtyState;
    window.getSettingsTabsToSave = function (fallbackTabId) {
        var tabsToSave = Object.keys(GLOBAL_SAVE_TABS).filter(function (trackedTabId) {
            return !!dirtyTabs[trackedTabId];
        });
        if (!tabsToSave.length && GLOBAL_SAVE_TABS[fallbackTabId]) {
            tabsToSave = [fallbackTabId];
        }
        return tabsToSave;
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTabs);
    } else {
        initTabs();
    }
})();





// Invitation functions
let inviteMailStatus = null;

async function loadInviteMailStatus() {
    try {
        const response = await fetch(apiUrl('/api/invitations.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'mail_status' })
        });
        const result = await response.json();
        inviteMailStatus = result.status === 'ok' ? (result.data || null) : null;
    } catch (error) {
        inviteMailStatus = null;
    }
    renderInviteMailNotice();
}

function renderInviteMailNotice() {
    const notice = document.getElementById('invite-email-connection-notice');
    const copy = document.getElementById('invite-email-connection-copy');
    const link = document.getElementById('invite-email-connection-link');
    const sendBtn = document.getElementById('invite-send-email-btn');
    const linkBtn = document.getElementById('invite-create-link-btn');
    if (!notice) return;

    notice.hidden = false;
    notice.classList.remove('callout-info', 'callout-warning');

    if (inviteMailStatus?.has_connection) {
        notice.classList.add('callout-info');
        const name = inviteMailStatus.connection_name || inviteMailStatus.provider || 'Email connection';
        const from = inviteMailStatus.from_email ? ` (${inviteMailStatus.from_email})` : '';
        if (copy) {
            copy.textContent = `Invitations are sent through your configured email connection: ${name}${from}. You can also create a link and share it manually.`;
        }
        if (sendBtn) sendBtn.disabled = false;
    } else {
        notice.classList.add('callout-warning');
        if (copy) {
            copy.textContent = stT('settings_js_invite_email_connection_hint', 'Sending invitation emails requires an email connection under Settings → Connections. Without one, use “Create invite link” and share the URL yourself.');
        }
        if (sendBtn) sendBtn.disabled = true;
    }

    if (link && inviteMailStatus?.settings_url) {
        link.href = inviteMailStatus.settings_url;
    }
    if (linkBtn) linkBtn.disabled = false;
}

function resetInviteLinkResult() {
    const result = document.getElementById('invite-link-result');
    const field = document.getElementById('invite-link-copy-field');
    if (result) result.hidden = true;
    if (field) field.value = '';
}

function showInviteLinkResult(url) {
    const result = document.getElementById('invite-link-result');
    const field = document.getElementById('invite-link-copy-field');
    if (field) field.value = url || '';
    if (result) result.hidden = !url;
}

async function openInviteUserModal() {
    document.getElementById('invite-user-modal').classList.add('active');
    document.getElementById('invite-user-form').reset();
    resetInviteLinkResult();
    await loadInviteMailStatus();
}

function closeInviteUserModal() {
    document.getElementById('invite-user-modal').classList.remove('active');
    resetInviteLinkResult();
}

function getInviteFormData() {
    const form = document.getElementById('invite-user-form');
    const formData = new FormData(form);
    return {
        action: 'create_invitation',
        email: formData.get('email'),
        role: formData.get('role'),
        expires_days: parseInt(formData.get('expires_days'), 10),
        _csrf: getCsrfToken()
    };
}

async function submitInvitation(sendEmail) {
    const invitationData = {
        ...getInviteFormData(),
        send_email: sendEmail
    };

    try {
        const response = await fetch(apiUrl('/api/invitations.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(invitationData)
        });

        const result = await response.json();
        const inviteUrl = result.data?.invite_url || '';

        if (result.status === 'ok') {
            if (inviteUrl) {
                showInviteLinkResult(inviteUrl);
            }
            let message = sendEmail ? 'Invitation email sent successfully!' : 'Invitation link created.';
            if (result.data?.localhost_note) {
                message = result.message + '\n\n' + result.data.localhost_note;
            }
            showMessage(message, 'success');
            await loadTeamInvitations();
            if (!inviteUrl) {
                closeInviteUserModal();
            }
            return;
        }

        if (inviteUrl) {
            showInviteLinkResult(inviteUrl);
            await loadTeamInvitations();
            showMessage(
                (result.message || 'Could not send invitation email.') +
                ' The invite link was saved — copy it below and share it manually.',
                'warning'
            );
            return;
        }

        showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('settings_js_invitation_failed', 'Invitation failed')), 'error');
    } catch (error) {
        showMessage(stT('settings_js_err_sending_invitation', 'Error sending invitation:') + ' ' + error.message, 'error');
    }
}

async function sendInvitation() {
    await submitInvitation(true);
}

async function createInvitationLink() {
    await submitInvitation(false);
}

function copyInviteLink() {
    const field = document.getElementById('invite-link-copy-field');
    const url = field?.value || '';
    if (!url) return;
    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(url).then(() => {
            showMessage(stT('settings_js_invite_link_copied', 'Invite link copied to clipboard.'), 'success');
        }).catch(() => {
            field.select();
            showMessage(stT('settings_js_copy_link_manual', 'Select the link field and copy it manually.'), 'warning');
        });
        return;
    }
    field.select();
    showMessage(stT('settings_js_copy_link_manual', 'Select the link field and copy it manually.'), 'warning');
}

function invitationStatusBadge(status) {
    const map = {
        pending: { className: 'badge-warning', label: 'Pending' },
        expired: { className: 'badge-neutral', label: 'Expired' },
        accepted: { className: 'badge-success', label: 'Accepted' },
        cancelled: { className: 'badge-neutral', label: 'Cancelled' },
    };
    return map[status] || { className: 'badge-neutral', label: status || 'Unknown' };
}

function formatInvitationDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

async function loadTeamInvitations() {
    const listEl = document.getElementById('team-invitations-list');
    const emptyEl = document.getElementById('team-invitations-empty');
    const loadingEl = document.getElementById('team-invitations-loading');
    if (!listEl || !emptyEl || !loadingEl) return;

    loadingEl.hidden = false;
    emptyEl.hidden = true;
    listEl.hidden = true;
    listEl.innerHTML = '';

    try {
        const response = await fetch(apiUrl('/api/invitations.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ action: 'list_invitations' }),
        });
        const result = await response.json();
        const invitations = result.status === 'ok' ? (result.data?.invitations || []) : [];

        if (!invitations.length) {
            emptyEl.hidden = false;
            return;
        }

        listEl.innerHTML = invitations.map(function (invite) {
            const badge = invitationStatusBadge(invite.status);
            const delivery = invite.delivery_label || '—';
            const emailError = invite.email_error
                ? '<span class="data-list-meta u-fg-danger">' + escapeHtml(invite.email_error) + '</span>'
                : '';
            const actions = [];

            if (invite.can_copy_link && invite.invite_url) {
                actions.push('<button type="button" class="btn btn-secondary btn-sm" data-admin-action="copyTeamInvitationLink" data-admin-args=\'' + JSON.stringify([invite.invite_url]) + '\'>Copy link</button>');
            }
            if (invite.can_resend) {
                actions.push('<button type="button" class="btn btn-ghost btn-sm" data-admin-action="resendTeamInvitation" data-admin-args=\'' + JSON.stringify([invite.token]) + '\'>Resend email</button>');
            }
            if (invite.can_cancel) {
                actions.push('<button type="button" class="btn btn-ghost btn-sm" data-admin-action="cancelTeamInvitation" data-admin-args=\'' + JSON.stringify([invite.token, invite.email]) + '\'>' + escapeHtml(stT('cancel', 'Cancel')) + '</button>');
            }

            return '<div class="data-list-item">' +
                '<div class="data-list-main">' +
                    '<span class="data-list-title">' + escapeHtml(invite.email || '') + '</span>' +
                    '<span class="data-list-meta">' +
                        escapeHtml((invite.role || 'viewer')) + ' · ' + escapeHtml(delivery) +
                        ' · Created ' + escapeHtml(formatInvitationDate(invite.created_at)) +
                        ' · Expires ' + escapeHtml(formatInvitationDate(invite.expires_at)) +
                    '</span>' +
                    emailError +
                '</div>' +
                '<div class="data-list-actions u-cluster-2">' +
                    '<span class="badge ' + badge.className + '">' + escapeHtml(badge.label) + '</span>' +
                    actions.join('') +
                '</div>' +
            '</div>';
        }).join('');
        listEl.hidden = false;
    } catch (error) {
        emptyEl.hidden = false;
        emptyEl.querySelector('.empty-state__description').textContent = stT('settings_js_invitations_load_failed', 'Could not load invitations.');
    } finally {
        loadingEl.hidden = true;
    }
}

function copyTeamInvitationLink(url) {
    if (!url) return;
    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(url).then(function () {
            showMessage(stT('settings_js_invite_link_copied', 'Invite link copied to clipboard.'), 'success');
        }).catch(function () {
            showMessage(url, 'info');
        });
        return;
    }
    showMessage(url, 'info');
}

async function resendTeamInvitation(token) {
    if (!token) return;
    try {
        const response = await fetch(apiUrl('/api/invitations.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'resend_invitation',
                token: token,
                _csrf: getCsrfToken(),
            }),
        });
        const result = await response.json();
        if (result.status === 'ok') {
            showMessage(result.message || stT('settings_js_invitation_resent', 'Invitation resent.'), 'success');
            await loadTeamInvitations();
            return;
        }
        if (result.data?.invite_url) {
            showMessage((result.message || stT('settings_js_could_not_resend_email', 'Could not resend email.')) + stT('settings_js_resend_copy_instead', ' Copy the link from the list instead.'), 'warning');
            await loadTeamInvitations();
            return;
        }
        showMessage(result.message || stT('settings_js_could_not_resend_invitation', 'Could not resend invitation.'), 'error');
    } catch (error) {
        showMessage(stT('settings_js_err_resending_invitation', 'Error resending invitation:') + ' ' + error.message, 'error');
    }
}

async function cancelTeamInvitation(token, email) {
    if (!token) return;
    const label = email ? 'Cancel invitation for ' + email + '?' : 'Cancel this invitation?';
    let confirmed = true;
    if (window.BirklyFeedback && typeof BirklyFeedback.confirm === 'function') {
        confirmed = await BirklyFeedback.confirm(label);
    } else {
        confirmed = window.confirm(label);
    }
    if (!confirmed) return;

    try {
        const response = await fetch(apiUrl('/api/invitations.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                action: 'cancel_invitation',
                token: token,
                _csrf: getCsrfToken(),
            }),
        });
        const result = await response.json();
        if (result.status === 'ok') {
            showMessage(result.message || stT('settings_js_invitation_cancelled', 'Invitation cancelled.'), 'success');
            await loadTeamInvitations();
            return;
        }
        showMessage(result.message || stT('settings_js_could_not_cancel_invitation', 'Could not cancel invitation.'), 'error');
    } catch (error) {
        showMessage(stT('settings_js_err_cancelling_invitation', 'Error cancelling invitation:') + ' ' + error.message, 'error');
    }
}

// User management functions
function openAddUserModal() {
    document.getElementById('add-user-modal').classList.add('active');
    document.getElementById('add-user-form').reset();
}

function closeAddUserModal() {
    document.getElementById('add-user-modal').classList.remove('active');
}

function openEditUserModal() {
    document.getElementById('edit-user-modal').classList.add('active');
}

function closeEditUserModal() {
    document.getElementById('edit-user-modal').classList.remove('active');
}

// Role functions are now defined at the top of the script tag

function editUser(userId) {
    // Find user data
    var users = getSettingsUsers();
    var user = users.find(u => u.id === userId);
    if (!user) return;
    
    // Populate edit form
    document.getElementById('edit-user-id').value = user.id;
    document.getElementById('edit-user-email').value = user.email;
    document.getElementById('edit-user-password').value = '';
    document.getElementById('edit-user-role').value = user.role;
    
    openEditUserModal();
}

async function createUser() {
    const form = document.getElementById('add-user-form');
    const formData = new FormData(form);
    
    const userData = {
        action: 'create_user',
        email: formData.get('email'),
        password: formData.get('password'),
        role: formData.get('role'),
        _csrf: getCsrfToken()
    };
    
    try {
        const response = await fetch(apiUrl('/api/users.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(userData)
        });
        
        const result = await response.json();
        
        if (result.status === 'ok') {
            showMessage(stT('settings_js_user_created', 'User created successfully!'), 'success');
            closeAddUserModal();
            // Reload the page to show the new user
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + result.message, 'error');
        }
    } catch (error) {
        showMessage(stT('settings_js_err_creating_user', 'Error creating user:') + ' ' + error.message, 'error');
    }
}

async function updateUser() {
    const form = document.getElementById('edit-user-form');
    const formData = new FormData(form);
    
    const updates = {
        email: formData.get('email'),
        role: formData.get('role')
    };
    
    if (formData.get('password')) {
        updates.password = formData.get('password');
    }
    
    const userData = {
        action: 'update_user',
        id: formData.get('id'),
        updates: updates,
        _csrf: getCsrfToken()
    };
    
    try {
        const response = await fetch(apiUrl('/api/users.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(userData)
        });
        
        const result = await response.json();
        
        if (result.status === 'ok') {
            showMessage(stT('settings_js_user_updated', 'User updated successfully!'), 'success');
            closeEditUserModal();
            // Reload the page to show the updated user
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + result.message, 'error');
        }
    } catch (error) {
        showMessage(stT('settings_js_err_updating_user', 'Error updating user:') + ' ' + error.message, 'error');
    }
}

async function confirmDeleteUser(userId, email) {
    if (await BirklyFeedback.confirm(stT('settings_js_confirm_delete_user', 'Are you sure you want to delete user "{email}"? This action cannot be undone.', { email: email }))) {
        deleteUser(userId);
    }
}

async function deleteUser(userId) {
    const userData = {
        action: 'delete_user',
        id: userId,
        _csrf: getCsrfToken()
    };
    
    try {
        const response = await fetch(apiUrl('/api/users.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(userData)
        });
        
        const result = await response.json();
        
        if (result.status === 'ok') {
            showMessage(stT('settings_js_user_deleted', 'User deleted successfully!'), 'success');
            // Reload the page to show the updated user list
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + result.message, 'error');
        }
    } catch (error) {
        showMessage(stT('settings_js_err_deleting_user', 'Error deleting user:') + ' ' + error.message, 'error');
    }
}

function deleteUserFromModal() {
    const userId = document.getElementById('edit-user-id').value;
    const email = document.getElementById('edit-user-email').value;
    closeEditUserModal();
    confirmDeleteUser(userId, email);
}

async function revokeUserMcpSessions(userId, label) {
    if (!userId) return;
    const who = label || 'this user';
    if (!(await BirklyFeedback.confirm(stT('settings_js_confirm_revoke_ai', 'Revoke all active AI app connections for {who}? They will need to sign in again in Claude, Cursor, or ChatGPT.', { who: who })))) {
        return;
    }

    fetch(apiUrl('/api/users.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'revoke_mcp_sessions',
            id: userId,
            _csrf: getCsrfToken(),
        }),
    })
        .then((response) => response.json())
        .then((result) => {
            if (result.status === 'ok') {
                showMessage(result.message || stT('settings_js_ai_connections_revoked', 'AI connections revoked'), 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('settings_js_could_not_revoke_ai', 'Could not revoke AI access')), 'error');
            }
        })
        .catch((error) => {
            showMessage(stT('settings_js_err_revoking_ai', 'Error revoking AI access:') + ' ' + error.message, 'error');
        });
}

// Plugin management functions
async function togglePlugin(pluginId, activate) {
    var action = activate ? 'activate' : 'deactivate';
    
    try {
        const response = await fetch(apiUrl('/api/plugins.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: action,
                plugin_id: pluginId,
                _csrf: getCsrfToken()
            })
        });
        
        const result = await response.json();
        
        if (result.status === 'ok') {
            showMessage(result.message || stT('settings_js_plugin_action_success', 'Plugin {action}d successfully', { action: action }), 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            var errorMsg = stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('settings_js_failed_plugin_action', 'Failed to {action} plugin', { action: action }));
            if (result.errors && Array.isArray(result.errors) && result.errors.length > 0) {
                errorMsg += ' — ' + result.errors.join('; ');
            }
            showMessage(errorMsg, 'error');
        }
    } catch (error) {
        showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + error.message, 'error');
    }
}

async function deletePlugin(pluginId) {
    if (!(await BirklyFeedback.confirm(stT('settings_js_confirm_delete_plugin', 'Are you sure you want to delete this plugin? This action cannot be undone.')))) {
        return;
    }
    
    try {
        const response = await fetch(apiUrl('/api/plugins.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete',
                plugin_id: pluginId,
                _csrf: getCsrfToken()
            })
        });
        
        const result = await response.json();
        
        if (result.status === 'ok') {
            showMessage(result.message || stT('settings_js_plugin_deleted', 'Plugin deleted successfully'), 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('settings_js_failed_delete_plugin', 'Failed to delete plugin')), 'error');
        }
    } catch (error) {
        showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + error.message, 'error');
    }
}

// 2FA is managed per-user in Account settings (admin/templates/account.html)

// Domain Management Functions
(function() {
    'use strict';
    
    // Domains loading is handled in the main tab click handler
    
    function deliveryRoleLabel(role) {
        var labels = {
            cms_primary: 'CMS (primary)',
            cms: 'CMS alias',
            public_primary: 'Public (primary)',
            public: 'Public alias',
            api_only: 'API only (CORS)',
            unassigned: 'Unassigned'
        };
        return labels[role] || role || 'Unassigned';
    }

    function deliveryRoleBadgeClass(role) {
        if (role === 'cms_primary' || role === 'cms') return 'accent';
        if (role === 'public_primary' || role === 'public') return 'success';
        if (role === 'api_only') return 'info';
        return 'neutral';
    }

    async function setDomainDeliveryRole(host, role, selectEl) {
        if (!host || !role) return;
        if (selectEl) selectEl.disabled = true;
        try {
            var response = await fetch(apiUrl('/api/hosting.php?action=set_domain_role'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ host: host, role: role, _csrf: getCsrfToken() })
            });
            var result = await response.json();
            if (result.status !== 'ok') {
                throw new Error(result.message || 'Failed to update delivery role');
            }
            showMessage(stT('settings_js_delivery_role_updated', 'Delivery role updated for {host}', { host: host }), 'success');
            if (typeof window.initDeliveryStatus === 'function') {
                window.initDeliveryStatus('#delivery-status-site-delivery');
            }
            if (typeof window.refreshHostingWizard === 'function') {
                window.refreshHostingWizard();
            }
            loadDomains();
            loadDeliveryMatrix();
        } catch (error) {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + error.message, 'error');
            if (selectEl) selectEl.disabled = false;
        }
    }

    async function setPublicPrimary(host, buttonEl) {
        if (!host) return;
        if (!(await BirklyFeedback.confirm(stT('settings_js_confirm_primary_url', 'Make {host} the primary public URL? The current primary will become an alias.', { host: host })))) {
            return;
        }
        if (buttonEl) buttonEl.disabled = true;
        try {
            var response = await fetch(apiUrl('/api/hosting.php?action=set_public_primary'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ host: host, _csrf: getCsrfToken() })
            });
            var result = await response.json();
            if (result.status !== 'ok') {
                throw new Error(result.message || 'Failed to update primary URL');
            }
            showMessage(result.message || stT('settings_js_primary_url_updated', 'Primary public URL updated'), 'success');
            if (typeof window.initDeliveryStatus === 'function') {
                window.initDeliveryStatus('#delivery-status-site-delivery');
            }
            if (typeof window.refreshHostingWizard === 'function') {
                window.refreshHostingWizard();
            }
            loadDomains();
            loadDeliveryMatrix();
        } catch (error) {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + error.message, 'error');
            if (buttonEl) buttonEl.disabled = false;
        }
    }

    async function setAliasRedirect(enabled, checkboxEl) {
        if (checkboxEl) checkboxEl.disabled = true;
        try {
            var response = await fetch(apiUrl('/api/hosting.php?action=set_alias_redirect'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled: !!enabled, _csrf: getCsrfToken() })
            });
            var result = await response.json();
            if (result.status !== 'ok') {
                throw new Error(result.message || 'Failed to update alias redirect');
            }
            showMessage(result.message || stT('settings_js_alias_redirect_updated', 'Alias redirect updated'), 'success');
            if (typeof window.initDeliveryStatus === 'function') {
                window.initDeliveryStatus('#delivery-status-site-delivery');
            }
            if (typeof window.refreshHostingWizard === 'function') {
                window.refreshHostingWizard();
            }
            loadDeliveryMatrix();
        } catch (error) {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + error.message, 'error');
            if (checkboxEl) checkboxEl.checked = !enabled;
        } finally {
            if (checkboxEl) checkboxEl.disabled = false;
        }
    }

    function renderDeliveryRoutingOptions(hosting, detect) {
        var wrap = document.getElementById('delivery-routing-options');
        var checkbox = document.getElementById('delivery-alias-redirect');
        if (!wrap || !checkbox) return;

        var mode = (hosting && hosting.public_site_mode) ? hosting.public_site_mode : 'none';
        var envManaged = !!(detect && detect.env_managed);
        wrap.hidden = mode === 'none';
        if (mode === 'none') return;

        checkbox.checked = !hosting || hosting.alias_redirect_to_primary !== false;
        checkbox.disabled = envManaged;
        if (checkbox.dataset.bound !== '1') {
            checkbox.dataset.bound = '1';
            checkbox.addEventListener('change', function () {
                setAliasRedirect(checkbox.checked, checkbox);
            });
        }
    }

    function renderDeliveryMatrixRow(row) {
        var host = String(row.host || '');
        var role = row.role || 'unassigned';
        var rolesMap = {};
        rolesMap[host] = role;
        var roleCell = renderDomainDeliveryRole(host, rolesMap);
        if (row.is_primary) {
            roleCell = '<span class="badge badge-' + deliveryRoleBadgeClass(role) + '">' + escapeHtml(deliveryRoleLabel(role)) + '</span>';
        }
        var corsBadge = row.cors_registered
            ? '<span class="badge badge-success">Registered</span>'
            : '<span class="badge badge-neutral">Not registered</span>';
        var suggestHint = '';
        if (!row.is_primary && row.suggested_role && row.settable_role !== row.suggested_role && role === 'api_only') {
            suggestHint = '<div class="form-help">Suggested: ' + escapeHtml(row.suggested_role) + ' — ' + escapeHtml(row.suggest_reason || '') + '</div>';
        }
        var routingCell = '—';
        if (row.is_primary) {
            routingCell = '<span class="badge badge-accent">Primary URL</span>';
        } else if (row.redirects_to_primary && row.redirect_target_host) {
            routingCell = '<span class="badge badge-info">301 → ' + escapeHtml(row.redirect_target_host) + '</span>';
        } else if (row.is_alias) {
            routingCell = '<span class="badge badge-success">Serves directly</span>';
        }
        var actions = '';
        if (row.can_make_public_primary) {
            actions = ' <button type="button" class="btn btn-secondary btn-sm delivery-make-primary" data-host="' + escapeHtml(host) + '">Make primary</button>';
        }
        return '<tr>' +
            '<td><code>' + escapeHtml(host) + '</code>' + suggestHint + '</td>' +
            '<td>' + corsBadge + '</td>' +
            '<td>' + roleCell + actions + '</td>' +
            '<td>' + routingCell + '</td>' +
            '</tr>';
    }

    function renderDeliveryMatrix(rows, meta) {
        var container = document.getElementById('delivery-matrix-content');
        if (!container) return;

        rows = rows || [];
        meta = meta || {};
        renderDeliveryRoutingOptions(meta.hosting || {}, meta.detect || {});

        if (!rows.length) {
            container.innerHTML = '<div class="empty-state empty-state--compact"><p class="empty-state__description">' + escapeHtml(stT('settings_js_no_delivery_hostnames', 'No delivery hostnames yet. Register a domain or configure hosting URLs in the wizard below.')) + '</p></div>';
            return;
        }

        var body = rows.map(renderDeliveryMatrixRow).join('');
        container.innerHTML =
            '<div class="table-container">' +
            '<table class="table table--hover">' +
            '<thead><tr><th>Hostname</th><th>CORS</th><th>Delivery role</th><th>Visitor routing</th></tr></thead>' +
            '<tbody>' + body + '</tbody></table></div>' +
            '<p class="form-help">Use <strong>Make primary</strong> to swap which URL is canonical. Uncheck alias redirect above to serve aliases without sending visitors to the primary hostname. Apex and <code>www</code> pairs are linked automatically when you assign a delivery role.</p>';

        var roleSelects = container.querySelectorAll('.domain-delivery-role');
        for (var r = 0; r < roleSelects.length; r++) {
            (function(select) {
                select.addEventListener('change', function () {
                    setDomainDeliveryRole(select.getAttribute('data-domain'), select.value, select);
                });
            })(roleSelects[r]);
        }

        container.querySelectorAll('.delivery-make-primary').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setPublicPrimary(btn.getAttribute('data-host'), btn);
            });
        });
    }

    async function loadDeliveryMatrix() {
        var container = document.getElementById('delivery-matrix-content');
        if (!container) return;

        container.innerHTML = '<div class="loading-state"><p>' + escapeHtml(stT('loading_delivery_matrix', 'Loading delivery matrix...')) + '</p></div>';
        try {
            var response = await fetch(apiUrl('/api/hosting.php?action=get'), {
                method: 'GET',
                credentials: 'same-origin'
            });
            var payload = await response.json();
            var data = (payload.status === 'ok' && payload.data) ? payload.data : {};
            var rows = data.delivery_matrix || [];
            renderDeliveryMatrix(rows, {
                hosting: data.hosting || {},
                detect: data.detect || {}
            });
        } catch (error) {
            container.innerHTML = '<div class="message message-error" role="alert">' + escapeHtml(stT('settings_js_err_loading_delivery_matrix', 'Error loading delivery matrix: {message}', { message: error.message })) + '</div>';
        }
    }

    async function loadDomains() {
        var listContainer = document.getElementById('domains-list');
        if (!listContainer) {
            console.error('domains-list container not found');
            return;
        }
        
        listContainer.innerHTML = '<div class="loading-state"><p>' + escapeHtml(stT('loading_domains', 'Loading domains')) + '</p></div>';
        
        try {
            var responses = await Promise.all([
                fetch(apiUrl('/api/domain_management.php?action=list_licenses'), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' }
                }),
                fetch(apiUrl('/api/hosting.php?action=get'), {
                    method: 'GET',
                    credentials: 'same-origin'
                })
            ]);
            var data = await responses[0].json();
            var hostingPayload = await responses[1].json();
            var domainRoles = (hostingPayload.status === 'ok' && hostingPayload.data && hostingPayload.data.domain_roles)
                ? hostingPayload.data.domain_roles
                : {};
            
            if (data.status === 'ok' && data.data && data.data.licenses) {
                renderDomains(data.data.licenses, domainRoles);
            } else {
                listContainer.innerHTML = '<div class="empty-state empty-state--compact"><p class="empty-state__description">' + escapeHtml(stT('settings_js_no_domains_registered', 'No domains registered yet.')) + '</p></div>';
            }
            attachDomainsCopyButtons(document.getElementById('domains'));
        } catch (error) {
            console.error('Error loading domains:', error);
            listContainer.innerHTML = '<div class="message message-error" role="alert">Error loading domains: ' + escapeHtml(error.message) + '</div>';
        }
    }
    
    function buildDomainTrackingScript(domainName) {
        // Match birkly_hosting_client_snippet(): absolute CMS URL + data-cms-url.
        var cmsBase = '';
        if (window.Birkly && typeof window.Birkly.url === 'function') {
            try {
                cmsBase = new URL(window.Birkly.url('/'), window.location.origin).origin;
            } catch (e) {
                cmsBase = window.location.origin;
            }
        } else {
            cmsBase = window.location.origin;
        }
        cmsBase = String(cmsBase || '').replace(/\/$/, '');
        var scriptSrc = cmsBase + '/birkly-client.js';
        var trackingAttr = domainName
            ? ' data-tracking-id="' + String(domainName).replace(/"/g, '') + '"'
            : '';
        return '<script src="' + scriptSrc + '" data-cms-url="' + cmsBase + '"' + trackingAttr + '></script>';
    }

    function renderDomainTrackingScript(domainName, enabled) {
        if (!enabled) {
            return '';
        }

        var trackingCode = buildDomainTrackingScript(domainName);
        var codeId = 'domain-script-' + domainName.replace(/[^a-zA-Z0-9_-]/g, '-');
        return '' +
            '<div class="domains-code-block">' +
            '<pre class="domains-code-pre"><code id="' + escapeHtml(codeId) + '">' + escapeHtml(trackingCode) + '</code></pre>' +
            '<button type="button" class="btn btn-secondary btn-sm domains-copy-btn" data-copy-target="' + escapeHtml(codeId) + '" title="' + escapeHtml(stT('copy_code', 'Copy code')) + '">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
            '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>' +
            '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>' +
            '</svg>' + escapeHtml(stT('copy', 'Copy')) + '</button></div>' +
            '<p class="form-help">' + stT('settings_domain_snippet_help', 'Add this snippet before the </body> tag on your site.') + '</p>';
    }

    function attachDomainsCopyButtons(root) {
        var copyButtons = (root || document).querySelectorAll('.domains-copy-btn');
        for (var i = 0; i < copyButtons.length; i++) {
            (function(btn) {
                if (btn.dataset.copyBound === '1') {
                    return;
                }
                btn.dataset.copyBound = '1';
                btn.addEventListener('click', async function () {
                    var targetId = btn.getAttribute('data-copy-target');
                    var codeEl = targetId ? document.getElementById(targetId) : null;
                    var code = codeEl ? codeEl.textContent : '';
                    if (!code || !navigator.clipboard || !navigator.clipboard.writeText) {
                        return;
                    }
                    navigator.clipboard.writeText(code).then(function() {
                        var originalText = btn.innerHTML;
                        btn.innerHTML = 'Copied';
                        setTimeout(function() {
                            btn.innerHTML = originalText;
                        }, 2000);
                    });
                });
            })(copyButtons[i]);
        }
    }

    async function toggleDomainAnalytics(domainName, enabled, toggleInput) {
        if (!domainName) {
            return;
        }

        if (toggleInput) {
            toggleInput.disabled = true;
        }

        try {
            var response = await fetch(apiUrl('/api/domain_management.php'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_license',
                    domain: domainName,
                    status: 'active',
                    analytics_enabled: !!enabled,
                    _csrf: getCsrfToken()
                })
            });

            var responseText = await response.text();
            var result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                throw new Error('Invalid response from server');
            }

            if (result.status !== 'ok') {
                throw new Error(result.message || 'Failed to update analytics');
            }

            showMessage(enabled ? stT('settings_js_analytics_enabled', 'Analytics enabled for {domain}', { domain: domainName }) : stT('settings_js_analytics_disabled', 'Analytics disabled for {domain}', { domain: domainName }), 'success');
            loadDomains();
        } catch (error) {
            if (toggleInput) {
                toggleInput.checked = !enabled;
            }
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + error.message, 'error');
        } finally {
            if (toggleInput) {
                toggleInput.disabled = false;
            }
        }
    }

    function renderAnalyticsReportSections(report) {
        function section(title, rows, labelKey, valueKey) {
            if (!rows || !rows.length) {
                return '';
            }
            var items = rows.map(function(row) {
                return '<li><span class="analytics-report__label">' + escapeHtml(row[labelKey] || '—') + '</span>' +
                    '<span class="analytics-report__value">' + escapeHtml(row[valueKey] || 0) + '</span></li>';
            }).join('');
            return '<div class="analytics-report__section"><h4>' + escapeHtml(title) + '</h4><ul class="analytics-report__list">' + items + '</ul></div>';
        }

        return '<div class="analytics-report__grid">' +
            section('Entrance pages', report.entrance_pages, 'path', 'entrances') +
            section('Exit pages', report.exit_pages, 'path', 'exits') +
            section('Devices', report.devices, 'device_type', 'visits') +
            section('Locations', report.countries, 'country', 'visits') +
            section('Browsers', report.browsers, 'browser', 'visits') +
            section('Operating systems', report.operating_systems, 'os', 'visits') +
            section('Traffic sources', report.traffic_sources, 'source', 'visits') +
            '</div>';
    }

    async function loadDomainAnalyticsReport(domainName, container) {
        if (!container) {
            return;
        }

        container.hidden = false;
        container.innerHTML = '<div class="loading-state"><p>' + escapeHtml(stT('loading_analytics_report', 'Loading analytics report...')) + '</p></div>';

        try {
            var endDate = new Date().toISOString().slice(0, 10);
            var startDate = new Date(Date.now() - (30 * 24 * 60 * 60 * 1000)).toISOString().slice(0, 10);
            var url = apiUrl('/api/analytics.php?action=data&tracking_id=' + encodeURIComponent(domainName) +
                '&start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate));
            var response = await fetch(url, { credentials: 'same-origin' });
            var payload = await response.json();

            if (payload.status !== 'ok' || !payload.data) {
                throw new Error(payload.message || 'Failed to load analytics report');
            }

            var report = payload.data;
            container.innerHTML =
                '<div class="card card--sunken u-p-4 u-stack-4">' +
                '<div class="u-grid-auto-sm">' +
                '<div class="stat"><span class="stat-value">' + escapeHtml(String(report.page_views || 0)) + '</span><span class="stat-label">Page views (30d)</span></div>' +
                '<div class="stat"><span class="stat-value">' + escapeHtml(String(report.unique_visitors || 0)) + '</span><span class="stat-label">Visitors (30d)</span></div>' +
                '</div>' +
                renderAnalyticsReportSections(report) +
                '</div>';
        } catch (error) {
            container.innerHTML = '<div class="message message-error" role="alert">Error loading analytics report: ' + escapeHtml(error.message) + '</div>';
        }
    }

    function renderDomainDeliveryRole(domainName, domainRoles) {
        var host = String(domainName || '').toLowerCase();
        var role = (domainRoles && domainRoles[host]) ? domainRoles[host] : 'unassigned';
        var isPrimary = role === 'cms_primary' || role === 'public_primary';
        if (isPrimary) {
            return '<span class="badge badge-' + deliveryRoleBadgeClass(role) + '">' + escapeHtml(deliveryRoleLabel(role)) + '</span>';
        }
        var options = [
            { value: 'api_only', label: 'API only (CORS)' },
            { value: 'cms', label: 'CMS alias' },
            { value: 'public', label: 'Public alias' }
        ];
        var selected = (role === 'cms' || role === 'public' || role === 'api_only') ? role : 'api_only';
        var optionHtml = options.map(function (opt) {
            return '<option value="' + escapeHtml(opt.value) + '"' + (opt.value === selected ? ' selected' : '') + '>' +
                escapeHtml(opt.label) + '</option>';
        }).join('');
        return '<select class="input input-sm domain-delivery-role" data-domain="' + escapeHtml(host) + '" aria-label="Delivery role for ' + escapeHtml(host) + '">' +
            optionHtml + '</select>';
    }

    function renderDomains(domains, domainRoles) {
        var listContainer = document.getElementById('domains-list');
        if (!listContainer) {
            console.error('domains-list container not found in renderDomains');
            return;
        }

        attachDomainsCopyButtons(document.getElementById('domains'));
        domainRoles = domainRoles || {};

        if (!domains.length) {
            listContainer.innerHTML = '<div class="empty-state empty-state--compact"><p class="empty-state__description">' + escapeHtml(stT('settings_js_no_domains_add_hint', 'No domains yet. Click "Add domain" to connect one.')) + '</p></div>';
            return;
        }

        listContainer.innerHTML = domains.map(function(domain) {
            var permissions = domain.permissions || {};
            var permissionLevel = permissions.permission_level || 'read_write';
            var readCollections = permissions.read_collections || [];
            var writeCollections = permissions.write_collections || [];
            var rateLimits = permissions.rate_limits || {};
            var analyticsEnabled = !!domain.analytics_enabled;
            var domainName = String(domain.domain || '');
            var domainEscaped = escapeHtml(domainName);
            var emailEscaped = escapeHtml(domain.customer_email || 'N/A');
            var statusClass = domain.status === 'active' ? 'success' : 'neutral';
            var statusText = domain.status || 'active';
            var permClass = permissionLevel === 'read_write' ? 'accent' : 'info';
            var permText = permissionLevel === 'read_write' ? 'Read & Write' : 'Read Only';
            var registeredDate = domain.registered_at ? new Date(domain.registered_at).toLocaleDateString() : 'N/A';
            var readLimit = rateLimits.read && rateLimits.read.limit ? rateLimits.read.limit : 100;
            var writeLimit = rateLimits.write && rateLimits.write.limit ? rateLimits.write.limit : 10;
            var readColsHtml = readCollections.length > 0 ? '<span class="kv-key">Read collections</span><span class="kv-value">' + escapeHtml(readCollections.join(', ')) + '</span>' : '';
            var writeColsHtml = writeCollections.length > 0 ? '<span class="kv-key">Write collections</span><span class="kv-value">' + escapeHtml(writeCollections.join(', ')) + '</span>' : '';
            var trackingScriptHtml = renderDomainTrackingScript(domainName, analyticsEnabled);
            var deliveryRoleHtml = renderDomainDeliveryRole(domainName, domainRoles);

            return '' +
                '<article class="domain-card card">' +
                '<div class="card-header">' +
                '<div class="u-stack-2 u-min-w-0">' +
                '<h4 class="card-title u-text-base">' + domainEscaped + '</h4>' +
                '<div class="u-cluster">' +
                '<span class="badge badge-' + statusClass + '">' + escapeHtml(statusText) + '</span>' +
                '<span class="badge badge-' + permClass + '">' + permText + '</span>' +
                (analyticsEnabled ? '<span class="badge badge-success">Analytics on</span>' : '') +
                '</div>' +
                '</div>' +
                '<div class="card-actions">' +
                '<button type="button" class="btn btn-sm btn-secondary" data-domain="' + domainEscaped + '" data-action="edit">Edit</button>' +
                '<button type="button" class="btn btn-sm btn-danger" data-domain="' + domainEscaped + '" data-action="delete">Delete</button>' +
                '</div>' +
                '</div>' +
                '<div class="card-body u-stack-4">' +
                '<div class="kv">' +
                '<span class="kv-key">Email</span><span class="kv-value">' + emailEscaped + '</span>' +
                '<span class="kv-key">Registered</span><span class="kv-value">' + registeredDate + '</span>' +
                '<span class="kv-key">Delivery role</span><span class="kv-value">' + deliveryRoleHtml + '</span>' +
                '<span class="kv-key">Rate limits</span><span class="kv-value">Read ' + readLimit + '/min · Write ' + writeLimit + '/hour</span>' +
                readColsHtml +
                writeColsHtml +
                '</div>' +
                '<div class="u-stack-3">' +
                '<div class="data-list-item domain-analytics-toggle-row">' +
                '<div class="data-list-main">' +
                '<span class="data-list-title">Website analytics</span>' +
                '<small class="data-list-meta">Self-hosted visitor tracking for this domain.</small>' +
                '</div>' +
                '<label class="switch" aria-label="Toggle analytics for ' + domainEscaped + '">' +
                '<input type="checkbox" class="domain-analytics-toggle" data-domain="' + domainEscaped + '"' + (analyticsEnabled ? ' checked' : '') + '>' +
                '<span class="switch__slider"></span>' +
                '</label>' +
                '</div>' +
                (trackingScriptHtml ? '<div class="domain-tracking-script u-stack-2">' + trackingScriptHtml + '</div>' : '<div class="domain-tracking-script" hidden></div>') +
                (analyticsEnabled ?
                    '<div class="u-cluster">' +
                    '<button type="button" class="btn btn-secondary btn-sm domain-analytics-report-btn" data-domain="' + domainEscaped + '">View 30-day report</button>' +
                    '</div>' +
                    '<div class="domain-analytics-report" hidden></div>' : '') +
                '</div>' +
                '</div>' +
                '</article>';
        }).join('');

        attachDomainsCopyButtons(listContainer);

        var domainActions = listContainer.querySelectorAll('[data-action="edit"], [data-action="delete"]');
        for (var i = 0; i < domainActions.length; i++) {
            (function(btn) {
                btn.addEventListener('click', async function (e) {
                    e.preventDefault();
                    var domain = btn.getAttribute('data-domain');
                    var action = btn.getAttribute('data-action');
                    if (action === 'edit' && window.editDomain) {
                        window.editDomain(domain);
                    } else if (action === 'delete' && window.deleteDomain) {
                        window.deleteDomain(domain);
                    }
                });
            })(domainActions[i]);
        }

        var analyticsToggles = listContainer.querySelectorAll('.domain-analytics-toggle');
        for (var j = 0; j < analyticsToggles.length; j++) {
            (function(toggle) {
                toggle.addEventListener('change', function() {
                    toggleDomainAnalytics(toggle.getAttribute('data-domain'), toggle.checked, toggle);
                });
            })(analyticsToggles[j]);
        }

        var reportButtons = listContainer.querySelectorAll('.domain-analytics-report-btn');
        for (var k = 0; k < reportButtons.length; k++) {
            (function(btn) {
                btn.addEventListener('click', async function () {
                    var domain = btn.getAttribute('data-domain');
                    var card = btn.closest('.domain-card');
                    var reportContainer = card ? card.querySelector('.domain-analytics-report') : null;
                    if (reportContainer && !reportContainer.dataset.loaded) {
                        reportContainer.dataset.loaded = '1';
                        loadDomainAnalyticsReport(domain, reportContainer);
                    } else if (reportContainer) {
                        reportContainer.hidden = !reportContainer.hidden;
                    }
                });
            })(reportButtons[k]);
        }

        var roleSelects = listContainer.querySelectorAll('.domain-delivery-role');
        for (var r = 0; r < roleSelects.length; r++) {
            (function(select) {
                select.addEventListener('change', function () {
                    setDomainDeliveryRole(select.getAttribute('data-domain'), select.value, select);
                });
            })(roleSelects[r]);
        }
    }
    
        
    // Expose functions globally
    window.loadDomains = loadDomains;
    window.renderDomains = renderDomains;
    window.loadDeliveryMatrix = loadDeliveryMatrix;
    window.renderDeliveryMatrix = renderDeliveryMatrix;
})();

function openAddDomainModal() {
    var modal = document.getElementById('add-domain-modal');
    var form = document.getElementById('add-domain-form');
    if (!modal) {
        console.error('Add domain modal not found');
        return;
    }
    modal.style.display = 'flex';
    modal.classList.add('active');
    if (form) {
        form.reset();
    }
    var helpEl = document.getElementById('domain-delivery-role-help');
    if (helpEl) {
        helpEl.textContent = stT('settings_js_domain_delivery_help', 'Choose how this hostname is used for site delivery. Apex and www pairs are linked automatically.');
    }
    bindRegisterDomainSuggestInline();
}

function bindRegisterDomainSuggestInline() {
    var domainInput = document.getElementById('domain-name');
    var roleSelect = document.getElementById('domain-delivery-role');
    var helpEl = document.getElementById('domain-delivery-role-help');
    if (!domainInput || domainInput.dataset.suggestBound === '1') return;
    domainInput.dataset.suggestBound = '1';
    var timer = null;
    function suggest() {
        var host = String(domainInput.value || '').trim().toLowerCase();
        if (!host) return;
        fetch(apiUrl('/api/hosting.php?action=suggest_domain_role&host=' + encodeURIComponent(host)), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok' || !payload.data) return;
                if (['cms', 'public', 'api_only'].indexOf(payload.data.role) !== -1) {
                    roleSelect.value = payload.data.role;
                }
                if (helpEl && payload.data.reason) {
                    helpEl.textContent = stT('settings_js_domain_suggested_role', 'Suggested: {role} — {reason}', { role: payload.data.role, reason: payload.data.reason });
                }
            })
            .catch(function () { /* optional */ });
    }
    domainInput.addEventListener('input', function () {
        if (timer) clearTimeout(timer);
        timer = setTimeout(suggest, 350);
    });
    domainInput.addEventListener('blur', suggest);
}

function closeAddDomainModal() {
    var modal = document.getElementById('add-domain-modal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(function() {
            modal.style.display = 'none';
        }, 300); // Wait for transition
    }
}

// Attach event listener to register domain button
(function() {
    function initDomainButtons() {
        var registerBtn = document.getElementById('register-domain-btn');
        if (registerBtn) {
            registerBtn.addEventListener('click', async function (e) {
                e.preventDefault();
                openAddDomainModal();
            });
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDomainButtons);
    } else {
        initDomainButtons();
    }
})();

async function registerDomain() {
    const form = document.getElementById('add-domain-form');
    if (!form) {
        showMessage(stT('settings_js_err_form_missing', 'Error: Form not found'), 'error');
        return;
    }
    
    const formData = new FormData(form);
    
    // Get and validate required fields
    const domain = (formData.get('domain') || '').trim();
    const customerEmail = (formData.get('customer_email') || '').trim();
    
    if (!domain || !customerEmail) {
        showMessage(stT('settings_js_err_domain_email_required', 'Error: Domain and customer email are required'), 'error');
        return;
    }
    
    const domainData = {
        action: 'register_domain',
        domain: domain,
        customer_email: customerEmail,
        permission_level: formData.get('permission_level') || 'read_write',
        read_limit: parseInt(formData.get('read_limit')) || 100,
        write_limit: parseInt(formData.get('write_limit')) || 10,
        enable_analytics: formData.get('enable_analytics') === '1',
        delivery_role: formData.get('delivery_role') || 'api_only',
        _csrf: getCsrfToken()
    };
    
    try {
        const response = await fetch(apiUrl('/api/domain_management.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(domainData)
        });
        
        const responseText = await response.text();
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Failed to parse JSON response:', parseError);
            console.error('Response text:', responseText);
            showMessage(stT('settings_js_err_invalid_server_response', 'Error: Invalid response from server. Check console for details.'), 'error');
            return;
        }
        
        
        if (result.status === 'ok') {
            var roleMsg = result.data && result.data.delivery_role
                ? ' (delivery: ' + result.data.delivery_role + ')'
                : '';
            if (result.data && result.data.delivery_role_warning) {
                showMessage(stT('settings_js_domain_registered_warning', 'Domain registered{role_msg}, but delivery role could not be applied: {warning}', { role_msg: roleMsg, warning: result.data.delivery_role_warning }), 'warning');
            } else {
                showMessage(stT('settings_js_domain_registered_success', 'Domain registered successfully!{role_msg}', { role_msg: roleMsg }), 'success');
            }
            closeAddDomainModal();
            loadDomains();
            if (typeof window.loadDeliveryMatrix === 'function') {
                window.loadDeliveryMatrix();
            }
            if (typeof window.initDeliveryStatus === 'function') {
                window.initDeliveryStatus('#delivery-status-site-delivery');
            }
        } else {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('ai_conn_js_unknown_error', 'Unknown error')), 'error');
        }
    } catch (error) {
        console.error('Error registering domain:', error);
        showMessage(stT('settings_js_err_registering_domain', 'Error registering domain:') + ' ' + error.message, 'error');
    }
}

window.editDomain = async function(domainName) {
    try {
        const response = await fetch(apiUrl('/api/domain_management.php?action=list_licenses'), {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        const data = await response.json();
        
        if (data.status === 'ok' && data.data.licenses) {
            const domain = data.data.licenses.find(d => d.domain === domainName);
            if (domain) {
                const permissions = domain.permissions || {};
                const rateLimits = permissions.rate_limits || {};
                
                document.getElementById('edit-domain-original').value = domainName;
                document.getElementById('edit-domain-permission-level').value = permissions.permission_level || 'read_write';
                document.getElementById('edit-domain-status').value = domain.status || 'active';
                document.getElementById('edit-domain-read-limit').value = rateLimits.read?.limit || 100;
                document.getElementById('edit-domain-write-limit').value = rateLimits.write?.limit || 10;
                document.getElementById('edit-domain-enable-analytics').checked = domain.analytics_enabled || false;
                
                var editModal = document.getElementById('edit-domain-modal');
                if (editModal) {
                    editModal.style.display = 'flex';
                    editModal.classList.add('active');
                } else {
                    console.error('Edit domain modal not found');
                }
            } else {
                console.error('Domain not found:', domainName);
            }
        }
    } catch (error) {
        console.error('Error loading domain details:', error);
        showMessage(stT('settings_js_err_loading_domain', 'Error loading domain details:') + ' ' + error.message, 'error');
    }
};

function closeEditDomainModal() {
    var modal = document.getElementById('edit-domain-modal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(function() {
            modal.style.display = 'none';
        }, 300); // Wait for transition
    }
}

async function updateDomain() {
    const form = document.getElementById('edit-domain-form');
    const formData = new FormData(form);
    const originalDomain = formData.get('original_domain');
    
    const updateData = {
        action: 'update_license',
        domain: originalDomain,
        permissions: {
            permission_level: formData.get('permission_level'),
            rate_limits: {
                read: {
                    limit: parseInt(formData.get('read_limit')) || 100,
                    window: 60
                },
                write: {
                    limit: parseInt(formData.get('write_limit')) || 10,
                    window: 3600
                }
            }
        },
        status: formData.get('status'),
        analytics_enabled: formData.get('enable_analytics') === '1',
        _csrf: getCsrfToken()
    };
    
    try {
        const response = await fetch(apiUrl('/api/domain_management.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(updateData)
        });
        
        const responseText = await response.text();
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Failed to parse JSON response:', parseError);
            console.error('Response text:', responseText);
            showMessage(stT('settings_js_err_invalid_server_response', 'Error: Invalid response from server. Check console for details.'), 'error');
            return;
        }
        
        
        if (result.status === 'ok') {
            showMessage(stT('settings_js_domain_updated', 'Domain updated successfully!'), 'success');
            closeEditDomainModal();
            loadDomains();
        } else {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('ai_conn_js_unknown_error', 'Unknown error')), 'error');
        }
    } catch (error) {
        console.error('Error updating domain:', error);
        showMessage(stT('settings_js_err_updating_domain', 'Error updating domain:') + ' ' + error.message, 'error');
    }
}

window.deleteDomain = async function(domainName) {
    if (!(await BirklyFeedback.confirm(stT('settings_js_confirm_delete_domain', 'Are you sure you want to delete domain "{name}"? This will revoke all access.', { name: domainName })))) {
        return;
    }
    
    try {
        const response = await fetch(apiUrl('/api/domain_management.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete_license',
                domain: domainName,
                _csrf: getCsrfToken()
            })
        });
        
        const result = await response.json();
        
        if (result.status === 'ok') {
            showMessage(stT('settings_js_domain_deleted', 'Domain deleted successfully!'), 'success');
            loadDomains();
        } else {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error deleting domain:', error);
        showMessage(stT('settings_js_err_deleting_domain', 'Error deleting domain:') + ' ' + error.message, 'error');
    }
};

(function() {
    'use strict';
    
    let currentPage = 0;
    const pageSize = 50;
    let allLogs = [];
    let filteredLogs = [];
    const eventTypes = new Set();
    
    // Initialize when tab is shown
    window.initAuditLog = function() {
        loadLogs();
        setupFilters();
        
        const refreshBtn = document.getElementById('refresh-logs');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', async function () {
                currentPage = 0;
                loadLogs(true);
            });
        }
        
        const loadMoreBtn = document.getElementById('load-more');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', async function () {
                currentPage++;
                renderLogs();
            });
        }
    };
    
    // Also initialize activity (for backward compatibility)
    window.initActivity = window.initAuditLog;
    
    function setupFilters() {
        const eventFilter = document.getElementById('event-filter');
        const categoryFilter = document.getElementById('category-filter');
        const dateFilter = document.getElementById('date-filter');
        
        if (eventFilter) {
            eventFilter.addEventListener('change', function() {
                filterLogs();
            });
        }
        
        if (categoryFilter) {
            categoryFilter.addEventListener('change', function() {
                filterLogs();
            });
        }
        
        if (dateFilter) {
            dateFilter.addEventListener('change', function() {
                filterLogs();
            });
        }
    }
    
    function getEventCategory(event) {
        const contentEvents = ['create_entry', 'update_entry', 'delete_entry', 'create_collection', 'update_collection', 'delete_collection', 'publish_entry', 'unpublish_entry', 'scheduled_publish', 'review_action', 'settings_update', 'settings_save_all'];
        const userEvents = ['create_user', 'update_user', 'delete_user', 'invitation_created', 'invitation_accepted', 'login_success', 'login_failed', 'logout'];
        const systemEvents = ['rate_limited', 'plugin_activated', 'plugin_deactivated', 'plugin_deleted'];
        
        if (contentEvents.includes(event)) return 'content';
        if (userEvents.includes(event)) return 'users';
        if (systemEvents.includes(event)) return 'system';
        return 'other';
    }
    
    function formatEventDetails(log) {
        const details = log.details || {};
        let formatted = [];
        
        // Format based on event type
        if (log.event === 'settings_update' || log.event === 'settings_save_all') {
            if (details.category) formatted.push('Category: ' + details.category);
            if (details.settings) {
                const settingsList = Object.keys(details.settings).map(key => {
                    const value = details.settings[key];
                    if (typeof value === 'object') {
                        return key + ': ' + JSON.stringify(value);
                    }
                    return key + ': ' + value;
                });
                formatted.push('Settings: ' + settingsList.join(', '));
            }
        } else if (log.event === 'update_entry' || log.event === 'create_entry') {
            if (details.collection) formatted.push('Collection: ' + details.collection);
            if (details.title) formatted.push('Title: ' + details.title);
            if (details.id) formatted.push('ID: ' + details.id);
        } else if (log.event === 'create_user' || log.event === 'update_user') {
            if (details.email) formatted.push('Email: ' + details.email);
            if (details.role) formatted.push('Role: ' + details.role);
        }
        
        // Add any remaining details
        Object.keys(details).forEach(key => {
            if (!['category', 'settings', 'collection', 'title', 'id', 'email', 'role'].includes(key)) {
                const value = details[key];
                formatted.push(key + ': ' + (typeof value === 'object' ? JSON.stringify(value) : value));
            }
        });
        
        return formatted.length > 0 ? formatted.join(' | ') : (JSON.stringify(details, null, 2));
    }
    
    function filterLogs() {
        const eventFilter = document.getElementById('event-filter')?.value || '';
        const categoryFilter = document.getElementById('category-filter')?.value || '';
        const dateFilter = document.getElementById('date-filter')?.value || '';
        
        filteredLogs = allLogs.filter(log => {
            if (eventFilter && log.event !== eventFilter) {
                return false;
            }
            
            if (categoryFilter && categoryFilter !== 'all') {
                const category = getEventCategory(log.event);
                if (category !== categoryFilter) {
                    return false;
                }
            }
            
            if (dateFilter) {
                const logDate = new Date(log.timestamp).toISOString().split('T')[0];
                if (logDate !== dateFilter) {
                    return false;
                }
            }
            
            return true;
        });
        
        currentPage = 0;
        renderLogs();
    }
    
    function loadLogs(refresh = false) {
        const tbody = document.getElementById('log-entries');
        if (!tbody) return;
        
        if (refresh) {
            tbody.innerHTML = '<tr><td colspan="6"><div class="loading-state"><p>' + escapeHtml(stT('settings_js_loading_table', 'Loading...')) + '</p></div></td></tr>';
        }
        
        // Load both audit logs and activity
        Promise.all([
            fetch(apiUrl('/api/log.php?action=list')).then(r => r.json()),
            fetch(apiUrl('/api/activity.php?action=list&limit=1000')).then(r => r.json())
        ]).then(([logData, activityData]) => {
            allLogs = [];
            
            // Merge audit logs
            if (logData.status === 'ok' && logData.logs) {
                logData.logs.forEach(log => {
                    allLogs.push({
                        ...log,
                        category: getEventCategory(log.event),
                        source: 'audit'
                    });
                    if (log.event) {
                        eventTypes.add(log.event);
                    }
                });
            }
            
            // Merge activity feed (avoid duplicates)
            if (activityData.status === 'ok' && activityData.activity) {
                activityData.activity.forEach(activity => {
                    // Check if we already have this event from audit log
                    const exists = allLogs.some(log => 
                        log.event === activity.event && 
                        log.timestamp === activity.timestamp &&
                        log.user === activity.user
                    );
                    
                    if (!exists) {
                        allLogs.push({
                            timestamp: activity.timestamp,
                            user: activity.user,
                            event: activity.event,
                            ip: activity.details?.ip || 'Unknown',
                            details: activity.details,
                            category: activity.category || getEventCategory(activity.event),
                            message: activity.message,
                            source: 'activity'
                        });
                        if (activity.event) {
                            eventTypes.add(activity.event);
                        }
                    }
                });
            }
            
            // Sort by timestamp (newest first)
            allLogs.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
            
            // Populate event filter
            const eventFilter = document.getElementById('event-filter');
            if (eventFilter) {
                eventFilter.innerHTML = '<option value="">All Events</option>';
                Array.from(eventTypes).sort().forEach(event => {
                    const option = document.createElement('option');
                    option.value = event;
                    option.textContent = event.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    eventFilter.appendChild(option);
                });
            }
            
            filteredLogs = allLogs;
            currentPage = 0;
            renderLogs();
        }).catch(error => {
            console.error('Error loading logs:', error);
            const tbody = document.getElementById('log-entries');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state empty-state--compact empty-state--error" role="alert"><p class="empty-state__description">Error loading logs: ' + escapeHtml(error.message) + '</p></div></td></tr>';
            }
        });
    }
    
    function renderLogs() {
        const tbody = document.getElementById('log-entries');
        if (!tbody) return;
        
        const startIndex = currentPage * pageSize;
        const endIndex = startIndex + pageSize;
        const logsToShow = filteredLogs.slice(0, endIndex);
        
        if (logsToShow.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state empty-state--compact"><p class="empty-state__description">' + escapeHtml(stT('settings_js_no_log_entries', 'No log entries found')) + '</p></div></td></tr>';
            const loadMoreBtn = document.getElementById('load-more');
            if (loadMoreBtn) {
                loadMoreBtn.hidden = true;
            }
            return;
        }
        
        let html = '';
        logsToShow.forEach(log => {
            const timestamp = new Date(log.timestamp).toLocaleString();
            const category = log.category || getEventCategory(log.event);
            const categoryBadge = category === 'content' ? 'badge-info' : category === 'users' ? 'badge-success' : category === 'system' ? 'badge-warning' : 'badge-neutral';
            const formattedDetails = formatEventDetails(log);
            const fullDetails = log.details ? JSON.stringify(log.details, null, 2) : '';
            
            html += `
                <tr>
                    <td class="u-fg-secondary u-nowrap">${timestamp}</td>
                    <td>${escapeHtml(log.user || 'Unknown')}</td>
                    <td><span class="badge ${categoryBadge}">${escapeHtml(log.event || 'Unknown')}</span></td>
                    <td><span class="badge badge-neutral">${escapeHtml(category)}</span></td>
                    <td class="u-fg-secondary u-nowrap">${escapeHtml(log.ip || 'Unknown')}</td>
                    <td>
                        ${formattedDetails ? `<details><summary class="u-clickable u-text-sm">View Details</summary><pre class="u-surface-sunken u-radius-sm u-p-3 u-mt-2 u-text-xs u-mono u-scroll-x">${escapeHtml(formattedDetails)}</pre></details>` : '-'}
                    </td>
                </tr>
            `;
        });
        
        tbody.innerHTML = html;
        
        const loadMoreBtn = document.getElementById('load-more');
        if (loadMoreBtn) {
            loadMoreBtn.hidden = endIndex >= filteredLogs.length;
        }
    }
    
        
    // Auto-initialize if tab is active
    if (document.getElementById('activity-log') && document.getElementById('activity-log').classList.contains('active')) {
        setTimeout(initAuditLog, 100);
    }
})();

// Denylist Management
let denylistCollectionsLoaded = false;

async function loadDenylistCollections() {
    const select = document.getElementById('denylist-collection-select');
    const addBtn = document.getElementById('denylist-add-collection-btn');
    if (!select) return;

    try {
        const response = await fetch(apiUrl('/api/content.php?action=list_collections'));
        const result = await response.json();
        const collections = (result.data && result.data.collections) || result.collections || [];
        select.innerHTML = '<option value="">Select a collection…</option>';
        collections.forEach(col => {
            const slug = col.slug || col.id || '';
            if (!slug) return;
            const opt = document.createElement('option');
            opt.value = slug;
            opt.textContent = (col.name || slug) + ' (' + slug + ')';
            select.appendChild(opt);
        });
        if (addBtn) addBtn.disabled = collections.length === 0;
        denylistCollectionsLoaded = true;
    } catch (error) {
        select.innerHTML = '<option value="">Failed to load collections</option>';
        if (addBtn) addBtn.disabled = true;
    }
}

async function loadDenylist() {
    const container = document.getElementById('denylist-management');
    if (!container) return;

    if (!denylistCollectionsLoaded) {
        loadDenylistCollections();
    }

    try {
        const response = await fetch(apiUrl('/api/plugins_permissions.php?action=get_denylist'));
        const result = await response.json();

        if (result.status === 'ok') {
            const data = result.data || {};
            renderDenylist(data.resources || [], data.deny_collections || [], data.deny_spaces || []);
            populateDenylistPluginSelect(data.available_plugins || []);
        } else {
            container.innerHTML = `<div class="message message-error" role="alert">Error loading denylist: ${escapeHtml(result.message || '')}</div>`;
        }
    } catch (error) {
        container.innerHTML = `<div class="message message-error" role="alert">Error: ${escapeHtml(error.message)}</div>`;
    }
}
window.loadDenylist = loadDenylist;

function populateDenylistPluginSelect(plugins) {
    const select = document.getElementById('denylist-plugin-select');
    const addBtn = document.getElementById('denylist-add-plugin-btn');
    if (!select) return;
    select.innerHTML = '<option value="">Select a plugin…</option>';
    plugins.forEach(function (plugin) {
        const id = plugin.id || '';
        if (!id) return;
        const opt = document.createElement('option');
        opt.value = id;
        opt.textContent = plugin.name || id;
        select.appendChild(opt);
    });
    if (addBtn) addBtn.disabled = plugins.length === 0;
}

function renderDenylistItem(resource, type) {
    const label = type === 'collection' ? 'collection' : (type === 'space' ? 'space' : 'pattern');
    return `
        <div class="data-list-item">
            <code class="u-mono u-text-sm u-fill u-break-word">${escapeHtml(resource)}</code>
            <span class="badge badge-neutral">${label}</span>
            <div class="data-list-actions">
                <button type="button" class="btn btn-sm btn-danger" data-denylist-resource="${escapeHtml(resource)}" onclick="removeDenylistResource(this.dataset.denylistResource)">
                    Remove
                </button>
            </div>
        </div>
    `;
}

function renderDenylist(resources, denyCollections, denySpaces) {
    const container = document.getElementById('denylist-management');
    if (!container) return;

    const collectionSet = new Set(denyCollections || []);
    const customResources = (resources || []).filter(r => !collectionSet.has(r));

    let html = '';

    if ((denyCollections || []).length > 0) {
        html += '<h3 class="u-label">Protected collections</h3><div class="data-list data-list--flush">';
        denyCollections.forEach(resource => {
            html += renderDenylistItem(resource, 'collection');
        });
        html += '</div>';
    }

    if (customResources.length > 0) {
        html += '<h3 class="u-label u-mt-4">Custom patterns</h3><div class="data-list data-list--flush">';
        customResources.forEach(resource => {
            html += renderDenylistItem(resource, 'pattern');
        });
        html += '</div>';
    }

    if ((denySpaces || []).length > 0) {
        html += '<h3 class="u-label u-mt-4">Protected spaces</h3><div class="data-list data-list--flush">';
        denySpaces.forEach(resource => {
            html += renderDenylistItem(resource, 'space');
        });
        html += '</div>';
    }

    if (!html) {
        container.innerHTML = '<p class="settings-help">' + escapeHtml(stT('settings_js_no_protected_resources', 'No protected resources. All collections are accessible to plugins (subject to capability checks).')) + '</p>';
        return;
    }

    container.innerHTML = html;
}

async function addDenylistPlugin() {
    const select = document.getElementById('denylist-plugin-select');
    const pluginId = select?.value || '';
    if (!pluginId) {
        showMessage(stT('settings_js_select_plugin_first', 'Select a plugin first'), 'error');
        return;
    }

    try {
        const response = await fetch(apiUrl('/api/plugins_permissions.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'add_to_denylist',
                resource: pluginId,
                as_space: true,
                _csrf: getCsrfToken()
            })
        });
        const result = await response.json();
        if (result.status === 'ok') {
            showMessage(stT('settings_js_plugin_denylist_added', 'Plugin added to denylist'), 'success');
            loadDenylist();
        } else {
            showMessage(result.message || stT('settings_js_failed_add_plugin', 'Failed to add plugin'), 'error');
        }
    } catch (error) {
        showMessage(error.message || stT('settings_js_failed_add_plugin', 'Failed to add plugin'), 'error');
    }
}

async function addDenylistCollection() {
    const select = document.getElementById('denylist-collection-select');
    const slug = select?.value || '';
    if (!slug) {
        showMessage(stT('settings_js_select_collection_first', 'Select a collection first'), 'error');
        return;
    }

    try {
        const response = await fetch(apiUrl('/api/plugins_permissions.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'add_to_denylist',
                resource: slug,
                as_collection: true,
                _csrf: getCsrfToken()
            })
        });

        const result = await response.json();

        if (result.status === 'ok') {
            showMessage(stT('settings_js_collection_denylist_added', 'Collection added to denylist'), 'success');
            loadDenylist();
        } else {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + result.message, 'error');
        }
    } catch (error) {
        showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + error.message, 'error');
    }
}

async function addDenylistResource() {
    const resource = await BirklyFeedback.prompt(
        'Enter resource identifier (collection slug, entry ID, or pattern like "payroll/*"):',
        '',
        { title: 'Add to denylist', confirmLabel: 'Add', placeholder: 'e.g. payroll/*' }
    );
    if (!resource || resource.trim() === '') return;
    
    try {
        const response = await fetch(apiUrl('/api/plugins_permissions.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'add_to_denylist',
                resource: resource.trim(),
                _csrf: getCsrfToken()
            })
        });
        
        const result = await response.json();
        
        if (result.status === 'ok') {
            showMessage(stT('settings_js_resource_denylist_added', 'Resource added to denylist'), 'success');
            loadDenylist();
        } else {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + result.message, 'error');
        }
    } catch (error) {
        showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + error.message, 'error');
    }
}

async function removeDenylistResource(resource) {
    if (!(await BirklyFeedback.confirm(stT('settings_js_confirm_remove_denylist', 'Remove "{resource}" from the denylist?', { resource: resource })))) return;
    
    try {
        const response = await fetch(apiUrl('/api/plugins_permissions.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'remove_from_denylist',
                resource: resource,
                _csrf: getCsrfToken()
            })
        });
        
        const result = await response.json();
        
        if (result.status === 'ok') {
            showMessage(stT('settings_js_resource_denylist_removed', 'Resource removed from denylist'), 'success');
            loadDenylist();
        } else {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + result.message, 'error');
        }
    } catch (error) {
        showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + error.message, 'error');
    }
}




// Storage Settings
document.getElementById('storage-backend')?.addEventListener('change', function() {
    const dbConfig = document.getElementById('database-config');
    if (dbConfig) {
        dbConfig.hidden = !['mysql', 'postgresql'].includes(this.value);
    }
});

function testDatabaseConnection() {
    const backend = document.getElementById('storage-backend')?.value;
    const host = document.getElementById('db-host')?.value;
    const port = document.getElementById('db-port')?.value;
    const name = document.getElementById('db-name')?.value;
    const user = document.getElementById('db-user')?.value;
    const password = document.getElementById('db-password')?.value;
    const prefix = document.getElementById('db-prefix')?.value;
    const resultEl = document.getElementById('db-test-result');
    
    if (!resultEl) return;
    resultEl.textContent = stT('settings_js_testing_connection', 'Testing...');
    resultEl.style.color = 'var(--text-2)';
    
    fetch(apiUrl('/api/settings.php?action=test_database'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            backend: backend,
            host: host,
            port: port,
            name: name,
            user: user,
            password: password,
            prefix: prefix,
            _csrf: getCsrfToken()
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'ok') {
            resultEl.textContent = '✓ Connection successful';
            resultEl.style.color = 'var(--success)';
        } else {
            resultEl.textContent = '✗ ' + (data.message || 'Connection failed');
            resultEl.style.color = 'var(--danger)';
        }
    })
    .catch(error => {
        resultEl.textContent = '✗ Error: ' + error.message;
        resultEl.style.color = 'var(--danger)';
    });
}

async function migrateStorage() {
    if (!(await BirklyFeedback.confirm(stT('settings_js_confirm_migration_backup', 'This will migrate all data to the selected storage backend. Make sure you have a backup! Continue?')))) {
        return;
    }
    
    const backend = document.getElementById('storage-backend')?.value;
    if (!backend || backend === 'flatfile') {
        showMessage(stT('settings_js_select_db_backend', 'Please select a database backend first'), 'error');
        return;
    }
    
    if (!(await BirklyFeedback.confirm(stT('settings_js_confirm_migration_wait', 'Migration may take some time. Do not close this window. Continue?')))) {
        return;
    }

    const migrateBtn = document.querySelector('[data-admin-action="migrateStorage"]');
    const statusEl = document.getElementById('migration-status');
    const defaultBtnLabel = migrateBtn ? migrateBtn.textContent : '';
    const host = document.getElementById('db-host')?.value;
    const port = document.getElementById('db-port')?.value;
    const name = document.getElementById('db-name')?.value;
    const user = document.getElementById('db-user')?.value;
    const password = document.getElementById('db-password')?.value;
    const prefix = document.getElementById('db-prefix')?.value;

    if (!host || !name || !user) {
        showMessage(stT('settings_js_migration_db_fields_required', 'Enter database host, name, and user before migrating'), 'error');
        return;
    }

    if (migrateBtn) {
        migrateBtn.disabled = true;
        migrateBtn.textContent = stT('settings_js_migrating_btn', 'Migrating...');
    }
    if (statusEl) {
        statusEl.textContent = stT('settings_js_migration_in_progress', 'Migration in progress...');
        statusEl.style.color = 'var(--text-2)';
    }
    
    try {
        const response = await fetch(apiUrl('/api/settings.php?action=migrate_storage'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                backend: backend,
                host: host,
                port: port,
                name: name,
                user: user,
                password: password,
                prefix: prefix,
                _csrf: getCsrfToken()
            })
        });

        let data;
        try {
            data = await response.json();
        } catch (parseError) {
            throw new Error('Server returned an invalid response. Check server logs.');
        }

        if (data.status === 'ok') {
            const migratedCount = data.data && typeof data.data.migrated === 'number'
                ? ' (' + data.data.migrated + ' files)'
                : '';
            const successMessage = 'Migration completed successfully' + migratedCount + '!';
            showMessage(successMessage, 'success');
            if (statusEl) {
                statusEl.textContent = '✓ ' + successMessage;
                statusEl.style.color = 'var(--success)';
            }
        } else {
            const failureMessage = data.message || stT('error_unknown', 'Unknown error');
            showMessage(stT('settings_js_migration_failed', 'Migration failed:') + ' ' + failureMessage, 'error');
            if (statusEl) {
                statusEl.textContent = '✗ ' + failureMessage;
                statusEl.style.color = 'var(--danger)';
            }
        }
    } catch (error) {
        showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + error.message, 'error');
        if (statusEl) {
            statusEl.textContent = '✗ ' + error.message;
            statusEl.style.color = 'var(--danger)';
        }
    } finally {
        if (migrateBtn) {
            migrateBtn.disabled = false;
            migrateBtn.textContent = defaultBtnLabel;
        }
    }
}

// Load denylist when Team & security tab is shown
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('denylist-add-collection-btn')?.addEventListener('click', addDenylistCollection);
    document.getElementById('denylist-add-plugin-btn')?.addEventListener('click', addDenylistPlugin);

    const securityTab = document.querySelector('[data-tab="security"]');
    if (securityTab) {
        securityTab.addEventListener('click', async function () {
            setTimeout(loadDenylist, 100);
        });
    }

    var securityPane = document.querySelector('.tab-pane#security.active');
    var params = new URLSearchParams(window.location.search);
    var tab = params.get('tab');
    if (tab === 'users-roles') tab = 'team-access';
    if (tab === 'security' || window.location.hash === '#security' || securityPane) {
        loadDenylist();
    }

    const teamAccessTab = document.querySelector('[data-tab="team-access"]');
    if (teamAccessTab) {
        teamAccessTab.addEventListener('click', function () {
            setTimeout(loadTeamInvitations, 100);
        });
    }

    var teamPane = document.querySelector('.tab-pane#team-access.active');
    if (tab === 'team-access' || window.location.hash === '#team-access' || teamPane) {
        loadTeamInvitations();
    }
});

// Global save bar — saves all dirty form tabs, or the current tab when nothing is dirty yet
async function handleGlobalSave() {
    var tabId = typeof window.getSettingsCurrentTab === 'function' ? window.getSettingsCurrentTab() : 'general';
    var forms = {
        general: null,
        security: null,
        storage: null,
        monitoring: null,
    };
    var tabsToSave = typeof window.getSettingsTabsToSave === 'function'
        ? window.getSettingsTabsToSave(tabId)
        : [];

    if (!tabsToSave.length) {
        if (globalThis.BirklyFeedback && typeof BirklyFeedback.toast === 'function') {
            BirklyFeedback.toast(stT('settings_js_no_pending_changes', 'No pending changes to save'), 'info');
        }
        return;
    }

    var saveAnalytics = tabsToSave.indexOf('analytics') >= 0 && typeof window.saveAnalyticsSettings === 'function';
    var coreTabs = tabsToSave.filter(function (id) { return id !== 'analytics'; });

    tabsToSave.forEach(function (trackedTabId) {
        if (trackedTabId === 'general') {
            forms.general = document.getElementById('general-form');
        } else if (trackedTabId === 'security') {
            forms.security = document.getElementById('security-form');
        } else if (trackedTabId === 'system') {
            forms.storage = document.getElementById('storage-form');
            forms.monitoring = document.getElementById('monitoring-form');
        }
    });

    [forms.general, forms.security, forms.storage, forms.monitoring].forEach(function (form) {
        if (form && form.classList) {
            form.classList.add('was-validated');
        }
    });

    var hasCoreForms = !!(forms.general || forms.security || forms.storage || forms.monitoring);
    if (!hasCoreForms && !saveAnalytics) {
        if (globalThis.BirklyFeedback && typeof BirklyFeedback.toast === 'function') {
            BirklyFeedback.toast(stT('settings_js_changes_save_per_action', 'Changes on this tab are saved when you confirm each action'), 'info');
        }
        return;
    }

    try {
        if (saveAnalytics) {
            await window.saveAnalyticsSettings();
        }
        if (hasCoreForms && coreTabs.length) {
            await collectAndSaveSettings(coreTabs, forms);
        } else if (saveAnalytics) {
            if (typeof window.showMessage === 'function') {
                window.showMessage(stT('settings_js_settings_saved', 'Settings saved successfully!'), 'success');
            } else if (globalThis.BirklyFeedback && typeof BirklyFeedback.toast === 'function') {
                BirklyFeedback.toast(stT('settings_js_analytics_settings_saved', 'Analytics settings saved'), 'success');
            }
        }
    } catch (err) {
        var msg = (err && err.message) ? err.message : 'Save failed';
        if (typeof window.showMessage === 'function') {
            window.showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + msg, 'error');
        } else if (globalThis.BirklyFeedback && typeof BirklyFeedback.toast === 'function') {
            BirklyFeedback.toast(msg, 'error');
        }
    }
}

function syncSavedLogoState(generalForm, logoPath) {
    if (!generalForm) {
        return;
    }

    var logoInput = generalForm.querySelector('#project-logo');
    if (logoInput) {
        logoInput.value = '';
        logoInput.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (!logoPath) {
        return;
    }

    document.querySelectorAll('.logo-preview-img').forEach(function (image) {
        image.src = logoPath;
    });

    var preview = document.getElementById('logo-preview');
    if (preview) {
        preview.hidden = false;
    }
}

async function collectAndSaveSettings(tabsToSave, forms) {
    const savedTabs = Array.isArray(tabsToSave) ? tabsToSave : [tabsToSave];
    const settings = {};
    const generalForm = forms.general || null;
    const securityForm = forms.security || null;
    const storageForm = forms.storage || null;
    const monitoringForm = forms.monitoring || null;

    function appendFormData(form) {
        if (!form) return;
        const formData = new FormData(form);
        for (const [key, value] of formData.entries()) {
            if (key !== 'project_logo') {
                settings[key] = value;
            }
        }
        form.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
            settings[checkbox.name] = checkbox.checked;
        });
    }

    appendFormData(generalForm);
    appendFormData(securityForm);
    appendFormData(storageForm);
    appendFormData(monitoringForm);

    let logoPath = '';
    if (generalForm) {
        const logoFile = new FormData(generalForm).get('project_logo');
        if (logoFile && logoFile.size > 0) {
            try {
                const logoFormData = new FormData();
                logoFormData.append('file', logoFile);
                logoFormData.append('_csrf', getCsrfToken());

                const uploadResponse = await fetch(apiUrl('/api/settings.php?action=upload_logo'), {
                    method: 'POST',
                    body: logoFormData
                });

                const uploadResult = await uploadResponse.json();
                if (uploadResult.status === 'ok') {
                    logoPath = uploadResult.data.logoPath;
                } else {
                    showMessage(stT('settings_js_err_uploading_logo', 'Error uploading logo:') + ' ' + (uploadResult.message || stT('ai_conn_js_unknown_error', 'Unknown error')), 'error');
                    return;
                }
            } catch (error) {
                showMessage(stT('settings_js_err_uploading_logo', 'Error uploading logo:') + ' ' + error.message, 'error');
                return;
            }
        }
    }

    if (logoPath) {
        settings.brandingLogoPath = logoPath;
    }

    try {
        const response = await fetch(apiUrl('/api/settings.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'save_all',
                settings: settings,
                _csrf: getCsrfToken()
            })
        });

        const result = await response.json();

        if (result.status === 'ok') {
            syncSavedLogoState(generalForm, logoPath);
            if (typeof window.markSettingsClean === 'function') {
                savedTabs.forEach(function (savedTabId) {
                    window.markSettingsClean(savedTabId);
                });
            }
            showMessage(stT('settings_js_settings_saved', 'Settings saved successfully!'), 'success');
        } else {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + result.message, 'error');
        }
    } catch (error) {
        showMessage(stT('settings_js_err_saving_settings', 'Error saving settings:') + ' ' + error.message, 'error');
    }
}

function initGlobalSave() {
    const saveBtn = document.getElementById('global-save-settings-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', handleGlobalSave);
    }

    const generalForm = document.getElementById('general-form');
    const securityForm = document.getElementById('security-form');
    const storageForm = document.getElementById('storage-form');
    const monitoringForm = document.getElementById('monitoring-form');

    if (generalForm) {
        generalForm.addEventListener('submit', function (e) {
            e.preventDefault();
            handleGlobalSave();
        });

        const logoInput = document.getElementById('project-logo');
        if (logoInput) {
            logoInput.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (event) {
                        const preview = document.getElementById('logo-preview');
                        const previewImg = document.getElementById('logo-preview-img');
                        if (preview && previewImg) {
                            previewImg.src = event.target.result;
                            preview.hidden = false;
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        const removeLogoBtn = document.getElementById('remove-logo-btn');
        if (removeLogoBtn) {
            removeLogoBtn.addEventListener('click', async function () {
                if (await BirklyFeedback.confirm(stT('settings_js_confirm_remove_logo', 'Are you sure you want to remove the logo? The default Birkly icon will be used.'))) {
                    try {
                        const response = await fetch(apiUrl('/api/settings.php'), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                action: 'update',
                                category: 'branding',
                                settings: {
                                    logoPath: ''
                                },
                                _csrf: getCsrfToken()
                            })
                        });

                        const result = await response.json();
                        if (result.status === 'ok') {
                            showMessage(stT('settings_js_logo_removed', 'Logo removed successfully!'), 'success');
                            setTimeout(function () { location.reload(); }, 1000);
                        } else {
                            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + result.message, 'error');
                        }
                    } catch (error) {
                        showMessage(stT('settings_js_err_removing_logo', 'Error removing logo:') + ' ' + error.message, 'error');
                    }
                }
            });
        }
    }

    if (securityForm) {
        securityForm.addEventListener('submit', function (e) {
            e.preventDefault();
            handleGlobalSave();
        });
    }

    if (storageForm) {
        storageForm.addEventListener('submit', function (e) {
            e.preventDefault();
            handleGlobalSave();
        });
    }

    if (monitoringForm) {
        monitoringForm.addEventListener('submit', function (e) {
            e.preventDefault();
            handleGlobalSave();
        });
    }

    initRebuildSystem();
}

function initRebuildSystem() {
    const openBtn = document.getElementById('open-rebuild-system-btn');
    const modal = document.getElementById('rebuild-system-modal');
    const closeBtn = document.getElementById('close-rebuild-system-modal');
    const cancelBtn = document.getElementById('cancel-rebuild-system-btn');
    const confirmBtn = document.getElementById('confirm-rebuild-system-btn');
    const confirmationInput = document.getElementById('rebuild-system-confirmation');

    if (!openBtn || !modal || !confirmBtn || !confirmationInput) {
        return;
    }

    function openModal() {
        confirmationInput.value = '';
        confirmBtn.disabled = true;
        modal.style.display = 'flex';
        modal.classList.add('active');
        confirmationInput.focus();
    }

    function closeModal() {
        modal.classList.remove('active');
        modal.style.display = 'none';
        confirmationInput.value = '';
        confirmBtn.disabled = true;
    }

    function updateConfirmState() {
        confirmBtn.disabled = confirmationInput.value.trim().toUpperCase() !== 'REBUILD';
    }

    openBtn.addEventListener('click', openModal);
    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);
    confirmationInput.addEventListener('input', updateConfirmState);

    modal.addEventListener('click', async function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    confirmBtn.addEventListener('click', async function () {
        if (confirmationInput.value.trim().toUpperCase() !== 'REBUILD') {
            return;
        }

        confirmBtn.disabled = true;
        confirmBtn.textContent = stT('settings_js_rebuilding_btn', 'Rebuilding…');

        try {
            const response = await fetch(apiUrl('/api/settings.php'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'rebuild_system',
                    confirmation: confirmationInput.value.trim(),
                    _csrf: getCsrfToken(),
                }),
            });

            const result = await response.json();
            if (result.status === 'ok') {
                showMessage(result.message || stT('settings_js_system_rebuilt', 'System rebuilt successfully'), 'success');
                window.location.href = apiUrl(result.redirect || '/admin/index.php?page=onboarding');
                return;
            }

            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + (result.message || stT('settings_js_failed_rebuild', 'Failed to rebuild system')), 'error');
        } catch (error) {
            showMessage(stT('feedback_error_prefix', 'Error:') + ' ' + error.message, 'error');
        } finally {
            confirmBtn.textContent = stT('settings_js_rebuild_system_btn', 'Rebuild system');
            updateConfirmState();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGlobalSave);
} else {
    initGlobalSave();
}
