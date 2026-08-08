/**
 * Hub admin page — Discover · Installed · Updates · Licenses · Upload (P53-M11).
 * marketplace_* API routes unchanged; buyer labels say Hub.
 */
(function () {
    'use strict';

    var HUB_TABS = ['discover', 'installed', 'updates', 'licenses', 'upload'];
    var HEALTH_BADGE_TONES = {
        healthy: 'badge-success',
        warning: 'badge-warning',
        error: 'badge-danger',
        unknown: 'badge-neutral',
    };
    var RATING_TIER_HELPERS = {
        good: 'u-fg-success',
        fair: 'u-fg-warning',
        poor: 'u-fg-danger',
        none: 'u-fg-secondary',
    };
    var OFFER_TYPE_BADGE_TONES = {
        plugin: 'badge-info',
        theme: 'badge-info',
        template_pack: 'badge-warning',
        fieldtype: 'badge-success',
    };
    var installedPlugins = [];
    var installedPacks = [];
    var updateMap = {};
    var marketplaceFilterOptions = { categories: [], offer_types: [] };
    var currentOfferSlug = null;
    var currentOfferProvider = null;
    var currentOfferTitle = '';
    var embedModalView = 'offer';
    var embedOfferTab = 'offer';
    var selectedEmbedDiscountId = '';
    var currentOfferDetail = null;
    var marketplaceBaseUrl = '';
    var marketplaceSearchDebounceId = null;
    var marketplaceDisplayCurrency = 'EUR';
    var marketplaceCurrencyRates = {
        EUR: 1,
        USD: 1.08,
        GBP: 0.85,
        CHF: 0.95,
        DKK: 7.46,
        SEK: 11.5,
        NOK: 11.8,
        CAD: 1.47,
        AUD: 1.65,
    };
    var marketplaceOffersCache = [];
    var reviewStatusMap = {};
    var pendingInstallContext = null;
    var pendingHubLicenseInstall = null;
    var hubLicenseKeyByRef = {};
    var HUB_LICENSE_KEY_SESSION_PREFIX = 'birkly_hub_license_';

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
                '<div class="card plugin-card" aria-hidden="true">' +
                '<div class="skeleton skeleton-title"></div>' +
                '<div class="skeleton skeleton-text"></div>' +
                '<div class="skeleton skeleton-text"></div>' +
                '<div class="u-grid-2 u-gap-2 u-mt-2">' +
                '<div class="skeleton skeleton-row"></div>' +
                '<div class="skeleton skeleton-row"></div>' +
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
                '<div class="card plugin-card" aria-hidden="true">' +
                '<div class="skeleton skeleton-title"></div>' +
                '<div class="skeleton skeleton-text"></div>' +
                '<div class="skeleton skeleton-text"></div>' +
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
        } else if (action === 'switch-upload') {
            switchTab('upload');
        } else if (action === 'set-offer-type') {
            setOfferTypeFilter(el.getAttribute('data-offer-type') || '');
        } else if (action === 'back-marketplace') {
            backToMarketplaceBrowse();
        } else if (action === 'open-offer') {
            openOfferDetail(el.getAttribute('data-offer-slug'));
        } else if (action === 'install-free') {
            installFreePlugin(el.getAttribute('data-offer-slug'), el.getAttribute('data-offer-version'));
        } else if (action === 'checkout-offer') {
            startMarketplaceCheckout(el.getAttribute('data-offer-slug'), {
                planId: el.getAttribute('data-plan-id') || '',
                promoCode: el.getAttribute('data-promo-code') || '',
                quantity: el.getAttribute('data-quantity') || '',
            });
        } else if (action === 'open-install') {
            openMarketplaceInstall(
                el.getAttribute('data-offer-slug'),
                el.getAttribute('data-offer-title'),
                el.getAttribute('data-offer-version'),
                el.getAttribute('data-offer-free') === 'true'
            );
        } else if (action === 'check-updates') {
            checkAllUpdates();
        } else if (action === 'open-install-modal') {
            openInstallModal();
        } else if (action === 'install-from-file') {
            installFromFile();
        } else if (action === 'activate-license') {
            activateHubLicense();
        } else if (action === 'revoke-hub-license') {
            revokeHubLicense(el.getAttribute('data-plugin-id'), el.getAttribute('data-license-ref'));
        } else if (action === 'hub-install-pending-license') {
            startHubLicenseInstall(
                el.getAttribute('data-offer-slug'),
                el.getAttribute('data-offer-title'),
                el.getAttribute('data-offer-version')
            );
        } else if (action === 'replace-all-pack') {
            replaceAllPackFromUi(el.getAttribute('data-pack-slug'));
        } else if (action === 'close-offer-embed') {
            closeOfferEmbedModal();
        } else if (action === 'view-provider-profile') {
            openProviderInEmbedModal(el.getAttribute('data-provider-id'));
        } else if (action === 'back-offer-detail') {
            backToOfferInEmbedModal();
        } else if (action === 'embed-offer-tab') {
            setEmbedOfferTab(el.getAttribute('data-embed-tab') || 'offer');
        } else if (action === 'select-pricing-discount') {
            selectedEmbedDiscountId = el.getAttribute('data-discount-id') || '';
            updateEmbedModalPanels();
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
        } else if (action === 'open-settings') {
            openPluginSettings(el.getAttribute('data-plugin-id'));
        } else if (action === 'close-settings-modal') {
            closePluginSettingsModal();
        } else if (action === 'save-settings') {
            savePluginSettings();
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
                    action === 'embed-offer-tab' ||
                    action === 'select-pricing-discount' ||
                    action === 'open-review' ||
                    action === 'install-free' ||
                    action === 'checkout-offer' ||
                    action === 'upgrade' ||
                    action === 'open-install' ||
                    action === 'close-marketplace-install' ||
                    action === 'open-install-modal' ||
                    action === 'install-from-file' ||
                    action === 'activate-license' ||
                    action === 'revoke-hub-license' ||
                    action === 'hub-install-pending-license' ||
                    action === 'replace-all-pack' ||
                    action === 'set-offer-type' ||
                    action === 'switch-upload' ||
                    action === 'install-marketplace' ||
                    action === 'close-review-modal' ||
                    action === 'submit-review' ||
                    action === 'close-install-consent' ||
                    action === 'accept-install-consent' ||
                    action === 'open-settings' ||
                    action === 'close-settings-modal' ||
                    action === 'save-settings'
                ) {
                    handlePluginAction(el);
                }
                return;
            }

            if (e.target.id === 'marketplace-offer-embed-modal' && e.target.classList.contains('active')) {
                closeOfferEmbedModal();
            } else if (e.target.id === 'marketplace-install-modal' && e.target.classList.contains('active')) {
                closeMarketplaceInstallModal();
            } else if (e.target.id === 'marketplace-consent-modal' && e.target.classList.contains('active')) {
                closeInstallConsentModal();
            } else if (e.target.id === 'plugin-settings-modal' && e.target.classList.contains('active')) {
                closePluginSettingsModal();
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
            var marketplaceInstallModal = document.getElementById('marketplace-install-modal');
            if (marketplaceInstallModal && marketplaceInstallModal.classList.contains('active')) {
                closeMarketplaceInstallModal();
                return;
            }
            var consentModal = document.getElementById('marketplace-consent-modal');
            if (consentModal && consentModal.classList.contains('active')) {
                closeInstallConsentModal();
                return;
            }
            var settingsModal = document.getElementById('plugin-settings-modal');
            if (settingsModal && settingsModal.classList.contains('active')) {
                closePluginSettingsModal();
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
            throw new Error(t('marketplace_empty_response', 'Hub request failed (empty server response). Try again or check server logs.'));
        }
        try {
            return JSON.parse(body);
        } catch (parseErr) {
            throw new Error(t('marketplace_invalid_response', 'Hub request failed (invalid server response). Try again or check server logs.'));
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
            parts.push('<h4>Capabilities</h4><ul>');
            perms.forEach(function (perm) {
                parts.push('<li><code>' + escapeHtml(perm) + '</code></li>');
            });
            parts.push('</ul>');
        }
        var hooks = manifest.hooks || [];
        if (hooks.length) {
            parts.push('<h4>Hooks</h4><ul>');
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
            parts.push('<h4>Data access</h4><ul>' + accessLines.join('') + '</ul>');
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
            showMessage(marketplaceErrorMessage(err), 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
            }
        }
    }

    function collectHubPackOptions() {
        var offerTypeEl = document.getElementById('marketplace-install-offer-type');
        var offerType = offerTypeEl ? offerTypeEl.value : 'plugin';
        if (offerType !== 'template_pack') {
            return {};
        }
        var modeEl = document.getElementById('marketplace-pack-overwrite-mode');
        var demoEl = document.getElementById('marketplace-pack-include-demo');
        return {
            overwrite_mode: modeEl ? modeEl.value : 'merge',
            include_demo_entries: demoEl && demoEl.checked ? 1 : 0,
        };
    }

    function formatMissingPluginsList(missing) {
        if (!missing || !missing.length) {
            return '';
        }
        return missing.map(function (row) {
            return '- ' + (row.ref || row.marketplace_slug || row.plugin_id || 'plugin');
        }).join('\n');
    }

    async function confirmRequiredPluginsIfNeeded(err) {
        if (!err || err.code !== 'required_plugins_confirmation_required') {
            return false;
        }
        var missing = (err.data && err.data.missing_required_plugins) || [];
        var list = formatMissingPluginsList(missing) || '- (required plugins)';
        var message = t(
            'confirm_required_plugins',
            'This site pack requires plugins that are not installed:\n\n{list}\n\nInstall the missing required plugins, then continue with the pack?'
        ).replace('{list}', list);
        return BirklyFeedback.confirm(message);
    }

    async function executeMarketplaceInstall(slug, version, licenseKey, consentAccepted, packExtras) {
        var payload = Object.assign({
            slug: slug,
            version: version,
            consent_accepted: consentAccepted ? 1 : 0,
        }, collectHubPackOptions(), packExtras || {});
        if (licenseKey) {
            payload.license_key = licenseKey;
        }
        try {
            await marketplaceApi('install', payload);
        } catch (err) {
            if (err.code === 'required_plugins_confirmation_required') {
                var confirmed = await confirmRequiredPluginsIfNeeded(err);
                if (!confirmed) {
                    return;
                }
                payload.confirm_required_plugins = 1;
                payload.consent_accepted = 1;
                await marketplaceApi('install', payload);
            } else if (err.code === 'consent_required' && err.data && err.data.consent) {
                openInstallConsentModal({
                    slug: err.data.consent.slug || slug,
                    version: err.data.consent.version || version,
                    licenseKey: licenseKey,
                    manifest: err.data.consent.permissions_manifest || {},
                    packRetry: true,
                });
                return;
            } else {
                throw err;
            }
        }
        closeMarketplaceInstallModal();
        closeInstallConsentModal();
        if (licenseKey) {
            rememberHubLicenseKey(licenseKey, licenseKeySuffix(licenseKey));
        }
        if (pendingHubLicenseInstall && pendingHubLicenseInstall.offer_slug === slug) {
            pendingHubLicenseInstall = null;
        }
        var offerTypeEl = document.getElementById('marketplace-install-offer-type');
        var isPack = offerTypeEl && offerTypeEl.value === 'template_pack';
        reloadAfterPluginChange(
            isPack
                ? t('site_pack_install_success', 'Site pack installed successfully')
                : t('install_success', 'Plugin installed successfully'),
            'installed'
        );
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
            return t('marketplace_type_template', 'Site pack');
        }
        if (offerType === 'fieldtype') {
            return t('marketplace_type_fieldtype', 'Fieldtype');
        }
        return t('marketplace_type_plugin', 'Plugin');
    }

    function renderOfferTypeBadge(offerType) {
        var tone = OFFER_TYPE_BADGE_TONES[offerTypeCssClass(offerType)] || 'badge-neutral';
        return (
            '<span class="badge badge-sm ' + tone + '">' +
            escapeHtml(offerTypeLabel(offerType || 'plugin')) +
            '</span>'
        );
    }

    function convertPriceCents(cents, fromCurrency, toCurrency) {
        var from = String(fromCurrency || 'EUR').toUpperCase();
        var to = String(toCurrency || marketplaceDisplayCurrency).toUpperCase();
        var amount = Math.max(0, parseInt(cents, 10) || 0);
        if (amount === 0 || from === to) {
            return amount;
        }
        var fromRate = marketplaceCurrencyRates[from];
        var toRate = marketplaceCurrencyRates[to];
        if (!fromRate || !toRate) {
            return amount;
        }
        return Math.round((amount / fromRate) * toRate);
    }

    function applyMarketplaceDisplayCurrency(currency) {
        var normalized = String(currency || 'EUR').toUpperCase();
        if (!marketplaceCurrencyRates[normalized]) {
            normalized = 'EUR';
        }
        marketplaceDisplayCurrency = normalized;
        var select = document.getElementById('marketplace-display-currency');
        if (select && select.value !== normalized) {
            select.value = normalized;
        }
    }

    async function saveDisplayCurrency(currency) {
        try {
            await fetch(url('/api/marketplace.php'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_display_currency',
                    currency: currency,
                    _csrf: csrfToken(),
                }),
            });
        } catch (err) {
            // Preference save is best-effort; prices still update locally.
        }
    }

    function refreshMarketplacePriceDisplay() {
        if (marketplaceOffersCache.length) {
            renderMarketplaceOffers(marketplaceOffersCache);
        }
        if (currentOfferDetail && currentOfferDetail.offer) {
            updateEmbedModalPanels();
        }
        var detailPricing = document.querySelector('.marketplace-offer-detail-pricing');
        if (detailPricing && currentOfferSlug) {
            var detailOffer = findCachedOffer(currentOfferSlug);
            if (detailOffer) {
                detailPricing.innerHTML = renderOfferPricingTable(detailOffer);
            }
        }
    }

    function lowestMonthlyPlanCents(pricing) {
        var plans = pricing.plans || [];
        if (!plans.length) {
            return parseInt(pricing.amount_cents || 0, 10) || 0;
        }
        var lowest = null;
        plans.forEach(function (plan) {
            var monthly = parseInt(plan.monthly_equivalent_cents || plan.amount_cents || 0, 10);
            if (monthly > 0) {
                lowest = lowest === null ? monthly : Math.min(lowest, monthly);
            }
        });
        return lowest !== null ? lowest : (parseInt(pricing.amount_cents || 0, 10) || 0);
    }

    function formatPrice(offer) {
        if (isFreeOffer(offer)) {
            return t('price_free', 'Free');
        }
        var pricing = offer.pricing || {};
        var fromCurrency = String(pricing.currency || 'EUR').toUpperCase();
        if (pricing.model === 'subscription') {
            var plans = pricing.plans || [];
            var monthlyCents = lowestMonthlyPlanCents(pricing);
            var convertedMonthly = convertPriceCents(monthlyCents, fromCurrency, marketplaceDisplayCurrency);
            var monthlyAmount = (convertedMonthly / 100).toFixed(2) + ' ' + marketplaceDisplayCurrency + '/month';
            if (plans.length > 1) {
                return t('price_from', 'From') + ' ' + monthlyAmount;
            }
            return monthlyAmount;
        }
        var cents = convertPriceCents(pricing.amount_cents || 0, fromCurrency, marketplaceDisplayCurrency);
        return (cents / 100).toFixed(2) + ' ' + marketplaceDisplayCurrency;
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
        return '<p class="u-text-xs u-fg-tertiary u-m-0">' + escapeHtml(byLabel) + '</p>';
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
            ? '<p class="u-text-sm u-fg-secondary u-m-0">' + escapeHtml(provider.tagline) + '</p>'
            : '';
        var profileLink = provider.published === true && providerId
            ? '<button type="button" class="btn btn-secondary btn-sm u-shrink-0" data-plugin-action="view-provider-profile" data-provider-id="' +
              escapeHtml(providerId) + '">' +
              escapeHtml(t('view_provider_profile', 'View provider profile')) +
              '</button>'
            : '';

        return (
            '<div class="u-row-3">' +
            avatarHtml +
            '<div class="u-stack-1 u-fill">' +
            '<p class="u-label u-m-0">' + escapeHtml(t('provider', 'Provider')) + '</p>' +
            '<p class="u-text-base u-weight-medium u-m-0">' + escapeHtml(name) + '</p>' +
            taglineHtml +
            '</div>' +
            profileLink +
            '</div>'
        );
    }

    function renderEmbedOfferHeader(offer) {
        if (!offer) {
            return '';
        }
        var iconUrl = offer.icon_url || '';
        var initial = (offer.title || offer.slug || '?').charAt(0).toUpperCase();
        var iconHtml = iconUrl
            ? '<img class="marketplace-embed-offer-header-icon" src="' + escapeHtml(iconUrl) + '" alt="">'
            : '<div class="marketplace-embed-offer-header-icon marketplace-embed-offer-header-icon--placeholder" aria-hidden="true">' + escapeHtml(initial) + '</div>';
        var tagline = offer.tagline || offer.summary || '';
        var taglineHtml = tagline
            ? '<p class="u-text-sm u-fg-secondary u-m-0 u-clamp-2">' + escapeHtml(tagline) + '</p>'
            : '';
        return (
            '<div class="u-row-3 u-align-start">' +
            iconHtml +
            '<div class="u-stack-1 u-fill">' +
            '<div class="u-cluster u-gap-2">' +
            '<h3 class="u-text-lg u-weight-semibold u-m-0">' + escapeHtml(offer.title || offer.slug || '') + '</h3>' +
            renderOfferTypeBadge(offer.offer_type || 'plugin') +
            (isOfferComingSoon(offer) ? renderComingSoonBadge(offer) : '') +
            '</div>' +
            taglineHtml +
            '</div>' +
            '<div class="u-stack-1 u-align-end u-shrink-0">' +
            '<span class="u-text-base u-weight-semibold u-numeric">' + escapeHtml(formatPrice(offer)) + '</span>' +
            renderOfferRatingStat(offer.rating_summary) +
            '</div>' +
            '</div>'
        );
    }

    function updateEmbedOfferHeader(offer) {
        var headerEl = document.getElementById('marketplace-embed-offer-header');
        if (!headerEl) {
            return;
        }
        if (embedModalView !== 'offer' || !offer) {
            headerEl.innerHTML = '';
            headerEl.hidden = true;
            return;
        }
        var html = renderEmbedOfferHeader(offer);
        headerEl.innerHTML = html;
        headerEl.hidden = html === '';
    }

    function setEmbedModalView(mode) {
        embedModalView = mode === 'provider' ? 'provider' : 'offer';
        var backBtn = document.getElementById('marketplace-embed-back');
        var providerBox = document.getElementById('marketplace-embed-provider-box');
        var offerHeader = document.getElementById('marketplace-embed-offer-header');
        var actionsEl = document.getElementById('marketplace-embed-offer-actions');
        var footerEl = document.querySelector('.marketplace-embed-footer');
        var tabsEl = document.getElementById('marketplace-embed-tabs');
        if (backBtn) {
            backBtn.hidden = embedModalView !== 'provider';
        }
        if (providerBox) {
            providerBox.hidden = embedModalView !== 'offer' || providerBox.innerHTML.trim() === '';
        }
        if (offerHeader) {
            offerHeader.hidden = embedModalView !== 'offer' || offerHeader.innerHTML.trim() === '';
        }
        if (tabsEl) {
            tabsEl.hidden = embedModalView === 'provider';
        }
        if (actionsEl) {
            actionsEl.hidden = embedModalView === 'provider';
        }
        if (footerEl) {
            footerEl.hidden = false;
        }
        if (embedModalView === 'provider') {
            document.querySelectorAll('.marketplace-embed-panel').forEach(function (panel) {
                var isOfferPanel = panel.getAttribute('data-embed-panel') === 'offer';
                panel.hidden = !isOfferPanel;
                panel.classList.toggle('active', isOfferPanel);
            });
        } else {
            setEmbedOfferTab(embedOfferTab || 'offer');
        }
    }

    function setEmbedOfferTab(tab) {
        if (embedModalView === 'provider') {
            return;
        }
        var allowed = ['offer', 'pricing', 'reviews', 'versions'];
        if (allowed.indexOf(tab) === -1) {
            tab = 'offer';
        }
        embedOfferTab = tab;
        document.querySelectorAll('.marketplace-embed-tab').forEach(function (button) {
            var active = button.getAttribute('data-embed-tab') === tab;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('.marketplace-embed-panel').forEach(function (panel) {
            var active = panel.getAttribute('data-embed-panel') === tab;
            panel.hidden = !active;
            panel.classList.toggle('active', active);
        });
    }

    function formatReviewDate(value) {
        if (!value) {
            return '';
        }
        return String(value).substring(0, 10);
    }

    function renderReviewStars(rating) {
        var stars = Math.max(0, Math.min(5, parseInt(rating, 10) || 0));
        return '\u2605'.repeat(stars) + '\u2606'.repeat(5 - stars);
    }

    function compareVersionStrings(a, b) {
        if (!a && !b) {
            return 0;
        }
        if (!a) {
            return -1;
        }
        if (!b) {
            return 1;
        }
        var pa = String(a).split('.').map(function (part) {
            return parseInt(part, 10) || 0;
        });
        var pb = String(b).split('.').map(function (part) {
            return parseInt(part, 10) || 0;
        });
        var len = Math.max(pa.length, pb.length);
        for (var i = 0; i < len; i++) {
            var diff = (pa[i] || 0) - (pb[i] || 0);
            if (diff !== 0) {
                return diff > 0 ? 1 : -1;
            }
        }
        return 0;
    }

    function renderOfferReviewsPanel(reviews, ratingSummary, slug) {
        var summaryHtml = '';
        if (ratingSummary && ratingSummary.count) {
            summaryHtml =
                '<div class="stat u-mb-4">' +
                '<span class="stat-value u-numeric">' + escapeHtml(formatRating(ratingSummary)) + '</span>' +
                '<span class="stat-meta">' +
                escapeHtml(String(ratingSummary.count)) + ' ' + escapeHtml(t('marketplace_reviews_count', 'reviews')) +
                '</span></div>';
        }

        var installedMatch = findInstalledPluginByOfferSlug(slug);
        var writeReviewHtml = '';
        if (installedMatch) {
            var reviewStatus = reviewStatusMap[installedMatch.id] || {};
            if (reviewStatus.can_review) {
                writeReviewHtml =
                    '<div class="u-cluster u-mb-4">' +
                    '<button type="button" class="btn btn-sm btn-secondary" data-plugin-action="open-review" data-plugin-id="' +
                    escapeHtml(installedMatch.id) + '" data-offer-slug="' + escapeHtml(slug) + '">' +
                    escapeHtml(t('rate_plugin', 'Rate this plugin')) + '</button></div>';
            } else if (reviewStatus.has_review) {
                writeReviewHtml =
                    '<div class="u-cluster u-mb-4">' +
                    '<button type="button" class="btn btn-sm btn-secondary" data-plugin-action="open-review" data-plugin-id="' +
                    escapeHtml(installedMatch.id) + '" data-offer-slug="' + escapeHtml(slug) + '">' +
                    escapeHtml(t('your_review', 'Your review')) + '</button></div>';
            }
        }

        if (!reviews || !reviews.length) {
            return (
                summaryHtml +
                writeReviewHtml +
                '<div class="empty-state empty-state--compact"><p class="empty-state-title">' +
                escapeHtml(t('marketplace_no_reviews', 'No reviews yet')) + '</p></div>'
            );
        }

        var items = reviews.map(function (review) {
            var verifiedHtml = review.verified_installation
                ? '<span class="badge badge-sm badge-success">' + escapeHtml(t('marketplace_review_verified', 'Verified installation')) + '</span>'
                : '';
            var replyHtml = review.provider_reply && review.provider_reply.body
                ? '<div class="callout callout-neutral callout--block u-mt-3"><p class="u-label u-mb-1">' +
                    escapeHtml(t('marketplace_provider_reply', 'Provider reply')) + '</p><p class="u-text-sm u-m-0">' +
                    escapeHtml(review.provider_reply.body) + '</p></div>'
                : '';
            return (
                '<article class="card"><div class="card-body u-stack-2">' +
                '<div class="u-cluster u-gap-2">' +
                '<span class="marketplace-review-stars" aria-hidden="true">' + renderReviewStars(review.rating) + '</span>' +
                '<span class="u-text-xs u-fg-tertiary u-numeric">' + escapeHtml(formatReviewDate(review.created_at)) + '</span>' +
                verifiedHtml +
                '</div>' +
                '<p class="marketplace-review-body u-text-sm u-m-0">' + escapeHtml(review.body || '') + '</p>' +
                replyHtml +
                '</div></article>'
            );
        }).join('');

        return summaryHtml + writeReviewHtml + '<div class="u-stack-3">' + items + '</div>';
    }

    function renderOfferVersionsPanel(offer, releases) {
        if (!releases || !releases.length) {
            return '<div class="empty-state empty-state--compact"><p class="empty-state-title">' +
                escapeHtml(t('marketplace_no_versions', 'No release history yet.')) + '</p></div>';
        }

        var latestVersion = (offer && offer.latest_version) || '';
        var sorted = releases.slice().sort(function (a, b) {
            return compareVersionStrings(b.version, a.version);
        });

        return '<div class="u-stack-3">' + sorted.map(function (release, index) {
            var version = release.version || '';
            var isLatest = version !== '' && (version === latestVersion || (latestVersion === '' && index === 0));
            var changelog = release.changelog || t('marketplace_no_changelog', 'No changelog provided.');
            var published = formatReviewDate(release.published_at);
            return (
                '<article class="card' + (isLatest ? ' card--accent' : '') + '"><div class="card-body u-stack-2">' +
                '<div class="u-cluster u-gap-2">' +
                '<h4 class="u-text-base u-weight-semibold u-numeric u-m-0">v' + escapeHtml(version) + '</h4>' +
                (isLatest ? '<span class="badge badge-sm badge-success">' + escapeHtml(t('marketplace_latest_version', 'Latest')) + '</span>' : '') +
                (published ? '<span class="u-text-xs u-fg-tertiary u-numeric u-push-right">' + escapeHtml(published) + '</span>' : '') +
                '</div>' +
                '<div class="marketplace-version-changelog u-text-sm u-fg-secondary">' + escapeHtml(changelog) + '</div>' +
                '</div></article>'
            );
        }).join('') + '</div>';
    }

    function updateEmbedModalPanels() {
        var detail = currentOfferDetail;
        if (!detail || !detail.offer) {
            return;
        }
        var offer = detail.offer;
        var slug = offer.slug || currentOfferSlug || '';

        updateEmbedOfferHeader(offer);

        var pricingPanel = document.getElementById('marketplace-embed-pricing-panel');
        if (pricingPanel) {
            pricingPanel.innerHTML = renderOfferPricingTable(offer);
        }

        var reviewsPanel = document.getElementById('marketplace-embed-reviews-panel');
        if (reviewsPanel) {
            reviewsPanel.innerHTML = renderOfferReviewsPanel(
                detail.reviews || [],
                detail.rating_summary || offer.rating_summary || null,
                slug
            );
        }

        var versionsPanel = document.getElementById('marketplace-embed-versions-panel');
        if (versionsPanel) {
            versionsPanel.innerHTML = renderOfferVersionsPanel(offer, detail.releases || []);
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

    function marketplaceReturnUrl(slug, planId) {
        var pageUrl = new URL(window.location.href);
        pageUrl.searchParams.set('tab', 'discover');
        if (slug) {
            pageUrl.searchParams.set('offer', slug);
            pageUrl.searchParams.set('hub_install', '1');
        }
        if (planId) {
            pageUrl.searchParams.set('plan_id', planId);
        }
        pageUrl.searchParams.delete('license_key');
        pageUrl.searchParams.delete('activation_key');
        pageUrl.searchParams.delete('license');
        return pageUrl.toString();
    }

    function marketplacePurchaseUrl(slug, options) {
        var base = marketplaceBaseUrl || 'https://marketplace.birkly.cloud';
        var purchaseUrl = base.replace(/\/$/, '') + '/buy/' + encodeURIComponent(slug || '');
        options = options || {};
        var params = [];
        if (options.planId) {
            params.push('plan_id=' + encodeURIComponent(options.planId));
        }
        if (options.promoCode) {
            params.push('promo_code=' + encodeURIComponent(options.promoCode));
        }
        if (options.quantity && parseInt(options.quantity, 10) > 1) {
            params.push('quantity=' + encodeURIComponent(String(options.quantity)));
        }
        params.push('return_url=' + encodeURIComponent(options.returnUrl || marketplaceReturnUrl(slug, options.planId || '')));
        if (params.length) {
            purchaseUrl += '?' + params.join('&');
        }
        return purchaseUrl;
    }

    function getSelectedEmbedDiscount(offer) {
        if (!selectedEmbedDiscountId) {
            return null;
        }
        var pricing = (offer && offer.pricing) || {};
        var discounts = Array.isArray(pricing.discounts) ? pricing.discounts : [];
        for (var i = 0; i < discounts.length; i++) {
            var discount = discounts[i];
            if (!discount) {
                continue;
            }
            var id = String(discount.id || discount.promo_code || ('discount-' + (i + 1)));
            if (id === selectedEmbedDiscountId) {
                return discount;
            }
        }
        return null;
    }

    function resolvePurchaseOptions(offer, row) {
        var options = {
            planId: row && row.kind === 'subscription' ? row.id : null,
        };
        var discount = getSelectedEmbedDiscount(offer);
        if (!discount) {
            return options;
        }
        var discountPlanId = String(discount.plan_id || '').trim();
        if (discountPlanId && row && row.id && discountPlanId !== row.id) {
            return options;
        }
        if (discount.promo_code) {
            options.promoCode = discount.promo_code;
        }
        options.quantity = Math.max(1, parseInt(discount.min_quantity, 10) || 1);
        return options;
    }

    function formatDiscountValueLabel(discount) {
        var type = String(discount.type || 'percent_off');
        var value = parseInt(discount.value, 10) || 0;
        if (type === 'percent_off') {
            return value + '% off';
        }
        if (type === 'fixed_off') {
            return (value / 100).toFixed(2) + ' off';
        }
        return String(discount.label || '');
    }

    function getOfferPricingRows(offer) {
        var pricing = (offer && offer.pricing) || {};
        var model = pricing.model || 'one_time';
        if (isFreeOffer(offer)) {
            return [{
                id: 'free',
                label: t('price_free', 'Free'),
                kind: 'free',
            }];
        }
        if (model === 'subscription') {
            var plans = Array.isArray(pricing.plans) ? pricing.plans : [];
            if (!plans.length) {
                var interval = pricing.subscription_interval || 'month';
                plans = [{
                    id: interval === 'year' ? 'yearly' : 'monthly',
                    label: interval === 'year' ? t('marketplace_pricing_yearly', 'Yearly') : t('marketplace_pricing_monthly', 'Monthly'),
                    interval: interval,
                    amount_cents: pricing.amount_cents || 0,
                    monthly_equivalent_cents: pricing.amount_cents || 0,
                    is_default: true,
                }];
            }
            return plans.map(function (plan) {
                return {
                    id: plan.id || plan.interval || 'plan',
                    label: plan.label || plan.id || t('marketplace_pricing_plan', 'Plan'),
                    interval: plan.interval || 'month',
                    amount_cents: plan.amount_cents || 0,
                    monthly_equivalent_cents: plan.monthly_equivalent_cents || plan.amount_cents || 0,
                    compare_at_cents: plan.compare_at_cents || 0,
                    promo_label: plan.promo_label || '',
                    kind: 'subscription',
                };
            });
        }
        return [{
            id: 'one_time',
            label: t('marketplace_pricing_one_time', 'One-time purchase'),
            amount_cents: pricing.amount_cents || 0,
            kind: 'one_time',
        }];
    }

    function formatPricingRowPriceHtml(row, pricing) {
        var fromCurrency = String(pricing.currency || 'EUR').toUpperCase();
        if (row.kind === 'free') {
            return escapeHtml(t('price_free', 'Free'));
        }

        var parts = [];
        if (row.compare_at_cents && row.compare_at_cents > (row.amount_cents || 0)) {
            var compareMonthly = row.interval === 'year'
                ? Math.round(row.compare_at_cents / 12)
                : row.compare_at_cents;
            var compareDisplay = (convertPriceCents(compareMonthly, fromCurrency, marketplaceDisplayCurrency) / 100).toFixed(2) +
                ' ' + marketplaceDisplayCurrency;
            parts.push('<s class="u-text-xs u-fg-tertiary u-numeric">' + escapeHtml(compareDisplay) + '</s>');
        }

        if (row.kind === 'subscription') {
            var monthlyCents = convertPriceCents(
                row.monthly_equivalent_cents || row.amount_cents || 0,
                fromCurrency,
                marketplaceDisplayCurrency
            );
            var amount = (monthlyCents / 100).toFixed(2) + ' ' + marketplaceDisplayCurrency;
            var suffix = row.interval === 'year'
                ? t('marketplace_pricing_billed_yearly', '/month · billed yearly')
                : t('marketplace_pricing_per_month', '/month');
            parts.push('<span class="u-text-sm u-weight-semibold u-numeric">' + escapeHtml(amount + suffix) + '</span>');
        } else {
            var cents = convertPriceCents(row.amount_cents || 0, fromCurrency, marketplaceDisplayCurrency);
            parts.push('<span class="u-text-sm u-weight-semibold u-numeric">' +
                escapeHtml((cents / 100).toFixed(2) + ' ' + marketplaceDisplayCurrency) +
                '</span>');
        }

        return parts.join(' ');
    }

    function renderOfferPricingRowAction(offer, row) {
        var slug = offer.slug || '';
        var version = offer.latest_version || '';
        var title = offer.title || slug;
        var state = getOfferInstallState(offer);

        if (isOfferComingSoon(offer)) {
            return (
                '<button type="button" class="btn btn-sm btn-secondary" disabled title="' +
                escapeHtml(t('marketplace_coming_soon_hint', 'This plugin is not available to install yet.')) +
                '">' + escapeHtml(t('marketplace_coming_soon', 'Coming soon')) + '</button>'
            );
        }

        if (row.kind === 'free') {
            if (state.installed) {
                if (state.update_available && state.plugin_id) {
                    return (
                        '<button type="button" class="btn btn-sm btn-secondary" data-plugin-action="upgrade" data-plugin-id="' +
                        escapeHtml(state.plugin_id) + '">' + escapeHtml(t('update', 'Update')) + '</button>'
                    );
                }
                return (
                    '<button type="button" class="btn btn-sm btn-secondary" disabled>' +
                    escapeHtml(t('installed', 'Installed')) + '</button>'
                );
            }
            return (
                '<button type="button" class="btn btn-sm btn-primary" data-plugin-action="install-free" data-offer-slug="' +
                escapeHtml(slug) + '" data-offer-version="' + escapeHtml(version) + '">' +
                escapeHtml(t('install', 'Install')) + '</button>'
            );
        }

        if (state.installed) {
            if (state.update_available && state.plugin_id) {
                return (
                    '<button type="button" class="btn btn-sm btn-secondary" data-plugin-action="upgrade" data-plugin-id="' +
                    escapeHtml(state.plugin_id) + '">' + escapeHtml(t('update', 'Update')) + '</button>'
                );
            }
            return (
                '<button type="button" class="btn btn-sm btn-secondary" disabled>' +
                escapeHtml(t('installed', 'Installed')) + '</button>'
            );
        }

        var planId = row.kind === 'subscription' ? row.id : null;
        var purchaseOptions = resolvePurchaseOptions(offer, row);
        if (!purchaseOptions.planId && planId) {
            purchaseOptions.planId = planId;
        }
        return (
            '<button type="button" class="btn btn-sm btn-primary" data-plugin-action="checkout-offer" data-offer-slug="' +
            escapeHtml(slug) + '" data-plan-id="' + escapeHtml(purchaseOptions.planId || '') +
            '" data-promo-code="' + escapeHtml(purchaseOptions.promoCode || '') +
            '" data-quantity="' + escapeHtml(purchaseOptions.quantity || '') + '">' +
            escapeHtml(t('checkout', 'Checkout')) + '</button>'
        );
    }

    function renderOfferPricingDiscounts(offer, pricing) {
        var discounts = Array.isArray(pricing.discounts) ? pricing.discounts : [];
        if (!discounts.length) {
            return '';
        }

        var noneChecked = selectedEmbedDiscountId === '' ? ' checked' : '';
        var optionsHtml =
            '<label class="data-list-item data-list-item--interactive u-row-3" data-plugin-action="select-pricing-discount" data-discount-id="">' +
            '<input type="radio" name="embed-pricing-discount" value=""' + noneChecked + '>' +
            '<span class="data-list-main">' +
            '<span class="data-list-title">' + escapeHtml(t('marketplace_pricing_no_discount', 'Standard pricing')) + '</span>' +
            '</span></label>';

        optionsHtml += discounts.map(function (discount, index) {
            var id = String(discount.id || discount.promo_code || ('discount-' + (index + 1)));
            var checked = selectedEmbedDiscountId === id ? ' checked' : '';
            var promoCode = discount.promo_code ? String(discount.promo_code) : '';
            var minQty = Math.max(1, parseInt(discount.min_quantity, 10) || 1);
            var metaParts = [formatDiscountValueLabel(discount)];
            if (minQty > 1) {
                metaParts.push(minQty + '+ ' + t('marketplace_pricing_licenses', 'licenses'));
            }
            if (promoCode) {
                metaParts.push(promoCode);
            }
            return (
                '<label class="data-list-item data-list-item--interactive u-row-3" data-plugin-action="select-pricing-discount" data-discount-id="' + escapeHtml(id) + '">' +
                '<input type="radio" name="embed-pricing-discount" value="' + escapeHtml(id) + '"' + checked + '>' +
                '<span class="data-list-main">' +
                '<span class="data-list-title">' + escapeHtml(String(discount.label || id)) + '</span>' +
                '<span class="data-list-meta">' + escapeHtml(metaParts.join(' · ')) + '</span>' +
                '</span></label>'
            );
        }).join('');

        var selectedDiscount = getSelectedEmbedDiscount(offer);
        var checkoutHint = selectedDiscount
            ? '<p class="u-text-xs u-fg-tertiary u-m-0">' +
                escapeHtml(t('marketplace_pricing_discount_checkout_hint', 'Buy buttons use the selected discount at checkout.')) +
                '</p>'
            : '';

        return (
            '<div class="u-stack-2 u-mt-4">' +
            '<p class="u-label u-m-0">' + escapeHtml(t('marketplace_pricing_discounts', 'Discount offers')) + '</p>' +
            '<div class="data-list data-list--compact data-list--flush" role="radiogroup" aria-label="' + escapeHtml(t('marketplace_pricing_select_discount', 'Select a discount offer')) + '">' +
            optionsHtml +
            '</div>' +
            checkoutHint +
            '</div>'
        );
    }

    function renderOfferPricingTable(offer) {
        if (!offer) {
            return '';
        }
        var pricing = offer.pricing || {};
        var rows = getOfferPricingRows(offer);
        if (!rows.length) {
            return '';
        }

        var bodyRows = rows.map(function (row) {
            var promoHtml = row.promo_label
                ? ' <span class="badge badge-sm badge-success">' + escapeHtml(row.promo_label) + '</span>'
                : '';
            return (
                '<tr>' +
                '<th scope="row">' + escapeHtml(row.label) + promoHtml + '</th>' +
                '<td class="numeric">' + formatPricingRowPriceHtml(row, pricing) + '</td>' +
                '<td class="col-shrink">' + renderOfferPricingRowAction(offer, row) + '</td>' +
                '</tr>'
            );
        }).join('');

        return (
            '<div class="u-stack-3">' +
            '<h3 class="section-title">' + escapeHtml(t('marketplace_pricing', 'Pricing')) + '</h3>' +
            '<div class="table-container"><table class="table table--compact">' +
            '<thead><tr>' +
            '<th scope="col">' + escapeHtml(t('marketplace_pricing_plan', 'Plan')) + '</th>' +
            '<th scope="col" class="numeric">' + escapeHtml(t('marketplace_pricing_price', 'Price')) + '</th>' +
            '<th scope="col" class="col-shrink">' + escapeHtml(t('marketplace_pricing_action', 'Action')) + '</th>' +
            '</tr></thead>' +
            '<tbody>' + bodyRows + '</tbody></table></div>' +
            renderOfferPricingDiscounts(offer, pricing) +
            '</div>'
        );
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

    function licenseKeySuffix(licenseKey) {
        var parts = String(licenseKey || '').trim().toUpperCase().split('-');
        return parts.length ? parts[parts.length - 1] : '';
    }

    function rememberHubLicenseKey(licenseKey, licenseRef) {
        if (!licenseKey) {
            return;
        }
        var ref = licenseRef || licenseKeySuffix(licenseKey);
        if (!ref) {
            return;
        }
        hubLicenseKeyByRef[ref] = licenseKey;
        try {
            sessionStorage.setItem(HUB_LICENSE_KEY_SESSION_PREFIX + ref, licenseKey);
        } catch (storageErr) {
            void storageErr;
        }
    }

    function recallHubLicenseKey(licenseRef) {
        if (!licenseRef) {
            return '';
        }
        if (hubLicenseKeyByRef[licenseRef]) {
            return hubLicenseKeyByRef[licenseRef];
        }
        try {
            return sessionStorage.getItem(HUB_LICENSE_KEY_SESSION_PREFIX + licenseRef) || '';
        } catch (storageErr) {
            return '';
        }
    }

    function forgetHubLicenseKey(licenseRef) {
        if (!licenseRef) {
            return;
        }
        delete hubLicenseKeyByRef[licenseRef];
        try {
            sessionStorage.removeItem(HUB_LICENSE_KEY_SESSION_PREFIX + licenseRef);
        } catch (storageErr) {
            void storageErr;
        }
    }

    function renderPluginReviewChip(pluginId, offerSlug, reviewStatus) {
        if (!offerSlug) {
            return '';
        }
        reviewStatus = reviewStatus || {};
        if (reviewStatus.has_review && reviewStatus.review) {
            var rating = String(reviewStatus.review.rating || '');
            return (
                '<button type="button" class="chip chip--interactive chip--selected" data-plugin-action="open-review" data-plugin-id="' +
                escapeHtml(pluginId) + '" data-offer-slug="' + escapeHtml(offerSlug) + '">' +
                '\u2605 ' + escapeHtml(rating) + ' \u00b7 ' + escapeHtml(t('your_review', 'Your review')) +
                '</button>'
            );
        }
        if (reviewStatus.can_review) {
            return (
                '<button type="button" class="chip chip--interactive" data-plugin-action="open-review" data-plugin-id="' +
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
        var resolvedOffer = offer || findCachedOffer(slug) || { slug: slug };
        var html = '';
        var installedMatch = findInstalledPluginByOfferSlug(slug);
        if (installedMatch) {
            html += renderInstalledOfferReviewButton(installedMatch.id, slug);
        }
        if (
            !isFreeOffer(resolvedOffer) &&
            !isOfferComingSoon(resolvedOffer) &&
            !getOfferInstallState(resolvedOffer).installed
        ) {
            html +=
                '<button type="button" class="btn btn-sm btn-primary" data-plugin-action="checkout-offer" data-offer-slug="' +
                escapeHtml(slug) + '">' +
                escapeHtml(t('checkout', 'Checkout')) + '</button>' +
                '<button type="button" class="btn btn-sm btn-secondary" data-plugin-action="open-install" data-offer-slug="' +
                escapeHtml(slug) + '" data-offer-title="' + escapeHtml(resolvedOffer.title || slug) +
                '" data-offer-version="' + escapeHtml(resolvedOffer.latest_version || '') +
                '" data-offer-free="false">' +
                escapeHtml(t('activate_license_key', 'Activate key')) + '</button>';
        }
        return html;
    }

    function openOfferEmbedModal(slug, offer, provider, detailExtras) {
        var modal = document.getElementById('marketplace-offer-embed-modal');
        var iframe = document.getElementById('marketplace-offer-iframe');
        var titleEl = document.getElementById('marketplace-embed-title');
        var actionsEl = document.getElementById('marketplace-embed-offer-actions');
        if (!modal || !iframe) {
            return;
        }

        detailExtras = detailExtras || {};
        var resolvedOffer = offer || findCachedOffer(slug) || { slug: slug, title: slug };
        currentOfferProvider = provider || currentOfferProvider;
        currentOfferTitle = resolvedOffer.title || slug;
        currentOfferDetail = {
            offer: resolvedOffer,
            releases: detailExtras.releases || [],
            reviews: detailExtras.reviews || [],
            rating_summary: detailExtras.rating_summary || resolvedOffer.rating_summary || null,
            provider: provider || null,
        };
        embedModalView = 'offer';
        embedOfferTab = 'offer';
        selectedEmbedDiscountId = '';
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
        updateEmbedModalPanels();
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
        embedOfferTab = 'offer';
        setEmbedModalView('offer');
        var actionsEl = document.getElementById('marketplace-embed-offer-actions');
        var cachedOffer = findCachedOffer(currentOfferSlug);
        if (actionsEl) {
            actionsEl.innerHTML = renderOfferEmbedFooterActions(currentOfferSlug, cachedOffer);
        }
        if (currentOfferDetail && currentOfferDetail.offer) {
            updateEmbedModalPanels();
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
        ['marketplace-embed-pricing-panel', 'marketplace-embed-reviews-panel', 'marketplace-embed-versions-panel'].forEach(function (id) {
            var panel = document.getElementById(id);
            if (panel) {
                panel.innerHTML = '';
            }
        });
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
        currentOfferDetail = null;
        embedModalView = 'offer';
        embedOfferTab = 'offer';
        selectedEmbedDiscountId = '';
        var offerHeader = document.getElementById('marketplace-embed-offer-header');
        if (offerHeader) {
            offerHeader.innerHTML = '';
            offerHeader.hidden = true;
        }
        setEmbedOfferTab('offer');
        updateMarketplaceUrl('');
        updatePageTitle('discover');
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
            '<span class="badge badge-sm badge-accent">' +
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
            '<button type="button" class="btn btn-sm btn-primary" data-plugin-action="checkout-offer" data-offer-slug="' +
            escapeHtml(slug) + '">' +
            escapeHtml(t('checkout', 'Checkout')) + '</button>' +
            '<button type="button" class="btn btn-sm btn-secondary" data-plugin-action="open-install" data-offer-slug="' +
            escapeHtml(slug) + '" data-offer-title="' + escapeHtml(title) + '" data-offer-version="' +
            escapeHtml(version) + '" data-offer-free="false">' +
            escapeHtml(t('activate_license_key', 'Activate key')) + '</button>'
        );
    }

    function renderOfferRatingStat(summary) {
        return (
            '<span class="u-text-sm u-weight-medium u-numeric ' +
            escapeHtml(RATING_TIER_HELPERS[ratingTierClass(summary)] || 'u-fg-secondary') +
            '">' +
            escapeHtml(formatRating(summary)) +
            '</span>'
        );
    }

    function renderOfferMetaRows(offer) {
        var versionText = offer.latest_version ? 'v' + escapeHtml(offer.latest_version) : '';
        return (
            '<div class="marketplace-offer-stats u-stack-1">' +
            '<div class="u-split u-gap-3">' +
            '<span class="u-text-base u-weight-semibold u-numeric">' + escapeHtml(formatPrice(offer)) + '</span>' +
            renderOfferRatingStat(offer.rating_summary) +
            '</div>' +
            '<div class="u-split u-gap-3 u-text-xs u-fg-secondary">' +
            '<span class="u-numeric">' + versionText + '</span>' +
            '<span class="u-numeric">' + escapeHtml(formatDownloads(offer.download_count)) + '</span>' +
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
        badge.textContent = String(count);
        badge.hidden = count <= 0;
    }

    function renderPluginCard(plugin, options) {
        options = options || {};
        var update = updateMap[plugin.id] || {};
        var hasUpdate = !!update.update_available;
        var active = !!plugin.active;
        var health = plugin.health || {};
        var healthStatus = health.status || 'unknown';
        var healthTitle = [].concat(health.errors || [], health.warnings || []).join('; ');
        var healthTone = HEALTH_BADGE_TONES[healthStatus] || 'badge-neutral';
        var offerSlug = resolvePluginOfferSlug(plugin);
        var hasMarketplaceOffer = !!(plugin.has_marketplace_offer || offerSlug);
        var reviewStatus = reviewStatusMap[plugin.id] || {};
        var reviewChip = options.hideReview ? '' : renderPluginReviewChip(plugin.id, offerSlug, reviewStatus);
        var pluginNameHtml = offerSlug
            ? '<button type="button" class="btn btn-link" data-plugin-action="open-offer" data-offer-slug="' +
                escapeHtml(offerSlug) + '">' + escapeHtml(plugin.name) + '</button>'
            : escapeHtml(plugin.name);
        var detailsButton = offerSlug
            ? '<button type="button" class="btn btn-sm btn-secondary" data-plugin-action="open-offer" data-offer-slug="' +
                escapeHtml(offerSlug) + '">' + escapeHtml(t('offer_details', 'Details')) + '</button>'
            : '';
        return (
            '<div class="card plugin-card' + (hasUpdate ? ' has-update' : '') + '">' +
            '<div class="u-split u-align-start u-gap-3">' +
            '<div class="u-stack-1 u-min-w-0">' +
            '<h3 class="card-title">' + pluginNameHtml + '</h3>' +
            (hasUpdate ? '<span class="badge badge-warning badge-sm u-self-start">' + escapeHtml('Update: v' + (update.latest_version || '')) + '</span>' : '') +
            '</div>' +
            '<div class="u-cluster u-gap-1 u-shrink-0">' +
            '<span class="badge badge-sm ' + healthTone + '" title="' + escapeHtml(healthTitle || healthStatus) + '">' +
            escapeHtml(healthStatus.charAt(0).toUpperCase() + healthStatus.slice(1)) +
            '</span>' +
            '<span class="status-badge badge-sm ' + (active ? 'status-active' : 'status-inactive') + '">' +
            (active ? 'Active' : 'Inactive') +
            '</span>' +
            '</div>' +
            '</div>' +
            (plugin.description ? '<p class="u-text-sm u-fg-secondary u-m-0 u-clamp-3">' + escapeHtml(plugin.description) + '</p>' : '') +
            '<div class="u-cluster u-gap-2">' +
            '<span class="chip u-numeric">v' + escapeHtml(plugin.version || '1.0.0') + '</span>' +
            (plugin.from_marketplace || hasMarketplaceOffer ? '<span class="tag">Hub</span>' : '') +
            (plugin.license_ref ? '<span class="chip u-mono" title="License ref">#' + escapeHtml(plugin.license_ref) + '</span>' : '') +
            reviewChip +
            '</div>' +
            '<div class="u-cluster u-mt-auto u-pt-2">' +
            detailsButton +
            (hasUpdate
                ? '<button type="button" class="btn btn-sm btn-primary" data-plugin-action="upgrade" data-plugin-id="' + escapeHtml(plugin.id) + '">Upgrade</button>'
                : '') +
            (options.hideManage ? '' :
                (plugin.has_settings
                    ? '<button type="button" class="btn btn-sm btn-secondary" data-plugin-action="open-settings" data-plugin-id="' +
                        escapeHtml(plugin.id) + '">' + escapeHtml(t('settings', 'Settings')) + '</button>'
                    : '') +
                '<button type="button" class="btn btn-sm ' + (active ? 'btn-secondary' : 'btn-primary') + '" data-plugin-action="toggle" data-plugin-id="' + escapeHtml(plugin.id) + '" data-plugin-activate="' + (!active) + '">' +
                (active ? 'Deactivate' : 'Activate') +
                '</button>' +
                '<button type="button" class="btn btn-sm btn-danger" data-plugin-action="delete" data-plugin-id="' + escapeHtml(plugin.id) + '">Delete</button>') +
            '</div>' +
            '</div>'
        );
    }

    function renderInstalledPacks() {
        var grid = document.getElementById('installed-packs-grid');
        if (!grid) return;

        if (!installedPacks.length) {
            grid.innerHTML =
                '<div class="empty-state empty-state--compact">' +
                '<p class="empty-state-title">' + escapeHtml(t('no_site_packs_installed', 'No site packs installed yet.')) + '</p>' +
                '<p class="empty-state-text">' + escapeHtml(t('no_site_packs_hint', 'Install a site pack from Discover, or upload a pack ZIP.')) + '</p>' +
                '<div class="empty-state-actions">' +
                '<button type="button" class="btn btn-primary" data-plugin-action="switch-marketplace">' +
                escapeHtml(t('browse_marketplace', 'Browse Hub')) +
                '</button>' +
                '<button type="button" class="btn btn-secondary" data-plugin-action="switch-upload">' +
                escapeHtml(t('upload_site_pack', 'Upload')) +
                '</button>' +
                '</div>' +
                '</div>';
            return;
        }

        grid.innerHTML = installedPacks.map(function (pack) {
            var slug = pack.slug || pack.pack_id || '';
            var title = pack.pack_id || slug;
            var collections = Array.isArray(pack.installed_collections) ? pack.installed_collections : [];
            var source = pack.source || '';
            return (
                '<div class="card plugin-card">' +
                '<div class="u-stack-1">' +
                '<h3 class="card-title">' + escapeHtml(title) + '</h3>' +
                '<span class="badge badge-warning badge-sm u-self-start">' +
                escapeHtml(t('marketplace_type_template', 'Site pack')) +
                '</span>' +
                '</div>' +
                '<div class="u-cluster u-gap-2">' +
                (pack.version ? '<span class="chip u-numeric">v' + escapeHtml(pack.version) + '</span>' : '') +
                (source ? '<span class="tag">' + escapeHtml(source) + '</span>' : '') +
                (pack.project_site ? '<span class="chip">' + escapeHtml(t('hub_pack_has_project', 'Includes project files')) + '</span>' : '') +
                '</div>' +
                (collections.length
                    ? '<p class="u-text-sm u-fg-secondary u-m-0 u-clamp-3">' + escapeHtml(t('hub_pack_collections', 'Collections')) + ': ' +
                        escapeHtml(collections.join(', ')) + '</p>'
                    : '') +
                '<div class="u-cluster u-mt-auto u-pt-2">' +
                '<button type="button" class="btn btn-sm btn-secondary" data-plugin-action="replace-all-pack" data-pack-slug="' +
                escapeHtml(slug) + '">' + escapeHtml(t('hub_pack_replace_all', 'Replace all')) + '</button>' +
                '</div>' +
                '</div>'
            );
        }).join('');
    }

    function renderInstalledPlugins() {
        var grid = document.getElementById('plugins-grid');
        if (!grid) return;

        if (!installedPlugins.length) {
            grid.innerHTML =
                '<div class="empty-state empty-state--compact">' +
                '<p class="empty-state-title">' + escapeHtml(t('no_plugins_installed', 'No plugins installed yet.')) + '</p>' +
                '<p class="empty-state-text">' + escapeHtml(t('no_plugins_marketplace_hint', 'Browse Discover to install official plugins like Commerce and Events — free for Birkly sites.')) + '</p>' +
                '<div class="empty-state-actions">' +
                '<button type="button" class="btn btn-primary" data-plugin-action="switch-marketplace">' +
                escapeHtml(t('browse_marketplace', 'Browse Hub')) +
                '</button>' +
                '</div>' +
                '</div>';
            return;
        }

        grid.innerHTML = installedPlugins.map(function (plugin) {
            return renderPluginCard(plugin);
        }).join('');
    }

    function renderUpdatesPanel() {
        var grid = document.getElementById('hub-updates-grid');
        if (!grid) return;

        var withUpdates = installedPlugins.filter(function (plugin) {
            var update = updateMap[plugin.id] || {};
            return !!update.update_available;
        });

        if (!withUpdates.length) {
            grid.innerHTML =
                '<div class="empty-state empty-state--compact">' +
                '<p class="empty-state-title">' + escapeHtml(t('hub_updates_none', 'All Hub plugins are up to date.')) + '</p>' +
                '<p class="empty-state-text">' + escapeHtml(t('hub_updates_none_hint', 'Check again after new releases are published.')) + '</p>' +
                '</div>';
            return;
        }

        grid.innerHTML = withUpdates.map(function (plugin) {
            return renderPluginCard(plugin, { hideManage: true });
        }).join('');
    }

    function licenseEntitlementLabel(status) {
        var code = String(status || '').toLowerCase();
        var map = {
            active: t('entitlement_status_active', 'Active'),
            trialing: t('entitlement_status_trialing', 'Trial'),
            grace: t('entitlement_status_grace', 'Grace'),
            held: t('entitlement_status_held', 'On hold'),
            revoked: t('entitlement_status_revoked', 'Revoked'),
            expired: t('entitlement_status_expired', 'Expired'),
            deactivated: t('entitlement_status_deactivated', 'Deactivated'),
        };
        return map[code] || status;
    }

    function licenseEntitlementBadgeClass(status) {
        var code = String(status || '').toLowerCase();
        if (code === 'active' || code === 'trialing') {
            return 'badge badge-success badge-sm';
        }
        if (code === 'grace') {
            return 'badge badge-warning badge-sm';
        }
        if (code === 'held' || code === 'revoked' || code === 'expired') {
            return 'badge badge-danger badge-sm';
        }
        return 'badge badge-sm';
    }

    function renderLicenseEntitlementMeta(plugin) {
        var es = plugin.entitlement_status;
        if (!es || !es.status) {
            return '';
        }
        var label = es.message || licenseEntitlementLabel(es.status);
        return '<span class="' + licenseEntitlementBadgeClass(es.status) + '">' + escapeHtml(label) + '</span>';
    }

    function syncLicensesActivateButtonTier() {
        var activateBtn = document.querySelector('[data-plugin-action="activate-license"]');
        if (!activateBtn) {
            return;
        }
        var hasPending = !!(pendingHubLicenseInstall && pendingHubLicenseInstall.offer_slug);
        activateBtn.classList.toggle('btn-primary', !hasPending);
        activateBtn.classList.toggle('btn-secondary', hasPending);
    }

    function renderLicensesListSkeleton(count) {
        count = count || 3;
        var rows = '';
        for (var i = 0; i < count; i++) {
            rows +=
                '<div class="data-list-item">' +
                '<div class="data-list-main">' +
                '<span class="skeleton skeleton-text" style="width:40%"></span>' +
                '<span class="skeleton skeleton-text" style="width:28%"></span>' +
                '</div>' +
                '<div class="data-list-actions">' +
                '<span class="skeleton skeleton-text" style="width:4rem"></span>' +
                '</div>' +
                '</div>';
        }
        return '<div class="card card--flush"><div class="data-list">' + rows + '</div></div>';
    }

    function renderPendingHubLicenseInstallRow() {
        if (!pendingHubLicenseInstall || !pendingHubLicenseInstall.offer_slug) {
            return '';
        }
        var pending = pendingHubLicenseInstall;
        return (
            '<div class="data-list-item">' +
            '<div class="data-list-main">' +
            '<span class="data-list-title">' + escapeHtml(pending.title || pending.offer_slug) + '</span>' +
            '<span class="data-list-meta">' +
            escapeHtml(t('hub_license_install_pending', 'License activated — plugin not installed on this site yet.')) +
            '</span>' +
            '</div>' +
            '<div class="data-list-actions">' +
            '<button type="button" class="btn btn-sm btn-primary" data-plugin-action="hub-install-pending-license" data-offer-slug="' +
            escapeHtml(pending.offer_slug) + '" data-offer-title="' + escapeHtml(pending.title || pending.offer_slug) +
            '" data-offer-version="' + escapeHtml(pending.version || '') + '">' +
            escapeHtml(t('install_now', 'Install now')) + '</button>' +
            '</div>' +
            '</div>'
        );
    }

    function renderLicensesPanel() {
        var grid = document.getElementById('hub-licenses-grid');
        if (!grid) return;

        var licensed = installedPlugins.filter(function (plugin) {
            return !!(plugin.license_ref);
        });
        var pendingRow = renderPendingHubLicenseInstallRow();
        syncLicensesActivateButtonTier();

        if (!licensed.length && !pendingRow) {
            grid.innerHTML =
                '<div class="empty-state empty-state--compact">' +
                '<p class="empty-state-title">' + escapeHtml(t('hub_licenses_none', 'No licenses bound to this installation yet.')) + '</p>' +
                '<p class="empty-state-text">' + escapeHtml(t('hub_licenses_none_hint', 'Activate a license key above, or install a paid offer from Discover.')) + '</p>' +
                '</div>';
            return;
        }

        var revokeHint =
            '<p class="form-help u-m-0">' +
            escapeHtml(t('hub_revoke_license_hint', 'Revoking frees the seat on this installation so you can activate or install the same license key on another site.')) +
            '</p>';

        grid.innerHTML =
            '<div class="u-stack-3">' +
            revokeHint +
            '<div class="card card--flush"><div class="data-list">' +
            pendingRow +
            licensed.map(function (plugin) {
                var offerSlug = resolvePluginOfferSlug(plugin);
                var metaParts = ['#' + String(plugin.license_ref || '')];
                if (plugin.version) {
                    metaParts.push('v' + plugin.version);
                }
                if (offerSlug) {
                    metaParts.push(offerSlug);
                }
                return (
                    '<div class="data-list-item">' +
                    '<div class="data-list-main">' +
                    '<span class="data-list-title">' + escapeHtml(plugin.name || plugin.id) + '</span>' +
                    '<span class="data-list-meta u-mono">' + escapeHtml(metaParts.join(' · ')) + '</span>' +
                    '</div>' +
                    '<div class="data-list-actions">' +
                    renderLicenseEntitlementMeta(plugin) +
                    '<button type="button" class="btn btn-sm btn-secondary" data-plugin-action="revoke-hub-license" data-plugin-id="' +
                    escapeHtml(plugin.id) + '" data-license-ref="' + escapeHtml(plugin.license_ref) + '">' +
                    escapeHtml(t('hub_revoke_license', 'Revoke on this site')) + '</button>' +
                    '</div>' +
                    '</div>'
                );
            }).join('') +
            '</div></div></div>';
    }

    async function loadInstalled() {
        var grid = document.getElementById('plugins-grid');
        var packsGrid = document.getElementById('installed-packs-grid');
        if (grid) {
            grid.innerHTML = renderPluginSkeletonCards(4);
        }
        if (packsGrid) {
            packsGrid.innerHTML = renderPluginSkeletonCards(2);
        }

        var response = await fetch(url('/api/plugins.php?action=list'), { credentials: 'same-origin' });
        var result = await parsePluginsResponse(response);
        if (result.status !== 'ok') {
            throw new Error(result.message || 'Failed to load plugins');
        }
        installedPlugins = (result.data && result.data.plugins) || [];
        try {
            installedPacks = await listInstalledTemplatePacks();
        } catch (e) {
            installedPacks = [];
        }
        await loadReviewStatuses();
        renderInstalledPacks();
        renderInstalledPlugins();
    }

    async function loadUpdatesTab() {
        var grid = document.getElementById('hub-updates-grid');
        if (grid) {
            grid.innerHTML = renderPluginSkeletonCards(4);
        }
        if (!installedPlugins.length) {
            var response = await fetch(url('/api/plugins.php?action=list'), { credentials: 'same-origin' });
            var result = await parsePluginsResponse(response);
            if (result.status !== 'ok') {
                throw new Error(result.message || 'Failed to load plugins');
            }
            installedPlugins = (result.data && result.data.plugins) || [];
        }
        await refreshUpdateBadge(true);
        renderUpdatesPanel();
    }

    async function loadLicensesTab() {
        var grid = document.getElementById('hub-licenses-grid');
        if (grid) {
            grid.innerHTML = renderLicensesListSkeleton(3);
        }
        if (!installedPlugins.length) {
            var response = await fetch(url('/api/plugins.php?action=list'), { credentials: 'same-origin' });
            var result = await parsePluginsResponse(response);
            if (result.status !== 'ok') {
                throw new Error(result.message || 'Failed to load plugins');
            }
            installedPlugins = (result.data && result.data.plugins) || [];
        }
        renderLicensesPanel();
    }

    async function activateHubLicense() {
        var input = document.getElementById('hub-license-key');
        var licenseKey = input ? input.value.trim() : '';
        if (!licenseKey) {
            showMessage(t('enter_license_key', 'Enter your license key'), 'error');
            return;
        }
        try {
            var result = await marketplaceApi('activate_license', { license_key: licenseKey });
            var data = result.data || {};
            var offerSlug = String(data.offer_slug || '').trim();
            var licenseRef = licenseKeySuffix(licenseKey);
            rememberHubLicenseKey(licenseKey, licenseRef);

            var installedPlugin = offerSlug ? findInstalledPluginByOfferSlug(offerSlug) : null;
            if (offerSlug && !installedPlugin) {
                var cachedOffer = findCachedOffer(offerSlug) || {};
                pendingHubLicenseInstall = {
                    offer_slug: offerSlug,
                    license_key: licenseKey,
                    title: cachedOffer.title || offerSlug,
                    version: cachedOffer.latest_version || '',
                };
                showMessage(
                    t('hub_license_install_now', 'License activated. Install the plugin on this site to use it.'),
                    'success'
                );
            } else {
                pendingHubLicenseInstall = null;
                showMessage(result.message || t('hub_license_activated', 'License activated'), 'success');
            }

            if (input) {
                input.value = '';
            }
            await loadInstalled();
            renderLicensesPanel();
        } catch (err) {
            showMessage(marketplaceErrorMessage(err), 'error');
        }
    }

    async function revokeHubLicense(pluginId, licenseRef) {
        licenseRef = String(licenseRef || '').trim();
        pluginId = String(pluginId || '').trim();
        var licenseKey = recallHubLicenseKey(licenseRef);
        if (!licenseKey && BirklyFeedback && typeof BirklyFeedback.prompt === 'function') {
            licenseKey = await BirklyFeedback.prompt('', '', {
                title: t('hub_revoke_license', 'Revoke on this site'),
                placeholder: t('license_key', 'License key'),
                confirmLabel: t('hub_revoke_license', 'Revoke on this site'),
            });
            licenseKey = String(licenseKey || '').trim();
        }
        if (!licenseKey) {
            showMessage(
                t('enter_license_key_revoke', 'Enter the license key to revoke this seat on the marketplace.'),
                'error'
            );
            return;
        }
        if (
            !(await BirklyFeedback.confirm(
                t(
                    'hub_revoke_license_confirm',
                    'Revoke this license on this site? The seat will be freed so you can activate the same key on another Birkly installation.'
                )
            ))
        ) {
            return;
        }
        try {
            var payload = { license_key: licenseKey };
            if (pluginId) {
                payload.plugin_id = pluginId;
            }
            var result = await marketplaceApi('deactivate_license', payload);
            forgetHubLicenseKey(licenseRef || licenseKeySuffix(licenseKey));
            if (
                pendingHubLicenseInstall &&
                pendingHubLicenseInstall.offer_slug &&
                findInstalledPluginByOfferSlug(pendingHubLicenseInstall.offer_slug)
            ) {
                pendingHubLicenseInstall = null;
            }
            showMessage(result.message || t('hub_license_revoked', 'License revoked on this site'), 'success');
            await loadInstalled();
            renderLicensesPanel();
        } catch (err) {
            showMessage(marketplaceErrorMessage(err), 'error');
        }
    }

    function startHubLicenseInstall(slug, title, version) {
        var pending = pendingHubLicenseInstall;
        if (!slug && pending) {
            slug = pending.offer_slug;
            title = pending.title;
            version = pending.version;
        }
        if (!slug) {
            return;
        }
        var licenseKey = pending && pending.offer_slug === slug && pending.license_key ? pending.license_key : '';
        openMarketplaceInstall(slug, title || slug, version || '', false, licenseKey);
    }

    function marketplaceErrorMessage(err) {
        var code = err && err.code ? String(err.code) : '';
        if (code === 'held' || code === 'entitlement_held') {
            return t('entitlement_held', 'This entitlement is on hold. Update payment or contact the marketplace provider.');
        }
        if (code === 'expired' || code === 'entitlement_expired' || code === 'license_expired') {
            return t('entitlement_expired', 'This entitlement has expired. Renew it before installing or updating.');
        }
        if (code === 'seat_limit' || code === 'seat_limit_reached' || code === 'activation_limit_reached') {
            return t('entitlement_seat_limit', 'This license has reached its activation or seat limit.');
        }
        if (code === 'wrong_offer' || code === 'offer_mismatch' || code === 'license_offer_mismatch') {
            return t('entitlement_wrong_offer', 'This license belongs to a different Hub offer.');
        }
        if (code === 'inactive' || code === 'license_inactive' || code === 'entitlement_inactive') {
            return t('entitlement_inactive', 'This entitlement is inactive. Activate a valid license or complete checkout.');
        }
        return (err && err.message) || t('marketplace_request_failed', 'Hub request failed.');
    }

    async function startMarketplaceCheckout(slug, options) {
        if (!slug) {
            return;
        }
        options = options || {};
        var payload = {
            slug: slug,
            plan_id: options.planId || '',
            promo_code: options.promoCode || '',
            quantity: options.quantity || '',
            return_url: marketplaceReturnUrl(slug, options.planId || ''),
        };
        try {
            var result = await marketplaceApi('checkout', payload);
            var data = result.data || {};
            var checkoutUrl = data.checkout_url || (data.psp && (data.psp.checkout_url || data.psp.url)) || '';
            if (checkoutUrl) {
                window.location.href = checkoutUrl;
                return;
            }
            window.location.href = marketplacePurchaseUrl(slug, {
                planId: options.planId || '',
                promoCode: options.promoCode || '',
                quantity: options.quantity || '',
                returnUrl: payload.return_url,
            });
        } catch (err) {
            showMessage(marketplaceErrorMessage(err), 'error');
        }
    }

    async function replaceAllPackFromUi(slug) {
        if (!slug) return;
        try {
            var result = await replaceAllTemplatePack(slug);
            if (result && result.cancelled) {
                return;
            }
            showMessage(t('hub_pack_replaced', 'Site pack files replaced'), 'success');
            installedPacks = await listInstalledTemplatePacks();
            renderInstalledPacks();
        } catch (err) {
            showMessage(marketplaceErrorMessage(err), 'error');
        }
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
            renderUpdatesPanel();
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
            switchTab('updates');
        } else {
            showMessage(t('hub_updates_none', 'All Hub plugins are up to date'), 'success');
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
            grid.innerHTML = '<div class="empty-state empty-state--compact"><p class="empty-state-title">No offers found.</p></div>';
            return;
        }

        grid.innerHTML = offers.map(function (offer) {
            var slug = offer.slug || '';
            return (
                '<article class="card card--interactive marketplace-offer-card" role="button" tabindex="0" data-offer-slug="' + escapeHtml(slug) + '">' +
                '<div class="marketplace-offer-media">' + offerImageHtml(offer) + '</div>' +
                '<div class="marketplace-offer-body">' +
                '<div class="u-stack-1">' +
                '<h3 class="card-title">' +
                '<button type="button" class="btn btn-link" data-plugin-action="open-offer" data-offer-slug="' + escapeHtml(slug) + '">' +
                escapeHtml(offer.title || slug) +
                '</button>' +
                '</h3>' +
                renderOfferCardProvider(offer) +
                '</div>' +
                '<p class="u-text-sm u-fg-secondary u-m-0 u-clamp-3">' + escapeHtml(offer.summary || '') + '</p>' +
                renderOfferMetaRows(offer) +
                '<div class="u-cluster u-pt-2">' +
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
        pageUrl.searchParams.set('tab', 'discover');
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
            '<section class="u-stack-3">' +
            '<h3 class="section-title">Screenshots</h3>' +
            '<div class="marketplace-screenshots">' +
            shots.map(function (src) {
                return '<img src="' + escapeHtml(src) + '" alt="" loading="lazy">';
            }).join('') +
            '</div></section>'
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
            '<section class="u-stack-3">' +
            '<h3 class="section-title">' + escapeHtml(t('releases', 'Releases')) + '</h3>' +
            '<div class="table-container"><table class="table table--compact">' +
            '<thead><tr><th>Version</th><th>Published</th><th>' + escapeHtml(t('changelog', 'Changelog')) + '</th></tr></thead>' +
            '<tbody>' + rows + '</tbody></table></div></section>'
        );
    }

    function renderMarketplaceOfferDetail(offer, releases) {
        var slug = offer.slug || '';
        var compatibility = offer.compatibility || {};
        var description = offer.description || offer.summary || t('no_description', 'No detailed description provided yet.');
        var providerId = offer.provider_id || '';

        return (
            '<div class="u-stack-6">' +
            '<button type="button" class="btn btn-secondary btn-sm u-self-start" data-plugin-action="back-marketplace">' +
            escapeHtml(t('back_to_marketplace', 'Back to marketplace')) +
            '</button>' +
            '<div class="marketplace-offer-detail-header">' +
            '<div class="u-stack-3">' +
            '<div class="marketplace-offer-detail-media">' + offerImageHtml(offer) + '</div>' +
            '<span class="u-label">' + escapeHtml(offerTypeLabel(offer.offer_type || 'plugin')) + '</span>' +
            '<h2 class="u-text-xl u-weight-semibold u-m-0">' + escapeHtml(offer.title || slug) + '</h2>' +
            '<p class="u-text-sm u-fg-secondary u-m-0">' + escapeHtml(offer.summary || '') + '</p>' +
            renderOfferMetaRows(offer) +
            '</div>' +
            '<aside class="card"><div class="card-body">' + renderOfferPricingTable(offer) + '</div></aside>' +
            '</div>' +
            '<section class="u-stack-3">' +
            '<h3 class="section-title">' + escapeHtml(t('description', 'Description')) + '</h3>' +
            '<div class="marketplace-offer-description u-text-sm u-max-w-measure-md">' + escapeHtml(description) + '</div>' +
            '</section>' +
            renderOfferDetailScreenshots(offer) +
            '<section class="u-stack-3">' +
            '<h3 class="section-title">' + escapeHtml(t('compatibility', 'Compatibility')) + '</h3>' +
            '<dl class="kv">' +
            (compatibility.requires_birkly
                ? '<dt class="kv-key">' + escapeHtml(t('requires_birkly', 'Requires Birkly')) + '</dt><dd class="kv-value">' + escapeHtml(compatibility.requires_birkly) + '</dd>'
                : '') +
            (compatibility.requires_php
                ? '<dt class="kv-key">' + escapeHtml(t('requires_php', 'Requires PHP')) + '</dt><dd class="kv-value">' + escapeHtml(compatibility.requires_php) + '</dd>'
                : '') +
            '</dl></section>' +
            (providerId
                ? '<section class="u-stack-3"><h3 class="section-title">' + escapeHtml(t('provider', 'Provider')) + '</h3><p class="u-text-sm u-m-0">' + escapeHtml(providerId) + '</p></section>'
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
            openOfferEmbedModal(slug, offer, provider, {
                releases: result.data.releases || [],
                reviews: result.data.reviews || [],
                rating_summary: result.data.rating_summary || offer.rating_summary || null,
            });
            maybeResumeHubInstall(offer);
        } catch (err) {
            var cached = findCachedOffer(slug);
            if (cached) {
                openOfferEmbedModal(slug, cached, null);
                maybeResumeHubInstall(cached);
                return;
            }
            showMessage(marketplaceErrorMessage(err), 'error');
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
            if (result.data && result.data.display_currency) {
                applyMarketplaceDisplayCurrency(result.data.display_currency);
            }

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

    function normalizeHubTab(tab) {
        if (tab === 'marketplace') {
            return 'discover';
        }
        if (HUB_TABS.indexOf(tab) !== -1) {
            return tab;
        }
        return 'discover';
    }

    function updatePageTitle(tab) {
        var el = document.getElementById('plugins-page-title');
        var titles = window.PluginsPageTitles || {};
        if (!el) {
            return;
        }
        tab = normalizeHubTab(tab);
        if (titles[tab]) {
            el.textContent = titles[tab];
        } else if (titles.discover) {
            el.textContent = titles.discover;
        }
    }

    function getInitialTab() {
        var fromWindow = normalizeHubTab(window.PluginsPageInitialTab || '');
        if (window.PluginsPageInitialTab && HUB_TABS.indexOf(fromWindow) !== -1) {
            return fromWindow;
        }
        var params = new URLSearchParams(window.location.search);
        return normalizeHubTab(params.get('tab') || 'discover');
    }

    function getInitialOfferSlug() {
        var params = new URLSearchParams(window.location.search);
        return params.get('offer') || '';
    }

    function getCheckoutReturnLicenseKey() {
        var params = new URLSearchParams(window.location.search);
        return params.get('license_key') || params.get('activation_key') || params.get('license') || '';
    }

    function shouldResumeHubInstall(slug) {
        var params = new URLSearchParams(window.location.search);
        return !!slug && params.get('offer') === slug && (
            params.get('hub_install') === '1' ||
            params.get('install') === '1' ||
            getCheckoutReturnLicenseKey() !== ''
        );
    }

    function clearCheckoutReturnSensitiveParams() {
        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }
        var pageUrl = new URL(window.location.href);
        pageUrl.searchParams.delete('license_key');
        pageUrl.searchParams.delete('activation_key');
        pageUrl.searchParams.delete('license');
        window.history.replaceState({}, '', pageUrl.pathname + pageUrl.search + pageUrl.hash);
    }

    function maybeResumeHubInstall(offer) {
        if (!offer || !offer.slug || !shouldResumeHubInstall(offer.slug) || isFreeOffer(offer) || getOfferInstallState(offer).installed) {
            return;
        }
        openMarketplaceInstall(offer.slug, offer.title || offer.slug, offer.latest_version || '', false);
        var returnedKey = getCheckoutReturnLicenseKey();
        if (returnedKey) {
            var licenseInput = document.getElementById('marketplace-license-key');
            if (licenseInput) {
                licenseInput.value = returnedKey;
            }
            clearCheckoutReturnSensitiveParams();
            showMessage(t('hub_checkout_return_ready', 'Checkout complete. Review and install with the returned license key.'), 'success');
        } else {
            showMessage(t('hub_checkout_return_manual_key', 'Checkout complete. Paste your activation key to finish installing.'), 'info');
        }
    }

    function syncOfferTypeFilterChips(offerType) {
        var type = offerType || '';
        document.querySelectorAll('.hub-type-filter').forEach(function (btn) {
            var active = (btn.getAttribute('data-offer-type') || '') === type;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        var select = document.getElementById('marketplace-filter-type');
        if (select && select.value !== type) {
            select.value = type;
        }
    }

    function setOfferTypeFilter(offerType) {
        syncOfferTypeFilterChips(offerType || '');
        loadMarketplace();
    }

    function switchTab(tab) {
        tab = normalizeHubTab(tab);
        updatePageTitle(tab);
        document.querySelectorAll('.plugins-tab').forEach(function (btn) {
            var active = btn.getAttribute('data-tab') === tab;
            if (window.Birkly && window.Birkly.TabsA11y && typeof window.Birkly.TabsA11y.syncTabButton === 'function') {
                window.Birkly.TabsA11y.syncTabButton(btn, active);
            } else {
                btn.classList.toggle('active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
                btn.tabIndex = active ? 0 : -1;
            }
        });
        document.querySelectorAll('.plugins-tab-panel').forEach(function (panel) {
            var panelTab = (panel.id || '').replace('plugins-tab-', '');
            var show = panelTab === tab;
            if (window.Birkly && window.Birkly.TabsA11y && typeof window.Birkly.TabsA11y.syncTabPanel === 'function') {
                window.Birkly.TabsA11y.syncTabPanel(panel, show);
            } else {
                panel.classList.toggle('active', show);
                panel.hidden = !show;
            }
        });
        if (tab === 'discover') {
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
        } else if (tab === 'updates') {
            currentOfferSlug = null;
            loadUpdatesTab().catch(function (err) { showMessage(err.message, 'error'); });
        } else if (tab === 'licenses') {
            currentOfferSlug = null;
            loadLicensesTab().catch(function (err) { showMessage(err.message, 'error'); });
        } else if (tab === 'upload') {
            currentOfferSlug = null;
        }
        if (window.history && typeof window.history.replaceState === 'function') {
            var pageUrl = new URL(window.location.href);
            pageUrl.searchParams.set('tab', tab);
            if (tab !== 'discover') {
                pageUrl.searchParams.delete('offer');
            }
            window.history.replaceState({}, '', pageUrl.pathname + pageUrl.search + pageUrl.hash);
        }
        var tabsRoot = document.querySelector('.plugins-tabs.tabs-v2-root');
        if (tabsRoot && window.Birkly && window.Birkly.TabsV2 && typeof window.Birkly.TabsV2.refresh === 'function') {
            window.Birkly.TabsV2.refresh(tabsRoot);
        }
    }

    function openInstallModal() {
        switchTab('upload');
    }

    function closeInstallModal() {
        // Upload pack is a Hub tab (P53-M11); no modal to close.
    }

    function openMarketplaceInstall(slug, title, version, isFree, prefillLicenseKey) {
        var free = isFree === true || isFree === 'true';
        var offer = findCachedOffer(slug) || {};
        var offerType = offer.offer_type || 'plugin';
        document.getElementById('marketplace-install-slug').value = slug;
        document.getElementById('marketplace-install-version').value = version || '';
        var offerTypeEl = document.getElementById('marketplace-install-offer-type');
        if (offerTypeEl) {
            offerTypeEl.value = offerType;
        }
        document.getElementById('marketplace-install-title').textContent =
            t('install_from_marketplace', 'Install from Hub') + ': ' + title;
        document.getElementById('marketplace-install-summary').textContent =
            version ? 'Latest version: v' + version : 'Latest release will be installed.';
        var licenseInput = document.getElementById('marketplace-license-key');
        if (licenseInput) {
            licenseInput.value = prefillLicenseKey ? String(prefillLicenseKey) : '';
        }

        var licenseGroup = document.getElementById('marketplace-license-group');
        if (licenseGroup) {
            licenseGroup.hidden = free;
        }

        var packOptions = document.getElementById('marketplace-pack-options');
        if (packOptions) {
            packOptions.hidden = offerType !== 'template_pack';
        }
        var demoEl = document.getElementById('marketplace-pack-include-demo');
        if (demoEl) {
            demoEl.checked = false;
        }
        var modeEl = document.getElementById('marketplace-pack-overwrite-mode');
        if (modeEl) {
            modeEl.value = 'merge';
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
            showMessage(marketplaceErrorMessage(err), 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
            }
        }
    }

    function switchToMarketplace() {
        switchTab('discover');
    }

    async function installFromFile() {
        var fileInput = document.getElementById('plugin-file');
        var file = fileInput && fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        if (!file) {
            showMessage(t('select_site_pack_zip', 'Choose a site pack ZIP file'), 'error');
            return;
        }

        var modeEl = document.getElementById('pack-overwrite-mode');
        var demoEl = document.getElementById('pack-include-demo-entries');
        var overwriteMode = modeEl ? modeEl.value : 'merge';
        var includeDemo = demoEl && demoEl.checked;

        var btn = document.querySelector('[data-plugin-action="install-from-file"]');
        if (btn) {
            btn.disabled = true;
        }

        async function postPackZip(confirmRequired) {
            var form = new FormData();
            form.append('action', 'install_template_pack_zip');
            form.append('_csrf', csrfToken());
            form.append('pack_file', file);
            form.append('overwrite_mode', overwriteMode);
            form.append('include_demo_entries', includeDemo ? '1' : '0');
            if (confirmRequired) {
                form.append('confirm_required_plugins', '1');
                form.append('consent_accepted', '1');
            }
            var response = await fetch(url('/api/marketplace.php'), {
                method: 'POST',
                credentials: 'same-origin',
                body: form,
            });
            var result = await parseMarketplaceResponse(response);
            if (!response.ok || result.status === 'error') {
                var err = new Error(result.message || 'Install failed');
                err.code = result.code || '';
                err.data = result.data || null;
                throw err;
            }
            return result;
        }

        try {
            await postPackZip(false);
            reloadAfterPluginChange(t('site_pack_upload_success', 'Site pack installed from ZIP'), 'installed');
        } catch (err) {
            if (err.code === 'required_plugins_confirmation_required') {
                var confirmed = await confirmRequiredPluginsIfNeeded(err);
                if (!confirmed) {
                    showMessage(t('cancel', 'Cancelled'), 'info');
                    return;
                }
                try {
                    await postPackZip(true);
                    reloadAfterPluginChange(t('site_pack_upload_success', 'Site pack installed from ZIP'), 'installed');
                } catch (err2) {
                    showMessage(err2.message, 'error');
                }
                return;
            }
            showMessage(err.message, 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
            }
        }
    }

    /**
     * P53-M7/M8 — API hooks for Hub Installed (M11).
     * Safe updates skip user-touched files server-side; these helpers expose
     * per-file reset and rare replace-all with confirm.
     */
    async function listInstalledTemplatePacks() {
        var response = await fetch(url('/api/marketplace.php?action=list_installed_template_packs'), {
            credentials: 'same-origin',
        });
        var result = await parseMarketplaceResponse(response);
        if (!response.ok || result.status === 'error') {
            throw new Error(result.message || 'Failed to list installed site packs');
        }
        return (result.data && result.data.packs) || [];
    }

    async function getTemplatePackStatus(slug) {
        var response = await fetch(url('/api/marketplace.php?action=template_pack_status&slug=' + encodeURIComponent(slug)), {
            credentials: 'same-origin',
        });
        var result = await parseMarketplaceResponse(response);
        if (!response.ok || result.status === 'error') {
            throw new Error(result.message || 'Failed to load pack status');
        }
        return result.data || {};
    }

    async function resetTemplatePackFile(slug, path) {
        var response = await fetch(url('/api/marketplace.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'reset_template_pack_file',
                _csrf: csrfToken(),
                slug: slug,
                path: path,
            }),
        });
        var result = await parseMarketplaceResponse(response);
        if (!response.ok || result.status === 'error') {
            throw new Error(result.message || 'Reset failed');
        }
        return result.data || {};
    }

    async function replaceAllTemplatePack(slug, options) {
        options = options || {};
        var confirmed = options.confirm_replace_all === true
            || window.confirm(t(
                'pack_replace_all_confirm',
                'Replace all files from this site pack? A backup is created first. User edits will be overwritten.'
            ));
        if (!confirmed) {
            return { cancelled: true };
        }
        var response = await fetch(url('/api/marketplace.php'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'replace_all_template_pack',
                _csrf: csrfToken(),
                slug: slug,
                confirm_replace_all: 1,
                include_demo_entries: options.include_demo_entries ? 1 : 0,
                version: options.version || '',
            }),
        });
        var result = await parseMarketplaceResponse(response);
        if (!response.ok || result.status === 'error') {
            throw new Error(result.message || 'Replace all failed');
        }
        return result.data || {};
    }

    function initMarketplaceFilters() {
        ['marketplace-filter-type', 'marketplace-filter-category', 'marketplace-sort', 'marketplace-price'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', function () {
                    if (id === 'marketplace-filter-type') {
                        syncOfferTypeFilterChips(el.value || '');
                    }
                    loadMarketplace();
                });
            }
        });
        var currencyEl = document.getElementById('marketplace-display-currency');
        if (currencyEl) {
            currencyEl.addEventListener('change', function () {
                applyMarketplaceDisplayCurrency(currencyEl.value);
                saveDisplayCurrency(marketplaceDisplayCurrency);
                refreshMarketplacePriceDisplay();
            });
        }
        var search = document.getElementById('marketplace-search');
        if (search) {
            search.addEventListener('input', function () {
                window.clearTimeout(marketplaceSearchDebounceId);
                marketplaceSearchDebounceId = window.setTimeout(searchMarketplace, 250);
            });
            search.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    window.clearTimeout(marketplaceSearchDebounceId);
                    searchMarketplace();
                }
            });
        }
        var typeSelect = document.getElementById('marketplace-filter-type');
        syncOfferTypeFilterChips(typeSelect ? typeSelect.value : '');
    }

    function initTabs() {
        document.querySelectorAll('.plugins-tab').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                switchTab(btn.getAttribute('data-tab'));
            });
        });
        var tablist = document.querySelector('.plugins-tabs .tabs[role="tablist"]');
        if (tablist && window.Birkly && window.Birkly.TabsA11y && typeof window.Birkly.TabsA11y.enhance === 'function') {
            window.Birkly.TabsA11y.enhance(tablist, {
                activateOnFocus: true,
                onActivate: function (targetTab) {
                    if (targetTab) {
                        switchTab(targetTab);
                    }
                },
            });
        }
        initMarketplaceFilters();
    }

    async function init() {
        bindModalDelegatedEvents();
        bindDelegatedEvents();
        initTabs();
        var tab = getInitialTab();
        switchTab(tab);
        try {
            // Badge on Updates tab for all entry points
            if (tab !== 'updates') {
                await refreshUpdateBadge(true);
            }
        } catch (err) {
            // Non-blocking — Hub still usable offline from registry
        }
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/`/g, '&#96;');
    }

    function renderPluginSettingField(key, schema, value) {
        var type = (schema && schema.type) || 'string';
        var label = (schema && schema.label) || key;
        var help = (schema && schema.description) || '';
        var id = 'plugin-setting-' + key.replace(/[^a-zA-Z0-9_-]/g, '_');
        var field = '<div class="form-group" data-setting-key="' + escapeAttr(key) + '" data-setting-type="' + escapeAttr(type) + '">';

        if (type === 'boolean') {
            var checked = value === true || value === 1 || value === '1' || value === 'true';
            field +=
                '<label class="u-row-2">' +
                '<input type="checkbox" id="' + id + '" name="' + escapeAttr(key) + '"' + (checked ? ' checked' : '') + '>' +
                '<span>' + escapeHtml(label) + '</span>' +
                '</label>';
        } else if (type === 'integer' || type === 'number') {
            field +=
                '<label for="' + id + '">' + escapeHtml(label) + '</label>' +
                '<input type="number" class="input" id="' + id + '" name="' + escapeAttr(key) + '" value="' +
                escapeAttr(value == null ? '' : value) + '"' +
                (type === 'integer' ? ' step="1"' : '') + '>';
        } else {
            field +=
                '<label for="' + id + '">' + escapeHtml(label) + '</label>' +
                '<input type="text" class="input" id="' + id + '" name="' + escapeAttr(key) + '" value="' +
                escapeAttr(value == null ? '' : value) + '">';
        }

        if (help) {
            field += '<p class="field-help">' + escapeHtml(help) + '</p>';
        }
        field += '</div>';
        return field;
    }

    async function openPluginSettings(pluginId) {
        if (!pluginId) return;
        var modal = document.getElementById('plugin-settings-modal');
        var fields = document.getElementById('plugin-settings-fields');
        var idInput = document.getElementById('plugin-settings-id');
        var title = document.getElementById('plugin-settings-title');
        if (!modal || !fields || !idInput) return;

        idInput.value = pluginId;
        fields.innerHTML = '<p class="field-help">' + escapeHtml(t('loading', 'Loading…')) + '</p>';
        modal.classList.add('active');

        try {
            var result = await pluginsApi('get_settings', { plugin_id: pluginId });
            var data = result.data || {};
            var plugin = data.plugin || {};
            var schema = data.settings_schema || {};
            var settings = data.settings || {};
            if (title) {
                title.textContent = (plugin.name || pluginId) + ' — ' + t('plugin_settings', 'Settings');
            }
            var keys = Object.keys(schema);
            if (!keys.length) {
                fields.innerHTML = '<p class="field-help">' + escapeHtml(t('no_plugin_settings', 'No settings available.')) + '</p>';
                return;
            }
            fields.innerHTML = keys.map(function (key) {
                return renderPluginSettingField(key, schema[key], settings[key]);
            }).join('');
        } catch (err) {
            fields.innerHTML = '<p class="callout callout-danger">' + escapeHtml(err.message || 'Failed to load settings') + '</p>';
        }
    }

    function closePluginSettingsModal() {
        var modal = document.getElementById('plugin-settings-modal');
        if (modal) {
            modal.classList.remove('active');
        }
    }

    async function savePluginSettings() {
        var idInput = document.getElementById('plugin-settings-id');
        var fields = document.getElementById('plugin-settings-fields');
        if (!idInput || !fields) return;
        var pluginId = idInput.value;
        if (!pluginId) return;

        var settings = {};
        fields.querySelectorAll('[data-setting-key]').forEach(function (group) {
            var key = group.getAttribute('data-setting-key');
            var type = group.getAttribute('data-setting-type') || 'string';
            var input = group.querySelector('input, select, textarea');
            if (!key || !input) return;
            if (type === 'boolean') {
                settings[key] = !!input.checked;
            } else if (type === 'integer') {
                settings[key] = parseInt(input.value, 10);
                if (isNaN(settings[key])) settings[key] = 0;
            } else if (type === 'number') {
                settings[key] = parseFloat(input.value);
                if (isNaN(settings[key])) settings[key] = 0;
            } else {
                settings[key] = input.value;
            }
        });

        try {
            await pluginsApi('save_settings', { plugin_id: pluginId, settings: settings });
            if (window.BirklyFeedback && typeof BirklyFeedback.toast === 'function') {
                BirklyFeedback.toast(t('settings_saved', 'Settings saved'), 'success');
            }
            closePluginSettingsModal();
        } catch (err) {
            if (window.BirklyFeedback && typeof BirklyFeedback.toast === 'function') {
                BirklyFeedback.toast(err.message || 'Failed to save settings', 'error');
            } else {
                alert(err.message || 'Failed to save settings');
            }
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
        setOfferTypeFilter: setOfferTypeFilter,
        activateHubLicense: activateHubLicense,
        openOfferDetail: openOfferDetail,
        backToMarketplaceBrowse: backToMarketplaceBrowse,
        openOfferEmbedModal: openOfferEmbedModal,
        closeOfferEmbedModal: closeOfferEmbedModal,
        openPluginSettings: openPluginSettings,
        closePluginSettingsModal: closePluginSettingsModal,
        savePluginSettings: savePluginSettings,
        // P53-M7/M8/M11 — Hub Installed pack hooks
        listInstalledTemplatePacks: listInstalledTemplatePacks,
        getTemplatePackStatus: getTemplatePackStatus,
        resetTemplatePackFile: resetTemplatePackFile,
        replaceAllTemplatePack: replaceAllTemplatePack,
    };

    window.togglePlugin = togglePlugin;
    window.deletePlugin = deletePlugin;
    window.openInstallPluginModal = openInstallModal;
    window.closeInstallPluginModal = closeInstallModal;
    window.installPlugin = installFromFile;
    window.openPluginSettings = openPluginSettings;
    window.closePluginSettingsModal = closePluginSettingsModal;
    window.savePluginSettings = savePluginSettings;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
