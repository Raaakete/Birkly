// community_users.js - Community Users admin page

function initCommunityUsersAdmin() {
  loadUsers();
  loadStats();
  
  // Set up form event listener
  const form = document.getElementById('userForm');
  if (form) {
    form.addEventListener('submit', handleUserFormSubmit);
  }
}

function loadUsers() {
  console.log('Loading users...');
  fetch('/api/community_users_admin.php?action=list')
    .then(res => {
      console.log('Users response status:', res.status);
      return res.json();
    })
    .then(data => {
      console.log('Users response data:', data);
      if (data.status === 'ok') {
        const users = data.data.users || [];
        const tableDiv = document.getElementById('usersTable');
        
        if (!users.length) {
          tableDiv.innerHTML = '<div class="text-muted">No community users found.</div>';
          return;
        }
        
        let html = `
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Username</th>
                  <th>Email</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
        `;
        
        users.forEach(user => {
          const statusBadge = user.status === 'active' 
            ? '<span class="badge badge-success">Active</span>'
            : '<span class="badge badge-secondary">Inactive</span>';
          
          html += `
            <tr>
              <td>${user.username || 'N/A'}</td>
              <td>${user.email || 'N/A'}</td>
              <td>${statusBadge}</td>
              <td>${user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}</td>
              <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editUser('${user.id}')">Edit</button>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteUser('${user.id}')">Delete</button>
              </td>
            </tr>
          `;
        });
        
        html += '</tbody></table></div>';
        tableDiv.innerHTML = html;
      } else {
        document.getElementById('usersTable').innerHTML = '<div class="text-danger">Failed to load users: ' + data.message + '</div>';
      }
    })
    .catch(error => {
      document.getElementById('usersTable').innerHTML = '<div class="text-danger">Error loading users: ' + error.message + '</div>';
    });
}

function loadStats() {
  console.log('Loading stats...');
  fetch('/api/community_users_admin.php?action=stats')
    .then(res => {
      console.log('Stats response status:', res.status);
      return res.json();
    })
    .then(data => {
      console.log('Stats response data:', data);
      if (data.status === 'ok') {
        const stats = data.data.stats || {};
        const statsContainer = document.getElementById('statsContainer');
        
        let html = `
          <div class="stats-grid">
            <div class="stat-item">
              <div class="stat-value">${stats.total_users || 0}</div>
              <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">${stats.active_users || 0}</div>
              <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">${stats.pending_verification || 0}</div>
              <div class="stat-label">Pending Verification</div>
            </div>
            <div class="stat-item">
              <div class="stat-value">${stats.pending_approval || 0}</div>
              <div class="stat-label">Pending Approval</div>
            </div>
          </div>
        `;
        
        statsContainer.innerHTML = html;
      } else {
        document.getElementById('statsContainer').innerHTML = '<div class="text-danger">Failed to load stats</div>';
      }
    })
    .catch(error => {
      document.getElementById('statsContainer').innerHTML = '<div class="text-danger">Error loading stats</div>';
    });
}

function openAddUserModal() {
  // Reset form
  document.getElementById('userForm').reset();
  document.getElementById('userId').value = '';
  document.getElementById('password').required = true; // Make password required for new users
  document.getElementById('userModalTitle').textContent = 'Add New User';
  
  // Show modal
  document.getElementById('userModal').style.display = 'block';
}

function closeUserModal() {
  document.getElementById('userModal').style.display = 'none';
}

function openPluginSettings() {
  // Open the plugin settings modal from the main settings page
  if (window.configurePlugin) {
    window.configurePlugin('community_users');
  } else {
    // Fallback: redirect to settings page
    window.location.hash = 'settings';
  }
}

function editUser(userId) {
  console.log('Editing user:', userId);
  
  // Fetch user data
  fetch(`/api/community_users_admin.php?action=get&id=${userId}`)
    .then(res => res.json())
    .then(data => {
      if (data.status === 'ok') {
        const user = data.data.user;
        
        // Populate form
        document.getElementById('userId').value = user.id;
        document.getElementById('username').value = user.username || '';
        document.getElementById('email').value = user.email || '';
        document.getElementById('password').value = ''; // Don't populate password
        document.getElementById('password').required = false; // Make password optional for editing
        document.getElementById('status').value = user.status || 'active';
        
        // Update modal title
        document.getElementById('userModalTitle').textContent = 'Edit User';
        
        // Show modal
        document.getElementById('userModal').style.display = 'block';
      } else {
        showMessage('Failed to load user data: ' + data.message, 'error');
      }
    })
    .catch(error => {
      showMessage('Error loading user data: ' + error.message, 'error');
    });
}

function deleteUser(userId) {
  if (!confirm('Are you sure you want to delete this user?')) return;
  
  fetch('/api/community_users_admin.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'delete_user',
      user_id: userId,
      _csrf: window.csrfToken || ''
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'ok') {
      showMessage('User deleted successfully', 'success');
      loadUsers();
      loadStats();
    } else {
      showMessage('Failed to delete user: ' + data.message, 'error');
    }
  })
  .catch(error => {
    showMessage('Error deleting user: ' + error.message, 'error');
  });
}

function showMessage(message, type = 'info') {
  // Create a temporary message display
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

// Form submission handler
function handleUserFormSubmit(event) {
  event.preventDefault();
  
  const formData = new FormData(event.target);
  const userId = formData.get('id');
  const isEditing = userId && userId.trim() !== '';
  
  const userData = {
    username: formData.get('username'),
    email: formData.get('email'),
    password: formData.get('password'),
    status: formData.get('status')
  };
  
  // Add ID for editing
  if (isEditing) {
    userData.id = userId;
  }
  
  // Validate required fields
  if (!userData.username || !userData.email) {
    showMessage('Username and email are required', 'error');
    return;
  }
  
  // Password is required for new users, optional for editing
  if (!isEditing && !userData.password) {
    showMessage('Password is required for new users', 'error');
    return;
  }
  
  const action = isEditing ? 'update' : 'add';
  const url = '/api/community_users_admin.php';
  
  fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: action,
      ...userData
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'ok') {
      showMessage(data.message || (isEditing ? 'User updated successfully' : 'User added successfully'), 'success');
      closeUserModal();
      loadUsers(); // Refresh the user list
      loadStats(); // Refresh stats
    } else {
      showMessage('Failed to save user: ' + data.message, 'error');
    }
  })
  .catch(error => {
    showMessage('Error saving user: ' + error.message, 'error');
  });
}

// SPA destroy function
function destroyCommunityUsersAdmin() {
  // Clean up any event listeners or intervals if needed
  const form = document.getElementById('userForm');
  if (form) {
    form.removeEventListener('submit', handleUserFormSubmit);
  }
} 