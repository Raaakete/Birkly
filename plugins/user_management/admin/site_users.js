// User Management Admin JavaScript

function apiUrl(path) {
    return (window.Birkly && typeof Birkly.url === 'function') ? Birkly.url(path) : path;
}

let currentPage = 1;
let currentFilters = {};
let searchTimeout = null;
let selectedUserIds = new Set();
let bulkBarInitialized = false;
let lastUsersTotal = 0;
let selectionScope = 'page'; // 'page' | 'all'
let selectionMatchedCount = 0;
let selectionForceConfirm = '';

function initUserManagement() {
    loadUsers();
    loadStats();
    initBulkBar();
    
    // Set up form event listener
    const form = document.getElementById('user-form');
    if (form) {
        // Form submission handled by saveUser()
    }
}

function initBulkBar() {
    if (bulkBarInitialized) return;
    bulkBarInitialized = true;

    const actionSelect = document.getElementById('users-bulk-action');
    if (!actionSelect) return;

    // Ensure tier options are available for bulk tier change
    loadUserTierOptions();

    actionSelect.addEventListener('change', () => {
        const op = actionSelect.value;
        const statusSel = document.getElementById('users-bulk-status');
        const tierSel = document.getElementById('users-bulk-tier');
        if (statusSel) statusSel.style.display = (op === 'set_status') ? '' : 'none';
        if (tierSel) tierSel.style.display = (op === 'set_tier') ? '' : 'none';
    });
}

function loadUsers(page = 1, filters = {}) {
    currentPage = page;
    currentFilters = filters;
    // Reset selection when list changes
    selectedUserIds = new Set();
    selectionScope = 'page';
    selectionMatchedCount = 0;
    selectionForceConfirm = '';
    updateBulkSelectionUI();
    
    const params = new URLSearchParams({
        action: 'list',
        limit: 50,
        offset: (page - 1) * 50,
        ...filters
    });
    
    fetch(apiUrl(`/api/index.php?route=user_admin&${params}`))
        .then(async res => {
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                if (res.status === 404) {
                    console.error('loadUsers 404 - Full response:', text);
                    try {
                        const errorData = JSON.parse(text);
                        if (errorData.debug) {
                            console.error('Debug info:', errorData.debug);
                            console.error('Available routes:', errorData.debug.available_routes);
                            console.error('User management active:', errorData.debug.user_management_active);
                        }
                    } catch (e2) {
                        // Not JSON, log raw response
                        console.error('404 response (not JSON):', text.substring(0, 500));
                    }
                    throw new Error('API route not found. Please check if the user management plugin is active.');
                }
                throw new Error(`Invalid JSON response: ${text.substring(0, 200)}`);
            }
            
            if (!res.ok) {
                if (data.debug) {
                    console.error('loadUsers error debug:', data.debug);
                }
                throw new Error(data.error || data.message || `HTTP ${res.status}: ${res.statusText}`);
            }
            return data;
        })
        .then(data => {
            if (data.users) {
                renderUsersTable(data.users, data.total || 0);
                renderPagination(data.total || 0, 50, page);
                // Keep stats in sync
                loadStats();
                lastUsersTotal = data.total || 0;
            } else if (data.error) {
                showError('Failed to load users: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error loading users:', error);
            // Don't break the page - show error but keep tabs visible
            const container = document.getElementById('users-table-container');
            if (container) {
                // Only update if container exists and is empty or has error
                const currentContent = container.innerHTML.trim();
                if (!currentContent || currentContent.includes('alert')) {
                    container.innerHTML = `
                        <div class="alert alert-warning">
                            <strong>Error loading users:</strong> ${escapeHtml(error.message)}
                            <br><small>Please refresh the page to try again.</small>
                        </div>
                    `;
                }
            }
            // Always show error message
            showError('Error loading users: ' + error.message);
        });
}

function renderUsersTable(users, total) {
    const container = document.getElementById('users-table-container');
    if (!container) {
        console.error('Users table container not found');
        showError('Users table container not found. Please refresh the page.');
        return;
    }
    
    if (!users || users.length === 0) {
        container.innerHTML = '<div class="text-center py-5 text-muted">No users found</div>';
        return;
    }
    
    let html = `
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 36px;">
                            <input type="checkbox" id="users-select-all-inline" onchange="toggleSelectAllUsers(this.checked)">
                        </th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Verified</th>
                        <th>2FA</th>
                        <th>Tier</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    users.forEach(user => {
        const isDeleted = (user.deleted ?? false) === true;
        const statusBadge = isDeleted
            ? '<span class="badge badge-danger" title="Soft-deleted">Deleted</span>'
            : getStatusBadge(user.status || 'active');
        const roleBadge = getRoleBadge(user.role || 'member');
        const tierBadge = getTierBadge(user.tier || 'free');
        const createdDate = user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A';
        
        // Check verification status from user data
        const emailVerified = user.email_verified || user.verified || false;
        const verifiedBadge = emailVerified 
            ? '<span class="badge badge-success" title="Email verified">✓</span>'
            : '<span class="badge badge-warning" title="Email not verified">⚠</span>';
        
        // 2FA status - will be loaded asynchronously if available
        const twofaBadge = user._2fa_enabled 
            ? '<span class="badge badge-success" title="2FA enabled">✓</span>'
            : '<span class="badge badge-secondary" title="2FA disabled">—</span>';
        
        // Escape user ID for use in data attribute (safe for HTML)
        const safeUserId = escapeHtml(user.id);
        
        const safeId = escapeHtml(user.id);
        const safeIdJs = String(user.id).replace(/\\/g, '\\\\').replace(/'/g, "\\'");

        const actionsHtml = isDeleted
            ? `
                <button class="btn btn-sm btn-outline-success" onclick="restoreUser('${safeIdJs}')" title="Restore">
                    Restore
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="purgeUser('${safeIdJs}')" title="Permanently delete">
                    Purge
                </button>
              `
            : `
                <button class="btn btn-sm btn-outline-primary" onclick="editUser('${safeIdJs}')" title="Edit">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser('${safeIdJs}')" title="Delete">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3,6 5,6 21,6"></polyline>
                        <path d="M19,6v14a2,2 0 0,1 -2,2H7a2,2 0 0,1 -2,-2V6m3,0V4a2,2 0 0,1 2,-2h4a2,2 0 0,1 2,2v2"></path>
                    </svg>
                </button>
              `;

        html += `
            <tr>
                <td>
                    <input type="checkbox" class="user-row-select" data-user-id="${safeId}" onchange="toggleSelectUser('${safeIdJs}', this.checked)">
                </td>
                <td>${escapeHtml(user.name || user.username || user.email || 'N/A')}</td>
                <td>${escapeHtml(user.email || 'N/A')}</td>
                <td>${roleBadge}</td>
                <td>${statusBadge}</td>
                <td>${verifiedBadge}</td>
                <td data-user-id="${safeUserId}" class="twofa-status-cell">${twofaBadge}</td>
                <td>${tierBadge}</td>
                <td>${createdDate}</td>
                <td>
                    <div class="btn-group">
                        ${actionsHtml}
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
    // Sync selection state with newly rendered rows
    syncRenderedSelectionState();
    
    // Load 2FA status for all users asynchronously (non-blocking)
    users.forEach(user => {
        // Only fetch if user has an ID
        if (!user.id) {
            return;
        }
        
        // Use POST instead of GET to ensure action is passed correctly
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fetch(apiUrl('/api/index.php?route=user_admin'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'get_user_status',
                user_id: user.id,
                csrf_token: csrfToken
            })
        })
            .then(async res => {
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    // If it's a 404 or other error, just return null - don't break the page
                    if (res.status === 404 || res.status >= 400) {
                        return null;
                    }
                    // For other errors, also return null to prevent breaking
                    return null;
                }
                
                if (!res.ok) {
                    // Don't throw error - just silently fail (status already shown)
                    return null;
                }
                return data;
            })
            .then(data => {
                // Find the status cell using data attribute - more robust than class selector
                // Use querySelectorAll and filter to avoid selector issues with special characters
                const allCells = document.querySelectorAll('.twofa-status-cell');
                let statusCell = null;
                for (const cell of allCells) {
                    if (cell.getAttribute('data-user-id') === user.id) {
                        statusCell = cell;
                        break;
                    }
                }
                
                if (!data) {
                    // Set default status on error - don't break the page
                    if (statusCell) {
                        statusCell.innerHTML = '<span class="badge badge-secondary" title="2FA status unknown">—</span>';
                    }
                    return;
                }
                
                if (statusCell && data.success) {
                    if (data['2fa_enabled']) {
                        statusCell.innerHTML = '<span class="badge badge-success" title="2FA enabled">✓</span>';
                    } else {
                        statusCell.innerHTML = '<span class="badge badge-secondary" title="2FA disabled">—</span>';
                    }
                }
            })
            .catch(() => {
                // Silently fail - 2FA status is not critical, don't break the page
                const allCells = document.querySelectorAll('.twofa-status-cell');
                let statusCell = null;
                for (const cell of allCells) {
                    if (cell.getAttribute('data-user-id') === user.id) {
                        statusCell = cell;
                        break;
                    }
                }
                if (statusCell) {
                    statusCell.innerHTML = '<span class="badge badge-secondary" title="2FA status unknown">—</span>';
                }
            });
    });
}

function updateBulkSelectionUI() {
    const countEl = document.getElementById('users-selected-count');
    const scopeEl = document.getElementById('users-selection-scope');
    if (countEl) {
        if (selectionScope === 'all') {
            countEl.textContent = `All matching selected (${selectionMatchedCount || lastUsersTotal})`;
        } else {
            countEl.textContent = `${selectedUserIds.size} selected`;
        }
    }
    if (scopeEl) {
        if (selectionScope === 'all') {
            scopeEl.textContent = 'All matching';
            scopeEl.className = 'badge badge-warning';
        } else {
            scopeEl.textContent = 'Page';
            scopeEl.className = 'badge badge-secondary';
        }
    }
    const selectAllTop = document.getElementById('users-select-all');
    const selectAllInline = document.getElementById('users-select-all-inline');
    if (selectAllTop) selectAllTop.checked = false;
    if (selectAllInline) selectAllInline.checked = false;
}

function syncRenderedSelectionState() {
    document.querySelectorAll('.user-row-select').forEach(cb => {
        const id = cb.getAttribute('data-user-id');
        cb.checked = selectedUserIds.has(id);
    });
    const countEl = document.getElementById('users-selected-count');
    if (countEl) countEl.textContent = `${selectedUserIds.size} selected`;
}

function toggleSelectUser(userId, checked) {
    // Any manual row selection implies "page" scope
    selectionScope = 'page';
    if (checked) {
        selectedUserIds.add(userId);
    } else {
        selectedUserIds.delete(userId);
    }
    const countEl = document.getElementById('users-selected-count');
    if (countEl) countEl.textContent = `${selectedUserIds.size} selected`;
}

function toggleSelectAllUsers(checked) {
    selectionScope = 'page';
    const rowCheckboxes = document.querySelectorAll('.user-row-select');
    rowCheckboxes.forEach(cb => {
        const id = cb.getAttribute('data-user-id');
        cb.checked = checked;
        if (checked) selectedUserIds.add(id);
        else selectedUserIds.delete(id);
    });

    const countEl = document.getElementById('users-selected-count');
    if (countEl) countEl.textContent = `${selectedUserIds.size} selected`;

    const selectAllTop = document.getElementById('users-select-all');
    const selectAllInline = document.getElementById('users-select-all-inline');
    if (selectAllTop) selectAllTop.checked = checked;
    if (selectAllInline) selectAllInline.checked = checked;
}

function selectAllMatchingUsers() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch(apiUrl('/api/index.php?route=user_admin'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'bulk_query_preview',
            filters: currentFilters,
            search: currentFilters.search || '',
            csrf_token: csrfToken
        })
    })
        .then(res => res.json().then(data => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
            if (!ok || !data?.success) throw new Error(data.error || 'Preview failed');
            const matched = data.matched ?? 0;
            const limit = data.limit ?? 1000;
            const forceLimit = data.force_limit ?? 5000;

            if (matched <= 0) {
                showError('No users match the current filters/search');
                return;
            }

            // If above safety cap, require explicit typed confirmation
            selectionForceConfirm = '';
            if (matched > limit) {
                const entered = prompt(`This matches ${matched} users (limit ${limit}). To force up to ${forceLimit}, type BULK_OK to continue:`, '');
                if (entered !== 'BULK_OK') {
                    showError('Cancelled');
                    return;
                }
                selectionForceConfirm = 'BULK_OK';
            } else {
                if (!confirm(`Select ALL ${matched} user(s) matching current filters/search (across pages)?`)) {
                    return;
                }
            }

            selectionScope = 'all';
            selectionMatchedCount = matched;
            selectedUserIds = new Set(); // not used in this mode
            updateBulkSelectionUI();
        })
        .catch(err => {
            console.error('Select all matching preview error:', err);
            showError('Select all matching failed: ' + err.message);
        });
}

function applyBulkUserAction() {
    const op = document.getElementById('users-bulk-action')?.value || '';
    if (!op) {
        showError('Please choose a bulk action');
        return;
    }
    if (selectionScope === 'page' && selectedUserIds.size === 0) {
        showError('No users selected');
        return;
    }

    const opLabel =
        op === 'delete' ? 'delete (soft)' :
        op === 'restore' ? 'restore' :
        op === 'purge' ? 'purge (hard delete)' :
        op === 'set_status' ? 'set status' :
        op === 'set_tier' ? 'set tier' :
        op;

    let value = null;
    if (op === 'set_status') {
        value = document.getElementById('users-bulk-status')?.value || '';
        if (!value) {
            showError('Please choose a status');
            return;
        }
    } else if (op === 'set_tier') {
        value = document.getElementById('users-bulk-tier')?.value || '';
        if (!value) {
            showError('Please choose a tier');
            return;
        }
    }

    const targetCount = selectionScope === 'all' ? lastUsersTotal : selectedUserIds.size;
    if (!confirm(`Apply "${opLabel}" to ${targetCount} user(s)?`)) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const payload = selectionScope === 'all'
        ? {
            action: 'bulk_query',
            op,
            value,
            filters: currentFilters,
            search: currentFilters.search || '',
            force: selectionForceConfirm === 'BULK_OK',
            confirm: selectionForceConfirm || '',
            csrf_token: csrfToken
        }
        : {
            action: 'bulk',
            op,
            ids: Array.from(selectedUserIds),
            value,
            csrf_token: csrfToken
        };

    fetch(apiUrl('/api/index.php?route=user_admin'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(res => res.json().then(data => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
            if (!ok) throw new Error(data.error || data.message || 'Bulk action failed');
            const s = data.summary || {};
            const label = selectionScope === 'all' ? `Bulk ${op} (all matching)` : `Bulk ${op}`;
            if ((s.failed || 0) > 0) {
                showError(`${label} completed: ${s.succeeded || 0} ok, ${s.failed || 0} failed`);
                console.error('Bulk failures:', s.failures || []);
            } else {
                showSuccess(`${label} completed: ${s.succeeded || 0} updated`);
            }
            selectedUserIds = new Set();
            selectionScope = 'page';
            updateBulkSelectionUI();
            loadUsers(currentPage, currentFilters);
        })
        .catch(err => {
            console.error('Bulk action error:', err);
            showError('Bulk action error: ' + err.message);
        });
}

function renderPagination(total, perPage, currentPage) {
    const container = document.getElementById('users-pagination');
    const totalPages = Math.ceil(total / perPage);
    
    if (totalPages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<div class="pagination">';
    
    // Previous button
    if (currentPage > 1) {
        html += `<button class="btn btn-sm btn-outline-secondary" onclick="loadUsers(${currentPage - 1}, currentFilters)">Previous</button>`;
    }
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
            html += `<button class="btn btn-sm ${i === currentPage ? 'btn-primary' : 'btn-outline-secondary'}" onclick="loadUsers(${i}, currentFilters)">${i}</button>`;
        } else if (i === currentPage - 3 || i === currentPage + 3) {
            html += '<span class="px-2">...</span>';
        }
    }
    
    // Next button
    if (currentPage < totalPages) {
        html += `<button class="btn btn-sm btn-outline-secondary" onclick="loadUsers(${currentPage + 1}, currentFilters)">Next</button>`;
    }
    
    html += '</div>';
    container.innerHTML = html;
}

function loadStats() {
    const container = document.getElementById('users-stats');
    if (!container) return;

    fetch(apiUrl('/api/index.php?route=user_admin&action=stats'))
        .then(res => res.json())
        .then(data => {
            if (!data || (!data.success && !data.stats)) {
                return;
            }
            const stats = data.stats || {};

            const items = [
                { label: 'Total', value: stats.total ?? 0, variant: 'secondary' },
                { label: 'Active', value: stats.active ?? 0, variant: 'success' },
                { label: 'Pending', value: stats.pending ?? 0, variant: 'warning' },
                { label: 'Inactive', value: stats.inactive ?? 0, variant: 'secondary' },
                { label: 'Deleted', value: stats.deleted ?? 0, variant: 'danger' },
            ];

            container.innerHTML = items.map(i => `
                <span class="badge badge-${i.variant}" style="padding:.5rem .75rem;">
                    ${escapeHtml(i.label)}: <strong>${i.value}</strong>
                </span>
            `).join('');
        })
        .catch(() => {
            // Non-critical; ignore
        });
}

function openAddUserModal() {
    resetUserForm();
    document.getElementById('user-modal-title').textContent = 'Add New User';
    document.getElementById('user-password').required = true;
    document.getElementById('password-required').style.display = 'inline';
    document.getElementById('password-help').textContent = 'Password is required for new users';
    
    // Load default tier from settings
    loadDefaultTierForNewUser();

    // NOTE: This file previously had a `switchTab()` implementation that hid `.tab-content`,
    // which is also used by the main User Management page tabs. That caused the whole page
    // to go blank after opening the modal (until refresh). The user modal has no internal tabs,
    // so we don't need to switch anything here.
    document.getElementById('user-modal').style.display = 'block';
}

function closeUserModal() {
    document.getElementById('user-modal').style.display = 'none';
    resetUserForm();
}

function resetUserForm() {
    document.getElementById('user-form').reset();
    document.getElementById('user-id').value = '';
    document.getElementById('user-password').required = false;
    document.getElementById('password-required').style.display = 'none';
    document.getElementById('password-help').textContent = 'Leave blank to keep existing password when editing';
    
    // Load tiers and set default tier
    loadUserTierOptions();
}

function editUser(userId) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch(apiUrl('/api/index.php?route=user_admin'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'get',
            id: userId,
            csrf_token: csrfToken
        })
    })
        .then(async res => {
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                if (res.status === 404) {
                    console.error('editUser 404 - Route not found. Response:', text.substring(0, 200));
                    throw new Error('API route not found. Please check if the user management plugin is active.');
                }
                throw new Error(`Invalid JSON response: ${text.substring(0, 200)}`);
            }
            
            if (!res.ok) {
                if (data.debug) {
                    console.error('Available routes:', data.debug.available_routes);
                }
                throw new Error(data.error || data.message || `HTTP ${res.status}`);
            }
            return data;
        })
        .then(data => {
            if (data.user) {
                const user = data.user;
                
                document.getElementById('user-id').value = user.id;
                document.getElementById('user-name').value = user.name || user.username || '';
                document.getElementById('user-email').value = user.email || '';
                document.getElementById('user-status').value = user.status || 'active';
                
                // Load tiers and set user's tier
                loadUserTierOptions(() => {
                    document.getElementById('user-tier').value = user.tier || 'all';
                });
                document.getElementById('user-approved').checked = user.approved || false;
                document.getElementById('user-password').value = '';
                document.getElementById('user-password').required = false;
                document.getElementById('password-required').style.display = 'none';
                
                // Show reset password button only when editing
                document.getElementById('reset-password-btn').style.display = 'inline-block';
                
                // Load verification and 2FA status
                loadUserVerificationStatus(userId);
                loadUser2FAStatus(userId);
                
                document.getElementById('user-modal-title').textContent = 'Edit User';
                document.getElementById('user-modal').style.display = 'block';
            } else {
                alert('Failed to load user data');
            }
        })
        .catch(error => {
            alert('Error loading user data: ' + error.message);
        });
}

function loadUserVerificationStatus(userId) {
    // Get user data which includes email_verified status
    fetch(apiUrl(`/api/index.php?route=user_admin&action=get&id=${userId}`))
        .then(res => res.json())
        .then(data => {
            const statusDiv = document.getElementById('email-verification-status');
            const resendBtn = document.getElementById('resend-verification-btn');
            
            const verified = data.user?.email_verified || data.user?.verified || false;
            
            if (verified) {
                statusDiv.innerHTML = '<span class="badge badge-success">✓ Verified</span>';
                resendBtn.style.display = 'none';
            } else {
                statusDiv.innerHTML = '<span class="badge badge-warning">⚠ Not Verified</span>';
                resendBtn.style.display = 'inline-block';
                resendBtn.onclick = () => resendVerificationEmail(userId);
            }
        })
        .catch(error => {
            document.getElementById('email-verification-status').innerHTML = '<span class="badge badge-secondary">Unknown</span>';
        });
}

function loadUser2FAStatus(userId) {
    // Get user status (verification and 2FA) via admin API
    fetch(apiUrl(`/api/index.php?route=user_admin&action=get_user_status&user_id=${userId}`))
        .then(res => res.json())
        .then(data => {
            const statusDiv = document.getElementById('2fa-status');
            const manageBtn = document.getElementById('manage-2fa-btn');
            
            if (data.success) {
                if (data['2fa_enabled']) {
                    statusDiv.innerHTML = '<span class="badge badge-success">✓ Enabled</span>';
                } else {
                    statusDiv.innerHTML = '<span class="badge badge-secondary">Disabled</span>';
                }
            } else {
                statusDiv.innerHTML = '<span class="badge badge-secondary">Unknown</span>';
            }
            manageBtn.style.display = 'inline-block';
            manageBtn.onclick = () => manageUser2FA(userId);
        })
        .catch(error => {
            const statusDiv = document.getElementById('2fa-status');
            statusDiv.innerHTML = '<span class="badge badge-secondary">Unknown</span>';
            const manageBtn = document.getElementById('manage-2fa-btn');
            manageBtn.style.display = 'inline-block';
            manageBtn.onclick = () => manageUser2FA(userId);
        });
}

function resendVerificationEmail(userId) {
    const targetUserId = userId || document.getElementById('user-id').value;
    if (!targetUserId) {
        alert('User ID required');
        return;
    }
    
    const btn = event?.target || document.getElementById('resend-verification-btn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Sending...';
    
    fetch(apiUrl('/api/index.php?route=user_verification&action=resend'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            user_id: targetUserId,
            _csrf: document.querySelector('meta[name="csrf-token"]')?.content || ''
        })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Verification email sent successfully');
                loadUserVerificationStatus(targetUserId);
            } else {
                alert(data.message || 'Failed to send verification email');
            }
        })
        .catch(error => {
            alert('Error sending verification email: ' + error.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = originalText;
        });
}

function resetUserPassword(userId) {
    const targetUserId = userId || document.getElementById('user-id').value;
    if (!targetUserId) {
        alert('User ID required');
        return;
    }
    
    if (!confirm('Send password reset email to this user?')) {
        return;
    }
    
    const btn = event?.target || document.getElementById('reset-password-btn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Sending...';
    
    // Get user email first
    fetch(apiUrl(`/api/index.php?route=user_admin&action=get&id=${targetUserId}`))
        .then(res => res.json())
        .then(data => {
            if (!data.user || !data.user.email) {
                throw new Error('User email not found');
            }
            
            return fetch(apiUrl('/api/index.php?route=password_reset&action=request'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    email: data.user.email
                })
            });
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                alert('Password reset email sent successfully');
            } else {
                alert(data.message || 'Failed to send password reset email');
            }
        })
        .catch(error => {
            alert('Error sending password reset email: ' + error.message);
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = originalText;
        });
}

function manageUser2FA(userId) {
    const targetUserId = userId || document.getElementById('user-id').value;
    if (!targetUserId) {
        showError('User ID required');
        return;
    }
    
    // For now, just show a message - full 2FA management UI can be added later
    alert('2FA management for individual users will be available in a future update. Users can manage their own 2FA from their account settings.');
}

function saveUser() {
    const form = document.getElementById('user-form');
    const formData = new FormData(form);
    const userId = document.getElementById('user-id').value;
    const isEditing = userId && userId.trim() !== '';
    
    const userData = {
        name: (formData.get('name') || '').trim(),
        email: (formData.get('email') || '').trim(),
        status: formData.get('status'),
        tier: formData.get('tier') || 'all',
        approved: document.getElementById('user-approved')?.checked || false
    };
    
    // Add password if provided
    const password = formData.get('password');
    if (password) {
        userData.password = password;
    }
    
    // Validate
    if (!userData.name || !userData.email) {
        showError('Name and email are required');
        return;
    }
    
    if (!isEditing && !password) {
        showError('Password is required for new users');
        return;
    }
    
    const action = isEditing ? 'update' : 'create';
    const url = apiUrl('/api/index.php?route=user_admin');
    
    // Get CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: action,
            id: userId || undefined,
            ...userData,
            csrf_token: csrfToken
        })
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(data => {
                throw new Error(data.error || `HTTP ${res.status}: ${res.statusText}`);
            });
        }
        return res.json();
    })
        .then(data => {
            if (data.success || data.user) {
                showSuccess(isEditing ? 'User updated successfully' : 'User created successfully');
                closeUserModal();
                // Reload users with error handling to prevent blank page
                try {
                    loadUsers(currentPage, currentFilters);
                } catch (e) {
                    console.error('Error reloading users after save:', e);
                    showError('User saved but failed to reload list. Please refresh the page.');
                }
            } else {
                showError('Failed to save user: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error saving user:', error);
            // Check if it's a 404 - route not found
            if (error.message.includes('404') || error.message.includes('Not Found')) {
                showError('API route not found. The user management plugin may not be active. Please refresh the page.');
            } else {
                showError('Failed to save user: ' + error.message);
            }
        });
}

function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        return;
    }
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    fetch(apiUrl('/api/index.php?route=user_admin'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'delete',
            id: userId,
            csrf_token: csrfToken
        })
    })
    .then(async res => {
        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            // If it's a 404, provide more helpful error
            if (res.status === 404) {
                throw new Error('API route not found (404). The user management plugin may not be active.');
            }
            throw new Error(`Invalid JSON response: ${text.substring(0, 200)}`);
        }
        
        if (!res.ok) {
            // Provide more context for 404 errors
            if (res.status === 404) {
                throw new Error('API route not found (404). The user management plugin may not be active.');
            }
            throw new Error(data.error || data.message || `HTTP ${res.status}: ${res.statusText}`);
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            showSuccess('User deleted successfully');
            loadUsers(currentPage, currentFilters);
        } else {
            showError('Failed to delete user: ' + (data.error || data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error deleting user:', error);
        // Check if it's a 404 - route not found
        if (error.message.includes('404') || error.message.includes('Not Found')) {
            showError('API route not found. The user management plugin may not be active. Please refresh the page.');
        } else {
            showError('Error deleting user: ' + error.message);
        }
        // Don't reload users if delete failed
    });
}

function purgeDeletedUsers() {
    if (!confirm('Permanently delete ALL soft-deleted users? This cannot be undone.')) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    fetch(apiUrl('/api/index.php?route=user_admin'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'purge_deleted',
            csrf_token: csrfToken
        })
    })
        .then(async res => {
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                throw new Error(`Invalid JSON response: ${text.substring(0, 200)}`);
            }

            if (!res.ok) {
                throw new Error(data.error || data.message || `HTTP ${res.status}: ${res.statusText}`);
            }
            return data;
        })
        .then(data => {
            if (data.success) {
                showSuccess(`Purged ${data.purged || 0} deleted user(s)`);
                loadUsers(currentPage, currentFilters);
            } else {
                showError('Failed to purge deleted users: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Error purging deleted users:', err);
            showError('Error purging deleted users: ' + err.message);
        });
}

function restoreUser(userId) {
    if (!confirm('Restore this user?')) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    fetch(apiUrl('/api/index.php?route=user_admin'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'restore',
            id: userId,
            csrf_token: csrfToken
        })
    })
        .then(res => res.json().then(data => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
            if (!ok) throw new Error(data.error || data.message || 'Restore failed');
            showSuccess('User restored successfully');
            loadUsers(currentPage, currentFilters);
        })
        .catch(err => {
            console.error('Error restoring user:', err);
            showError('Error restoring user: ' + err.message);
        });
}

function purgeUser(userId) {
    if (!confirm('Permanently delete this user? This cannot be undone.')) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    fetch(apiUrl('/api/index.php?route=user_admin'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'purge_user',
            id: userId,
            csrf_token: csrfToken
        })
    })
        .then(res => res.json().then(data => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
            if (!ok) throw new Error(data.error || data.message || 'Purge failed');
            showSuccess('User permanently deleted');
            loadUsers(currentPage, currentFilters);
        })
        .catch(err => {
            console.error('Error purging user:', err);
            showError('Error purging user: ' + err.message);
        });
}

function handleSearch(event) {
    if (event.key === 'Enter') {
        applyFilters();
    } else {
        // Debounce search
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 500);
    }
}

function applyFilters() {
    const filters = {};
    
    const search = document.getElementById('search-users').value.trim();
    if (search) {
        // Search will be handled server-side via query parameter
        filters.search = search;
    }
    
    const role = document.getElementById('filter-role').value;
    if (role) {
        filters.role = role;
    }
    
    const status = document.getElementById('filter-status').value;
    if (status) {
        filters.status = status;
    }

    const includeDeleted = document.getElementById('filter-include-deleted')?.checked || false;
    if (includeDeleted) {
        filters.include_deleted = '1';
    }
    
    loadUsers(1, filters);
}

function clearFilters() {
    document.getElementById('search-users').value = '';
    document.getElementById('filter-role').value = '';
    document.getElementById('filter-status').value = '';
    const includeDeletedEl = document.getElementById('filter-include-deleted');
    if (includeDeletedEl) includeDeletedEl.checked = false;
    loadUsers(1, {});
}

function loadUserActivity(userId) {
    // Activity loading will be implemented
    const container = document.getElementById('user-activity-list');
    container.innerHTML = '<div class="text-center py-3"><p class="text-muted">Activity log will be loaded here</p></div>';
}

function exportUsers() {
    const params = new URLSearchParams({
        action: 'list',
        limit: 10000,
        ...currentFilters
    });
    
    window.open(apiUrl(`/api/index.php?route=user_admin&${params}&format=csv`), '_blank');
}

function getStatusBadge(status) {
    const badges = {
        'active': '<span class="badge badge-success">Active</span>',
        'inactive': '<span class="badge badge-secondary">Inactive</span>',
        'pending': '<span class="badge badge-warning">Pending</span>'
    };
    return badges[status] || '<span class="badge badge-secondary">' + status + '</span>';
}

function getRoleBadge(role) {
    const badges = {
        'vip': '<span class="badge badge-danger">VIP</span>',
        'premium': '<span class="badge badge-warning">Premium</span>',
        'subscriber': '<span class="badge badge-info">Subscriber</span>',
        'member': '<span class="badge badge-secondary">Member</span>'
    };
    return badges[role] || '<span class="badge badge-secondary">' + role + '</span>';
}

function getTierBadge(tier) {
    const badges = {
        'premium': '<span class="badge badge-warning">Premium</span>',
        'basic': '<span class="badge badge-info">Basic</span>',
        'free': '<span class="badge badge-secondary">Free</span>'
    };
    return badges[tier] || '<span class="badge badge-secondary">' + tier + '</span>';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Make functions available globally
window.initUserManagement = initUserManagement;
window.loadUsers = loadUsers;
window.openAddUserModal = openAddUserModal;
window.closeUserModal = closeUserModal;
window.saveUser = saveUser;
window.editUser = editUser;
window.deleteUser = deleteUser;
window.handleSearch = handleSearch;
window.applyFilters = applyFilters;
window.clearFilters = clearFilters;
window.exportUsers = exportUsers;

function showSuccess(message) {
    showMessage(message, 'success');
}

function showError(message) {
    showMessage(message, 'error');
}

function showMessage(message, type = 'info') {
    const messageDiv = document.createElement('div');
    messageDiv.className = `alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'}`;
    messageDiv.style.position = 'fixed';
    messageDiv.style.top = '20px';
    messageDiv.style.right = '20px';
    messageDiv.style.zIndex = '9999';
    messageDiv.style.minWidth = '300px';
    messageDiv.textContent = message;
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.parentNode.removeChild(messageDiv);
        }
    }, 5000);
}

// Load tier options for user form
function loadUserTierOptions(callback) {
    const select = document.getElementById('user-tier');
    if (!select) {
        if (callback) callback();
        return;
    }
    
    fetch(apiUrl('/api/index.php?route=user_tiers&action=list'))
        .then(res => res.json())
        .then(data => {
            // Clear existing options except "All"
            select.innerHTML = '<option value="all">All</option>';

            // Also populate bulk tier select if present
            const bulkTier = document.getElementById('users-bulk-tier');
            if (bulkTier) {
                bulkTier.innerHTML = '<option value="all">All</option>';
            }
            
            if (data.success && data.tiers) {
                data.tiers.forEach(tier => {
                    if (tier.id !== 'all') {
                        const option = document.createElement('option');
                        option.value = tier.id;
                        option.textContent = tier.name || tier.id;
                        select.appendChild(option);

                        if (bulkTier) {
                            const opt2 = document.createElement('option');
                            opt2.value = tier.id;
                            opt2.textContent = tier.name || tier.id;
                            bulkTier.appendChild(opt2);
                        }
                    }
                });
            }
            
            if (callback) callback();
        })
        .catch(error => {
            console.error('Error loading tiers:', error);
            if (callback) callback();
        });
}

// Load default tier from settings for new user
function loadDefaultTierForNewUser() {
    fetch(apiUrl('/api/index.php?route=user_settings&action=get'))
        .then(res => res.json())
        .then(data => {
            if (data.success && data.settings) {
                const defaultTier = data.settings.default_tier || 'all';
                const select = document.getElementById('user-tier');
                if (select) {
                    // Make sure tiers are loaded first
                    loadUserTierOptions(() => {
                        select.value = defaultTier;
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error loading default tier:', error);
            // Still load tiers even if settings fail
            loadUserTierOptions();
        });
}

// Expose functions globally
window.initUserManagement = initUserManagement;
window.loadUsers = loadUsers;
window.openAddUserModal = openAddUserModal;
window.closeUserModal = closeUserModal;
window.editUser = editUser;
window.deleteUser = deleteUser;
window.saveUser = saveUser;
window.purgeDeletedUsers = purgeDeletedUsers;
window.restoreUser = restoreUser;
window.purgeUser = purgeUser;
window.applyFilters = applyFilters;
window.clearFilters = clearFilters;
window.exportUsers = exportUsers;
window.handleSearch = handleSearch;
window.loadUserTierOptions = loadUserTierOptions;
window.loadDefaultTierForNewUser = loadDefaultTierForNewUser;
window.toggleSelectAllUsers = toggleSelectAllUsers;
window.toggleSelectUser = toggleSelectUser;
window.applyBulkUserAction = applyBulkUserAction;
window.selectAllMatchingUsers = selectAllMatchingUsers;