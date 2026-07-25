/**
 * BirklyFeedback — toast notifications and confirm modal (P44-M7).
 * Replaces native alert()/confirm() across the admin UI.
 */
(function (global) {
    'use strict';

    var TOAST_DURATION = 4000;
    var confirmResolve = null;

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = String(text ?? '');
        return div.innerHTML;
    }

    function ensureToastRegion() {
        var region = document.getElementById('birkly-feedback-toast-region');
        if (region) return region;

        region = document.createElement('div');
        region.id = 'birkly-feedback-toast-region';
        region.className = 'birkly-feedback-toast-region';
        region.setAttribute('aria-live', 'polite');
        region.setAttribute('aria-atomic', 'false');
        region.setAttribute('role', 'status');
        document.body.appendChild(region);
        return region;
    }

    function normalizeToastType(type) {
        var t = String(type || 'info').toLowerCase();
        if (t === 'danger') return 'error';
        if (['success', 'error', 'warning', 'info'].indexOf(t) !== -1) return t;
        return 'info';
    }

    function toast(message, type) {
        var region = ensureToastRegion();
        var toastType = normalizeToastType(type);
        var el = document.createElement('div');
        el.className = 'birkly-feedback-toast birkly-feedback-toast--' + toastType;
        el.textContent = String(message ?? '');
        region.appendChild(el);

        global.setTimeout(function () {
            el.style.opacity = '0';
            el.style.transition = 'opacity 0.2s ease';
            global.setTimeout(function () {
                el.remove();
            }, 220);
        }, TOAST_DURATION);
    }

    function closeConfirmModal(modal, result) {
        if (!modal) return;
        modal.classList.remove('active');
        modal.style.display = 'none';
        if (typeof confirmResolve === 'function') {
            var resolve = confirmResolve;
            confirmResolve = null;
            resolve(!!result);
        }
    }

    function ensureConfirmModal() {
        var modal = document.getElementById('birkly-feedback-confirm-modal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = 'birkly-feedback-confirm-modal';
        modal.className = 'modal-backdrop birkly-feedback-confirm-modal';
        modal.innerHTML =
            '<div class="modal-content" style="max-width: 480px;">' +
            '<div class="modal-header">' +
            '<h2 id="birkly-feedback-confirm-title">Confirm</h2>' +
            '<button type="button" class="modal-close-btn" data-birkly-feedback-action="cancel" aria-label="Close">&times;</button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<p id="birkly-feedback-confirm-message"></p>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-birkly-feedback-action="cancel">Cancel</button>' +
            '<button type="button" class="btn btn-primary" data-birkly-feedback-action="confirm">Confirm</button>' +
            '</div>' +
            '</div>';

        document.body.appendChild(modal);

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeConfirmModal(modal, false);
            }
        });

        modal.querySelectorAll('[data-birkly-feedback-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = btn.getAttribute('data-birkly-feedback-action');
                closeConfirmModal(modal, action === 'confirm');
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) {
                closeConfirmModal(modal, false);
            }
        });

        return modal;
    }

    function confirm(message) {
        return new Promise(function (resolve) {
            var modal = ensureConfirmModal();
            var messageEl = modal.querySelector('#birkly-feedback-confirm-message');
            if (messageEl) {
                messageEl.textContent = String(message ?? '');
            }
            confirmResolve = resolve;
            modal.style.display = 'flex';
            requestAnimationFrame(function () {
                modal.classList.add('active');
            });
            var confirmBtn = modal.querySelector('[data-birkly-feedback-action="confirm"]');
            if (confirmBtn) confirmBtn.focus();
        });
    }

    global.BirklyFeedback = {
        toast: toast,
        confirm: confirm
    };

    // Bridge for hosting-wizard legacy call
    global.Birkly = global.Birkly || {};
    global.Birkly.Utils = global.Birkly.Utils || {};
    if (typeof global.Birkly.Utils.showToast !== 'function') {
        global.Birkly.Utils.showToast = toast;
    }

    // Global shim: route legacy alert/confirm when BirklyFeedback is loaded.
    // Permission-denied handling in utils.js runs first; this catches the rest.
    if (!global.__birklyFeedbackShimmed) {
        global.__birklyFeedbackShimmed = true;

        var nativeAlert = global.alert ? global.alert.bind(global) : null;
        var nativeConfirm = global.confirm ? global.confirm.bind(global) : null;

        global.alert = function (message) {
            var msg = String(message ?? '');
            var looksLikePermissionError =
                /forbidden|permission denied|insufficient permissions|access denied|unauthorized/i.test(msg);
            var recentlyForbidden = (Date.now() - (global.__birklyLastForbiddenAt || 0)) < 2500;
            if (looksLikePermissionError || recentlyForbidden) {
                if (nativeAlert) return nativeAlert(message);
                return;
            }
            var isSuccess = /success|saved|completed|restored|submitted successfully/i.test(msg);
            toast(msg, isSuccess ? 'success' : 'error');
        };

        global.confirm = function (message) {
            // Synchronous confirm cannot be shimmed; log and defer to native only if needed.
            // All admin JS should use BirklyFeedback.confirm (async) instead.
            if (nativeConfirm) {
                console.warn('[BirklyFeedback] Sync confirm() called; use BirklyFeedback.confirm() instead.');
                return nativeConfirm(message);
            }
            return false;
        };
    }
})(window);
