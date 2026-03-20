// Analytics Settings JavaScript
(function() {
    'use strict';
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const cmsUrl = window.location.origin;
    
    // Load tracking configurations
    function loadTrackingList() {
        const container = document.getElementById('tracking-list');
        if (!container) return;
        
        container.innerHTML = '<div class="loading">Loading...</div>';
        
        fetch('/api/analytics.php?action=list')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok') {
                    renderTrackingList(data.data);
                } else {
                    container.innerHTML = '<div class="error">Error loading tracking configurations</div>';
                }
            })
            .catch(error => {
                console.error('Error loading tracking list:', error);
                container.innerHTML = '<div class="error">Error loading tracking configurations</div>';
            });
    }
    
    // Render tracking list
    function renderTrackingList(trackingIds) {
        const container = document.getElementById('tracking-list');
        if (!container) return;
        
        if (Object.keys(trackingIds).length === 0) {
            container.innerHTML = `
                <div class="empty-state" style="text-align: center; padding: 3rem;">
                    <p style="color: #6b7280; margin-bottom: 1rem;">No websites are being tracked yet.</p>
                    <button class="btn btn-primary" onclick="openAddTrackingModal()">Add Your First Website</button>
                </div>
            `;
            return;
        }
        
        container.innerHTML = Object.values(trackingIds).map(config => {
            const statusClass = config.enabled ? 'enabled' : 'disabled';
            const statusText = config.enabled ? 'Enabled' : 'Disabled';
            
            return `
                <div class="tracking-card" data-tracking-id="${config.id}">
                    <div class="tracking-header">
                        <div>
                            <h4>${escapeHtml(config.name)}</h4>
                            <p class="tracking-domain">${escapeHtml(config.domain)}</p>
                        </div>
                        <div class="tracking-status">
                            <span class="status-badge ${statusClass}">${statusText}</span>
                        </div>
                    </div>
                    <div class="tracking-body">
                        <div class="tracking-code-section">
                            <label>Tracking Code</label>
                            <div class="code-block">
                                <pre><code>&lt;script&gt;
  window.HaruAnalytics = {
    trackingId: '${config.id}',
    cmsUrl: '${cmsUrl}'
  };
&lt;/script&gt;
&lt;script src="${cmsUrl}/birkly-analytics.js"&gt;&lt;/script&gt;</code></pre>
                                <button class="btn-icon copy-code-btn" data-code="${escapeHtml(`<script>
  window.HaruAnalytics = {
    trackingId: '${config.id}',
    cmsUrl: '${cmsUrl}'
  };
</script>
<script src="${cmsUrl}/birkly-analytics.js"></script>`)}" title="Copy code">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                </button>
                            </div>
                            <small>Add this code to your website before the closing &lt;/body&gt; tag</small>
                        </div>
                        <div class="tracking-actions">
                            <button class="btn btn-secondary toggle-tracking-btn" data-id="${config.id}" data-enabled="${config.enabled}">
                                ${config.enabled ? 'Disable' : 'Enable'}
                            </button>
                            <button class="btn btn-danger delete-tracking-btn" data-id="${config.id}">Delete</button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        // Attach event listeners
        attachTrackingListeners();
    }
    
    // Attach event listeners to tracking cards
    function attachTrackingListeners() {
        // Copy code buttons
        document.querySelectorAll('.copy-code-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const code = this.getAttribute('data-code');
                navigator.clipboard.writeText(code).then(() => {
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20,6 9,17 4,12"></polyline></svg>';
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                    }, 2000);
                });
            });
        });
        
        // Toggle tracking buttons
        document.querySelectorAll('.toggle-tracking-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const trackingId = this.getAttribute('data-id');
                const currentEnabled = this.getAttribute('data-enabled') === 'true';
                toggleTracking(trackingId, !currentEnabled);
            });
        });
        
        // Delete tracking buttons
        document.querySelectorAll('.delete-tracking-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const trackingId = this.getAttribute('data-id');
                if (confirm('Are you sure you want to delete this tracking configuration? This will stop collecting analytics for this website.')) {
                    deleteTracking(trackingId);
                }
            });
        });
    }
    
    // Toggle tracking enabled/disabled
    function toggleTracking(trackingId, enabled) {
        fetch('/api/analytics.php?action=update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                tracking_id: trackingId,
                updates: { enabled: enabled },
                _csrf: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                loadTrackingList();
            } else {
                alert('Error: ' + (data.message || 'Failed to update tracking'));
            }
        })
        .catch(error => {
            console.error('Error toggling tracking:', error);
            alert('Error updating tracking configuration');
        });
    }
    
    // Delete tracking
    function deleteTracking(trackingId) {
        fetch('/api/analytics.php?action=delete', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                tracking_id: trackingId,
                _csrf: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'ok') {
                loadTrackingList();
            } else {
                alert('Error: ' + (data.message || 'Failed to delete tracking'));
            }
        })
        .catch(error => {
            console.error('Error deleting tracking:', error);
            alert('Error deleting tracking configuration');
        });
    }
    
    // Open add tracking modal
    window.openAddTrackingModal = function() {
        const modal = document.getElementById('add-tracking-modal');
        if (modal) {
            modal.style.display = 'flex';
            document.getElementById('add-tracking-form').reset();
        }
    };
    
    // Close add tracking modal
    function closeAddTrackingModal() {
        const modal = document.getElementById('add-tracking-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
    // Handle add tracking form
    const addTrackingForm = document.getElementById('add-tracking-form');
    if (addTrackingForm) {
        addTrackingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const name = document.getElementById('tracking-name').value;
            const domain = document.getElementById('tracking-domain').value;
            
            if (!name || !domain) {
                alert('Please fill in all fields');
                return;
            }
            
            fetch('/api/analytics.php?action=create', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    name: name,
                    domain: domain,
                    _csrf: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok') {
                    closeAddTrackingModal();
                    loadTrackingList();
                } else {
                    alert('Error: ' + (data.message || 'Failed to create tracking'));
                }
            })
            .catch(error => {
                console.error('Error creating tracking:', error);
                alert('Error creating tracking configuration');
            });
        });
    }
    
    // Modal close handlers
    document.querySelectorAll('#add-tracking-modal .modal-close').forEach(btn => {
        btn.addEventListener('click', closeAddTrackingModal);
    });
    
    // Close modal on backdrop click
    const addTrackingModal = document.getElementById('add-tracking-modal');
    if (addTrackingModal) {
        addTrackingModal.addEventListener('click', function(e) {
            if (e.target === addTrackingModal) {
                closeAddTrackingModal();
            }
        });
    }
    
    // Add tracking button
    const addTrackingBtn = document.getElementById('add-tracking-btn');
    if (addTrackingBtn) {
        addTrackingBtn.addEventListener('click', openAddTrackingModal);
    }
    
    // Load tracking list when analytics tab is shown
    document.addEventListener('DOMContentLoaded', function() {
        // Check if we're on the analytics tab
        const analyticsTab = document.querySelector('[data-tab="analytics"]');
        if (analyticsTab) {
            analyticsTab.addEventListener('click', function() {
                setTimeout(loadTrackingList, 100);
            });
            
            // Load if already on analytics tab
            if (window.location.hash === '#analytics' || analyticsTab.classList.contains('active')) {
                loadTrackingList();
            }
        }
    });
    
    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})();

