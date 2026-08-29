/**
 * Birkly Analytics consent helper (P61) — Standard+ presets.
 *
 * Loads public preset config from the CMS. When consent is required and unset,
 * shows a minimal banner and sets cookie `birkly_analytics_consent=granted|denied`.
 * Trackers should send `consent` in the payload and only track when granted.
 *
 * Usage:
 *   <script src="https://cms…/birkly-analytics-consent.js" data-cms-url="https://cms…"></script>
 *   <!-- optional: data-consent-text="…" to override server text -->
 */
(function () {
    'use strict';

    var COOKIE = 'birkly_analytics_consent';
    var DAYS = 180;
    var script = document.currentScript || document.querySelector('script[src*="birkly-analytics-consent"]');

    function readCookie(name) {
        var parts = (document.cookie || '').split(';');
        for (var i = 0; i < parts.length; i++) {
            var p = parts[i].trim();
            if (p.indexOf(name + '=') === 0) {
                return decodeURIComponent(p.slice(name.length + 1));
            }
        }
        return '';
    }

    function writeCookie(name, value, days) {
        var maxAge = Math.max(1, days | 0) * 86400;
        var secure = location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(value)
            + '; Path=/; Max-Age=' + maxAge + '; SameSite=Lax' + secure;
    }

    function getConsent() {
        return readCookie(COOKIE);
    }

    function setConsent(value) {
        writeCookie(COOKIE, value, DAYS);
        try {
            document.dispatchEvent(new CustomEvent('birkly:analytics-consent', {
                detail: { consent: value }
            }));
        } catch (e) { /* ignore */ }
        if (window.HaruAnalytics) window.HaruAnalytics.consent = value;
        if (window.BirklyAnalytics) window.BirklyAnalytics.consent = value;
    }

    function removeBanner() {
        var el = document.getElementById('birkly-analytics-consent');
        if (el && el.parentNode) el.parentNode.removeChild(el);
    }

    function showBanner(text) {
        if (document.getElementById('birkly-analytics-consent')) return;
        var wrap = document.createElement('div');
        wrap.id = 'birkly-analytics-consent';
        wrap.setAttribute('role', 'dialog');
        wrap.setAttribute('aria-label', 'Analytics consent');
        wrap.style.cssText = 'position:fixed;z-index:99999;inset-inline:1rem;inset-block-end:1rem;max-width:28rem;padding:1rem 1.1rem;background:#161616;color:#f4f4f4;font:14px/1.45 system-ui,-apple-system,sans-serif;border-radius:10px;box-shadow:0 8px 28px rgba(0,0,0,.35)';
        var msg = document.createElement('p');
        msg.style.cssText = 'margin:0 0 .75rem';
        msg.textContent = text || 'We use privacy-friendly analytics to understand how the site is used. You can accept or decline.';
        var actions = document.createElement('div');
        actions.style.cssText = 'display:flex;gap:.5rem;flex-wrap:wrap';
        var accept = document.createElement('button');
        accept.type = 'button';
        accept.textContent = 'Accept';
        accept.style.cssText = 'appearance:none;border:0;border-radius:6px;padding:.45rem .9rem;background:#9ef0c0;color:#102018;font-weight:600;cursor:pointer';
        accept.addEventListener('click', function () {
            setConsent('granted');
            removeBanner();
        });
        var decline = document.createElement('button');
        decline.type = 'button';
        decline.textContent = 'Decline';
        decline.style.cssText = 'appearance:none;border:1px solid rgba(255,255,255,.35);border-radius:6px;padding:.45rem .9rem;background:transparent;color:#f4f4f4;cursor:pointer';
        decline.addEventListener('click', function () {
            setConsent('denied');
            removeBanner();
        });
        actions.appendChild(accept);
        actions.appendChild(decline);
        wrap.appendChild(msg);
        wrap.appendChild(actions);
        (document.body || document.documentElement).appendChild(wrap);
    }

    function resolveCmsUrl() {
        var fromAttr = script && script.getAttribute('data-cms-url');
        if (fromAttr) return String(fromAttr).replace(/\/$/, '');
        if (window.HaruAnalytics && window.HaruAnalytics.cmsUrl) {
            return String(window.HaruAnalytics.cmsUrl).replace(/\/$/, '');
        }
        if (window.Birkly && window.Birkly.cmsUrl) {
            return String(window.Birkly.cmsUrl).replace(/\/$/, '');
        }
        return String(window.location.origin || '').replace(/\/$/, '');
    }

    function applyConfig(cfg) {
        cfg = cfg || {};
        if (window.HaruAnalytics) {
            window.HaruAnalytics.requireConsent = !!cfg.consent_required;
            window.HaruAnalytics.enableEvents = !!cfg.events;
            if (cfg.consent_text) window.HaruAnalytics.consentText = cfg.consent_text;
        }
        if (window.BirklyAnalytics) {
            window.BirklyAnalytics.requireConsent = !!cfg.consent_required;
            window.BirklyAnalytics.enableEvents = !!cfg.events;
        }
        window.BirklyAnalyticsPublicConfig = cfg;

        if (!cfg.collecting || !cfg.consent_required) {
            return;
        }
        var existing = getConsent();
        if (existing === 'granted' || existing === 'denied') {
            if (window.HaruAnalytics) window.HaruAnalytics.consent = existing;
            if (window.BirklyAnalytics) window.BirklyAnalytics.consent = existing;
            return;
        }
        var text = (script && script.getAttribute('data-consent-text')) || cfg.consent_text || '';
        var show = function () { showBanner(text); };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', show);
        } else {
            show();
        }
    }

    function init() {
        var existing = getConsent();
        if (existing) {
            if (window.HaruAnalytics) window.HaruAnalytics.consent = existing;
            if (window.BirklyAnalytics) window.BirklyAnalytics.consent = existing;
        }

        var cmsUrl = resolveCmsUrl();
        if (!cmsUrl) {
            applyConfig({ collecting: true, consent_required: true, consent_text: '', events: false });
            return;
        }

        fetch(cmsUrl + '/api/insights.php?action=public_config', {
            credentials: 'omit',
            cache: 'no-store'
        }).then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload && payload.status === 'ok' && payload.data) {
                    applyConfig(payload.data);
                    return;
                }
                applyConfig({
                    collecting: true,
                    consent_required: true,
                    anonymous_counts: true,
                    consent_text: 'We use privacy-friendly analytics to understand how the site is used. Basic page views are counted even if you decline; detailed metrics need your consent.',
                    events: false
                });
            })
            .catch(function () {
                applyConfig({
                    collecting: true,
                    consent_required: true,
                    anonymous_counts: true,
                    consent_text: 'We use privacy-friendly analytics to understand how the site is used. Basic page views are counted even if you decline; detailed metrics need your consent.',
                    events: false
                });
            });
    }

    window.BirklyAnalyticsConsent = {
        get: getConsent,
        set: setConsent,
        show: showBanner
    };

    init();
})();
