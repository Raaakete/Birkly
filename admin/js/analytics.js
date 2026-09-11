// Analytics Settings JavaScript (legacy tracking list UI)
(function (global) {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const cmsUrl = window.location.origin;

    function t(key, fallback, params) {
        let text = (global.Birkly && global.Birkly.i18n)
            ? global.Birkly.i18n.t('admin.' + key, fallback || key)
            : (fallback || key);
        if (params && typeof params === 'object') {
            Object.keys(params).forEach(function (name) {
                text = text.split('{' + name + '}').join(String(params[name]));
            });
        }
        return text;
    }

    function toastError(message, fallbackKey) {
        const msg = message || t(fallbackKey, 'Something went wrong');
        global.BirklyFeedback.toast(t('analytics_legacy_error_prefix', 'Error: {message}', { message: msg }), 'error');
    }

    function loadTrackingList() {
        const container = document.getElementById('tracking-list');
        if (!container) return;

        container.innerHTML = '<div class="loading">' + escapeHtml(t('analytics_legacy_loading', 'Loading…')) + '</div>';

        fetch('/api/analytics.php?action=list')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'ok') {
                    renderTrackingList(data.data);
                } else {
                    container.innerHTML = '<div class="error">' + escapeHtml(t('analytics_legacy_load_error', 'Error loading tracking configurations')) + '</div>';
                }
            })
            .catch(error => {
                console.error('Error loading tracking list:', error);
                container.innerHTML = '<div class="error">' + escapeHtml(t('analytics_legacy_load_error', 'Error loading tracking configurations')) + '</div>';
            });
    }

    function renderTrackingList(trackingIds) {
        const container = document.getElementById('tracking-list');
        if (!container) return;

        if (Object.keys(trackingIds).length === 0) {
            container.innerHTML =
                '<div class="empty-state" style="text-align: center; padding: 3rem;">' +
                '<p style="color: #6b7280; margin-bottom: 1rem;">' + escapeHtml(t('analytics_legacy_empty', 'No websites are being tracked yet.')) + '</p>' +
                '<button class="btn btn-primary" onclick="openAddTrackingModal()">' + escapeHtml(t('analytics_legacy_add_first', 'Add your first website')) + '</button>' +
                '</div>';
            return;
        }

        container.innerHTML = Object.values(trackingIds).map(config => {
            const statusClass = config.enabled ? 'enabled' : 'disabled';
            const statusText = config.enabled
                ? t('analytics_legacy_enabled', 'Enabled')
                : t('analytics_legacy_disabled', 'Disabled');
            const toggleLabel = config.enabled
                ? t('analytics_legacy_disable', 'Disable')
                : t('analytics_legacy_enable', 'Enable');

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
                            <label>${escapeHtml(t('analytics_legacy_tracking_code', 'Tracking code'))}</label>
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
<script src="${cmsUrl}/birkly-analytics.js"></script>`)}" title="${escapeHtml(t('analytics_legacy_copy_code', 'Copy code'))}">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                </button>
                            </div>
                            <small>${escapeHtml(t('analytics_legacy_snippet_help', 'Add this code to your website before the closing </body> tag'))}</small>
                        </div>
                        <div class="tracking-actions">
                            <button class="btn btn-secondary toggle-tracking-btn" data-id="${config.id}" data-enabled="${config.enabled}">
                                ${escapeHtml(toggleLabel)}
                            </button>
                            <button class="btn btn-danger delete-tracking-btn" data-id="${config.id}">${escapeHtml(t('analytics_legacy_delete', 'Delete'))}</button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        attachTrackingListeners();
    }

    function attachTrackingListeners() {
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

        document.querySelectorAll('.toggle-tracking-btn').forEach(btn => {
            btn.addEventListener('click', async function () {
                const trackingId = this.getAttribute('data-id');
                const currentEnabled = this.getAttribute('data-enabled') === 'true';
                toggleTracking(trackingId, !currentEnabled);
            });
        });

        document.querySelectorAll('.delete-tracking-btn').forEach(btn => {
            btn.addEventListener('click', async function () {
                const trackingId = this.getAttribute('data-id');
                if (await global.BirklyFeedback.confirm(t('analytics_legacy_delete_confirm', 'Are you sure you want to delete this tracking configuration? Analytics will stop for this website.'))) {
                    deleteTracking(trackingId);
                }
            });
        });
    }

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
                toastError(data.message, 'analytics_legacy_update_failed');
            }
        })
        .catch(error => {
            console.error('Error toggling tracking:', error);
            global.BirklyFeedback.toast(t('analytics_legacy_update_error', 'Error updating tracking configuration'), 'error');
        });
    }

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
                toastError(data.message, 'analytics_legacy_delete_failed');
            }
        })
        .catch(error => {
            console.error('Error deleting tracking:', error);
            global.BirklyFeedback.toast(t('analytics_legacy_delete_error', 'Error deleting tracking configuration'), 'error');
        });
    }

    global.openAddTrackingModal = function () {
        const modal = document.getElementById('add-tracking-modal');
        if (modal) {
            modal.style.display = 'flex';
            document.getElementById('add-tracking-form').reset();
        }
    };

    function closeAddTrackingModal() {
        const modal = document.getElementById('add-tracking-modal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    const addTrackingForm = document.getElementById('add-tracking-form');
    if (addTrackingForm) {
        addTrackingForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const name = document.getElementById('tracking-name').value;
            const domain = document.getElementById('tracking-domain').value;

            if (!name || !domain) {
                global.BirklyFeedback.toast(t('analytics_legacy_fill_fields', 'Please fill in all fields'), 'warning');
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
                    toastError(data.message, 'analytics_legacy_create_failed');
                }
            })
            .catch(error => {
                console.error('Error creating tracking:', error);
                global.BirklyFeedback.toast(t('analytics_legacy_create_error', 'Error creating tracking configuration'), 'error');
            });
        });
    }

    document.querySelectorAll('#add-tracking-modal .modal-close').forEach(btn => {
        btn.addEventListener('click', closeAddTrackingModal);
    });

    const addTrackingModal = document.getElementById('add-tracking-modal');
    if (addTrackingModal) {
        addTrackingModal.addEventListener('click', async function (e) {
            if (e.target === addTrackingModal) {
                closeAddTrackingModal();
            }
        });
    }

    const addTrackingBtn = document.getElementById('add-tracking-btn');
    if (addTrackingBtn) {
        addTrackingBtn.addEventListener('click', global.openAddTrackingModal);
    }

    document.addEventListener('DOMContentLoaded', function () {
        const analyticsTab = document.querySelector('[data-tab="analytics"]');
        if (analyticsTab) {
            analyticsTab.addEventListener('click', async function () {
                setTimeout(loadTrackingList, 100);
            });

            if (window.location.hash === '#analytics' || analyticsTab.classList.contains('active')) {
                loadTrackingList();
            }
        }
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})(typeof window !== 'undefined' ? window : globalThis);
