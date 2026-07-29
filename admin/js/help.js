/**
 * Help v3 — categorized nav, rich markdown snapshots, in-app cross-links
 */
(function () {
    'use strict';

    let manifest = null;
    let activeTopicId = null;
    let topicLinkMaps = { byBasename: {}, byDocsPath: {}, byId: {} };

    const NAV_SECTION_ORDER = [
        'overview', 'admin', 'content', 'templating', 'integration',
        'automation', 'extensibility', 'plugin-economy', 'operations', 'platform'
    ];

    const NAV_SECTION_LABELS = {
        overview: 'Overview',
        admin: 'Admin',
        content: 'Content model',
        templating: 'Templating',
        integration: 'Integration',
        automation: 'Automation',
        extensibility: 'Extensibility',
        'plugin-economy': 'Plugin economy',
        operations: 'Operations',
        platform: 'Platform',
    };

    const DOC_SECTION_CLASSES = {
        summary: 'help-doc-label help-doc-label-summary',
        beginner: 'help-doc-label help-doc-label-beginner',
        'advanced users': 'help-doc-label help-doc-label-advanced',
        advanced: 'help-doc-label help-doc-label-advanced',
        'further reading': 'help-doc-label help-doc-label-further',
        related: 'help-doc-label help-doc-label-further',
    };

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

    function navSectionForTopic(topic) {
        if (topic.nav_section) return String(topic.nav_section);
        const docsPath = String(topic.docs_path || '');
        const match = docsPath.match(/^(\d{2}-[^/]+)/);
        if (match) {
            return match[1].replace(/^\d{2}-/, '').replace(/-/g, '_');
        }
        return topic.section || 'platform';
    }

    function navSectionLabel(sectionId) {
        return NAV_SECTION_LABELS[sectionId] || sectionId.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
    }

    function buildTopicLinkMaps() {
        topicLinkMaps = { byBasename: {}, byDocsPath: {}, byId: {} };
        if (!manifest?.topics) return;

        manifest.topics.forEach((topic) => {
            topicLinkMaps.byId[topic.id] = topic.id;
            if (topic.docs_path) {
                topicLinkMaps.byDocsPath[topic.docs_path] = topic.id;
                const mdPath = topic.docs_path.replace(/\.html$/i, '.md');
                topicLinkMaps.byDocsPath[mdPath] = topic.id;
                const base = mdPath.split('/').pop() || '';
                if (base) {
                    topicLinkMaps.byBasename[base] = topic.id;
                    topicLinkMaps.byBasename[base.replace(/\.md$/i, '')] = topic.id;
                }
            }
            topicLinkMaps.byBasename[topic.id + '.md'] = topic.id;
            topicLinkMaps.byBasename[topic.id] = topic.id;
        });
    }

    function resolveTopicIdFromHref(href) {
        if (!href) return null;
        const clean = href.split('#')[0].trim();
        if (topicLinkMaps.byId[clean]) return clean;
        if (topicLinkMaps.byDocsPath[clean]) return topicLinkMaps.byDocsPath[clean];
        const base = clean.split('/').pop() || clean;
        if (topicLinkMaps.byBasename[base]) return topicLinkMaps.byBasename[base];
        const noExt = base.replace(/\.md$/i, '');
        if (topicLinkMaps.byBasename[noExt]) return topicLinkMaps.byBasename[noExt];
        return null;
    }

    function renderInline(text) {
        let html = escapeHtml(text);
        html = html.replace(/`([^`]+)`/g, '<code class="help-inline-code">$1</code>');
        html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (_, label, href) => {
            const topicId = resolveTopicIdFromHref(href);
            if (topicId) {
                return '<a href="#" class="help-inline-link help-topic-link" data-help-topic-link="' +
                    escapeHtml(topicId) + '">' + escapeHtml(label) + '</a>';
            }
            if (/^https?:\/\//i.test(href)) {
                return '<a href="' + escapeHtml(href) + '" class="help-inline-link" target="_blank" rel="noopener">' +
                    escapeHtml(label) + '</a>';
            }
            return '<span class="help-link-muted">' + escapeHtml(label) + '</span>';
        });
        return html;
    }

    function renderCallout(type, body) {
        const label = type.charAt(0).toUpperCase() + type.slice(1);
        return '<div class="help-callout help-callout-' + escapeHtml(type) + '">' +
            '<div class="help-callout-label">' + escapeHtml(label) + '</div>' +
            '<div class="help-callout-body">' + renderInline(body) + '</div></div>';
    }

    function renderMarkdown(md) {
        if (!md) return '';

        const codeBlocks = [];
        let text = md.replace(/```(\w*)\n?([\s\S]*?)```/g, (_, lang, code) => {
            const idx = codeBlocks.length;
            codeBlocks.push({ lang: lang || '', code: code.trim() });
            return '\n@@CODEBLOCK_' + idx + '@@\n';
        });

        const lines = text.split('\n');
        const blocks = [];
        let i = 0;

        while (i < lines.length) {
            const line = lines[i];
            const trimmed = line.trim();

            if (trimmed === '') {
                i++;
                continue;
            }

            if (/^@@CODEBLOCK_\d+@@$/.test(trimmed)) {
                const idx = parseInt(trimmed.match(/\d+/)[0], 10);
                const block = codeBlocks[idx];
                const langClass = block.lang ? ' class="language-' + escapeHtml(block.lang) + '"' : '';
                const langLabel = block.lang
                    ? '<span class="help-code-lang">' + escapeHtml(block.lang) + '</span>'
                    : '';
                blocks.push(
                    '<div class="help-code-block">' + langLabel +
                    '<pre><code' + langClass + '>' + escapeHtml(block.code) + '</code></pre></div>'
                );
                i++;
                continue;
            }

            if (/^---+$/.test(trimmed)) {
                blocks.push('<hr class="help-hr">');
                i++;
                continue;
            }

            const hMatch = trimmed.match(/^(#{1,4})\s+(.+)$/);
            if (hMatch) {
                const level = hMatch[1].length;
                const title = hMatch[2].trim();
                const key = title.toLowerCase();
                const sectionClass = DOC_SECTION_CLASSES[key] || '';
                const tag = 'h' + level;
                const cls = sectionClass ? ' class="' + sectionClass + '"' : '';
                blocks.push('<' + tag + cls + '>' + renderInline(title) + '</' + tag + '>');
                i++;
                continue;
            }

            if (/^\|/.test(trimmed)) {
                const tableLines = [];
                while (i < lines.length && lines[i].trim().startsWith('|')) {
                    tableLines.push(lines[i].trim());
                    i++;
                }
                if (tableLines.length >= 2) {
                    const headerCells = tableLines[0].split('|').filter((c) => c.trim() !== '');
                    const bodyLines = tableLines.slice(2);
                    let table = '<div class="help-table-wrap"><table><thead><tr>';
                    headerCells.forEach((c) => { table += '<th>' + renderInline(c.trim()) + '</th>'; });
                    table += '</tr></thead><tbody>';
                    bodyLines.forEach((row) => {
                        const cells = row.split('|').filter((c) => c.trim() !== '');
                        table += '<tr>';
                        cells.forEach((c) => { table += '<td>' + renderInline(c.trim()) + '</td>'; });
                        table += '</tr>';
                    });
                    table += '</tbody></table></div>';
                    blocks.push(table);
                }
                continue;
            }

            if (/^>\s?/.test(trimmed)) {
                const quoteLines = [];
                while (i < lines.length && /^>\s?/.test(lines[i].trim())) {
                    quoteLines.push(lines[i].trim().replace(/^>\s?/, ''));
                    i++;
                }
                const quoteText = quoteLines.join(' ');
                const calloutMatch = quoteText.match(/^\*\*(Note|Tip|Warning|Example|Action|Test):\*\*\s*(.*)$/i);
                if (calloutMatch) {
                    blocks.push(renderCallout(calloutMatch[1].toLowerCase(), calloutMatch[2]));
                } else {
                    blocks.push('<blockquote class="help-blockquote">' + renderInline(quoteText) + '</blockquote>');
                }
                continue;
            }

            if (/^-\s+/.test(trimmed)) {
                const items = [];
                while (i < lines.length && /^-\s+/.test(lines[i].trim())) {
                    items.push(lines[i].trim().replace(/^-\s+/, ''));
                    i++;
                }
                blocks.push('<ul class="help-list">' +
                    items.map((item) => '<li>' + renderInline(item) + '</li>').join('') +
                    '</ul>');
                continue;
            }

            if (/^\d+\.\s+/.test(trimmed)) {
                const items = [];
                while (i < lines.length && /^\d+\.\s+/.test(lines[i].trim())) {
                    items.push(lines[i].trim().replace(/^\d+\.\s+/, ''));
                    i++;
                }
                blocks.push('<ol class="help-list help-list-ordered">' +
                    items.map((item) => '<li>' + renderInline(item) + '</li>').join('') +
                    '</ol>');
                continue;
            }

            const paraLines = [];
            while (i < lines.length && lines[i].trim() !== '' &&
                !/^#{1,4}\s/.test(lines[i].trim()) &&
                !/^>\s?/.test(lines[i].trim()) &&
                !/^-\s+/.test(lines[i].trim()) &&
                !/^\d+\.\s+/.test(lines[i].trim()) &&
                !/^\|/.test(lines[i].trim()) &&
                !/^---+$/.test(lines[i].trim()) &&
                !/^@@CODEBLOCK_\d+@@$/.test(lines[i].trim())
            ) {
                paraLines.push(lines[i]);
                i++;
            }
            blocks.push('<p>' + renderInline(paraLines.join(' ')) + '</p>');
        }

        let html = blocks.join('\n');

        html = html.replace(
            /(<h[23][^>]*help-doc-label-further[^>]*>[\s\S]*?<\/h[23]>)([\s\S]*?)(?=<h[12]|$)/i,
            (_, heading, body) =>
                '<section class="help-further-reading">' + heading + body + '</section>'
        );

        return html;
    }

    async function loadManifest() {
        try {
            const res = await fetch(Birkly.url('/api/help_manifest.php'));
            if (!res.ok) throw new Error('Failed to load help manifest');
            manifest = await res.json();
            manifest._loadedFrom = 'api';
        } catch (apiErr) {
            const offlineRes = await fetch(Birkly.url('/api/help_offline_manifest.php'));
            if (!offlineRes.ok) throw apiErr;
            manifest = await offlineRes.json();
            manifest._loadedFrom = 'offline';
            manifest._offlineMessage = 'Showing bundled Help topics — live Birkly-Docs manifest unavailable.';
        }
        buildTopicLinkMaps();
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

    function topicSearchText(topic) {
        return [
            topic.title,
            topic.id,
            navSectionLabel(navSectionForTopic(topic)),
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

    function groupTopicsByNavSection(topics) {
        const groups = new Map();
        topics.forEach((topic) => {
            const sectionId = navSectionForTopic(topic);
            if (!groups.has(sectionId)) {
                groups.set(sectionId, []);
            }
            groups.get(sectionId).push(topic);
        });

        const ordered = [];
        NAV_SECTION_ORDER.forEach((id) => {
            if (groups.has(id)) {
                ordered.push({ id, label: navSectionLabel(id), topics: groups.get(id) });
                groups.delete(id);
            }
        });
        groups.forEach((sectionTopics, id) => {
            ordered.push({ id, label: navSectionLabel(id), topics: sectionTopics });
        });
        return ordered;
    }

    function bindTopicLinks(root) {
        if (!root) return;
        root.querySelectorAll('[data-help-topic-link]').forEach((link) => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                showTopic(link.getAttribute('data-help-topic-link'));
            });
        });
    }

    function renderNav(currentId) {
        const navRoot = document.getElementById('help-topic-nav-platform');
        if (!navRoot || !manifest?.topics) return;

        const groups = groupTopicsByNavSection(manifest.topics);
        const activeSection = navSectionForTopic(
            manifest.topics.find((t) => t.id === currentId) || { id: currentId }
        );

        navRoot.innerHTML = groups.map((group) => {
            const isOpen = group.id === activeSection || group.topics.some((t) => t.id === currentId);
            const items = group.topics.map((topic) => `
                <li class="help-nav-item">
                    <a href="#" class="help-nav-link ${topic.id === currentId ? 'active' : ''}"
                       data-help-topic-link="${escapeHtml(topic.id)}">
                        ${escapeHtml(topic.title)}
                    </a>
                </li>
            `).join('');

            return `
                <details class="help-nav-group" ${isOpen ? 'open' : ''}>
                    <summary class="help-nav-group-title">${escapeHtml(group.label)}</summary>
                    <ul class="help-nav-list">${items}</ul>
                </details>
            `;
        }).join('');

        bindTopicLinks(navRoot);
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
                banner = '<div class="alert alert-info help-offline-banner">' +
                    escapeHtml(manifest._offlineMessage || 'Offline Help mode') + '</div>';
            }
            if (!topic.snapshot) {
                throw new Error('Help topic is missing a snapshot path');
            }
            const res = await fetch(resolveUrl(topic.snapshot));
            if (!res.ok) throw new Error('Snapshot not found');
            const md = await res.text();
            if (/^\s*<!DOCTYPE html/i.test(md) || /^\s*<html[\s>]/i.test(md)) {
                throw new Error('Snapshot returned HTML instead of markdown — check Help manifest snapshot paths');
            }

            const sectionLabel = navSectionLabel(navSectionForTopic(topic));
            content.innerHTML = banner +
                '<div class="help-topic-meta"><span class="help-topic-category">' + escapeHtml(sectionLabel) + '</span></div>' +
                '<article class="markdown-content help-article">' + renderMarkdown(md) + '</article>';

            bindTopicLinks(content);

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
                    <div class="help-search-result-keywords">${escapeHtml(navSectionLabel(navSectionForTopic(topic)))} · ${escapeHtml((topic.keywords || []).slice(0, 4).join(', '))}</div>
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
