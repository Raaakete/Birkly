(function () {
    'use strict';

    function escapeHtml(value) {
        const element = document.createElement('div');
        element.textContent = String(value ?? '');
        return element.innerHTML;
    }

    function birklyIcon(name, opts) {
        return (window.Birkly && typeof Birkly.icon === 'function') ? Birkly.icon(name, opts) : '';
    }

    const STAT_TONE_CLASS = {
        good: ' u-fg-success',
        published: ' u-fg-success',
        commerce: ' u-fg-success',
        warning: ' u-fg-warning',
        drafts: ' u-fg-warning',
        error: ' u-fg-danger',
    };

    window.Birkly = window.Birkly || {};
    window.Birkly.DashboardMount = {
        escapeHtml: escapeHtml,
        setContent: function (element, html) {
            if (element) {
                element.innerHTML = html;
            }
        },
        empty: function (message) {
            return '<div class="empty-state empty-state--compact"><p class="empty-state-text">' +
                escapeHtml(message) + '</p></div>';
        },
        statTile: function (value, label, tone, iconName) {
            const toneClass = STAT_TONE_CLASS[tone] || '';
            const icon = iconName ? birklyIcon(iconName, { size: 14, class: 'u-fg-tertiary' }) : '';
            return '<div class="stat"><span class="stat-label u-row-1">' + icon + escapeHtml(label) +
                '</span><span class="stat-value u-numeric' + toneClass + '">' + escapeHtml(value ?? 0) + '</span></div>';
        },
        statGrid: function (tiles) {
            return '<div class="dash-metrics">' + tiles.join('') + '</div>';
        },
        feedList: function (items) {
            return '<div class="data-list">' + items.join('') + '</div>';
        },
        feedItem: function (options) {
            const title = '<span class="data-list-title">' + escapeHtml(options.title) + '</span>';
            const meta = options.meta ? '<span class="data-list-meta">' + options.meta + '</span>' : '';
            const aside = options.aside ? '<div class="data-list-actions">' + options.aside + '</div>' : '';
            const chevron = options.href
                ? '<span class="dash-row-arrow" aria-hidden="true">' + birklyIcon('caret-right', { size: 14 }) + '</span>'
                : '';
            const tag = options.href ? 'a' : 'div';
            const hrefAttr = options.href ? ' href="' + escapeHtml(options.href) + '"' : '';
            return '<' + tag + ' class="data-list-item"' + hrefAttr + '><div class="data-list-main">' + title + meta + '</div>' + aside + chevron + '</' + tag + '>';
        },
        footerLinks: function (links) {
            return links ? '<div class="u-cluster-3 u-text-sm u-mt-3">' + links + '</div>' : '';
        },
        formatCents: function (cents) {
            return (Number(cents || 0) / 100).toLocaleString(undefined, {
                style: 'currency',
                currency: 'EUR',
                minimumFractionDigits: 0,
            });
        },
        formatTimeAgo: function (timestamp) {
            if (!timestamp) {
                return '';
            }
            const date = new Date(timestamp);
            const diff = Math.floor((Date.now() - date.getTime()) / 1000);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
            return date.toLocaleDateString();
        },
    };
})();
