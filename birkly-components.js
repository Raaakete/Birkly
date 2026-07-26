/**
 * Birkly Components — enhance-only helpers (P45-M5)
 *
 * Use these after CMS Light DOM regions have rendered.
 *
 * CRITICAL RULES:
 * - NEVER wipe or replace innerHTML of CMS-rendered regions in connectedCallback.
 * - NEVER set innerHTML on a host that may contain `{…}` templates or Birkly output.
 * - Only add listeners, toggle classes / aria attributes, or enhance existing nodes.
 *
 * Usage:
 *   <script src="/birkly-client.js"></script>
 *   <script src="/birkly-components.js"></script>
 *   <script>
 *     document.addEventListener('birkly:templates-processed', function () {
 *       BirklyComponents.setActiveNav(document);
 *     });
 *   </script>
 */
(function (root) {
    'use strict';

    function normalizePath(pathname) {
        let path = String(pathname || '/');
        try {
            path = decodeURIComponent(path);
        } catch (e) { /* keep raw */ }
        if (!path) path = '/';
        if (path.length > 1 && path.endsWith('/')) {
            path = path.slice(0, -1);
        }
        return path.toLowerCase();
    }

    function pathsMatch(linkPath, currentPath) {
        const a = normalizePath(linkPath);
        const b = normalizePath(currentPath);
        if (a === b) return true;
        // Treat /index.html as /
        const stripIndex = (p) => (p.endsWith('/index.html') ? p.slice(0, -'/index.html'.length) || '/' : p);
        return stripIndex(a) === stripIndex(b);
    }

    /**
     * Toggle .active / aria-current on nav links under root that match the current page.
     * @param {ParentNode} [root=document]
     * @param {string} [currentPage] optional path override (defaults to location.pathname)
     * @param {{ linkSelector?: string, activeClass?: string }} [options]
     */
    function setActiveNav(root, currentPage, options) {
        const scope = root || (typeof document !== 'undefined' ? document : null);
        if (!scope || typeof scope.querySelectorAll !== 'function') return 0;

        const opts = options && typeof options === 'object' ? options : {};
        const linkSelector = opts.linkSelector || 'a[href]';
        const activeClass = opts.activeClass || 'active';
        const current = currentPage != null
            ? String(currentPage)
            : (typeof location !== 'undefined' ? location.pathname : '/');

        let updated = 0;
        scope.querySelectorAll(linkSelector).forEach((link) => {
            if (!link || typeof link.getAttribute !== 'function') return;
            const href = link.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('javascript:')) {
                return;
            }
            let linkPath = href;
            try {
                if (typeof location !== 'undefined') {
                    linkPath = new URL(href, location.origin).pathname;
                }
            } catch (e) {
                linkPath = href;
            }
            const match = pathsMatch(linkPath, current);
            if (activeClass) {
                link.classList.toggle(activeClass, match);
            }
            if (match) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
            updated++;
        });
        return updated;
    }

    /**
     * Thin enhance-only custom element: toggles active nav on connect.
     * Does NOT set innerHTML.
     */
    function defineEnhanceNavElement() {
        if (typeof customElements === 'undefined' || typeof HTMLElement === 'undefined') {
            return;
        }
        if (customElements.get('birkly-enhance-nav')) {
            return;
        }
        class BirklyEnhanceNav extends HTMLElement {
            connectedCallback() {
                // Enhance only — never replace children.
                setActiveNav(this);
            }
        }
        customElements.define('birkly-enhance-nav', BirklyEnhanceNav);
    }

    const api = {
        setActiveNav: setActiveNav,
        normalizePath: normalizePath,
        defineEnhanceNavElement: defineEnhanceNavElement
    };

    defineEnhanceNavElement();

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    }

    root.BirklyComponents = api;
})(typeof globalThis !== 'undefined' ? globalThis : typeof window !== 'undefined' ? window : global);
