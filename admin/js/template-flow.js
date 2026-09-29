/**
 * Template flow — one pattern for every place that keeps editable templates
 * (Commerce invoices and emails, User emails, …):
 *
 *   list    rows as slabs: icon · name and meta · status · chevron
 *   sheet   New template: kind, name, start from, make it active, what happens next
 *   editor  the panel swaps to an editor view with a back button; tokens insert
 *           at the cursor of the body editor
 *
 * UI only: callers own data, saving and the editor fields.
 */
(function () {
    'use strict';

    window.Birkly = window.Birkly || {};

    function t(key, fallback, params) {
        const i18n = window.Birkly.i18n;
        let text = i18n && typeof i18n.t === 'function' ? i18n.t('admin.' + key, fallback) : fallback;
        Object.keys(params || {}).forEach(function (name) {
            text = String(text).split('{' + name + '}').join(String(params[name]));
        });
        return text;
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function icon(name) {
        return typeof window.Birkly.icon === 'function' ? window.Birkly.icon(name) : '';
    }

    function toast(message) {
        const utils = window.Birkly.Utils;
        if (utils && typeof utils.showToast === 'function') {
            utils.showToast(message, 'success');
        }
    }

    function switchMarkup(id, checked, labelledBy) {
        return '<button type="button" class="switch" role="switch" id="' + esc(id) + '" aria-checked="' + (checked ? 'true' : 'false') + '"'
            + (labelledBy ? ' aria-labelledby="' + esc(labelledBy) + '"' : '') + '>'
            + '<span>' + icon('x') + '</span><span>' + icon('check') + '</span></button>';
    }

    function bindSwitch(btn) {
        btn.addEventListener('click', function () {
            btn.setAttribute('aria-checked', btn.getAttribute('aria-checked') === 'true' ? 'false' : 'true');
        });
    }

    /** Miniature page drawings for the Start from cards. */
    function thumb(kind) {
        const line = function () { return '<span class="choice-thumb-line"></span>'; };
        switch (kind) {
            case 'blank':
                return '<span class="choice-thumb is-blank" aria-hidden="true"></span>';
            case 'copy':
                return '<span class="choice-thumb is-icon" aria-hidden="true">' + icon('copy') + '</span>';
            case 'band':
                return '<span class="choice-thumb" aria-hidden="true"><span class="choice-thumb-band"></span>'
                    + line() + '<span class="choice-thumb-box"></span></span>';
            case 'lines':
                return '<span class="choice-thumb" aria-hidden="true">' + line() + line() + line() + line() + '</span>';
            case 'mail':
                return '<span class="choice-thumb" aria-hidden="true"><span class="choice-thumb-block is-wide"></span>'
                    + line() + line() + line() + '</span>';
            default:
                return '<span class="choice-thumb" aria-hidden="true"><span class="choice-thumb-block"></span>'
                    + line() + line() + '<span class="choice-thumb-box"></span></span>';
        }
    }

    /**
     * @param {HTMLElement} el
     * @param {{items: Array<{id: string, title: string, meta?: string, icon?: string, status?: {label: string, tone?: string}}>, empty?: string, onOpen: function(string)}} opts
     */
    function list(el, opts) {
        if (!el) return;
        const items = opts.items || [];
        if (!items.length) {
            el.innerHTML = '<p class="dashed template-list-empty">' + esc(opts.empty || t('template_flow_empty', 'No templates yet.')) + '</p>';
            return;
        }
        el.innerHTML = '<ul class="list-rows template-list">' + items.map(function (item) {
            const status = item.status && item.status.label
                ? '<span class="status' + (item.status.tone ? ' is-' + esc(item.status.tone) : '') + '">' + esc(item.status.label) + '</span>'
                : '';
            return '<li><button type="button" class="list-row template-row" data-template-id="' + esc(item.id) + '">'
                + '<span class="row-icon">' + icon(item.icon || 'content') + '</span>'
                + '<span class="row-body"><span class="row-title">' + esc(item.title) + '</span>'
                + (item.meta ? '<span class="row-meta">' + esc(item.meta) + '</span>' : '') + '</span>'
                + status + '<span class="template-row-chev">' + icon('chev-right') + '</span></button></li>';
        }).join('') + '</ul>';
        el.querySelectorAll('[data-template-id]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                opts.onOpen(btn.getAttribute('data-template-id'));
            });
        });
    }

    let sheetSeq = 0;

    /**
     * New template sheet.
     * @param {object} opts
     *   title, text, submitLabel, cancelLabel
     *   kinds: [{id, label, icon}], kind           optional segmented choice
     *   name: {label, placeholder, help}
     *   fields(kind): [{id, label, help, options: [{value, label}], value}]   extra selects
     *   starts(kind): [{id, title, text, thumb, copyOptions: [{value, label}], copyLabel}]
     *   start(kind): id of the preselected card
     *   active(kind): {label, text, value} | null
     *   next(kind): {title, text} | null
     *   onSubmit(values): Promise|void; values = {kind, name, start, copyOf, active, fields}
     * @returns {{close: function}}
     */
    function sheet(opts) {
        const id = 'template-flow-sheet-' + (++sheetSeq);
        const opener = document.activeElement;
        const kinds = opts.kinds || [];
        let kind = opts.kind || (kinds[0] && kinds[0].id) || '';
        let start = '';

        const root = document.createElement('div');
        root.className = 'modal-backdrop is-sheet is-wide template-sheet active';
        root.innerHTML = '<div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="' + id + '-title" aria-describedby="' + id + '-text">'
            + '<div class="modal-header"><div>'
            + '<h2 class="dialog-title" id="' + id + '-title">' + esc(opts.title) + '</h2>'
            + (opts.text ? '<p class="dialog-text" id="' + id + '-text">' + esc(opts.text) + '</p>' : '')
            + '</div><button type="button" class="icon-button modal-close" data-flow-close aria-label="' + esc(t('close', 'Close')) + '">' + icon('x') + '</button></div>'
            + '<form class="modal-body template-sheet-body" novalidate></form>'
            + '<div class="modal-footer">'
            + '<button type="button" class="button is-quiet" data-flow-close>' + esc(opts.cancelLabel || t('cancel', 'Cancel')) + '</button>'
            + '<button type="submit" class="button is-main" form="' + id + '-form">' + icon('arrow-r') + esc(opts.submitLabel || t('template_flow_create', 'Create and open editor')) + '</button>'
            + '</div></div>';
        const form = root.querySelector('form');
        form.id = id + '-form';

        function current(fn) {
            return typeof fn === 'function' ? fn(kind) : fn;
        }

        function render() {
            const nameValue = form.querySelector('[name="template-name"]')?.value || '';
            const fieldValues = {};
            form.querySelectorAll('[data-flow-field]').forEach(function (sel) { fieldValues[sel.name] = sel.value; });
            const starts = current(opts.starts) || [];
            if (!starts.some(function (s) { return s.id === start; })) {
                start = current(opts.start) || (starts[0] && starts[0].id) || '';
            }
            const active = current(opts.active);
            const next = current(opts.next);
            const fields = current(opts.fields) || [];
            const nameOpt = opts.name || {};
            let html = '';

            if (kinds.length > 1) {
                html += '<div class="slab template-sheet-slab"><p class="field-label" id="' + id + '-kind">' + esc(opts.kindLabel || t('template_flow_kind', 'What are you creating?')) + '</p>'
                    + '<div class="segmented template-sheet-kinds" role="group" aria-labelledby="' + id + '-kind">'
                    + kinds.map(function (k) {
                        return '<button type="button" data-flow-kind="' + esc(k.id) + '" aria-pressed="' + (k.id === kind ? 'true' : 'false') + '">'
                            + (k.icon ? icon(k.icon) : '') + esc(k.label) + '</button>';
                    }).join('') + '</div></div>';
            }

            html += '<div class="slab template-sheet-slab field-group">'
                + '<label class="field-label" for="' + id + '-name">' + esc(nameOpt.label || t('template_flow_name', 'Name')) + '</label>'
                + '<input type="text" class="input" id="' + id + '-name" name="template-name" required autocomplete="off" placeholder="' + esc(nameOpt.placeholder || '') + '" value="' + esc(nameValue) + '">'
                + (nameOpt.help ? '<p class="field-help">' + esc(nameOpt.help) + '</p>' : '')
                + '</div>';

            fields.forEach(function (f) {
                const value = fieldValues[f.id] != null ? fieldValues[f.id] : (f.value || '');
                html += '<div class="slab template-sheet-slab field-group">'
                    + '<label class="field-label" for="' + id + '-' + esc(f.id) + '">' + esc(f.label) + '</label>'
                    + '<select class="input" data-flow-field id="' + id + '-' + esc(f.id) + '" name="' + esc(f.id) + '">'
                    + (f.options || []).map(function (o) {
                        return '<option value="' + esc(o.value) + '"' + (String(o.value) === String(value) ? ' selected' : '') + '>' + esc(o.label) + '</option>';
                    }).join('') + '</select>'
                    + (f.help ? '<p class="field-help">' + esc(f.help) + '</p>' : '')
                    + '</div>';
            });

            if (starts.length) {
                html += '<p class="template-sheet-heading" id="' + id + '-start">' + esc(opts.startLabel || t('template_flow_start_from', 'Start from')) + '</p>'
                    + '<div class="choice-grid" role="group" aria-labelledby="' + id + '-start">'
                    + starts.map(function (s) {
                        return '<button type="button" class="choice-card" data-flow-start="' + esc(s.id) + '" aria-pressed="' + (s.id === start ? 'true' : 'false') + '">'
                            + thumb(s.thumb) + '<span class="choice-title">' + esc(s.title) + '</span>'
                            + (s.text ? '<span class="choice-text">' + esc(s.text) + '</span>' : '') + '</button>';
                    }).join('') + '</div>';
                const chosen = starts.find(function (s) { return s.id === start; });
                if (chosen && chosen.copyOptions && chosen.copyOptions.length) {
                    html += '<div class="slab template-sheet-slab field-group">'
                        + '<label class="field-label" for="' + id + '-copy">' + esc(chosen.copyLabel || t('template_flow_copy_which', 'Template to copy')) + '</label>'
                        + '<select class="input" id="' + id + '-copy" name="template-copy">'
                        + chosen.copyOptions.map(function (o) { return '<option value="' + esc(o.value) + '">' + esc(o.label) + '</option>'; }).join('')
                        + '</select></div>';
                }
            }

            if (active) {
                html += '<div class="slab template-sheet-slab template-sheet-switch">'
                    + '<div class="row-body"><span class="row-title" id="' + id + '-active-label">' + esc(active.label) + '</span>'
                    + (active.text ? '<span class="row-meta">' + esc(active.text) + '</span>' : '') + '</div>'
                    + switchMarkup(id + '-active', !!active.value, id + '-active-label') + '</div>';
            }

            if (next) {
                html += '<div class="summary-note template-sheet-next"><strong>' + esc(next.title) + '</strong>'
                    + (next.text ? '<span>' + esc(next.text) + '</span>' : '') + '</div>';
            }

            form.innerHTML = html;
            const sw = form.querySelector('.switch');
            if (sw) bindSwitch(sw);
        }

        function close() {
            document.removeEventListener('keydown', onKey, true);
            root.remove();
            if (opener && typeof opener.focus === 'function') opener.focus();
        }

        function onKey(e) {
            if (e.key === 'Escape') {
                e.stopPropagation();
                close();
            }
        }

        root.addEventListener('click', function (e) {
            if (e.target === root || e.target.closest('[data-flow-close]')) {
                close();
                return;
            }
            const kindBtn = e.target.closest('[data-flow-kind]');
            if (kindBtn && kindBtn.getAttribute('data-flow-kind') !== kind) {
                kind = kindBtn.getAttribute('data-flow-kind');
                start = '';
                render();
                return;
            }
            const startBtn = e.target.closest('[data-flow-start]');
            if (startBtn) {
                start = startBtn.getAttribute('data-flow-start');
                render();
                form.querySelector('[data-flow-start="' + CSS.escape(start) + '"]')?.focus();
            }
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const nameInput = form.querySelector('[name="template-name"]');
            const name = (nameInput.value || '').trim();
            if (!name) {
                nameInput.setAttribute('aria-invalid', 'true');
                nameInput.focus();
                return;
            }
            const fields = {};
            form.querySelectorAll('[data-flow-field]').forEach(function (sel) { fields[sel.name] = sel.value; });
            const submit = root.querySelector('button[type="submit"]');
            submit.classList.add('is-busy');
            submit.disabled = true;
            Promise.resolve(opts.onSubmit({
                kind: kind,
                name: name,
                start: start,
                copyOf: form.querySelector('[name="template-copy"]')?.value || '',
                active: form.querySelector('.switch')?.getAttribute('aria-checked') === 'true',
                fields: fields,
            })).then(function (result) {
                if (result === false) {
                    submit.classList.remove('is-busy');
                    submit.disabled = false;
                    return;
                }
                close();
            }, function () {
                submit.classList.remove('is-busy');
                submit.disabled = false;
            });
        });

        render();
        document.body.appendChild(root);
        document.addEventListener('keydown', onKey, true);
        form.querySelector('[name="template-name"]')?.focus();
        return { close: close };
    }

    /**
     * The panel shows either its normal content or the editor view.
     * @param {HTMLElement} panel  element whose other children hide while editing
     * @param {HTMLElement} view   the .template-editor-view inside panel
     */
    function editor(panel, view) {
        return {
            open: function () {
                if (!panel || !view) return;
                panel.setAttribute('data-template-editing', '');
                view.hidden = false;
                (panel.closest('.main-content') || document.scrollingElement || document.documentElement).scrollTop = 0;
                window.scrollTo(0, 0);
            },
            close: function () {
                if (!panel || !view) return;
                panel.removeAttribute('data-template-editing');
                view.hidden = true;
            },
            isOpen: function () {
                return !!panel && panel.hasAttribute('data-template-editing');
            },
        };
    }

    /**
     * Token chips; a click inserts the token where the cursor was in the body
     * editor (visual or HTML), otherwise copies it.
     * @param {HTMLElement} el
     * @param {Array<string|{token: string, label?: string}>} tokens
     * @param {HTMLElement} host  body editor host
     */
    function tokens(el, list, host) {
        if (!el) return;
        const items = (list || []).map(function (tok) {
            return typeof tok === 'string' ? { token: tok } : tok;
        }).filter(function (tok) { return tok && tok.token; });
        if (!items.length) {
            el.hidden = true;
            return;
        }
        el.hidden = false;
        el.innerHTML = '<span class="token-list-label">' + esc(t('template_flow_insert', 'Insert')) + '</span>'
            + items.map(function (tok) {
                const text = '{{' + tok.token + '}}';
                return '<button type="button" class="chip token-chip" data-token="' + esc(text) + '"' + (tok.label ? ' title="' + esc(tok.label) + '"' : '') + '>' + esc(text) + '</button>';
            }).join('');

        let lastTarget = null;
        let lastRange = null;
        if (host && host.dataset.tokenTrack !== '1') {
            host.dataset.tokenTrack = '1';
            const remember = function () {
                const active = document.activeElement;
                if (!active || !host.contains(active)) return;
                lastTarget = active;
                const sel = window.getSelection();
                lastRange = active.isContentEditable && sel && sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
            };
            host.addEventListener('keyup', remember);
            host.addEventListener('mouseup', remember);
            host.addEventListener('focusin', remember);
            host._templateFlowTarget = function () { return { el: lastTarget, range: lastRange }; };
        }

        el.addEventListener('mousedown', function (e) {
            if (e.target.closest('[data-token]')) e.preventDefault();
        });
        el.onclick = function (e) {
            const chip = e.target.closest('[data-token]');
            if (!chip) return;
            const text = chip.getAttribute('data-token');
            const target = host && host._templateFlowTarget ? host._templateFlowTarget() : { el: null };
            const node = target.el;
            if (node && host.contains(node) && (node.tagName === 'TEXTAREA' || node.tagName === 'INPUT')) {
                node.focus();
                node.setRangeText(text, node.selectionStart, node.selectionEnd, 'end');
                node.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }
            if (node && host.contains(node) && node.isContentEditable) {
                node.focus();
                if (target.range) {
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(target.range);
                }
                document.execCommand('insertText', false, text);
                node.dispatchEvent(new Event('input', { bubbles: true }));
                return;
            }
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(text).then(function () {
                    toast(t('template_flow_copied', 'Copied {token}', { token: text }));
                }, function () {});
            }
        };
    }

    const TEST_TO_KEY = 'birkly-template-test-to';

    /**
     * Ask where a test send goes; remembers the last address for this tab.
     * @returns {Promise<string|null>}
     */
    function askTestRecipient() {
        let last = '';
        try { last = sessionStorage.getItem(TEST_TO_KEY) || ''; } catch (e) { /* storage off */ }
        const message = t('template_flow_test_message', 'We send the template with sample data to this address.');
        const ask = window.BirklyFeedback && typeof window.BirklyFeedback.prompt === 'function'
            ? window.BirklyFeedback.prompt(message, last, {
                title: t('template_flow_test_title', 'Send a test'),
                confirmLabel: t('template_flow_test_send', 'Send test'),
                placeholder: 'you@example.com',
            })
            : Promise.resolve(window.prompt(message, last));
        return ask.then(function (value) {
            const to = String(value || '').trim();
            if (!to) return null;
            try { sessionStorage.setItem(TEST_TO_KEY, to); } catch (e) { /* storage off */ }
            return to;
        });
    }

    window.Birkly.TemplateFlow = {
        askTestRecipient: askTestRecipient,
        list: list,
        sheet: sheet,
        editor: editor,
        tokens: tokens,
        thumb: thumb,
        switchMarkup: switchMarkup,
        bindSwitch: bindSwitch,
    };
})();
