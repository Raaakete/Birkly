/**
 * Birkly Admin Utils
 * Basic utility functions for the admin interface
 */

window.Birkly = window.Birkly || {};

/**
 * Sliding thumb for secondary nav tracks (Agent Guide segmented pattern).
 * Tracks: .tabs / .segmented / .language-tabs-nav / .side-nav--track /
 *         .side-nav-sub / .media-tree
 */
Birkly.Nav = {
    TRACK_SEL: '.tabs, .segmented, .language-tabs-nav, .side-nav--track, .media-tree',
    ITEM_SEL: '.tab, .segmented-item, .language-tab-btn, .side-nav-item, .side-nav-sub-item, .folder-item, .folder-item-move',

    init() {
        if (this._inited) return;
        this._inited = true;
        this._syncing = false;
        this.refresh(document);

        document.addEventListener('click', (event) => {
            const item = event.target && event.target.closest
                ? event.target.closest(this.ITEM_SEL)
                : null;
            if (!item) return;
            const track = item.closest(this.TRACK_SEL);
            if (!track) return;
            requestAnimationFrame(() => this.syncTrack(track));
            setTimeout(() => this.syncTrack(track), 50);
        }, true);

        window.addEventListener('resize', () => this.refresh(document));

        // Watch for active-state changes from page JS — ignore our own
        // .nav-thumb mutations so we never re-enter syncTrack in a loop.
        this._observer = new MutationObserver((mutations) => {
            if (this._syncing) return;
            const tracks = new Set();
            for (const m of mutations) {
                const el = m.target.nodeType === 1 ? m.target : m.target.parentElement;
                if (!el || (el.classList && el.classList.contains('nav-thumb'))) continue;
                if (m.type === 'attributes' && m.attributeName === 'class' &&
                    el.classList && el.classList.contains('nav-thumb')) continue;
                const track = el.closest ? el.closest(this.TRACK_SEL) : null;
                if (track) tracks.add(track);
                if (el.matches && el.matches(this.TRACK_SEL)) tracks.add(el);
            }
            tracks.forEach((t) => this.syncTrack(t));
        });
        this._observer.observe(document.documentElement, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['class', 'aria-selected', 'aria-pressed']
        });
    },

    refresh(root) {
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll(this.TRACK_SEL).forEach((track) => this.syncTrack(track, true));
    },

    _itemsInTrack(track) {
        return Array.from(track.querySelectorAll(this.ITEM_SEL)).filter((el) => {
            const owner = el.closest(this.TRACK_SEL);
            return owner === track;
        });
    },

    _activeItem(track) {
        const items = this._itemsInTrack(track);
        return items.find((el) => (
            el.classList.contains('active') ||
            el.classList.contains('selected') ||
            el.getAttribute('aria-selected') === 'true' ||
            el.getAttribute('aria-pressed') === 'true'
        )) || null;
    },

    syncTrack(track, instant) {
        if (!track || !track.isConnected || this._syncing) return;
        this._syncing = true;
        try {
            let thumb = track.querySelector(':scope > .nav-thumb');
            if (!thumb) {
                thumb = document.createElement('span');
                thumb.className = 'nav-thumb';
                thumb.setAttribute('aria-hidden', 'true');
                track.insertBefore(thumb, track.firstChild);
                instant = true;
            }

            const active = this._activeItem(track);
            if (!active || active.hidden) {
                thumb.classList.remove('is-ready');
                return;
            }

            const trackRect = track.getBoundingClientRect();
            const itemRect = active.getBoundingClientRect();
            if (itemRect.width < 1 || itemRect.height < 1) {
                thumb.classList.remove('is-ready');
                return;
            }

            const left = itemRect.left - trackRect.left + track.scrollLeft;
            const top = itemRect.top - trackRect.top + track.scrollTop;

            if (instant) thumb.classList.add('is-instant');
            thumb.style.width = `${Math.round(itemRect.width)}px`;
            thumb.style.height = `${Math.round(itemRect.height)}px`;
            thumb.style.transform = `translate(${Math.round(left)}px, ${Math.round(top)}px)`;
            thumb.classList.add('is-ready');
            if (instant) {
                void thumb.offsetWidth;
                thumb.classList.remove('is-instant');
            }
        } finally {
            this._syncing = false;
        }
    }
};

Birkly.Utils = {
    // Format date for display
    formatDate(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    },
    
    // Format datetime for display
    formatDateTime(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        return date.toLocaleString();
    },
    
    // Show notification
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    },
    
    // Confirm action (async modal)
    confirmAction(message) {
        return BirklyFeedback.confirm(message);
    },
    
    // Escape HTML
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    // Ensure a shared modal exists (created lazily)
    ensureModal(id, title, bodyHtml) {
        let modal = document.getElementById(id);
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = id;
        modal.className = 'modal-backdrop';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 520px;">
                <div class="modal-header">
                    <h2>${this.escapeHtml(title)}</h2>
                    <button class="modal-close-btn" type="button" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    ${bodyHtml}
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" type="button" data-modal-action="close">OK</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        const close = () => {
            modal.classList.remove('active');
            modal.style.display = 'none';
        };

        // Backdrop click closes
        modal.addEventListener('click', (e) => {
            if (e.target === modal) close();
        });
        // Close button closes
        modal.querySelector('.modal-close-btn')?.addEventListener('click', close);
        modal.querySelector('[data-modal-action="close"]')?.addEventListener('click', close);
        // Escape closes
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('active')) close();
        });

        return modal;
    },

    showPermissionDenied(details = {}) {
        const id = 'permission-denied-modal';

        const actionText = details.action ? `<div style="margin-top: 0.75rem; font-size: 0.9rem; opacity: 0.85;">
            <div><strong>Action:</strong> ${this.escapeHtml(details.action)}</div>
            ${details.url ? `<div><strong>Endpoint:</strong> ${this.escapeHtml(details.url)}</div>` : ''}
        </div>` : '';

        const modal = this.ensureModal(
            id,
            'Permission denied',
            `<p>You don’t have permission to perform this action with your current role.</p>
             <p style="margin-top: 0.75rem;">If you believe this is incorrect, contact an admin to update your role/permissions.</p>
             ${actionText}`
        );

        modal.style.display = 'flex';
        // Ensure modal CSS shows it
        requestAnimationFrame(() => modal.classList.add('active'));
    }
};

// Suppress legacy alert() popups for permission errors and show the unified modal instead.
(function wrapAlertForPermissions() {
    if (window.__birklyAlertWrapped) return;
    window.__birklyAlertWrapped = true;

    const originalAlert = window.alert?.bind(window);
    if (!originalAlert) return;

    window.alert = function(message) {
        const msg = String(message ?? '');
        const looksLikePermissionError =
            /forbidden|permission denied|insufficient permissions|access denied|unauthorized/i.test(msg);

        // If a 403 just happened, or message clearly indicates permission issues, show modal and suppress alert.
        const recentlyForbidden = (Date.now() - (window.__birklyLastForbiddenAt || 0)) < 2500;
        if (looksLikePermissionError || recentlyForbidden) {
            try {
                Birkly?.Utils?.showPermissionDenied?.({ action: 'Not allowed' });
            } catch (_) {}
            return;
        }

        return originalAlert(message);
    };
})();

// Global fetch interceptor: show a unified modal on 403 responses.
// This improves UX for roles like "viewer" when the backend correctly blocks write operations.
(function wrapFetchForPermissions() {
    if (window.__birklyFetchWrapped) return;
    window.__birklyFetchWrapped = true;

    const originalFetch = window.fetch?.bind(window);
    if (!originalFetch) return;

    let lastShownAt = 0;
    const THROTTLE_MS = 1500;

    window.fetch = async function(input, init) {
        const method = (init?.method || 'GET').toUpperCase();
        const url = (typeof input === 'string') ? input : (input?.url || '');

        const res = await originalFetch(input, init);

        if (res && res.status === 403) {
            const now = Date.now();
            if (now - lastShownAt > THROTTLE_MS) {
                lastShownAt = now;
                window.__birklyLastForbiddenAt = now;
                // Try to extract a nicer action name from common query params
                let action = '';
                try {
                    const u = new URL(url, window.location.origin);
                    action = u.searchParams.get('action') || '';
                } catch (_) {}

                Birkly?.Utils?.showPermissionDenied?.({
                    action: action || `${method} forbidden`,
                    url
                });
            }
        }

        return res;
    };
})();