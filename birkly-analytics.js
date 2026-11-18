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

    function getVisitorToken() {
        const key = 'birkly_vid_' + trackingId;
        let token = localStorage.getItem(key);
        if (!token) {
            token = 'vid_' + Date.now() + '_' + Math.random().toString(36).substr(2, 12);
            localStorage.setItem(key, token);
        }
        return token;
    }

    function getEntrancePath() {
        const key = 'birkly_entrance_' + trackingId;
        let entrance = sessionStorage.getItem(key);
        if (!entrance) {
            entrance = window.location.pathname + window.location.search;
            sessionStorage.setItem(key, entrance);
        }
        return entrance;
    }

    function getTimezone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        } catch (err) {
            return '';
        }
    }

    function bindSpaNavigation(onNavigate) {
        let lastUrl = location.href;
        function notify() {
            const url = location.href;
            if (url !== lastUrl) {
                lastUrl = url;
                onNavigate();
            }
        }
        ['pushState', 'replaceState'].forEach(function (method) {
            const original = history[method];
            if (typeof original !== 'function') {
                return;
            }
            history[method] = function () {
                const result = original.apply(this, arguments);
                notify();
                return result;
            };
        });
        window.addEventListener('popstate', function () {
            setTimeout(notify, 0);
        });
        window.addEventListener('hashchange', notify);
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

    function readConsentPrefs() {
        const parts = (document.cookie || '').split(';');
        for (let i = 0; i < parts.length; i++) {
            const p = parts[i].trim();
            if (p.indexOf('birkly_analytics_consent_prefs=') === 0) {
                try {
                    return JSON.parse(decodeURIComponent(p.slice('birkly_analytics_consent_prefs='.length)));
                } catch (err) {
                    return null;
                }
            }
        }
        if (window.BirklyAnalytics && window.BirklyAnalytics.consentPrefs) {
            return window.BirklyAnalytics.consentPrefs;
        }
        return null;
    }

    function consentAllowsAnyDetail() {
        const consent = readConsentCookie();
        if (consent === 'granted' || consent === '1' || consent === true) {
            return true;
        }
        if (consent !== 'custom') {
            return false;
        }
        const prefs = readConsentPrefs();
        if (!prefs || typeof prefs !== 'object') {
            return false;
        }
        return !!(prefs.sessions || prefs.devices || prefs.campaigns || prefs.events);
    }

    function consentGranted() {
        const consent = readConsentCookie();
        return consent === 'granted' || consent === '1' || consent === true;
    }

    function consentPrefEnabled(key) {
        if (consentGranted()) {
            return true;
        }
        const consent = readConsentCookie();
        if (consent !== 'custom') {
            return false;
        }
        const prefs = readConsentPrefs();
        return !!(prefs && prefs[key]);
    }

    function eventsAllowedByConsent() {
        if (!eventsEnabled()) {
            return false;
        }
        if (!consentRequired()) {
            return true;
        }
        if (consentGranted()) {
            return true;
        }
        if (readConsentCookie() === 'custom') {
            return consentPrefEnabled('events');
        }
        return false;
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
        if (consentRequired() && !consentGranted() && !consentAllowsAnyDetail()) {
            return allowsAnonymousPageView(payload);
        }
        if (consentRequired() && readConsentCookie() === 'custom') {
            const eventType = String((payload && payload.event_type) || 'page_view').toLowerCase();
            if (eventType !== 'page_view' && eventType !== 'pageview' && eventType !== '' && eventType !== 'page_exit' && eventType !== 'not_found') {
                return consentPrefEnabled('events');
            }
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
        const anonymous = consentRequired() && !consentGranted() && !consentAllowsAnyDetail() && allowsAnonymousPageView(payload);
        const prefs = readConsentPrefs();
        if (anonymous) {
            const ref = document.referrer || '';
            payload = {
                tracking_id: trackingId,
                domain: window.location.hostname,
                path: window.location.pathname + window.location.search,
                event_type: 'page_view',
                consent: 'anonymous',
                consent_state: 'anonymous',
                referrer: ref
            };
        } else {
            if (consent) {
                payload.consent = consent;
            }
            if (prefs && consent === 'custom') {
                payload.consent_prefs = prefs;
            }
            if (consentRequired()) {
                if (consentGranted()) {
                    payload.consent_state = 'granted';
                } else if (consent === 'custom') {
                    payload.consent_state = 'custom';
                } else {
                    payload.consent_state = 'anonymous';
                }
            } else {
                payload.consent_state = 'not_required';
            }
            if (consentPrefEnabled('sessions') || consentGranted() || !consentRequired()) {
                payload.visitor_token = getVisitorToken();
            }
            if (!consentPrefEnabled('sessions') && !consentGranted() && consent === 'custom') {
                delete payload.session_id;
                delete payload.visitor_token;
            }
            if (!consentPrefEnabled('devices') && !consentGranted() && consent === 'custom') {
                delete payload.screen_width;
                delete payload.screen_height;
                delete payload.viewport_width;
                delete payload.viewport_height;
                delete payload.language;
                delete payload.timezone;
            }
            if (!consentPrefEnabled('campaigns') && !consentGranted() && consent === 'custom') {
                delete payload.utm_source;
                delete payload.utm_medium;
                delete payload.utm_campaign;
                delete payload.utm_term;
                delete payload.utm_content;
                if (!payload.referrer) {
                    payload.referrer = document.referrer || '';
                }
            }
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
        const nfMeta = document.querySelector('meta[name="birkly-not-found"]');
        if (nfMeta) {
            const requested = nfMeta.getAttribute('content') || '';
            sendTrack({
                tracking_id: trackingId,
                domain: window.location.hostname,
                path: requested || (window.location.pathname + window.location.search),
                entrance_path: getEntrancePath(),
                referrer: document.referrer || '',
                screen_width: window.screen.width || 0,
                screen_height: window.screen.height || 0,
                viewport_width: window.innerWidth || 0,
                viewport_height: window.innerHeight || 0,
                language: navigator.language || navigator.userLanguage || '',
                timezone: getTimezone(),
                session_id: getSessionId(),
                event_type: 'not_found',
                requested_path: requested
            });
            return;
        }
        sendTrack({
            tracking_id: trackingId,
            domain: window.location.hostname,
            path: window.location.pathname + window.location.search,
            entrance_path: getEntrancePath(),
            referrer: document.referrer || '',
            screen_width: window.screen.width || 0,
            screen_height: window.screen.height || 0,
            viewport_width: window.innerWidth || 0,
            viewport_height: window.innerHeight || 0,
            language: navigator.language || navigator.userLanguage || '',
            timezone: getTimezone(),
            session_id: getSessionId(),
            event_type: 'page_view',
            utm_source: utm.utm_source,
            utm_medium: utm.utm_medium,
            utm_campaign: utm.utm_campaign,
            utm_term: utm.utm_term,
            utm_content: utm.utm_content
        });
    }

    function trackPageExit() {
        sendTrack({
            tracking_id: trackingId,
            domain: window.location.hostname,
            path: window.location.pathname + window.location.search,
            session_id: getSessionId(),
            event_type: 'page_exit'
        });
    }

    function bindAdvancedEvents() {
        if (!eventsAllowedByConsent()) {
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
                        path: window.location.pathname + window.location.search,
                        event_type: 'outbound_click',
                        referrer: url.href,
                        session_id: getSessionId(),
                        event_data: { href: url.href }
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

        bindSpaNavigation(trackPageView);

        window.addEventListener('pagehide', trackPageExit);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                trackPageExit();
            }
        });

        document.addEventListener('birkly:analytics-consent', function (e) {
            if (e && e.detail && (e.detail.consent === 'granted' || e.detail.consent === 'custom')) {
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
