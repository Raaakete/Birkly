/**
 * Plugins admin page — installed plugins + marketplace browse/install/update (P9-M6).
 */
(function () {
    'use strict';

    var installedPlugins = [];
    var updateMap = {};
    var marketplaceFilterOptions = { categories: [], offer_types: [] };
    var currentOfferSlug = null;
    var currentOfferProvider = null;
    var currentOfferTitle = '';
    var embedModalView = 'offer';
    var marketplaceBaseUrl = '';
    var marketplaceOffersCache = [];
    var reviewStatusMap = {};
    var pendingInstallContext = null;

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
        BirklyFeedback.toast(message, type || 'error');
    }

    function renderPluginSkeletonCards(count) {
        var n = count || 6;
        var cards = '';
        for (var i = 0; i < n; i++) {
            cards +=
                '<div class="plugin-card plugin-card--skeleton" aria-hidden="true">' +
                '<div class="plugin-skeleton-header">' +
                '<div class="plugin-skeleton-line plugin-skeleton-line--title"></div>' +
                '<div class="plugin-skeleton-pill"></div>' +
                '</div>' +
                '<div class="plugin-skeleton-line plugin-skeleton-line--body"></div>' +
                '<div class="plugin-skeleton-line plugin-skeleton-line--short"></div>' +
                '<div class="plugin-skeleton-actions">' +
                '<div class="plugin-skeleton-pill plugin-skeleton-pill--btn"></div>' +
                '<div class="plugin-skeleton-pill plugin-skeleton-pill--btn"></div>' +
                '</div>' +
                '</div>';
        }
        return cards;
    }

    function renderMarketplaceSkeletonCards(count) {
        var n = count || 8;
        var cards = '';
        for (var i = 0; i < n; i++) {
            cards +=
                '<div class="marketplace-card marketplace-card--skeleton" aria-hidden="true">' +
                '<div class="plugin-skeleton-line plugin-skeleton-line--title"></div>' +
                '<div class="plugin-skeleton-line plugin-skeleton-line--body"></div>' +
                '<div class="plugin-skeleton-line plugin-skeleton-line--short"></div>' +
                '</div>';
        }
        return cards;
    }

    function handlePluginAction(el) {
        var action = el.getAttribute('data-plugin-action');
        if (action === 'toggle') {
            togglePlugin(el.getAttribute('data-plugin-id'), el.getAttribute('data-plugin-activate') === 'true');
        } else if (action === 'delete') {
            deletePlugin(el.getAttribute('data-plugin-id'));
        } else if (action === 'upgrade') {
            upgradePlugin(el.getAttribute('data-plugin-id'));
        } else if (action === 'switch-marketplace') {
            switchToMarketplace();
        } else if (action === 'back-marketplace') {
            backToMarketplaceBrowse();
        } else if (action === 'open-offer') {
            openOfferDetail(el.getAttribute('data-offer-slug'));
        } else if (action === 'install-free') {
            installFreePlugin(el.getAttribute('data-offer-slug'), el.getAttribute('data-offer-version'));
        } else if (action === 'open-install') {
            openMarketplaceInstall(
                el.getAttribute('data-offer-slug'),
                el.getAttribute('data-offer-title'),
                el.getAttribute('data-offer-version'),
                el.getAttribute('data-offer-free') === 'true'
            );
        } else if (action === 'search-marketplace') {
            searchMarketplace();
        } else if (action === 'check-updates') {
            checkAllUpdates();
        } else if (action === 'open-install-modal') {
            openInstallModal();
        } else if (action === 'close-install-modal') {
            closeInstallModal();
        } else if (action === 'install-from-file') {
            installFromFile();
        } else if (action === 'close-offer-embed') {
            closeOfferEmbedModal();
        } else if (action === 'view-provider-profile') {
            openProviderInEmbedModal(el.getAttribute('data-provider-id'));
        } else if (action === 'back-offer-detail') {
            backToOfferInEmbedModal();
        } else if (action === 'close-marketplace-install') {
            closeMarketplaceInstallModal();
        } else if (action === 'install-marketplace') {
            installFromMarketplace();
        } else if (action === 'open-review') {
            openReviewModal(el.getAttribute('data-plugin-id'), el.getAttribute('data-offer-slug'));
        } else if (action === 'close-review-modal') {
            closeReviewModal();
        } else if (action === 'submit-review') {
            submitPluginReview();
        } else if (action === 'close-install-consent') {
            closeInstallConsentModal();
        } else if (action === 'accept-install-consent') {
            acceptInstallConsent();
        }
    }

    /**
     * Modals live outside `.plugins-page`; bind document-level handlers (P35 hotfix).
     */
    function bindModalDelegatedEvents() {
        if (document.body.dataset.pluginModalsBound === '1') {
            return;
        }
        document.body.dataset.pluginModalsBound = '1';

        document.addEventListener('click', async function (e) {
            var el = e.target.closest('[data-plugin-action]');
            if (el) {
                var action = el.getAttribute('data-plugin-action');
                if (
                    action === 'close-offer-embed' ||
                    action === 'view-provider-profile' ||
                    action === 'back-offer-detail' ||
                    action === 'close-install-modal' ||
                    action === 'close-marketplace-install' ||
                    action === 'open-install-modal' ||
                    action === 'install-from-file' ||
                    action === 'install-marketplace' ||
                    action === 'close-review-modal' ||
                    action === 'submit-review' ||
                    action === 'close-install-consent' ||
                    action === 'accept-install-consent'
                ) {
                    handlePluginAction(el);
                }
                return;
            }

            if (e.target.id === 'marketplace-offer-embed-modal' && e.target.classList.contains('active')) {
                closeOfferEmbedModal();
            } else if (e.target.id === 'install-plugin-modal' && e.target.classList.contains('active')) {
                closeInstallModal();
            } else if (e.target.id === 'marketplace-install-modal' && e.target.classList.contains('active')) {
                closeMarketplaceInstallModal();
            } else if (e.target.id === 'marketplace-consent-modal' && e.target.classList.contains('active')) {
                closeInstallConsentModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') {
                return;
            }
            var embedModal = document.getElementById('marketplace-offer-embed-modal');
            if (embedModal && embedModal.classList.contains('active')) {
                if (embedModalView === 'provider') {
                    backToOfferInEmbedModal();
                } else {
                    closeOfferEmbedModal();
                }
                return;
            }
            var installModal = document.getElementById('install-plugin-modal');
            if (installModal && installModal.classList.contains('active')) {
                closeInstallModal();
                return;
            }
            var marketplaceInstallModal = document.getElementById('marketplace-install-modal');
            if (marketplaceInstallModal && marketplaceInstallModal.classList.contains('active')) {
                closeMarketplaceInstallModal();
                return;
            }
            var consentModal = document.getElementById('marketplace-consent-modal');
            if (consentModal && consentModal.classList.contains('active')) {
                closeInstallConsentModal();
            }
        });
    }

    /**
     * CSP nonce blocks inline onclick — use delegated listeners (P16-M5 / plugin activate fix).
     */
    function bindDelegatedEvents() {
        var root = document.querySelector('.plugins-page');
        if (!root || root.dataset.delegationBound === '1') {
            return;
        }
        root.dataset.delegationBound = '1';

        root.addEventListener('click', async function (e) {
            var el = e.target.closest('[data-plugin-action]');
            if (el) {
                handlePluginAction(el);
                return;
            }

            var card = e.target.closest('.marketplace-offer-card[data-offer-slug]');
            if (card) {
                openOfferDetail(card.getAttribute('data-offer-slug'));
            }
        });

        root.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') {
                return;
            }
            var card = e.target.closest('.marketplace-offer-card[data-offer-slug]');
            if (card && !e.target.closest('[data-plugin-action]')) {
                e.preventDefault();
                openOfferDetail(card.getAttribute('data-offer-slug'));
            }
        });
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

    async function parseMarketplaceResponse(response) {
        var body = await response.text();
        if (!body || !body.trim()) {
            throw new Error(t('marketplace_empty_response', 'Marketplace request failed (empty server response). Try again or check server logs.'));
        }
        try {
            return JSON.parse(body);
        } catch (parseErr) {
            throw new Error(t('marketplace_invalid_response', 'Marketplace request failed (invalid server response). Try again or check server logs.'));
        }
    }

    async function marketplaceApi(action, extra) {
        var response = await fetch(url('/api/marketplace.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ action: action, _csrf: csrfToken() }, extra || {})),
        });
        var result = await parseMarketplaceResponse(response);
        if (!response.ok || result.status === 'error') {
            var err = new Error(result.message || 'Request failed');
            err.code = result.code || '';
            err.data = result.data || null;
            throw err;
        }
        return result;
    }

    async function marketplaceApiGet(action, params) {
        var qs = new URLSearchParams(Object.assign({ action: action }, params || {}));
        var response = await fetch(url('/api/marketplace.php?' + qs.toString()), {
            credentials: 'same-origin',
        });
        var result = await parseMarketplaceResponse(response);
        if (!response.ok || result.status === 'error') {
            var err = new Error(result.message || 'Request failed');
            err.code = result.code || '';
            err.data = result.data || null;
            throw err;
        }
        return result;
    }

    function renderPermissionsManifest(manifest) {
        manifest = manifest || {};
        var parts = [];
        var perms = manifest.permissions || [];
        if (perms.length) {
            parts.push('<h4>Capabilities</h4><ul class="consent-perm-list">');
            perms.forEach(function (perm) {
                parts.push('<li><code>' + escapeHtml(perm) + '</code></li>');
            });
            parts.push('</ul>');
        }
        var hooks = manifest.hooks || [];
        if (hooks.length) {
            parts.push('<h4>Hooks</h4><ul class="consent-perm-list">');
            hooks.forEach(function (hook) {
                parts.push('<li><code>' + escapeHtml(hook) + '</code></li>');
            });
            parts.push('</ul>');
        }
        var dataAccess = manifest.data_access || {};
        var accessLines = [];
        Object.keys(dataAccess).forEach(function (domain) {
            var level = dataAccess[domain];
            if (level && level !== 'none') {
                accessLines.push('<li>' + escapeHtml(domain) + ': <strong>' + escapeHtml(level) + '</strong></li>');
            }
        });
        if (accessLines.length) {
            parts.push('<h4>Data access</h4><ul class="consent-perm-list">' + accessLines.join('') + '</ul>');
        }
        if (!parts.length) {
            return '<p class="form-help">This plugin does not declare elevated permissions.</p>';
        }
        return parts.join('');
    }

    function openInstallConsentModal(context) {
        pendingInstallContext = context;
        var modal = document.getElementById('marketplace-consent-modal');
        var manifestEl = document.getElementById('marketplace-consent-manifest');
        var summaryEl = document.getElementById('marketplace-consent-summary');
        if (!modal || !manifestEl) {
            return;
        }
        if (summaryEl) {
            summaryEl.textContent = t(
                'install_consent_summary',
                'This plugin requests access to your site. Review the permissions below, then accept to install.'
            );
        }
        manifestEl.innerHTML = renderPermissionsManifest(context.manifest || {});
        modal.classList.add('active');
    }

    function closeInstallConsentModal() {
        var modal = document.getElementById('marketplace-consent-modal');
        if (modal) {
            modal.classList.remove('active');
        }
        pendingInstallContext = null;
    }

    async function acceptInstallConsent() {
        var pending = pendingInstallContext;
        if (!pending || !pending.slug) {
            return;
        }
        var btn = document.getElementById('marketplace-consent-accept-btn');
        if (btn) {
            btn.disabled = true;
        }
        try {
            await executeMarketplaceInstall(pending.slug, pending.version || '', pending.licenseKey || '', true);
        } catch (err) {
            showMessage(err.message, 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
            }
        }
    }

    async function executeMarketplaceInstall(slug, version, licenseKey, consentAccepted) {
        var payload = {
            slug: slug,
            version: version,
            consent_accepted: consentAccepted ? 1 : 0,
        };
        if (licenseKey) {
            payload.license_key = licenseKey;
        }
        await marketplaceApi('install', payload);
        closeMarketplaceInstallModal();
        closeInstallConsentModal();
        reloadAfterPluginChange(t('install_success', 'Plugin installed successfully'), 'installed');
    }

    async function ensureInstallConsentThenRun(slug, version, licenseKey) {
        var perm = await marketplaceApiGet('permissions', { slug: slug, version: version });
        var data = perm.data || {};
        var resolvedVersion = data.version || version;
        if (data.consent_required) {
            openInstallConsentModal({
                slug: slug,
                version: resolvedVersion,
                licenseKey: licenseKey,
                manifest: data.permissions_manifest || {},
            });
            return;
        }
        await executeMarketplaceInstall(slug, resolvedVersion, licenseKey, false);
    }

    async function parsePluginsResponse(response) {
        var body = await response.text();
        if (!body || !body.trim()) {
            throw new Error(t('plugins_empty_response', 'Plugin request failed (empty server response). Try again or check server logs.'));
        }
        try {
            return JSON.parse(body);
        } catch (parseErr) {
            throw new Error(t('plugins_invalid_response', 'Plugin request failed (invalid server response). Try again or check server logs.'));
        }
    }

    async function pluginsApi(action, extra) {
        var response = await fetch(url('/api/plugins.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ action: action, _csrf: csrfToken() }, extra || {})),
        });
        var result = await parsePluginsResponse(response);
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

    function offerTypeCssClass(offerType) {
        var type = String(offerType || 'plugin').replace(/[^a-z_]/g, '');
        return type || 'plugin';
    }

    function offerTypeLabel(offerType) {
        if (offerType === 'theme') {
            return t('marketplace_type_theme', 'Theme');
        }
        if (offerType === 'template_pack') {
            return t('marketplace_type_template', 'Template pack');
        }
        if (offerType === 'fieldtype') {
            return t('marketplace_type_fieldtype', 'Fieldtype');
        }
        return t('marketplace_type_plugin', 'Plugin');
    }

    function renderOfferTypeBadge(offerType) {
        var typeClass = offerTypeCssClass(offerType);
        return (
            '<span class="marketplace-offer-type-badge marketplace-offer-type-badge--' +
            escapeHtml(typeClass) +
            '">' +
            escapeHtml(offerTypeLabel(offerType || 'plugin')) +
            '</span>'
        );
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

    function marketplaceOfferUrl(slug, embed) {
        var base = marketplaceBaseUrl || 'https://marketplace.birkly.cloud';
        var url = base.replace(/\/$/, '') + '/offers/' + encodeURIComponent(slug || '');
        if (embed) {
            url += '?embed=1';
        }
        return url;
    }

    function marketplaceProviderEmbedUrl(providerId) {
        var base = marketplaceBaseUrl || 'https://marketplace.birkly.cloud';
        return base.replace(/\/$/, '') + '/providers/' + encodeURIComponent(providerId || '') + '?embed=1';
    }

    function offerProviderName(offer, provider) {
        if (provider && provider.display_name) {
            return String(provider.display_name);
        }
        if (offer && offer.provider_name) {
            return String(offer.provider_name);
        }
        return '';
    }

    function renderOfferCardProvider(offer) {
        var name = offerProviderName(offer, null);
        if (!name) {
            return '';
        }
        var byLabel = t('offer_by_provider', 'by {provider}').replace('{provider}', name);
        return '<p class="marketplace-offer-provider">' + escapeHtml(byLabel) + '</p>';
    }

    function renderProviderProfileBox(provider) {
        if (!provider) {
            return '';
        }
        var name = provider.display_name || provider.id || '';
        if (!name) {
            return '';
        }
        var providerId = provider.id || '';
        var initial = String(name).charAt(0).toUpperCase();
        var avatarHtml = provider.avatar_url
            ? '<img class="marketplace-provider-card-avatar" src="' + escapeHtml(provider.avatar_url) + '" alt="">'
            : '<div class="marketplace-provider-card-avatar marketplace-provider-card-avatar--placeholder" aria-hidden="true">' + escapeHtml(initial) + '</div>';
        var taglineHtml = provider.tagline
            ? '<p class="marketplace-provider-card-tagline">' + escapeHtml(provider.tagline) + '</p>'
            : '';
        var profileLink = provider.published === true && providerId
            ? '<button type="button" class="btn btn-secondary btn-sm marketplace-provider-card-action" data-plugin-action="view-provider-profile" data-provider-id="' +
              escapeHtml(providerId) + '">' +
              escapeHtml(t('view_provider_profile', 'View provider profile')) +
              '</button>'
            : '';

        return (
            '<div class="marketplace-provider-card">' +
            avatarHtml +
            '<div class="marketplace-provider-card-body">' +
            '<p class="marketplace-provider-card-label">' + escapeHtml(t('provider', 'Provider')) + '</p>' +
            '<p class="marketplace-provider-card-name">' + escapeHtml(name) + '</p>' +
            taglineHtml +
            '</div>' +
            profileLink +
            '</div>'
        );
    }

    function setEmbedModalView(mode) {
        embedModalView = mode === 'provider' ? 'provider' : 'offer';
        var backBtn = document.getElementById('marketplace-embed-back');
        var providerBox = document.getElementById('marketplace-embed-provider-box');
        var actionsEl = document.getElementById('marketplace-embed-offer-actions');
        var footerEl = document.querySelector('.marketplace-embed-footer');
        if (backBtn) {
            backBtn.hidden = embedModalView !== 'provider';
        }
        if (providerBox) {
            providerBox.hidden = embedModalView !== 'offer' || providerBox.innerHTML.trim() === '';
        }
        if (actionsEl) {
            actionsEl.hidden = embedModalView === 'provider';
        }
        if (footerEl) {
            footerEl.hidden = false;
        }
    }

    function updateEmbedModalProviderBox(provider) {
        var providerBox = document.getElementById('marketplace-embed-provider-box');
        if (!providerBox) {
            return;
        }
        var html = renderProviderProfileBox(provider);
        providerBox.innerHTML = html;
        providerBox.hidden = embedModalView !== 'offer' || html === '';
    }

    function marketplacePurchaseUrl(slug) {
        var base = marketplaceBaseUrl || 'https://marketplace.birkly.cloud';
        return base.replace(/\/$/, '') + '/buy/' + encodeURIComponent(slug || '');
    }

    function findCachedOffer(slug) {
        for (var i = 0; i < marketplaceOffersCache.length; i++) {
            if (marketplaceOffersCache[i] && marketplaceOffersCache[i].slug === slug) {
                return marketplaceOffersCache[i];
            }
        }
        return null;
    }

    function resolvePluginOfferSlug(plugin) {
        if (!plugin) {
            return '';
        }
        return String(plugin.offer_slug || plugin.marketplace_slug || '').trim();
    }

    function findInstalledPluginByOfferSlug(slug) {
        if (!slug) {
            return null;
        }
        for (var i = 0; i < installedPlugins.length; i++) {
            if (resolvePluginOfferSlug(installedPlugins[i]) === slug) {
                return installedPlugins[i];
            }
        }
        return null;
    }

    function renderPluginReviewChip(pluginId, offerSlug, reviewStatus) {
        if (!offerSlug) {
            return '';
        }
        reviewStatus = reviewStatus || {};
        if (reviewStatus.has_review && reviewStatus.review) {
            var rating = String(reviewStatus.review.rating || '');
            return (
                '<button type="button" class="plugin-review-chip plugin-review-chip--rated" data-plugin-action="open-review" data-plugin-id="' +
                escapeHtml(pluginId) + '" data-offer-slug="' + escapeHtml(offerSlug) + '">' +
                '\u2605 ' + escapeHtml(rating) + ' \u00b7 ' + escapeHtml(t('your_review', 'Your review')) +
                '</button>'
            );
        }
        if (reviewStatus.can_review) {
            return (
                '<button type="button" class="plugin-review-chip" data-plugin-action="open-review" data-plugin-id="' +
                escapeHtml(pluginId) + '" data-offer-slug="' + escapeHtml(offerSlug) + '">' +
                escapeHtml(t('rate_plugin', 'Rate this plugin')) +
                '</button>'
            );
        }
        return '';
    }

    function renderInstalledOfferReviewButton(pluginId, offerSlug) {
        if (!pluginId || !offerSlug) {
            return '';
        }
        var reviewStatus = reviewStatusMap[pluginId] || {};
        if (reviewStatus.has_review) {
            return (
                '<button type="button" class="btn btn-sm btn-secondary" data-plugin-action="open-review" data-plugin-id="' +
                escapeHtml(pluginId) + '" data-offer-slug="' + escapeHtml(offerSlug) + '">' +
                escapeHtml(t('your_review', 'Your review')) +
                '</button>'
            );
        }
        if (reviewStatus.can_review) {
            return (
                '<button type="button" class="btn btn-sm btn-secondary" data-plugin-action="open-review" data-plugin-id="' +
                escapeHtml(pluginId) + '" data-offer-slug="' + escapeHtml(offerSlug) + '">' +
                escapeHtml(t('rate_plugin', 'Rate this plugin')) +
                '</button>'
            );
        }
        return '';
    }

    function renderOfferEmbedFooterActions(slug, offer) {
        var footerHtml = renderOfferActionButtons(offer || findCachedOffer(slug) || { slug: slug }, false);
        var installedMatch = findInstalledPluginByOfferSlug(slug);
        if (installedMatch) {
            var reviewBtn = renderInstalledOfferReviewButton(installedMatch.id, slug);
            if (reviewBtn) {
                footerHtml = reviewBtn + footerHtml;
            }
        }
        return footerHtml;
    }

    function openOfferEmbedModal(slug, offer, provider) {
        var modal = document.getElementById('marketplace-offer-embed-modal');
        var iframe = document.getElementById('marketplace-offer-iframe');
        var titleEl = document.getElementById('marketplace-embed-title');
        var actionsEl = document.getElementById('marketplace-embed-offer-actions');
        if (!modal || !iframe) {
            return;
        }

        var resolvedOffer = offer || findCachedOffer(slug) || { slug: slug, title: slug };
        currentOfferProvider = provider || currentOfferProvider;
        currentOfferTitle = resolvedOffer.title || slug;
        embedModalView = 'offer';
        if (titleEl) {
            titleEl.textContent = currentOfferTitle;
        }
        iframe.src = marketplaceOfferUrl(slug, true);
        var providerForBox = currentOfferProvider;
        if (!providerForBox && (resolvedOffer.provider_name || resolvedOffer.provider_id)) {
            providerForBox = {
                id: resolvedOffer.provider_id || '',
                display_name: resolvedOffer.provider_name || resolvedOffer.provider_id || '',
                published: false,
            };
        }
        updateEmbedModalProviderBox(providerForBox);
        setEmbedModalView('offer');
        if (actionsEl) {
            actionsEl.innerHTML = renderOfferEmbedFooterActions(slug, resolvedOffer);
        }
        modal.classList.add('active');
    }

    function openProviderInEmbedModal(providerId) {
        if (!providerId || !currentOfferSlug) {
            return;
        }
        var modal = document.getElementById('marketplace-offer-embed-modal');
        var iframe = document.getElementById('marketplace-offer-iframe');
        var titleEl = document.getElementById('marketplace-embed-title');
        if (!modal || !iframe) {
            return;
        }

        embedModalView = 'provider';
        if (titleEl) {
            titleEl.textContent = currentOfferProvider && currentOfferProvider.display_name
                ? currentOfferProvider.display_name
                : providerId;
        }
        iframe.src = marketplaceProviderEmbedUrl(providerId);
        setEmbedModalView('provider');
        modal.classList.add('active');
    }

    function backToOfferInEmbedModal() {
        if (!currentOfferSlug) {
            closeOfferEmbedModal();
            return;
        }
        var iframe = document.getElementById('marketplace-offer-iframe');
        var titleEl = document.getElementById('marketplace-embed-title');
        if (iframe) {
            iframe.src = marketplaceOfferUrl(currentOfferSlug, true);
        }
        if (titleEl) {
            titleEl.textContent = currentOfferTitle || currentOfferSlug;
        }
        embedModalView = 'offer';
        setEmbedModalView('offer');
        var actionsEl = document.getElementById('marketplace-embed-offer-actions');
        var cachedOffer = findCachedOffer(currentOfferSlug);
        if (actionsEl) {
            actionsEl.innerHTML = renderOfferEmbedFooterActions(currentOfferSlug, cachedOffer);
        }
    }

    function closeOfferEmbedModal() {
        var modal = document.getElementById('marketplace-offer-embed-modal');
        var iframe = document.getElementById('marketplace-offer-iframe');
        var providerBox = document.getElementById('marketplace-embed-provider-box');
        var actionsEl = document.getElementById('marketplace-embed-offer-actions');
        var footerEl = document.querySelector('.marketplace-embed-footer');
        if (modal) {
            modal.classList.remove('active');
        }
        if (iframe) {
            iframe.src = 'about:blank';
        }
        if (providerBox) {
            providerBox.innerHTML = '';
            providerBox.hidden = true;
        }
        if (actionsEl) {
            actionsEl.innerHTML = '';
            actionsEl.hidden = false;
        }
        var backBtn = document.getElementById('marketplace-embed-back');
        if (backBtn) {
            backBtn.hidden = true;
        }
        if (footerEl) {
            footerEl.hidden = false;
        }
        currentOfferSlug = null;
        currentOfferProvider = null;
        currentOfferTitle = '';
        embedModalView = 'offer';
        updateMarketplaceUrl('');
        updatePageTitle('marketplace');
    }

    function ratingTierClass(summary) {
        if (!summary || !summary.count) {
            return 'none';
        }
        var avg = Number(summary.average || 0);
        if (avg >= 4) {
            return 'good';
        }
        if (avg >= 3) {
            return 'fair';
        }
        return 'poor';
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

    function isOfferComingSoon(offer) {
        if (!offer) {
            return false;
        }
        if (offer.status === 'coming_soon') {
            return true;
        }
        return offer.installable === false && offer.status !== 'published';
    }

    function renderComingSoonBadge(offer) {
        if (!isOfferComingSoon(offer)) {
            return '';
        }
        return (
            '<span class="marketplace-offer-status-badge marketplace-offer-status-badge--coming-soon">' +
            escapeHtml(t('marketplace_coming_soon', 'Coming soon')) +
            '</span>'
        );
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
        void stopPropagation;

        if (isOfferComingSoon(offer)) {
            return (
                '<button type="button" class="btn btn-sm btn-secondary" disabled title="' +
                escapeHtml(t('marketplace_coming_soon_hint', 'This plugin is not available to install yet.')) +
                '">' +
                escapeHtml(t('marketplace_coming_soon', 'Coming soon')) + '</button>'
            );
        }

        if (state.installed) {
            if (state.update_available && state.plugin_id) {
                return (
                    '<button type="button" class="btn btn-sm btn-secondary" data-plugin-action="upgrade" data-plugin-id="' +
                    escapeHtml(state.plugin_id) + '">' +
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
                '<button type="button" class="btn btn-sm btn-primary" data-plugin-action="install-free" data-offer-slug="' +
                escapeHtml(slug) + '" data-offer-version="' + escapeHtml(version) + '">' +
                escapeHtml(t('install', 'Install')) + '</button>'
            );
        }

        return (
            '<a class="btn btn-sm btn-secondary" href="' + escapeHtml(marketplacePurchaseUrl(slug)) + '" target="_blank" rel="noopener noreferrer">' +
            escapeHtml(t('purchase', 'Purchase')) + '</a>' +
            '<button type="button" class="btn btn-sm btn-primary" data-plugin-action="open-install" data-offer-slug="' +
            escapeHtml(slug) + '" data-offer-title="' + escapeHtml(title) + '" data-offer-version="' +
            escapeHtml(version) + '" data-offer-free="false">' +
            escapeHtml(t('install_with_license', 'Install')) + '</button>'
        );
    }

    function renderOfferRatingStat(summary) {
        var tier = ratingTierClass(summary);
        return (
            '<span class="marketplace-stat marketplace-stat--rating marketplace-rating--' +
            escapeHtml(tier) +
            '">' +
            escapeHtml(formatRating(summary)) +
            '</span>'
        );
    }

    function renderOfferMetaRows(offer) {
        var versionText = offer.latest_version ? 'v' + escapeHtml(offer.latest_version) : '';
        return (
            '<div class="marketplace-offer-stats">' +
            '<div class="marketplace-offer-stats-row marketplace-offer-stats-row--primary">' +
            '<span class="marketplace-stat marketplace-stat--price">' + escapeHtml(formatPrice(offer)) + '</span>' +
            renderOfferRatingStat(offer.rating_summary) +
            '</div>' +
            '<div class="marketplace-offer-stats-row marketplace-offer-stats-row--secondary">' +
            (versionText
                ? '<span class="marketplace-stat marketplace-stat--version">' + versionText + '</span>'
                : '<span class="marketplace-stat marketplace-stat--version marketplace-stat--empty"></span>') +
            '<span class="marketplace-stat marketplace-stat--downloads">' + escapeHtml(formatDownloads(offer.download_count)) + '</span>' +
            '</div>' +
            '</div>'
        );
    }

    function renderOfferMediaBadges(offer) {
        var badges = renderOfferTypeBadge(offer.offer_type || 'plugin');
        if (isOfferComingSoon(offer)) {
            badges += renderComingSoonBadge(offer);
        }
        return '<div class="marketplace-offer-media-badges">' + badges + '</div>';
    }

    function offerImageHtml(offer) {
        var coverUrl = offer.cover_url || '';
        var iconUrl = offer.icon_url || '';
        if (!coverUrl && Array.isArray(offer.screenshots) && offer.screenshots.length) {
            coverUrl = offer.screenshots[0];
        }
        var html = renderOfferMediaBadges(offer);
        if (coverUrl) {
            html += '<img class="marketplace-offer-cover" src="' + escapeHtml(coverUrl) + '" alt="" loading="lazy">';
        } else if (iconUrl) {
            html += '<img class="marketplace-offer-image" src="' + escapeHtml(iconUrl) + '" alt="" loading="lazy">';
        } else {
            var initial = (offer.title || offer.slug || '?').charAt(0).toUpperCase();
            var typeClass = 'placeholder-' + escapeHtml((offer.offer_type || 'plugin').replace(/[^a-z_]/g, ''));
            html += '<div class="marketplace-offer-placeholder ' + typeClass + '" aria-hidden="true">' + escapeHtml(initial) + '</div>';
        }
        if (iconUrl && coverUrl) {
            html += '<img class="marketplace-offer-icon-badge" src="' + escapeHtml(iconUrl) + '" alt="" loading="lazy">';
        }
        return html;
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
                '<button type="button" class="btn btn-primary" data-plugin-action="switch-marketplace">' +
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
            var offerSlug = resolvePluginOfferSlug(plugin);
            var hasMarketplaceOffer = !!(plugin.has_marketplace_offer || offerSlug);
            var reviewStatus = reviewStatusMap[plugin.id] || {};
            var reviewChip = renderPluginReviewChip(plugin.id, offerSlug, reviewStatus);
            var pluginNameHtml = offerSlug
                ? '<button type="button" class="plugin-name-link" data-plugin-action="open-offer" data-offer-slug="' +
                    escapeHtml(offerSlug) + '">' + escapeHtml(plugin.name) + '</button>'
                : escapeHtml(plugin.name);
            var detailsButton = offerSlug
                ? '<button type="button" class="btn btn-sm btn-secondary" data-plugin-action="open-offer" data-offer-slug="' +
                    escapeHtml(offerSlug) + '">' + escapeHtml(t('offer_details', 'Details')) + '</button>'
                : '';
            return (
                '<div class="card plugin-card' + (hasUpdate ? ' has-update' : '') + '">' +
                '<div class="plugin-header">' +
                '<div>' +
                '<h3 class="plugin-name">' + pluginNameHtml + '</h3>' +
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
                (plugin.from_marketplace || hasMarketplaceOffer ? '<span class="marketplace-tag">Marketplace</span>' : '') +
                (plugin.license_ref ? '<span class="license-ref" title="License ref">#' + escapeHtml(plugin.license_ref) + '</span>' : '') +
                reviewChip +
                '</div>' +
                '<div class="plugin-actions">' +
                detailsButton +
                (hasUpdate
                    ? '<button type="button" class="btn btn-sm btn-primary" data-plugin-action="upgrade" data-plugin-id="' + escapeHtml(plugin.id) + '">Upgrade</button>'
                    : '') +
                '<button type="button" class="btn btn-sm ' + (active ? 'btn-secondary' : 'btn-primary') + '" data-plugin-action="toggle" data-plugin-id="' + escapeHtml(plugin.id) + '" data-plugin-activate="' + (!active) + '">' +
                (active ? 'Deactivate' : 'Activate') +
                '</button>' +
                '<button type="button" class="btn btn-sm btn-danger" data-plugin-action="delete" data-plugin-id="' + escapeHtml(plugin.id) + '">Delete</button>' +
                '</div>' +
                '</div>'
            );
        }).join('');
    }

    async function loadInstalled() {
        var grid = document.getElementById('plugins-grid');
        if (grid) {
            grid.innerHTML = renderPluginSkeletonCards(6);
        }

        var response = await fetch(url('/api/plugins.php?action=list'), { credentials: 'same-origin' });
        var result = await parsePluginsResponse(response);
        if (result.status !== 'ok') {
            throw new Error(result.message || 'Failed to load plugins');
        }
        installedPlugins = (result.data && result.data.plugins) || [];
        await loadReviewStatuses();
        renderInstalledPlugins();
    }

    async function loadReviewStatuses() {
        reviewStatusMap = {};
        try {
            var result = await marketplaceApi('review_status_batch');
            reviewStatusMap = (result.data && result.data.reviews) || {};
        } catch (e) {
            reviewStatusMap = {};
        }
    }

    function openReviewModal(pluginId, offerSlug) {
        var modal = document.getElementById('plugin-review-modal');
        var plugin = installedPlugins.find(function (p) { return p.id === pluginId; }) || {};
        var status = reviewStatusMap[pluginId] || {};
        var titleEl = document.getElementById('plugin-review-title');
        var summaryEl = document.getElementById('plugin-review-summary');
        var existingEl = document.getElementById('plugin-review-existing');
        var form = document.getElementById('plugin-review-form');
        var submitBtn = document.getElementById('plugin-review-submit-btn');
        if (!modal) return;

        document.getElementById('plugin-review-plugin-id').value = pluginId || '';
        document.getElementById('plugin-review-slug').value = offerSlug || plugin.marketplace_slug || plugin.offer_slug || '';

        if (titleEl) {
            titleEl.textContent = status.has_review ? 'Your review' : 'Write a review';
        }
        if (summaryEl) {
            summaryEl.textContent = 'Reviews are tied to this Birkly site installation. Only people who installed and use the plugin here can submit a review.';
        }

        if (existingEl) {
            if (status.has_review && status.review) {
                existingEl.hidden = false;
                existingEl.innerHTML =
                    '<p><strong>' + escapeHtml(String(status.review.rating || '')) + ' stars</strong></p>' +
                    '<p>' + escapeHtml(status.review.body || '') + '</p>';
            } else {
                existingEl.hidden = true;
                existingEl.innerHTML = '';
            }
        }

        if (form) {
            form.style.display = status.has_review ? 'none' : '';
        }
        if (submitBtn) {
            submitBtn.style.display = status.has_review ? 'none' : '';
        }

        modal.classList.add('active');
    }

    function closeReviewModal() {
        var modal = document.getElementById('plugin-review-modal');
        if (modal) {
            modal.classList.remove('active');
        }
        var body = document.getElementById('plugin-review-body');
        if (body) {
            body.value = '';
        }
    }

    async function submitPluginReview() {
        var pluginId = document.getElementById('plugin-review-plugin-id')?.value || '';
        var slug = document.getElementById('plugin-review-slug')?.value || '';
        var body = document.getElementById('plugin-review-body')?.value || '';
        var ratingInput = document.querySelector('#plugin-review-form input[name="plugin-review-rating"]:checked');
        var rating = ratingInput ? parseInt(ratingInput.value, 10) : 5;

        if (!slug && !pluginId) {
            showMessage('Missing plugin information', 'error');
            return;
        }
        if (!body.trim()) {
            showMessage('Please write a review', 'error');
            return;
        }

        try {
            var result = await marketplaceApi('submit_review', {
                plugin_id: pluginId,
                slug: slug,
                rating: rating,
                body: body.trim(),
            });
            showMessage(result.message || 'Review submitted', 'success');
            closeReviewModal();
            await loadReviewStatuses();
            renderInstalledPlugins();
        } catch (err) {
            showMessage(err.message, 'error');
        }
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
        if (!(await BirklyFeedback.confirm('Are you sure you want to delete this plugin? This action cannot be undone.'))) {
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
        if (!(await BirklyFeedback.confirm('Upgrade this plugin to the latest marketplace version?'))) {
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
                '<article class="marketplace-offer-card" role="button" tabindex="0" data-offer-slug="' + escapeHtml(slug) + '">' +
                '<div class="marketplace-offer-media">' + offerImageHtml(offer) + '</div>' +
                '<div class="marketplace-offer-body">' +
                '<div class="marketplace-offer-heading">' +
                '<h3 class="marketplace-offer-title">' +
                '<button type="button" class="marketplace-offer-title-link" data-plugin-action="open-offer" data-offer-slug="' + escapeHtml(slug) + '">' +
                escapeHtml(offer.title || slug) +
                '</button>' +
                '</h3>' +
                renderOfferCardProvider(offer) +
                '</div>' +
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
            '<button type="button" class="btn btn-secondary btn-sm" data-plugin-action="back-marketplace">' +
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
        updateMarketplaceUrl(slug);

        try {
            var response = await fetch(url('/api/marketplace.php?action=offer&slug=' + encodeURIComponent(slug)), {
                credentials: 'same-origin',
            });
            var result = await response.json();
            if (result.status !== 'ok' || !result.data || !result.data.offer) {
                throw new Error(result.message || 'Offer not found');
            }
            var offer = result.data.offer;
            var provider = result.data.provider || null;
            var cachedIndex = -1;
            for (var i = 0; i < marketplaceOffersCache.length; i++) {
                if (marketplaceOffersCache[i] && marketplaceOffersCache[i].slug === slug) {
                    cachedIndex = i;
                    break;
                }
            }
            if (cachedIndex >= 0) {
                marketplaceOffersCache[cachedIndex] = Object.assign({}, marketplaceOffersCache[cachedIndex], offer);
            }
            openOfferEmbedModal(slug, offer, provider);
        } catch (err) {
            var cached = findCachedOffer(slug);
            if (cached) {
                openOfferEmbedModal(slug, cached, null);
                return;
            }
            showMessage(err.message, 'error');
        }
    }

    function backToMarketplaceBrowse() {
        closeOfferEmbedModal();
        setMarketplaceView('browse');
        loadMarketplace();
    }

    function updateCategoryFilterOptions(categories) {
        var select = document.getElementById('marketplace-filter-category');
        if (!select) return;
        var current = select.value;
        var html = '<option value="">' + escapeHtml(t('marketplace_filter_category_all', 'Category · All categories')) + '</option>';
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
        var priceEl = document.getElementById('marketplace-price');
        return {
            q: qEl ? qEl.value : '',
            offer_type: typeEl ? typeEl.value : '',
            category: catEl ? catEl.value : '',
            sort: sortEl ? sortEl.value : 'name',
            price: priceEl ? priceEl.value : '',
        };
    }

    async function loadMarketplace() {
        setMarketplaceView('browse');
        var grid = document.getElementById('marketplace-grid');
        if (grid) {
            grid.innerHTML = renderMarketplaceSkeletonCards(8);
        }

        var params = getMarketplaceBrowseParams();
        var query = new URLSearchParams();
        query.set('action', 'browse');
        if (params.q) query.set('q', params.q);
        if (params.category) query.set('category', params.category);
        if (params.offer_type) query.set('offer_type', params.offer_type);
        if (params.sort) query.set('sort', params.sort);
        if (params.price) query.set('price', params.price);

        try {
            var browse = await fetch(url('/api/marketplace.php?' + query.toString()), {
                credentials: 'same-origin',
            });
            var result = await browse.json();
            if (result.status !== 'ok') {
                throw new Error(result.message || 'Browse failed');
            }

            marketplaceBaseUrl = (result.data && result.data.marketplace_url) || marketplaceBaseUrl;

            marketplaceFilterOptions = (result.data && result.data.filter_options) || marketplaceFilterOptions;
            updateCategoryFilterOptions(marketplaceFilterOptions.categories || []);
            marketplaceOffersCache = (result.data && result.data.offers) || [];
            renderMarketplaceOffers(marketplaceOffersCache);
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
        var tabsRoot = document.querySelector('.plugins-tabs.tabs-v2-root');
        if (tabsRoot && window.Birkly && window.Birkly.TabsV2 && typeof window.Birkly.TabsV2.refresh === 'function') {
            window.Birkly.TabsV2.refresh(tabsRoot);
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
        var offer = findCachedOffer(slug);
        if (isOfferComingSoon(offer)) {
            showMessage(t('marketplace_coming_soon_install_blocked', 'This plugin is coming soon and cannot be installed yet.'), 'info');
            return;
        }
        if (!(await BirklyFeedback.confirm(t('confirm_free_install', 'Install this free plugin now?')))) {
            return;
        }
        await runMarketplaceInstall(slug, version, '');
    }

    async function runMarketplaceInstall(slug, version, licenseKey) {
        var btn = document.getElementById('marketplace-install-btn');
        if (btn) {
            btn.disabled = true;
        }
        try {
            await ensureInstallConsentThenRun(slug, version, licenseKey);
        } catch (err) {
            if (err.code === 'consent_required') {
                openInstallConsentModal({
                    slug: slug,
                    version: (err.data && err.data.version) || version,
                    licenseKey: licenseKey,
                    manifest: (err.data && err.data.permissions_manifest) || {},
                });
                return;
            }
            showMessage(err.message, 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
            }
        }
    }

    function switchToMarketplace() {
        switchTab('marketplace');
    }

    function installFromFile() {
        showMessage(t('manual_install_unavailable', 'Manual zip install is not available yet. Use the Marketplace tab with your license key.'), 'info');
    }

    function initMarketplaceFilters() {
        ['marketplace-filter-type', 'marketplace-filter-category', 'marketplace-sort', 'marketplace-price'].forEach(function (id) {
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
            btn.addEventListener('click', async function () {
                switchTab(btn.getAttribute('data-tab'));
            });
        });
        initMarketplaceFilters();
    }

    async function init() {
        bindModalDelegatedEvents();
        bindDelegatedEvents();
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
        openOfferEmbedModal: openOfferEmbedModal,
        closeOfferEmbedModal: closeOfferEmbedModal,
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
