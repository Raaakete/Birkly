/**
 * Client-side i18n for admin JS (P44-M8).
 * Reads translations from window.BirklyAdminI18n (injected in layout_head.php).
 */
(function (global) {
    'use strict';

    function t(key, fallback) {
        var dict = global.BirklyAdminI18n || {};
        var parts = String(key || '').split('.');
        var lookupKey = parts[parts.length - 1];
        if (dict[lookupKey] != null && dict[lookupKey] !== '') {
            return dict[lookupKey];
        }
        return fallback != null ? fallback : key;
    }

    global.Birkly = global.Birkly || {};
    global.Birkly.i18n = { t: t };
})(window);
