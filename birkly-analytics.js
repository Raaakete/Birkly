/**
 * Birkly Self-Hosted Analytics Tracker
 * Zero-dependency analytics tracking script
 * 
 * Usage:
 *   <script>
 *     window.HaruAnalytics = {
 *       trackingId: 'your_tracking_id',
 *       cmsUrl: 'https://your-cms-domain.com'
 *     };
 *   </script>
 *   <script src="https://your-cms-domain.com/birkly-analytics.js"></script>
 */

(function() {
    'use strict';
    
    // Get configuration
    const config = window.HaruAnalytics || {};
    const trackingId = config.trackingId || '';
    const cmsUrl = (config.cmsUrl || '').replace(/\/$/, ''); // Remove trailing slash
    
    if (!trackingId || !cmsUrl) {
        console.warn('Birkly Analytics: trackingId and cmsUrl are required');
        return;
    }
    
    // Generate or get session ID
    function getSessionId() {
        const key = 'birkly_session_' + trackingId;
        let sessionId = sessionStorage.getItem(key);
        if (!sessionId) {
            sessionId = 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            sessionStorage.setItem(key, sessionId);
        }
        return sessionId;
    }
    
    function readConsentCookie() {
        const parts = (document.cookie || '').split(';');
        for (let i = 0; i < parts.length; i++) {
            const p = parts[i].trim();
            if (p.indexOf('birkly_analytics_consent=') === 0) {
                return decodeURIComponent(p.slice('birkly_analytics_consent='.length));
            }
        }
        return (config.consent || window.HaruAnalytics && window.HaruAnalytics.consent || '');
    }

    function sendTrack(payload) {
        const consent = readConsentCookie();
        if (consent) {
            payload.consent = consent;
        }
        // When consent helper is present and not granted, skip client send
        // (server also gates Standard+).
        if (window.BirklyAnalyticsConsent && consent !== 'granted' && consent !== '1') {
            // Allow Minimal/Off server decisions; still send only if no consent helper required
            if (config.requireConsent || (window.HaruAnalytics && window.HaruAnalytics.requireConsent)) {
                return;
            }
        }

        const url = cmsUrl + '/api/analytics.php?action=track';
        if (navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
            navigator.sendBeacon(url, blob);
        } else {
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
                keepalive: true
            }).catch(function (err) {
                console.debug('Birkly Analytics: Tracking failed', err);
            });
        }
    }

    // Track page view
    function trackPageView() {
        sendTrack({
            tracking_id: trackingId,
            domain: window.location.hostname,
            path: window.location.pathname + window.location.search,
            referrer: document.referrer || '',
            screen_width: window.screen.width || 0,
            screen_height: window.screen.height || 0,
            language: navigator.language || navigator.userLanguage || '',
            session_id: getSessionId(),
            event_type: 'page_view'
        });
    }

    function bindAdvancedEvents() {
        if (!(config.enableEvents || (window.HaruAnalytics && window.HaruAnalytics.enableEvents))) {
            return;
        }
        document.addEventListener('click', function (e) {
            var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            if (!a) return;
            var href = a.getAttribute('href') || '';
            if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
            try {
                var url = new URL(href, window.location.href);
                if (url.origin !== window.location.origin) {
                    sendTrack({
                        tracking_id: trackingId,
                        domain: window.location.hostname,
                        path: window.location.pathname,
                        event_type: 'outbound_click',
                        referrer: url.href,
                        session_id: getSessionId()
                    });
                }
            } catch (err) { /* ignore */ }
        }, true);
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || form.tagName !== 'FORM') return;
            sendTrack({
                tracking_id: trackingId,
                domain: window.location.hostname,
                path: window.location.pathname,
                event_type: 'form_submit',
                session_id: getSessionId(),
                event_data: { form_id: form.id || form.getAttribute('name') || '' }
            });
        }, true);
    }
    bindAdvancedEvents();
    
    // Track page view on load
    if (document.readyState === 'complete') {
        trackPageView();
    } else {
        window.addEventListener('load', trackPageView);
    }
    
    // Track page views on history changes (SPA support)
    let lastUrl = location.href;
    new MutationObserver(function() {
        const url = location.href;
        if (url !== lastUrl) {
            lastUrl = url;
            trackPageView();
        }
    }).observe(document, { subtree: true, childList: true });
    
    // Also listen to popstate for back/forward navigation
    window.addEventListener('popstate', function() {
        setTimeout(trackPageView, 100);
    });
    
    // Expose API for manual tracking
    window.HaruAnalyticsTrack = function(eventName, eventData) {
        sendTrack({
            tracking_id: trackingId,
            domain: window.location.hostname,
            path: window.location.pathname,
            referrer: document.referrer || '',
            screen_width: window.screen.width || 0,
            screen_height: window.screen.height || 0,
            language: navigator.language || '',
            session_id: getSessionId(),
            event_type: eventName || 'plugin_event',
            event_name: eventName,
            event_data: eventData || {}
        });
    };
})();

