/**
 * In-plugin documentation panel — loads register_plugin_docs content via plugin_doc API.
 */
(function () {
  'use strict';

  var manifestCache = null;
  var loadedPanels = {};

  function apiUrl(path) {
    return (window.Birkly && typeof Birkly.url === 'function') ? Birkly.url(path) : path;
  }

  function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
  }

  function renderMarkdown(md) {
    if (!md) return '';
    var html = escapeHtml(md);
    html = html.replace(/```([\s\S]*?)```/g, function (_, code) {
      return '<pre><code>' + code.trim() + '</code></pre>';
    });
    html = html.replace(/^#### (.+)$/gm, '<h4>$1</h4>');
    html = html.replace(/^### (.+)$/gm, '<h3>$1</h3>');
    html = html.replace(/^## (.+)$/gm, '<h2>$1</h2>');
    html = html.replace(/^# (.+)$/gm, '<h1>$1</h1>');
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    html = html.replace(/(?:^|\n)((?:- .+\n?)+)/g, function (match, block) {
      var items = block.trim().split('\n').map(function (line) {
        return '<li>' + line.replace(/^- /, '') + '</li>';
      }).join('');
      return '\n<ul>' + items + '</ul>\n';
    });
    html = html.split(/\n{2,}/).map(function (block) {
      block = block.trim();
      if (!block) return '';
      if (/^<(h[1-6]|ul|ol|pre|table)/.test(block)) return block;
      return '<p>' + block.replace(/\n/g, '<br>') + '</p>';
    }).join('\n');
    return html;
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
        return t.plugin_id === pluginId || t.id === pluginId;
      });
      if (!topics.length) {
        topics = [{ id: 'overview', title: 'Overview', plugin_id: pluginId, snapshot: apiUrl('/api/plugin_doc.php?plugin=' + encodeURIComponent(pluginId) + '&doc=overview') }];
      }
      return topics;
    });
  }

  function fetchDoc(snapshotUrl) {
    return fetch(snapshotUrl, { credentials: 'same-origin' }).then(function (res) {
      if (!res.ok) throw new Error('Documentation not found');
      return res.text();
    });
  }

  function renderPanel(panelEl, pluginId) {
    if (!panelEl || loadedPanels[pluginId]) return Promise.resolve();
    loadedPanels[pluginId] = true;

    panelEl.innerHTML = '<div class="plugin-docs-layout"><nav class="plugin-docs-nav" aria-label="Documentation topics"></nav><div class="plugin-docs-content"><p class="form-text">Loading documentation…</p></div></div>';
    var nav = panelEl.querySelector('.plugin-docs-nav');
    var content = panelEl.querySelector('.plugin-docs-content');

    return topicsForPlugin(pluginId).then(function (topics) {
      nav.innerHTML = topics.map(function (topic, index) {
        return '<button type="button" class="plugin-docs-nav-item' + (index === 0 ? ' is-active' : '') + '" data-snapshot="' + escapeHtml(topic.snapshot || '') + '">' + escapeHtml(topic.title || topic.id) + '</button>';
      }).join('');

      function showTopic(btn) {
        nav.querySelectorAll('.plugin-docs-nav-item').forEach(function (b) {
          b.classList.toggle('is-active', b === btn);
        });
        var url = btn.getAttribute('data-snapshot');
        content.innerHTML = '<p class="form-text">Loading…</p>';
        fetchDoc(url).then(function (md) {
          content.innerHTML = '<div class="markdown-content">' + renderMarkdown(md) + '</div>';
        }).catch(function (err) {
          content.innerHTML = '<p class="error">' + escapeHtml(err.message || 'Failed to load') + '</p>';
        });
      }

      nav.querySelectorAll('.plugin-docs-nav-item').forEach(function (btn) {
        btn.addEventListener('click', function () { showTopic(btn); });
      });

      var first = nav.querySelector('.plugin-docs-nav-item');
      if (first) showTopic(first);
    });
  }

  function activate(pluginId, panelId) {
    var panelEl = panelId
      ? document.getElementById(panelId)
      : document.querySelector('.plugin-docs-panel[data-plugin-id="' + pluginId + '"]');
    if (!panelEl) return;
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

  window.BirklyPluginDocs = { activate: activate, init: initPanels };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPanels);
  } else {
    initPanels();
  }
})();
