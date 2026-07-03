// P5.5-M2 — Table visual studio: colgroup, inspector, row min-height handles
window.ContentEditorTableLayout = {
  MIN_ROW_PX: 32,
  STRIPE_BG: '#f9fafb',

  ensureColgroup(table) {
    if (!table || table.tagName !== 'TABLE') return null;
    const firstRow = table.querySelector('tr');
    const colCount = firstRow ? firstRow.cells.length : 0;
    if (!colCount) return null;

    let colgroup = table.querySelector('colgroup');
    if (!colgroup) {
      colgroup = document.createElement('colgroup');
      const tbody = table.querySelector('tbody');
      if (tbody) {
        table.insertBefore(colgroup, tbody);
      } else {
        table.insertBefore(colgroup, table.firstChild);
      }
    }

    const equalPct = `${Math.round((100 / colCount) * 10) / 10}%`;
    while (colgroup.children.length < colCount) {
      const col = document.createElement('col');
      col.style.width = equalPct;
      colgroup.appendChild(col);
    }
    while (colgroup.children.length > colCount) {
      colgroup.lastElementChild.remove();
    }

    if (colgroup.children.length === colCount) {
      const unset = Array.from(colgroup.children).every((col) => !col.style.width);
      if (unset) {
        Array.from(colgroup.children).forEach((col) => {
          col.style.width = equalPct;
        });
      }
    }

    return colgroup;
  },

  enhanceTable(table) {
    if (!table || !table.classList.contains('content-editor-table')) return;
    this.ensureColgroup(table);
    if (!table.style.width) {
      table.style.width = '100%';
    }
    if (!table.style.borderCollapse) {
      table.style.borderCollapse = 'collapse';
    }
  },

  bindEditor(editor) {
    if (!editor || editor.dataset.tableLayoutBound === 'true') return;
    editor.dataset.tableLayoutBound = 'true';

    editor.querySelectorAll('table.content-editor-table').forEach((table) => {
      this.enhanceTable(table);
    });

    document.addEventListener('contenteditor:layout-select', (event) => {
      if (!event.detail || event.detail.editor !== editor) return;
      const layout = event.detail.layout;
      if (!layout || layout.type !== 'table') return;
      this.onTableSelect(editor, layout);
    });

    document.addEventListener('contenteditor:layout-deselect', () => {
      this.onTableDeselect(editor);
    });
  },

  ensureTableSection(wrapper) {
    const panel = wrapper && wrapper.querySelector('.content-editor-layout-inspector');
    if (!panel) return null;

    let section = panel.querySelector('[data-ce-table-section="true"]');
    if (section) return section;

    section = document.createElement('div');
    section.className = 'content-editor-table-inspector-section';
    section.setAttribute('data-ce-table-section', 'true');
    section.innerHTML = `
      <div class="content-editor-layout-inspector-subheader">Table</div>
      <label class="ce-inspector-field">
        <span>Width (%)</span>
        <input type="number" data-ce-table-field="width" min="10" max="100" step="1">
      </label>
      <label class="ce-inspector-field">
        <span>Border collapse</span>
        <select data-ce-table-field="borderCollapse">
          <option value="collapse">Collapse</option>
          <option value="separate">Separate</option>
        </select>
      </label>
      <label class="ce-inspector-field ce-inspector-toggle">
        <span>Header row</span>
        <input type="checkbox" data-ce-table-field="headerRow">
      </label>
      <label class="ce-inspector-field ce-inspector-toggle">
        <span>Striped rows</span>
        <input type="checkbox" data-ce-table-field="striped">
      </label>
      <label class="ce-inspector-field">
        <span>Vertical align (cell)</span>
        <select data-ce-table-field="verticalAlign">
          <option value="">Default</option>
          <option value="top">Top</option>
          <option value="middle">Middle</option>
          <option value="bottom">Bottom</option>
        </select>
      </label>
    `;

    const body = panel.querySelector('.content-editor-layout-inspector-body');
    if (body) {
      body.appendChild(section);
    } else {
      panel.appendChild(section);
    }

    section.querySelectorAll('[data-ce-table-field]').forEach((input) => {
      input.addEventListener('input', () => this.applyTableInspector(editorFromWrapper(wrapper)));
      input.addEventListener('change', () => this.applyTableInspector(editorFromWrapper(wrapper)));
    });

    return section;
  },

  onTableSelect(editor, layout) {
    const wrapper = editor.closest('.content-editor-wrapper');
    const section = wrapper ? this.ensureTableSection(wrapper) : null;
    if (section) {
      section.hidden = false;
      this.syncTableInspector(layout.root, layout.cell);
    }
    this.mountRowHandles(layout.root, editor);
    if (window.ContentEditorLayoutInspector && layout.cell) {
      window.ContentEditorLayoutInspector.syncFieldsFromTarget(layout.cell, layout.root, 'table');
    }
  },

  onTableDeselect(editor) {
    if (!editor) return;
    const wrapper = editor.closest('.content-editor-wrapper');
    if (wrapper) {
      const section = wrapper.querySelector('[data-ce-table-section="true"]');
      if (section) section.hidden = true;
    }
    editor.querySelectorAll('.content-editor-table-row-handles').forEach((el) => el.remove());
  },

  parseTableWidth(table) {
    const w = table.style.width || '';
    if (w.endsWith('%')) return parseInt(w, 10) || 100;
    return 100;
  },

  syncTableInspector(table, cell) {
    const wrapper = table && table.closest('.content-editor-wrapper');
    const section = wrapper && wrapper.querySelector('[data-ce-table-section="true"]');
    if (!section || !table) return;

    const widthField = section.querySelector('[data-ce-table-field="width"]');
    if (widthField) widthField.value = this.parseTableWidth(table);

    const collapseField = section.querySelector('[data-ce-table-field="borderCollapse"]');
    if (collapseField) {
      const cs = window.getComputedStyle(table);
      collapseField.value = (table.style.borderCollapse || cs.borderCollapse || 'collapse').toLowerCase();
    }

    const headerField = section.querySelector('[data-ce-table-field="headerRow"]');
    if (headerField) headerField.checked = this.hasHeaderRow(table);

    const stripeField = section.querySelector('[data-ce-table-field="striped"]');
    if (stripeField) stripeField.checked = this.hasStripes(table);

    const valignField = section.querySelector('[data-ce-table-field="verticalAlign"]');
    if (valignField && cell) {
      const cs = window.getComputedStyle(cell);
      valignField.value = cell.style.verticalAlign || cs.verticalAlign || '';
      if (valignField.value === 'baseline') valignField.value = '';
    } else if (valignField) {
      valignField.value = '';
    }
  },

  applyTableInspector(editor) {
    const block = window.ContentEditorLayoutBlock;
    if (!block || !block.selected || block.selected.type !== 'table') return;

    const table = block.selected.root;
    const cell = block.selected.cell;
    const wrapper = editor && editor.closest('.content-editor-wrapper');
    const section = wrapper && wrapper.querySelector('[data-ce-table-section="true"]');
    if (!section) return;

    const widthField = section.querySelector('[data-ce-table-field="width"]');
    if (widthField) {
      const pct = Math.min(100, Math.max(10, parseInt(widthField.value, 10) || 100));
      table.style.width = `${pct}%`;
    }

    const collapseField = section.querySelector('[data-ce-table-field="borderCollapse"]');
    if (collapseField && collapseField.value) {
      table.style.borderCollapse = collapseField.value;
    }

    const headerField = section.querySelector('[data-ce-table-field="headerRow"]');
    if (headerField) {
      this.setHeaderRow(table, headerField.checked);
    }

    const stripeField = section.querySelector('[data-ce-table-field="striped"]');
    if (stripeField) {
      this.setStripes(table, stripeField.checked);
    }

    const valignField = section.querySelector('[data-ce-table-field="verticalAlign"]');
    if (valignField && cell) {
      cell.style.verticalAlign = valignField.value || '';
    }

    this.syncEditor(editor);
  },

  hasHeaderRow(table) {
    const firstRow = table.querySelector('tr');
    if (!firstRow) return false;
    return firstRow.querySelector('th') !== null;
  },

  setHeaderRow(table, enabled) {
    const firstRow = table.querySelector('tr');
    if (!firstRow) return;

    Array.from(firstRow.cells).forEach((cell) => {
      const tag = enabled ? 'th' : 'td';
      if (cell.tagName.toLowerCase() === tag) return;
      const replacement = document.createElement(tag);
      replacement.innerHTML = cell.innerHTML;
      Array.from(cell.attributes).forEach((attr) => {
        if (attr.name !== 'scope') replacement.setAttribute(attr.name, attr.value);
      });
      if (enabled) replacement.setAttribute('scope', 'col');
      cell.replaceWith(replacement);
    });
  },

  hasStripes(table) {
    const rows = table.querySelectorAll('tbody tr');
    for (let i = 1; i < rows.length; i += 2) {
      const bg = rows[i].style.backgroundColor || window.getComputedStyle(rows[i]).backgroundColor;
      if (bg && bg !== 'rgba(0, 0, 0, 0)' && bg !== 'transparent') return true;
    }
    return false;
  },

  setStripes(table, enabled) {
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach((row, index) => {
      if (enabled && index % 2 === 1) {
        row.style.backgroundColor = this.STRIPE_BG;
      } else {
        row.style.backgroundColor = '';
      }
    });
  },

  mountRowHandles(table, editor) {
    if (!table || !editor) return;
    editor.querySelectorAll('.content-editor-table-row-handles').forEach((el) => el.remove());

    const rows = table.querySelectorAll('tbody tr');
    if (rows.length < 1) return;

    const wrap = document.createElement('div');
    wrap.className = 'content-editor-table-row-handles content-editor-layout-handles';
    wrap.setAttribute('data-ce-handles', 'true');
    wrap.contentEditable = 'false';

    rows.forEach((row, index) => {
      if (index >= rows.length - 1) return;
      const handle = document.createElement('div');
      handle.className = 'content-editor-layout-handle content-editor-layout-handle-row';
      handle.dataset.rowIndex = String(index);
      handle.title = 'Drag to set row min-height';
      handle.addEventListener('pointerdown', (event) => this.startRowDrag(event, table, row, index));
      wrap.appendChild(handle);
    });

    if (wrap.childElementCount > 0) {
      table.style.position = table.style.position || 'relative';
      table.appendChild(wrap);
      this.positionRowHandles(table, wrap);
    }
  },

  positionRowHandles(table, wrap) {
    const rect = table.getBoundingClientRect();
    const tableTop = rect.top;
    const rows = table.querySelectorAll('tbody tr');
    const handles = wrap.querySelectorAll('.content-editor-layout-handle-row');

    handles.forEach((handle, idx) => {
      const row = rows[idx];
      if (!row) return;
      const rowRect = row.getBoundingClientRect();
      const bottom = rowRect.bottom - tableTop;
      handle.style.top = `${bottom}px`;
    });
  },

  rowMinHeightPx(row) {
    const mh = row.style.minHeight || '';
    if (mh.endsWith('px')) return parseFloat(mh);
    const rect = row.getBoundingClientRect();
    return rect.height || this.MIN_ROW_PX;
  },

  startRowDrag(event, table, row, rowIndex) {
    event.preventDefault();
    event.stopPropagation();

    const startY = event.clientY;
    const startMin = this.rowMinHeightPx(row);
    const wrap = table.querySelector('.content-editor-table-row-handles');

    const onMove = (moveEvent) => {
      const delta = moveEvent.clientY - startY;
      const newMin = Math.max(this.MIN_ROW_PX, Math.round(startMin + delta));
      row.style.minHeight = `${newMin}px`;
      Array.from(row.cells).forEach((cell) => {
        cell.style.minHeight = `${newMin}px`;
      });
      if (wrap) this.positionRowHandles(table, wrap);
    };

    const onUp = () => {
      document.removeEventListener('pointermove', onMove);
      document.removeEventListener('pointerup', onUp);
      const editor = table.closest('.content-editor-area');
      this.syncEditor(editor);
    };

    document.addEventListener('pointermove', onMove);
    document.addEventListener('pointerup', onUp);
  },

  syncEditor(editorOrNode) {
    const editor = editorOrNode && editorOrNode.classList
      && editorOrNode.classList.contains('content-editor-area')
      ? editorOrNode
      : (editorOrNode && editorOrNode.closest ? editorOrNode.closest('.content-editor-area') : null);
    if (!editor || !window.ContentEditorSync) return;
    const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
    if (hiddenInput) {
      window.ContentEditorSync.toHidden(editor, hiddenInput, true);
    }
  },
};

function editorFromWrapper(wrapper) {
  if (!wrapper) return null;
  return wrapper.querySelector('.content-editor-area[contenteditable="true"]');
}
