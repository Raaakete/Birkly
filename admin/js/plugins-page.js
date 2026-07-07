/**
 * Plugins admin page — installed plugins + marketplace browse/install/update (P9-M6).
 */
(function () {
    'use strict';

    var installedPlugins = [];
    var updateMap = {};
    var marketplaceFilterOptions = { categories: [], offer_types: [] };
    var currentOfferSlug = null;
    var marketplaceBaseUrl = '';

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

    /** Sidebar nav is server-rendered; reload after plugin state changes (matches legacy Settings → Plugins). */
    function reloadAfterPluginChange(message, tab) {
        if (message) {
            showMessage(message, 'success');
        }
        setTimeout(function () {
            if (tab) {
                var pageUrl = new URL(window.location.href);
                pageUrl.searchParams.set('tab', tab);
                window.location.href = pageUrl.toString();
                return;
            }
            window.location.reload();
        }, 1000);
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

    function t(key, fallback) {
        var i18n = window.PluginsPageI18n || {};
        return i18n[key] || fallback || key;
    }

    function isFreeOffer(offer) {
        if (!offer || !offer.pricing) {
            return false;
        }
        if (offer.pricing.model === 'free') {
            return true;
        }
        return (offer.pricing.amount_cents || 0) === 0;
    }

    function offerTypeLabel(offerType) {
        if (offerType === 'theme') {
            return t('marketplace_type_theme', 'Theme');
        }
        if (offerType === 'template_pack') {
            return t('marketplace_type_template', 'Template pack');
        }
        return t('marketplace_type_plugin', 'Plugin');
    }

    function formatPrice(offer) {
        if (isFreeOffer(offer)) {
            return t('price_free', 'Free');
        }
        var pricing = offer.pricing || {};
        var amount = ((pricing.amount_cents || 0) / 100).toFixed(2) + ' ' + (pricing.currency || 'USD');
        if (pricing.model === 'subscription') {
            return amount + '/' + (pricing.subscription_interval || 'month');
        }
        return amount;
    }

    function marketplacePurchaseUrl(slug) {
        var base = marketplaceBaseUrl || 'https://marketplace.birkly.io';
        return base.replace(/\/$/, '') + '/buy/' + encodeURIComponent(slug || '');
    }

    function formatRating(summary) {
        if (!summary || !summary.count) {
            return t('marketplace_no_reviews', 'No reviews yet');
        }
        var avg = Number(summary.average || 0).toFixed(1);
        return '\u2605 ' + avg + ' (' + summary.count + ')';
    }

    function formatDownloads(count) {
        var n = Number(count || 0);
        if (n >= 1000000) {
            return (n / 1000000).toFixed(1) + 'M ' + t('marketplace_downloads', 'downloads');
        }
        if (n >= 1000) {
            return (n / 1000).toFixed(1) + 'k ' + t('marketplace_downloads', 'downloads');
        }
        return n + ' ' + t('marketplace_downloads', 'downloads');
    }

    function getOfferInstallState(offer) {
        if (offer && offer.install_state) {
            return offer.install_state;
        }
        return {
            installed: false,
            plugin_id: null,
            installed_version: null,
            latest_version: (offer && offer.latest_version) || '',
            update_available: false,
        };
    }

    function renderOfferActionButtons(offer, stopPropagation) {
        var state = getOfferInstallState(offer);
        var slug = offer.slug || '';
        var title = offer.title || slug;
        var version = offer.latest_version || '';
        var free = isFreeOffer(offer);
        var clickGuard = stopPropagation ? 'event.stopPropagation(); ' : '';

        if (state.installed) {
            if (state.update_available && state.plugin_id) {
                return (
                    '<button type="button" class="btn btn-sm btn-secondary" onclick="' + clickGuard +
                    'PluginsPage.upgradePlugin(\'' + escapeHtml(state.plugin_id) + '\')">' +
                    escapeHtml(t('update', 'Update')) + '</button>'
                );
            }
            return (
                '<button type="button" class="btn btn-sm btn-secondary" disabled>' +
                escapeHtml(t('installed', 'Installed')) + '</button>'
            );
        }

        if (free) {
            return (
                '<button type="button" class="btn btn-sm btn-primary" onclick="' + clickGuard +
                'PluginsPage.installFreePlugin(\'' + escapeHtml(slug) + '\', \'' + escapeHtml(version) + '\')">' +
                escapeHtml(t('install', 'Install')) + '</button>'
            );
        }

        return (
            '<a class="btn btn-sm btn-secondary" href="' + escapeHtml(marketplacePurchaseUrl(slug)) + '" target="_blank" rel="noopener noreferrer" onclick="' + clickGuard + '">' +
            escapeHtml(t('purchase', 'Purchase')) + '</a>' +
            '<button type="button" class="btn btn-sm btn-primary" onclick="' + clickGuard +
            'PluginsPage.openMarketplaceInstall(\'' + escapeHtml(slug) + '\', \'' +
            escapeHtml(title) + '\', \'' + escapeHtml(version) + '\', false)">' +
            escapeHtml(t('install_with_license', 'Install')) + '</button>'
        );
    }

    function renderOfferMetaRows(offer) {
        return (
            '<div class="marketplace-offer-meta">' +
            '<div class="marketplace-offer-meta-row">' +
            '<span class="marketplace-price">' + escapeHtml(formatPrice(offer)) + '</span>' +
            '<span class="marketplace-rating">' + escapeHtml(formatRating(offer.rating_summary)) + '</span>' +
            '</div>' +
            '<div class="marketplace-offer-meta-row">' +
            '<span class="marketplace-downloads">' + escapeHtml(formatDownloads(offer.download_count)) + '</span>' +
            (offer.latest_version
                ? '<span class="marketplace-version">v' + escapeHtml(offer.latest_version) + '</span>'
                : '<span class="marketplace-version"></span>') +
            '</div>' +
            '</div>'
        );
    }

    function offerImageHtml(offer) {
        var imgUrl = offer.icon_url || '';
        if (!imgUrl && Array.isArray(offer.screenshots) && offer.screenshots.length) {
            imgUrl = offer.screenshots[0];
        }
        if (imgUrl) {
            return '<img class="marketplace-offer-image" src="' + escapeHtml(imgUrl) + '" alt="" loading="lazy">';
        }
        var initial = (offer.title || offer.slug || '?').charAt(0).toUpperCase();
        var typeClass = 'placeholder-' + escapeHtml((offer.offer_type || 'plugin').replace(/[^a-z_]/g, ''));
        return '<div class="marketplace-offer-placeholder ' + typeClass + '" aria-hidden="true">' + escapeHtml(initial) + '</div>';
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
            grid.innerHTML =
                '<div class="empty-state plugins-empty-state">' +
                '<p>' + escapeHtml(t('no_plugins_installed', 'No plugins installed yet.')) + '</p>' +
                '<p class="form-help">' + escapeHtml(t('no_plugins_marketplace_hint', 'Browse the Marketplace tab to install official plugins like Commerce and Events — free for Birkly sites.')) + '</p>' +
                '<button type="button" class="btn btn-primary" onclick="PluginsPage.switchToMarketplace()">' +
                escapeHtml(t('browse_marketplace', 'Browse Marketplace')) +
                '</button>' +
                '</div>';
            return;
        }

        grid.innerHTML = installedPlugins.map(function (plugin) {
            var update = updateMap[plugin.id] || {};
            var hasUpdate = !!update.update_available;
            var active = !!plugin.active;
            var health = plugin.health || {};
            var healthStatus = health.status || 'unknown';
            var healthTitle = [].concat(health.errors || [], health.warnings || []).join('; ');
            var healthClass = 'health-' + healthStatus;
            return (
                '<div class="card plugin-card' + (hasUpdate ? ' has-update' : '') + '">' +
                '<div class="plugin-header">' +
                '<div>' +
                '<h3 class="plugin-name">' + escapeHtml(plugin.name) + '</h3>' +
                (hasUpdate ? '<span class="update-pill">' + escapeHtml('Update: v' + (update.latest_version || '')) + '</span>' : '') +
                '</div>' +
                '<div class="plugin-header-badges">' +
                '<span class="health-badge ' + healthClass + '" title="' + escapeHtml(healthTitle || healthStatus) + '">' +
                escapeHtml(healthStatus.charAt(0).toUpperCase() + healthStatus.slice(1)) +
                '</span>' +
                '<span class="status-badge ' + (active ? 'status-active' : 'status-inactive') + '">' +
                (active ? 'Active' : 'Inactive') +
                '</span>' +
                '</div>' +
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
                '<button type="button" class="btn btn-sm ' + (active ? 'btn-secondary' : 'btn-primary') + '" onclick="PluginsPage.togglePlugin(\'' + escapeHtml(plugin.id) + '\', ' + (!active) + ')">' +
                (active ? 'Deactivate' : 'Activate') +
                '</button>' +
                '<button type="button" class="btn btn-sm btn-danger" onclick="PluginsPage.deletePlugin(\'' + escapeHtml(plugin.id) + '\')">Delete</button>' +
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

    async function togglePlugin(pluginId, activate) {
        var action = activate ? 'activate' : 'deactivate';
        try {
            var result = await pluginsApi(action, { plugin_id: pluginId });
            reloadAfterPluginChange(result.message || 'Plugin ' + action + 'd successfully');
        } catch (err) {
            showMessage(err.message, 'error');
        }
    }

    async function deletePlugin(pluginId) {
        if (!confirm('Are you sure you want to delete this plugin? This action cannot be undone.')) {
            return;
        }
        try {
            var result = await pluginsApi('delete', { plugin_id: pluginId });
            reloadAfterPluginChange(result.message || 'Plugin deleted successfully');
        } catch (err) {
            showMessage(err.message, 'error');
        }
    }

    async function refreshUpdateBadge(silent) {
        try {
            var result = await marketplaceApi('check_updates');
            var updates = (result.data && result.data.updates) || {};
            updateMap = updates;
            var count = (result.data && result.data.update_count) || 0;
            setUpdateBadge(count);
            renderInstalledPlugins();
            return count;
        } catch (err) {
            if (!silent) {
                showMessage(err.message, 'error');
            }
            return 0;
        }
    }

    async function checkAllUpdates() {
        var count = await refreshUpdateBadge(false);
        if (count > 0) {
            showMessage(count + ' plugin update(s) available', 'info');
        } else {
            showMessage('All marketplace plugins are up to date', 'success');
        }
    }

    async function upgradePlugin(pluginId) {
        if (!confirm('Upgrade this plugin to the latest marketplace version?')) {
            return;
        }
        try {
            var result = await pluginsApi('update', { plugin_id: pluginId });
            reloadAfterPluginChange(result.message || 'Plugin upgraded successfully');
        } catch (err) {
            showMessage(err.message, 'error');
        }
    }

    function renderMarketplaceOffers(offers) {
        var grid = document.getElementById('marketplace-grid');
        var countEl = document.getElementById('marketplace-result-count');
        if (!grid) return;

        if (countEl) {
            if (offers.length) {
                countEl.textContent = t('marketplace_offers_count', '{count} offers').replace('{count}', String(offers.length));
                countEl.hidden = false;
            } else {
                countEl.hidden = true;
            }
        }

        if (!offers.length) {
            grid.innerHTML = '<div class="empty-state"><p>No offers found.</p></div>';
            return;
        }

        grid.innerHTML = offers.map(function (offer) {
            var slug = offer.slug || '';
            return (
                '<article class="marketplace-offer-card" role="button" tabindex="0" onclick="PluginsPage.openOfferDetail(\'' + escapeHtml(slug) + '\')" onkeydown="if(event.key===\'Enter\'){PluginsPage.openOfferDetail(\'' + escapeHtml(slug) + '\')}">' +
                '<div class="marketplace-offer-media">' + offerImageHtml(offer) + '</div>' +
                '<div class="marketplace-offer-body">' +
                '<div class="marketplace-offer-top">' +
                '<span class="marketplace-offer-type">' + escapeHtml(offerTypeLabel(offer.offer_type || 'plugin')) + '</span>' +
                '</div>' +
                '<h3 class="marketplace-offer-title">' +
                '<button type="button" class="marketplace-offer-title-link" onclick="event.stopPropagation(); PluginsPage.openOfferDetail(\'' + escapeHtml(slug) + '\')">' +
                escapeHtml(offer.title || slug) +
                '</button>' +
                '</h3>' +
                '<p class="marketplace-offer-summary">' + escapeHtml(offer.summary || '') + '</p>' +
                renderOfferMetaRows(offer) +
                '<div class="marketplace-offer-actions">' +
                renderOfferActionButtons(offer, true) +
                '</div>' +
                '</div>' +
                '</article>'
            );
        }).join('');
    }

    function setMarketplaceView(mode) {
        var browse = document.getElementById('marketplace-browse-view');
        var detail = document.getElementById('marketplace-offer-detail');
        if (browse) {
            browse.hidden = mode === 'detail';
        }
        if (detail) {
            detail.hidden = mode !== 'detail';
        }
    }

    function updateMarketplaceUrl(slug) {
        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }
        var pageUrl = new URL(window.location.href);
        pageUrl.searchParams.set('tab', 'marketplace');
        if (slug) {
            pageUrl.searchParams.set('offer', slug);
        } else {
            pageUrl.searchParams.delete('offer');
        }
        window.history.replaceState({}, '', pageUrl.pathname + pageUrl.search + pageUrl.hash);
    }

    function renderOfferDetailScreenshots(offer) {
        var shots = Array.isArray(offer.screenshots) ? offer.screenshots : [];
        if (!shots.length) {
            return '';
        }
        return (
            '<div class="marketplace-offer-detail-section">' +
            '<h3>Screenshots</h3>' +
            '<div class="marketplace-screenshots">' +
            shots.map(function (src) {
                return '<img src="' + escapeHtml(src) + '" alt="" loading="lazy">';
            }).join('') +
            '</div></div>'
        );
    }

    function renderOfferDetailReleases(releases) {
        if (!releases || !releases.length) {
            return '';
        }
        var rows = releases.map(function (release) {
            var version = release.version || '';
            var changelog = release.changelog || '';
            var published = (release.published_at || '').substring(0, 10);
            return (
                '<tr>' +
                '<td>v' + escapeHtml(version) + '</td>' +
                '<td>' + escapeHtml(published) + '</td>' +
                '<td>' + escapeHtml(changelog) + '</td>' +
                '</tr>'
            );
        }).join('');

        return (
            '<div class="marketplace-offer-detail-section">' +
            '<h3>' + escapeHtml(t('releases', 'Releases')) + '</h3>' +
            '<table class="marketplace-release-table">' +
            '<thead><tr><th>Version</th><th>Published</th><th>' + escapeHtml(t('changelog', 'Changelog')) + '</th></tr></thead>' +
            '<tbody>' + rows + '</tbody></table></div>'
        );
    }

    function renderMarketplaceOfferDetail(offer, releases) {
        var slug = offer.slug || '';
        var compatibility = offer.compatibility || {};
        var description = offer.description || offer.summary || t('no_description', 'No detailed description provided yet.');
        var providerId = offer.provider_id || '';

        return (
            '<div class="marketplace-offer-detail-inner">' +
            '<button type="button" class="btn btn-secondary btn-sm" onclick="PluginsPage.backToMarketplaceBrowse()">' +
            escapeHtml(t('back_to_marketplace', 'Back to marketplace')) +
            '</button>' +
            '<div class="marketplace-offer-detail-header">' +
            '<div>' +
            '<div class="marketplace-offer-detail-media">' + offerImageHtml(offer) + '</div>' +
            '<span class="marketplace-offer-type">' + escapeHtml(offerTypeLabel(offer.offer_type || 'plugin')) + '</span>' +
            '<h2 class="marketplace-offer-title">' + escapeHtml(offer.title || slug) + '</h2>' +
            '<p class="marketplace-offer-summary">' + escapeHtml(offer.summary || '') + '</p>' +
            renderOfferMetaRows(offer) +
            '</div>' +
            '<aside class="marketplace-offer-detail-sidebar">' +
            '<p class="marketplace-offer-detail-price">' + escapeHtml(formatPrice(offer)) + '</p>' +
            '<div class="marketplace-offer-actions">' + renderOfferActionButtons(offer, false) + '</div>' +
            '</aside>' +
            '</div>' +
            '<div class="marketplace-offer-detail-section">' +
            '<h3>' + escapeHtml(t('description', 'Description')) + '</h3>' +
            '<div class="marketplace-offer-description">' + escapeHtml(description) + '</div>' +
            '</div>' +
            renderOfferDetailScreenshots(offer) +
            '<div class="marketplace-offer-detail-section">' +
            '<h3>' + escapeHtml(t('compatibility', 'Compatibility')) + '</h3>' +
            '<dl class="marketplace-detail-meta-list">' +
            (compatibility.requires_birkly
                ? '<div><dt>' + escapeHtml(t('requires_birkly', 'Requires Birkly')) + '</dt><dd>' + escapeHtml(compatibility.requires_birkly) + '</dd></div>'
                : '') +
            (compatibility.requires_php
                ? '<div><dt>' + escapeHtml(t('requires_php', 'Requires PHP')) + '</dt><dd>' + escapeHtml(compatibility.requires_php) + '</dd></div>'
                : '') +
            '</dl></div>' +
            (providerId
                ? '<div class="marketplace-offer-detail-section"><h3>' + escapeHtml(t('provider', 'Provider')) + '</h3><p>' + escapeHtml(providerId) + '</p></div>'
                : '') +
            renderOfferDetailReleases(releases) +
            '</div>'
        );
    }

    async function openOfferDetail(slug) {
        if (!slug) {
            return;
        }
        currentOfferSlug = slug;
        setMarketplaceView('detail');
        updateMarketplaceUrl(slug);

        var detailEl = document.getElementById('marketplace-offer-detail');
        if (!detailEl) {
            return;
        }
        detailEl.innerHTML = '<div class="loading-state"><p>' + escapeHtml(t('loading_marketplace', 'Loading marketplace...')) + '</p></div>';

        var titleEl = document.getElementById('plugins-page-title');
        if (titleEl) {
            titleEl.textContent = slug;
        }

        try {
            var response = await fetch(url('/api/marketplace.php?action=offer&slug=' + encodeURIComponent(slug)), {
                credentials: 'same-origin',
            });
            var result = await response.json();
            if (result.status !== 'ok' || !result.data || !result.data.offer) {
                throw new Error(result.message || 'Offer not found');
            }
            var offer = result.data.offer;
            if (titleEl) {
                titleEl.textContent = offer.title || slug;
            }
            detailEl.innerHTML = renderMarketplaceOfferDetail(offer, result.data.releases || []);
        } catch (err) {
            detailEl.innerHTML = '<div class="error-state"><p>' + escapeHtml(err.message) + '</p>' +
                '<button type="button" class="btn btn-secondary" onclick="PluginsPage.backToMarketplaceBrowse()">' +
                escapeHtml(t('back_to_marketplace', 'Back to marketplace')) + '</button></div>';
        }
    }

    function backToMarketplaceBrowse() {
        currentOfferSlug = null;
        setMarketplaceView('browse');
        updateMarketplaceUrl('');
        updatePageTitle('marketplace');
        loadMarketplace();
    }

    function updateCategoryFilterOptions(categories) {
        var select = document.getElementById('marketplace-filter-category');
        if (!select) return;
        var current = select.value;
        var html = '<option value="">' + escapeHtml(t('marketplace_filter_all_categories', 'All categories')) + '</option>';
        (categories || []).forEach(function (cat) {
            html += '<option value="' + escapeHtml(cat) + '"' + (current === cat ? ' selected' : '') + '>' + escapeHtml(cat) + '</option>';
        });
        select.innerHTML = html;
    }

    function getMarketplaceBrowseParams() {
        var qEl = document.getElementById('marketplace-search');
        var typeEl = document.getElementById('marketplace-filter-type');
        var catEl = document.getElementById('marketplace-filter-category');
        var sortEl = document.getElementById('marketplace-sort');
        return {
            q: qEl ? qEl.value : '',
            offer_type: typeEl ? typeEl.value : '',
            category: catEl ? catEl.value : '',
            sort: sortEl ? sortEl.value : 'name',
        };
    }

    async function loadMarketplace() {
        setMarketplaceView('browse');
        var grid = document.getElementById('marketplace-grid');
        if (grid) {
            grid.innerHTML = '<div class="loading-state"><p>' + escapeHtml(t('loading_marketplace', 'Loading marketplace...')) + '</p></div>';
        }

        var params = getMarketplaceBrowseParams();
        var query = new URLSearchParams();
        query.set('action', 'browse');
        if (params.q) query.set('q', params.q);
        if (params.category) query.set('category', params.category);
        if (params.offer_type) query.set('offer_type', params.offer_type);
        if (params.sort) query.set('sort', params.sort);

        try {
            var browse = await fetch(url('/api/marketplace.php?' + query.toString()), {
                credentials: 'same-origin',
            });
            var result = await browse.json();
            if (result.status !== 'ok') {
                throw new Error(result.message || 'Browse failed');
            }

            marketplaceBaseUrl = (result.data && result.data.marketplace_url) || marketplaceBaseUrl;

            var hint = document.getElementById('marketplace-url-hint');
            if (hint) {
                var hintText = 'Registry: ' + ((result.data && result.data.marketplace_url) || '');
                if (result.data && result.data.installation_owner_email) {
                    hintText += ' · Owner: ' + result.data.installation_owner_email;
                }
                if (result.data && result.data.fallback) {
                    hintText += ' (showing bundled catalog — registry unreachable)';
                }
                hint.textContent = hintText;
            }

            marketplaceFilterOptions = (result.data && result.data.filter_options) || marketplaceFilterOptions;
            updateCategoryFilterOptions(marketplaceFilterOptions.categories || []);
            renderMarketplaceOffers((result.data && result.data.offers) || []);
        } catch (err) {
            if (grid) {
                grid.innerHTML = '<div class="error-state"><p>' + escapeHtml(err.message) + '</p></div>';
            }
        }
    }

    function searchMarketplace() {
        loadMarketplace();
    }

    function updatePageTitle(tab) {
        var el = document.getElementById('plugins-page-title');
        var titles = window.PluginsPageTitles || {};
        if (!el) {
            return;
        }
        if (tab === 'installed' && titles.installed) {
            el.textContent = titles.installed;
        } else if (titles.marketplace) {
            el.textContent = titles.marketplace;
        }
    }

    function getInitialTab() {
        if (window.PluginsPageInitialTab === 'marketplace' || window.PluginsPageInitialTab === 'installed') {
            return window.PluginsPageInitialTab;
        }
        var params = new URLSearchParams(window.location.search);
        var tab = params.get('tab');
        if (tab === 'marketplace' || tab === 'installed') {
            return tab;
        }
        return 'marketplace';
    }

    function getInitialOfferSlug() {
        var params = new URLSearchParams(window.location.search);
        return params.get('offer') || '';
    }

    function switchTab(tab) {
        updatePageTitle(tab);
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
            var offerSlug = getInitialOfferSlug();
            if (offerSlug) {
                openOfferDetail(offerSlug);
            } else {
                setMarketplaceView('browse');
                loadMarketplace();
            }
        } else if (tab === 'installed') {
            currentOfferSlug = null;
            setMarketplaceView('browse');
            loadInstalled()
                .then(function () { return refreshUpdateBadge(true); })
                .catch(function (err) { showMessage(err.message, 'error'); });
        }
        if (window.history && typeof window.history.replaceState === 'function') {
            var pageUrl = new URL(window.location.href);
            if (tab === 'installed') {
                pageUrl.searchParams.delete('tab');
                pageUrl.searchParams.delete('offer');
            } else {
                pageUrl.searchParams.set('tab', tab);
            }
            window.history.replaceState({}, '', pageUrl.pathname + pageUrl.search + pageUrl.hash);
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

    function openMarketplaceInstall(slug, title, version, isFree) {
        var free = isFree === true || isFree === 'true';
        document.getElementById('marketplace-install-slug').value = slug;
        document.getElementById('marketplace-install-version').value = version || '';
        document.getElementById('marketplace-install-title').textContent =
            t('install_from_marketplace', 'Install from Marketplace') + ': ' + title;
        document.getElementById('marketplace-install-summary').textContent =
            version ? 'Latest version: v' + version : 'Latest release will be installed.';
        document.getElementById('marketplace-license-key').value = '';

        var licenseGroup = document.getElementById('marketplace-license-group');
        if (licenseGroup) {
            licenseGroup.hidden = free;
        }

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
        var licenseGroup = document.getElementById('marketplace-license-group');
        var licenseKey = document.getElementById('marketplace-license-key').value.trim();
        if (licenseGroup && !licenseGroup.hidden && !licenseKey) {
            showMessage(t('enter_license_key', 'Enter your license key'), 'error');
            return;
        }
        await runMarketplaceInstall(slug, version, licenseKey);
    }

    async function installFreePlugin(slug, version) {
        if (!confirm(t('confirm_free_install', 'Install this free plugin now?'))) {
            return;
        }
        await runMarketplaceInstall(slug, version, '');
    }

    async function runMarketplaceInstall(slug, version, licenseKey) {
        var btn = document.getElementById('marketplace-install-btn');
        if (btn) btn.disabled = true;
        try {
            var payload = { slug: slug, version: version };
            if (licenseKey) {
                payload.license_key = licenseKey;
            }
            await marketplaceApi('install', payload);
            closeMarketplaceInstallModal();
            reloadAfterPluginChange(t('install_success', 'Plugin installed successfully'), 'installed');
        } catch (err) {
            showMessage(err.message, 'error');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    function switchToMarketplace() {
        switchTab('marketplace');
    }

    function installFromFile() {
        showMessage(t('manual_install_unavailable', 'Manual zip install is not available yet. Use the Marketplace tab with your license key.'), 'info');
    }

    function initMarketplaceFilters() {
        ['marketplace-filter-type', 'marketplace-filter-category', 'marketplace-sort'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', function () {
                    loadMarketplace();
                });
            }
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

    function initTabs() {
        document.querySelectorAll('.plugins-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                switchTab(btn.getAttribute('data-tab'));
            });
        });
        initMarketplaceFilters();
    }

    async function init() {
        initTabs();
        var tab = getInitialTab();
        switchTab(tab);
        try {
            if (tab === 'installed') {
                await loadInstalled();
                await refreshUpdateBadge(true);
            }
        } catch (err) {
            showMessage(err.message, 'error');
        }
    }

    window.PluginsPage = {
        checkAllUpdates: checkAllUpdates,
        upgradePlugin: upgradePlugin,
        togglePlugin: togglePlugin,
        deletePlugin: deletePlugin,
        openInstallModal: openInstallModal,
        closeInstallModal: closeInstallModal,
        openMarketplaceInstall: openMarketplaceInstall,
        closeMarketplaceInstallModal: closeMarketplaceInstallModal,
        installFromMarketplace: installFromMarketplace,
        installFreePlugin: installFreePlugin,
        installFromFile: installFromFile,
        searchMarketplace: searchMarketplace,
        loadMarketplace: loadMarketplace,
        switchToMarketplace: switchToMarketplace,
        switchTab: switchTab,
        openOfferDetail: openOfferDetail,
        backToMarketplaceBrowse: backToMarketplaceBrowse,
    };

    window.togglePlugin = togglePlugin;
    window.deletePlugin = deletePlugin;
    window.openInstallPluginModal = openInstallModal;
    window.closeInstallPluginModal = closeInstallModal;
    window.installPlugin = installFromFile;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
