/**
 * Shared activity row renderer (P69) — dashboard, Activity page, Settings project pane.
 */
(function (global) {
    'use strict';

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function formatTimestamp(timestamp) {
        if (!timestamp) return '';
        const date = new Date(timestamp);
        if (Number.isNaN(date.getTime())) return String(timestamp);
        return date.toLocaleString();
    }

    function formatTimeAgo(timestamp) {
        if (!timestamp) return '';
        const date = new Date(timestamp);
        const diff = Math.floor((Date.now() - date.getTime()) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return date.toLocaleDateString();
    }

    function actorLabel(item) {
        if (item.actor && item.actor.label) {
            return String(item.actor.label);
        }
        return String(item.user || 'Unknown');
    }

    function targetLabel(item) {
        if (item.target && item.target.label) {
            return String(item.target.label);
        }
        return '';
    }

    function severityBadge(severity) {
        const level = String(severity || 'info');
        const label = level.charAt(0).toUpperCase() + level.slice(1);
        return '<span class="activity-severity activity-severity--' + escapeHtml(level) + '">' + escapeHtml(label) + '</span>';
    }

    function detailJson(item) {
        const detail = item.detail || item.details;
        if (!detail || typeof detail !== 'object' || Object.keys(detail).length === 0) {
            return '';
        }
        try {
            return JSON.stringify(detail, null, 2);
        } catch (e) {
            return String(detail);
        }
    }

    function resolveHref(item) {
        const href = item.href;
        if (!href) return '';
        if (typeof global.Birkly !== 'undefined' && typeof global.Birkly.url === 'function') {
            try {
                if (href.startsWith('http://') || href.startsWith('https://')) {
                    return href;
                }
                const path = href.replace(/^https?:\/\/[^/]+/, '');
                return global.Birkly.url(path.startsWith('/') ? path : '/' + path);
            } catch (err) {
                return href;
            }
        }
        return href;
    }

    /**
     * @param {object} item
     * @param {object} [options]
     */
    function renderRow(item, options) {
        const opts = options || {};
        const id = escapeHtml(item.id || item.event || Math.random().toString(36).slice(2));
        const message = escapeHtml(item.message || item.summary || item.event || 'Activity');
        const when = opts.timeStyle === 'ago' ? formatTimeAgo(item.timestamp) : formatTimestamp(item.timestamp);
        const actor = escapeHtml(actorLabel(item));
        const category = escapeHtml(item.category || 'other');
        const target = targetLabel(item);
        const href = resolveHref(item);
        const detailText = detailJson(item);
        const detailSummary = item.detail_summary ? String(item.detail_summary) : '';
        const expandable = opts.expandable !== false && detailText !== '' && !detailSummary;
        const rowClass = 'activity-row' + (opts.compact ? ' activity-row--compact' : '');
        const linkBtn = href
            ? '<a class="btn btn-ghost btn-sm activity-row__link" href="' + escapeHtml(href) + '">Open</a>'
            : '';
        const expandBtn = expandable
            ? '<button type="button" class="btn btn-ghost btn-sm activity-row__toggle" data-activity-toggle="' + id + '" aria-expanded="false">Details</button>'
            : '';
        const targetHtml = target
            ? '<span class="activity-row__target">' + escapeHtml(target) + '</span>'
            : '';

        return '<article class="' + rowClass + '" data-activity-id="' + id + '">' +
            '<div class="activity-row__icon" aria-hidden="true">' + escapeHtml(item.icon || '📌') + '</div>' +
            '<div class="activity-row__body">' +
            '<div class="activity-row__head">' +
            '<span class="activity-row__message">' + message + '</span>' +
            severityBadge(item.severity) +
            '</div>' +
            '<div class="activity-row__meta">' +
            '<span>' + actor + '</span>' +
            (targetHtml ? '<span>·</span>' + targetHtml : '') +
            '<span>·</span><span>' + escapeHtml(category) + '</span>' +
            '<span>·</span><span class="activity-row__time">' + escapeHtml(when) + '</span>' +
            '</div>' +
            (detailSummary
                ? '<p class="activity-row__summary">' + escapeHtml(detailSummary) + '</p>'
                : '') +
            (expandable
                ? '<pre class="activity-row__detail" id="activity-detail-' + id + '" hidden>' + escapeHtml(detailText) + '</pre>'
                : '') +
            '</div>' +
            '<div class="activity-row__actions">' + linkBtn + expandBtn + '</div>' +
            '</article>';
    }

    function renderList(items, options) {
        if (!items || !items.length) {
            return '';
        }
        return items.map(function (item) {
            return renderRow(item, options);
        }).join('');
    }

    function bindToggle(root) {
        const container = root || document;
        container.querySelectorAll('[data-activity-toggle]').forEach(function (btn) {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                const id = btn.getAttribute('data-activity-toggle');
                const panel = document.getElementById('activity-detail-' + id);
                if (!panel) return;
                const open = panel.hasAttribute('hidden');
                if (open) {
                    panel.removeAttribute('hidden');
                    btn.setAttribute('aria-expanded', 'true');
                    btn.textContent = 'Hide';
                } else {
                    panel.setAttribute('hidden', '');
                    btn.setAttribute('aria-expanded', 'false');
                    btn.textContent = 'Details';
                }
            });
        });
    }

    global.BirklyActivityRows = {
        escapeHtml: escapeHtml,
        formatTimestamp: formatTimestamp,
        formatTimeAgo: formatTimeAgo,
        renderRow: renderRow,
        renderList: renderList,
        bindToggle: bindToggle,
        resolveHref: resolveHref,
        actorLabel: actorLabel,
        targetLabel: targetLabel,
    };
})(typeof window !== 'undefined' ? window : this);
