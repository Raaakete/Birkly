// P5.5-M2 — Table visual studio: colgroup, inspector, row min-height handles
// P8-M5 — Multi-cell select + merge/split (native colspan/rowspan)
window.ContentEditorTableLayout = {
  MIN_ROW_PX: 32,
  STRIPE_BG: '#f9fafb',
  CELL_SELECTED_CLASS: 'content-editor-table-cell-selected',

  // Multi-cell selection state (P8-M5)
  mergeSelection: [],
  _dragAnchor: null,
  _dragTable: null,
  _dragMoved: false,
  _suppressNextClick: false,

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

    // P8-M5 — pointer drag across cells selects a rectangular region for merge.
    editor.addEventListener('pointerdown', (event) => this.onCellPointerDown(event, editor));
    // Capture phase: swallow the click that follows a multi-cell drag so the
    // layout-block click handler does not collapse the selection back to one cell.
    editor.addEventListener('click', (event) => {
      if (this._suppressNextClick) {
        this._suppressNextClick = false;
        event.stopImmediatePropagation();
        event.preventDefault();
      }
    }, true);

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
    this.refreshMergeControls();
  },

  onTableDeselect(editor) {
    if (!editor) return;
    const wrapper = editor.closest('.content-editor-wrapper');
    if (wrapper) {
      const section = wrapper.querySelector('[data-ce-table-section="true"]');
      if (section) section.hidden = true;
    }
    editor.querySelectorAll('.content-editor-table-row-handles').forEach((el) => el.remove());
    this.clearMergeSelection(editor);
    this.refreshMergeControls();
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

  // ---- P8-M5: multi-cell selection + merge/split ----------------------------

  /**
   * Build a logical grid model of the table, honouring existing colspan/rowspan.
   * Returns { rows: [<tr>], matrix: cell[row][col], cellMap: Map(cell -> rect) }
   * where rect = { top, left, bottom, right } are inclusive grid coordinates.
   */
  buildTableGrid(table) {
    const rows = Array.from(table.querySelectorAll('tr'));
    const matrix = [];
    const cellMap = new Map();

    rows.forEach((tr, r) => {
      if (!matrix[r]) matrix[r] = [];
      let c = 0;
      Array.from(tr.children).forEach((cell) => {
        if (cell.tagName !== 'TD' && cell.tagName !== 'TH') return;
        while (matrix[r][c] !== undefined) c += 1;
        const colspan = Math.max(1, cell.colSpan || 1);
        const rowspan = Math.max(1, cell.rowSpan || 1);
        for (let dr = 0; dr < rowspan; dr += 1) {
          const rr = r + dr;
          if (!matrix[rr]) matrix[rr] = [];
          for (let dc = 0; dc < colspan; dc += 1) {
            matrix[rr][c + dc] = cell;
          }
        }
        cellMap.set(cell, { top: r, left: c, bottom: r + rowspan - 1, right: c + colspan - 1 });
        c += colspan;
      });
    });

    return { rows, matrix, cellMap };
  },

  /**
   * Rectangular set of cells spanning from anchorCell to focusCell, expanded so
   * that any cell whose span intersects the rectangle is fully contained.
   */
  computeSelectionCells(table, anchorCell, focusCell) {
    const grid = this.buildTableGrid(table);
    const a = grid.cellMap.get(anchorCell);
    const f = grid.cellMap.get(focusCell);
    if (!a || !f) return { cells: [], grid };

    let top = Math.min(a.top, f.top);
    let left = Math.min(a.left, f.left);
    let bottom = Math.max(a.bottom, f.bottom);
    let right = Math.max(a.right, f.right);

    let changed = true;
    while (changed) {
      changed = false;
      grid.cellMap.forEach((rect) => {
        const intersects = !(rect.right < left || rect.left > right || rect.bottom < top || rect.top > bottom);
        if (!intersects) return;
        if (rect.top < top) { top = rect.top; changed = true; }
        if (rect.left < left) { left = rect.left; changed = true; }
        if (rect.bottom > bottom) { bottom = rect.bottom; changed = true; }
        if (rect.right > right) { right = rect.right; changed = true; }
      });
    }

    const cells = [];
    grid.cellMap.forEach((rect, cell) => {
      if (rect.top >= top && rect.left >= left && rect.bottom <= bottom && rect.right <= right) {
        cells.push(cell);
      }
    });

    return { cells, grid, rect: { top, left, bottom, right } };
  },

  setMergeSelection(cells) {
    this.clearMergeSelection();
    this.mergeSelection = Array.isArray(cells) ? cells.slice() : [];
    this.mergeSelection.forEach((cell) => {
      cell.classList.add(this.CELL_SELECTED_CLASS);
      cell.setAttribute('data-ce-cell-selected', 'true');
    });
    this.refreshMergeControls();
  },

  clearMergeSelection(editor) {
    (this.mergeSelection || []).forEach((cell) => {
      cell.classList.remove(this.CELL_SELECTED_CLASS);
      cell.removeAttribute('data-ce-cell-selected');
    });
    this.mergeSelection = [];
    // Sweep any stragglers (e.g. after DOM reflow).
    const scope = editor || document;
    scope.querySelectorAll('.' + this.CELL_SELECTED_CLASS).forEach((cell) => {
      cell.classList.remove(this.CELL_SELECTED_CLASS);
      cell.removeAttribute('data-ce-cell-selected');
    });
  },

  onCellPointerDown(event, editor) {
    if (event.button !== 0) return;
    const node = event.target;
    const cell = node && node.closest ? node.closest('td, th') : null;
    if (!cell) {
      this.clearMergeSelection(editor);
      this.refreshMergeControls();
      return;
    }
    const table = cell.closest('table.content-editor-table');
    if (!table || !editor.contains(table)) return;

    // A fresh interaction clears any previous multi-cell selection.
    this.clearMergeSelection(editor);
    this._dragAnchor = cell;
    this._dragTable = table;
    this._dragMoved = false;

    const onMove = (moveEvent) => {
      const overNode = document.elementFromPoint(moveEvent.clientX, moveEvent.clientY);
      const overCell = overNode && overNode.closest ? overNode.closest('td, th') : null;
      if (!overCell || !table.contains(overCell)) return;
      if (overCell !== this._dragAnchor) this._dragMoved = true;
      if (!this._dragMoved) return;
      moveEvent.preventDefault();
      const result = this.computeSelectionCells(table, this._dragAnchor, overCell);
      this.setMergeSelection(result.cells);
    };

    const onUp = () => {
      document.removeEventListener('pointermove', onMove);
      document.removeEventListener('pointerup', onUp);
      if (this._dragMoved && this.mergeSelection.length >= 2) {
        this._suppressNextClick = true;
        // Capture before select(): its deselect() clears the highlight via onTableDeselect.
        const backup = this.mergeSelection.slice();
        if (window.ContentEditorLayoutBlock) {
          window.ContentEditorLayoutBlock.select(table, editor, this._dragAnchor);
        }
        const sel = window.getSelection();
        if (sel) sel.removeAllRanges();
        this.setMergeSelection(backup);
      }
      this._dragAnchor = null;
      this._dragTable = null;
      this._dragMoved = false;
      this.refreshMergeControls();
    };

    document.addEventListener('pointermove', onMove);
    document.addEventListener('pointerup', onUp);
  },

  getSplitTarget() {
    if (this.mergeSelection.length === 1) return this.mergeSelection[0];
    const block = window.ContentEditorLayoutBlock;
    if (block && block.selected && block.selected.type === 'table' && block.selected.cell) {
      return block.selected.cell;
    }
    return null;
  },

  canMerge() {
    return this.mergeSelection.length >= 2;
  },

  canSplit() {
    const target = this.getSplitTarget();
    return !!(target && ((target.colSpan || 1) > 1 || (target.rowSpan || 1) > 1));
  },

  createEmptyCell(tag) {
    const cell = document.createElement(tag === 'th' ? 'th' : 'td');
    cell.innerHTML = '<br>';
    return cell;
  },

  mergeSelectedCells(editor) {
    const cells = (this.mergeSelection || []).slice();
    if (cells.length < 2) return false;
    const table = cells[0].closest('table.content-editor-table');
    if (!table) return false;

    const grid = this.buildTableGrid(table);
    let top = Infinity;
    let left = Infinity;
    let bottom = -Infinity;
    let right = -Infinity;
    cells.forEach((cell) => {
      const rect = grid.cellMap.get(cell);
      if (!rect) return;
      top = Math.min(top, rect.top);
      left = Math.min(left, rect.left);
      bottom = Math.max(bottom, rect.bottom);
      right = Math.max(right, rect.right);
    });
    if (!isFinite(top)) return false;

    // Validate the rectangle is fully covered by the selected cells only.
    const selected = new Set(cells);
    for (let r = top; r <= bottom; r += 1) {
      for (let c = left; c <= right; c += 1) {
        const occupant = grid.matrix[r] && grid.matrix[r][c];
        if (!occupant || !selected.has(occupant)) return false;
      }
    }

    const target = grid.matrix[top][left];
    const ordered = cells
      .filter((cell) => grid.cellMap.has(cell))
      .sort((a, b) => {
        const ra = grid.cellMap.get(a);
        const rb = grid.cellMap.get(b);
        return ra.top - rb.top || ra.left - rb.left;
      });

    const htmlParts = [];
    ordered.forEach((cell) => {
      const inner = (cell.innerHTML || '').trim();
      if (inner && inner.toLowerCase() !== '<br>') htmlParts.push(cell.innerHTML.trim());
    });

    ordered.forEach((cell) => {
      if (cell !== target) cell.remove();
    });

    const colspan = right - left + 1;
    const rowspan = bottom - top + 1;
    if (colspan > 1) target.colSpan = colspan; else target.removeAttribute('colspan');
    if (rowspan > 1) target.rowSpan = rowspan; else target.removeAttribute('rowspan');
    target.innerHTML = htmlParts.length ? htmlParts.join(' ') : '<br>';

    this.clearMergeSelection(editor);
    this.refreshMergeControls();
    this.syncEditor(editor || table.closest('.content-editor-area'));
    return true;
  },

  splitSelectedCell(editor) {
    const target = this.getSplitTarget();
    if (!target) return false;
    return this.splitCell(target, editor);
  },

  splitCell(cell, editor) {
    if (!cell) return false;
    const table = cell.closest('table.content-editor-table');
    if (!table) return false;

    const colspan = Math.max(1, cell.colSpan || 1);
    const rowspan = Math.max(1, cell.rowSpan || 1);
    if (colspan <= 1 && rowspan <= 1) return false;

    const grid = this.buildTableGrid(table);
    const info = grid.cellMap.get(cell);
    if (!info) return false;

    const tag = cell.tagName.toLowerCase();
    cell.removeAttribute('colspan');
    cell.removeAttribute('rowspan');

    // Anchor row: fill the remaining columns right after the origin cell.
    let prev = cell;
    for (let i = 1; i < colspan; i += 1) {
      const newCell = this.createEmptyCell(tag);
      prev.after(newCell);
      prev = newCell;
    }

    // Lower rows previously covered by rowspan: insert `colspan` cells at column `left`.
    for (let r = info.top + 1; r <= info.bottom; r += 1) {
      const tr = grid.rows[r];
      if (!tr) continue;
      let ref = null;
      Array.from(tr.children).some((child) => {
        const childInfo = grid.cellMap.get(child);
        if (childInfo && childInfo.left > info.right) {
          ref = child;
          return true;
        }
        return false;
      });
      for (let i = 0; i < colspan; i += 1) {
        tr.insertBefore(this.createEmptyCell(tag), ref);
      }
    }

    this.clearMergeSelection(editor);
    this.refreshMergeControls();
    this.syncEditor(editor || table.closest('.content-editor-area'));
    return true;
  },

  refreshMergeControls() {
    const block = window.ContentEditorLayoutBlock;
    const editor = block && block.activeEditor;
    const wrapper = editor && editor.closest ? editor.closest('.content-editor-wrapper') : null;
    const panel = wrapper && wrapper.querySelector('.content-editor-layout-inspector');
    if (!panel) return;
    const controls = panel.querySelector('[data-ce-merge-controls]');
    if (!controls) return;

    const isTable = !!(block && block.selected && block.selected.type === 'table');
    controls.hidden = !isTable;

    const mergeBtn = controls.querySelector('[data-ce-table-action="merge"]');
    const splitBtn = controls.querySelector('[data-ce-table-action="split"]');
    if (mergeBtn) mergeBtn.disabled = !this.canMerge();
    if (splitBtn) splitBtn.disabled = !this.canSplit();
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
