/**
 * Groups Management JavaScript
 */


function apiUrl(path) {
    return (window.Birkly && typeof Birkly.url === 'function') ? Birkly.url(path) : path;
}
// Use IIFE to avoid variable conflicts
(function() {
'use strict';

let currentGroups = [];
let currentUsers = [];
let groupFilters = {}; // Renamed to avoid conflict with users.js
let isEditing = false;
let currentGroupId = null;

function initGroupsManagement() {
    loadGroups();
    loadUsers(); // Load users for member selection
}

// Functions will be exposed at the end of the file

function loadGroups(filters = {}) {
    groupFilters = filters;
    
    const params = new URLSearchParams({
        action: 'list',
        ...filters
    });
    
    fetch(apiUrl(`/api/index.php?route=user_groups&${params}`))
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
            if (data.groups) {
                currentGroups = data.groups;
                renderGroupsTable(data.groups);
            } else if (data.error) {
                showError('Failed to load groups: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error loading groups:', error);
            showError('Error loading groups: ' + error.message);
        });
}

function loadUsers() {
    fetch(apiUrl('/api/index.php?route=user_admin&action=list&limit=1000'))
        .then(async res => {
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                return {users: []};
            }
            
            if (!res.ok) {
                return {users: []};
            }
            return data;
        })
        .then(data => {
            if (data.users) {
                currentUsers = data.users;
            }
        })
        .catch(error => {
            console.error('Error loading users:', error);
        });
}

function renderGroupsTable(groups) {
    const container = document.getElementById('groups-table-container');
    
    if (!groups || groups.length === 0) {
        container.innerHTML = '<div class="text-center py-5 text-muted">No groups found. Create your first group to get started.</div>';
        return;
    }
    
    let html = `
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Members</th>
                        <th>Owner</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    groups.forEach(group => {
        const memberCount = (group.members || []).length;
        const typeBadge = getTypeBadge(group.type || 'private');
        const createdDate = group.created_at ? new Date(group.created_at).toLocaleDateString() : 'N/A';
        
        html += `
            <tr>
                <td>
                    <strong>${escapeHtml(group.name || 'Unnamed')}</strong>
                    ${group.description ? `<br><small class="text-muted">${escapeHtml(group.description)}</small>` : ''}
                </td>
                <td>${typeBadge}</td>
                <td>${memberCount} member${memberCount !== 1 ? 's' : ''}</td>
                <td>${group.owner_id ? escapeHtml(group.owner_id) : 'N/A'}</td>
                <td>${createdDate}</td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" onclick="editGroup('${group.id}')" title="Edit">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                        <button class="btn btn-sm btn-outline-info" onclick="manageMembers('${group.id}')" title="Manage Members">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                            </svg>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteGroup('${group.id}')" title="Delete">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    container.innerHTML = html;
}

function openAddGroupModal() {
    isEditing = false;
    currentGroupId = null;
    document.getElementById('group-modal-title').textContent = 'Create Group';
    document.getElementById('group-form').reset();
    document.getElementById('group-id').value = '';
    document.getElementById('group-modal').style.display = 'block';
}

function closeGroupModal() {
    document.getElementById('group-modal').style.display = 'none';
    isEditing = false;
    currentGroupId = null;
}

function editGroup(groupId) {
    const group = currentGroups.find(g => g.id === groupId);
    if (!group) {
        showError('Group not found');
        return;
    }
    
    isEditing = true;
    currentGroupId = groupId;
    document.getElementById('group-modal-title').textContent = 'Edit Group';
    document.getElementById('group-id').value = group.id;
    document.getElementById('group-name').value = group.name || '';
    document.getElementById('group-description').value = group.description || '';
    document.getElementById('group-type').value = group.type || 'private';
    document.getElementById('group-modal').style.display = 'block';
}

function saveGroup() {
    const form = document.getElementById('group-form');
    const formData = new FormData(form);
    
    const groupData = {
        action: isEditing ? 'update' : 'create',
        csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || '',
        name: formData.get('name'),
        description: formData.get('description'),
        type: formData.get('type') || 'private',
        members: [],
        permissions: {}
    };
    
    if (isEditing) {
        groupData.group_id = currentGroupId;
    }
    
    // Validate
    if (!groupData.name) {
        showError('Group name is required');
        return;
    }
    
    const url = apiUrl('/api/index.php?route=user_groups');
    
    fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(groupData)
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
            throw new Error(data.error || `HTTP ${res.status}: ${res.statusText}`);
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            showSuccess(isEditing ? 'Group updated successfully' : 'Group created successfully');
            closeGroupModal();
            loadGroups(groupFilters);
        } else {
            showError('Failed to save group: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error saving group:', error);
        showError('Error saving group: ' + error.message);
    });
}

function deleteGroup(groupId) {
    if (!confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
        return;
    }
    
    const url = apiUrl('/api/index.php?route=user_groups');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    fetch(url, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'delete',
            csrf_token: csrfToken,
            group_id: groupId
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
            throw new Error(data.error || `HTTP ${res.status}: ${res.statusText}`);
        }
        return data;
    })
    .then(data => {
        if (data.success) {
            showSuccess('Group deleted successfully');
            loadGroups(groupFilters);
        } else {
            showError('Failed to delete group: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error deleting group:', error);
        showError('Error deleting group: ' + error.message);
    });
}

function manageMembers(groupId) {
    // TODO: Implement member management modal
    alert('Member management will be implemented in the next update');
}

function handleGroupSearch(event) {
    if (event.key === 'Enter' || event.keyCode === 13) {
        applyGroupFilters();
    }
}

function applyGroupFilters() {
    const search = document.getElementById('search-groups')?.value.trim() || '';
    const type = document.getElementById('filter-group-type')?.value || '';
    
    const newFilters = {
        ...(search ? {search} : {}),
        ...(type ? {type} : {})
    };
    
    loadGroups(newFilters);
}

function clearGroupFilters() {
    document.getElementById('search-groups').value = '';
    document.getElementById('filter-group-type').value = '';
    loadGroups({});
}

function getTypeBadge(type) {
    const badges = {
        'public': '<span class="badge badge-success">Public</span>',
        'private': '<span class="badge badge-secondary">Private</span>',
        'closed': '<span class="badge badge-warning">Closed</span>'
    };
    return badges[type] || '<span class="badge badge-secondary">' + type + '</span>';
}

function showError(message) {
    alert('Error: ' + message);
}

function showSuccess(message) {
    alert('Success: ' + message);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Expose functions to window at the end
window.openAddGroupModal = openAddGroupModal;
window.closeGroupModal = closeGroupModal;
window.saveGroup = saveGroup;
window.deleteGroup = deleteGroup;
window.editGroup = editGroup;
window.manageMembers = manageMembers;
window.handleGroupSearch = handleGroupSearch;
window.applyGroupFilters = applyGroupFilters;
window.clearGroupFilters = clearGroupFilters;
window.initGroupsManagement = initGroupsManagement;
window.loadGroups = loadGroups;

})(); // End IIFE
