/**
 * Help v2 — manifest search + markdown-ish snapshot renderer
 */
(function () {
    'use strict';

    let manifest = null;
    let activeTopicId = null;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function resolveUrl(path) {
        if (!path) return '';
        if (/^https?:\/\//i.test(path)) return path;
        return (window.Birkly && typeof Birkly.url === 'function') ? Birkly.url(path) : path;
    }

    function renderMarkdown(md) {
        if (!md) return '';
        let html = escapeHtml(md);

        html = html.replace(/```([\s\S]*?)```/g, (_, code) =>
            '<pre><code>' + code.trim() + '</code></pre>'
        );

        html = html.replace(/^#### (.+)$/gm, '<h4>$1</h4>');
        html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
        html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
        html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');

        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

        html = html.replace(/(?:^|\n)((?:- .+\n?)+)/g, (match, block) => {
            const items = block.trim().split('\n').map((line) => {
                const text = line.replace(/^- /, '');
                return '<li>' + text + '</li>';
            }).join('');
            return '\n<ul>' + items + '</ul>\n';
        });

        html = html.replace(/(?:^|\n)(\|[^\n]+\|\n\|[-| :]+\|\n(?:\|[^\n]+\|\n?)+)/g, (match) => {
            const lines = match.trim().split('\n').filter(Boolean);
            if (lines.length < 2) return match;
            const headerCells = lines[0].split('|').filter((c) => c.trim() !== '');
            const bodyLines = lines.slice(2);
            let table = '<table><thead><tr>';
            headerCells.forEach((c) => { table += '<th>' + c.trim() + '</th>'; });
            table += '</tr></thead><tbody>';
            bodyLines.forEach((line) => {
                const cells = line.split('|').filter((c) => c.trim() !== '');
                table += '<tr>';
                cells.forEach((c) => { table += '<td>' + c.trim() + '</td>'; });
                table += '</tr>';
            });
            table += '</tbody></table>';
            return '\n' + table + '\n';
        });

        html = html.split(/\n{2,}/).map((block) => {
            block = block.trim();
            if (!block) return '';
            if (/^<(h[1-6]|ul|ol|pre|table)/.test(block)) return block;
            return '<p>' + block.replace(/\n/g, '<br>') + '</p>';
        }).join('\n');

        return html;
    }

    async function loadManifest() {
        try {
            const res = await fetch(Birkly.url('/api/help_manifest.php'));
            if (!res.ok) throw new Error('Failed to load help manifest');
            manifest = await res.json();
            manifest._loadedFrom = 'api';
            return manifest;
        } catch (apiErr) {
            const offlineRes = await fetch(Birkly.url('/api/help_offline_manifest.php'));
            if (!offlineRes.ok) throw apiErr;
            manifest = await offlineRes.json();
            manifest._loadedFrom = 'offline';
            manifest._offlineMessage = 'Showing bundled Help topics — live Birkly-Docs manifest unavailable.';
            return manifest;
        }
    }

    function getTopicFromUrl() {
        const params = new URLSearchParams(window.location.search);
        return params.get('topic') || 'getting-started';
    }

    function setUrlTopic(topicId) {
        const url = new URL(window.location.href);
        url.searchParams.set('topic', topicId);
        window.history.replaceState({}, '', url.toString());
    }

    function topicSearchText(topic) {
        return [
            topic.title,
            topic.id,
            topic.section || '',
            topic.description || '',
            topic.docs_path || '',
            topic.ai_topic || '',
            ...(topic.keywords || []),
        ].join(' ').toLowerCase();
    }

    function searchTopics(query) {
        if (!manifest?.topics) return [];
        const q = query.trim().toLowerCase();
        if (!q) return [];

        const words = q.split(/\s+/).filter(Boolean);

        return manifest.topics.filter((topic) => {
            const hay = topicSearchText(topic);
            return hay.includes(q) || words.every((word) => hay.includes(word));
        });
    }

    function renderNavList(navEl, topics, currentId) {
        if (!navEl) return;
        navEl.innerHTML = topics.map((topic) => `
            <li class="help-nav-item${topic.parent ? ' help-nav-item-sub' : ''}">
                <a href="#" class="help-nav-link ${topic.id === currentId ? 'active' : ''}"
                   data-help-topic-link="${escapeHtml(topic.id)}">
                    ${escapeHtml(topic.title)}
                </a>
            </li>
        `).join('');

        navEl.querySelectorAll('[data-help-topic-link]').forEach((link) => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                showTopic(link.getAttribute('data-help-topic-link'));
            });
        });
    }

    function renderNav(currentId) {
        if (!manifest?.topics) return;
        renderNavList(document.getElementById('help-topic-nav-platform'), manifest.topics, currentId);
    }

    async function showTopic(topicId) {
        if (!manifest?.topics) return;
        const topic = manifest.topics.find((t) => t.id === topicId);
        if (!topic) return;

        activeTopicId = topicId;
        setUrlTopic(topicId);
        renderNav(topicId);

        const content = document.getElementById('help-topic-content');
        const source = document.getElementById('help-doc-source');
        if (!content) return;

        content.innerHTML = '<p class="text-secondary">Loading…</p>';

        try {
            let banner = '';
            if (manifest && manifest._loadedFrom === 'offline') {
                banner = '<div class="alert alert-info help-offline-banner">' + escapeHtml(manifest._offlineMessage || 'Offline Help mode') + '</div>';
            }
            const res = await fetch(resolveUrl(topic.snapshot));
            if (!res.ok) throw new Error('Snapshot not found');
            const md = await res.text();
            content.innerHTML = banner + '<div class="markdown-content">' + renderMarkdown(md) + '</div>';

            if (source) {
                const base = manifest.docs_base_url || 'https://docs.birkly.com';
                const docsPath = topic.docs_path || '';
                const parts = [];
                if (docsPath) {
                    const docsUrl = base.replace(/\/$/, '') + '/' + docsPath;
                    parts.push('Source: <a href="' + escapeHtml(docsUrl) + '" target="_blank" rel="noopener">Birkly-Docs — ' + escapeHtml(topic.title) + '</a>');
                }
                if (topic.ai_topic) {
                    const aiSlug = escapeHtml(topic.ai_topic);
                    parts.push('AI playbook: <code>' + aiSlug + '</code> — load via admin AI <code>read_birkly_docs</code> or MCP <code>birkly://docs/' + aiSlug + '</code>');
                }
                source.innerHTML = parts.join(' · ');
            }
        } catch (err) {
            content.innerHTML = '<div class="alert alert-info"><p>Could not load topic: ' + escapeHtml(err.message) + '</p></div>';
        }

        const results = document.getElementById('help-search-results');
        if (results) results.classList.remove('is-open');
        const input = document.getElementById('help-search-input');
        if (input) input.value = '';
    }

    function setupSearch() {
        const input = document.getElementById('help-search-input');
        const results = document.getElementById('help-search-results');
        if (!input || !results) return;

        let debounce = null;

        function renderResults(matches) {
            if (!matches.length) {
                results.innerHTML = '<div class="help-search-result-item" style="cursor:default;color:var(--text-secondary)">No matches</div>';
                results.classList.add('is-open');
                return;
            }

            results.innerHTML = matches.map((topic) => `
                <a href="#" class="help-search-result-item" data-help-search-id="${escapeHtml(topic.id)}">
                    <div class="help-search-result-title">${escapeHtml(topic.title)}</div>
                    <div class="help-search-result-keywords">${escapeHtml((topic.keywords || []).slice(0, 5).join(', '))}</div>
                </a>
            `).join('');

            results.querySelectorAll('[data-help-search-id]').forEach((el) => {
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    showTopic(el.getAttribute('data-help-search-id'));
                });
            });
            results.classList.add('is-open');
        }

        input.addEventListener('input', () => {
            clearTimeout(debounce);
            debounce = setTimeout(() => {
                const q = input.value.trim();
                if (q.length < 2) {
                    results.classList.remove('is-open');
                    return;
                }
                renderResults(searchTopics(q));
            }, 200);
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                results.classList.remove('is-open');
                input.blur();
            }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.help-search-wrap')) {
                results.classList.remove('is-open');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', async () => {
        const page = document.querySelector('.help-page-v1');
        if (!page) return;

        try {
            await loadManifest();
            setupSearch();
            const topicId = getTopicFromUrl();
            await showTopic(manifest.topics.find((t) => t.id === topicId) ? topicId : 'getting-started');
        } catch (err) {
            const content = document.getElementById('help-topic-content');
            if (content) {
                content.innerHTML = '<div class="alert alert-error">Help system error: ' + escapeHtml(err.message) + '</div>';
            }
        }
    });

    window.Birkly = window.Birkly || {};
    window.Birkly.Help = { showTopic, searchTopics, getManifest: () => manifest };
})();
