// Analytics Settings JavaScript
(function() {
    'use strict';
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const cmsUrl = window.location.origin;

    function anaT(key, fallback, params) {
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
    
    // Load tracking configurations
    function loadTrackingList() {
        const container = document.getElementById('tracking-list');
        if (!container) return;
        
        container.innerHTML = '<div class="loading">' + anaT('loading', 'Loading') + '…</div>';
        
        fetch('/api/analytics.php?action=list')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok') {
                    renderTrackingList(data.data);
                } else {
                    container.innerHTML = '<div class="error">' + anaT('analytics_js_load_error', 'Error loading tracking configurations') + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading tracking list:', error);
                container.innerHTML = '<div class="error">' + anaT('analytics_js_load_error', 'Error loading tracking configurations') + '</div>';
            });
    }
    
    // Render tracking list
    function renderTrackingList(trackingIds) {
        const container = document.getElementById('tracking-list');
        if (!container) return;
        
        if (Object.keys(trackingIds).length === 0) {
            container.innerHTML =
                '<div class="empty-state" style="text-align: center; padding: 3rem;">' +
                '<p style="color: #6b7280; margin-bottom: 1rem;">' + escapeHtml(anaT('analytics_js_empty_not_tracked', 'No websites are being tracked yet.')) + '</p>' +
                '<button type="button" class="button is-main" onclick="openAddTrackingModal()">' + escapeHtml(anaT('analytics_js_add_first_website', 'Add Your First Website')) + '</button>' +
                '</div>';
            return;
        }
        
        container.innerHTML = Object.values(trackingIds).map(config => {
            const statusClass = config.enabled ? 'enabled' : 'disabled';
            const statusText = config.enabled ? anaT('enabled', 'Enabled') : anaT('analytics_js_disabled', 'Disabled');
            const toggleLabel = config.enabled ? anaT('analytics_js_disable', 'Disable') : anaT('analytics_js_enable', 'Enable');
            const deleteLabel = anaT('delete', 'Delete');
            const codeLabel = anaT('analytics_js_tracking_code_label', 'Tracking Code');
            const copyTitle = anaT('analytics_js_copy_code', 'Copy code');
            const embedHint = anaT('analytics_js_embed_hint', 'Add this code to your website before the closing </body> tag');
            
            return `
                <div class="tracking-card" data-tracking-id="${config.id}">
                    <div class="tracking-header">
                        <div>
                            <h4>${escapeHtml(config.name)}</h4>
                            <p class="tracking-domain">${escapeHtml(config.domain)}</p>
                        </div>
                        <div class="tracking-status">
                            <span class="status-badge ${statusClass}">${escapeHtml(statusText)}</span>
                        </div>
                    </div>
                    <div class="tracking-body">
                        <div class="tracking-code-section">
                            <label>${escapeHtml(codeLabel)}</label>
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
<script src="${cmsUrl}/birkly-analytics.js"></script>`)}" title="${escapeHtml(copyTitle)}">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                </button>
                            </div>
                            <small>${escapeHtml(embedHint)}</small>
                        </div>
                        <div class="tracking-actions">
                            <button class="button toggle-tracking-btn" data-id="${config.id}" data-enabled="${config.enabled}">
                                ${escapeHtml(toggleLabel)}
                            </button>
                            <button class="button is-danger delete-tracking-btn" data-id="${config.id}">${escapeHtml(deleteLabel)}</button>
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
            btn.addEventListener('click', async function () {
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
            btn.addEventListener('click', async function () {
                const trackingId = this.getAttribute('data-id');
                const currentEnabled = this.getAttribute('data-enabled') === 'true';
                toggleTracking(trackingId, !currentEnabled);
            });
        });
        
        // Delete tracking buttons
        document.querySelectorAll('.delete-tracking-btn').forEach(btn => {
            btn.addEventListener('click', async function () {
                const trackingId = this.getAttribute('data-id');
                if (await BirklyFeedback.confirm(anaT('analytics_js_confirm_delete_tracking', 'Are you sure you want to delete this tracking configuration? This will stop collecting analytics for this website.'))) {
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
                BirklyFeedback.toast(anaT('feedback_error_prefix', 'Error:') + ' ' + (data.message || anaT('analytics_js_failed_update_tracking', 'Failed to update tracking')), 'error');
            }
        })
        .catch(error => {
            console.error('Error toggling tracking:', error);
            BirklyFeedback.toast(anaT('analytics_js_err_update_tracking', 'Error updating tracking configuration'), 'error')
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
                BirklyFeedback.toast(anaT('feedback_error_prefix', 'Error:') + ' ' + (data.message || anaT('analytics_js_failed_delete_tracking', 'Failed to delete tracking')), 'error');
            }
        })
        .catch(error => {
            console.error('Error deleting tracking:', error);
            BirklyFeedback.toast(anaT('analytics_js_err_delete_tracking', 'Error deleting tracking configuration'), 'error')
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
                BirklyFeedback.toast(anaT('analytics_js_fill_all_fields', 'Please fill in all fields'), 'warning')
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
                    BirklyFeedback.toast(anaT('feedback_error_prefix', 'Error:') + ' ' + (data.message || anaT('analytics_js_failed_create_tracking', 'Failed to create tracking')), 'error');
                }
            })
            .catch(error => {
                console.error('Error creating tracking:', error);
                BirklyFeedback.toast(anaT('analytics_js_err_create_tracking', 'Error creating tracking configuration'), 'error')
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
        addTrackingModal.addEventListener('click', async function (e) {
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
            analyticsTab.addEventListener('click', async function () {
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

