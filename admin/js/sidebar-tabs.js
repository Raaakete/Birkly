/**
 * Scoped tab switching for editor sidebars only (.sidebar-tabs).
 */
(function () {
    'use strict';

    function initSidebarTabs(container) {
        const tabBtns = container.querySelectorAll('.tab-btn');
        const tabPanes = container.querySelectorAll('.tab-content > .tab-pane');

        tabBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const tabName = this.dataset.tab;

                container.querySelectorAll('.tab-btn').forEach(function (b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');

                tabPanes.forEach(function (pane) {
                    pane.classList.remove('active');
                });

                const targetPane = container.querySelector('#' + tabName + '-tab');
                if (targetPane) {
                    targetPane.classList.add('active');
                }
            });
        });
    }

    function init() {
        document.querySelectorAll('.sidebar-tabs').forEach(initSidebarTabs);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
