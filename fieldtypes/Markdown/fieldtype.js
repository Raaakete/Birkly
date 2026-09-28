window.HaruFieldtypes = window.HaruFieldtypes || {};

function birklyMarkdownEscapeHtml(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

window.HaruFieldtypes['markdown'] = {
  label: 'Markdown',
  category: 'content',
  dynamicItemsEligible: true,
  render: function(fieldConfig, value) {
    return `<textarea class="input" name="${fieldConfig.name}" id="field_${fieldConfig.name}" rows="${fieldConfig.rows || 10}" ${fieldConfig.required ? 'required' : ''} placeholder="${birklyMarkdownEscapeHtml(fieldConfig.placeholder || 'Write markdown...')}">${birklyMarkdownEscapeHtml(value || '')}</textarea>`;
  },
  getValue: function(fieldName) {
    var input = document.getElementById('field_' + fieldName);
    return input ? input.value : '';
  },
  setValue: function(fieldName, value) {
    var input = document.getElementById('field_' + fieldName);
    if (input) input.value = value || '';
  },
  renderConfig: function(config, onChange) {
    return null;
  }
};

(function() {
  if (window.__birklyMarkdownFieldtypeLoaded) {
    return;
  }
  window.__birklyMarkdownFieldtypeLoaded = true;

  function safeLink(url) {
    if (/^(https?:\/\/|mailto:)/i.test(url)) {
      return birklyMarkdownEscapeHtml(url);
    }
    return '#';
  }

  function renderInline(text) {
    var html = birklyMarkdownEscapeHtml(text);
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function(match, label, url) {
      return '<a href="' + safeLink(url) + '" target="_blank" rel="noopener noreferrer">' + birklyMarkdownEscapeHtml(label) + '</a>';
    });
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    return html;
  }

  function renderMarkdown(source) {
    var lines = String(source || '').replace(/\r\n?/g, '\n').split('\n');
    var html = [];
    var inUl = false;
    var inOl = false;

    function closeLists() {
      if (inUl) {
        html.push('</ul>');
        inUl = false;
      }
      if (inOl) {
        html.push('</ol>');
        inOl = false;
      }
    }

    lines.forEach(function(line) {
      var trimmed = line.trim();
      var match;

      if (!trimmed) {
        closeLists();
        return;
      }

      match = trimmed.match(/^(#{1,6})\s+(.+)$/);
      if (match) {
        closeLists();
        html.push('<h' + match[1].length + '>' + renderInline(match[2]) + '</h' + match[1].length + '>');
        return;
      }

      if (/^>\s?/.test(trimmed)) {
        closeLists();
        html.push('<blockquote>' + renderInline(trimmed.replace(/^>\s?/, '')) + '</blockquote>');
        return;
      }

      if (/^- /.test(trimmed)) {
        if (!inUl) {
          closeLists();
          html.push('<ul>');
          inUl = true;
        }
        html.push('<li>' + renderInline(trimmed.replace(/^- /, '')) + '</li>');
        return;
      }

      if (/^\d+\.\s+/.test(trimmed)) {
        if (!inOl) {
          closeLists();
          html.push('<ol>');
          inOl = true;
        }
        html.push('<li>' + renderInline(trimmed.replace(/^\d+\.\s+/, '')) + '</li>');
        return;
      }

      closeLists();
      html.push('<p>' + renderInline(trimmed) + '</p>');
    });

    closeLists();

    if (!html.length) {
      return '<p class="ft-list-empty">Nothing to preview yet.</p>';
    }

    return html.join('');
  }

  function setActiveTab(root, tabName) {
    var buttons = root.querySelectorAll('[data-markdown-tab]');
    var textarea = root.querySelector('.markdown-field-input');
    var preview = root.querySelector('.markdown-field-preview');

    buttons.forEach(function(button) {
      var active = button.getAttribute('data-markdown-tab') === tabName;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    if (!textarea || !preview) {
      return;
    }

    preview.innerHTML = renderMarkdown(textarea.value);
    var previewMode = tabName === 'preview';
    textarea.hidden = previewMode;
    preview.hidden = !previewMode;
  }

  function initField(root) {
    if (!root || root.dataset.markdownBound === '1') {
      return;
    }

    var textarea = root.querySelector('.markdown-field-input');
    var buttons = root.querySelectorAll('[data-markdown-tab]');
    var preview = root.querySelector('.markdown-field-preview');
    if (!textarea || !buttons.length || !preview) {
      return;
    }

    root.dataset.markdownBound = '1';
    preview.innerHTML = renderMarkdown(textarea.value);

    buttons.forEach(function(button) {
      button.addEventListener('click', function() {
        setActiveTab(root, button.getAttribute('data-markdown-tab'));
      });
    });

    textarea.addEventListener('input', function() {
      preview.innerHTML = renderMarkdown(textarea.value);
    });

    setActiveTab(root, 'write');
  }

  function initAll() {
    document.querySelectorAll('[data-markdown-field]').forEach(initField);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }

  if ('MutationObserver' in window) {
    new MutationObserver(initAll).observe(document.documentElement, { childList: true, subtree: true });
  }
})();
