/**
 * Birkly Self-Hosted Analytics Tracker (P61)
 *
 * Usage:
 *   <script>
 *     window.HaruAnalytics = {
 *       trackingId: 'your_tracking_id',
 *       cmsUrl: 'https://your-cms-domain.com'
 *     };
 *   </script>
 *   <script src="https://your-cms-domain.com/birkly-analytics-consent.js"></script>
 *   <script src="https://your-cms-domain.com/birkly-analytics.js"></script>
 */

(function() {
    'use strict';

    const config = window.HaruAnalytics || {};
    const trackingId = config.trackingId || '';
    const cmsUrl = (config.cmsUrl || '').replace(/\/$/, '');

    if (!trackingId || !cmsUrl) {
        console.warn('Birkly Analytics: trackingId and cmsUrl are required');
        return;
    }

    let publicConfig = window.BirklyAnalyticsPublicConfig || null;
    let started = false;

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
        return config.consent || '';
    }

    function consentGranted() {
        const consent = readConsentCookie();
        return consent === 'granted' || consent === '1' || consent === true;
    }

    function consentRequired() {
        if (publicConfig && typeof publicConfig.consent_required === 'boolean') {
            return publicConfig.consent_required;
        }
        return !!config.requireConsent;
    }

    function eventsEnabled() {
        if (publicConfig && typeof publicConfig.events === 'boolean') {
            return publicConfig.events;
        }
        return !!(config.enableEvents);
    }

    function allowsAnonymousPageView(payload) {
        if (!publicConfig || publicConfig.anonymous_counts !== true) {
            return false;
        }
        const eventType = String((payload && payload.event_type) || 'page_view').toLowerCase();
        return eventType === 'page_view' || eventType === 'pageview' || eventType === '';
    }

    function canTrack(payload) {
        if (publicConfig && publicConfig.collecting === false) {
            return false;
        }
        if (consentRequired() && !consentGranted()) {
            return allowsAnonymousPageView(payload);
        }
        return true;
    }

    function getUtmParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            utm_source: params.get('utm_source') || '',
            utm_medium: params.get('utm_medium') || '',
            utm_campaign: params.get('utm_campaign') || '',
            utm_term: params.get('utm_term') || '',
            utm_content: params.get('utm_content') || ''
        };
    }

    function sendTrack(payload) {
        if (!canTrack(payload)) {
            return;
        }
        const consent = readConsentCookie();
        const anonymous = consentRequired() && !consentGranted() && allowsAnonymousPageView(payload);
        if (anonymous) {
            payload = {
                tracking_id: trackingId,
                domain: window.location.hostname,
                path: window.location.pathname + window.location.search,
                event_type: 'page_view',
                consent: 'anonymous'
            };
        } else if (consent) {
            payload.consent = consent;
        } else if (!consentRequired()) {
            // Minimal / Off server-side; omit consent field
        }

        const url = cmsUrl + '/api/insights.php?action=track';
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

    function trackPageView() {
        const utm = getUtmParams();
        sendTrack({
            tracking_id: trackingId,
            domain: window.location.hostname,
            path: window.location.pathname + window.location.search,
            referrer: document.referrer || '',
            screen_width: window.screen.width || 0,
            screen_height: window.screen.height || 0,
            language: navigator.language || navigator.userLanguage || '',
            session_id: getSessionId(),
            event_type: 'page_view',
            utm_source: utm.utm_source,
            utm_medium: utm.utm_medium,
            utm_campaign: utm.utm_campaign,
            utm_term: utm.utm_term,
            utm_content: utm.utm_content
        });
    }

    function bindAdvancedEvents() {
        if (!eventsEnabled()) {
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

    function startTracking() {
        if (started) return;
        if (publicConfig && publicConfig.collecting === false) return;
        started = true;

        bindAdvancedEvents();

        if (document.readyState === 'complete') {
            trackPageView();
        } else {
            window.addEventListener('load', trackPageView);
        }

        let lastUrl = location.href;
        new MutationObserver(function() {
            const url = location.href;
            if (url !== lastUrl) {
                lastUrl = url;
                trackPageView();
            }
        }).observe(document, { subtree: true, childList: true });

        window.addEventListener('popstate', function() {
            setTimeout(trackPageView, 100);
        });

        document.addEventListener('birkly:analytics-consent', function (e) {
            if (e && e.detail && e.detail.consent === 'granted') {
                trackPageView();
            }
        });
    }

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

    // Prefer config from consent helper if already loaded; otherwise fetch.
    function boot() {
        if (window.BirklyAnalyticsPublicConfig) {
            publicConfig = window.BirklyAnalyticsPublicConfig;
            startTracking();
            return;
        }
        fetch(cmsUrl + '/api/insights.php?action=public_config', {
            credentials: 'omit',
            cache: 'no-store'
        }).then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload && payload.status === 'ok' && payload.data) {
                    publicConfig = payload.data;
                    window.BirklyAnalyticsPublicConfig = publicConfig;
                    if (window.HaruAnalytics) {
                        window.HaruAnalytics.requireConsent = !!publicConfig.consent_required;
                        window.HaruAnalytics.enableEvents = !!publicConfig.events;
                    }
                }
            })
            .catch(function () { /* keep defaults */ })
            .then(function () {
                startTracking();
            });
    }

    boot();
})();
