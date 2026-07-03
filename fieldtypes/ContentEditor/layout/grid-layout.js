// P5.5-M3 — Grid visual studio (gap, span, flow, fluid track presets)
window.ContentEditorGridLayout = {
  TRACK_PRESETS: {
    custom: '',
    'equal-2': 'repeat(2, 1fr)',
    'equal-3': 'repeat(3, 1fr)',
    'equal-4': 'repeat(4, 1fr)',
    'auto-fit-200': 'repeat(auto-fit, minmax(200px, 1fr))',
    'auto-fit-250': 'repeat(auto-fit, minmax(250px, 1fr))',
    'auto-fit-300': 'repeat(auto-fit, minmax(300px, 1fr))',
    'auto-fill-200': 'repeat(auto-fill, minmax(200px, 1fr))',
  },

  FLOW_OPTIONS: [
    { value: 'row', label: 'Row' },
    { value: 'column', label: 'Column' },
    { value: 'row dense', label: 'Row dense' },
    { value: 'column dense', label: 'Column dense' },
  ],

  section: null,
  boundEditor: null,

  ensureSection(inspectorPanel) {
    if (this.section && this.section.closest('.content-editor-layout-inspector') === inspectorPanel) {
      return this.section;
    }
    if (this.section) {
      this.section.remove();
      this.section = null;
    }

    const section = document.createElement('div');
    section.className = 'content-editor-grid-inspector-section';
    section.hidden = true;
    section.innerHTML = `
      <div class="ce-inspector-subheading">Grid tracks</div>
      <label class="ce-inspector-field">
        <span>Column tracks</span>
        <select data-ce-grid-field="trackPreset">
          <option value="custom">Custom (current)</option>
          <option value="equal-2">2 equal columns</option>
          <option value="equal-3">3 equal columns</option>
          <option value="equal-4">4 equal columns</option>
          <option value="auto-fit-200">Responsive — min 200px (auto-fit)</option>
          <option value="auto-fit-250">Responsive — min 250px (auto-fit)</option>
          <option value="auto-fit-300">Responsive — min 300px (auto-fit)</option>
          <option value="auto-fill-200">Responsive — min 200px (auto-fill)</option>
        </select>
      </label>
      <label class="ce-inspector-field">
        <span>Auto flow</span>
        <select data-ce-grid-field="autoFlow">
          <option value="row">Row</option>
          <option value="column">Column</option>
          <option value="row dense">Row dense</option>
          <option value="column dense">Column dense</option>
        </select>
      </label>
      <div class="ce-inspector-subheading">Gap</div>
      <label class="ce-inspector-field">
        <span>Gap mode</span>
        <select data-ce-grid-field="gapMode">
          <option value="uniform">Uniform</option>
          <option value="split">Row + column</option>
        </select>
      </label>
      <label class="ce-inspector-field" data-ce-grid-gap-uniform>
        <span>Gap (px)</span>
        <input type="number" data-ce-grid-field="gapUniform" min="0" max="128" step="1">
      </label>
      <div class="ce-inspector-gap-split" data-ce-grid-gap-split hidden>
        <label class="ce-inspector-field">
          <span>Row gap (px)</span>
          <input type="number" data-ce-grid-field="gapRow" min="0" max="128" step="1">
        </label>
        <label class="ce-inspector-field">
          <span>Column gap (px)</span>
          <input type="number" data-ce-grid-field="gapCol" min="0" max="128" step="1">
        </label>
      </div>
      <div class="content-editor-grid-cell-section" data-ce-grid-cell-section hidden>
        <div class="ce-inspector-subheading">Cell span</div>
        <label class="ce-inspector-field">
          <span>Column span</span>
          <input type="number" data-ce-grid-field="colSpan" min="1" max="12" step="1">
        </label>
        <label class="ce-inspector-field">
          <span>Row span</span>
          <input type="number" data-ce-grid-field="rowSpan" min="1" max="12" step="1">
        </label>
      </div>
    `;

    const body = inspectorPanel.querySelector('.content-editor-layout-inspector-body');
    if (body) {
      body.appendChild(section);
    } else {
      inspectorPanel.appendChild(section);
    }

    section.querySelectorAll('[data-ce-grid-field]').forEach((input) => {
      input.addEventListener('input', () => this.applyFromPanel());
      input.addEventListener('change', () => this.applyFromPanel());
    });

    section.querySelector('[data-ce-grid-field="gapMode"]').addEventListener('change', () => {
      this.toggleGapModeFields();
      this.applyFromPanel();
    });

    this.section = section;
    return section;
  },

  bind(wrapper, editor) {
    if (!wrapper || wrapper.dataset.gridLayoutBound === 'true') return;
    wrapper.dataset.gridLayoutBound = 'true';
    this.boundEditor = editor;

    const inspector = window.ContentEditorLayoutInspector;
    const panel = inspector && inspector.ensurePanel ? inspector.ensurePanel(wrapper) : null;
    if (panel) {
      this.ensureSection(panel);
    }

    document.addEventListener('contenteditor:layout-select', (event) => {
      if (!event.detail || event.detail.editor !== editor) return;
      if (event.detail.layout && event.detail.layout.type === 'grid') {
        this.show(event.detail.layout);
      } else {
        this.hide();
      }
    });

    document.addEventListener('contenteditor:layout-deselect', () => {
      this.hide();
    });
  },

  show(layout) {
    const inspector = window.ContentEditorLayoutInspector;
    const panel = inspector && inspector.panel;
    if (!panel || !layout || !layout.root) return;

    const section = this.ensureSection(panel);
    section.hidden = false;
    panel.dataset.layoutType = 'grid';

    const genericGap = panel.querySelector('[data-ce-field="gap"]');
    if (genericGap) {
      const genericGapWrap = genericGap.closest('.ce-inspector-field');
      if (genericGapWrap) genericGapWrap.hidden = true;
    }

    this.syncFromLayout(layout);
  },

  hide() {
    if (this.section) {
      this.section.hidden = true;
    }
    const inspector = window.ContentEditorLayoutInspector;
    const panel = inspector && inspector.panel;
    if (panel) {
      const genericGap = panel.querySelector('[data-ce-field="gap"]');
      if (genericGap) {
        const genericGapWrap = genericGap.closest('.ce-inspector-field');
        if (genericGapWrap) genericGapWrap.hidden = false;
      }
    }
  },

  toggleGapModeFields() {
    if (!this.section) return;
    const mode = this.section.querySelector('[data-ce-grid-field="gapMode"]').value;
    const uniformWrap = this.section.querySelector('[data-ce-grid-gap-uniform]');
    const splitWrap = this.section.querySelector('[data-ce-grid-gap-split]');
    if (uniformWrap) uniformWrap.hidden = mode !== 'uniform';
    if (splitWrap) splitWrap.hidden = mode !== 'split';
  },

  parsePx(value, fallback = 0) {
    if (value == null || value === '') return fallback;
    const n = parseInt(String(value).replace(/px$/i, ''), 10);
    return Number.isFinite(n) ? n : fallback;
  },

  parseSpan(styleValue) {
    if (!styleValue) return 1;
    const match = String(styleValue).match(/span\s+(\d+)/i);
    if (match) return Math.max(1, parseInt(match[1], 10));
    return 1;
  },

  detectTrackPreset(columnsValue) {
    if (!columnsValue) return 'custom';
    const normalized = columnsValue.replace(/\s+/g, ' ').trim();
    for (const [key, preset] of Object.entries(this.TRACK_PRESETS)) {
      if (key === 'custom') continue;
      if (normalized === preset.replace(/\s+/g, ' ').trim()) return key;
    }
    return 'custom';
  },

  syncFromLayout(layout) {
    if (!this.section || !layout.root) return;
    const root = layout.root;
    const rootCs = window.getComputedStyle(root);
    const columnsValue = root.style.gridTemplateColumns || rootCs.gridTemplateColumns || '';

    const trackField = this.section.querySelector('[data-ce-grid-field="trackPreset"]');
    if (trackField) {
      trackField.value = this.detectTrackPreset(columnsValue);
    }

    const flowField = this.section.querySelector('[data-ce-grid-field="autoFlow"]');
    if (flowField) {
      const flow = (root.style.gridAutoFlow || rootCs.gridAutoFlow || 'row').trim();
      flowField.value = this.FLOW_OPTIONS.some((o) => o.value === flow) ? flow : 'row';
    }

    const rowGapPx = this.parsePx(root.style.rowGap || rootCs.rowGap, 16);
    const colGapPx = this.parsePx(root.style.columnGap || rootCs.columnGap, rowGapPx);
    const uniformGap = rowGapPx === colGapPx;

    const gapModeField = this.section.querySelector('[data-ce-grid-field="gapMode"]');
    if (gapModeField) {
      gapModeField.value = uniformGap ? 'uniform' : 'split';
    }
    this.toggleGapModeFields();

    const gapUniformField = this.section.querySelector('[data-ce-grid-field="gapUniform"]');
    if (gapUniformField) gapUniformField.value = rowGapPx;

    const gapRowField = this.section.querySelector('[data-ce-grid-field="gapRow"]');
    if (gapRowField) gapRowField.value = rowGapPx;

    const gapColField = this.section.querySelector('[data-ce-grid-field="gapCol"]');
    if (gapColField) gapColField.value = colGapPx;

    const cellSection = this.section.querySelector('[data-ce-grid-cell-section]');
    const cell = layout.cell;
    if (cellSection) {
      if (cell && root.contains(cell)) {
        cellSection.hidden = false;
        const colSpanField = this.section.querySelector('[data-ce-grid-field="colSpan"]');
        const rowSpanField = this.section.querySelector('[data-ce-grid-field="rowSpan"]');
        if (colSpanField) {
          colSpanField.value = this.parseSpan(cell.style.gridColumn || window.getComputedStyle(cell).gridColumn);
        }
        if (rowSpanField) {
          rowSpanField.value = this.parseSpan(cell.style.gridRow || window.getComputedStyle(cell).gridRow);
        }
      } else {
        cellSection.hidden = true;
      }
    }
  },

  applyGap(root, mode, uniform, row, col) {
    if (!root) return;
    if (mode === 'split') {
      const rowPx = `${Math.max(0, parseInt(row, 10) || 0)}px`;
      const colPx = `${Math.max(0, parseInt(col, 10) || 0)}px`;
      root.style.gap = '';
      root.style.rowGap = rowPx;
      root.style.columnGap = colPx;
    } else {
      const gapPx = `${Math.max(0, parseInt(uniform, 10) || 0)}px`;
      root.style.gap = gapPx;
      root.style.rowGap = gapPx;
      root.style.columnGap = gapPx;
    }
  },

  applySpan(cell, colSpan, rowSpan) {
    if (!cell) return;
    const cs = Math.max(1, parseInt(colSpan, 10) || 1);
    const rs = Math.max(1, parseInt(rowSpan, 10) || 1);
    cell.style.gridColumn = cs > 1 ? `span ${cs}` : '';
    cell.style.gridRow = rs > 1 ? `span ${rs}` : '';
  },

  applyFromPanel() {
    const block = window.ContentEditorLayoutBlock;
    if (!block || !block.selected || block.selected.type !== 'grid' || !this.section) return;

    const layout = block.selected;
    const root = layout.root;

    const trackPreset = this.section.querySelector('[data-ce-grid-field="trackPreset"]');
    if (trackPreset && trackPreset.value !== 'custom') {
      const preset = this.TRACK_PRESETS[trackPreset.value];
      if (preset) {
        root.style.gridTemplateColumns = preset;
      }
    }

    const autoFlow = this.section.querySelector('[data-ce-grid-field="autoFlow"]');
    if (autoFlow) {
      root.style.gridAutoFlow = autoFlow.value || 'row';
    }

    const gapMode = this.section.querySelector('[data-ce-grid-field="gapMode"]');
    const gapUniform = this.section.querySelector('[data-ce-grid-field="gapUniform"]');
    const gapRow = this.section.querySelector('[data-ce-grid-field="gapRow"]');
    const gapCol = this.section.querySelector('[data-ce-grid-field="gapCol"]');
    this.applyGap(
      root,
      gapMode ? gapMode.value : 'uniform',
      gapUniform ? gapUniform.value : 16,
      gapRow ? gapRow.value : 16,
      gapCol ? gapCol.value : 16
    );

    if (layout.cell) {
      const colSpan = this.section.querySelector('[data-ce-grid-field="colSpan"]');
      const rowSpan = this.section.querySelector('[data-ce-grid-field="rowSpan"]');
      this.applySpan(layout.cell, colSpan ? colSpan.value : 1, rowSpan ? rowSpan.value : 1);
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
