/**
 * Page action rows on phones (< 768px): keep the main action and icon buttons
 * in the row; every other button, control and filter moves into a More menu.
 * Elements are moved, not cloned, so their listeners keep working, and they
 * go back to their original place when the viewport widens again.
 */
(function () {
    'use strict';

    const QUERY = window.matchMedia('(max-width: 767px)');
    const BAR_SELECTOR = '.main-content :is(.page-bar, .main-actions-bar, .page-header > .page-header-actions)';
    const CONTAINER_SELECTOR = ':is(.page-bar-tools, .page-bar-actions, .main-actions-left, .main-actions-right, .main-actions-tools, .plugin-actions-left, .plugin-actions-right, .u-row-2, .u-row-3, .u-split)';
    const PRIMARY_SELECTOR = ':is(.is-main, .btn-primary)';
    const KEEP_SELECTOR = ':is([hidden], [aria-live], [role="status"], .message, .badge, .page-more)';
    let menuSeq = 0;

    function t(key, fallback, params) {
        const i18n = window.Birkly && window.Birkly.i18n;
        return i18n && typeof i18n.t === 'function' ? i18n.t('admin.' + key, fallback, params) : fallback;
    }

    const WIDGET_SELECTOR = 'button, a, label, input, select, textarea, .dropdown, .search-field, .select-box, .segmented, .input-group, .switch';

    function isIconOnly(el) {
        if (el.matches('.dropdown')) {
            const trigger = el.querySelector(':scope > .dropdown-trigger');
            return !!trigger && isIconOnly(trigger);
        }
        if (!el.matches('button, a')) {
            return false;
        }
        if (el.matches('.icon-button, .topbar-icon-btn')) {
            return true;
        }
        return (el.textContent || '').trim() === '' && !!el.querySelector('svg, .birkly-icon');
    }

    function isShown(el) {
        return !el.hidden && getComputedStyle(el).display !== 'none';
    }

    function isWrapper(el) {
        if (!el.matches('div, span, nav') || el.matches(WIDGET_SELECTOR) || !isShown(el)) {
            return false;
        }
        const ownText = Array.from(el.childNodes).some((n) => n.nodeType === 3 && n.textContent.trim() !== '');
        return !ownText && !!el.querySelector('button, a, input, select, textarea');
    }

    function flatten(node, out) {
        Array.from(node.children).forEach((child) => {
            if ((child.matches(CONTAINER_SELECTOR) && isShown(child)) || isWrapper(child)) {
                child.classList.add('page-more-unwrapped');
                flatten(child, out);
            } else {
                out.push(child);
            }
        });
        return out;
    }

    function hasContent(el) {
        return isShown(el) && (el.matches(WIDGET_SELECTOR) || !!el.querySelector('button, a, input, select, textarea'));
    }

    function buildMenu(bar) {
        const id = 'page-more-' + (++menuSeq);
        const wrap = document.createElement('div');
        wrap.className = 'page-more';
        const icon = window.Birkly && typeof window.Birkly.icon === 'function' ? window.Birkly.icon('dots') : '···';
        wrap.innerHTML = '<button type="button" class="icon-button page-more-trigger" aria-haspopup="true" aria-expanded="false" aria-controls="' + id + '">' + icon + '</button>'
            + '<div class="menu page-more-menu" id="' + id + '" hidden></div>';
        const trigger = wrap.firstElementChild;
        const menu = wrap.lastElementChild;
        trigger.setAttribute('aria-label', t('more_actions', 'More actions'));
        trigger.title = t('more_actions', 'More actions');
        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const open = menu.hidden;
            closeAll();
            menu.hidden = !open;
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        menu.addEventListener('click', (event) => {
            if (event.target.closest('button:not(.segmented > button), a')) {
                window.setTimeout(closeAll, 0);
            }
        });
        bar.appendChild(wrap);
        return { wrap, menu };
    }

    function collapse(bar) {
        if (bar._pageMore && !bar._pageMore.wrap.isConnected) {
            delete bar._pageMore;
        }
        if (bar._pageMore || bar.closest('.modal, .modal-backdrop, .dialog, .sheet, #admin-topbar')) {
            return;
        }
        const items = flatten(bar, []);
        const primary = items.find((el) => el.matches(PRIMARY_SELECTOR) && !el.hidden);
        const moving = items.filter((el) => el !== primary && !isIconOnly(el) && !el.matches(KEEP_SELECTOR) && hasContent(el));
        const overflow = items.filter((el) => el.matches('.dropdown') && isIconOnly(el) && isShown(el));
        if (moving.length === 0 && overflow.length === 0) {
            return;
        }
        items.forEach((el) => {
            if (el !== primary && !moving.includes(el) && !isIconOnly(el) && isShown(el) && (el.textContent || '').trim() !== '') {
                el.classList.add('page-more-keep');
            }
        });
        if (primary) {
            primary.classList.add('page-more-main');
        }
        items.filter((el) => el !== primary && isIconOnly(el)).forEach((el) => el.classList.add('page-more-icon'));
        const { wrap, menu } = buildMenu(bar);
        const park = (el) => {
            const mark = document.createComment('page-more');
            el.parentNode.insertBefore(mark, el);
            menu.appendChild(el);
            return { el, mark };
        };
        const placeholders = moving.map(park);
        const hiddenDropdowns = [];
        overflow.forEach((dropdown) => {
            const entries = Array.from(dropdown.querySelectorAll(':scope > .dropdown-menu > :is(.dropdown-item, a, button)'));
            if (entries.length === 0) {
                return;
            }
            if (menu.childElementCount > 0) {
                const sep = document.createElement('hr');
                sep.className = 'page-more-sep';
                menu.appendChild(sep);
            }
            entries.forEach((el) => placeholders.push(park(el)));
            dropdown.hidden = true;
            hiddenDropdowns.push(dropdown);
        });
        bar._pageMore = { wrap, placeholders, hiddenDropdowns };
        bar.classList.add('has-page-more');
    }

    function expand(bar) {
        const state = bar._pageMore;
        if (!state) {
            return;
        }
        state.placeholders.forEach(({ el, mark }) => {
            if (mark.parentNode) {
                mark.parentNode.insertBefore(el, mark);
                mark.remove();
            }
        });
        state.hiddenDropdowns.forEach((dropdown) => { dropdown.hidden = false; });
        state.wrap.remove();
        bar.querySelectorAll('.page-more-keep, .page-more-main, .page-more-unwrapped, .page-more-icon').forEach((el) => el.classList.remove('page-more-keep', 'page-more-main', 'page-more-unwrapped', 'page-more-icon'));
        bar.classList.remove('has-page-more');
        delete bar._pageMore;
    }

    function closeAll() {
        document.querySelectorAll('.page-more-menu:not([hidden])').forEach((menu) => {
            menu.hidden = true;
            const trigger = menu.previousElementSibling;
            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function apply() {
        document.querySelectorAll(BAR_SELECTOR).forEach((bar) => {
            if (QUERY.matches) {
                collapse(bar);
            } else {
                expand(bar);
            }
        });
    }

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.page-more')) {
            closeAll();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll();
        }
    });

    QUERY.addEventListener('change', apply);
    document.addEventListener('birkly:layout-change', apply);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', apply);
    } else {
        apply();
    }
    window.addEventListener('load', () => window.setTimeout(apply, 300));

    const main = document.querySelector('.main-content');
    if (main && typeof MutationObserver !== 'undefined') {
        let pending = 0;
        new MutationObserver(() => {
            if (!QUERY.matches || pending) {
                return;
            }
            pending = window.setTimeout(() => {
                pending = 0;
                apply();
            }, 150);
        }).observe(main, { childList: true, subtree: true });
    }

    window.Birkly = window.Birkly || {};
    window.Birkly.pageActions = { refresh: apply };
})();
