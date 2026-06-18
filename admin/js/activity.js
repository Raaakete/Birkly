/**
 * Activity feed page — template-driven reference (M3-T4).
 * Markup lives in admin/templates/activity.html; behavior here only.
 */
(function () {
    'use strict';

    let currentOffset = 0;
    const pageSize = 20;
    let allActivity = [];
    let filteredActivity = [];

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderFeed() {
        const feed = document.getElementById('activity-feed');
        if (!feed) return;

        if (filteredActivity.length === 0) {
            feed.innerHTML = '<div class="text-center"><p>No activity found</p></div>';
            const loadMoreBtn = document.getElementById('load-more');
            if (loadMoreBtn) loadMoreBtn.style.display = 'none';
            return;
        }

        const items = filteredActivity.slice(0, currentOffset + pageSize);
        let html = '';
        items.forEach(function (item) {
            const timestamp = item.timestamp ? new Date(item.timestamp).toLocaleString() : '';
            const message = item.message || item.event || 'Activity';
            const user = item.user || 'Unknown';
            const category = item.category || 'other';
            html += '<div class="activity-item" style="padding:1rem;border-bottom:1px solid var(--border-light);">';
            html += '<div style="display:flex;justify-content:space-between;gap:1rem;">';
            html += '<strong>' + escapeHtml(message) + '</strong>';
            html += '<span style="color:var(--text-secondary);font-size:0.875rem;">' + escapeHtml(timestamp) + '</span>';
            html += '</div>';
            html += '<div style="margin-top:0.25rem;font-size:0.875rem;color:var(--text-secondary);">';
            html += escapeHtml(user) + ' · ' + escapeHtml(category);
            html += '</div></div>';
        });
        feed.innerHTML = html;

        const loadMoreBtn = document.getElementById('load-more');
        if (loadMoreBtn) {
            loadMoreBtn.style.display = items.length < filteredActivity.length ? 'block' : 'none';
        }
    }

    function applyFilter() {
        const filter = document.getElementById('category-filter')?.value || 'all';
        if (filter === 'all') {
            filteredActivity = allActivity.slice();
        } else {
            filteredActivity = allActivity.filter(function (item) {
                return (item.category || 'other') === filter;
            });
        }
        currentOffset = 0;
        renderFeed();
    }

    function loadActivity() {
        const feed = document.getElementById('activity-feed');
        if (feed) {
            feed.innerHTML = '<div class="text-center"><div class="spinner">Loading...</div></div>';
        }

        const filter = document.getElementById('category-filter')?.value || 'all';
        const url = '/api/activity.php?action=list&limit=200&filter=' + encodeURIComponent(filter);

        fetch(url, { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.status === 'ok' && Array.isArray(data.activity)) {
                    allActivity = data.activity;
                } else {
                    allActivity = [];
                }
                applyFilter();
            })
            .catch(function (error) {
                console.error('Activity load error:', error);
                if (feed) {
                    feed.innerHTML = '<div class="text-center">Error loading activity</div>';
                }
            });
    }

    function bindEvents() {
        const refreshBtn = document.getElementById('refresh-activity');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', loadActivity);
        }

        const categoryFilter = document.getElementById('category-filter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', loadActivity);
        }

        const loadMoreBtn = document.getElementById('load-more');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function () {
                currentOffset += pageSize;
                renderFeed();
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.getElementById('activity-feed')) return;
        bindEvents();
        loadActivity();
    });
})();
