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
    const messageEl = document.getElementById('settings-message');
    if (!messageEl) return;
    messageEl.textContent = message;
    messageEl.className = 'message ' + (type === 'success' ? 'success' : type === 'error' ? 'error' : type || 'info');
    messageEl.style.display = 'flex';
    setTimeout(function () {
        messageEl.style.display = 'none';
    }, 5000);
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
    const form = container.closest('form');
    if (!form) {
        console.warn('Permissions grid container is not inside a form');
    }
    
    container.innerHTML = '';
    
    availableResources.forEach(resource => {
        const section = document.createElement('div');
        section.className = 'permission-section';
        section.style.cssText = 'background: var(--bg-secondary, #f8f9fa); border: 1px solid var(--border-color, #e2e8f0); border-radius: var(--radius-md, 8px); padding: 1rem;';
        
        availableActions.forEach(action => {
            const label = document.createElement('label');
            label.className = 'checkbox-label';
            label.style.cssText = 'display: flex; align-items: center; font-size: 0.875rem; color: var(--text-primary, #1e293b); cursor: pointer; user-select: none; padding: 0.5rem; border-radius: 4px; transition: background-color 0.15s ease;';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = `permissions[${resource}][]`;
            checkbox.value = action;
            checkbox.setAttribute('data-resource', resource);
            checkbox.checked = hasPermission(permissions, resource, action);
            // Simple, clear checkbox styling
            checkbox.style.cssText = 'width: 20px; height: 20px; min-width: 20px; min-height: 20px; cursor: pointer; margin: 0; margin-right: 8px; flex-shrink: 0;';
            
            checkbox.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            
            const span = document.createElement('span');
            span.textContent = action.charAt(0).toUpperCase() + action.slice(1);
            span.style.cssText = 'cursor: pointer;';
            
            label.appendChild(checkbox);
            label.appendChild(span);
            
            // Simple hover effect
            label.addEventListener('mouseenter', function() {
                this.style.backgroundColor = 'rgba(0, 0, 0, 0.05)';
            });
            
            label.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
            
            const checkboxesDiv = section.querySelector('.permission-checkboxes') || document.createElement('div');
            if (!section.querySelector('.permission-checkboxes')) {
                checkboxesDiv.className = 'permission-checkboxes';
                checkboxesDiv.style.cssText = 'display: flex; flex-direction: column; gap: 0.5rem;';
                
                const h4 = document.createElement('h4');
                h4.textContent = resource;
                h4.style.cssText = 'margin: 0 0 0.5rem 0; font-size: 0.9rem; color: var(--text-primary, #1e293b); text-transform: capitalize; font-weight: 600;';
                section.appendChild(h4);
                section.appendChild(checkboxesDiv);
            }
            
            checkboxesDiv.appendChild(label);
        });
        
        container.appendChild(section);
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

function openAddRoleModal() {
    const modal = document.getElementById('add-role-modal');
    if (!modal) {
        console.error('Add role modal not found');
        showMessage('Role modal not found. Please refresh the page.', 'error');
        return;
    }
    modal.style.display = 'flex';
    modal.classList.add('active');
    const form = document.getElementById('add-role-form');
    if (form) form.reset();
    createPermissionsGrid('add-permissions-grid');
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
        showMessage('Edit role modal not found. Please refresh the page.', 'error');
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
            
        } else {
            showMessage('Error loading role: ' + (result.message || 'Unknown error'), 'error');
            closeEditRoleModal();
        }
    } catch (error) {
        console.error('Error loading role:', error);
        showMessage('Error loading role: ' + error.message, 'error');
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
        showMessage('Error: Form not found', 'error');
        return;
    }
    
    const roleId = document.getElementById('edit-role-id').value;
    const nameInput = form.querySelector('#edit-role-name');
    const descriptionInput = form.querySelector('#edit-role-description');
    
    if (!nameInput || !nameInput.value.trim()) {
        showMessage('Error: Role name is required', 'error');
        nameInput?.focus();
        return;
    }
    
    const roleName = nameInput.value.trim();
    const roleDescription = descriptionInput ? descriptionInput.value.trim() : '';
    
    // Collect permissions
    const permissions = collectPermissions(form);
    
    const roleData = {
        role: {
            id: roleId,
            name: roleName,
            description: roleDescription,
            permissions: permissions
        },
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
            showMessage('Role updated successfully!', 'success');
            closeEditRoleModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage('Error: ' + (result.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error updating role:', error);
        showMessage('Error updating role: ' + error.message, 'error');
    }
}

async function createRole() {
    const form = document.getElementById('add-role-form');
    if (!form) {
        console.error('Form not found');
        showMessage('Error: Form not found', 'error');
        return;
    }
    
    // Get form values
    const nameInput = form.querySelector('#role-name');
    const descriptionInput = form.querySelector('#role-description');
    
    if (!nameInput || !nameInput.value.trim()) {
        showMessage('Error: Role name is required', 'error');
        nameInput?.focus();
        return;
    }
    
    const roleName = nameInput.value.trim();
    const roleDescription = descriptionInput ? descriptionInput.value.trim() : '';
    
    // Collect permissions
    const permissions = collectPermissions(form);
    
    const roleData = {
        action: 'create_role',
        role: {
            id: roleName.toLowerCase().replace(/[^a-z0-9]/g, '-'),
            name: roleName,
            description: roleDescription,
            permissions: permissions
        },
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
            showMessage('Role created successfully!', 'success');
            closeAddRoleModal();
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage('Error: ' + (result.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error creating role:', error);
        showMessage('Error creating role: ' + error.message, 'error');
    }
}

function deleteRole(roleId) {
    if (!confirm('Are you sure you want to delete this role? This action cannot be undone.')) {
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
            showMessage('Role deleted successfully!', 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage('Error: ' + result.message, 'error');
        }
    })
    .catch(error => {
        showMessage('Error deleting role: ' + error.message, 'error');
    });
}

// Ensure functions are available on window for onclick handlers
window.openAddRoleModal = openAddRoleModal;
window.closeAddRoleModal = closeAddRoleModal;
window.createRole = createRole;
window.editRole = editRole;
window.closeEditRoleModal = closeEditRoleModal;
window.updateRole = updateRole;
window.deleteRole = deleteRole;

// Tab functionality - simplified and robust
(function() {
    var LEGACY_AI_HASH_MAP = {
        'ai-connections': 'outbound-connections',
        'mcp-connections': 'inbound-mcp'
    };

    function resolveSettingsHash(hash) {
        if (!hash) return { tab: null, scrollTo: null, legacyHash: null };
        if (hash === 'ai-integrations') {
            return { tab: 'ai-integrations', scrollTo: null, legacyHash: null };
        }
        if (LEGACY_AI_HASH_MAP[hash]) {
            return { tab: 'ai-integrations', scrollTo: LEGACY_AI_HASH_MAP[hash], legacyHash: hash };
        }
        return { tab: hash, scrollTo: null, legacyHash: null };
    }

    function scrollToAiSection(sectionId) {
        if (!sectionId) return;
        var section = document.getElementById(sectionId);
        if (section) {
            setTimeout(function() {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 150);
        }
    }

    function activateTab(tabId, tabButtons, tabPanes, scrollTo, urlHash) {
        if (!tabId) return;
        var tabButton = document.querySelector('[data-tab="' + tabId + '"]');
        var tabPane = document.getElementById(tabId);
        if (!tabButton || !tabPane) return;

        for (var j = 0; j < tabButtons.length; j++) {
            tabButtons[j].classList.remove('active');
        }
        for (var k = 0; k < tabPanes.length; k++) {
            tabPanes[k].classList.remove('active');
        }

        tabButton.classList.add('active');
        tabPane.classList.add('active');

        var hashForUrl = urlHash || tabId;
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '?page=settings#' + hashForUrl);
        }

        setTimeout(function() {
            if (tabId === 'activity-log' && typeof window.initAuditLog === 'function') {
                window.initAuditLog();
            } else if (tabId === 'webhooks' && typeof window.initWebhooksManager === 'function') {
                window.initWebhooksManager(window.webhooksData || {});
            } else if (tabId === 'redirects' && typeof window.initRedirectsManager === 'function') {
                window.initRedirectsManager();
            } else if (tabId === 'general' && typeof window.initUpdates === 'function') {
                window.initUpdates();
            } else if (tabId === 'domains' && typeof window.loadDomains === 'function') {
                window.loadDomains();
            } else if (tabId === 'project' && typeof window.initProjectSettings === 'function') {
                window.initProjectSettings();
            } else if (tabId === 'ai-integrations') {
                window.dispatchEvent(new CustomEvent('birkly:ai-integrations-tab'));
            }
            scrollToAiSection(scrollTo);
        }, 100);

        var tabsRoot = document.querySelector('.settings-tabs.tabs-v2-root');
        if (tabsRoot && window.Birkly && window.Birkly.TabsV2 && typeof window.Birkly.TabsV2.refresh === 'function') {
            window.Birkly.TabsV2.refresh(tabsRoot);
        }
    }

    function getSettingsTabButtons() {
        return document.querySelectorAll('.settings-tabs .tab[data-tab]');
    }

    function getSettingsTabPanes() {
        return document.querySelectorAll('.settings-tab-panels > .tab-pane');
    }

    function initTabs() {
        var tabNav = document.querySelector('.settings-tabs .tabs');
        var tabButtons = getSettingsTabButtons();
        var tabPanes = getSettingsTabPanes();

        if (!tabNav || !tabButtons.length) {
            console.error('Settings tab navigation not found');
            return;
        }

        if (!tabPanes.length) {
            console.error('Settings tab panes not found');
            return;
        }

        // Event delegation — survives DOM updates and avoids duplicate handlers
        tabNav.addEventListener('click', function (e) {
            var btn = e.target.closest('.tab[data-tab]');
            if (!btn || !tabNav.contains(btn)) {
                return;
            }
            e.preventDefault();
            var targetTab = btn.getAttribute('data-tab');
            if (!targetTab) {
                return;
            }
            activateTab(targetTab, getSettingsTabButtons(), getSettingsTabPanes(), null, targetTab);
        });
        
        // Handle hash on load (including legacy AI tab hashes)
        var rawHash = window.location.hash.replace('#', '');
        var resolved = resolveSettingsHash(rawHash);
        if (resolved.tab) {
            activateTab(resolved.tab, getSettingsTabButtons(), getSettingsTabPanes(), resolved.scrollTo, rawHash || resolved.tab);
        } else {
            var defaultTab = document.querySelector('.settings-tabs .tab.active');
            if (defaultTab && defaultTab.getAttribute('data-tab') === 'domains') {
                setTimeout(function () {
                    if (typeof window.loadDomains === 'function') {
                        window.loadDomains();
                    }
                }, 100);
            }
        }

        window.addEventListener('hashchange', function () {
            var hash = window.location.hash.replace('#', '');
            var next = resolveSettingsHash(hash);
            if (next.tab) {
                activateTab(next.tab, getSettingsTabButtons(), getSettingsTabPanes(), next.scrollTo, hash || next.tab);
            }
        });
    }

    window.initSettingsTabs = initTabs;

    document.addEventListener('DOMContentLoaded', initTabs);
    
    // Initialize updates if general tab is active on page load
    if (document.getElementById('general') && document.getElementById('general').classList.contains('active')) {
        setTimeout(function() {
            if (typeof window.initUpdates === 'function') {
                window.initUpdates();
            }
        }, 200);
    }
})();





// Invitation functions
function openInviteUserModal() {
    document.getElementById('invite-user-modal').classList.add('active');
    document.getElementById('invite-user-form').reset();
}

function closeInviteUserModal() {
    document.getElementById('invite-user-modal').classList.remove('active');
}

async function sendInvitation() {
    const form = document.getElementById('invite-user-form');
    const formData = new FormData(form);
    
    const invitationData = {
        action: 'create_invitation',
        email: formData.get('email'),
        role: formData.get('role'),
        expires_days: parseInt(formData.get('expires_days')),
        _csrf: getCsrfToken()
    };
    
    try {
        const response = await fetch(apiUrl('/api/invitations.php'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin', // Include cookies/session
            body: JSON.stringify(invitationData)
        });
        
        const result = await response.json();
        
        if (result.status === 'ok') {
            let message = 'Invitation sent successfully!';
            if (result.data && result.data.localhost_note) {
                message = result.message + '\n\n' + result.data.localhost_note;
            }
            showMessage(message, 'success');
            closeInviteUserModal();
        } else {
            showMessage('Error: ' + result.message, 'error');
        }
    } catch (error) {
        showMessage('Error sending invitation: ' + error.message, 'error');
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
            showMessage('User created successfully!', 'success');
            closeAddUserModal();
            // Reload the page to show the new user
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage('Error: ' + result.message, 'error');
        }
    } catch (error) {
        showMessage('Error creating user: ' + error.message, 'error');
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
            showMessage('User updated successfully!', 'success');
            closeEditUserModal();
            // Reload the page to show the updated user
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage('Error: ' + result.message, 'error');
        }
    } catch (error) {
        showMessage('Error updating user: ' + error.message, 'error');
    }
}

function confirmDeleteUser(userId, email) {
    if (confirm('Are you sure you want to delete user "' + email + '"? This action cannot be undone.')) {
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
            showMessage('User deleted successfully!', 'success');
            // Reload the page to show the updated user list
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showMessage('Error: ' + result.message, 'error');
        }
    } catch (error) {
        showMessage('Error deleting user: ' + error.message, 'error');
    }
}

function deleteUserFromModal() {
    const userId = document.getElementById('edit-user-id').value;
    const email = document.getElementById('edit-user-email').value;
    closeEditUserModal();
    confirmDeleteUser(userId, email);
}

function revokeUserMcpSessions(userId, label) {
    if (!userId) return;
    const who = label || 'this user';
    if (!confirm('Revoke all active AI app connections for ' + who + '? They will need to sign in again in Claude, Cursor, or ChatGPT.')) {
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
                showMessage(result.message || 'AI connections revoked', 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showMessage('Error: ' + (result.message || 'Could not revoke AI access'), 'error');
            }
        })
        .catch((error) => {
            showMessage('Error revoking AI access: ' + error.message, 'error');
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
            showMessage(result.message || 'Plugin ' + action + 'd successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            var errorMsg = 'Error: ' + (result.message || 'Failed to ' + action + ' plugin');
            if (result.errors && Array.isArray(result.errors) && result.errors.length > 0) {
                errorMsg += ' — ' + result.errors.join('; ');
            }
            showMessage(errorMsg, 'error');
        }
    } catch (error) {
        showMessage('Error: ' + error.message, 'error');
    }
}

async function deletePlugin(pluginId) {
    if (!confirm('Are you sure you want to delete this plugin? This action cannot be undone.')) {
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
            showMessage(result.message || 'Plugin deleted successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage('Error: ' + (result.message || 'Failed to delete plugin'), 'error');
        }
    } catch (error) {
        showMessage('Error: ' + error.message, 'error');
    }
}

// 2FA is managed per-user in Account settings (admin/templates/account.html)

// Domain Management Functions
(function() {
    'use strict';
    
    // Domains loading is handled in the main tab click handler
    
    async function loadDomains() {
        var listContainer = document.getElementById('domains-list');
        if (!listContainer) {
            console.error('domains-list container not found');
            return;
        }
        
        listContainer.innerHTML = '<div class="loading-state"><div class="spinner"></div><p>Loading domains...</p></div>';
        
        try {
            var response = await fetch(apiUrl('/api/domain_management.php?action=list_licenses'), {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
            var data = await response.json();
            
            if (data.status === 'ok' && data.data && data.data.licenses) {
                renderDomains(data.data.licenses);
            } else {
                listContainer.innerHTML = '<div class="empty-state"><p>No domains registered yet.</p></div>';
            }
            attachDomainsCopyButtons(document.getElementById('domains'));
        } catch (error) {
            console.error('Error loading domains:', error);
            listContainer.innerHTML = '<div class="error-state"><p>Error loading domains: ' + escapeHtml(error.message) + '</p></div>';
        }
    }
    
    function buildDomainTrackingScript(domainName) {
        var scriptPath = apiUrl('/birkly-client.js');
        return '<script src="' + scriptPath + '" data-tracking-id="' + domainName + '"></script>';
    }

    function renderDomainTrackingScript(domainName, enabled) {
        if (!enabled) {
            return '';
        }

        var trackingCode = buildDomainTrackingScript(domainName);
        var codeId = 'domain-script-' + domainName.replace(/[^a-zA-Z0-9_-]/g, '-');
        return '' +
            '<div class="domains-code-block domains-code-block--nested">' +
            '<pre class="domains-code-pre"><code id="' + escapeHtml(codeId) + '">' + escapeHtml(trackingCode) + '</code></pre>' +
            '<button type="button" class="btn btn-secondary btn-sm domains-copy-btn" data-copy-target="' + escapeHtml(codeId) + '" title="Copy code">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
            '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>' +
            '<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>' +
            '</svg>Copy</button></div>' +
            '<p class="domains-code-hint">Add this snippet before the <code>&lt;/body&gt;</code> tag on your site.</p>';
    }

    function attachDomainsCopyButtons(root) {
        var copyButtons = (root || document).querySelectorAll('.domains-copy-btn');
        for (var i = 0; i < copyButtons.length; i++) {
            (function(btn) {
                if (btn.dataset.copyBound === '1') {
                    return;
                }
                btn.dataset.copyBound = '1';
                btn.addEventListener('click', function() {
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

            showMessage(enabled ? 'Analytics enabled for ' + domainName : 'Analytics disabled for ' + domainName, 'success');
            loadDomains();
        } catch (error) {
            if (toggleInput) {
                toggleInput.checked = !enabled;
            }
            showMessage('Error: ' + error.message, 'error');
        } finally {
            if (toggleInput) {
                toggleInput.disabled = false;
            }
        }
    }

    function renderDomains(domains) {
        var listContainer = document.getElementById('domains-list');
        if (!listContainer) {
            console.error('domains-list container not found in renderDomains');
            return;
        }

        attachDomainsCopyButtons(document.getElementById('domains'));

        if (!domains.length) {
            listContainer.innerHTML = '<div class="empty-state"><p>No domains registered yet. Click "Register domain" to add one.</p></div>';
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
            var statusClass = domain.status === 'active' ? 'success' : 'secondary';
            var statusText = domain.status || 'active';
            var permClass = permissionLevel === 'read_write' ? 'primary' : 'info';
            var permText = permissionLevel === 'read_write' ? 'Read & Write' : 'Read Only';
            var registeredDate = domain.registered_at ? new Date(domain.registered_at).toLocaleDateString() : 'N/A';
            var readLimit = rateLimits.read && rateLimits.read.limit ? rateLimits.read.limit : 100;
            var writeLimit = rateLimits.write && rateLimits.write.limit ? rateLimits.write.limit : 10;
            var readColsHtml = readCollections.length > 0 ? '<div class="detail-row"><strong>Read collections:</strong> <span>' + escapeHtml(readCollections.join(', ')) + '</span></div>' : '';
            var writeColsHtml = writeCollections.length > 0 ? '<div class="detail-row"><strong>Write collections:</strong> <span>' + escapeHtml(writeCollections.join(', ')) + '</span></div>' : '';
            var trackingScriptHtml = renderDomainTrackingScript(domainName, analyticsEnabled);

            return '' +
                '<article class="domain-card">' +
                '<div class="domain-header">' +
                '<div class="domain-info">' +
                '<h4 class="domain-name">' + domainEscaped + '</h4>' +
                '<div class="domain-badges">' +
                '<span class="badge badge-' + statusClass + '">' + escapeHtml(statusText) + '</span>' +
                '<span class="badge badge-' + permClass + '">' + permText + '</span>' +
                (analyticsEnabled ? '<span class="badge badge-success">Analytics on</span>' : '') +
                '</div>' +
                '</div>' +
                '<div class="domain-actions">' +
                '<button type="button" class="btn btn-small btn-secondary" data-domain="' + domainEscaped + '" data-action="edit">Edit</button>' +
                '<button type="button" class="btn btn-small btn-danger" data-domain="' + domainEscaped + '" data-action="delete">Delete</button>' +
                '</div>' +
                '</div>' +
                '<div class="domain-details">' +
                '<div class="detail-row"><strong>Email:</strong> <span>' + emailEscaped + '</span></div>' +
                '<div class="detail-row"><strong>Registered:</strong> <span>' + registeredDate + '</span></div>' +
                '<div class="detail-row"><strong>Rate limits:</strong> <span>Read ' + readLimit + '/min · Write ' + writeLimit + '/hour</span></div>' +
                readColsHtml +
                writeColsHtml +
                '</div>' +
                '<div class="domain-analytics-panel">' +
                '<div class="preference-item domain-analytics-toggle-row">' +
                '<div class="preference-label">' +
                '<div class="preference-title">Website analytics</div>' +
                '<small class="preference-desc">Self-hosted visitor tracking for this domain.</small>' +
                '</div>' +
                '<label class="toggle-switch" aria-label="Toggle analytics for ' + domainEscaped + '">' +
                '<input type="checkbox" class="domain-analytics-toggle" data-domain="' + domainEscaped + '"' + (analyticsEnabled ? ' checked' : '') + '>' +
                '<span class="toggle-slider"></span>' +
                '</label>' +
                '</div>' +
                (trackingScriptHtml ? '<div class="domain-tracking-script">' + trackingScriptHtml + '</div>' : '<div class="domain-tracking-script" hidden></div>') +
                '</div>' +
                '</article>';
        }).join('');

        attachDomainsCopyButtons(listContainer);

        var domainActions = listContainer.querySelectorAll('[data-action="edit"], [data-action="delete"]');
        for (var i = 0; i < domainActions.length; i++) {
            (function(btn) {
                btn.addEventListener('click', function(e) {
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
    }
    
        
    // Expose functions globally
    window.loadDomains = loadDomains;
    window.renderDomains = renderDomains;
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
            registerBtn.addEventListener('click', function(e) {
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
        showMessage('Error: Form not found', 'error');
        return;
    }
    
    const formData = new FormData(form);
    
    // Get and validate required fields
    const domain = (formData.get('domain') || '').trim();
    const customerEmail = (formData.get('customer_email') || '').trim();
    
    if (!domain || !customerEmail) {
        showMessage('Error: Domain and customer email are required', 'error');
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
            showMessage('Error: Invalid response from server. Check console for details.', 'error');
            return;
        }
        
        
        if (result.status === 'ok') {
            showMessage('Domain registered successfully!', 'success');
            closeAddDomainModal();
            loadDomains();
        } else {
            showMessage('Error: ' + (result.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error registering domain:', error);
        showMessage('Error registering domain: ' + error.message, 'error');
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
        showMessage('Error loading domain details: ' + error.message, 'error');
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
            showMessage('Error: Invalid response from server. Check console for details.', 'error');
            return;
        }
        
        
        if (result.status === 'ok') {
            showMessage('Domain updated successfully!', 'success');
            closeEditDomainModal();
            loadDomains();
        } else {
            showMessage('Error: ' + (result.message || 'Unknown error'), 'error');
        }
    } catch (error) {
        console.error('Error updating domain:', error);
        showMessage('Error updating domain: ' + error.message, 'error');
    }
}

window.deleteDomain = async function(domainName) {
    if (!confirm('Are you sure you want to delete domain "' + domainName + '"? This will revoke all access.')) {
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
            showMessage('Domain deleted successfully!', 'success');
            loadDomains();
        } else {
            showMessage('Error: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error deleting domain:', error);
        showMessage('Error deleting domain: ' + error.message, 'error');
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
            refreshBtn.addEventListener('click', function() {
                currentPage = 0;
                loadLogs(true);
            });
        }
        
        const loadMoreBtn = document.getElementById('load-more');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function() {
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
            tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding: 2rem;"><div class="spinner">Loading...</div></td></tr>';
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
                tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding: 2rem;">Error loading logs: ' + error.message + '</td></tr>';
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
            tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding: 2rem;">No log entries found</td></tr>';
            const loadMoreBtn = document.getElementById('load-more');
            if (loadMoreBtn) {
                loadMoreBtn.style.display = 'none';
            }
            return;
        }
        
        let html = '';
        logsToShow.forEach(log => {
            const timestamp = new Date(log.timestamp).toLocaleString();
            const category = log.category || getEventCategory(log.event);
            const categoryBadge = category === 'content' ? 'badge-info' : category === 'users' ? 'badge-success' : category === 'system' ? 'badge-warning' : 'badge-secondary';
            const formattedDetails = formatEventDetails(log);
            const fullDetails = log.details ? JSON.stringify(log.details, null, 2) : '';
            
            html += `
                <tr style="border-bottom: 1px solid var(--border-light);">
                    <td style="padding: 0.75rem; color: var(--text-secondary); font-size: 0.875rem;">${timestamp}</td>
                    <td style="padding: 0.75rem; color: var(--text-primary);">${escapeHtml(log.user || 'Unknown')}</td>
                    <td style="padding: 0.75rem;"><span class="badge ${categoryBadge}" style="padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">${escapeHtml(log.event || 'Unknown')}</span></td>
                    <td style="padding: 0.75rem;"><span class="badge badge-secondary" style="padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.75rem;">${escapeHtml(category)}</span></td>
                    <td style="padding: 0.75rem; color: var(--text-secondary); font-size: 0.875rem;">${escapeHtml(log.ip || 'Unknown')}</td>
                    <td style="padding: 0.75rem;">
                        ${formattedDetails ? `<details><summary style="cursor: pointer; color: #3b82f6; text-decoration: underline;">View Details</summary><div style="margin-top: 0.5rem; padding: 0.5rem; background: var(--bg-secondary); border-radius: 4px; font-size: 0.875rem; max-width: 500px; overflow-x: auto;"><pre style="margin: 0; white-space: pre-wrap; word-break: break-word;">${escapeHtml(formattedDetails)}</pre></div></details>` : '-'}
                    </td>
                </tr>
            `;
        });
        
        tbody.innerHTML = html;
        
        const loadMoreBtn = document.getElementById('load-more');
        if (loadMoreBtn) {
            loadMoreBtn.style.display = (endIndex < filteredLogs.length) ? 'block' : 'none';
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
        } else {
            container.innerHTML = `<p class="error">Error loading denylist: ${result.message}</p>`;
        }
    } catch (error) {
        container.innerHTML = `<p class="error">Error: ${error.message}</p>`;
    }
}

function renderDenylistItem(resource, type) {
    const label = type === 'collection' ? 'collection' : (type === 'space' ? 'space' : 'pattern');
    return `
        <div class="denylist-item">
            <code>${escapeHtml(resource)}</code>
            <span class="denylist-type-badge">${label}</span>
            <button type="button" class="btn btn-sm btn-danger" data-denylist-resource="${escapeHtml(resource)}" onclick="removeDenylistResource(this.dataset.denylistResource)">
                Remove
            </button>
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
        html += '<h3 class="settings-subheading">Protected collections</h3><div class="denylist-list">';
        denyCollections.forEach(resource => {
            html += renderDenylistItem(resource, 'collection');
        });
        html += '</div>';
    }

    if (customResources.length > 0) {
        html += '<h3 class="settings-subheading" style="margin-top: 1rem;">Custom patterns</h3><div class="denylist-list">';
        customResources.forEach(resource => {
            html += renderDenylistItem(resource, 'pattern');
        });
        html += '</div>';
    }

    if ((denySpaces || []).length > 0) {
        html += '<h3 class="settings-subheading" style="margin-top: 1rem;">Protected spaces</h3><div class="denylist-list">';
        denySpaces.forEach(resource => {
            html += renderDenylistItem(resource, 'space');
        });
        html += '</div>';
    }

    if (!html) {
        container.innerHTML = '<p class="settings-help">No protected resources. All collections are accessible to plugins (subject to capability checks).</p>';
        return;
    }

    container.innerHTML = html;
}

async function addDenylistCollection() {
    const select = document.getElementById('denylist-collection-select');
    const slug = select?.value || '';
    if (!slug) {
        showMessage('Select a collection first', 'error');
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
            showMessage('Collection added to denylist', 'success');
            loadDenylist();
        } else {
            showMessage('Error: ' + result.message, 'error');
        }
    } catch (error) {
        showMessage('Error: ' + error.message, 'error');
    }
}

async function addDenylistResource() {
    const resource = prompt('Enter resource identifier (collection slug, entry ID, or pattern like "payroll/*"):');
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
            showMessage('Resource added to denylist', 'success');
            loadDenylist();
        } else {
            showMessage('Error: ' + result.message, 'error');
        }
    } catch (error) {
        showMessage('Error: ' + error.message, 'error');
    }
}

async function removeDenylistResource(resource) {
    if (!confirm(`Remove "${resource}" from the denylist?`)) return;
    
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
            showMessage('Resource removed from denylist', 'success');
            loadDenylist();
        } else {
            showMessage('Error: ' + result.message, 'error');
        }
    } catch (error) {
        showMessage('Error: ' + error.message, 'error');
    }
}




// Storage Settings
document.getElementById('storage-backend')?.addEventListener('change', function() {
    const dbConfig = document.getElementById('database-config');
    if (dbConfig) {
        dbConfig.style.display = ['mysql', 'postgresql'].includes(this.value) ? 'block' : 'none';
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
    resultEl.textContent = 'Testing...';
    resultEl.style.color = 'var(--text-secondary)';
    
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
            resultEl.style.color = 'var(--success-600)';
        } else {
            resultEl.textContent = '✗ ' + (data.message || 'Connection failed');
            resultEl.style.color = 'var(--error-600)';
        }
    })
    .catch(error => {
        resultEl.textContent = '✗ Error: ' + error.message;
        resultEl.style.color = 'var(--error-600)';
    });
}

function migrateStorage() {
    if (!confirm('This will migrate all data to the selected storage backend. Make sure you have a backup! Continue?')) {
        return;
    }
    
    const backend = document.getElementById('storage-backend')?.value;
    if (!backend || backend === 'flatfile') {
        showMessage('Please select a database backend first', 'error');
        return;
    }
    
    if (!confirm('Migration may take some time. Do not close this window. Continue?')) {
        return;
    }
    
    fetch(apiUrl('/api/settings.php?action=migrate_storage'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            backend: backend,
            _csrf: getCsrfToken()
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'ok') {
            showMessage('Migration completed successfully!', 'success');
        } else {
            showMessage('Migration failed: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        showMessage('Error: ' + error.message, 'error');
    });
}

// Load denylist when security tab is shown
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('denylist-add-collection-btn')?.addEventListener('click', addDenylistCollection);

    const securityTab = document.querySelector('[data-tab="security"]');
    if (securityTab) {
        securityTab.addEventListener('click', function() {
            setTimeout(loadDenylist, 100); // Small delay to ensure tab is visible
        });
    }
    
    // Also load if we're already on security tab
    if (window.location.hash === '#security' || document.querySelector('.tab-pane#security.active')) {
        loadDenylist();
    }
});
// Global save bar
function handleGlobalSave() {
    const generalForm = document.getElementById('general-form');
    const securityForm = document.getElementById('security-form');
    const storageForm = document.getElementById('storage-form');
    const monitoringForm = document.getElementById('monitoring-form');
    if (!generalForm && !securityForm && !storageForm && !monitoringForm) return;

    collectAndSaveSettings(generalForm, securityForm, storageForm, monitoringForm);
}

async function collectAndSaveSettings(generalForm, securityForm, storageForm, monitoringForm) {
    const settings = {};

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
                    showMessage('Error uploading logo: ' + (uploadResult.message || 'Unknown error'), 'error');
                    return;
                }
            } catch (error) {
                showMessage('Error uploading logo: ' + error.message, 'error');
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
            showMessage('Settings saved successfully!', 'success');
            setTimeout(function () { location.reload(); }, 1000);
        } else {
            showMessage('Error: ' + result.message, 'error');
        }
    } catch (error) {
        showMessage('Error saving settings: ' + error.message, 'error');
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
                            preview.style.display = 'block';
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        const removeLogoBtn = document.getElementById('remove-logo-btn');
        if (removeLogoBtn) {
            removeLogoBtn.addEventListener('click', async function () {
                if (confirm('Are you sure you want to remove the logo? The default Birkly icon will be used.')) {
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
                            showMessage('Logo removed successfully!', 'success');
                            setTimeout(function () { location.reload(); }, 1000);
                        } else {
                            showMessage('Error: ' + result.message, 'error');
                        }
                    } catch (error) {
                        showMessage('Error removing logo: ' + error.message, 'error');
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

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    confirmBtn.addEventListener('click', async function () {
        if (confirmationInput.value.trim().toUpperCase() !== 'REBUILD') {
            return;
        }

        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Rebuilding…';

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
                showMessage(result.message || 'System rebuilt successfully', 'success');
                window.location.href = apiUrl(result.redirect || '/admin/index.php?page=onboarding');
                return;
            }

            showMessage('Error: ' + (result.message || 'Failed to rebuild system'), 'error');
        } catch (error) {
            showMessage('Error: ' + error.message, 'error');
        } finally {
            confirmBtn.textContent = 'Rebuild system';
            updateConfirmState();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGlobalSave);
} else {
    initGlobalSave();
}
