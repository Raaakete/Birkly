/**
 * Base-path helpers for admin JS (subdirectory installs).
 * Requires window.Birkly.basePath set from PHP before this script runs.
 */
(function () {
    'use strict';

    window.Birkly = window.Birkly || {};

    const basePath = typeof window.Birkly.basePath === 'string' ? window.Birkly.basePath : '';

    function url(path) {
        if (!path) return path;
        if (/^https?:\/\//i.test(path)) return path;
        if (path[0] !== '/') path = '/' + path;
        return basePath ? basePath + path : path;
    }

    function adminUrl(page, params) {
        const q = Object.assign({ page: page }, params || {});
        const query = Object.keys(q)
            .filter((k) => q[k] !== undefined && q[k] !== null && q[k] !== '')
            .map((k) => encodeURIComponent(k) + '=' + encodeURIComponent(String(q[k])))
            .join('&');
        return url('/admin/index.php' + (query ? '?' + query : ''));
    }

    Birkly.url = url;
    Birkly.adminUrl = adminUrl;

    if (!basePath) return;

    const nativeFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        if (typeof input === 'string' && (input.startsWith('/api/') || input.startsWith('/admin/') || input.startsWith('/docs/'))) {
            input = url(input);
        }
        return nativeFetch(input, init);
    };
})();
