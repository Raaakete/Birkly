(function () {
    'use strict';

    function getScrollElement(table, viewport) {
        if (viewport && viewport.scrollWidth > viewport.clientWidth + 1) {
            return viewport;
        }
        if (table.scrollWidth > table.clientWidth + 1) {
            return table;
        }
        return viewport || table;
    }

    function getRowContentWidth(row) {
        if (!row) {
            return 0;
        }

        let maxRight = 0;
        Array.from(row.children).forEach(function (child) {
            const rect = child.getBoundingClientRect();
            if (rect.width > 0) {
                maxRight = Math.max(maxRight, rect.right);
            }
        });

        const rowRect = row.getBoundingClientRect();
        return Math.max(0, maxRight - rowRect.left);
    }

    function clampHorizontalScroll(viewport) {
        const headRow = viewport.querySelector('.head-row');
        if (!headRow) {
            return;
        }

        let contentWidth = getRowContentWidth(headRow);
        viewport.querySelectorAll('.table-row').forEach(function (row) {
            contentWidth = Math.max(contentWidth, getRowContentWidth(row));
        });

        const maxScrollLeft = Math.max(0, Math.ceil(contentWidth) - viewport.clientWidth);
        if (viewport.scrollLeft > maxScrollLeft + 1) {
            viewport.scrollLeft = maxScrollLeft;
        }
    }

    function isActionsOverlappingContent(table) {
        const actionsCell = table.querySelector('.table-row .cell-actions') || table.querySelector('.head-actions');
        if (!actionsCell) {
            return false;
        }

        const actionsLeft = actionsCell.getBoundingClientRect().left;
        const cells = table.querySelectorAll(
            '.head-title, .head-status, .head-field, .cell-title, .cell-status, .cell-field'
        );

        for (let index = 0; index < cells.length; index += 1) {
            const rect = cells[index].getBoundingClientRect();
            if (rect.width <= 0) {
                continue;
            }
            if (rect.right > actionsLeft + 1 && rect.left < actionsLeft - 1) {
                return true;
            }
        }

        return false;
    }

    function updateTableScrollState(viewport) {
        const table = viewport.closest('.entries-smart-table');
        if (!table) {
            return;
        }

        const scrollElement = getScrollElement(table, viewport);
        const hasOverflow = scrollElement.scrollWidth > scrollElement.clientWidth + 1;
        const scrolled = scrollElement.scrollLeft > 1;
        const overlapping = isActionsOverlappingContent(table);

        table.classList.toggle(
            'is-actions-overlapping',
            overlapping || (hasOverflow && scrolled)
        );
    }

    function bindTable(table) {
        const viewport = table.querySelector('.table-viewport');
        if (!viewport || viewport.dataset.smartTableScrollBound === '1') {
            return;
        }

        viewport.dataset.smartTableScrollBound = '1';

        const onChange = function () {
            clampHorizontalScroll(viewport);
            updateTableScrollState(viewport);
        };

        viewport.addEventListener('scroll', onChange, { passive: true });
        table.addEventListener('scroll', onChange, { passive: true });
        window.addEventListener('resize', onChange);

        if (typeof ResizeObserver !== 'undefined') {
            const observer = new ResizeObserver(onChange);
            observer.observe(viewport);
            observer.observe(table);
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
