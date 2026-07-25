(function () {
    'use strict';

    function updateTableScrollState(viewport) {
        const table = viewport.closest('.entries-smart-table');
        if (!table) {
            return;
        }

        const hasOverflow = viewport.scrollWidth > viewport.clientWidth + 1;
        const scrolled = viewport.scrollLeft > 1;

        table.classList.toggle('is-viewport-scrolled', hasOverflow && scrolled);
    }

    function bindTable(table) {
        const viewport = table.querySelector('.table-viewport');
        if (!viewport || viewport.dataset.smartTableScrollBound === '1') {
            return;
        }

        viewport.dataset.smartTableScrollBound = '1';

        const onChange = function () {
            updateTableScrollState(viewport);
        };

        viewport.addEventListener('scroll', onChange, { passive: true });
        window.addEventListener('resize', onChange);

        if (typeof ResizeObserver !== 'undefined') {
            const observer = new ResizeObserver(onChange);
            observer.observe(viewport);
        }

        onChange();
    }

    function initializeSmartTableScroll() {
        document.querySelectorAll('.entries-smart-table').forEach(bindTable);
    }

    window.Birkly = window.Birkly || {};
    window.Birkly.SmartTableScroll = {
        init: initializeSmartTableScroll,
        update: updateTableScrollState,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeSmartTableScroll);
    } else {
        initializeSmartTableScroll();
    }

    const mutationObserver = new MutationObserver(function () {
        initializeSmartTableScroll();
    });

    mutationObserver.observe(document.documentElement, { childList: true, subtree: true });
})();
