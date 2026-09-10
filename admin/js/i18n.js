/**
 * Client-side i18n for admin JS (P44-M8, P85-M2).
 * Reads flat full-key translations from window.BirklyAdminI18n (layout_head.php).
 */
(function (global) {
    'use strict';

    function lookupNested(dict, parts) {
        var value = dict;
        for (var i = 0; i < parts.length; i++) {
            if (value == null || typeof value !== 'object') {
                return null;
            }
            value = value[parts[i]];
        }
        return typeof value === 'string' ? value : null;
    }

    function t(key, fallback) {
        var dict = global.BirklyAdminI18n || {};
        var keyStr = String(key || '');

        if (keyStr !== '' && dict[keyStr] != null && dict[keyStr] !== '') {
            return dict[keyStr];
        }

        var parts = keyStr.split('.');
        if (parts.length > 1) {
            var nested = lookupNested(dict, parts);
            if (nested != null && nested !== '') {
                return nested;
            }
        }

        var lookupKey = parts[parts.length - 1];
        if (lookupKey && dict[lookupKey] != null && dict[lookupKey] !== '') {
            return dict[lookupKey];
        }

        return fallback != null ? fallback : key;
    }

    global.Birkly = global.Birkly || {};
    global.Birkly.i18n = { t: t };
})(window);
