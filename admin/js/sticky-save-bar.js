/**
 * Sticky save bar — adds is-stuck class when bar is pinned during scroll.
 */
(function () {
    'use strict';

    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function playStuckEnterAnimation(bar) {
        if (prefersReducedMotion()) {
            return;
        }

        bar.classList.remove('is-stuck-enter');
        void bar.offsetWidth;
        bar.classList.add('is-stuck-enter');

        function cleanup() {
            bar.classList.remove('is-stuck-enter');
            bar.removeEventListener('animationend', onAnimationEnd);
        }

        function onAnimationEnd(event) {
            if (event.target !== bar.querySelector('.main-actions-right') &&
                event.target !== bar.querySelector('.main-actions-left')) {
                return;
            }
            cleanup();
        }

        bar.addEventListener('animationend', onAnimationEnd);
        window.setTimeout(cleanup, 700);
    }

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

        var wasStuck = false;

        var observer = new IntersectionObserver(function (entries) {
            var entry = entries[0];
            if (!entry) return;

            var isStuck = !entry.isIntersecting;
            bar.classList.toggle('is-stuck', isStuck);

            if (isStuck && !wasStuck) {
                playStuckEnterAnimation(bar);
            }

            wasStuck = isStuck;
        }, {
            threshold: [0]
        });

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
