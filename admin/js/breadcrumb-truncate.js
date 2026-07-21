/**
 * Smart breadcrumb truncation — collapse from the left when topbar space is tight.
 * Always keeps the last two segments visible (… / Collection / Entry).
 */
(function () {
    'use strict';

    window.Birkly = window.Birkly || {};

    const MIN_VISIBLE_SEGMENTS = 2;
    const ELLIPSIS = '…';

    let truncateFrame = null;

    function makeSeparator() {
        const sep = document.createElement('span');
        sep.className = 'separator';
        sep.setAttribute('aria-hidden', 'true');
        sep.textContent = '›';
        return sep;
    }

    /**
     * Parse breadcrumb nav children into segment objects.
     * @param {HTMLElement} nav
     * @returns {Array<{label: string, href: string|null, isCurrent: boolean}>}
     */
    function parseSegments(nav) {
        const segments = [];
        Array.from(nav.children).forEach((el) => {
            if (el.classList.contains('separator') || el.classList.contains('breadcrumb-ellipsis')) {
                return;
            }
            if (el.tagName === 'A') {
                segments.push({
                    label: (el.textContent || '').trim(),
                    href: el.getAttribute('href'),
                    isCurrent: false,
                });
                return;
            }
            if (el.classList.contains('current') || el.classList.contains('breadcrumb-segment')) {
                segments.push({
                    label: (el.textContent || '').trim(),
                    href: null,
                    isCurrent: el.classList.contains('current'),
                });
            }
        });
        return segments;
    }

    function buildFullPathLabel(segments) {
        return segments.map((s) => s.label).filter(Boolean).join(' / ');
    }

    /**
     * How many leading segments to collapse when showing only the last N.
     * @param {number} segmentCount
     * @param {number} minVisible
     * @returns {number}
     */
    function collapsedStartIndex(segmentCount, minVisible) {
        if (segmentCount <= minVisible) {
            return 0;
        }
        return segmentCount - minVisible;
    }

    function renderSegments(nav, segments, collapsedStart, fullPathLabel) {
        const renderKey = collapsedStart + '|' + fullPathLabel;
        if (nav._birklyBreadcrumbRenderKey === renderKey) {
            return;
        }
        nav._birklyBreadcrumbRenderKey = renderKey;

        nav.innerHTML = '';
        nav.classList.add('breadcrumbs--truncated-ready');

        const frag = document.createDocumentFragment();

        if (collapsedStart > 0) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'breadcrumb-ellipsis';
            ellipsis.setAttribute('aria-hidden', 'true');
            ellipsis.textContent = ELLIPSIS;
            ellipsis.title = fullPathLabel;
            frag.appendChild(ellipsis);
            frag.appendChild(makeSeparator());
        }

        const visible = segments.slice(collapsedStart);
        visible.forEach((seg, idx) => {
            if (idx > 0) {
                frag.appendChild(makeSeparator());
            }
            if (seg.href && !seg.isCurrent) {
                const link = document.createElement('a');
                link.href = seg.href;
                link.className = 'breadcrumb-segment';
                link.textContent = seg.label;
                frag.appendChild(link);
            } else {
                const span = document.createElement('span');
                span.className = seg.isCurrent
                    ? 'current breadcrumb-segment breadcrumb-segment--current'
                    : 'breadcrumb-segment';
                span.textContent = seg.label;
                if (seg.isCurrent) {
                    span.setAttribute('aria-current', 'page');
                }
                frag.appendChild(span);
            }
        });

        nav.appendChild(frag);
        nav.setAttribute('aria-label', 'Breadcrumb: ' + fullPathLabel);
        nav.title = collapsedStart > 0 ? fullPathLabel : '';
    }

    function measureOverflow(nav) {
        return nav.scrollWidth > nav.clientWidth + 1;
    }

    function truncateNavNow(nav) {
        if (!nav || !nav.classList.contains('breadcrumbs')) {
            return;
        }

        if (!nav._birklyBreadcrumbSegments) {
            const parsed = parseSegments(nav);
            if (parsed.length === 0) {
                return;
            }
            nav._birklyBreadcrumbSegments = parsed;
        }

        const segments = nav._birklyBreadcrumbSegments.map((s) => ({ ...s }));
        const fullPathLabel = buildFullPathLabel(segments);

        nav._birklyBreadcrumbMeasuring = true;
        const prevVisibility = nav.style.visibility;
        nav.style.visibility = 'hidden';
        delete nav._birklyBreadcrumbRenderKey;

        let collapsedStart = 0;
        if (segments.length > MIN_VISIBLE_SEGMENTS) {
            collapsedStart = segments.length - MIN_VISIBLE_SEGMENTS;
            for (let start = 0; start <= segments.length - MIN_VISIBLE_SEGMENTS; start++) {
                renderSegments(nav, segments, start, fullPathLabel);
                if (!measureOverflow(nav)) {
                    collapsedStart = start;
                    break;
                }
                collapsedStart = Math.min(start + 1, segments.length - MIN_VISIBLE_SEGMENTS);
            }
        }

        renderSegments(nav, segments, collapsedStart, fullPathLabel);
        nav.classList.toggle('breadcrumbs--collapsed', collapsedStart > 0);

        nav.style.visibility = prevVisibility;
        nav._birklyBreadcrumbMeasuring = false;
    }

    function truncateNav(nav) {
        if (!nav) {
            return;
        }
        if (truncateFrame) {
            cancelAnimationFrame(truncateFrame);
        }
        truncateFrame = requestAnimationFrame(() => {
            truncateFrame = null;
            truncateNavNow(nav);
        });
    }

    function initNav(nav) {
        if (!nav) {
            return;
        }
        if (nav.dataset.breadcrumbTruncateInit !== '1') {
            nav.dataset.breadcrumbTruncateInit = '1';
        }
        truncateNav(nav);
    }

    function init() {
        const nav = document.getElementById('admin-topbar-breadcrumbs');
        if (!nav || nav.childElementCount === 0) {
            return;
        }
        initNav(nav);

        if (typeof ResizeObserver !== 'undefined' && !nav._birklyBreadcrumbResizeObserver) {
            nav._birklyBreadcrumbResizeObserver = new ResizeObserver(() => {
                if (nav._birklyBreadcrumbMeasuring) {
                    return;
                }
                truncateNav(nav);
            });
            const topbarLeft = nav.closest('.admin-topbar-left');
            if (topbarLeft) {
                nav._birklyBreadcrumbResizeObserver.observe(topbarLeft);
            }
        }
    }

    function refresh() {
        const nav = document.getElementById('admin-topbar-breadcrumbs');
        if (!nav) {
            return;
        }
        delete nav._birklyBreadcrumbSegments;
        delete nav._birklyBreadcrumbRenderKey;
        initNav(nav);
    }

    Birkly.BreadcrumbTruncate = {
        parseSegments,
        collapsedStartIndex,
        buildFullPathLabel,
        truncateNav,
        init,
        refresh,
        MIN_VISIBLE_SEGMENTS,
    };

    document.addEventListener('birkly:layout-change', refresh);
    window.addEventListener('resize', () => {
        const nav = document.getElementById('admin-topbar-breadcrumbs');
        if (nav) {
            delete nav._birklyBreadcrumbRenderKey;
            truncateNav(nav);
        }
    });
})();
