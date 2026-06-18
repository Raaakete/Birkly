/**
 * Birkly Hydrate — lightweight read path for publish-time snapshots (M4).
 *
 * Sites with baked HTML load this script instead of the full birkly-client.js when
 * a page snapshot exists. Falls back to full client only when hydration cannot apply.
 *
 * Usage:
 *   <meta name="birkly-baked" content="1">
 *   <script defer src="/birkly-hydrate.js" data-cms-url="https://cms.example/Birkly-0.1"></script>
 *
 * Optional inline snapshot (no network):
 *   <script>window.BIRKLY_PAGE_SNAPSHOT = { page, slug, data, requests, rendered };</script>
 */
(function () {
    'use strict';

    function resolveCmsUrl() {
        const script = document.querySelector('script[data-cms-url]');
        if (script) {
            return String(script.getAttribute('data-cms-url') || '').replace(/\/$/, '');
        }
        const host = window.location.hostname;
        const isLocal = host === 'localhost' || host === '127.0.0.1' || host === '[::1]';
        if (isLocal) {
            return 'http://localhost:8080';
        }
        return window.location.origin;
    }

    const cmsUrl = resolveCmsUrl();

    function isBakedPage() {
        return !!document.querySelector('meta[name="birkly-baked"]');
    }

    function pageRoute() {
        const pageMeta = document.querySelector('meta[name="birkly-page"]');
        const slugMeta = document.querySelector('meta[name="birkly-slug"]');
        if (pageMeta && pageMeta.content) {
            const page = pageMeta.content.trim();
            if (!/^[a-zA-Z0-9_-]+$/.test(page)) return null;
            const slug = slugMeta && slugMeta.content ? slugMeta.content.trim() : '';
            if (slug && !/^[a-zA-Z0-9_-]+$/.test(slug)) return null;
            return { page, slug: slug || null };
        }

        const path = (window.location.pathname || '').toLowerCase();
        const file = path.split('/').pop() || 'index.html';
        if (file === '' || file === 'index.html') return { page: 'index', slug: null };
        const base = file.replace(/\.html$/, '');
        if (!/^[a-z0-9_-]+$/.test(base)) return null;
        const slug = new URLSearchParams(window.location.search).get('slug');
        if (slug && /^[a-zA-Z0-9_-]+$/.test(slug)) {
            return { page: base, slug };
        }
        return { page: base, slug: null };
    }

    function loadFullClient() {
        const script = document.createElement('script');
        script.src = cmsUrl + '/birkly-client.js';
        script.setAttribute('data-cms-url', cmsUrl);
        if (document.querySelector('meta[name="birkly-baked"]')) {
            script.setAttribute('data-birkly-no-floating-language-selector', 'true');
        }
        document.body.appendChild(script);
    }

    function applyHtml(selector, html) {
        if (!html) return false;
        const el = document.querySelector(selector);
        if (!el) return false;
        el.innerHTML = html;
        return true;
    }

    function seedPageBundle(snapshot) {
        if (!snapshot || !snapshot.data) return;

        window.Birkly = window.Birkly || {};
        const pb = {
            loaded: true,
            data: Object.assign({}, snapshot.data),
            subIndex: {},
            keys: new Set(),
            failed: new Set()
        };
        const requests = Array.isArray(snapshot.requests) ? snapshot.requests : [];
        const errors = Array.isArray(snapshot.errors) ? snapshot.errors : [];

        requests.forEach(function (req) {
            if (!req || !req.into) return;
            pb.keys.add(req.into);
            const failed = errors.some(function (err) {
                const s = String(err);
                return s.indexOf(req.into) !== -1
                    || (req.collection && s.indexOf(req.collection) !== -1);
            });
            const rows = snapshot.data[req.into];
            if (failed || (req.type !== 'get_entry' && !Array.isArray(rows))) {
                pb.failed.add(req.into);
            }
        });

        window.Birkly.pageBundle = pb;
    }

    function finishReady() {
        const root = document.documentElement;
        root.classList.remove('birkly-loading');
        root.classList.add('birkly-ready', 'birkly-hydrated');
        try {
            document.dispatchEvent(new CustomEvent('birkly:chrome-ready'));
            document.dispatchEvent(new CustomEvent('birkly:render-complete'));
        } catch (e) { /* ignore */ }
    }

    async function fetchSnapshot(route) {
        const inline = window.BIRKLY_PAGE_SNAPSHOT || window.KEKABA_PAGE_SNAPSHOT;
        if (inline && inline.page === route.page
            && (route.slug ? inline.slug === route.slug : !inline.slug)
            && inline.data) {
            return inline;
        }

        let url = cmsUrl + '/api/page_snapshot.php?page=' + encodeURIComponent(route.page);
        if (route.slug) {
            url += '&slug=' + encodeURIComponent(route.slug);
        }
        const res = await fetch(url, { credentials: 'same-origin' });
        const json = await res.json().catch(function () { return {}; });
        if (!res.ok || json.status !== 'ok') {
            return null;
        }
        return json;
    }

    async function init() {
        const route = pageRoute();
        if (!route) {
            loadFullClient();
            return;
        }

        try {
            if (isBakedPage()) {
                finishReady();
                return;
            }

            const snapshot = await fetchSnapshot(route);
            if (!snapshot || !snapshot.rendered || !snapshot.rendered.main) {
                loadFullClient();
                return;
            }

            seedPageBundle(snapshot);
            applyHtml('main', snapshot.rendered.main);
            applyHtml('header-component', snapshot.rendered.header);
            applyHtml('footer-component', snapshot.rendered.footer);

            finishReady();
        } catch (err) {
            console.error('[Birkly] Hydrate failed, loading full client', err);
            loadFullClient();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
