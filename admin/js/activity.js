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

    function rowsApi() {
        return window.BirklyActivityRows;
    }

    function apiUrl(path) {
        if (typeof window.Birkly !== 'undefined' && typeof window.Birkly.url === 'function') {
            return window.Birkly.url(path.startsWith('/') ? path : '/' + path);
        }
        return path;
    }

    function renderFeed() {
        const feed = document.getElementById('activity-feed');
        if (!feed) return;

        const loadMoreBtn = document.getElementById('load-more');
        const api = rowsApi();

        if (filteredActivity.length === 0) {
            feed.innerHTML = '<div class="empty-state empty-state--compact">'
                + '<p class="empty-state__description">No activity found</p></div>';
            if (loadMoreBtn) loadMoreBtn.hidden = true;
            return;
        }

        const items = filteredActivity.slice(0, currentOffset + pageSize);
        if (api) {
            feed.innerHTML = api.renderList(items, { expandable: true });
            api.bindToggle(feed);
        } else {
            feed.innerHTML = items.map(function (item) {
                return '<div class="activity-item"><span>' + (item.message || '') + '</span></div>';
            }).join('');
        }

        if (loadMoreBtn) {
            loadMoreBtn.hidden = items.length >= filteredActivity.length;
        }
    }

    function applyFilter() {
        const category = document.getElementById('category-filter')?.value || 'all';
        const severity = document.getElementById('severity-filter')?.value || 'all';

        filteredActivity = allActivity.filter(function (item) {
            if (category !== 'all' && (item.category || 'other') !== category) {
                return false;
            }
            if (severity !== 'all' && (item.severity || 'info') !== severity) {
                return false;
            }
            return true;
        });

        currentOffset = 0;
        renderFeed();
    }

    function loadActivity() {
        const feed = document.getElementById('activity-feed');
        if (feed) {
            feed.innerHTML = '<div class="loading-state"><p>Loading...</p></div>';
        }

        const category = document.getElementById('category-filter')?.value || 'all';
        const severity = document.getElementById('severity-filter')?.value || 'all';
        const url = apiUrl('/api/activity.php?action=list&limit=200&filter=' + encodeURIComponent(category)
            + '&severity=' + encodeURIComponent(severity));

        fetch(url, { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
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
                    feed.innerHTML = '<div class="empty-state empty-state--compact empty-state--error" role="alert">'
                        + '<p class="empty-state__description">Error loading activity</p></div>';
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

        const severityFilter = document.getElementById('severity-filter');
        if (severityFilter) {
            severityFilter.addEventListener('change', applyFilter);
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
