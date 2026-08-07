/**
 * Settings → System → Developer mode (P61).
 */
(function () {
    'use strict';

    function apiUrl(path) {
        return (window.Birkly && typeof window.Birkly.url === 'function') ? window.Birkly.url(path) : path;
    }

    function getCsrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function escapeHtml(text) {
        var d = document.createElement('div');
        d.textContent = text == null ? '' : String(text);
        return d.innerHTML;
    }

    function setChrome(enabled) {
        document.body.classList.toggle('developer-mode-on', !!enabled);
        var badge = document.getElementById('developer-mode-badge');
        if (!badge && enabled) {
            badge = document.createElement('span');
            badge.id = 'developer-mode-badge';
            badge.className = 'badge badge-neutral developer-mode-badge';
            badge.textContent = 'Developer mode';
            var host = document.querySelector('.topbar-actions') || document.querySelector('.admin-topbar') || document.querySelector('.sidebar-header');
            if (host) host.appendChild(badge);
        }
        if (badge) badge.hidden = !enabled;
    }

    function renderInfo(info) {
        var el = document.getElementById('developer-system-info');
        if (!el) return;
        el.innerHTML = '<div class="card-body"><div class="kv">' +
            '<span class="kv-key">PHP</span><span class="kv-value">' + escapeHtml(info.php_version) + '</span>' +
            '<span class="kv-key">SAPI</span><span class="kv-value">' + escapeHtml(info.sapi) + '</span>' +
            '<span class="kv-key">Project root</span><span class="kv-value">' + escapeHtml(info.project_root) + '</span>' +
            '<span class="kv-key">Data root</span><span class="kv-value">' + escapeHtml(info.data_root || '(legacy layout)') + '</span>' +
            '<span class="kv-key">Git</span><span class="kv-value">' + (info.git_available ? 'available' : 'not found') + '</span>' +
            '<span class="kv-key">Zip</span><span class="kv-value">' + (info.zip_available ? 'available' : 'missing') + '</span>' +
            '</div></div>';
    }

    function loadTools() {
        return fetch(apiUrl('/api/developer.php?action=system_info'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') throw new Error(payload.message || 'Failed');
                renderInfo(payload.data || {});
            })
            .catch(function (err) {
                var el = document.getElementById('developer-system-info');
                if (el) el.innerHTML = '<div class="card-body"><p class="form-help">' + escapeHtml(err.message) + '</p></div>';
            });
    }

    function setEnabled(enabled) {
        var tools = document.getElementById('developer-mode-tools');
        if (tools) tools.hidden = !enabled;
        setChrome(enabled);
        if (enabled) loadTools();
    }

    function save(enabled) {
        return fetch(apiUrl('/api/developer.php?action=save'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save', enabled: !!enabled, _csrf: getCsrf() })
        }).then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') throw new Error(payload.message || 'Save failed');
                setEnabled(!!payload.data.enabled);
                if (window.BirklyFeedback) {
                    BirklyFeedback.toast(payload.message || 'Saved', 'success');
                }
            })
            .catch(function (err) {
                if (window.BirklyFeedback) BirklyFeedback.toast(err.message || 'Save failed', 'error');
            });
    }

    function exportPack() {
        var includeContent = !!(document.getElementById('developer-export-include-content') || {}).checked;
        return fetch(apiUrl('/api/developer.php?action=export_template'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'export_template',
                include_content: includeContent,
                _csrf: getCsrf()
            })
        }).then(function (r) {
            if (!r.ok) {
                return r.json().then(function (p) { throw new Error((p && p.message) || 'Export failed'); });
            }
            return r.blob().then(function (blob) {
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'birkly-template-export.zip';
                document.body.appendChild(a);
                a.click();
                a.remove();
                if (window.BirklyFeedback) BirklyFeedback.toast('Template pack downloaded', 'success');
            });
        }).catch(function (err) {
            if (window.BirklyFeedback) BirklyFeedback.toast(err.message || 'Export failed', 'error');
        });
    }

    function smoke() {
        var out = document.getElementById('developer-smoke-result');
        return fetch(apiUrl('/api/developer.php?action=smoke'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') throw new Error(payload.message || 'Smoke failed');
                if (out) {
                    out.hidden = false;
                    out.textContent = JSON.stringify(payload.data, null, 2);
                }
            })
            .catch(function (err) {
                if (out) {
                    out.hidden = false;
                    out.textContent = err.message || 'Smoke failed';
                }
            });
    }

    function init() {
        var toggle = document.getElementById('developer-mode-toggle');
        if (!toggle) return;
        fetch(apiUrl('/api/developer.php?action=get'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                var enabled = !!(payload.data && payload.data.enabled);
                toggle.checked = enabled;
                setEnabled(enabled);
            })
            .catch(function () { /* ignore */ });

        toggle.addEventListener('change', function () {
            save(toggle.checked);
        });
        var exportBtn = document.getElementById('developer-export-btn');
        if (exportBtn) exportBtn.addEventListener('click', exportPack);
        var smokeBtn = document.getElementById('developer-smoke-btn');
        if (smokeBtn) smokeBtn.addEventListener('click', smoke);
    }

    window.initDeveloperModeSettings = init;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
