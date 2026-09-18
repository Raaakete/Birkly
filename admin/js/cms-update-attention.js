/**
 * Background CMS update check, Settings nav dot refresh, and optional update modal.
 */
(function () {
    'use strict';

    var CMS_PLUGIN_ID = 'birkly';
    var syncIntervalMs = 30 * 60 * 1000;

    function url(path) {
        return window.Birkly && typeof window.Birkly.url === 'function' ? window.Birkly.url(path) : path;
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    function showModal(latestVersion, currentVersion) {
        var modal = document.getElementById('cms-update-modal');
        if (!modal || modal.dataset.shownFor === latestVersion) {
            return;
        }
        var body = document.getElementById('cms-update-modal-body');
        if (body && latestVersion) {
            var template = body.getAttribute('data-template') || body.textContent || '';
            body.textContent = template
                .replace(/\{latest\}/g, latestVersion)
                .replace(/\{current\}/g, currentVersion || '');
        }
        modal.removeAttribute('hidden');
        modal.classList.add('active');
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        modal.dataset.shownFor = latestVersion;
    }

    function hideModal() {
        var modal = document.getElementById('cms-update-modal');
        if (!modal) {
            return;
        }
        modal.classList.remove('active');
        modal.style.display = 'none';
        modal.setAttribute('hidden', 'hidden');
        modal.setAttribute('aria-hidden', 'true');
    }

    function dismissModal(version) {
        hideModal();
        if (!version) {
            return;
        }
        fetch(url('/api/plugin_activity.php?action=cms_update_dismiss_modal'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ version: version, _csrf: csrfToken() }),
        }).catch(function () { /* non-critical */ });
    }

    function bindModal(version) {
        var modal = document.getElementById('cms-update-modal');
        if (!modal || modal.dataset.bound === '1') {
            return;
        }
        modal.dataset.bound = '1';
        modal.querySelectorAll('[data-cms-update-modal-close], [data-cms-update-modal-later]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                dismissModal(version);
            });
        });
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                dismissModal(version);
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                dismissModal(version);
            }
        });
    }

    function syncCmsUpdates() {
        return fetch(url('/api/plugin_activity.php?action=cms_update_sync'), { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || data.status !== 'ok' || !data.data) {
                    return;
                }
                var payload = data.data;
                if (typeof window.loadNavAttention === 'function') {
                    window.loadNavAttention();
                }
                if (payload.show_modal && payload.update_available) {
                    var latest = payload.latest_version || '';
                    var current = payload.current_version || '';
                    bindModal(latest);
                    showModal(latest, current);
                }
            })
            .catch(function () { /* ignore */ });
    }

    document.addEventListener('DOMContentLoaded', function () {
        syncCmsUpdates();
        setInterval(syncCmsUpdates, syncIntervalMs);
    });

    window.birklyCmsUpdateAttentionSync = syncCmsUpdates;
})();
