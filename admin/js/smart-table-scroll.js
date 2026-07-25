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

    function getRowCells(row) {
        const cells = [];
        Array.from(row.children).forEach(function (child) {
            if (child.classList.contains('fields-head-region') || child.classList.contains('fields-region')) {
                Array.from(child.children).forEach(function (fieldCell) {
                    cells.push(fieldCell);
                });
                return;
            }
            cells.push(child);
        });
        return cells;
    }

    function syncColumnWidths(table) {
        const headRow = table.querySelector('.head-row');
        if (!headRow) {
            return;
        }

        const headCells = getRowCells(headRow);
        if (headCells.length === 0) {
            return;
        }

        const widths = headCells.map(function (cell) {
            cell.style.width = '';
            cell.style.minWidth = '';
            cell.style.maxWidth = '';
            cell.style.flex = '';
            return cell.getBoundingClientRect().width;
        });

        function applyWidths(cells) {
            cells.forEach(function (cell, index) {
                const width = widths[index];
                if (!width || width <= 0) {
                    return;
                }
                const px = Math.ceil(width) + 'px';
                cell.style.width = px;
                cell.style.minWidth = px;
                cell.style.maxWidth = px;
                cell.style.flex = '0 0 ' + px;
            });
        }

        applyWidths(headCells);
        table.querySelectorAll('.table-row').forEach(function (row) {
            applyWidths(getRowCells(row));
        });

        const totalWidth = widths.reduce(function (sum, width) {
            return sum + (width > 0 ? Math.ceil(width) : 0);
        }, 0);

        if (totalWidth > 0) {
            const widthPx = totalWidth + 'px';
            headRow.style.width = widthPx;
            headRow.style.minWidth = widthPx;
            table.querySelectorAll('.table-row').forEach(function (row) {
                row.style.width = widthPx;
                row.style.minWidth = widthPx;
            });
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
            syncColumnWidths(table);
            updateTableScrollState(viewport);
        };

        viewport.addEventListener('scroll', function () {
            updateTableScrollState(viewport);
        }, { passive: true });
        table.addEventListener('scroll', function () {
            updateTableScrollState(viewport);
        }, { passive: true });
        window.addEventListener('resize', onChange);

        if (typeof ResizeObserver !== 'undefined') {
            const observer = new ResizeObserver(onChange);
            observer.observe(viewport);
            observer.observe(table);
        }

        if (typeof requestAnimationFrame !== 'undefined') {
            requestAnimationFrame(onChange);
        } else {
            onChange();
        }
    }

    function initializeSmartTableScroll() {
        document.querySelectorAll('.entries-smart-table').forEach(bindTable);
    }

    window.Birkly = window.Birkly || {};
    window.Birkly.SmartTableScroll = {
        init: initializeSmartTableScroll,
        update: updateTableScrollState,
        syncColumnWidths: function (table) {
            if (table) {
                syncColumnWidths(table);
            }
        },
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
