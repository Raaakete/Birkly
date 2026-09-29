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

    function applyParams(text, params) {
        if (!params || typeof params !== 'object') {
            return text;
        }
        Object.keys(params).forEach(function (name) {
            var value = String(params[name]);
            text = text.split('{{' + name + '}}').join(value);
            text = text.split('{' + name + '}').join(value);
        });
        return text;
    }

    function t(key, fallback, params) {
        var dict = global.BirklyAdminI18n || {};
        var keyStr = String(key || '');
        var text = null;

        if (keyStr !== '' && dict[keyStr] != null && dict[keyStr] !== '') {
            text = dict[keyStr];
        }

        if (text == null) {
            var parts = keyStr.split('.');
            if (parts.length > 1) {
                var nested = lookupNested(dict, parts);
                if (nested != null && nested !== '') {
                    text = nested;
                }
            }

            if (text == null) {
                var lookupKey = parts[parts.length - 1];
                if (lookupKey && dict[lookupKey] != null && dict[lookupKey] !== '') {
                    text = dict[lookupKey];
                }
            }
        }

        if (text == null) {
            text = fallback != null ? fallback : key;
        }

        return applyParams(String(text), params);
    }

    global.Birkly = global.Birkly || {};
    global.Birkly.i18n = { t: t };
})(window);
