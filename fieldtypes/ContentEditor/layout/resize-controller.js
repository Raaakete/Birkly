// P5.5-M1 — Pointer-driven column / track resize for tables and grids
window.ContentEditorResizeController = {
  activeDrag: null,
  MIN_COL_PX: 48,

  bindEditor(editor) {
    if (!editor || editor.dataset.layoutResizeBound === 'true') return;
    editor.dataset.layoutResizeBound = 'true';

    document.addEventListener('contenteditor:layout-select', (event) => {
      if (event.detail && event.detail.editor === editor) {
        this.mountHandles(event.detail.layout.root, editor);
      }
    });

    document.addEventListener('contenteditor:layout-deselect', () => {
      this.removeAllHandles(editor);
    });
  },

  removeAllHandles(editor) {
    if (!editor) return;
    editor.querySelectorAll('.content-editor-layout-handles').forEach((el) => el.remove());
  },

  mountHandles(layoutRoot, editor) {
    this.removeAllHandles(editor);
    if (!layoutRoot || !editor.contains(layoutRoot)) return;

    const handlesWrap = document.createElement('div');
    handlesWrap.className = 'content-editor-layout-handles';
    handlesWrap.setAttribute('data-ce-handles', 'true');
    handlesWrap.contentEditable = 'false';

    if (layoutRoot.classList.contains('content-editor-grid')) {
      this.mountGridHandles(layoutRoot, handlesWrap);
    } else if (layoutRoot.tagName === 'TABLE') {
      this.mountTableHandles(layoutRoot, handlesWrap);
    }

    if (handlesWrap.childElementCount > 0) {
      layoutRoot.style.position = layoutRoot.style.position || 'relative';
      layoutRoot.appendChild(handlesWrap);
    }
  },

  mountGridHandles(grid, wrap) {
    const template = grid.style.gridTemplateColumns || window.getComputedStyle(grid).gridTemplateColumns;
    const trackCount = Math.max(1, template.split(' ').filter(Boolean).length);
    for (let i = 0; i < trackCount - 1; i += 1) {
      const handle = document.createElement('div');
      handle.className = 'content-editor-layout-handle content-editor-layout-handle-col';
      handle.dataset.trackIndex = String(i);
      handle.title = 'Drag to resize column track';
      handle.addEventListener('pointerdown', (event) => this.startGridDrag(event, grid, i));
      wrap.appendChild(handle);
    }
    this.positionGridHandles(grid, wrap);
  },

  positionGridHandles(grid, wrap) {
    const rect = grid.getBoundingClientRect();
    const template = grid.style.gridTemplateColumns || window.getComputedStyle(grid).gridTemplateColumns;
    const tracks = template.split(' ').filter(Boolean);
    if (tracks.length < 2) return;

    const gap = this.parsePx(grid.style.gap || window.getComputedStyle(grid).columnGap, 0);
    const totalGap = gap * (tracks.length - 1);
    const innerWidth = rect.width - totalGap;
    const frValues = tracks.map((t) => {
      const fr = t.match(/^([\d.]+)fr$/i);
      if (fr) return parseFloat(fr[1]);
      return 1;
    });
    const frSum = frValues.reduce((a, b) => a + b, 0) || 1;
    let x = 0;
    const positions = [];
    frValues.forEach((fr, idx) => {
      x += (innerWidth * fr) / frSum;
      if (idx < frValues.length - 1) {
        x += gap;
        positions.push(x);
      }
    });

    const handles = wrap.querySelectorAll('.content-editor-layout-handle-col');
    handles.forEach((handle, idx) => {
      const left = positions[idx] != null ? positions[idx] : 0;
      handle.style.left = `${left}px`;
    });
  },

  mountTableHandles(table, wrap) {
    this.ensureColgroup(table);
    const cols = table.querySelectorAll('colgroup col');
    if (cols.length < 2) return;

    cols.forEach((col, index) => {
      if (index >= cols.length - 1) return;
      const handle = document.createElement('div');
      handle.className = 'content-editor-layout-handle content-editor-layout-handle-col';
      handle.dataset.colIndex = String(index);
      handle.title = 'Drag to resize column';
      handle.addEventListener('pointerdown', (event) => this.startTableDrag(event, table, index));
      wrap.appendChild(handle);
    });
    this.positionTableHandles(table, wrap);
  },

  ensureColgroup(table) {
    let colgroup = table.querySelector('colgroup');
    const firstRow = table.querySelector('tr');
    const colCount = firstRow ? firstRow.cells.length : 0;
    if (!colCount) return;

    if (!colgroup) {
      colgroup = document.createElement('colgroup');
      table.insertBefore(colgroup, table.firstChild);
    }

    while (colgroup.children.length < colCount) {
      const col = document.createElement('col');
      col.style.width = `${Math.round(100 / colCount)}%`;
      colgroup.appendChild(col);
    }
    while (colgroup.children.length > colCount) {
      colgroup.lastElementChild.remove();
    }
  },

  positionTableHandles(table, wrap) {
    const rect = table.getBoundingClientRect();
    const cols = table.querySelectorAll('colgroup col');
    if (cols.length < 2) return;

    let offset = 0;
    const positions = [];
    cols.forEach((col, idx) => {
      const width = this.colWidthPx(col, rect.width, cols.length);
      offset += width;
      if (idx < cols.length - 1) {
        positions.push(offset);
      }
    });

    wrap.querySelectorAll('.content-editor-layout-handle-col').forEach((handle, idx) => {
      handle.style.left = `${positions[idx] || 0}px`;
    });
  },

  colWidthPx(col, tableWidth, colCount) {
    const w = col.style.width || '';
    if (w.endsWith('%')) {
      return (parseFloat(w) / 100) * tableWidth;
    }
    if (w.endsWith('px')) {
      return parseFloat(w);
    }
    return tableWidth / colCount;
  },

  startTableDrag(event, table, colIndex) {
    event.preventDefault();
    event.stopPropagation();
    this.ensureColgroup(table);
    const cols = table.querySelectorAll('colgroup col');
    const rect = table.getBoundingClientRect();
    const startX = event.clientX;
    const leftWidth = this.colWidthPx(cols[colIndex], rect.width, cols.length);
    const rightWidth = this.colWidthPx(cols[colIndex + 1], rect.width, cols.length);
    const min = this.MIN_COL_PX;

    const onMove = (moveEvent) => {
      const delta = moveEvent.clientX - startX;
      let newLeft = leftWidth + delta;
      let newRight = rightWidth - delta;
      if (newLeft < min) {
        newRight -= (min - newLeft);
        newLeft = min;
      }
      if (newRight < min) {
        newLeft -= (min - newRight);
        newRight = min;
      }
      const total = rect.width || 1;
      cols[colIndex].style.width = `${Math.round((newLeft / total) * 1000) / 10}%`;
      cols[colIndex + 1].style.width = `${Math.round((newRight / total) * 1000) / 10}%`;
      const wrap = table.querySelector('.content-editor-layout-handles');
      if (wrap) this.positionTableHandles(table, wrap);
    };

    const onUp = () => {
      document.removeEventListener('pointermove', onMove);
      document.removeEventListener('pointerup', onUp);
      this.syncEditor(table);
    };

    document.addEventListener('pointermove', onMove);
    document.addEventListener('pointerup', onUp);
  },

  startGridDrag(event, grid, trackIndex) {
    event.preventDefault();
    event.stopPropagation();
    const template = (grid.style.gridTemplateColumns || window.getComputedStyle(grid).gridTemplateColumns).split(' ').filter(Boolean);
    if (template.length < 2) return;

    const frValues = template.map((t) => {
      const fr = t.match(/^([\d.]+)fr$/i);
      if (fr) return parseFloat(fr[1]);
      return 1;
    });
    const startX = event.clientX;
    const startLeft = frValues[trackIndex];
    const startRight = frValues[trackIndex + 1];
    const minFr = 0.25;

    const onMove = (moveEvent) => {
      const rect = grid.getBoundingClientRect();
      const deltaFr = ((moveEvent.clientX - startX) / Math.max(rect.width, 1)) * (startLeft + startRight);
      let newLeft = startLeft + deltaFr;
      let newRight = startRight - deltaFr;
      if (newLeft < minFr) {
        newRight -= (minFr - newLeft);
        newLeft = minFr;
      }
      if (newRight < minFr) {
        newLeft -= (minFr - newRight);
        newRight = minFr;
      }
      frValues[trackIndex] = Math.round(newLeft * 100) / 100;
      frValues[trackIndex + 1] = Math.round(newRight * 100) / 100;
      grid.style.gridTemplateColumns = frValues.map((v) => `${v}fr`).join(' ');
      const wrap = grid.querySelector('.content-editor-layout-handles');
      if (wrap) this.positionGridHandles(grid, wrap);
    };

    const onUp = () => {
      document.removeEventListener('pointermove', onMove);
      document.removeEventListener('pointerup', onUp);
      this.syncEditor(grid);
    };

    document.addEventListener('pointermove', onMove);
    document.addEventListener('pointerup', onUp);
  },

  parsePx(value, fallback) {
    const n = parseInt(String(value).replace(/px$/i, ''), 10);
    return Number.isFinite(n) ? n : fallback;
  },

  syncEditor(node) {
    const editor = node.closest('.content-editor-area');
    if (!editor || !window.ContentEditorSync) return;
    const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
    if (hiddenInput) {
      window.ContentEditorSync.toHidden(editor, hiddenInput, true);
    }
  },
};
