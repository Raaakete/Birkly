/**
 * User Fieldtype JavaScript
 * Adds search + add/remove UX for selecting users.
 */

(function() {
    'use strict';

    if (window.__haru_user_fieldtype_bound) return;
    window.__haru_user_fieldtype_bound = true;

    function initContainer(container) {
        if (!container || !(container instanceof HTMLElement)) return;
        if (container.dataset.userFieldtypeInit === '1') return;
        container.dataset.userFieldtypeInit = '1';

        const isMultiple = container.getAttribute('data-multiple') === '1';
        const hiddenWrap = container.querySelector('.user-fieldtype-hidden-inputs');
        const selectedWrap = container.querySelector('.user-fieldtype-selected');
        const search = container.querySelector('.user-fieldtype-search');
        const select = container.querySelector('.user-fieldtype-select');
        const addBtn = container.querySelector('.user-fieldtype-add');
        const clearBtn = container.querySelector('.user-fieldtype-clear');
        const fieldName = container.getAttribute('data-field-name') || '';

        if (!hiddenWrap || !selectedWrap || !select || !addBtn || !fieldName) return;

        const optionLabelById = () => {
            const map = new Map();
            Array.from(select.options).forEach(opt => {
                const id = (opt.value || '').trim();
                if (!id) return;
                map.set(id, opt.textContent || id);
            });
            return map;
        };

        function getSelectedIds() {
            const ids = [];
            const inputs = hiddenWrap.querySelectorAll('input.user-fieldtype-hidden');
            inputs.forEach(i => {
                const v = (i.value || '').trim();
                if (v) ids.push(v);
            });
            return ids;
        }

        function setSingleValue(id) {
            let input = hiddenWrap.querySelector('input.user-fieldtype-hidden');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.className = 'user-fieldtype-hidden';
                input.name = fieldName;
                hiddenWrap.appendChild(input);
            }
            input.value = id || '';
        }

        function addValue(id) {
            id = (id || '').trim();
            if (!id) return;

            if (!isMultiple) {
                setSingleValue(id);
                renderSelected();
                return;
            }

            const existing = new Set(getSelectedIds());
            if (existing.has(id)) return;

            const input = document.createElement('input');
            input.type = 'hidden';
            input.className = 'user-fieldtype-hidden';
            input.name = fieldName + '[]';
            input.value = id;
            hiddenWrap.appendChild(input);
            renderSelected();
        }

        function removeValue(id) {
            id = (id || '').trim();
            if (!id) return;

            if (!isMultiple) {
                setSingleValue('');
                renderSelected();
                return;
            }

            const inputs = hiddenWrap.querySelectorAll('input.user-fieldtype-hidden');
            inputs.forEach(i => {
                if ((i.value || '').trim() === id) {
                    i.remove();
                }
            });
            renderSelected();
        }

        function renderSelected() {
            const ids = getSelectedIds();
            const labels = optionLabelById();
            selectedWrap.innerHTML = '';

            ids.forEach(id => {
                const chip = document.createElement('span');
                chip.className = 'user-fieldtype-chip';
                chip.dataset.userId = id;

                const label = document.createElement('span');
                label.className = 'user-fieldtype-chip-label';
                label.textContent = labels.get(id) || `Unknown user (${id})`;

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn user-fieldtype-remove';
                btn.dataset.userId = id;
                btn.setAttribute('aria-label', 'Remove user');
                btn.textContent = '×';

                chip.appendChild(label);
                chip.appendChild(btn);
                selectedWrap.appendChild(chip);
            });
        }

        // Initial render (server rendered chips may exist, but keep canonical from hidden inputs)
        renderSelected();

        // Add button
        addBtn.addEventListener('click', () => {
            const id = (select.value || '').trim();
            if (!id) return;
            addValue(id);
            select.value = '';
            if (search) search.focus();
        });

        // Clear button (single)
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                setSingleValue('');
                renderSelected();
                select.value = '';
            });
        }

        // Remove chip (event delegation)
        container.addEventListener('click', (e) => {
            const t = e.target;
            if (!(t instanceof HTMLElement)) return;
            if (!t.classList.contains('user-fieldtype-remove')) return;
            const id = t.dataset.userId || '';
            removeValue(id);
        });

        // Search filters options
        if (search) {
            search.addEventListener('input', function() {
                const term = (this.value || '').toLowerCase();
                Array.from(select.options).forEach((opt, idx) => {
                    if (idx === 0) return; // keep placeholder
                    const text = (opt.textContent || '').toLowerCase();
                    opt.hidden = term ? !text.includes(term) : false;
                });
            });
        }

        // For single: selecting from dropdown sets immediately (quality-of-life)
        if (!isMultiple) {
            select.addEventListener('change', () => {
                const id = (select.value || '').trim();
                if (!id) {
                    setSingleValue('');
                    renderSelected();
                    return;
                }
                setSingleValue(id);
                renderSelected();
            });
        }
    }

    // Init existing
    function initAll() {
        document.querySelectorAll('.user-fieldtype').forEach(initContainer);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Init when admin entry editor dispatches fieldtype:init
    document.addEventListener('fieldtype:init', (e) => {
        const detail = e.detail || {};
        if (detail.fieldtype !== 'user') return;
        const el = detail.element;
        if (!(el instanceof HTMLElement)) return;
        const container = el.classList.contains('user-fieldtype') ? el : el.closest('.user-fieldtype');
        initContainer(container);
    });
})();
