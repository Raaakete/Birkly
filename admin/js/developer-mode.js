/**
 * Settings → System → Developer mode (P61).
 */
(function () {
    'use strict';

    var initialized = false;

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

    function toast(message, type) {
        if (window.BirklyFeedback && typeof BirklyFeedback.toast === 'function') {
            BirklyFeedback.toast(message, type || 'info');
            return;
        }
        if (typeof window.showMessage === 'function') {
            window.showMessage(message, type || 'info');
        }
    }

    function setChromeCue(enabled) {
        document.body.classList.toggle('developer-mode-on', !!enabled);
        var badge = document.getElementById('developer-mode-badge');
        if (badge) {
            badge.hidden = !enabled;
        }
    }

    function setToolsVisible(enabled) {
        var tools = document.getElementById('developer-mode-tools');
        if (tools) tools.hidden = !enabled;
        setChromeCue(enabled);
    }

    function renderSystemInfo(info) {
        var mount = document.getElementById('developer-system-info');
        if (!mount) return;
        info = info || {};
        var plugins = Array.isArray(info.plugins) ? info.plugins : [];
        var activePlugins = plugins.filter(function (p) { return p.active; }).map(function (p) { return p.id; });
        mount.innerHTML =
            '<div class="card-body u-stack-3">' +
            '<div class="kv">' +
            '<span class="kv-key">PHP</span><span class="kv-value">' + escapeHtml(info.php_version || '—') + '</span>' +
            '<span class="kv-key">SAPI</span><span class="kv-value">' + escapeHtml(info.sapi || '—') + '</span>' +
            '<span class="kv-key">OS</span><span class="kv-value">' + escapeHtml(info.os || '—') + '</span>' +
            '<span class="kv-key">ZipArchive</span><span class="kv-value">' + (info.zip_available ? 'yes' : 'no') + '</span>' +
            '<span class="kv-key">git</span><span class="kv-value">' + (info.git_available ? 'yes' : 'no') + '</span>' +
            '<span class="kv-key">Project root</span><span class="kv-value u-break-all">' + escapeHtml(info.project_root || '—') + '</span>' +
            '<span class="kv-key">Data root</span><span class="kv-value u-break-all">' + escapeHtml(info.data_root || '—') + '</span>' +
            '<span class="kv-key">Active plugins</span><span class="kv-value">' +
            escapeHtml(activePlugins.length ? activePlugins.join(', ') : 'none') + '</span>' +
            '</div>' +
            '<p class="form-help">Debug helpers: add <code>?__dbg=1</code> to a public page for client logs. ' +
            'MCP endpoint: <code>/mcp</code>. Template export never includes users or secrets.</p>' +
            '</div>';
    }

    function loadSystemInfo() {
        var mount = document.getElementById('developer-system-info');
        if (mount) {
            mount.innerHTML = '<div class="card-body"><div class="loading-state"><p>Loading system info…</p></div></div>';
        }
        return fetch(apiUrl('/api/developer.php?action=system_info'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') {
                    throw new Error(payload.message || 'Failed to load system info');
                }
                renderSystemInfo(payload.data || {});
            })
            .catch(function (err) {
                if (mount) {
                    mount.innerHTML = '<div class="card-body"><div class="message message-error" role="alert">' +
                        escapeHtml(err.message) + '</div></div>';
                }
            });
    }

    function saveEnabled(enabled) {
        return fetch(apiUrl('/api/developer.php?action=save'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ enabled: !!enabled, _csrf: getCsrf() })
        }).then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') {
                    throw new Error(payload.message || 'Failed to save');
                }
                setToolsVisible(!!enabled);
                toast(enabled ? 'Developer mode on' : 'Developer mode off', 'success');
                if (enabled) {
                    return loadSystemInfo();
                }
                return null;
            });
    }

    function exportTemplate() {
        var include = document.getElementById('developer-export-include-content');
        var includeContent = !!(include && include.checked);
        var btn = document.getElementById('developer-export-btn');
        if (btn) btn.disabled = true;

        return fetch(apiUrl('/api/developer.php?action=export_template'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                include_content: includeContent,
                _csrf: getCsrf()
            })
        }).then(function (response) {
            var type = (response.headers.get('Content-Type') || '').toLowerCase();
            if (!response.ok || type.indexOf('application/zip') === -1) {
                return response.json().then(function (payload) {
                    throw new Error((payload && payload.message) || 'Export failed');
                }).catch(function (err) {
                    if (err && err.message) throw err;
                    throw new Error('Export failed');
                });
            }
            return response.blob().then(function (blob) {
                var disposition = response.headers.get('Content-Disposition') || '';
                var match = /filename="([^"]+)"/.exec(disposition);
                var filename = match ? match[1] : 'birkly-template-export.zip';
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
                toast('Template pack downloaded', 'success');
            });
        }).catch(function (err) {
            toast(err.message || 'Export failed', 'error');
        }).finally(function () {
            if (btn) btn.disabled = false;
        });
    }

    function runSmoke() {
        var out = document.getElementById('developer-smoke-result');
        var btn = document.getElementById('developer-smoke-btn');
        if (btn) btn.disabled = true;
        return fetch(apiUrl('/api/developer.php?action=smoke'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') {
                    throw new Error(payload.message || 'Smoke failed');
                }
                if (out) {
                    out.hidden = false;
                    out.textContent = JSON.stringify(payload.data || {}, null, 2);
                }
                toast('Smoke checks complete', 'success');
            })
            .catch(function (err) {
                if (out) {
                    out.hidden = false;
                    out.textContent = err.message || 'Smoke failed';
                }
                toast(err.message || 'Smoke failed', 'error');
            })
            .finally(function () {
                if (btn) btn.disabled = false;
            });
    }

    function load() {
        var toggle = document.getElementById('developer-mode-toggle');
        if (!toggle) return Promise.resolve();

        return fetch(apiUrl('/api/developer.php?action=get'), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload.status !== 'ok') {
                    throw new Error(payload.message || 'Failed to load developer mode');
                }
                var enabled = !!(payload.data && payload.data.enabled);
                toggle.checked = enabled;
                setToolsVisible(enabled);
                if (enabled) {
                    return loadSystemInfo();
                }
                return null;
            })
            .catch(function (err) {
                toast(err.message || 'Failed to load developer mode', 'error');
            });
    }

    function init() {
        if (!document.getElementById('developer-mode-card')) return;

        if (!initialized) {
            initialized = true;
            var toggle = document.getElementById('developer-mode-toggle');
            if (toggle) {
                toggle.addEventListener('change', function () {
                    var next = !!toggle.checked;
                    saveEnabled(next).catch(function (err) {
                        toggle.checked = !next;
                        setToolsVisible(!next);
                        toast(err.message || 'Failed to save', 'error');
                    });
                });
            }
            var exportBtn = document.getElementById('developer-export-btn');
            if (exportBtn) exportBtn.addEventListener('click', exportTemplate);
            var smokeBtn = document.getElementById('developer-smoke-btn');
            if (smokeBtn) smokeBtn.addEventListener('click', runSmoke);
        }

        load();
    }

    window.initDeveloperMode = init;
    window.initDeveloperModeSettings = init;
})();
