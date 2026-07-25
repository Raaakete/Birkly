/**
 * Settings → Hosting tab (P43).
 */
(function () {
    'use strict';

    var state = {
        hosting: null,
        snippet: ''
    };

    function apiUrl(path) {
        return (window.Birkly && typeof Birkly.url === 'function') ? Birkly.url(path) : path;
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function $(id) {
        return document.getElementById(id);
    }

    function togglePublicFields() {
        var mode = document.querySelector('input[name="hosting-public-mode"]:checked');
        var value = mode ? mode.value : 'none';
        var show = value !== 'none';
        document.querySelectorAll('[data-hosting-public-field]').forEach(function (el) {
            el.style.display = show ? '' : 'none';
        });
        document.querySelectorAll('[data-hosting-same-host-field]').forEach(function (el) {
            el.style.display = show && state.hosting && state.hosting.same_host ? '' : 'none';
        });
        document.querySelectorAll('[data-hosting-project-link]').forEach(function (el) {
            el.style.display = value === 'project' ? '' : 'none';
        });
    }

    function renderSnippet() {
        var pre = $('hosting-client-snippet');
        if (pre) {
            pre.textContent = state.snippet || '<script src="…/birkly-client.js" data-cms-url="…"></script>';
        }
    }

    function loadHosting() {
        return fetch(apiUrl('/api/hosting.php?action=get'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') throw new Error(payload.message || 'Failed to load hosting');
                state.hosting = payload.data.hosting || {};
                state.snippet = payload.data.client_snippet || '';
                $('hosting-cms-url').value = state.hosting.cms_base_url || payload.data.suggested_cms_url || '';
                var mode = state.hosting.public_site_mode || 'none';
                var modeInput = document.querySelector('input[name="hosting-public-mode"][value="' + mode + '"]');
                if (modeInput) modeInput.checked = true;
                $('hosting-public-url').value = state.hosting.public_site_url || '';
                var landing = state.hosting.same_host_landing || 'admin';
                var landingInput = document.querySelector('input[name="hosting-same-host-landing"][value="' + landing + '"]');
                if (landingInput) landingInput.checked = true;
                $('hosting-strict-admin').checked = state.hosting.strict_admin_host !== false;
                state.hosting.same_host = !!payload.data.same_host;
                togglePublicFields();
                renderSnippet();
            });
    }

    function saveHosting() {
        var modeInput = document.querySelector('input[name="hosting-public-mode"]:checked');
        var landingInput = document.querySelector('input[name="hosting-same-host-landing"]:checked');
        var body = {
            action: 'save',
            hosting: {
                cms_base_url: $('hosting-cms-url').value.trim(),
                public_site_mode: modeInput ? modeInput.value : 'none',
                public_site_url: $('hosting-public-url').value.trim(),
                same_host_landing: landingInput ? landingInput.value : 'admin',
                strict_admin_host: $('hosting-strict-admin').checked
            },
            _csrf: getCsrfToken()
        };
        return fetch(apiUrl('/api/hosting.php?action=save'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }

    function init() {
        var tab = document.querySelector('[data-tab="hosting"]');
        if (!tab) return;

        document.querySelectorAll('input[name="hosting-public-mode"]').forEach(function (input) {
            input.addEventListener('change', togglePublicFields);
        });

        $('hosting-use-current-host')?.addEventListener('click', function () {
            $('hosting-cms-url').value = window.location.origin;
        });

        $('hosting-save-btn')?.addEventListener('click', function () {
            var btn = $('hosting-save-btn');
            btn.disabled = true;
            saveHosting().then(function (payload) {
                if (payload.status === 'ok') {
                    state.snippet = payload.data.client_snippet || state.snippet;
                    renderSnippet();
                    if (window.Birkly && Birkly.Utils && typeof Birkly.Utils.showToast === 'function') {
                        Birkly.Utils.showToast('Hosting settings saved', 'success');
                    }
                } else {
                    alert(payload.message || 'Failed to save hosting settings');
                }
            }).catch(function () {
                alert('Failed to save hosting settings');
            }).finally(function () {
                btn.disabled = false;
            });
        });

        $('hosting-copy-snippet')?.addEventListener('click', function () {
            var text = $('hosting-client-snippet')?.textContent || '';
            navigator.clipboard.writeText(text).catch(function () {});
        });

        tab.addEventListener('click', function () {
            if (!state.hosting) loadHosting().catch(function (e) { console.error(e); });
        });

        if (window.location.hash === '#hosting') {
            loadHosting().catch(function (e) { console.error(e); });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
