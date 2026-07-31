/**
 * Birkly loading / animation helpers (P51-M4)
 *
 * Additive, opt-in helpers that let template-first pages avoid FOUC (flash of
 * unstyled / un-templated content) and premature CSS animations. When none of
 * the opt-in hooks below are present the default page behavior is completely
 * unchanged — nothing is hidden, no classes are toggled, no styles injected
 * beyond a single (harmless) <style> tag.
 *
 * ------------------------------------------------------------------------
 * Opt-in API (all optional — pick what you need in your static HTML):
 *
 *   <html data-birkly-loading>            Hide the page until templates are
 *                                         processed, then fade it in.
 *   <body data-birkly-reveal>             Same, applied to <body>.
 *   <html data-birkly-hold-animations>    Pause CSS animations & transitions
 *                                         inside the element until ready.
 *   <any  data-birkly-skeleton>           Show a shimmer placeholder on that
 *                                         element until its region (or the
 *                                         page) is processed.
 *
 * "Ready" is reached when the FIRST of these happens:
 *   1. `birkly:templates-processed` fires (full-document render finished), OR
 *   2. an SSR marker is present — <meta name="birkly-ssr" content="1"> or
 *      <html data-birkly-ssr> — in which case content is already rendered, so
 *      we reveal on the next microtask, OR
 *   3. a safety timeout (default 8000ms) elapses, so a page can never stay
 *      blank forever even if something goes wrong.
 *
 * On ready the helper adds the `birkly-ready` class to every opt-in element
 * (and to <html>), which flips the injected CSS to reveal the page, resume
 * animations, and remove skeleton placeholders.
 *
 * JS API (also on window.Birkly.loading in the browser bundle):
 *   Birkly.loading.reveal()             Force reveal now (idempotent).
 *   Birkly.loading.showSkeleton(el)     Add a shimmer placeholder.
 *   Birkly.loading.hideSkeleton(el)     Remove a shimmer placeholder.
 *   Birkly.loading.isReady()            Boolean — has the page been revealed?
 *
 * No third-party dependencies. Works in the browser (auto-inits) and under
 * Node/CommonJS (exports pure helpers + `create(win, doc, opts)` for tests).
 * ------------------------------------------------------------------------
 */
(function (root) {
    'use strict';

    var READY_CLASS = 'birkly-ready';
    var SKELETON_CLASS = 'birkly-skeleton';
    var STYLE_ID = 'birkly-loading-styles';
    var DEFAULT_TIMEOUT = 8000;

    var OPT_IN_SELECTOR =
        '[data-birkly-loading],[data-birkly-reveal],[data-birkly-hold-animations]';

    var STYLE_CSS = [
        /* Reveal: keep opt-in elements invisible until birkly-ready is added. */
        '[data-birkly-loading]:not(.' + READY_CLASS + '),',
        '[data-birkly-reveal]:not(.' + READY_CLASS + '){opacity:0!important;}',
        '[data-birkly-loading].' + READY_CLASS + ',',
        '[data-birkly-reveal].' + READY_CLASS + '{opacity:1!important;',
        'transition:opacity .25s ease-in;}',
        /* Animation holdback: freeze animations/transitions until ready. */
        '[data-birkly-hold-animations]:not(.' + READY_CLASS + ') *,',
        '[data-birkly-hold-animations]:not(.' + READY_CLASS + ') *::before,',
        '[data-birkly-hold-animations]:not(.' + READY_CLASS + ') *::after{',
        'animation:none!important;transition:none!important;}',
        /* Skeleton shimmer placeholder. */
        '.' + SKELETON_CLASS + '{position:relative;min-height:1em;',
        'color:transparent!important;border-radius:4px;',
        'background:linear-gradient(90deg,#eee 25%,#f5f5f5 37%,#eee 63%);',
        'background-size:400% 100%;',
        'animation:birkly-shimmer 1.4s ease infinite;}',
        '.' + SKELETON_CLASS + ' *{visibility:hidden!important;}',
        '@keyframes birkly-shimmer{',
        '0%{background-position:100% 50%}100%{background-position:0 50%}}'
    ].join('');

    /**
     * Detect a server-side-rendered page. Pure — safe to unit test.
     */
    function isSsrRendered(doc) {
        if (!doc) return false;
        try {
            var de = doc.documentElement;
            if (de && typeof de.hasAttribute === 'function' &&
                de.hasAttribute('data-birkly-ssr')) {
                return true;
            }
        } catch (e) { /* ignore */ }
        try {
            if (typeof doc.querySelector === 'function' &&
                doc.querySelector('meta[name="birkly-ssr"]')) {
                return true;
            }
        } catch (e) { /* ignore */ }
        return false;
    }

    function addClass(el, cls) {
        if (el && el.classList && typeof el.classList.add === 'function') {
            el.classList.add(cls);
        }
    }

    function removeClass(el, cls) {
        if (el && el.classList && typeof el.classList.remove === 'function') {
            el.classList.remove(cls);
        }
    }

    /**
     * Create a loading-helper instance bound to a window/document pair.
     * `doc`/`win` only need a small, easily-mockable surface (documentElement,
     * body, querySelector(All), addEventListener, createElement, head, and
     * setTimeout/clearTimeout on `win`). This keeps the logic unit-testable.
     */
    function create(win, doc, opts) {
        opts = opts || {};
        win = win || (typeof window !== 'undefined' ? window : {});
        doc = doc || (typeof document !== 'undefined' ? document : {});

        var timeoutMs = typeof opts.timeout === 'number' ? opts.timeout : DEFAULT_TIMEOUT;
        var ready = false;
        var timer = null;
        var listening = false;

        function queryAll(selector) {
            if (typeof doc.querySelectorAll !== 'function') return [];
            try {
                return Array.prototype.slice.call(doc.querySelectorAll(selector));
            } catch (e) {
                return [];
            }
        }

        /**
         * Every element that should receive `birkly-ready`: <html>, <body>, and
         * any element carrying an opt-in attribute (deduplicated).
         */
        function optInTargets() {
            var targets = [];
            if (doc.documentElement) targets.push(doc.documentElement);
            if (doc.body && targets.indexOf(doc.body) === -1) targets.push(doc.body);
            queryAll(OPT_IN_SELECTOR).forEach(function (el) {
                if (el && targets.indexOf(el) === -1) targets.push(el);
            });
            return targets;
        }

        function injectStyles() {
            if (typeof doc.getElementById === 'function' && doc.getElementById(STYLE_ID)) {
                return;
            }
            if (typeof doc.createElement !== 'function') return;
            var head = doc.head ||
                (typeof doc.getElementsByTagName === 'function'
                    ? doc.getElementsByTagName('head')[0]
                    : null) ||
                doc.documentElement;
            if (!head || typeof head.appendChild !== 'function') return;
            var style = doc.createElement('style');
            style.id = STYLE_ID;
            style.textContent = STYLE_CSS;
            head.appendChild(style);
        }

        function showSkeleton(el) {
            addClass(el, SKELETON_CLASS);
        }

        function hideSkeleton(el) {
            removeClass(el, SKELETON_CLASS);
            if (el && typeof el.removeAttribute === 'function') {
                el.removeAttribute('data-birkly-skeleton');
            }
        }

        function applyDeclaredSkeletons() {
            queryAll('[data-birkly-skeleton]').forEach(showSkeleton);
        }

        function clearAllSkeletons() {
            queryAll('.' + SKELETON_CLASS).forEach(hideSkeleton);
        }

        function reveal() {
            if (ready) return;
            ready = true;
            if (timer != null && typeof win.clearTimeout === 'function') {
                win.clearTimeout(timer);
            }
            timer = null;
            optInTargets().forEach(function (el) { addClass(el, READY_CLASS); });
            clearAllSkeletons();
            try {
                if (typeof doc.dispatchEvent === 'function' &&
                    typeof win.CustomEvent === 'function') {
                    doc.dispatchEvent(new win.CustomEvent('birkly:ready'));
                }
            } catch (e) { /* ignore */ }
        }

        function onProcessed() { reveal(); }

        function onRegionProcessed(event) {
            if (event && event.target) hideSkeleton(event.target);
        }

        /**
         * Wire up styles + listeners. Idempotent. Returns the api object.
         */
        function setup() {
            injectStyles();
            applyDeclaredSkeletons();

            if (isSsrRendered(doc)) {
                // Content already rendered by the server — reveal ASAP but let
                // the current tick finish first (mirrors event-loop timing).
                var micro = typeof win.queueMicrotask === 'function'
                    ? win.queueMicrotask
                    : function (fn) {
                        if (typeof win.setTimeout === 'function') win.setTimeout(fn, 0);
                        else fn();
                    };
                micro(reveal);
                return api;
            }

            if (!listening && typeof doc.addEventListener === 'function') {
                doc.addEventListener('birkly:templates-processed', onProcessed);
                doc.addEventListener('birkly:region-processed', onRegionProcessed);
                listening = true;
            }

            if (typeof win.setTimeout === 'function') {
                timer = win.setTimeout(reveal, timeoutMs);
            }
            return api;
        }

        var api = {
            setup: setup,
            reveal: reveal,
            showSkeleton: showSkeleton,
            hideSkeleton: hideSkeleton,
            isReady: function () { return ready; },
            // Exposed mainly for tests / advanced callers.
            _optInTargets: optInTargets,
            READY_CLASS: READY_CLASS,
            SKELETON_CLASS: SKELETON_CLASS,
            timeout: timeoutMs
        };
        return api;
    }

    var moduleApi = {
        create: create,
        isSsrRendered: isSsrRendered,
        READY_CLASS: READY_CLASS,
        SKELETON_CLASS: SKELETON_CLASS,
        STYLE_ID: STYLE_ID,
        DEFAULT_TIMEOUT: DEFAULT_TIMEOUT,
        OPT_IN_SELECTOR: OPT_IN_SELECTOR
    };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = moduleApi;
    }

    // Browser: attach factory output to Birkly.loading and auto-init when the
    // full birkly-client.js bundle is NOT the one wiring it up. The bundle
    // rebuilds window.Birkly at the end, so it wires loading itself; this branch
    // only matters when birkly-loading.js is loaded as a standalone dev module.
    if (root && typeof window !== 'undefined' && typeof document !== 'undefined') {
        root.Birkly = root.Birkly || {};
        root.Birkly.loadingModule = moduleApi;
        if (!root.Birkly.loading) {
            var inst = create(window, document);
            root.Birkly.loading = inst;
            inst.setup();
        }
    }
})(typeof globalThis !== 'undefined' ? globalThis : (typeof window !== 'undefined' ? window : this));
