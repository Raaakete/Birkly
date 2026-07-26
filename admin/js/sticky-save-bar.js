/**
 * Sticky save bar — adds is-stuck class when bar is pinned during scroll.
 */
(function () {
    'use strict';

    function initStickySaveBar(bar) {
        if (!bar || bar.getAttribute('data-sticky-save-init') === '1') {
            return;
        }
        bar.setAttribute('data-sticky-save-init', '1');

        var sentinel = document.createElement('div');
        sentinel.className = 'sticky-save-sentinel';
        sentinel.setAttribute('aria-hidden', 'true');
        sentinel.style.cssText = 'height:1px;margin-top:-1px;pointer-events:none;visibility:hidden;';
        bar.parentNode.insertBefore(sentinel, bar);

        if (typeof IntersectionObserver === 'undefined') {
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            var entry = entries[0];
            if (!entry) return;
            bar.classList.toggle('is-stuck', !entry.isIntersecting);
        }, { threshold: [0] });

        observer.observe(sentinel);
    }

    function initAll() {
        document.querySelectorAll('.main-actions-bar--sticky').forEach(initStickySaveBar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
