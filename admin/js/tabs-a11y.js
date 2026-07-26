/**
 * ARIA tabs keyboard navigation (WAI-ARIA tabs pattern).
 */
(function (global) {
    'use strict';

    function isVisibleTab(btn) {
        if (!btn || btn.hidden) return false;
        if (btn.classList.contains('tabs-v2-hidden-tab')) return false;
        return btn.offsetParent !== null || btn.getAttribute('aria-selected') === 'true';
    }

    function getVisibleTabs(tablist) {
        return Array.from(tablist.querySelectorAll('[role="tab"], .tab[data-tab]')).filter(isVisibleTab);
    }

    function enhance(tablist, options) {
        if (!tablist || tablist.getAttribute('data-tabs-a11y') === '1') return;
        tablist.setAttribute('data-tabs-a11y', '1');
        if (!tablist.getAttribute('role')) {
            tablist.setAttribute('role', 'tablist');
        }

        options = options || {};

        tablist.addEventListener('keydown', function (e) {
            var tabs = getVisibleTabs(tablist);
            if (!tabs.length) return;

            var current = document.activeElement;
            var index = tabs.indexOf(current);
            if (index < 0) {
                index = tabs.findIndex(function (t) {
                    return t.getAttribute('aria-selected') === 'true' || t.classList.contains('active');
                });
            }
            if (index < 0) index = 0;

            var nextIndex = index;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                e.preventDefault();
                nextIndex = (index + 1) % tabs.length;
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                e.preventDefault();
                nextIndex = (index - 1 + tabs.length) % tabs.length;
            } else if (e.key === 'Home') {
                e.preventDefault();
                nextIndex = 0;
            } else if (e.key === 'End') {
                e.preventDefault();
                nextIndex = tabs.length - 1;
            } else if (e.key === 'Enter' || e.key === ' ') {
                if (tabs.indexOf(current) >= 0) {
                    e.preventDefault();
                    activateTabButton(current, options);
                }
                return;
            } else {
                return;
            }

            tabs[nextIndex].focus();
            if (options.activateOnFocus) {
                activateTabButton(tabs[nextIndex], options);
            }
        });
    }

    function activateTabButton(btn, options) {
        if (typeof options.onActivate === 'function') {
            var tabId = btn.getAttribute('data-tab');
            options.onActivate(tabId, btn);
        } else {
            btn.click();
        }
    }

    function syncTabButton(btn, active) {
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
        btn.tabIndex = active ? 0 : -1;
        btn.classList.toggle('active', !!active);
    }

    function syncTabPanel(panel, active) {
        if (!panel) return;
        panel.classList.toggle('active', !!active);
        panel.hidden = !active;
    }

    global.Birkly = global.Birkly || {};
    global.Birkly.TabsA11y = {
        enhance: enhance,
        syncTabButton: syncTabButton,
        syncTabPanel: syncTabPanel,
        getVisibleTabs: getVisibleTabs,
    };
})(window);
