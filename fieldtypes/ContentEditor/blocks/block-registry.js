// ContentEditor block type registry (P3-M12)
window.ContentEditorBlockRegistry = {
  _blocks: new Map(),

  /**
   * @param {{type:string, tags?:string[], matches?:(el:Element)=>boolean, create?:(opts?:object)=>Element|Element[], serialize?:(el:Element)=>string}} definition
   */
  register(definition) {
    if (!definition || !definition.type) {
      console.warn('ContentEditorBlockRegistry: block definition requires type');
      return;
    }
    this._blocks.set(definition.type, definition);
  },

  get(type) {
    return this._blocks.get(type) || null;
  },

  all() {
    return Array.from(this._blocks.values());
  },

  /**
   * Map a block tag name (p, h1, ul, …) to a registered block type.
   */
  tagToType(tagName) {
    const tag = (tagName || '').toLowerCase();
    for (const def of this._blocks.values()) {
      if (def.tags && def.tags.includes(tag)) {
        return def.type;
      }
    }
    if (tag === 'li') {
      return 'list-item';
    }
    return null;
  },

  /**
   * Infer block type from a DOM element.
   */
  inferType(element) {
    if (!element || element.nodeType !== Node.ELEMENT_NODE) {
      return null;
    }
    for (const def of this._blocks.values()) {
      if (typeof def.matches === 'function' && def.matches(element)) {
        return def.type;
      }
    }
    return null;
  },

  detect(element) {
    const type = this.inferType(element);
    return type ? this.get(type) : null;
  },

  /**
   * Create DOM for a block type.
   * @returns {Element|Element[]|null}
   */
  create(type, options = {}) {
    const def = this.get(type);
    if (!def || typeof def.create !== 'function') {
      console.warn('ContentEditorBlockRegistry: unknown block type:', type);
      return null;
    }
    return def.create(options);
  },

  serialize(element) {
    const def = this.detect(element);
    if (def && typeof def.serialize === 'function') {
      return def.serialize(element);
    }
    return element ? element.outerHTML : '';
  },
};

(function registerBuiltinBlocks() {
  const registry = window.ContentEditorBlockRegistry;
  if (!registry) return;

  const emptyParagraph = () => {
    const p = document.createElement('p');
    p.innerHTML = '<br>';
    return p;
  };

  registry.register({
    type: 'paragraph',
    tags: ['p'],
    matches(el) {
      return el.tagName === 'P' && !el.querySelector('table');
    },
    create() {
      return emptyParagraph();
    },
    serialize(el) {
      return el.outerHTML;
    },
  });

  registry.register({
    type: 'heading',
    tags: ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
    matches(el) {
      return /^H[1-6]$/.test(el.tagName);
    },
    create(opts = {}) {
      const level = Math.min(6, Math.max(1, parseInt(opts.level, 10) || 2));
      const el = document.createElement('h' + level);
      el.innerHTML = opts.content || '<br>';
      return el;
    },
    serialize(el) {
      return el.outerHTML;
    },
  });

  registry.register({
    type: 'list-unordered',
    tags: ['ul'],
    matches(el) {
      return el.tagName === 'UL';
    },
    create(opts = {}) {
      const ul = document.createElement('ul');
      const li = document.createElement('li');
      li.innerHTML = opts.content || '<br>';
      ul.appendChild(li);
      return ul;
    },
    serialize(el) {
      return el.outerHTML;
    },
  });

  registry.register({
    type: 'list-ordered',
    tags: ['ol'],
    matches(el) {
      return el.tagName === 'OL';
    },
    create(opts = {}) {
      const ol = document.createElement('ol');
      const li = document.createElement('li');
      li.innerHTML = opts.content || '<br>';
      ol.appendChild(li);
      return ol;
    },
    serialize(el) {
      return el.outerHTML;
    },
  });

  registry.register({
    type: 'list-item',
    tags: ['li'],
    matches(el) {
      return el.tagName === 'LI';
    },
    create(opts = {}) {
      const li = document.createElement('li');
      li.innerHTML = opts.content || '<br>';
      return li;
    },
    serialize(el) {
      return el.outerHTML;
    },
  });

  registry.register({
    type: 'divider',
    tags: ['hr'],
    matches(el) {
      return el.tagName === 'HR';
    },
    create() {
      const hr = document.createElement('hr');
      hr.className = 'content-editor-divider';
      return hr;
    },
    serialize(el) {
      return el.outerHTML;
    },
  });

  registry.register({
    type: 'table',
    tags: ['table'],
    matches(el) {
      return el.tagName === 'TABLE';
    },
    create(opts = {}) {
      const rows = Math.min(20, Math.max(1, parseInt(opts.rows, 10) || 3));
      const cols = Math.min(20, Math.max(1, parseInt(opts.cols, 10) || 3));
      const table = document.createElement('table');
      table.className = 'content-editor-table';
      table.setAttribute('data-editable-table', 'true');

      const tbody = document.createElement('tbody');
      for (let i = 0; i < rows; i++) {
        const tr = document.createElement('tr');
        for (let j = 0; j < cols; j++) {
          const td = document.createElement('td');
          td.innerHTML = '<br>';
          tr.appendChild(td);
        }
        tbody.appendChild(tr);
      }
      table.appendChild(tbody);

      const wrapperP = document.createElement('p');
      wrapperP.appendChild(table);
      return wrapperP;
    },
    serialize(el) {
      const table = el.tagName === 'TABLE' ? el : el.querySelector('table');
      if (table && table.parentElement && table.parentElement.tagName === 'P' && table.parentElement.childNodes.length === 1) {
        return table.parentElement.outerHTML;
      }
      return el.outerHTML;
    },
  });
})();
