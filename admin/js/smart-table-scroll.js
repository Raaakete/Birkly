(function () {
    'use strict';

    function updateTableScrollState(viewport) {
        const table = viewport.closest('.entries-smart-table');
        if (!table) {
            return;
        }

        const hasOverflow = viewport.scrollWidth > viewport.clientWidth + 1;
        const scrolled = viewport.scrollLeft > 0;
        table.classList.toggle('is-actions-overlapping', hasOverflow && scrolled);
    }

    function bindViewport(viewport) {
        if (!viewport || viewport.dataset.smartTableScrollBound === '1') {
            return;
        }

        viewport.dataset.smartTableScrollBound = '1';

        const onScroll = function () {
            updateTableScrollState(viewport);
        };

        viewport.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);
        onScroll();
    }

    function initializeSmartTableScroll() {
        document.querySelectorAll('.entries-smart-table .table-viewport').forEach(bindViewport);
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

    const observer = new MutationObserver(function () {
        initializeSmartTableScroll();
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
