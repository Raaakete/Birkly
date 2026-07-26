/**
 * Fieldtype icon registry — maps fieldtype keys to Birkly icon names.
 */
(function () {
    'use strict';

    window.Birkly = window.Birkly || {};

    var FIELD_TYPE_ICON_MAP = {
        text: 'text-aa',
        textarea: 'article',
        contenteditor: 'layout',
        markdown: 'article',
        code: 'code',
        number: 'hash',
        range: 'sliders-horizontal',
        date: 'calendar',
        datetime: 'calendar-clock',
        time: 'clock',
        checkbox: 'check-square',
        toggle: 'toggle-left',
        radio: 'circle-dot',
        select: 'caret-down',
        multiselect: 'list-checks',
        color: 'palette',
        tags: 'tag',
        email: 'envelope',
        url: 'link',
        phone: 'phone',
        file: 'file-text',
        gallery: 'squares-four',
        map: 'map-pin',
        relation: 'users',
        dynamicitems: 'list',
        oembed: 'play',
        media: 'image'
    };

    function normalizeFieldtypeKey(name) {
        return String(name || '').toLowerCase().replace(/[-_\s]/g, '');
    }

    function resolveFieldtypeIconName(name) {
        var key = normalizeFieldtypeKey(name);
        return FIELD_TYPE_ICON_MAP[key] || 'stack';
    }

    Birkly.fieldtypeIconName = resolveFieldtypeIconName;

    Birkly.fieldtypeIcon = function fieldtypeIcon(name, opts) {
        opts = opts || {};
        if (!window.Birkly || typeof Birkly.icon !== 'function') {
            return '';
        }
        return Birkly.icon(resolveFieldtypeIconName(name), opts);
    };

    Birkly.fieldtypeDisplayLabel = function fieldtypeDisplayLabel(name, fieldtypes) {
        if (!name) {
            return 'SELECT TYPE';
        }
        fieldtypes = fieldtypes || (window.availableFieldtypes || window.fieldtypes || []);
        for (var i = 0; i < fieldtypes.length; i++) {
            var ft = fieldtypes[i];
            if (ft.name === name || ft.type === name) {
                return String(ft.label || name).toUpperCase();
            }
        }
        return String(name).toUpperCase();
    };

    Birkly.updateFieldtypeTypeBadge = function updateFieldtypeTypeBadge(fieldElement, fieldtypeName, fieldtypes) {
        if (!fieldElement) {
            return;
        }
        var badge = fieldElement.querySelector('.fieldtype-type');
        if (!badge) {
            return;
        }

        var label = Birkly.fieldtypeDisplayLabel(fieldtypeName, fieldtypes);
        badge.setAttribute('data-fieldtype', fieldtypeName || '');

        var iconWrap = badge.querySelector('.fieldtype-type-icon');
        if (iconWrap) {
            iconWrap.innerHTML = fieldtypeName ? Birkly.fieldtypeIcon(fieldtypeName, { size: 14, class: 'fieldtype-type-icon-svg' }) : '';
        }

        var labelEl = badge.querySelector('.fieldtype-type-label');
        if (labelEl) {
            labelEl.textContent = label;
        } else {
            badge.textContent = label;
        }
    };

    Birkly.hydrateFieldtypeTypeBadges = function hydrateFieldtypeTypeBadges(root, fieldtypes) {
        root = root || document;
        fieldtypes = fieldtypes || (window.availableFieldtypes || window.fieldtypes || []);
        if (!root.querySelectorAll) {
            return;
        }
        root.querySelectorAll('.fieldtype-section[data-index], .fieldtype-section[data-sub-field-index]').forEach(function (section) {
            var typeSelect = section.querySelector('.field-type-select');
            var badge = section.querySelector('.fieldtype-type');
            var fieldtypeName = typeSelect && typeSelect.value
                ? typeSelect.value
                : (badge ? badge.getAttribute('data-fieldtype') : '');
            if (fieldtypeName) {
                Birkly.updateFieldtypeTypeBadge(section, fieldtypeName, fieldtypes);
            }
        });
    };
})();
