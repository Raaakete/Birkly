/**
 * Background CMS update check, Settings nav dot refresh, and optional update modal.
 */
(function () {
    'use strict';

    var syncIntervalMs = 30 * 60 * 1000;
    var SESSION_DISMISS_KEY = 'birkly_cms_update_modal_dismissed';

    function url(path) {
        return window.Birkly && typeof window.Birkly.url === 'function' ? window.Birkly.url(path) : path;
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    function isSettingsSystemPage() {
        var params = new URLSearchParams(window.location.search);
        return params.get('page') === 'settings' && params.get('tab') === 'system';
    }

    function isModalDismissedForVersion(version) {
        if (!version) {
            return false;
        }
        try {
            if (sessionStorage.getItem(SESSION_DISMISS_KEY) === version) {
                return true;
            }
        } catch (e) { /* ignore */ }
        return false;
    }

    function rememberModalDismissed(version) {
        if (!version) {
            return;
        }
        try {
            sessionStorage.setItem(SESSION_DISMISS_KEY, version);
        } catch (e) { /* ignore */ }
    }

    function showModal(latestVersion, currentVersion) {
        if (isSettingsSystemPage() || isModalDismissedForVersion(latestVersion)) {
            hideModal();
            return;
        }
        var modal = document.getElementById('cms-update-modal');
        if (!modal) {
            return;
        }
        if (modal.classList.contains('active') && modal.dataset.shownFor === latestVersion) {
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
        delete modal.dataset.shownFor;
    }

    function dismissModal(version, options) {
        options = options || {};
        version = version || '';
        hideModal();
        if (version) {
            rememberModalDismissed(version);
        }
        if (version && !options.skipServer) {
            fetch(url('/api/plugin_activity.php?action=cms_update_dismiss_modal'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ version: version, _csrf: csrfToken() }),
            }).catch(function () { /* non-critical */ });
        }
    }

    function bindModalOnce() {
        var modal = document.getElementById('cms-update-modal');
        if (!modal || modal.dataset.bound === '1') {
            return;
        }
        modal.dataset.bound = '1';
        modal.querySelectorAll('[data-cms-update-modal-close], [data-cms-update-modal-later]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                dismissModal(modal.dataset.shownFor || '');
            });
        });
        var goBtn = modal.querySelector('[data-cms-update-modal-go]');
        if (goBtn) {
            goBtn.addEventListener('click', function () {
                var version = modal.dataset.shownFor || '';
                dismissModal(version, { skipServer: false });
            });
        }
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                dismissModal(modal.dataset.shownFor || '');
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                dismissModal(modal.dataset.shownFor || '');
            }
        });
    }

    function applyAttentionPayload(payload) {
        if (!payload) {
            return;
        }
        if (typeof window.loadUnreadCount === 'function') {
            window.loadUnreadCount();
        }
        if (typeof window.loadNavAttention === 'function') {
            window.loadNavAttention();
        }
        if (isSettingsSystemPage()) {
            hideModal();
            return;
        }
        var latest = payload.latest_version || '';
        var showModalFlag = !!payload.show_modal && !!payload.update_available;
        if (showModalFlag && !isModalDismissedForVersion(latest)) {
            bindModalOnce();
            showModal(latest, payload.current_version || '');
        }
    }

    function syncCmsUpdates() {
        return fetch(url('/api/plugin_activity.php?action=cms_update_sync'), { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || data.status !== 'ok' || !data.data) {
                    return;
                }
                applyAttentionPayload(data.data);
            })
            .catch(function () { /* ignore */ });
    }

    window.birklyCmsUpdateAttentionAfterCheck = function (updateData) {
        if (!updateData || !updateData.update_available) {
            return;
        }
        if (isSettingsSystemPage()) {
            hideModal();
            if (typeof window.loadUnreadCount === 'function') {
                window.loadUnreadCount();
            }
            if (typeof window.loadNavAttention === 'function') {
                window.loadNavAttention();
            }
            return;
        }
        var attention = updateData.attention || {};
        applyAttentionPayload({
            update_available: true,
            current_version: updateData.current_version,
            latest_version: updateData.latest_version,
            show_modal: attention.show_modal === true,
        });
    };

    document.addEventListener('DOMContentLoaded', function () {
        bindModalOnce();
        if (isSettingsSystemPage()) {
            hideModal();
        }
        syncCmsUpdates();
        setInterval(syncCmsUpdates, syncIntervalMs);
    });

    window.birklyCmsUpdateAttentionSync = syncCmsUpdates;
    window.birklyCmsUpdateAttentionHideModal = hideModal;
})();
