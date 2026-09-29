/**
 * Collection editor — fieldtype config UI (option lists for select/radio/multiselect).
 * Replaces inline <script> blocks from fieldtype getConfigUI() (CSP-safe).
 */
(function (global) {
    'use strict';

    function ftT(key, fallback) {
        if (global.Birkly && global.Birkly.i18n && typeof global.Birkly.i18n.t === 'function') {
            return global.Birkly.i18n.t('admin.' + key, fallback);
        }
        return fallback;
    }

    function escapeAttr(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function buildOptionRow(fieldIndex, label, value, optionIndex) {
        return (
            '<div class="option-item" data-option-index="' + optionIndex + '">' +
            '<input type="text" class="input option-label" placeholder="' + escapeAttr(ftT('fieldtype_option_label_placeholder', 'Label')) + '" value="' + escapeAttr(label) + '" data-field="' + escapeAttr(fieldIndex) + '" data-option="' + optionIndex + '">' +
            '<input type="text" class="input option-value" placeholder="' + escapeAttr(ftT('fieldtype_option_value_placeholder', 'Value')) + '" value="' + escapeAttr(value) + '" data-field="' + escapeAttr(fieldIndex) + '" data-option="' + optionIndex + '">' +
            '<button type="button" class="icon-button remove-option-btn" data-field="' + escapeAttr(fieldIndex) + '" data-option="' + optionIndex + '" title="' + escapeAttr(ftT('fieldtype_remove_option', 'Remove option')) + '" aria-label="' + escapeAttr(ftT('fieldtype_remove_option', 'Remove option')) + '">' +
            '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">' +
            '<line x1="18" y1="6" x2="6" y2="18"></line>' +
            '<line x1="6" y1="6" x2="18" y2="18"></line>' +
            '</svg></button></div>'
        );
    }

    function slugifyOptionValue(label) {
        return label.toLowerCase().replace(/\s+/g, '_');
    }

    function handleAddOption(btn) {
        var fieldIndex = btn.getAttribute('data-field');
        if (!fieldIndex) return;

        var optionsList = document.getElementById('options_list_' + fieldIndex);
        var newLabelInput = document.getElementById('new_option_label_' + fieldIndex);
        var newValueInput = document.getElementById('new_option_value_' + fieldIndex);
        if (!optionsList || !newLabelInput) return;

        var label = newLabelInput.value.trim();
        if (!label) {
            if (global.BirklyFeedback && typeof global.BirklyFeedback.toast === 'function') {
                global.BirklyFeedback.toast(ftT('fieldtype_option_label_required', 'Please enter an option label'), 'warning');
            }
            newLabelInput.focus();
            return;
        }

        var value = (newValueInput && newValueInput.value.trim()) || slugifyOptionValue(label);
        var optionIndex = optionsList.children.length;
        optionsList.insertAdjacentHTML('beforeend', buildOptionRow(fieldIndex, label, value, optionIndex));
        newLabelInput.value = '';
        if (newValueInput) newValueInput.value = '';
        newLabelInput.focus();
    }

    document.addEventListener('click', function (e) {
        var addBtn = e.target.closest('.add-option-btn');
        if (addBtn) {
            e.preventDefault();
            handleAddOption(addBtn);
            return;
        }

        var removeBtn = e.target.closest('.remove-option-btn');
        if (removeBtn) {
            e.preventDefault();
            var item = removeBtn.closest('.option-item');
            if (item) item.remove();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        var input = e.target;
        if (!input || !input.matches('.new-option-label, .new-option-value')) return;
        var fieldIndex = input.id.replace(/^new_option_(label|value)_/, '');
        var addBtn = document.querySelector('.add-option-btn[data-field="' + fieldIndex + '"]');
        if (addBtn) {
            e.preventDefault();
            handleAddOption(addBtn);
        }
    });

    global.initFieldtypeConfig = function initFieldtypeConfig(fieldIndex, fieldtype, container) {
        if (!container) return;
        container.setAttribute('data-fieldtype-config-ready', fieldtype || 'unknown');
    };
})(window);
