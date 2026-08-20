/**
 * Delivery status strip — shared on Site delivery + Project Overview (P66-M1).
 */
(function (global) {
    'use strict';

    var instances = {};

    function apiUrl(path) {
        return (global.Birkly && typeof global.Birkly.url === 'function') ? global.Birkly.url(path) : path;
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function escapeHtml(text) {
        if (text == null) return '';
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function statusClass(overall) {
        if (overall === 'green') return 'delivery-status--green';
        if (overall === 'yellow') return 'delivery-status--yellow';
        return 'delivery-status--red';
    }

    function checkIcon(status) {
        if (status === 'pass') return '✓';
        if (status === 'warn') return '!';
        return '×';
    }

    function fixUrl(fix) {
        if (!fix || !fix.tab) return '#';
        var base = apiUrl('/admin/index.php?page=settings&tab=' + encodeURIComponent(fix.tab));
        return fix.hash ? base + '#' + fix.hash : base;
    }

    function runHostingAction(action, onDone) {
        return fetch(apiUrl('/api/hosting.php?action=' + encodeURIComponent(action)), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _csrf: getCsrfToken(), sync_domains: true })
        })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                if (global.BirklyFeedback && typeof global.BirklyFeedback.toast === 'function') {
                    global.BirklyFeedback.toast(result.message || 'Done', result.status === 'ok' ? 'success' : 'error');
                }
                if (typeof onDone === 'function') {
                    onDone(result);
                }
                return result;
            });
    }

    function fixConfirmMessage(action) {
        if (action === 'repair') {
            return 'Repair delivery: fix CMS/public URLs if needed, sync domain aliases, remove legacy demo-website/ and default demo HTML from project/, disable harmful /project redirects, fix project HTML paths, and clear SSR cache? A backup of hosting.json will be created when config changes.';
        }
        if (action === 'set_same_host_public') {
            return 'Set “Root / opens public site” on this domain? The site home page will be served at / with clean URLs and project HTML paths will be updated.';
        }
        if (action === 'fix_project_paths') {
            return 'Rewrite /project/ paths in project HTML and inject the client script?';
        }
        return 'Apply this fix?';
    }

    function renderFixControl(check) {
        if (!check.fix) return '';
        if (check.fix.action === 'fix_project_paths') {
            return ' <button type="button" class="btn btn-secondary btn-sm delivery-status-fix delivery-status-fix-action" data-fix-action="fix_project_paths">Fix paths</button>';
        }
        if (check.fix.action === 'repair') {
            return ' <button type="button" class="btn btn-secondary btn-sm delivery-status-fix delivery-status-fix-action" data-fix-action="repair">Fix</button>';
        }
        if (check.fix.action === 'set_same_host_public') {
            return ' <button type="button" class="btn btn-secondary btn-sm delivery-status-fix delivery-status-fix-action" data-fix-action="set_same_host_public">Fix</button>';
        }
        return ' <a class="delivery-status-fix" href="' + escapeHtml(fixUrl(check.fix)) + '">Fix</a>';
    }

    function renderStripHtml(id, overall, checks, meta) {
        var label = overall === 'green' ? 'Delivery healthy' : (overall === 'yellow' ? 'Delivery needs attention' : 'Delivery broken');
        var rows = (checks || []).map(function (check) {
            return '<li class="delivery-status-check delivery-status-check--' + escapeHtml(check.status) + '">' +
                '<span class="delivery-status-check-icon" aria-hidden="true">' + checkIcon(check.status) + '</span>' +
                '<span class="delivery-status-check-text">' + escapeHtml(check.message || check.code || check.id) + '</span>' +
                renderFixControl(check) +
                '</li>';
        }).join('');

        var metaBits = [];
        if (meta && meta.request_host) {
            metaBits.push('Host: ' + escapeHtml(meta.request_host) + ' (' + escapeHtml(meta.request_host_role || 'unknown') + ')');
        }
        if (meta && meta.schema_version) {
            metaBits.push('Schema v' + escapeHtml(String(meta.schema_version)));
        }
        if (meta && meta.env_managed) {
            metaBits.push('Env overrides active');
        }
        if (meta && meta.migration_pending) {
            metaBits.push('Migration pending');
        }

        return '' +
            '<section class="delivery-status card ' + statusClass(overall) + '" data-delivery-status-id="' + escapeHtml(id) + '" aria-label="Site delivery status">' +
            '<div class="delivery-status-header">' +
            '<div><h3 class="delivery-status-title">' + escapeHtml(label) + '</h3>' +
            (metaBits.length ? '<p class="delivery-status-meta">' + metaBits.join(' · ') + '</p>' : '') +
            '</div>' +
            '<div class="delivery-status-actions">' +
            '<button type="button" class="btn btn-secondary btn-sm delivery-status-refresh">Refresh</button>' +
            '<button type="button" class="btn btn-secondary btn-sm delivery-status-repair">Repair delivery</button>' +
            '</div></div>' +
            '<ul class="delivery-status-checks">' + rows + '</ul>' +
            '</section>';
    }

    function refreshHostingPanels() {
        if (typeof global.refreshHostingWizard === 'function') {
            global.refreshHostingWizard();
        }
        if (typeof global.refreshProjectSettingsHosting === 'function') {
            global.refreshProjectSettingsHosting();
        }
    }

    function mount(options) {
        var id = options.id || 'default';
        var mountEl = options.mount;
        if (!mountEl) return null;

        function load() {
            mountEl.innerHTML = '<div class="loading-state"><p>Checking delivery…</p></div>';
            fetch(apiUrl('/api/hosting.php?action=status'), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (payload) {
                    if (!payload || payload.status !== 'ok' || !payload.data) {
                        throw new Error('Failed to load delivery status');
                    }
                    var delivery = payload.data.delivery || {};
                    mountEl.innerHTML = renderStripHtml(id, delivery.overall || 'red', delivery.checks || [], delivery);
                    wireActions(mountEl, id);
                })
                .catch(function (err) {
                    mountEl.innerHTML = '<div class="message message-error">' + escapeHtml(err.message || 'Delivery status unavailable') + '</div>';
                });
        }

        function wireActions(root, instanceId) {
            var refreshBtn = root.querySelector('.delivery-status-refresh');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', load);
            }
            root.querySelectorAll('.delivery-status-fix-action').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var action = btn.getAttribute('data-fix-action') || 'fix_project_paths';
                    if (!window.confirm(fixConfirmMessage(action))) {
                        return;
                    }
                    btn.disabled = true;
                    runHostingAction(action, function (result) {
                        load();
                        btn.disabled = false;
                        if (action === 'repair' || action === 'set_same_host_public') {
                            refreshHostingPanels();
                        }
                    }).catch(function () {
                        btn.disabled = false;
                    });
                });
            });
            var repairBtn = root.querySelector('.delivery-status-repair');
            if (repairBtn) {
                repairBtn.addEventListener('click', function () {
                    if (!window.confirm(fixConfirmMessage('repair'))) {
                        return;
                    }
                    repairBtn.disabled = true;
                    runHostingAction('repair', function () {
                        load();
                        repairBtn.disabled = false;
                        refreshHostingPanels();
                    }).catch(function () {
                        repairBtn.disabled = false;
                    });
                });
            }
        }

        instances[id] = { load: load };
        load();
        return instances[id];
    }

    global.initDeliveryStatus = function (selector) {
        var nodes = document.querySelectorAll(selector || '[data-delivery-status-mount]');
        nodes.forEach(function (node, index) {
            mount({ id: node.getAttribute('data-delivery-status-id') || ('mount-' + index), mount: node });
        });
    };
})(window);
