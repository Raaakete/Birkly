/**
 * Birkly Analytics consent helper (P61) — Standard+ presets.
 *
 * Simple banner for Standard; Advanced adds Accept all, Decline, Customize.
 */
(function () {
    'use strict';

    var COOKIE = 'birkly_analytics_consent';
    var PREFS_COOKIE = 'birkly_analytics_consent_prefs';
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

    function getPrefs() {
        var raw = readCookie(PREFS_COOKIE);
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function defaultPrefs(cfg) {
        cfg = cfg || {};
        var cats = Array.isArray(cfg.consent_categories) ? cfg.consent_categories : [];
        var prefs = { sessions: true, devices: true, campaigns: true, events: !!cfg.events };
        cats.forEach(function (c) {
            if (c && c.id && typeof c.default === 'boolean') {
                if (c.id === 'essential') {
                    return;
                }
                prefs[c.id] = c.default;
            }
        });
        return prefs;
    }

    function emitConsentChange(consent, prefs) {
        try {
            document.dispatchEvent(new CustomEvent('birkly:analytics-consent', {
                detail: { consent: consent, prefs: prefs || null }
            }));
        } catch (e) { /* ignore */ }
        if (window.HaruAnalytics) {
            window.HaruAnalytics.consent = consent;
            if (prefs) window.HaruAnalytics.consentPrefs = prefs;
        }
        if (window.BirklyAnalytics) {
            window.BirklyAnalytics.consent = consent;
            if (prefs) window.BirklyAnalytics.consentPrefs = prefs;
        }
    }

    function setConsent(value, prefs) {
        writeCookie(COOKIE, value, DAYS);
        if (prefs && typeof prefs === 'object') {
            writeCookie(PREFS_COOKIE, JSON.stringify(prefs), DAYS);
        } else if (value === 'granted') {
            writeCookie(PREFS_COOKIE, '', 0);
        } else if (value === 'denied') {
            writeCookie(PREFS_COOKIE, JSON.stringify({ sessions: false, devices: false, campaigns: false, events: false }), DAYS);
        }
        emitConsentChange(value, prefs);
    }

    function removeBanner() {
        var el = document.getElementById('birkly-analytics-consent');
        if (el && el.parentNode) el.parentNode.removeChild(el);
    }

    function bannerBase(text) {
        var wrap = document.createElement('div');
        wrap.id = 'birkly-analytics-consent';
        wrap.setAttribute('role', 'dialog');
        wrap.setAttribute('aria-label', 'Analytics consent');
        wrap.style.cssText = 'position:fixed;z-index:99999;inset-inline:1rem;inset-block-end:1rem;max-width:32rem;padding:1rem 1.1rem;background:#161616;color:#f4f4f4;font:14px/1.45 system-ui,-apple-system,sans-serif;border-radius:10px;box-shadow:0 8px 28px rgba(0,0,0,.35)';
        var msg = document.createElement('p');
        msg.style.cssText = 'margin:0 0 .75rem';
        msg.textContent = text || 'We use privacy-friendly analytics to understand how the site is used. You can accept, decline, or customize.';
        wrap.appendChild(msg);
        return wrap;
    }

    function actionButton(label, primary) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = label;
        if (primary) {
            btn.style.cssText = 'appearance:none;border:0;border-radius:6px;padding:.45rem .9rem;background:#9ef0c0;color:#102018;font-weight:600;cursor:pointer';
        } else {
            btn.style.cssText = 'appearance:none;border:1px solid rgba(255,255,255,.35);border-radius:6px;padding:.45rem .9rem;background:transparent;color:#f4f4f4;cursor:pointer';
        }
        return btn;
    }

    function showSimpleBanner(text) {
        if (document.getElementById('birkly-analytics-consent')) return;
        var wrap = bannerBase(text);
        var actions = document.createElement('div');
        actions.style.cssText = 'display:flex;gap:.5rem;flex-wrap:wrap';
        var accept = actionButton('Accept', true);
        accept.addEventListener('click', function () {
            setConsent('granted');
            removeBanner();
        });
        var decline = actionButton('Decline', false);
        decline.addEventListener('click', function () {
            setConsent('denied');
            removeBanner();
        });
        actions.appendChild(accept);
        actions.appendChild(decline);
        wrap.appendChild(actions);
        (document.body || document.documentElement).appendChild(wrap);
    }

    function showAdvancedBanner(text, cfg) {
        if (document.getElementById('birkly-analytics-consent')) return;
        var wrap = bannerBase(text);
        var panel = document.createElement('div');
        panel.hidden = true;
        panel.style.cssText = 'margin:.75rem 0;padding:.75rem;border:1px solid rgba(255,255,255,.2);border-radius:8px';
        var prefs = defaultPrefs(cfg);
        var cats = Array.isArray(cfg.consent_categories) ? cfg.consent_categories : [];
        var inputs = {};

        cats.forEach(function (cat) {
            if (!cat || !cat.id || cat.id === 'essential') {
                return;
            }
            var row = document.createElement('label');
            row.style.cssText = 'display:flex;gap:.5rem;align-items:flex-start;margin:0 0 .45rem;cursor:pointer';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = prefs[cat.id] !== false;
            inputs[cat.id] = cb;
            var span = document.createElement('span');
            span.textContent = cat.label || cat.id;
            row.appendChild(cb);
            row.appendChild(span);
            panel.appendChild(row);
        });

        var essential = document.createElement('p');
        essential.style.cssText = 'margin:0 0 .5rem;font-size:12px;opacity:.85';
        essential.textContent = 'Essential page counts always run so we can measure traffic volume. Declining optional categories keeps reports privacy-limited.';
        panel.insertBefore(essential, panel.firstChild);

        var actions = document.createElement('div');
        actions.style.cssText = 'display:flex;gap:.5rem;flex-wrap:wrap';
        var acceptAll = actionButton('Accept all', true);
        acceptAll.addEventListener('click', function () {
            var all = defaultPrefs(cfg);
            Object.keys(all).forEach(function (k) { all[k] = true; });
            setConsent('granted', all);
            removeBanner();
        });
        var saveCustom = actionButton('Save choices', true);
        saveCustom.style.display = 'none';
        saveCustom.addEventListener('click', function () {
            var chosen = { sessions: false, devices: false, campaigns: false, events: false };
            Object.keys(inputs).forEach(function (k) {
                chosen[k] = !!inputs[k].checked;
            });
            setConsent('custom', chosen);
            removeBanner();
        });
        var customize = actionButton('Customize', false);
        customize.addEventListener('click', function () {
            panel.hidden = !panel.hidden;
            saveCustom.style.display = panel.hidden ? 'none' : 'inline-block';
            customize.textContent = panel.hidden ? 'Customize' : 'Hide options';
        });
        var decline = actionButton('Decline optional', false);
        decline.addEventListener('click', function () {
            setConsent('denied');
            removeBanner();
        });
        actions.appendChild(acceptAll);
        actions.appendChild(customize);
        actions.appendChild(saveCustom);
        actions.appendChild(decline);
        wrap.appendChild(panel);
        wrap.appendChild(actions);
        (document.body || document.documentElement).appendChild(wrap);
    }

    function showBanner(text, cfg) {
        cfg = cfg || {};
        if (cfg.consent_ui === 'advanced') {
            showAdvancedBanner(text, cfg);
            return;
        }
        showSimpleBanner(text);
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

    function hydrateFromCookies() {
        var existing = getConsent();
        var prefs = getPrefs();
        if (existing) {
            emitConsentChange(existing, prefs);
        }
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
        if (existing === 'granted' || existing === 'denied' || existing === 'custom') {
            hydrateFromCookies();
            return;
        }
        var text = (script && script.getAttribute('data-consent-text')) || cfg.consent_text || '';
        var show = function () { showBanner(text, cfg); };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', show);
        } else {
            show();
        }
    }

    function init() {
        hydrateFromCookies();

        if (window.__BIRKLY_CONSENT_DELEGATE_TO_CLIENT) {
            return;
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
        getPrefs: getPrefs,
        set: setConsent,
        show: showBanner
    };

    init();
})();