/**
 * In-plugin documentation panel — loads register_plugin_docs content via plugin_doc API.
 * Markdown subset: headings, bold, code, lists, tables, links, images, fenced code.
 */
(function () {
  'use strict';

  var manifestCache = null;
  var loadedPanels = {};

  function docsT(key, fallback) {
    if (window.Birkly && Birkly.i18n && typeof Birkly.i18n.t === 'function') {
      return Birkly.i18n.t('admin.' + key, fallback);
    }
    return fallback;
  }

  function apiUrl(path) {
    return (window.Birkly && typeof Birkly.url === 'function') ? Birkly.url(path) : path;
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text == null ? '' : String(text);
    return div.innerHTML;
  }

  function inlineFormat(text) {
    var html = escapeHtml(text);
    html = html.replace(/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/g, function (_, alt, src, title) {
      var t = title ? ' title="' + escapeHtml(title) + '"' : '';
      return '<img class="plugin-docs-figure" src="' + escapeHtml(src) + '" alt="' + escapeHtml(alt) + '"' + t + ' loading="lazy">';
    });
    html = html.replace(/\[([^\]]+)\]\(([^)\s]+)(?:\s+"([^"]*)")?\)/g, function (_, label, href, title) {
      var t = title ? ' title="' + escapeHtml(title) + '"' : '';
      var ext = /^https?:\/\//i.test(href) ? ' target="_blank" rel="noopener"' : '';
      return '<a href="' + escapeHtml(href) + '"' + t + ext + '>' + label + '</a>';
    });
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    return html;
  }

  function isTableBlock(block) {
    var lines = block.trim().split('\n');
    if (lines.length < 2) return false;
    if (lines[0].indexOf('|') === -1) return false;
    return /^\|?\s*:?-+:?\s*(\|\s*:?-+:?\s*)+\|?\s*$/.test(lines[1].trim());
  }

  function renderTable(block) {
    var lines = block.trim().split('\n').filter(function (l) { return l.trim(); });
    if (lines.length < 2) return '<p>' + inlineFormat(block) + '</p>';
    function cells(line) {
      var raw = line.trim();
      if (raw.charAt(0) === '|') raw = raw.slice(1);
      if (raw.charAt(raw.length - 1) === '|') raw = raw.slice(0, -1);
      return raw.split('|').map(function (c) { return c.trim(); });
    }
    var head = cells(lines[0]);
    var body = lines.slice(2).map(cells);
    var html = '<div class="plugin-docs-table-wrap"><table class="plugin-docs-table"><thead><tr>';
    head.forEach(function (c) { html += '<th>' + inlineFormat(c) + '</th>'; });
    html += '</tr></thead><tbody>';
    body.forEach(function (row) {
      html += '<tr>';
      row.forEach(function (c) { html += '<td>' + inlineFormat(c) + '</td>'; });
      html += '</tr>';
    });
    html += '</tbody></table></div>';
    return html;
  }

  function renderList(block, ordered) {
    var re = ordered ? /^\d+\.\s+/ : /^- /;
    var tag = ordered ? 'ol' : 'ul';
    var items = block.trim().split('\n').map(function (line) {
      return '<li>' + inlineFormat(line.replace(re, '')) + '</li>';
    }).join('');
    return '<' + tag + '>' + items + '</' + tag + '>';
  }

  function renderMarkdown(md) {
    if (!md) return '';
    var html = String(md);
    var fences = [];
    html = html.replace(/```([a-zA-Z0-9_-]*)\n?([\s\S]*?)```/g, function (_, lang, code) {
      var i = fences.length;
      fences.push(
        '<pre class="plugin-docs-pre"' + (lang ? ' data-lang="' + escapeHtml(lang) + '"' : '') + '><code>' +
        escapeHtml(code.replace(/^\n+|\n+$/g, '')) +
        '</code></pre>'
      );
      return '\n%%FENCE' + i + '%%\n';
    });

    html = html.replace(/^#### (.+)$/gm, '%%H4%%$1%%/H4%%');
    html = html.replace(/^### (.+)$/gm, '%%H3%%$1%%/H3%%');
    html = html.replace(/^## (.+)$/gm, '%%H2%%$1%%/H2%%');
    html = html.replace(/^# (.+)$/gm, '%%H1%%$1%%/H1%%');
    html = html.replace(/^>\s?(.+)$/gm, '%%BQ%%$1%%/BQ%%');
    html = html.replace(/^---+$/gm, '%%HR%%');

    html = html.split(/\n{2,}/).map(function (block) {
      block = block.trim();
      if (!block) return '';
      var fence = block.match(/^%%FENCE(\d+)%%$/);
      if (fence) return fences[Number(fence[1])] || '';
      if (block.indexOf('%%H1%%') === 0) return '<h1>' + inlineFormat(block.replace(/^%%H1%%|%%\/H1%%$/g, '')) + '</h1>';
      if (block.indexOf('%%H2%%') === 0) return '<h2>' + inlineFormat(block.replace(/^%%H2%%|%%\/H2%%$/g, '')) + '</h2>';
      if (block.indexOf('%%H3%%') === 0) return '<h3>' + inlineFormat(block.replace(/^%%H3%%|%%\/H3%%$/g, '')) + '</h3>';
      if (block.indexOf('%%H4%%') === 0) return '<h4>' + inlineFormat(block.replace(/^%%H4%%|%%\/H4%%$/g, '')) + '</h4>';
      if (block === '%%HR%%') return '<hr>';
      if (block.indexOf('%%BQ%%') === 0) {
        var quote = block.replace(/%%BQ%%/g, '').replace(/%%\/BQ%%/g, '\n').trim();
        return '<blockquote class="plugin-docs-callout">' + inlineFormat(quote).replace(/\n/g, '<br>') + '</blockquote>';
      }
      if (isTableBlock(block)) return renderTable(block);
      if (/^(?:- .+\n?)+$/.test(block + '\n')) return renderList(block, false);
      if (/^(?:\d+\.\s.+\n?)+$/.test(block + '\n')) return renderList(block, true);
      return '<p>' + inlineFormat(block).replace(/\n/g, '<br>') + '</p>';
    }).join('\n');

    return html.replace(
      /<h2>Advanced Users<\/h2>([\s\S]*?)(?=<h2>|$)/i,
      '<details class="plugin-docs-advanced"><summary>Advanced Users</summary>$1</details>'
    );
  }

  function loadManifest() {
    if (manifestCache) return Promise.resolve(manifestCache);
    return fetch(apiUrl('/api/help_manifest.php'), { credentials: 'same-origin' })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        manifestCache = data;
        return data;
      });
  }

  function topicsForPlugin(pluginId) {
    return loadManifest().then(function (manifest) {
      var topics = (manifest.topics || []).filter(function (t) {
        return t.plugin_id === pluginId;
      });
      // Prefer overview first, then help_topics in registration order
      topics.sort(function (a, b) {
        if (a.id === pluginId) return -1;
        if (b.id === pluginId) return 1;
        return 0;
      });
      if (!topics.length) {
        topics = [{
          id: 'overview',
          title: 'Overview',
          plugin_id: pluginId,
          snapshot: apiUrl('/api/plugin_doc.php?plugin=' + encodeURIComponent(pluginId) + '&doc=overview')
        }];
      }
      return topics;
    });
  }

  function topicButtonHtml(topic, index) {
    var title = topic.title || topic.id;
    var isOverview = topic.id === topic.plugin_id;
    var label = isOverview ? 'Overview' : title;
    return (
      '<button type="button" class="side-nav-item plugin-docs-nav-item' + (index === 0 ? ' active is-active' : '') + '"' +
      ' role="tab" data-snapshot="' + escapeHtml(topic.snapshot || '') + '"' +
      ' data-topic-id="' + escapeHtml(topic.id || '') + '"' +
      ' aria-selected="' + (index === 0 ? 'true' : 'false') + '">' +
      '<span class="side-nav-label" data-text="' + escapeHtml(label) + '">' + escapeHtml(label) + '</span>' +
      '</button>'
    );
  }

  function buildNavHtml(topics) {
    var hasGroups = topics.some(function (t) {
      return t.id !== t.plugin_id && t.group;
    });
    if (!hasGroups) {
      return '<div class="side-nav-group">' + topics.map(topicButtonHtml).join('') + '</div>';
    }

    var groups = {};
    var groupOrder = [];
    topics.forEach(function (topic) {
      var g;
      if (topic.id === topic.plugin_id) {
        g = 'Start here';
      } else {
        g = topic.group || 'More';
      }
      if (!groups[g]) {
        groups[g] = [];
        groupOrder.push(g);
      }
      groups[g].push(topic);
    });
    // Keep Overview first inside its group
    Object.keys(groups).forEach(function (name) {
      groups[name].sort(function (a, b) {
        if (a.id === a.plugin_id) return -1;
        if (b.id === b.plugin_id) return 1;
        return 0;
      });
    });

    var html = '';
    var index = 0;
    groupOrder.forEach(function (name) {
      html += '<p class="side-nav-group-title">' + escapeHtml(name) + '</p>';
      html += '<div class="side-nav-group">';
      groups[name].forEach(function (t) {
        html += topicButtonHtml(t, index++);
      });
      html += '</div>';
    });
    return html;
  }

  function renderPanel(panelEl, pluginId) {
    if (!panelEl || loadedPanels[pluginId]) return Promise.resolve();
    loadedPanels[pluginId] = true;

    panelEl.innerHTML =
      '<div class="plugin-docs-layout">' +
        '<nav class="side-nav side-nav--track plugin-docs-nav" role="tablist" aria-label="' + escapeHtml(docsT('docs_topics', 'Documentation topics')) + '">' +
          '<div data-docs-nav-host></div>' +
        '</nav>' +
        '<div class="plugin-docs-content">' +
          '<p class="form-text">Loading documentation…</p>' +
        '</div>' +
      '</div>';
    var navHost = panelEl.querySelector('[data-docs-nav-host]');
    var content = panelEl.querySelector('.plugin-docs-content');

    return topicsForPlugin(pluginId).then(function (topics) {
      navHost.innerHTML = buildNavHtml(topics);

      function showTopic(btn) {
        navHost.querySelectorAll('.plugin-docs-nav-item').forEach(function (b) {
          var on = b === btn;
          b.classList.toggle('is-active', on);
          b.classList.toggle('active', on);
          b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        var url = btn.getAttribute('data-snapshot');
        var topicId = btn.getAttribute('data-topic-id') || '';
        content.innerHTML = '<p class="form-text">Loading…</p>';
        fetchDoc(url).then(function (md) {
          content.innerHTML = '<article class="markdown-content plugin-docs-article" data-topic="' + escapeHtml(topicId) + '">' + renderMarkdown(md) + '</article>';
          content.scrollTop = 0;
        }).catch(function (err) {
          content.innerHTML = '<p class="error">' + escapeHtml(err.message || docsT('docs_failed_to_load', 'Failed to load')) + '</p>';
        });
      }

      navHost.querySelectorAll('.plugin-docs-nav-item').forEach(function (btn) {
        btn.addEventListener('click', function () { showTopic(btn); });
      });

      var first = navHost.querySelector('.plugin-docs-nav-item');
      if (first) showTopic(first);
    });
  }

  function fetchDoc(snapshotUrl) {
    return fetch(snapshotUrl, { credentials: 'same-origin' }).then(function (res) {
      if (!res.ok) throw new Error(docsT('docs_not_found', 'Documentation not found'));
      return res.text();
    });
  }

  function activate(pluginId, panelId) {
    var panelEl = panelId
      ? document.getElementById(panelId)
      : document.querySelector('.plugin-docs-panel[data-plugin-id="' + pluginId + '"]');
    if (!panelEl) return;
    // Allow re-activate into a moved host (e.g. Settings → Docs tab)
    if (loadedPanels[pluginId] && !panelEl.querySelector('.plugin-docs-layout')) {
      delete loadedPanels[pluginId];
    }
    renderPanel(panelEl, pluginId);
  }

  function initPanels() {
    document.querySelectorAll('.plugin-docs-panel[data-plugin-id]').forEach(function (panel) {
      var pluginId = panel.getAttribute('data-plugin-id');
      if (!pluginId) return;
      if (!panel.closest('[hidden]') && panel.offsetParent !== null) {
        renderPanel(panel, pluginId);
      }
    });
  }

  window.BirklyPluginDocs = { activate: activate, init: initPanels, renderMarkdown: renderMarkdown };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPanels);
  } else {
    initPanels();
  }
})();
