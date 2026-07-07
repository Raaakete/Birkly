/**
 * Plugins admin page — installed plugins + marketplace browse/install/update (P9-M6).
 */
(function () {
    'use strict';

    var installedPlugins = [];
    var updateMap = {};

    function url(path) {
        return window.Birkly && typeof window.Birkly.url === 'function'
            ? window.Birkly.url(path)
            : path;
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function showMessage(message, type) {
        if (typeof window.showMessage === 'function') {
            window.showMessage(message, type);
            return;
        }
        alert(message);
    }

    async function marketplaceApi(action, extra) {
        var response = await fetch(url('/api/marketplace.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ action: action, _csrf: csrfToken() }, extra || {})),
        });
        var result = await response.json();
        if (!response.ok || result.status === 'error') {
            throw new Error(result.message || 'Request failed');
        }
        return result;
    }

    async function pluginsApi(action, extra) {
        var response = await fetch(url('/api/plugins.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ action: action, _csrf: csrfToken() }, extra || {})),
        });
        var result = await response.json();
        if (!response.ok || result.status === 'error') {
            throw new Error(result.message || 'Request failed');
        }
        return result;
    }

    function setUpdateBadge(count) {
        var badge = document.getElementById('plugins-update-badge');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = String(count);
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    function renderInstalledPlugins() {
        var grid = document.getElementById('plugins-grid');
        if (!grid) return;

        if (!installedPlugins.length) {
            grid.innerHTML = '<div class="empty-state"><p>No plugins installed yet.</p></div>';
            return;
        }

        grid.innerHTML = installedPlugins.map(function (plugin) {
            var update = updateMap[plugin.id] || {};
            var hasUpdate = !!update.update_available;
            var active = !!plugin.active;
            return (
                '<div class="card plugin-card' + (hasUpdate ? ' has-update' : '') + '">' +
                '<div class="plugin-header">' +
                '<div>' +
                '<h3 class="plugin-name">' + escapeHtml(plugin.name) + '</h3>' +
                (hasUpdate ? '<span class="update-pill">' + escapeHtml('Update: v' + (update.latest_version || '')) + '</span>' : '') +
                '</div>' +
                '<span class="status-badge ' + (active ? 'status-active' : 'status-inactive') + '">' +
                (active ? 'Active' : 'Inactive') +
                '</span>' +
                '</div>' +
                (plugin.description ? '<p class="plugin-description">' + escapeHtml(plugin.description) + '</p>' : '') +
                '<div class="plugin-meta">' +
                '<span class="plugin-version">v' + escapeHtml(plugin.version || '1.0.0') + '</span>' +
                (plugin.from_marketplace ? '<span class="marketplace-tag">Marketplace</span>' : '') +
                (plugin.license_ref ? '<span class="license-ref" title="License ref">#' + escapeHtml(plugin.license_ref) + '</span>' : '') +
                '</div>' +
                '<div class="plugin-actions">' +
                (hasUpdate
                    ? '<button type="button" class="btn btn-sm btn-primary" onclick="PluginsPage.upgradePlugin(\'' + escapeHtml(plugin.id) + '\')">Upgrade</button>'
                    : '') +
                '<button type="button" class="btn btn-sm ' + (active ? 'btn-secondary' : 'btn-primary') + '" onclick="togglePlugin(\'' + escapeHtml(plugin.id) + '\', ' + (!active) + ')">' +
                (active ? 'Deactivate' : 'Activate') +
                '</button>' +
                '<button type="button" class="btn btn-sm btn-danger" onclick="deletePlugin(\'' + escapeHtml(plugin.id) + '\')">Delete</button>' +
                '</div>' +
                '</div>'
            );
        }).join('');
    }

    async function loadInstalled() {
        var grid = document.getElementById('plugins-grid');
        if (grid) {
            grid.innerHTML = '<div class="loading-state"><p>Loading plugins...</p></div>';
        }

        var response = await fetch(url('/api/plugins.php?action=list'), { credentials: 'same-origin' });
        var result = await response.json();
        if (result.status !== 'ok') {
            throw new Error(result.message || 'Failed to load plugins');
        }
        installedPlugins = (result.data && result.data.plugins) || [];
        renderInstalledPlugins();
    }

    async function checkAllUpdates() {
        try {
            var result = await marketplaceApi('check_updates');
            var updates = (result.data && result.data.updates) || {};
            updateMap = updates;
            var count = (result.data && result.data.update_count) || 0;
            setUpdateBadge(count);
            renderInstalledPlugins();
            if (count > 0) {
                showMessage(count + ' plugin update(s) available', 'info');
            } else {
                showMessage('All marketplace plugins are up to date', 'success');
            }
        } catch (err) {
            showMessage(err.message, 'error');
        }
    }

    async function upgradePlugin(pluginId) {
        if (!confirm('Upgrade this plugin to the latest marketplace version?')) {
            return;
        }
        try {
            await pluginsApi('update', { plugin_id: pluginId });
            showMessage('Plugin upgraded successfully', 'success');
            await loadInstalled();
            await checkAllUpdates();
        } catch (err) {
            showMessage(err.message, 'error');
        }
    }

    function renderMarketplaceOffers(offers) {
        var grid = document.getElementById('marketplace-grid');
        if (!grid) return;

        if (!offers.length) {
            grid.innerHTML = '<div class="empty-state"><p>No offers found.</p></div>';
            return;
        }

        grid.innerHTML = offers.map(function (offer) {
            var price = 'Free';
            if (offer.pricing && offer.pricing.model !== 'free' && offer.pricing.amount_cents > 0) {
                price = ((offer.pricing.amount_cents || 0) / 100).toFixed(2) + ' ' + (offer.pricing.currency || 'USD');
            }
            return (
                '<div class="card marketplace-offer-card">' +
                '<div class="plugin-header">' +
                '<h3 class="plugin-name">' + escapeHtml(offer.title || offer.slug) + '</h3>' +
                '<span class="trust-badge">' + escapeHtml(offer.trust_level || 'untrusted') + '</span>' +
                '</div>' +
                '<p class="plugin-description">' + escapeHtml(offer.summary || '') + '</p>' +
                '<div class="plugin-meta">' +
                '<span class="plugin-version">' + escapeHtml(offer.category || '') + '</span>' +
                (offer.latest_version ? '<span class="plugin-version">v' + escapeHtml(offer.latest_version) + '</span>' : '') +
                '<span class="marketplace-price">' + escapeHtml(price) + '</span>' +
                '</div>' +
                '<div class="plugin-actions">' +
                '<button type="button" class="btn btn-sm btn-primary" onclick="PluginsPage.openMarketplaceInstall(\'' +
                escapeHtml(offer.slug) + '\', \'' + escapeHtml(offer.title || offer.slug) + '\', \'' +
                escapeHtml(offer.latest_version || '') + '\')">Install with license</button>' +
                '</div>' +
                '</div>'
            );
        }).join('');
    }

    async function loadMarketplace(query) {
        var grid = document.getElementById('marketplace-grid');
        if (grid) {
            grid.innerHTML = '<div class="loading-state"><p>Loading marketplace...</p></div>';
        }

        try {
            var config = await marketplaceApi('config');
            var hint = document.getElementById('marketplace-url-hint');
            if (hint && config.data) {
                hint.textContent = 'Registry: ' + (config.data.marketplace_url || '');
            }

            var browse = await fetch(url('/api/marketplace.php?action=browse&q=' + encodeURIComponent(query || '') + '&offer_type=plugin'), {
                credentials: 'same-origin',
            });
            var result = await browse.json();
            if (result.status !== 'ok') {
                throw new Error(result.message || 'Browse failed');
            }
            renderMarketplaceOffers((result.data && result.data.offers) || []);
        } catch (err) {
            if (grid) {
                grid.innerHTML = '<div class="error-state"><p>' + escapeHtml(err.message) + '</p></div>';
            }
        }
    }

    function searchMarketplace() {
        var q = document.getElementById('marketplace-search');
        loadMarketplace(q ? q.value : '');
    }

    function switchTab(tab) {
        document.querySelectorAll('.plugins-tab').forEach(function (btn) {
            var active = btn.getAttribute('data-tab') === tab;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('.plugins-tab-panel').forEach(function (panel) {
            var isInstalled = panel.id === 'plugins-tab-installed';
            var show = (tab === 'installed' && isInstalled) || (tab === 'marketplace' && !isInstalled);
            panel.classList.toggle('active', show);
            panel.hidden = !show;
        });
        if (tab === 'marketplace') {
            loadMarketplace('');
        }
    }

    function openInstallModal() {
        var modal = document.getElementById('install-plugin-modal');
        if (modal) modal.classList.add('active');
    }

    function closeInstallModal() {
        var modal = document.getElementById('install-plugin-modal');
        if (modal) modal.classList.remove('active');
    }

    function openMarketplaceInstall(slug, title, version) {
        document.getElementById('marketplace-install-slug').value = slug;
        document.getElementById('marketplace-install-version').value = version || '';
        document.getElementById('marketplace-install-title').textContent = 'Install: ' + title;
        document.getElementById('marketplace-install-summary').textContent =
            version ? 'Latest version: v' + version : 'Latest release will be installed.';
        document.getElementById('marketplace-license-key').value = '';
        var modal = document.getElementById('marketplace-install-modal');
        if (modal) modal.classList.add('active');
    }

    function closeMarketplaceInstallModal() {
        var modal = document.getElementById('marketplace-install-modal');
        if (modal) modal.classList.remove('active');
    }

    async function installFromMarketplace() {
        var slug = document.getElementById('marketplace-install-slug').value;
        var version = document.getElementById('marketplace-install-version').value;
        var licenseKey = document.getElementById('marketplace-license-key').value.trim();
        if (!licenseKey) {
            showMessage('Enter your license key', 'error');
            return;
        }
        var btn = document.getElementById('marketplace-install-btn');
        if (btn) btn.disabled = true;
        try {
            await marketplaceApi('install', {
                slug: slug,
                version: version,
                license_key: licenseKey,
            });
            closeMarketplaceInstallModal();
            showMessage('Plugin installed successfully', 'success');
            switchTab('installed');
            await loadInstalled();
            await checkAllUpdates();
        } catch (err) {
            showMessage(err.message, 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    function installFromFile() {
        showMessage('Manual zip install is not available yet. Use the Marketplace tab with your license key.', 'info');
    }

    function initTabs() {
        document.querySelectorAll('.plugins-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchTab(btn.getAttribute('data-tab'));
            });
        });
        var search = document.getElementById('marketplace-search');
        if (search) {
            search.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchMarketplace();
                }
            });
        }
    }

    async function init() {
        initTabs();
        try {
            await loadInstalled();
            await checkAllUpdates();
        } catch (err) {
            showMessage(err.message, 'error');
        }
    }

    window.PluginsPage = {
        checkAllUpdates: checkAllUpdates,
        upgradePlugin: upgradePlugin,
        openInstallModal: openInstallModal,
        closeInstallModal: closeInstallModal,
        openMarketplaceInstall: openMarketplaceInstall,
        closeMarketplaceInstallModal: closeMarketplaceInstallModal,
        installFromMarketplace: installFromMarketplace,
        installFromFile: installFromFile,
        searchMarketplace: searchMarketplace,
        loadMarketplace: loadMarketplace,
    };

    window.openInstallPluginModal = openInstallModal;
    window.closeInstallPluginModal = closeInstallModal;
    window.installPlugin = installFromFile;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
