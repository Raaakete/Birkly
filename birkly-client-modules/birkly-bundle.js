/**
 * Birkly bundle module — batched public reads (M4).
 * Load after birkly-utils.js and before birkly-client.js in development.
 */
(function (Birkly) {
    'use strict';

    if (!Birkly) Birkly = {};

    Birkly.bundle = {
        getTemplateCollectionSlugs(templateStr) {
            const collections = [];
            const quoted = templateStr.match(/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+['"]([^'"]+)['"][^}]*\}/g);
            (quoted || []).forEach((match) => {
                const m = match.match(/['"]([^'"]+)['"]/);
                if (m && collections.indexOf(m[1]) === -1) collections.push(m[1]);
            });
            const unquoted = templateStr.match(/\{for\s+(?:each\s+)?[a-zA-Z0-9_]+\s+in\s+([a-zA-Z0-9_\.]+)(?:\s+with\s+[^{}]+?)?(?:\s+ordered\s+by\s+[^{}]+?)?\s*\}/g);
            (unquoted || []).forEach((match) => {
                const m = match.match(/\s+in\s+([a-zA-Z0-9_\.]+)/);
                if (!m) return;
                const name = m[1];
                if (name.includes('.')) return;
                if (collections.indexOf(name) === -1) collections.push(name);
            });
            return collections;
        },

        collectBundleRequests() {
            const requests = [];
            const seen = new Set();
            const add = (req) => {
                if (!req || !req.type || !req.into) return;
                const key = JSON.stringify(req);
                if (seen.has(key)) return;
                seen.add(key);
                requests.push(req);
            };

            const parts = [];
            const mainEl = document.querySelector('main');
            if (mainEl) parts.push(mainEl.innerHTML || '');
            else if (document.body) parts.push(document.body.innerHTML || '');
            const combined = parts.join('\n');

            Birkly.bundle.getTemplateCollectionSlugs(combined).forEach((collection) => {
                add({ type: 'list_entries', into: collection, collection });
            });
            add({ type: 'list_entries', into: 'site_elements', collection: 'site_elements' });

            document.querySelectorAll('[data-birkly-prefetch]').forEach((el) => {
                const type = el.getAttribute('data-birkly-prefetch');
                const into = el.getAttribute('data-birkly-into');
                if (!type || !into) return;
                if (type === 'sub_entries') {
                    add({
                        type: 'sub_entries',
                        into,
                        parent_collection: el.getAttribute('data-parent-collection') || '',
                        parent_entry_id: el.getAttribute('data-parent-entry-id') || '',
                        sub_collection: el.getAttribute('data-sub-collection') || ''
                    });
                } else if (type === 'entries') {
                    add({
                        type: 'list_entries',
                        into,
                        collection: el.getAttribute('data-collection') || '',
                        filter_field: el.getAttribute('data-filter-field') || '',
                        filter_value: el.getAttribute('data-filter-value') || ''
                    });
                }
            });

            return requests;
        },

        applyBundleResponse(requests, json) {
            if (!Birkly.pageBundle.data) Birkly.pageBundle.data = {};
            Object.assign(Birkly.pageBundle.data, json.data);
            const errorList = Array.isArray(json.errors) ? json.errors : [];
            requests.forEach((req) => {
                Birkly.pageBundle.keys.add(req.into);
                const failed = errorList.some((err) => String(err).indexOf(req.into) !== -1);
                const rows = json.data[req.into];
                if (failed || (req.type !== 'get_entry' && !Array.isArray(rows))) {
                    Birkly.pageBundle.failed.add(req.into);
                }
            });
            Birkly.pageBundle.loaded = true;
        },

        async loadPageBundle(requests, options = {}) {
            if (!requests || !requests.length) return false;
            const label = options.label || 'page bundle';
            const url = Birkly.cmsUrl + '/api/page_bundle.php';
            try {
                Birkly.log('Birkly: loadPageBundle', { count: requests.length, label });
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        language: Birkly.currentLanguage || 'en',
                        requests
                    }),
                    credentials: 'include'
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok || json.status !== 'ok' || !json.data) {
                    Birkly.warn('Birkly: page bundle failed', json);
                    return false;
                }
                Birkly.bundle.applyBundleResponse(requests, json);
                return true;
            } catch (err) {
                Birkly.warn('Birkly: page bundle error', err);
                return false;
            }
        }
    };
})(window.Birkly || (window.Birkly = {}));
