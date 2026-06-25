/**
 * Contextual ? help links (P3-M11)
 * Elements with data-help-topic get a link to Help with the matching topic.
 */
(function () {
    'use strict';

    function injectHelpLinks() {
        document.querySelectorAll('[data-help-topic]').forEach((el) => {
            if (el.querySelector('.contextual-help-link')) return;

            const topic = el.getAttribute('data-help-topic');
            if (!topic) return;

            const link = document.createElement('a');
            link.href = '/admin/index.php?page=help&topic=' + encodeURIComponent(topic);
            link.className = 'contextual-help-link';
            link.title = 'Help: ' + topic;
            link.setAttribute('aria-label', 'Help for ' + topic);
            link.textContent = '?';

            el.appendChild(link);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectHelpLinks);
    } else {
        injectHelpLinks();
    }
})();
