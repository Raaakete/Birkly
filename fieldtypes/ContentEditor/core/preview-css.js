// Project CSS preview for ContentEditor (scoped to .content-editor-area only)
window.ContentEditorPreview = {
  DEFAULT_CSS_PATH: '/fieldtypes/ContentEditor/preview/demo-preview.css',

  resolveUrl(cssUrl) {
    const trimmed = (cssUrl || '').trim();
    if (!trimmed) return '';
    try {
      return new URL(trimmed, window.location.href).href;
    } catch (e) {
      return trimmed;
    }
  },

  setStatus(wrapper, state, detail) {
    if (!wrapper) return;
    const badge = wrapper.querySelector('.content-editor-preview-badge');
    if (!badge) return;

    badge.dataset.previewState = state || 'idle';
    if (state === 'active') {
      badge.hidden = false;
      badge.textContent = detail || 'Site CSS preview';
      badge.title = detail || 'Project stylesheet applied to the visual editor';
    } else if (state === 'loading') {
      badge.hidden = false;
      badge.textContent = 'Loading preview CSS…';
      badge.title = detail || '';
    } else if (state === 'error') {
      badge.hidden = false;
      badge.textContent = 'Preview CSS unavailable';
      badge.title = detail || 'Could not load project stylesheet';
    } else {
      badge.hidden = true;
      badge.textContent = '';
      badge.title = '';
    }
  },

  scopeCSS(cssText, scopeSelector) {
    cssText = cssText.replace(/\/\*[\s\S]*?\*\//g, '');

    let result = '';
    let i = 0;
    let depth = 0;
    let inString = false;
    let stringChar = '';

    while (i < cssText.length) {
      const char = cssText[i];

      if (!inString && (char === '"' || char === "'")) {
        inString = true;
        stringChar = char;
        result += char;
        i++;
        continue;
      }
      if (inString) {
        if (char === stringChar && cssText[i - 1] !== '\\') {
          inString = false;
          stringChar = '';
        }
        result += char;
        i++;
        continue;
      }

      if (char === '@' && depth === 0) {
        let atEnd = i;
        while (atEnd < cssText.length && cssText[atEnd] !== '{') {
          atEnd++;
        }
        const atRule = cssText.substring(i, atEnd);
        result += atRule;
        i = atEnd;
        if (cssText[atEnd] === '{') {
          result += '{';
          i++;
          depth++;
          let contentStart = i;
          let contentDepth = 1;
          let contentEnd = i;
          while (contentEnd < cssText.length && contentDepth > 0) {
            if (cssText[contentEnd] === '{') contentDepth++;
            if (cssText[contentEnd] === '}') contentDepth--;
            if (contentDepth > 0) contentEnd++;
          }
          if (contentEnd < cssText.length) {
            const content = cssText.substring(contentStart, contentEnd);
            result += this.scopeCSS(content, scopeSelector);
            i = contentEnd;
            continue;
          }
        }
        continue;
      }

      if (char === '{') {
        depth++;
        result += char;
        i++;
        continue;
      }

      if (char === '}') {
        depth--;
        result += char;
        i++;
        continue;
      }

      if (depth === 0) {
        let selectorEnd = i;
        while (selectorEnd < cssText.length && cssText[selectorEnd] !== '{') {
          selectorEnd++;
        }

        if (selectorEnd < cssText.length) {
          const selector = cssText.substring(i, selectorEnd).trim();

          if (selector && !selector.includes(scopeSelector)) {
            if (selector.match(/^(:root|html|body)(\s|,|$)/)) {
              result += selector.replace(/(^|\s|,)(:root|html|body)(\s|,|$)/g, `$1${scopeSelector}$3`);
            } else {
              const selectors = selector.split(',').map(s => s.trim()).filter(Boolean);
              const scoped = selectors.map(sel => {
                if (sel.includes(scopeSelector)) return sel;
                return `${scopeSelector} ${sel}`;
              });
              result += scoped.join(', ');
            }
          } else {
            result += selector;
          }

          i = selectorEnd;
          continue;
        }
      }

      result += char;
      i++;
    }

    return result;
  },

  load(editor) {
    const wrapper = editor.closest('.content-editor-wrapper');
    if (!wrapper) return;

    const storedUrl = (wrapper.dataset.cssUrl || '').trim();
    const fetchUrl = (wrapper.dataset.cssFetchUrl || '').trim() || this.resolveUrl(storedUrl);
    if (!fetchUrl) {
      this.setStatus(wrapper, 'idle');
      return;
    }

    const resolvedKey = fetchUrl;
    const existingStyle = document.querySelector(`style[data-content-editor-css][data-css-url="${resolvedKey}"]`);
    if (existingStyle) {
      editor.classList.add('post-content');
      this.setStatus(wrapper, 'active', this.labelFromUrl(storedUrl || fetchUrl));
      return;
    }

    this.setStatus(wrapper, 'loading', storedUrl || fetchUrl);

    fetch(fetchUrl)
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.text();
      })
      .then(cssText => {
        let scopedCSS = this.scopeCSS(cssText, '.content-editor-area');
        scopedCSS = scopedCSS.replace(/(font-size\s*:\s*[^;!}]+)(;|})/g, (match, declaration, ending) => {
          if (!declaration.includes('!important')) {
            return declaration + ' !important' + ending;
          }
          return match;
        });

        const style = document.createElement('style');
        style.setAttribute('data-content-editor-css', 'true');
        style.setAttribute('data-css-url', resolvedKey);
        style.textContent = scopedCSS;
        document.head.appendChild(style);

        editor.classList.add('post-content');
        editor.offsetHeight;
        this.setStatus(wrapper, 'active', this.labelFromUrl(storedUrl || fetchUrl));
      })
      .catch(error => {
        console.warn('ContentEditor: Failed to load preview CSS from:', fetchUrl, error);
        this.setStatus(wrapper, 'error', String(error.message || error));
      });
  },

  labelFromUrl(cssUrl) {
    try {
      const path = new URL(cssUrl).pathname;
      const parts = path.split('/').filter(Boolean);
      return parts.length ? `Preview: ${parts[parts.length - 1]}` : 'Site CSS preview';
    } catch (e) {
      return 'Site CSS preview';
    }
  }
};
