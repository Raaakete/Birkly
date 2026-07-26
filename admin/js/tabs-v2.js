/**
 * Tab v2 — responsive tab navigation
 * Desktop (≥1280): standard horizontal tabs
 * Tablet (1024–1279): horizontal scroll + More overflow menu
 * Mobile (<1024): select picker (no link cloud)
 */
(function () {
    'use strict';

    var BP_XL = 1280;
    var BP_LG = 1024;

    function getMode() {
        var w = window.innerWidth;
        if (w >= BP_XL) {
            return 'desktop';
        }
        if (w >= BP_LG) {
            return 'scroll';
        }
        return 'picker';
    }

    function getTabButtons(nav) {
        return Array.prototype.slice.call(nav.querySelectorAll('.tab[data-tab]')).filter(function (btn) {
            return !btn.hidden
                && !btn.classList.contains('tab--hidden')
                && btn.getAttribute('aria-hidden') !== 'true';
        });
    }

    function getTabLabel(btn) {
        var clone = btn.cloneNode(true);
        clone.querySelectorAll('svg, .update-badge, .tabs-v2-picker-ignore').forEach(function (el) {
            el.remove();
        });
        return (clone.textContent || '').trim();
    }

    function rebuildPickerOptions(picker, buttons) {
        if (!picker) {
            return;
        }
        picker.innerHTML = '';
        buttons.forEach(function (btn) {
            var opt = document.createElement('option');
            opt.value = btn.getAttribute('data-tab') || '';
            opt.textContent = getTabLabel(btn);
            if (btn.classList.contains('active')) {
                opt.selected = true;
            }
            picker.appendChild(opt);
        });
    }

    function buildPicker(wrap, nav, buttons) {
        var pickerWrap = wrap.querySelector('.tabs-v2-picker-wrap');
        var picker;

        if (!pickerWrap) {
            pickerWrap = document.createElement('div');
            pickerWrap.className = 'tabs-v2-picker-wrap';

            var icon = document.createElement('span');
            icon.className = 'tabs-v2-picker-icon';
            icon.setAttribute('aria-hidden', 'true');
            icon.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="18" x2="20" y2="18"></line></svg>';

            picker = document.createElement('select');
            picker.className = 'tabs-v2-picker';
            picker.setAttribute('aria-label', (window.Birkly && window.Birkly.i18n && window.Birkly.i18n.t)
                ? window.Birkly.i18n.t('admin.select_section', 'Select section')
                : 'Select section');

            var chevron = document.createElement('span');
            chevron.className = 'tabs-v2-picker-chevron';
            chevron.setAttribute('aria-hidden', 'true');
            chevron.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>';

            picker.addEventListener('change', function () {
                var tabId = picker.value;
                var target = nav.querySelector('.tab[data-tab="' + tabId + '"]');
                if (target) {
                    target.click();
                }
            });

            pickerWrap.appendChild(icon);
            pickerWrap.appendChild(picker);
            pickerWrap.appendChild(chevron);
            wrap.insertBefore(pickerWrap, nav);
        } else {
            picker = pickerWrap.querySelector('.tabs-v2-picker');
        }

        rebuildPickerOptions(picker, buttons);
        return picker;
    }

    function buildMoreMenu(wrap, nav, buttons) {
        if (wrap.querySelector('.tabs-v2-more')) {
            return {
                moreBtn: wrap.querySelector('.tabs-v2-more'),
                moreMenu: wrap.querySelector('.tabs-v2-more-menu'),
            };
        }

        var moreWrap = document.createElement('div');
        moreWrap.className = 'tabs-v2-more-wrap';

        var moreBtn = document.createElement('button');
        moreBtn.type = 'button';
        moreBtn.className = 'tabs-v2-more btn btn-secondary btn-sm';
        moreBtn.textContent = (window.Birkly && window.Birkly.i18n && window.Birkly.i18n.t)
            ? window.Birkly.i18n.t('admin.more', 'More')
            : 'More';
        moreBtn.setAttribute('aria-haspopup', 'true');
        moreBtn.setAttribute('aria-expanded', 'false');

        var moreMenu = document.createElement('div');
        moreMenu.className = 'tabs-v2-more-menu';
        moreMenu.setAttribute('role', 'menu');
        moreMenu.hidden = true;

        buttons.forEach(function (btn) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'tabs-v2-more-item';
            item.setAttribute('role', 'menuitem');
            item.setAttribute('data-tab', btn.getAttribute('data-tab') || '');
            item.textContent = getTabLabel(btn);
            item.addEventListener('click', function () {
                btn.click();
                moreMenu.hidden = true;
                moreBtn.setAttribute('aria-expanded', 'false');
            });
            moreMenu.appendChild(item);
        });

        moreBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = moreMenu.hidden;
            moreMenu.hidden = !open;
            moreBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', function () {
            moreMenu.hidden = true;
            moreBtn.setAttribute('aria-expanded', 'false');
        });

        moreWrap.appendChild(moreBtn);
        moreWrap.appendChild(moreMenu);
        wrap.appendChild(moreWrap);

        return { moreBtn: moreBtn, moreMenu: moreMenu };
    }

    function updateScrollOverflow(wrap, nav, buttons, moreMenu) {
        if (!moreMenu) {
            return;
        }

        var navRect = nav.getBoundingClientRect();
        var overflow = [];

        buttons.forEach(function (btn) {
            var rect = btn.getBoundingClientRect();
            var isOverflow = rect.right > navRect.right - 4 || rect.left < navRect.left;
            btn.classList.toggle('tabs-v2-hidden-tab', isOverflow);
            if (isOverflow) {
                overflow.push(btn.getAttribute('data-tab'));
            }
        });

        moreMenu.querySelectorAll('.tabs-v2-more-item').forEach(function (item) {
            var tabId = item.getAttribute('data-tab');
            item.hidden = overflow.indexOf(tabId) === -1;
        });

        var moreWrap = wrap.querySelector('.tabs-v2-more-wrap');
        if (moreWrap) {
            moreWrap.hidden = overflow.length === 0;
        }
    }

    function syncPicker(picker, buttons) {
        if (!picker) {
            return;
        }
        buttons.forEach(function (btn) {
            var tabId = btn.getAttribute('data-tab');
            var opt = picker.querySelector('option[value="' + tabId + '"]');
            if (opt) {
                opt.selected = btn.classList.contains('active');
            }
        });
    }

    function applyMode(wrap) {
        var nav = wrap.querySelector('.tabs');
        if (!nav) {
            return;
        }

        var mode = getMode();
        var buttons = getTabButtons(nav);
        var pickerWrap = wrap.querySelector('.tabs-v2-picker-wrap');
        var picker = pickerWrap ? pickerWrap.querySelector('.tabs-v2-picker') : null;
        var moreParts = wrap.querySelector('.tabs-v2-more-wrap');

        wrap.dataset.tabsMode = mode;
        wrap.classList.toggle('tabs-v2--desktop', mode === 'desktop');
        wrap.classList.toggle('tabs-v2--scroll', mode === 'scroll');
        wrap.classList.toggle('tabs-v2--picker', mode === 'picker');

        if (mode === 'picker') {
            nav.hidden = true;
            if (moreParts) {
                moreParts.hidden = true;
            }
            picker = buildPicker(wrap, nav, buttons);
            if (pickerWrap) {
                pickerWrap.hidden = false;
            }
            syncPicker(picker, buttons);
            return;
        }

        nav.hidden = false;
        if (pickerWrap) {
            pickerWrap.hidden = true;
        }

        buttons.forEach(function (btn) {
            btn.classList.remove('tabs-v2-hidden-tab');
        });

        if (mode === 'scroll') {
            var parts = buildMoreMenu(wrap, nav, buttons);
            parts.moreBtn.parentElement.hidden = false;
            updateScrollOverflow(wrap, nav, buttons, parts.moreMenu);
        } else if (moreParts) {
            moreParts.hidden = true;
        }
    }

    function initTabsV2(wrap) {
        if (!wrap || wrap.dataset.tabsV2Init === '1') {
            return;
        }
        wrap.dataset.tabsV2Init = '1';
        wrap.classList.add('tabs-v2');

        var nav = wrap.querySelector('.tabs');
        if (!nav) {
            return;
        }

        applyMode(wrap);

        nav.addEventListener('click', function () {
            window.requestAnimationFrame(function () {
                applyMode(wrap);
            });
        });

        if (typeof MutationObserver !== 'undefined') {
            var mo = new MutationObserver(function () {
                if (wrap.dataset.tabsMode === 'picker') {
                    var buttons = getTabButtons(nav);
                    var pickerEl = wrap.querySelector('.tabs-v2-picker');
                    rebuildPickerOptions(pickerEl, buttons);
                    syncPicker(pickerEl, buttons);
                }
            });
            mo.observe(nav, { attributes: true, subtree: true, attributeFilter: ['class'] });
        }

        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                applyMode(wrap);
            }, 100);
        });

        if (typeof ResizeObserver !== 'undefined') {
            var ro = new ResizeObserver(function () {
                if (wrap.dataset.tabsMode === 'scroll') {
                    var navEl = wrap.querySelector('.tabs');
                    var buttons = getTabButtons(navEl);
                    var moreMenu = wrap.querySelector('.tabs-v2-more-menu');
                    updateScrollOverflow(wrap, navEl, buttons, moreMenu);
                }
            });
            ro.observe(nav);
        }
    }

    function initAll() {
        document.querySelectorAll('.tabs-v2-root').forEach(initTabsV2);
    }

    window.Birkly = window.Birkly || {};
    window.Birkly.TabsV2 = {
        init: initTabsV2,
        initAll: initAll,
        refresh: applyMode,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
