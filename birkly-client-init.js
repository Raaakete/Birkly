/**
 * Birkly client bootstrap — load in <head> before first paint.
 * Injected automatically by the CMS when serving template-first project pages.
 */
(function () {
    'use strict';
    var root = document.documentElement;
    root.classList.add('birkly-pending');
    if (document.getElementById('birkly-anti-fouc')) {
        return;
    }
    var style = document.createElement('style');
    style.id = 'birkly-anti-fouc';
    style.textContent = [
        'html.birkly-pending:not(.birkly-ready) header,',
        'html.birkly-pending:not(.birkly-ready) main,',
        'html.birkly-pending:not(.birkly-ready) footer{visibility:hidden}',
        'html.birkly-pending.birkly-ready header,',
        'html.birkly-pending.birkly-ready main,',
        'html.birkly-pending.birkly-ready footer{',
        'visibility:visible;',
        'animation:birkly-region-in .2s ease both}',
        '@keyframes birkly-region-in{from{opacity:0}to{opacity:1}}'
    ].join('');
    (document.head || root).appendChild(style);
})();
