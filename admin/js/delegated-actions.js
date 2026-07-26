/**
 * Delegated click handlers — replaces inline onclick (CSP-safe).
 */
(function (global) {
    'use strict';

    function parseArgs(el) {
        var raw = el.getAttribute('data-admin-args');
        if (!raw) return [];
        try {
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [parsed];
        } catch (_) {
            return [];
        }
    }

    function resolveAction(el) {
        var objectPath = el.getAttribute('data-admin-object');
        var method = el.getAttribute('data-admin-method');
        if (objectPath && method) {
            var parts = objectPath.split('.');
            var ctx = global;
            for (var i = 0; i < parts.length; i++) {
                ctx = ctx[parts[i]];
                if (ctx == null) return null;
            }
            if (typeof ctx[method] === 'function') {
                return ctx[method].bind(ctx);
            }
            return null;
        }

        var action = el.getAttribute('data-admin-action');
        if (!action) return null;

        var fn = global[action];
        if (typeof fn === 'function') return fn;

        // dotted path on window
        parts = action.split('.');
        ctx = global;
        for (var j = 0; j < parts.length; j++) {
            ctx = ctx[parts[j]];
            if (ctx == null) return null;
        }
        return typeof ctx === 'function' ? ctx : null;
    }

    function hideModal(target) {
        if (!target) return;
        if (target.charAt(0) === '#') {
            var byId = document.querySelector(target);
            if (byId) {
                byId.style.display = 'none';
                byId.classList.remove('active');
            }
            return;
        }
        var modal = document.getElementById(target);
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
        }
    }

    document.addEventListener('click', function (e) {
        var dismissEl = e.target.closest('[data-modal-dismiss]');
        if (dismissEl) {
            e.preventDefault();
            var targetId = dismissEl.getAttribute('data-modal-dismiss');
            if (targetId && targetId !== 'self') {
                hideModal(targetId.charAt(0) === '#' ? targetId : '#' + targetId);
                return;
            }
            var modal = dismissEl.closest('.modal, .modal-backdrop.birkly-feedback-confirm-modal, .birkly-feedback-prompt-modal, .keyboard-shortcuts');
            if (modal) {
                if (modal.classList.contains('modal-backdrop')) {
                    modal = modal.closest('.modal') || modal;
                }
                if (modal.id === 'birkly-feedback-confirm-modal' || modal.id === 'birkly-feedback-prompt-modal') {
                    return; // handled by ui-feedback.js
                }
                if (modal.classList.contains('keyboard-shortcuts')) {
                    modal.remove();
                    return;
                }
                modal.style.display = 'none';
                modal.classList.remove('active');
            }
            return;
        }

        var closeEl = e.target.closest('[data-modal-close]');
        if (closeEl) {
            e.preventDefault();
            var closeTarget = closeEl.getAttribute('data-modal-close');
            if (closeTarget) {
                hideModal(closeTarget.charAt(0) === '#' ? closeTarget : '#' + closeTarget);
            }
            return;
        }

        var actionEl = e.target.closest('[data-admin-action], [data-admin-object]');
        if (!actionEl) return;

        var fn = resolveAction(actionEl);
        if (typeof fn !== 'function') {
            console.warn('[delegated-actions] Unknown action:', actionEl.getAttribute('data-admin-action') || actionEl.getAttribute('data-admin-method'));
            return;
        }

        e.preventDefault();
        fn.apply(null, parseArgs(actionEl));
    });
})(window);
