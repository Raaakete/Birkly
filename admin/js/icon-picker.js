/**
 * Shared emoji/icon picker for collection + group icons (P62-A5).
 * Wires to an input via data-icon-picker / Birkly.IconPicker.attach(input).
 */
(function () {
    'use strict';

    window.Birkly = window.Birkly || {};

    var EMOJI_GROUPS = [
        {
            label: 'Content',
            icons: ['📝', '📄', '📰', '📚', '📖', '🗂️', '📁', '📂', '🏷️', '🔖', '📋', '📌']
        },
        {
            label: 'Media',
            icons: ['🖼️', '📷', '🎬', '🎵', '🎧', '🎙️', '🎨', '🖌️', '🎞️', '📺', '📡', '🔊']
        },
        {
            label: 'People & places',
            icons: ['👤', '👥', '🧑‍💼', '🏠', '🏢', '🏪', '🗺️', '📍', '🌍', '🧭', '✈️', '🚗']
        },
        {
            label: 'Commerce & events',
            icons: ['🛒', '💳', '💰', '🏷️', '📦', '🎁', '🎟️', '📅', '⏰', '⭐', '🏆', '🎯']
        },
        {
            label: 'Misc',
            icons: ['⚙️', '🔧', '💡', '🔔', '📣', '💬', '✉️', '🔗', '🛡️', '✅', '❤️', '✨']
        }
    ];

    var modalEl = null;
    var gridEl = null;
    var previewEl = null;
    var activeInput = null;
    var escapeBound = false;

    function ensureModal() {
        if (modalEl) return modalEl;

        modalEl = document.createElement('div');
        modalEl.id = 'birkly-icon-picker-modal';
        modalEl.className = 'modal icon-picker-modal';
        modalEl.setAttribute('role', 'dialog');
        modalEl.setAttribute('aria-modal', 'true');
        modalEl.setAttribute('aria-labelledby', 'birkly-icon-picker-title');
        modalEl.innerHTML =
            '<div class="modal-backdrop" data-icon-picker-close></div>' +
            '<div class="modal-content modal--md">' +
            '  <div class="modal-header">' +
            '    <h2 id="birkly-icon-picker-title">Choose an icon</h2>' +
            '    <button type="button" class="modal-close" data-icon-picker-close aria-label="Close">&times;</button>' +
            '  </div>' +
            '  <div class="modal-body u-stack-4">' +
            '    <div class="icon-picker-preview-row">' +
            '      <span class="icon-picker-preview" id="birkly-icon-picker-preview" aria-hidden="true"></span>' +
            '      <p class="form-help u-m-0">Pick an emoji for the sidebar and content cards. You can still type or paste a custom emoji in the field.</p>' +
            '    </div>' +
            '    <div class="icon-picker-grid" id="birkly-icon-picker-grid"></div>' +
            '  </div>' +
            '  <div class="modal-footer">' +
            '    <button type="button" class="button" data-icon-picker-clear>Clear</button>' +
            '    <button type="button" class="button" data-icon-picker-close>Cancel</button>' +
            '  </div>' +
            '</div>';

        document.body.appendChild(modalEl);
        gridEl = modalEl.querySelector('#birkly-icon-picker-grid');
        previewEl = modalEl.querySelector('#birkly-icon-picker-preview');
        renderGrid();

        modalEl.addEventListener('click', function (e) {
            if (e.target.closest('[data-icon-picker-close]')) {
                e.preventDefault();
                close();
                return;
            }
            if (e.target.closest('[data-icon-picker-clear]')) {
                e.preventDefault();
                applyValue('');
                close();
                return;
            }
            var btn = e.target.closest('[data-icon-value]');
            if (btn) {
                e.preventDefault();
                applyValue(btn.getAttribute('data-icon-value') || '');
                close();
            }
        });

        if (!escapeBound) {
            escapeBound = true;
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modalEl && modalEl.classList.contains('active')) {
                    e.preventDefault();
                    close();
                }
            });
        }

        return modalEl;
    }

    function renderGrid() {
        if (!gridEl) return;
        var html = '';
        EMOJI_GROUPS.forEach(function (group) {
            html += '<section class="icon-picker-group">';
            html += '<h3 class="icon-picker-group-label">' + group.label + '</h3>';
            html += '<div class="icon-picker-group-grid">';
            group.icons.forEach(function (icon) {
                html +=
                    '<button type="button" class="icon-picker-tile" data-icon-value="' +
                    icon +
                    '" title="' +
                    icon +
                    '" aria-label="Choose ' +
                    icon +
                    '">' +
                    '<span aria-hidden="true">' +
                    icon +
                    '</span></button>';
            });
            html += '</div></section>';
        });
        gridEl.innerHTML = html;
    }

    function applyValue(value) {
        if (!activeInput) return;
        activeInput.value = value;
        activeInput.dispatchEvent(new Event('input', { bubbles: true }));
        activeInput.dispatchEvent(new Event('change', { bubbles: true }));
        syncTrigger(activeInput);
    }

    function syncTrigger(input) {
        if (!input) return;
        var wrap = input.closest('.icon-picker-field');
        if (!wrap) return;
        var preview = wrap.querySelector('.icon-picker-field-preview');
        if (preview) {
            preview.textContent = input.value || '—';
            preview.classList.toggle('is-empty', !input.value);
        }
    }

    function open(input) {
        if (!input) return;
        activeInput = input;
        ensureModal();
        if (previewEl) {
            previewEl.textContent = input.value || '—';
            previewEl.classList.toggle('is-empty', !input.value);
        }
        modalEl.classList.add('active');
        modalEl.setAttribute('aria-hidden', 'false');
        var first = modalEl.querySelector('.icon-picker-tile');
        if (first && first.focus) first.focus();
    }

    function close() {
        if (!modalEl) return;
        modalEl.classList.remove('active');
        modalEl.setAttribute('aria-hidden', 'true');
        var input = activeInput;
        activeInput = null;
        if (input && input.focus) input.focus();
    }

    function enhanceInput(input) {
        if (!input || input.dataset.iconPickerBound === '1') return;
        input.dataset.iconPickerBound = '1';

        var wrap = document.createElement('div');
        wrap.className = 'icon-picker-field';
        input.parentNode.insertBefore(wrap, input);

        var preview = document.createElement('span');
        preview.className = 'icon-picker-field-preview';
        preview.setAttribute('aria-hidden', 'true');

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'button is-small icon-picker-open-btn';
        btn.textContent = 'Browse';
        btn.setAttribute('aria-haspopup', 'dialog');
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            open(input);
        });

        wrap.appendChild(preview);
        wrap.appendChild(input);
        wrap.appendChild(btn);

        input.addEventListener('input', function () {
            syncTrigger(input);
        });
        syncTrigger(input);
    }

    function attach(selectorOrElement) {
        var nodes = [];
        if (typeof selectorOrElement === 'string') {
            nodes = Array.prototype.slice.call(document.querySelectorAll(selectorOrElement));
        } else if (selectorOrElement && selectorOrElement.nodeType === 1) {
            nodes = [selectorOrElement];
        }
        nodes.forEach(enhanceInput);
    }

    function autoAttach() {
        attach('#icon, #group-icon, [data-icon-picker]');
    }

    Birkly.IconPicker = {
        open: open,
        close: close,
        attach: attach,
        autoAttach: autoAttach
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoAttach);
    } else {
        autoAttach();
    }
})();
