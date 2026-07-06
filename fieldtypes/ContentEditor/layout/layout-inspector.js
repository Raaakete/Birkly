// P5.5-M1 — Floating layout inspector panel (gap, padding, bg, border, radius)
window.ContentEditorLayoutInspector = {
  panel: null,
  boundEditor: null,

  ensurePanel(wrapper) {
    if (this.panel && this.panel.closest('.content-editor-wrapper') === wrapper) {
      return this.panel;
    }
    if (this.panel) {
      this.panel.remove();
      this.panel = null;
    }

    const panel = document.createElement('div');
    panel.className = 'content-editor-layout-inspector';
    panel.setAttribute('data-ce-inspector', 'true');
    panel.hidden = true;
    panel.innerHTML = `
      <div class="content-editor-layout-inspector-header">
        <strong class="content-editor-layout-inspector-title">Layout</strong>
        <button type="button" class="content-editor-layout-inspector-close" aria-label="Close inspector">×</button>
      </div>
      <div class="content-editor-layout-inspector-body">
        <label class="ce-inspector-field">
          <span>Gap (px)</span>
          <input type="number" data-ce-field="gap" min="0" max="128" step="1">
        </label>
        <label class="ce-inspector-field">
          <span>Padding (px)</span>
          <input type="number" data-ce-field="padding" min="0" max="128" step="1">
        </label>
        <label class="ce-inspector-field">
          <span>Background</span>
          <input type="color" data-ce-field="background">
        </label>
        <label class="ce-inspector-field">
          <span>Border (px)</span>
          <input type="number" data-ce-field="borderWidth" min="0" max="16" step="1">
        </label>
        <label class="ce-inspector-field">
          <span>Border color</span>
          <input type="color" data-ce-field="borderColor">
        </label>
        <label class="ce-inspector-field">
          <span>Radius (px)</span>
          <input type="number" data-ce-field="borderRadius" min="0" max="64" step="1">
        </label>
      </div>
      <div class="content-editor-table-merge-controls" data-ce-merge-controls hidden>
        <div class="content-editor-layout-inspector-subheader">Cells</div>
        <div class="content-editor-merge-actions">
          <button type="button" class="content-editor-merge-btn" data-ce-table-action="merge" disabled title="Merge selected cells">Merge cells</button>
          <button type="button" class="content-editor-merge-btn" data-ce-table-action="split" disabled title="Split cell into grid">Split cell</button>
        </div>
        <p class="content-editor-merge-hint">Drag across cells to select, then merge.</p>
      </div>
    `;

    panel.querySelectorAll('[data-ce-table-action]').forEach((btn) => {
      btn.addEventListener('click', (event) => {
        event.preventDefault();
        const table = window.ContentEditorTableLayout;
        if (!table) return;
        const editor = window.ContentEditorLayoutBlock ? window.ContentEditorLayoutBlock.activeEditor : null;
        if (btn.dataset.ceTableAction === 'merge') {
          table.mergeSelectedCells(editor);
        } else if (btn.dataset.ceTableAction === 'split') {
          table.splitSelectedCell(editor);
        }
      });
    });

    panel.querySelector('.content-editor-layout-inspector-close').addEventListener('click', () => {
      if (window.ContentEditorLayoutBlock) {
        window.ContentEditorLayoutBlock.deselect();
      }
      this.hide();
    });

    panel.querySelectorAll('[data-ce-field]').forEach((input) => {
      input.addEventListener('input', () => this.applyFromPanel());
      input.addEventListener('change', () => this.applyFromPanel());
    });

    wrapper.appendChild(panel);
    this.panel = panel;
    return panel;
  },

  bind(wrapper, editor) {
    if (!wrapper || wrapper.dataset.layoutInspectorBound === 'true') return;
    wrapper.dataset.layoutInspectorBound = 'true';
    this.boundEditor = editor;
    this.ensurePanel(wrapper);

    document.addEventListener('contenteditor:layout-select', (event) => {
      if (event.detail && event.detail.editor === editor) {
        this.show(wrapper, event.detail.layout);
      }
    });

    document.addEventListener('contenteditor:layout-deselect', () => {
      this.hide();
    });
  },

  show(wrapper, layout) {
    const panel = this.ensurePanel(wrapper);
    panel.hidden = false;
    panel.dataset.layoutType = layout.type || 'table';

    const tableSection = panel.querySelector('[data-ce-table-section="true"]');
    if (tableSection) {
      tableSection.hidden = layout.type !== 'table';
    }

    const mergeControls = panel.querySelector('[data-ce-merge-controls]');
    if (mergeControls) {
      mergeControls.hidden = layout.type !== 'table';
    }

    const target = layout.cell || layout.root;
    this.syncFieldsFromTarget(target, layout.root, layout.type);

    if (layout.type === 'table' && window.ContentEditorTableLayout) {
      window.ContentEditorTableLayout.syncTableInspector(layout.root, layout.cell);
      window.ContentEditorTableLayout.refreshMergeControls();
    }
  },

  hide() {
    if (this.panel) {
      this.panel.hidden = true;
    }
  },

  parsePx(value, fallback = 0) {
    if (value == null || value === '') return fallback;
    const n = parseInt(String(value).replace(/px$/i, ''), 10);
    return Number.isFinite(n) ? n : fallback;
  },

  rgbToHex(color) {
    if (!color || color === 'transparent') return '#ffffff';
    if (color.startsWith('#')) return color.length === 4
      ? '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3]
      : color.slice(0, 7);
    const m = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
    if (!m) return '#ffffff';
    const hex = (n) => ('0' + parseInt(n, 10).toString(16)).slice(-2);
    return '#' + hex(m[1]) + hex(m[2]) + hex(m[3]);
  },

  syncFieldsFromTarget(target, root, type) {
    if (!this.panel || !target) return;
    const cs = window.getComputedStyle(target);
    const rootCs = root ? window.getComputedStyle(root) : cs;

    const gapField = this.panel.querySelector('[data-ce-field="gap"]');
    if (gapField) {
      if (type === 'grid') {
        gapField.disabled = false;
        gapField.value = this.parsePx(rootCs.gap || rootCs.columnGap, 16);
      } else {
        gapField.disabled = true;
        gapField.value = '';
      }
    }

    const paddingField = this.panel.querySelector('[data-ce-field="padding"]');
    if (paddingField) {
      paddingField.value = this.parsePx(cs.paddingTop, 12);
    }

    const bgField = this.panel.querySelector('[data-ce-field="background"]');
    if (bgField) {
      bgField.value = this.rgbToHex(cs.backgroundColor);
    }

    const borderWidthField = this.panel.querySelector('[data-ce-field="borderWidth"]');
    if (borderWidthField) {
      borderWidthField.value = this.parsePx(cs.borderTopWidth, 0);
    }

    const borderColorField = this.panel.querySelector('[data-ce-field="borderColor"]');
    if (borderColorField) {
      borderColorField.value = this.rgbToHex(cs.borderTopColor || '#e5e7eb');
    }

    const radiusField = this.panel.querySelector('[data-ce-field="borderRadius"]');
    if (radiusField) {
      radiusField.value = this.parsePx(cs.borderTopLeftRadius, 0);
    }
  },

  applyFromPanel() {
    const block = window.ContentEditorLayoutBlock;
    if (!block || !block.selected || !this.panel) return;

    const layout = block.selected;
    const target = layout.cell || layout.root;
    const gap = this.panel.querySelector('[data-ce-field="gap"]');
    const padding = this.panel.querySelector('[data-ce-field="padding"]');
    const background = this.panel.querySelector('[data-ce-field="background"]');
    const borderWidth = this.panel.querySelector('[data-ce-field="borderWidth"]');
    const borderColor = this.panel.querySelector('[data-ce-field="borderColor"]');
    const borderRadius = this.panel.querySelector('[data-ce-field="borderRadius"]');

    const gridLayoutHandlesGap = layout.type === 'grid'
      && window.ContentEditorGridLayout
      && window.ContentEditorGridLayout.section
      && !window.ContentEditorGridLayout.section.hidden;
    if (layout.type === 'grid' && gap && !gap.disabled && layout.root && !gridLayoutHandlesGap) {
      const gapPx = `${Math.max(0, parseInt(gap.value, 10) || 0)}px`;
      layout.root.style.gap = gapPx;
      layout.root.style.rowGap = gapPx;
      layout.root.style.columnGap = gapPx;
    }

    if (padding) {
      const padPx = `${Math.max(0, parseInt(padding.value, 10) || 0)}px`;
      target.style.padding = padPx;
    }
    if (background && background.value) {
      target.style.backgroundColor = background.value;
    }
    if (borderWidth && borderColor) {
      const w = Math.max(0, parseInt(borderWidth.value, 10) || 0);
      if (w === 0) {
        target.style.border = 'none';
      } else {
        target.style.border = `${w}px solid ${borderColor.value || '#e5e7eb'}`;
      }
    }
    if (borderRadius) {
      target.style.borderRadius = `${Math.max(0, parseInt(borderRadius.value, 10) || 0)}px`;
    }

    this.syncEditor(block.activeEditor);
  },

  syncEditor(editor) {
    if (!editor || !window.ContentEditorSync) return;
    const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
    if (hiddenInput) {
      window.ContentEditorSync.toHidden(editor, hiddenInput, true);
    }
  },
};
