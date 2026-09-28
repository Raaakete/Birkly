/**
 * Fieldtype badges for the collection builder. Icons and labels come from the
 * fieldtypes themselves (BaseField::getIcon()) via the getAvailableFieldtypes() rows.
 */
(function () {
    'use strict';

    window.Birkly = window.Birkly || {};

    function normalizeFieldtypeKey(name) {
        return String(name || '').toLowerCase().replace(/[-_\s]/g, '');
    }

    function registry(fieldtypes) {
        return fieldtypes || window.availableFieldtypes || window.fieldtypes || [];
    }

    function findFieldtype(name, fieldtypes) {
        var key = normalizeFieldtypeKey(name);
        if (!key) {
            return null;
        }
        var list = registry(fieldtypes);
        for (var i = 0; i < list.length; i++) {
            var ft = list[i];
            if (normalizeFieldtypeKey(ft.name) === key || normalizeFieldtypeKey(ft.type) === key) {
                return ft;
            }
        }
        return null;
    }

    Birkly.findFieldtype = findFieldtype;

    // Icon declared by the fieldtype (BaseField::getIcon()), or the generic one.
    Birkly.fieldtypeIconName = function fieldtypeIconName(name, fieldtypes) {
        var ft = findFieldtype(name, fieldtypes);
        return ft && ft.icon ? String(ft.icon) : 'layers';
    };

    Birkly.fieldtypeIcon = function fieldtypeIcon(name, opts, fieldtypes) {
        opts = opts || {};
        if (!window.Birkly || typeof Birkly.icon !== 'function') {
            return '';
        }
        return Birkly.icon(Birkly.fieldtypeIconName(name, fieldtypes), opts) || Birkly.icon('layers', opts);
    };

    Birkly.fieldtypeDisplayLabel = function fieldtypeDisplayLabel(name, fieldtypes) {
        if (!name) {
            return 'Select type';
        }
        var ft = findFieldtype(name, fieldtypes);
        if (ft) {
            return String(ft.label || name);
        }
        return String(name).charAt(0).toUpperCase() + String(name).slice(1);
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
            iconWrap.innerHTML = fieldtypeName ? Birkly.fieldtypeIcon(fieldtypeName, { size: 14, class: 'fieldtype-type-icon-svg' }, fieldtypes) : '';
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
