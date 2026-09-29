/**
 * P87 — Shared empty-state renderer for admin JS surfaces.
 */
(function (global) {
    'use strict';

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function t(key, fallback, params) {
        if (global.Birkly && global.Birkly.i18n && typeof global.Birkly.i18n.t === 'function') {
            return global.Birkly.i18n.t('admin.' + key, fallback, params);
        }
        return fallback || key;
    }

    /**
     * @param {object} options
     * @param {string} options.title
     * @param {string} [options.hint]
     * @param {{ label: string, href?: string, onClick?: function }} [options.primary]
     * @param {{ label: string, href?: string, guideAction?: string }} [options.secondary]
     * @param {string} [options.helpTopic]
     * @returns {HTMLElement}
     */
    function renderEmptyState(options) {
        const root = document.createElement('div');
        root.className = 'empty-state';

        const title = document.createElement('p');
        title.className = 'empty-state-title';
        title.textContent = options.title || '';
        root.appendChild(title);

        if (options.hint) {
            const hint = document.createElement('p');
            hint.className = 'empty-state-text';
            hint.textContent = options.hint;
            root.appendChild(hint);
        }

        const actions = document.createElement('div');
        actions.className = 'empty-state-actions';

        if (options.primary) {
            const primary = options.primary.href
                ? document.createElement('a')
                : document.createElement('button');
            primary.className = 'button is-main';
            if (options.primary.href) {
                primary.href = options.primary.href;
            } else {
                primary.type = 'button';
                if (typeof options.primary.onClick === 'function') {
                    primary.addEventListener('click', options.primary.onClick);
                }
            }
            primary.textContent = options.primary.label;
            actions.appendChild(primary);
        }

        if (options.secondary) {
            const secondary = document.createElement('button');
            secondary.type = 'button';
            secondary.className = 'button is-quiet is-small';
            secondary.textContent = options.secondary.label;
            if (options.secondary.guideAction && global.BirklyGuide && typeof global.BirklyGuide.open === 'function') {
                secondary.addEventListener('click', function () {
                    global.BirklyGuide.open({ journeyId: options.secondary.guideAction, tab: 'journeys' });
                });
            } else if (options.secondary.href) {
                secondary.className = 'button is-small';
                const link = document.createElement('a');
                link.href = options.secondary.href;
                link.className = secondary.className;
                link.textContent = options.secondary.label;
                actions.appendChild(link);
            } else {
                actions.appendChild(secondary);
            }
        }

        if (options.helpTopic) {
            const help = document.createElement('a');
            help.className = 'button is-quiet is-small contextual-help-link';
            help.href = (global.Birkly && typeof global.Birkly.url === 'function')
                ? global.Birkly.url('/admin/index.php?page=help&topic=' + encodeURIComponent(options.helpTopic))
                : '#';
            help.textContent = t('guide_learn_more', 'Learn more');
            help.setAttribute('data-help-topic', options.helpTopic);
            actions.appendChild(help);
        }

        if (actions.childNodes.length) {
            root.appendChild(actions);
        }

        return root;
    }

    global.Birkly = global.Birkly || {};
    global.Birkly.renderEmptyState = renderEmptyState;
}(typeof window !== 'undefined' ? window : global));
