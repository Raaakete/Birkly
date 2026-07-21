(function () {
    'use strict';

    let searchTimeout = null;
    let currentResults = [];
    let selectedIndex = -1;

    function apiUrl(path) {
        return window.Birkly && typeof window.Birkly.url === 'function' ? window.Birkly.url(path) : path;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = String(text || '');
        return div.innerHTML;
    }

    function overlay() {
        return document.getElementById('search-overlay');
    }

    function input() {
        return document.getElementById('search-input');
    }

    function resultsContainer() {
        return document.getElementById('search-results');
    }

    function renderResults(results) {
        const container = resultsContainer();
        if (!container) return;

        if (!results.length) {
            const query = input()?.value.trim() || '';
            container.innerHTML = query.length < 2
                ? '<div class="search-empty-state"><p>Type to search...</p></div>'
                : '<div class="search-empty-state"><p>No results found</p></div>';
            return;
        }

        const grouped = {};
        results.forEach(function (result) {
            const type = result.type || 'other';
            if (!grouped[type]) grouped[type] = [];
            grouped[type].push(result);
        });

        const labels = {
            collection: 'Collections',
            entry: 'Entries',
        };

        let html = '';
        Object.keys(grouped).forEach(function (type) {
            const group = grouped[type];
            html += '<div class="search-results-group">';
            html += '<div class="search-results-group-header">' + escapeHtml(labels[type] || type) + ' (' + group.length + ')</div>';
            html += '<div class="search-results-list">';
            group.forEach(function (result) {
                const globalIndex = results.indexOf(result);
                html += '<button type="button" class="search-result-item' + (selectedIndex === globalIndex ? ' selected' : '') + '" data-index="' + globalIndex + '" data-url="' + escapeHtml(result.url || '#') + '">';
                html += '<span class="search-result-icon">' + escapeHtml(result.icon || '📌') + '</span>';
                html += '<div class="search-result-content">';
                html += '<div class="search-result-title">' + escapeHtml(result.title || 'Untitled') + '</div>';
                html += '<div class="search-result-subtitle">' + escapeHtml(result.subtitle || '') + '</div>';
                html += '</div></button>';
            });
            html += '</div></div>';
        });

        container.innerHTML = html;
    }

    function closeSearch() {
        const modal = overlay();
        if (modal) {
            modal.style.display = 'none';
        }
        const field = input();
        if (field) {
            field.value = '';
        }
        currentResults = [];
        selectedIndex = -1;
        renderResults([]);
    }

    function openSearch() {
        const modal = overlay();
        const field = input();
        if (!modal || !field) return;

        modal.style.display = 'flex';
        window.setTimeout(function () {
            field.focus();
        }, 50);
    }

    function selectResult(index) {
        const result = currentResults[index];
        if (result && result.url) {
            window.location.href = result.url;
        }
    }

    function navigateResults(direction) {
        if (!currentResults.length) return;

        if (direction === 'up') {
            selectedIndex = selectedIndex > 0 ? selectedIndex - 1 : currentResults.length - 1;
        } else {
            selectedIndex = selectedIndex < currentResults.length - 1 ? selectedIndex + 1 : 0;
        }

        renderResults(currentResults);
        const active = document.querySelector('.search-result-item[data-index="' + selectedIndex + '"]');
        if (active) {
            active.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function performSearch(query) {
        if (!query || query.trim().length < 2) {
            currentResults = [];
            selectedIndex = -1;
            renderResults([]);
            return;
        }

        const container = resultsContainer();
        if (container) {
            container.innerHTML = '<div class="search-loading"><p>Searching...</p></div>';
        }

        fetch(apiUrl('/api/search.php?action=admin_search&q=' + encodeURIComponent(query) + '&limit=20'), {
            credentials: 'same-origin',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function (payload) {
                if (payload.status !== 'ok') {
                    throw new Error(payload.message || 'Search failed');
                }
                currentResults = Array.isArray(payload.results) ? payload.results : [];
                selectedIndex = -1;
                renderResults(currentResults);
            })
            .catch(function (error) {
                currentResults = [];
                selectedIndex = -1;
                const container = resultsContainer();
                if (container) {
                    container.innerHTML = '<div class="search-empty-state"><p>Error: ' + escapeHtml(error.message) + '</p></div>';
                }
            });
    }

    function init() {
        const button = document.getElementById('topbar-search-btn')
            || document.querySelector('.topbar-icon-btn[aria-label="Search"]');
        const field = input();
        const backdrop = document.getElementById('search-overlay-backdrop');
        const closeButton = document.getElementById('search-close-btn');
        const modal = overlay();

        if (!button || !field || !modal) return;

        button.addEventListener('click', function (event) {
            event.preventDefault();
            openSearch();
        });

        field.addEventListener('input', function () {
            const query = field.value.trim();
            window.clearTimeout(searchTimeout);
            searchTimeout = window.setTimeout(function () {
                performSearch(query);
            }, 250);
        });

        field.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                navigateResults('down');
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                navigateResults('up');
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (selectedIndex >= 0) {
                    selectResult(selectedIndex);
                }
            } else if (event.key === 'Escape') {
                event.preventDefault();
                closeSearch();
            }
        });

        if (backdrop) {
            backdrop.addEventListener('click', closeSearch);
        }
        if (closeButton) {
            closeButton.addEventListener('click', closeSearch);
        }

        document.addEventListener('click', function (event) {
            const item = event.target.closest('.search-result-item');
            if (item) {
                const index = Number(item.getAttribute('data-index'));
                if (Number.isInteger(index)) {
                    selectResult(index);
                }
            }
        });

        document.addEventListener('keydown', function (event) {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                openSearch();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
