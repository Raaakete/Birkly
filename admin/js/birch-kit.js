/**
 * Birch component kit page (P96-M7): preview controls only.
 */
(function () {
    'use strict';

    const root = document.querySelector('.birch-kit');
    if (!root) {
        return;
    }

    const pick = (group, apply) => {
        group.addEventListener('click', (event) => {
            const button = event.target.closest('button[data-value]');
            if (!button) {
                return;
            }
            group.querySelectorAll('button[data-value]').forEach((b) => b.setAttribute('aria-pressed', b === button ? 'true' : 'false'));
            apply(button.getAttribute('data-value'));
        });
    };

    const themeGroup = root.querySelector('[data-kit-theme]');
    if (themeGroup) {
        const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        themeGroup.querySelectorAll('button[data-value]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.value === current ? 'true' : 'false'));
        pick(themeGroup, (theme) => {
            document.documentElement.setAttribute('data-theme', theme);
            document.body.setAttribute('data-theme', theme);
        });
    }

    const densityGroup = root.querySelector('[data-kit-density]');
    if (densityGroup) {
        const current = document.body.dataset.density || 'default';
        densityGroup.querySelectorAll('button[data-value]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.value === current ? 'true' : 'false'));
        pick(densityGroup, (density) => {
            document.body.dataset.density = density;
        });
    }

    root.querySelectorAll('.segmented:not([data-kit-theme]):not([data-kit-density])').forEach((group) => {
        group.addEventListener('click', (event) => {
            const button = event.target.closest('button');
            if (!button) {
                return;
            }
            group.querySelectorAll('button').forEach((b) => b.setAttribute('aria-pressed', b === button ? 'true' : 'false'));
        });
    });

    root.querySelectorAll('.switch[role="switch"]').forEach((sw) => {
        sw.addEventListener('click', () => {
            sw.setAttribute('aria-checked', sw.getAttribute('aria-checked') === 'true' ? 'false' : 'true');
        });
    });

    root.querySelectorAll('[data-kit-indeterminate]').forEach((box) => {
        box.indeterminate = true;
    });

    root.querySelectorAll('.meter[data-value]').forEach((meter) => {
        meter.style.setProperty('--value', meter.getAttribute('data-value'));
    });

    root.querySelectorAll('.skeleton[data-width]').forEach((bar) => {
        bar.style.width = bar.getAttribute('data-width') + '%';
    });
})();
