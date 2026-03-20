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
    
    // Track page view
    function trackPageView() {
        const data = {
            tracking_id: trackingId,
            domain: window.location.hostname,
            path: window.location.pathname + window.location.search,
            referrer: document.referrer || '',
            screen_width: window.screen.width || 0,
            screen_height: window.screen.height || 0,
            language: navigator.language || navigator.userLanguage || '',
            session_id: getSessionId()
        };
        
        // Send tracking data
        const url = cmsUrl + '/api/analytics.php?action=track';
        
        // Use sendBeacon if available (more reliable)
        if (navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
            navigator.sendBeacon(url, blob);
        } else {
            // Fallback to fetch
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data),
                keepalive: true
            }).catch(function(err) {
                // Silently fail - don't interrupt user experience
                console.debug('Birkly Analytics: Tracking failed', err);
            });
        }
    }
    
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
        const data = {
            tracking_id: trackingId,
            domain: window.location.hostname,
            path: window.location.pathname,
            referrer: document.referrer || '',
            screen_width: window.screen.width || 0,
            screen_height: window.screen.height || 0,
            language: navigator.language || '',
            session_id: getSessionId(),
            event_name: eventName,
            event_data: eventData || {}
        };
        
        const url = cmsUrl + '/api/analytics.php?action=track';
        
        if (navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify(data)], { type: 'application/json' });
            navigator.sendBeacon(url, blob);
        } else {
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data),
                keepalive: true
            }).catch(function(err) {
                console.debug('Birkly Analytics: Event tracking failed', err);
            });
        }
    };
})();

