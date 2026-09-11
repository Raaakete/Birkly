/**
 * BirklyFeedback — toast notifications, confirm, and prompt modals (P44-M7+).
 * Replaces native alert()/confirm()/prompt() across the admin UI.
 */
(function (global) {
    'use strict';

    var TOAST_DURATION = 5000;
    var confirmResolve = null;
    var promptResolve = null;
    var focusTrapHandler = null;

    function uiT(key, fallback) {
        if (global.Birkly && global.Birkly.i18n && typeof global.Birkly.i18n.t === 'function') {
            return global.Birkly.i18n.t('admin.' + key, fallback);
        }
        return fallback != null ? fallback : key;
    }

    var TOAST_ICONS = {
        success: '\u2713',
        error: '\u2717',
        warning: '!',
        info: 'i'
    };

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

    function removeToast(el) {
        if (!el || !el.parentNode) return;
        el.classList.add('birkly-feedback-toast--leaving');
        global.setTimeout(function () {
            el.remove();
        }, 200);
    }

    function toast(message, type) {
        var region = ensureToastRegion();
        var toastType = normalizeToastType(type);
        var el = document.createElement('div');
        el.className = 'birkly-feedback-toast birkly-feedback-toast--' + toastType;
        el.setAttribute('role', toastType === 'error' ? 'alert' : 'status');

        el.innerHTML =
            '<span class="birkly-feedback-toast__icon" aria-hidden="true">' + escapeHtml(TOAST_ICONS[toastType] || TOAST_ICONS.info) + '</span>' +
            '<span class="birkly-feedback-toast__message">' + escapeHtml(message) + '</span>' +
            '<button type="button" class="birkly-feedback-toast__close" aria-label="' + escapeHtml(uiT('ui_feedback_dismiss_toast', 'Dismiss notification')) + '">&times;</button>';

        var closeBtn = el.querySelector('.birkly-feedback-toast__close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                removeToast(el);
            });
        }

        region.appendChild(el);

        var timer = global.setTimeout(function () {
            removeToast(el);
        }, TOAST_DURATION);

        el.addEventListener('mouseenter', function () {
            global.clearTimeout(timer);
        });
        el.addEventListener('mouseleave', function () {
            timer = global.setTimeout(function () {
                removeToast(el);
            }, 2000);
        });
    }

    function getFocusableElements(container) {
        return Array.from(container.querySelectorAll(
            'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter(function (el) {
            return el.offsetParent !== null || el === document.activeElement;
        });
    }

    function trapFocus(modal) {
        releaseFocusTrap();
        focusTrapHandler = function (e) {
            if (e.key !== 'Tab' || !modal.classList.contains('active')) return;
            var focusable = getFocusableElements(modal);
            if (!focusable.length) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        };
        document.addEventListener('keydown', focusTrapHandler);
    }

    function releaseFocusTrap() {
        if (focusTrapHandler) {
            document.removeEventListener('keydown', focusTrapHandler);
            focusTrapHandler = null;
        }
    }

    function openModal(modal) {
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        requestAnimationFrame(function () {
            modal.classList.add('active');
            trapFocus(modal);
        });
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('active');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        releaseFocusTrap();
    }

    function closeConfirmModal(modal, result) {
        closeModal(modal);
        if (typeof confirmResolve === 'function') {
            var resolve = confirmResolve;
            confirmResolve = null;
            resolve(!!result);
        }
    }

    function closePromptModal(modal, result) {
        closeModal(modal);
        if (typeof promptResolve === 'function') {
            var resolve = promptResolve;
            promptResolve = null;
            resolve(result);
        }
    }

    function renderConfirmDetails(modal, details) {
        var detailsEl = modal.querySelector('#birkly-feedback-confirm-details');
        if (!detailsEl) return;

        if (!details) {
            detailsEl.innerHTML = '';
            detailsEl.hidden = true;
            return;
        }

        var intro = '';
        var items = [];

        if (Array.isArray(details)) {
            items = details;
        } else if (typeof details === 'string') {
            intro = details;
        } else if (typeof details === 'object') {
            intro = details.intro || '';
            items = Array.isArray(details.items) ? details.items : [];
        }

        var html = '';
        if (intro) {
            html += '<p class="birkly-feedback-confirm-details__intro">' + escapeHtml(intro) + '</p>';
        }
        if (items.length) {
            html += '<ul class="birkly-feedback-confirm-details__list">' + items.map(function (item) {
                return '<li>' + escapeHtml(item) + '</li>';
            }).join('') + '</ul>';
        }

        detailsEl.innerHTML = html;
        detailsEl.hidden = !html;
    }

    function ensureConfirmModal() {
        var modal = document.getElementById('birkly-feedback-confirm-modal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = 'birkly-feedback-confirm-modal';
        modal.className = 'modal-backdrop birkly-feedback-confirm-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'birkly-feedback-confirm-title');
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML =
            '<div class="modal-content birkly-feedback-dialog">' +
            '<div class="modal-header">' +
            '<h2 id="birkly-feedback-confirm-title">' + escapeHtml(uiT('ui_feedback_confirm_title', 'Confirm')) + '</h2>' +
            '<button type="button" class="modal-close-btn" data-birkly-feedback-action="cancel" aria-label="' + escapeHtml(uiT('ui_feedback_close', 'Close')) + '">&times;</button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<p id="birkly-feedback-confirm-message"></p>' +
            '<div id="birkly-feedback-confirm-details" class="birkly-feedback-confirm-details" hidden></div>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-birkly-feedback-action="cancel">' + escapeHtml(uiT('ui_feedback_cancel_label', 'Cancel')) + '</button>' +
            '<button type="button" class="btn btn-primary" data-birkly-feedback-action="confirm">' + escapeHtml(uiT('ui_feedback_confirm_label', 'Confirm')) + '</button>' +
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

        if (!global.__birklyFeedbackEscapeBound) {
            global.__birklyFeedbackEscapeBound = true;
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                var confirmEl = document.getElementById('birkly-feedback-confirm-modal');
                if (confirmEl && confirmEl.classList.contains('active')) {
                    closeConfirmModal(confirmEl, false);
                }
                var promptEl = document.getElementById('birkly-feedback-prompt-modal');
                if (promptEl && promptEl.classList.contains('active')) {
                    closePromptModal(promptEl, null);
                }
            });
        }

        return modal;
    }

    function ensurePromptModal() {
        var modal = document.getElementById('birkly-feedback-prompt-modal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = 'birkly-feedback-prompt-modal';
        modal.className = 'modal-backdrop birkly-feedback-prompt-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'birkly-feedback-prompt-title');
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML =
            '<div class="modal-content birkly-feedback-dialog">' +
            '<div class="modal-header">' +
            '<h2 id="birkly-feedback-prompt-title">' + escapeHtml(uiT('ui_feedback_input_required', 'Input required')) + '</h2>' +
            '<button type="button" class="modal-close-btn" data-birkly-prompt-action="cancel" aria-label="' + escapeHtml(uiT('ui_feedback_close', 'Close')) + '">&times;</button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<p id="birkly-feedback-prompt-message"></p>' +
            '<input type="text" id="birkly-feedback-prompt-input" class="input" autocomplete="off">' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-birkly-prompt-action="cancel">' + escapeHtml(uiT('ui_feedback_cancel_label', 'Cancel')) + '</button>' +
            '<button type="button" class="btn btn-primary" data-birkly-prompt-action="confirm">' + escapeHtml(uiT('ui_feedback_ok_label', 'OK')) + '</button>' +
            '</div>' +
            '</div>';

        document.body.appendChild(modal);

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closePromptModal(modal, null);
            }
        });

        modal.querySelectorAll('[data-birkly-prompt-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var action = btn.getAttribute('data-birkly-prompt-action');
                if (action === 'confirm') {
                    var input = modal.querySelector('#birkly-feedback-prompt-input');
                    closePromptModal(modal, input ? input.value : '');
                } else {
                    closePromptModal(modal, null);
                }
            });
        });

        var input = modal.querySelector('#birkly-feedback-prompt-input');
        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    closePromptModal(modal, input.value);
                }
            });
        }

        return modal;
    }

    /**
     * @param {string} message
     * @param {{ title?: string, confirmLabel?: string, cancelLabel?: string, danger?: boolean, details?: string|string[]|{ intro?: string, items?: string[] } }} [options]
     */
    function confirm(message, options) {
        options = options || {};
        return new Promise(function (resolve) {
            var modal = ensureConfirmModal();
            var titleEl = modal.querySelector('#birkly-feedback-confirm-title');
            var messageEl = modal.querySelector('#birkly-feedback-confirm-message');
            var confirmBtn = modal.querySelector('[data-birkly-feedback-action="confirm"]');
            var cancelBtn = modal.querySelector('[data-birkly-feedback-action="cancel"]');

            if (titleEl) titleEl.textContent = options.title || uiT('ui_feedback_confirm_title', 'Confirm');
            if (messageEl) messageEl.textContent = String(message ?? '');
            renderConfirmDetails(modal, options.details);
            if (confirmBtn) {
                confirmBtn.textContent = options.confirmLabel || uiT('ui_feedback_confirm_label', 'Confirm');
                confirmBtn.className = options.danger ? 'btn btn-danger' : 'btn btn-primary';
            }
            if (cancelBtn) cancelBtn.textContent = options.cancelLabel || uiT('ui_feedback_cancel_label', 'Cancel');

            modal.classList.toggle('birkly-feedback-confirm-modal--danger', !!options.danger);
            confirmResolve = resolve;
            openModal(modal);
            if (confirmBtn) confirmBtn.focus();
        });
    }

    /**
     * @param {string} message
     * @param {string[]} changes
     * @param {{ title?: string, confirmLabel?: string, cancelLabel?: string, intro?: string }} [options]
     */
    function confirmDestructive(message, changes, options) {
        options = options || {};
        return confirm(message, {
            title: options.title || uiT('ui_feedback_destructive_title', 'Confirm destructive changes'),
            confirmLabel: options.confirmLabel || uiT('ui_feedback_continue', 'Continue'),
            cancelLabel: options.cancelLabel || uiT('ui_feedback_cancel_label', 'Cancel'),
            danger: true,
            details: {
                intro: options.intro || uiT('ui_feedback_destructive_intro', 'These changes can affect existing content:'),
                items: Array.isArray(changes) ? changes : []
            }
        });
    }

    /**
     * @param {string} message
     * @param {string} [defaultValue]
     * @param {{ title?: string, confirmLabel?: string, cancelLabel?: string, placeholder?: string }} [options]
     * @returns {Promise<string|null>}
     */
    function prompt(message, defaultValue, options) {
        options = options || {};
        return new Promise(function (resolve) {
            var modal = ensurePromptModal();
            var titleEl = modal.querySelector('#birkly-feedback-prompt-title');
            var messageEl = modal.querySelector('#birkly-feedback-prompt-message');
            var input = modal.querySelector('#birkly-feedback-prompt-input');
            var confirmBtn = modal.querySelector('[data-birkly-prompt-action="confirm"]');
            var cancelBtn = modal.querySelector('[data-birkly-prompt-action="cancel"]');

            if (titleEl) titleEl.textContent = options.title || uiT('ui_feedback_input_required', 'Input required');
            if (messageEl) messageEl.textContent = String(message ?? '');
            if (input) {
                input.value = defaultValue != null ? String(defaultValue) : '';
                input.placeholder = options.placeholder || '';
            }
            if (confirmBtn) confirmBtn.textContent = options.confirmLabel || uiT('ui_feedback_ok_label', 'OK');
            if (cancelBtn) cancelBtn.textContent = options.cancelLabel || uiT('ui_feedback_cancel_label', 'Cancel');

            promptResolve = resolve;
            openModal(modal);
            if (input) {
                input.focus();
                input.select();
            }
        });
    }

    global.BirklyFeedback = {
        toast: toast,
        confirm: confirm,
        confirmDestructive: confirmDestructive,
        prompt: prompt
    };

    global.Birkly = global.Birkly || {};
    global.Birkly.Utils = global.Birkly.Utils || {};
    if (typeof global.Birkly.Utils.showToast !== 'function') {
        global.Birkly.Utils.showToast = toast;
    }

    if (!global.__birklyFeedbackShimmed) {
        global.__birklyFeedbackShimmed = true;

        var nativeAlert = global.alert ? global.alert.bind(global) : null;

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
    }
})(window);
