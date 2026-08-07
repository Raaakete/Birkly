/**
 * Birkly Analytics consent helper (P61) — Standard+ presets.
 *
 * Usage (after configuring HaruAnalytics / BirklyAnalytics):
 *   <script src="https://cms…/birkly-analytics-consent.js" data-cms-url="…"></script>
 *
 * Sets cookie `birkly_analytics_consent=granted|denied` and dispatches
 * `birkly:analytics-consent` on document. The tracker should only send when granted.
 */
(function () {
    'use strict';

    var COOKIE = 'birkly_analytics_consent';
    var DAYS = 180;

    function readCookie(name) {
        var parts = (';cookie || '').split(';');
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
        if (window.HaruAnalytics) {
            window.HaruAnalytics.consent = value;
        }
        if (window.BirklyAnalytics) {
            window.BirklyAnalytics.consent = value;
        }
    }

    function removeBanner() {
        var el = document.getElementById('birkly-analytics-consent');
        if (el && el.parentNode) {
            el.parentNode.removeChild(el);
        }
    }

    function showBanner(text) {
        if (document.getElementById('birkly-analytics-consent')) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.id = 'birkly-analytics-consent';
        wrap.setAttribute('role', 'dialog');
        wrap.setAttribute('aria-label', 'Analytics consent');
        wrap.style.cssText = 'position:fixed;z-index:99999;inset-inline:1rem;inset-block-end:1rem;max-width:28rem;padding:1rem 1.1rem;background:#111;color:#f5f5f5;font:14px/1.45 system-ui,sans-serif;border-radius:10px;box-shadow:0 8px 28px rgba(0,0,0,.35)';
        var msg = document.createElement('p');
        msg.style.cssText = 'margin:0 0 .75rem';
        msg.textContent = text || 'We use privacy-friendly analytics to understand how the site is used. You can accept or decline.';
        var actions = document.createElement('div');
        actions.style.cssText = 'display:flex;gap:.5rem;flex-wrap:wrap';
        var accept = document.createElement('button');
        accept.type = 'button';
        accept.textContent = 'Accept';
        accept.style.cssText = 'appearance:none;border:0;border-radius:6px;padding:.45rem .9rem;background:#7dffa8;color:#102018;font-weight:600;cursor:pointer';
        accept.addEventListener('click', function () {
            setConsent('granted');
            removeBanner();
        });
        var decline = document.createElement('button');
        decline.type = 'button';
        decline.textContent = 'Decline';
        decline.style.cssText = 'appearance:none;border:1px solid rgba(255,255,255,.35);border-radius:6px;padding:.45rem .9rem;background:transparent;color:#f5f5f5;cursor:pointer';
        decline.addEventListener('click', function () {
            setConsent('denied');
            removeBanner();
        });
        actions.appendChild(accept);
        actions.appendChild(decline);
        wrap.appendChild(msg);
        wrap.appendChild(actions);
        document.body.appendChild(wrap);
    }

    function init() {
        var existing = getConsent();
        if (existing === 'granted' || existing === 'denied') {
            if (window.HaruAnalytics) window.HaruAnalytics.consent = existing;
            if (window.BirklyAnalytics) window.BirklyAnalytics.consent = existing;
            return;
        }
        var script = document.currentScript || document.querySelector('script[src*="birkly-analytics-consent"]');
        var text = (script && script.getAttribute('data-consent-text')) || '';
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { showBanner(text); });
        } else {
            showBanner(text);
        }
    }

    window.BirklyAnalyticsConsent = {
        get: getConsent,
        set: setConsent,
        show: showBanner
    };

    init();
})();
