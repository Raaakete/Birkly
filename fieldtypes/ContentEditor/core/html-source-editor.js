// HTML source panel — syntax highlighting and line numbers (dependency-free)
window.ContentEditorHtmlSource = {
  bound: new WeakSet(),

  bind(textarea, wrapper) {
    if (!textarea || this.bound.has(textarea)) return;
    this.bound.add(textarea);

    const panel = textarea.closest('.content-editor-html-panel');
    const gutter = panel ? panel.querySelector('.content-editor-html-gutter') : null;
    const code = panel ? panel.querySelector('.content-editor-html-highlight code') : null;

    textarea._ceHtmlGutter = gutter;
    textarea._ceHtmlHighlight = code;

    if (panel && code) {
      panel.classList.add('has-syntax-layer');
    }

    textarea.addEventListener('input', () => this.refresh(textarea));
    textarea.addEventListener('scroll', () => this.syncScroll(textarea));

    textarea.addEventListener('keydown', (event) => {
      if (event.key !== 'Tab') return;
      event.preventDefault();
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const value = textarea.value;
      textarea.value = `${value.substring(0, start)}  ${value.substring(end)}`;
      textarea.selectionStart = textarea.selectionEnd = start + 2;
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    });

    this.refresh(textarea);
  },

  escapeHtml(text) {
    return text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  },

  highlightAttributes(attrString) {
    return attrString.replace(
      /(\s)([\w:-]+)(\s*)(=)(\s*)("([^"]*)"|'([^']*)')/g,
      (_, space, name, space2, eq, space3, quoted, dbl, sgl) => {
        const value = dbl !== undefined ? `"${dbl}"` : `'${sgl}'`;
        return `${space}<span class="ce-html-attr">${name}</span>${space2}<span class="ce-html-bracket">${eq}</span>${space3}<span class="ce-html-value">${value}</span>`;
      }
    );
  },

  highlightHtml(source) {
    if (!source) return '';

    let html = this.escapeHtml(source);

    html = html.replace(
      /(&lt;!--[\s\S]*?--&gt;)/g,
      '<span class="ce-html-comment">$1</span>'
    );

    html = html.replace(
      /(&lt;)(\/?)([\w-]+)((?:\s(?:[^&]|&(?!gt;))*?)?)(\s*\/?)(&gt;)/g,
      (_, lt, slash, tag, attrs, selfClose, gt) => {
        let out = `<span class="ce-html-bracket">${lt}${slash}</span>`;
        out += `<span class="ce-html-tag">${tag}</span>`;
        if (attrs && attrs.trim()) {
          out += this.highlightAttributes(attrs);
        }
        out += `<span class="ce-html-bracket">${selfClose || ''}${gt}</span>`;
        return out;
      }
    );

    return html;
  },

  updateGutter(textarea, text) {
    const gutter = textarea._ceHtmlGutter;
    if (!gutter) return;

    const lines = text.split('\n');
    const lineCount = Math.max(lines.length, 1);
    const digits = String(lineCount).length;

    gutter.style.minWidth = `${Math.max(2.75, digits * 0.65 + 1.75)}rem`;
    gutter.innerHTML = lines
      .map((_, index) => `<div class="content-editor-html-line-num">${index + 1}</div>`)
      .join('');
  },

  refresh(textarea) {
    if (!textarea) return;

    const text = textarea.value || '';
    const code = textarea._ceHtmlHighlight;

    if (code) {
      code.innerHTML = `${this.highlightHtml(text)}\n`;
    }

    this.updateGutter(textarea, text);
    this.syncScroll(textarea);
  },

  syncScroll(textarea) {
    const gutter = textarea._ceHtmlGutter;
    const panel = textarea.closest('.content-editor-html-panel');
    const highlight = panel ? panel.querySelector('.content-editor-html-highlight') : null;

    if (gutter) {
      gutter.scrollTop = textarea.scrollTop;
    }

    if (highlight) {
      highlight.style.transform = `translate(${-textarea.scrollLeft}px, ${-textarea.scrollTop}px)`;
    }
  },
};
