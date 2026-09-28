/**
 * Birch collection builder (P96-M9): fields are compact rows that open on click.
 * Existing fields start closed; a field that fails validation opens itself.
 */
(function () {
    'use strict';

    const page = document.querySelector('.collection-editor-page[data-birch]');
    if (!page) {
        return;
    }

    const t = (key, fallback) => (window.Birkly && Birkly.i18n ? Birkly.i18n.t('admin.' + key, fallback) : fallback);
    const containers = ['fields-container', 'sub-collection-fields-container']
        .map((id) => document.getElementById(id))
        .filter(Boolean);

    function summary(section) {
        const actions = section.querySelector(':scope > .fieldtype-header .fieldtype-header-actions');
        if (!actions) return;
        let chip = actions.querySelector('.fieldtype-summary');
        if (!chip) {
            chip = document.createElement('span');
            chip.className = 'fieldtype-summary chip';
            chip.textContent = t('col_editor_field_required_chip', 'Required');
            actions.prepend(chip);
        }
        const required = section.querySelector('input[type="checkbox"][name$="[required]"]');
        chip.hidden = !(required && required.checked);
    }

    function setOpen(section, open) {
        section.classList.toggle('is-collapsed', !open);
        const header = section.querySelector(':scope > .fieldtype-header');
        header?.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    function prepare(section, collapse) {
        if (section.dataset.birchReady === '1') return;
        section.dataset.birchReady = '1';
        const header = section.querySelector(':scope > .fieldtype-header');
        if (header) {
            header.tabIndex = 0;
            header.setAttribute('role', 'button');
        }
        summary(section);
        setOpen(section, !collapse);
    }

    function updateCount() {
        const count = document.getElementById('fields-count');
        const main = document.getElementById('fields-container');
        if (count && main) {
            count.textContent = String(main.querySelectorAll(':scope > .fieldtype-section').length);
        }
    }

    containers.forEach((container) => {
        container.querySelectorAll(':scope > .fieldtype-section').forEach((section) => prepare(section, true));

        new MutationObserver((mutations) => {
            mutations.forEach((m) => m.addedNodes.forEach((node) => {
                if (node.nodeType === 1 && node.classList.contains('fieldtype-section')) {
                    prepare(node, false);
                }
            }));
            updateCount();
        }).observe(container, { childList: true });

        container.addEventListener('click', (event) => {
            const header = event.target.closest('.fieldtype-header');
            if (!header || event.target.closest('button, a, input, select, textarea, label, .fieldtype-drag-handle')) return;
            const section = header.closest('.fieldtype-section');
            setOpen(section, section.classList.contains('is-collapsed'));
        });

        container.addEventListener('keydown', (event) => {
            if ((event.key !== 'Enter' && event.key !== ' ') || !event.target.classList?.contains('fieldtype-header')) return;
            event.preventDefault();
            const section = event.target.closest('.fieldtype-section');
            setOpen(section, section.classList.contains('is-collapsed'));
        });

        container.addEventListener('change', (event) => {
            if (event.target.matches('input[type="checkbox"][name$="[required]"]')) {
                summary(event.target.closest('.fieldtype-section'));
            }
        });
    });

    document.getElementById('collection-form')?.addEventListener('invalid', (event) => {
        const section = event.target.closest('.fieldtype-section.is-collapsed');
        if (section) setOpen(section, true);
    }, true);

    page.addEventListener('click', (event) => {
        const proxy = event.target.closest('[data-add-field-proxy]');
        if (proxy) document.getElementById(proxy.getAttribute('data-add-field-proxy'))?.click();
    });

    updateCount();
})();
