/**
 * Help v1 — manifest search + markdown-ish snapshot renderer (P3-M11)
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

    function renderMarkdown(md) {
        if (!md) return '';
        let html = escapeHtml(md);

        // Fenced code blocks
        html = html.replace(/```([\s\S]*?)```/g, (_, code) =>
            '<pre><code>' + code.trim() + '</code></pre>'
        );

        // Headings
        html = html.replace(/^#### (.+)$/gm, '<h4>$1</h4>');
        html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
        html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
        html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');

        // Bold and inline code
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

        // Unordered lists (simple)
        html = html.replace(/(?:^|\n)((?:- .+\n?)+)/g, (match, block) => {
            const items = block.trim().split('\n').map((line) => {
                const text = line.replace(/^- /, '');
                return '<li>' + text + '</li>';
            }).join('');
            return '\n<ul>' + items + '</ul>\n';
        });

        // Tables (pipe syntax)
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

        // Paragraphs
        html = html.split(/\n{2,}/).map((block) => {
            block = block.trim();
            if (!block) return '';
            if (/^<(h[1-6]|ul|ol|pre|table)/.test(block)) return block;
            return '<p>' + block.replace(/\n/g, '<br>') + '</p>';
        }).join('\n');

        return html;
    }

    async function loadManifest() {
        const res = await fetch('/docs/help-manifest.json');
        if (!res.ok) throw new Error('Failed to load help manifest');
        manifest = await res.json();
        return manifest;
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

    function searchTopics(query) {
        if (!manifest?.topics) return [];
        const q = query.trim().toLowerCase();
        if (!q) return [];

        return manifest.topics.filter((topic) => {
            const hay = [
                topic.title,
                topic.id,
                ...(topic.keywords || []),
            ].join(' ').toLowerCase();
            return hay.includes(q) || q.split(/\s+/).every((word) => hay.includes(word));
        });
    }

    function renderNav(currentId) {
        const nav = document.getElementById('help-topic-nav');
        if (!nav || !manifest?.topics) return;

        nav.innerHTML = manifest.topics.map((topic) => `
            <li class="help-nav-item">
                <a href="#" class="help-nav-link ${topic.id === currentId ? 'active' : ''}"
                   data-help-topic-link="${escapeHtml(topic.id)}">
                    ${escapeHtml(topic.title)}
                </a>
            </li>
        `).join('');

        nav.querySelectorAll('[data-help-topic-link]').forEach((link) => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                showTopic(link.getAttribute('data-help-topic-link'));
            });
        });
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
            const res = await fetch(topic.snapshot);
            if (!res.ok) throw new Error('Snapshot not found');
            const md = await res.text();
            content.innerHTML = '<div class="markdown-content">' + renderMarkdown(md) + '</div>';

            if (source) {
                const base = manifest.docs_base_url || 'https://docs.birkly.com';
                const docsUrl = base.replace(/\/$/, '') + '/' + (topic.docs_path || '');
                source.innerHTML = 'Source: <a href="' + escapeHtml(docsUrl) + '" target="_blank" rel="noopener">Birkly-Docs — ' + escapeHtml(topic.title) + '</a>';
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
