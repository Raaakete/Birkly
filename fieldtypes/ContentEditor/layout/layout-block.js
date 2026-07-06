// P5.5-M1 — Layout block selection, hover chrome, serialize helpers
window.ContentEditorLayoutBlock = {
  LAYOUT_ROOT_SELECTOR: 'table.content-editor-table, div.content-editor-grid',
  selected: null,
  activeEditor: null,

  isLayoutRoot(element) {
    if (!element || element.nodeType !== Node.ELEMENT_NODE) return false;
    return element.matches(this.LAYOUT_ROOT_SELECTOR);
  },

  findLayoutRoot(element) {
    if (!element) return null;
    const node = element.nodeType === Node.TEXT_NODE ? element.parentElement : element;
    return node && node.closest ? node.closest(this.LAYOUT_ROOT_SELECTOR) : null;
  },

  findActiveCell(element, layoutRoot) {
    if (!element || !layoutRoot) return null;
    const node = element.nodeType === Node.TEXT_NODE ? element.parentElement : element;
    if (!node || !layoutRoot.contains(node)) return null;
    if (layoutRoot.tagName === 'TABLE') {
      return node.closest('td, th');
    }
    if (layoutRoot.classList.contains('content-editor-grid')) {
      return node.closest('.content-editor-grid-item') || node.closest('.content-editor-grid > div');
    }
    return null;
  },

  assignLayoutId(root) {
    if (!root) return '';
    if (!root.dataset.ceLayoutId) {
      root.dataset.ceLayoutId = 'ce-layout-' + Math.random().toString(36).slice(2, 10);
    }
    return root.dataset.ceLayoutId;
  },

  clearSelectionClasses(editor) {
    if (!editor) return;
    editor.querySelectorAll('[data-ce-selected="true"]').forEach((el) => {
      el.removeAttribute('data-ce-selected');
      el.classList.remove('content-editor-layout-selected');
    });
  },

  select(layoutRoot, editor, activeCell) {
    if (!layoutRoot || !editor) return;
    this.deselect();
    this.activeEditor = editor;
    this.selected = {
      root: layoutRoot,
      cell: activeCell && layoutRoot.contains(activeCell) ? activeCell : null,
      type: layoutRoot.classList.contains('content-editor-grid') ? 'grid' : 'table',
    };
    this.assignLayoutId(layoutRoot);
    layoutRoot.setAttribute('data-ce-selected', 'true');
    layoutRoot.classList.add('content-editor-layout-selected');
    if (this.selected.cell) {
      this.selected.cell.setAttribute('data-ce-selected', 'true');
      this.selected.cell.classList.add('content-editor-layout-cell-selected');
    }
    document.dispatchEvent(new CustomEvent('contenteditor:layout-select', {
      detail: { layout: this.selected, editor },
    }));
  },

  deselect() {
    if (this.selected && this.selected.root) {
      this.selected.root.removeAttribute('data-ce-selected');
      this.selected.root.classList.remove('content-editor-layout-selected');
    }
    if (this.selected && this.selected.cell) {
      this.selected.cell.removeAttribute('data-ce-selected');
      this.selected.cell.classList.remove('content-editor-layout-cell-selected');
    }
    this.selected = null;
    document.dispatchEvent(new CustomEvent('contenteditor:layout-deselect'));
  },

  getStyleTarget() {
    if (!this.selected) return null;
    return this.selected.cell || this.selected.root;
  },

  enhanceEditor(editor) {
    if (!editor || editor.dataset.layoutBlockBound === 'true') return;
    editor.dataset.layoutBlockBound = 'true';

    editor.addEventListener('click', (event) => {
      const root = this.findLayoutRoot(event.target);
      if (root && editor.contains(root)) {
        event.stopPropagation();
        const cell = this.findActiveCell(event.target, root);
        this.select(root, editor, cell);
        return;
      }
      if (!event.target.closest('.content-editor-layout-inspector')) {
        this.deselect();
      }
    });

    editor.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        this.deselect();
      }
    });
  },

  /**
   * Strip editor-only layout metadata from HTML string (shared with sync-manager).
   */
  stripEditorMetadata(html) {
    if (!html) return '';
    let out = html;

    out = out.replace(/<span[^>]*data-ce-insert-marker="true"[^>]*>\s*<\/span>/gi, '');
    out = out.replace(/<div[^>]*class="[^"]*content-editor-layout-handles[^"]*"[^>]*>[\s\S]*?<\/div>/gi, '');
    out = out.replace(/<div[^>]*class="[^"]*content-editor-grid-controls[^"]*"[^>]*>[\s\S]*?<\/div>/gi, '');
    out = out.replace(/<div[^>]*class="[^"]*content-editor-table-controls[^"]*"[^>]*>[\s\S]*?<\/div>/gi, '');

    out = out.replace(/\s*data-ce-[a-z0-9-]+="[^"]*"/gi, '');
    out = out.replace(/\s*data-ce-[a-z0-9-]+='[^']*'/gi, '');
    out = out.replace(/\s*data-editable-table="[^"]*"/gi, '');
    out = out.replace(/\s*data-block-type="[^"]*"/gi, '');
    out = out.replace(/\s*contenteditable="(true|false)"/gi, '');

    out = out.replace(/\s*class="[^"]*content-editor-layout-selected[^"]*"/gi, (match) => {
      const cleaned = match.replace(/content-editor-layout-selected/g, '').replace(/\s+/g, ' ').trim();
      return cleaned === 'class=""' ? '' : cleaned;
    });
    out = out.replace(/\s*class="[^"]*content-editor-layout-cell-selected[^"]*"/gi, (match) => {
      const cleaned = match.replace(/content-editor-layout-cell-selected/g, '').replace(/\s+/g, ' ').trim();
      return cleaned === 'class=""' ? '' : cleaned;
    });

    // P8-M5 — multi-cell selection highlight (native colspan/rowspan is preserved).
    out = out.replace(/content-editor-table-cell-selected\s*/gi, '');
    out = out.replace(/\s*class="\s*"/gi, '');

    return out;
  },
};
