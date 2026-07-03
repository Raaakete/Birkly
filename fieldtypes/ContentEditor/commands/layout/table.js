// Table layout commands for ContentEditor (P5.5-M4)
(function () {
  const getActiveEditor = () =>
    window.ContentEditorCommands && window.ContentEditorCommands.getActiveEditor
      ? window.ContentEditorCommands.getActiveEditor()
      : null;

  window.ContentEditorTableCommands = {
insertTable() {
    const editor = getActiveEditor();
    if (!editor) return;

    window.ContentEditorSelection.save();
    const selectionSnapshot = window.ContentEditorSelection.cloneSavedData
      ? window.ContentEditorSelection.cloneSavedData()
      : (window.ContentEditorSelection.savedSelectionData
        ? JSON.parse(JSON.stringify(window.ContentEditorSelection.savedSelectionData))
        : null);
    const rangeSnapshot = window.ContentEditorSelection.cloneSavedRange
      ? window.ContentEditorSelection.cloneSavedRange()
      : (window.ContentEditorSelection.savedRange
        ? window.ContentEditorSelection.savedRange.cloneRange()
        : null);

    const restoreInsertSelection = () => {
      if (window.ContentEditorSelection.restoreFromSnapshot) {
        window.ContentEditorSelection.restoreFromSnapshot(editor, selectionSnapshot, rangeSnapshot);
      } else {
        window.ContentEditorSelection.restore(editor);
      }
    };

    const showDialog = (onConfirm) => {
      const dialog = this.createTableDialog({
        title: 'Insert Table',
        primaryLabel: 'Insert',
        rows: 3,
        cols: 3,
        onConfirm,
      });

      document.body.appendChild(dialog);

      setTimeout(() => {
        dialog.classList.add('active');
        const rowsInput = dialog.querySelector('.table-rows-input');
        if (rowsInput) {
          rowsInput.focus();
          rowsInput.select();
        }
      }, 10);
    };

    if (window.ContentEditorBlockManager) {
      showDialog(({ rows, cols }) => {
        restoreInsertSelection();
        window.ContentEditorBlockManager.insertTable(editor, rows, cols);
      });
      return;
    }

    showDialog(({ rows, cols }) => {
      restoreInsertSelection();
      this.createTableElement(rows, cols);
    });
  },

createTableDialog(options = {}) {
    const config = {
      title: 'Insert Table',
      primaryLabel: 'Insert',
      rows: 3,
      cols: 3,
      onConfirm: null,
      ...options
    };
    
    const dialog = document.createElement('div');
    dialog.className = 'content-editor-table-dialog';
    
    dialog.innerHTML = `
      <div class="table-dialog-overlay"></div>
      <div class="table-dialog-content">
        <div class="table-dialog-header">
          <h3>${config.title}</h3>
          <button type="button" class="table-dialog-close" title="Close">×</button>
        </div>
        <div class="table-dialog-body">
          <div class="table-dialog-field">
            <label for="table-rows">Rows</label>
            <input type="number" id="table-rows" class="table-rows-input" min="1" max="20" value="${config.rows}">
          </div>
          <div class="table-dialog-field">
            <label for="table-cols">Columns</label>
            <input type="number" id="table-cols" class="table-cols-input" min="1" max="20" value="${config.cols}">
          </div>
        </div>
        <div class="table-dialog-footer">
          <button type="button" class="table-dialog-btn table-dialog-cancel">Cancel</button>
          <button type="button" class="table-dialog-btn table-dialog-save table-dialog-primary">${config.primaryLabel}</button>
        </div>
      </div>
    `;
    
    // Close handlers
    const closeDialog = () => {
      dialog.classList.remove('active');
      setTimeout(() => {
        document.body.removeChild(dialog);
      }, 200);
    };
    
    dialog.querySelector('.table-dialog-overlay').addEventListener('click', closeDialog);
    dialog.querySelector('.table-dialog-close').addEventListener('click', closeDialog);
    dialog.querySelector('.table-dialog-cancel').addEventListener('click', closeDialog);
    
    // Save handler
    const saveBtn = dialog.querySelector('.table-dialog-save');
    saveBtn.addEventListener('click', () => {
      const rowsInput = dialog.querySelector('.table-rows-input');
      const colsInput = dialog.querySelector('.table-cols-input');
      
      const rows = parseInt(rowsInput.value, 10) || 3;
      const cols = parseInt(colsInput.value, 10) || 3;
      
      // Clamp values
      const numRows = Math.max(1, Math.min(20, rows));
      const numCols = Math.max(1, Math.min(20, cols));
      
      if (typeof config.onConfirm === 'function') {
        const result = config.onConfirm({ rows: numRows, cols: numCols, closeDialog, dialog });
        if (result === false) {
          return;
        }
      }
      
      closeDialog();
    });
    
    // Enter key to save
    dialog.querySelectorAll('input').forEach(input => {
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          saveBtn.click();
        }
        if (e.key === 'Escape') {
          e.preventDefault();
          closeDialog();
        }
      });
    });
    
    return dialog;
  },

createTableElement(rows, cols) {
    const editor = getActiveEditor();
    if (!editor) return;

    window.ContentEditorSelection.restore(editor);
    
    const selection = window.getSelection();
    
    // Create table
    const table = document.createElement('table');
    table.className = 'content-editor-table';
    table.setAttribute('data-editable-table', 'true');
    table.style.width = '100%';
    table.style.borderCollapse = 'collapse';
    
    const equalPct = `${Math.round((100 / cols) * 10) / 10}%`;
    const colgroup = document.createElement('colgroup');
    for (let j = 0; j < cols; j++) {
      const col = document.createElement('col');
      col.style.width = equalPct;
      colgroup.appendChild(col);
    }
    table.appendChild(colgroup);
    
    // Create table body
    const tbody = document.createElement('tbody');
    
    for (let i = 0; i < rows; i++) {
      const tr = document.createElement('tr');
      for (let j = 0; j < cols; j++) {
        const td = document.createElement('td');
        td.innerHTML = '<br>'; // Empty cell with cursor placeholder
        tr.appendChild(td);
      }
      tbody.appendChild(tr);
    }
    
    table.appendChild(tbody);

    if (window.ContentEditorTableLayout && window.ContentEditorTableLayout.enhanceTable) {
      window.ContentEditorTableLayout.enhanceTable(table);
    }
    
    // Initialize table controls (row/column management)
    this.initTableControls(table);
    
    // Create wrapper paragraph for table
    const wrapperP = document.createElement('p');
    wrapperP.appendChild(table);
    
    const dom = window.ContentEditorDOMUtils;
    const insertResult = dom && dom.insertNodesAtCaret
      ? dom.insertNodesAtCaret(editor, [wrapperP], { addTrailingParagraph: true })
      : null;

    const newP = insertResult && insertResult.trailing
      ? insertResult.trailing
      : (() => {
          const p = document.createElement('p');
          p.innerHTML = '<br>';
          editor.appendChild(p);
          return p;
        })();
    
    // Move cursor to first cell
    const firstCell = table.querySelector('td');
    if (firstCell) {
      const newRange = document.createRange();
      newRange.selectNodeContents(firstCell);
      newRange.collapse(true);
      selection.removeAllRanges();
      selection.addRange(newRange);
    } else {
      // Fallback: move cursor to new paragraph
      const newRange = document.createRange();
      newRange.selectNodeContents(newP);
      newRange.collapse(true);
      selection.removeAllRanges();
      selection.addRange(newRange);
    }
    
    // Sync and update toolbar
    if (window.ContentEditorSync) {
      const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
      if (hiddenInput) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
    }
    if (window.ContentEditorToolbar) {
      const toolbar = editor.closest('.content-editor-wrapper')?.querySelector('.content-editor-toolbar') ||
                     document.querySelector('.content-editor-toolbar');
      if (toolbar) {
        window.ContentEditorToolbar.updateState(editor, toolbar);
      }
    }
  },

initTableControls(table) {
    if (!table) return;
    
    if (table._rowControl && table._colControl) {
      this.addTableControls(table);
      return;
    }
    
    // Create single row control (will be positioned dynamically)
    const rowControl = document.createElement('div');
    rowControl.className = 'table-row-control';
    rowControl.style.display = 'none';
    
    // Create single column control (will be positioned dynamically)
    const colControl = document.createElement('div');
    colControl.className = 'table-col-control';
    colControl.style.display = 'none';
    
    // Add controls to table wrapper (not table itself, to avoid positioning issues)
    const wrapper = table.closest('p') || table.parentElement;
    if (wrapper) {
      wrapper.style.position = 'relative';
      wrapper.appendChild(rowControl);
      wrapper.appendChild(colControl);
    } else {
      table.style.position = 'relative';
      table.appendChild(rowControl);
      table.appendChild(colControl);
    }
    
    // Store controls on table for easy access
    table._rowControl = rowControl;
    table._colControl = colControl;
    table._hideTimeout = null;
    table._hoveringControls = false;
    table._rowShowTimer = null;
    table._colShowTimer = null;
    table._pendingRowData = null;
    table._pendingColData = null;
    
    // Keep controls visible when hovering over them
    rowControl.addEventListener('mouseenter', () => {
      if (table._hideTimeout) {
        clearTimeout(table._hideTimeout);
        table._hideTimeout = null;
      }
      table._hoveringControls = true;
    });
    
    colControl.addEventListener('mouseenter', () => {
      if (table._hideTimeout) {
        clearTimeout(table._hideTimeout);
        table._hideTimeout = null;
      }
      table._hoveringControls = true;
    });
    
    // Handle mouse leave from controls
    rowControl.addEventListener('mouseleave', (e) => {
      // Check if mouse is moving to the other control
      const relatedTarget = e.relatedTarget;
      if (relatedTarget && (relatedTarget === colControl || colControl.contains(relatedTarget))) {
        return; // Moving to column control, don't hide
      }
      table._hoveringControls = false;
      this.hideTableControls(table, rowControl, colControl);
    });
    
    colControl.addEventListener('mouseleave', (e) => {
      // Check if mouse is moving to the other control
      const relatedTarget = e.relatedTarget;
      if (relatedTarget && (relatedTarget === rowControl || rowControl.contains(relatedTarget))) {
        return; // Moving to row control, don't hide
      }
      table._hoveringControls = false;
      this.hideTableControls(table, rowControl, colControl);
    });
    
    // Handle mouse events on table
    table.addEventListener('mouseleave', () => {
      if (!table._hoveringControls) {
        this.hideTableControls(table, rowControl, colControl);
      }
    });
    
    table.addEventListener('mousemove', (e) => {
      if (table._hideTimeout) {
        clearTimeout(table._hideTimeout);
        table._hideTimeout = null;
      }
      this.handleTableHover(table, e, rowControl, colControl);
    });
    
    // Hide controls when user clicks into a cell to edit
    table.addEventListener('click', (e) => {
      const cell = e.target.closest('td, th');
      if (cell && e.target === cell || cell.contains(e.target)) {
        // User clicked into cell - hide controls after a short delay
        setTimeout(() => {
          if (!table._hoveringControls) {
            this.hideTableControls(table, rowControl, colControl);
          }
        }, 100);
      }
    });
    
    // Hide controls when user starts typing in a cell
    table.addEventListener('focusin', (e) => {
      const cell = e.target.closest('td, th');
      if (cell) {
        // User focused on cell - hide controls
        setTimeout(() => {
          if (!table._hoveringControls) {
            this.hideTableControls(table, rowControl, colControl);
          }
        }, 100);
      }
    });
    
    this.addTableControls(table);
  },

addTableControls(table) {
    if (!table) return;
    const wrapper = table.closest('p') || table.parentElement || table;
    
    if (getComputedStyle(wrapper).position === 'static') {
      wrapper.style.position = 'relative';
    }
    wrapper.classList.add('content-editor-table-wrapper');
    
    const existingControls = wrapper.querySelector('.content-editor-table-controls');
    if (existingControls) {
      existingControls.remove();
    }
    
    const controls = document.createElement('div');
    controls.className = 'content-editor-table-controls';
    controls.innerHTML = `
      <button type="button" class="table-action-btn table-control-btn" data-action="edit-table" title="Edit table">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 20h9"></path>
          <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
        </svg>
      </button>
      <button type="button" class="table-action-btn table-control-btn" data-action="delete-table" title="Delete table">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="3 6 5 6 21 6"></polyline>
          <path d="M19 6l-1 14H6L5 6"></path>
          <path d="M10 11v6"></path>
          <path d="M14 11v6"></path>
          <path d="M9 6V4h6v2"></path>
        </svg>
      </button>
    `;
    
    const editBtn = controls.querySelector('[data-action="edit-table"]');
    const deleteBtn = controls.querySelector('[data-action="delete-table"]');
    
    editBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.editTableElement(table);
    });
    
    deleteBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.deleteTableElement(table);
    });
    
    wrapper.appendChild(controls);
  },

editTableElement(table) {
    if (!table) return;
    const tbody = table.querySelector('tbody');
    const rows = tbody ? tbody.querySelectorAll('tr').length : 1;
    const firstRow = tbody ? tbody.querySelector('tr') : null;
    const cols = firstRow ? firstRow.querySelectorAll('td, th').length : 1;
    
    const dialog = this.createTableDialog({
      title: 'Edit Table',
      primaryLabel: 'Apply',
      rows: rows || 1,
      cols: cols || 1,
      onConfirm: ({ rows: targetRows, cols: targetCols }) => {
        this.resizeTableElement(table, targetRows, targetCols);
      }
    });
    
    document.body.appendChild(dialog);
    
    setTimeout(() => {
      dialog.classList.add('active');
      const rowsInput = dialog.querySelector('.table-rows-input');
      if (rowsInput) {
        rowsInput.focus();
        rowsInput.select();
      }
    }, 10);
  },

resizeTableElement(table, targetRows, targetCols) {
    if (!table) return;
    const tbody = table.querySelector('tbody') || table.appendChild(document.createElement('tbody'));
    const desiredRows = Math.max(1, parseInt(targetRows, 10) || 1);
    const desiredCols = Math.max(1, parseInt(targetCols, 10) || 1);
    
    let rows = Array.from(tbody.querySelectorAll('tr'));
    rows.forEach(row => {
      const cells = Array.from(row.querySelectorAll('td, th'));
      const cellTag = cells[0]?.tagName?.toLowerCase() || 'td';
      if (cells.length < desiredCols) {
        for (let i = cells.length; i < desiredCols; i++) {
          const cell = document.createElement(cellTag);
          cell.innerHTML = '<br>';
          row.appendChild(cell);
        }
      } else if (cells.length > desiredCols) {
        for (let i = cells.length - 1; i >= desiredCols; i--) {
          cells[i].remove();
        }
      }
    });
    
    rows = Array.from(tbody.querySelectorAll('tr'));
    if (rows.length < desiredRows) {
      for (let i = rows.length; i < desiredRows; i++) {
        const tr = document.createElement('tr');
        for (let j = 0; j < desiredCols; j++) {
          const td = document.createElement('td');
          td.innerHTML = '<br>';
          tr.appendChild(td);
        }
        tbody.appendChild(tr);
      }
    } else if (rows.length > desiredRows) {
      for (let i = rows.length - 1; i >= desiredRows; i--) {
        rows[i].remove();
      }
    }
    
    table.classList.add('content-editor-table');
    table.setAttribute('data-editable-table', 'true');
    
    tbody.querySelectorAll('tr').forEach(tr => {
      tr.classList.remove('table-row-hover-top', 'table-row-hover-bottom');
    });
    tbody.querySelectorAll('td, th').forEach(cell => {
      cell.classList.remove('table-col-hover-left', 'table-col-hover-right');
    });
    
    this.addTableControls(table);
    
    const editor = table.closest('.content-editor-area');
    const fieldName = editor?.dataset?.fieldName || editor?.getAttribute('data-field-name');
    if (editor && fieldName && window.ContentEditorSync) {
      const hiddenInput = document.getElementById('field_' + fieldName);
      if (hiddenInput) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
    }
    
    if (window.ContentEditorHistory) {
      setTimeout(() => {
        window.ContentEditorHistory.saveState();
      }, 50);
    }
  },

deleteTableElement(table) {
    if (!table) return;
    const editor = table.closest('.content-editor-area') || getActiveEditor();
    const wrapper = table.closest('.content-editor-table-wrapper') || table.closest('p') || table.parentElement;
    let focusTarget = null;
    
    if (wrapper && wrapper.parentNode) {
      focusTarget = wrapper.nextElementSibling || wrapper.previousElementSibling;
      wrapper.remove();
    } else {
      table.remove();
    }
    
    if (!focusTarget && editor) {
      const placeholder = document.createElement('p');
      placeholder.innerHTML = '<br>';
      editor.appendChild(placeholder);
      focusTarget = placeholder;
    }
    
    if (focusTarget) {
      const focusEl = focusTarget.matches('p,div') ? focusTarget : focusTarget.querySelector('p,div');
      if (focusEl) {
        const range = document.createRange();
        range.selectNodeContents(focusEl);
        range.collapse(true);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        if (focusEl.focus) {
          focusEl.focus();
        }
      }
    }
    
    const fieldName = editor?.dataset?.fieldName || editor?.getAttribute('data-field-name');
    if (editor && fieldName && window.ContentEditorSync) {
      const hiddenInput = document.getElementById('field_' + fieldName);
      if (hiddenInput) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
    }
    
    if (window.ContentEditorHistory) {
      setTimeout(() => {
        window.ContentEditorHistory.saveState();
      }, 50);
    }
  },

hideTableControls(table, rowControl, colControl) {
    // Clear any existing timeout
    if (table._hideTimeout) {
      clearTimeout(table._hideTimeout);
    }
    this.cancelTableControlShow(table, 'row');
    this.cancelTableControlShow(table, 'col');
    
    // Longer delay to allow moving to controls (300ms)
    table._hideTimeout = setTimeout(() => {
      // Double check we're not hovering over controls or near them
      if (!table._hoveringControls) {
        // Final check: are controls still visible and should they stay?
        const rowVisible = rowControl.style.display !== 'none';
        const colVisible = colControl.style.display !== 'none';
        
        if (rowVisible || colVisible) {
          // One more check - if mouse is still near, don't hide
          // This will be checked on next mousemove anyway
          rowControl.style.display = 'none';
          colControl.style.display = 'none';
          // Remove border highlights
          const tbody = table.querySelector('tbody');
          if (tbody) {
            tbody.querySelectorAll('tr').forEach(tr => {
              tr.classList.remove('table-row-hover-top', 'table-row-hover-bottom');
            });
            tbody.querySelectorAll('td, th').forEach(cell => {
              cell.classList.remove('table-col-hover-left', 'table-col-hover-right');
            });
          }
        }
      }
      table._hideTimeout = null;
    }, 300); // 300ms delay to allow moving to controls
  },

handleTableHover(table, e, rowControl, colControl) {
    // Check if mouse is near the controls themselves (within 80px buffer for easier clicking)
    const mouseX = e.clientX;
    const mouseY = e.clientY;
    
    let nearRowControl = false;
    let nearColControl = false;
    
    if (rowControl.style.display !== 'none' && rowControl.offsetParent !== null) {
      try {
        const rowControlRect = rowControl.getBoundingClientRect();
        // Check if mouse is within expanded bounds (80px buffer)
        nearRowControl = mouseX >= (rowControlRect.left - 40) && 
                        mouseX <= (rowControlRect.right + 40) &&
                        mouseY >= (rowControlRect.top - 40) && 
                        mouseY <= (rowControlRect.bottom + 40);
      } catch (e) {
        // Control might not be positioned yet
      }
    }
    
    if (colControl.style.display !== 'none' && colControl.offsetParent !== null) {
      try {
        const colControlRect = colControl.getBoundingClientRect();
        // Check if mouse is within expanded bounds (80px buffer)
        nearColControl = mouseX >= (colControlRect.left - 40) && 
                        mouseX <= (colControlRect.right + 40) &&
                        mouseY >= (colControlRect.top - 40) && 
                        mouseY <= (colControlRect.bottom + 40);
      } catch (e) {
        // Control might not be positioned yet
      }
    }
    
    // If near controls, keep them visible and don't process further
    if (nearRowControl || nearColControl || table._hoveringControls) {
      // Clear any hide timeout
      if (table._hideTimeout) {
        clearTimeout(table._hideTimeout);
        table._hideTimeout = null;
      }
      return; // Don't hide controls if mouse is near them
    }
    
    const cell = e.target.closest('td, th');
    if (!cell) {
      if (!table._hoveringControls) {
        this.hideTableControls(table, rowControl, colControl);
      }
      return;
    }
    
    const row = cell.parentElement;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const rowIndex = rows.indexOf(row);
    const colIndex = Array.from(row.children).indexOf(cell);
    
    if (rowIndex === -1 || colIndex === -1) return;
    
    // Get positions
    const cellRect = cell.getBoundingClientRect();
    const rowRect = row.getBoundingClientRect();
    const wrapper = table.closest('p') || table.parentElement;
    const wrapperRect = wrapper ? wrapper.getBoundingClientRect() : table.getBoundingClientRect();
    
    // Calculate mouse position relative to cell
    const mouseXInCell = e.clientX - cellRect.left;
    const mouseYInCell = e.clientY - cellRect.top;
    const cellWidth = cellRect.width;
    const cellHeight = cellRect.height;
    
    // Threshold for border detection (10px from edges - larger for easier detection)
    const borderThreshold = 10;
    
    // Check if near row border (top or bottom of cell)
    const nearTopBorder = mouseYInCell < borderThreshold;
    const nearBottomBorder = mouseYInCell > (cellHeight - borderThreshold);
    const nearRowBorder = nearTopBorder || nearBottomBorder;
    
    // Check if near column border (left or right of cell)
    const nearLeftBorder = mouseXInCell < borderThreshold;
    const nearRightBorder = mouseXInCell > (cellWidth - borderThreshold);
    const nearColBorder = nearLeftBorder || nearRightBorder;
    
    // Remove all border highlights first
    tbody.querySelectorAll('tr').forEach(tr => {
      tr.classList.remove('table-row-hover-top', 'table-row-hover-bottom');
    });
    tbody.querySelectorAll('td, th').forEach(cell => {
      cell.classList.remove('table-col-hover-left', 'table-col-hover-right');
    });
    
    // Position row control (overlapping left border of table)
    if (nearRowBorder) {
      // Clear any pending hide timeout
      if (table._hideTimeout) {
        clearTimeout(table._hideTimeout);
        table._hideTimeout = null;
      }
      
      const isTopBorder = nearTopBorder;
      table._pendingRowData = { row, rowIndex, isTopBorder };
      if (rowControl.style.display !== 'none') {
        this.renderRowControl(table, rowControl);
      } else {
        this.scheduleTableControlShow(table, 'row', () => {
          if (!table._pendingRowData) return;
          this.renderRowControl(table, rowControl);
        });
      }
    } else {
      table._pendingRowData = null;
      this.cancelTableControlShow(table, 'row');
      if (rowControl.style.display !== 'none' && !table._hoveringControls && !nearRowControl) {
        rowControl.style.display = 'none';
        this.clearRowHoverState(table);
        this.clearColHoverState(table);
      }
    }
    
    // Position column control (overlapping top border of table)
    if (nearColBorder) {
      const firstRow = rows[0];
      if (firstRow) {
        const firstRowCells = Array.from(firstRow.querySelectorAll('td, th'));
        const targetCell = firstRowCells[colIndex];
        if (targetCell) {
          const targetCellRect = targetCell.getBoundingClientRect();
          const colLeft = targetCellRect.left - wrapperRect.left;
          const colWidth = targetCellRect.width;
          
          // Determine which border (left or right)
          const isLeftBorder = nearLeftBorder;
          table._pendingColData = { colIndex, isLeftBorder };
          
          if (colControl.style.display !== 'none') {
            this.renderColControl(table, colControl);
          } else {
            this.scheduleTableControlShow(table, 'col', () => {
              if (!table._pendingColData) return;
              this.renderColControl(table, colControl);
            });
          }
        }
      }
    } else {
      table._pendingColData = null;
      this.cancelTableControlShow(table, 'col');
      if (colControl.style.display !== 'none' && !table._hoveringControls && !nearColControl) {
        colControl.style.display = 'none';
        this.clearColHoverState(table);
        this.clearRowHoverState(table);
      }
    }
  },

attachTableControlListeners(table) {
    const buttons = table.querySelectorAll('.table-control-btn');
    buttons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const action = btn.dataset.action;
        const index = parseInt(btn.dataset.index, 10);
        
        if (action === 'add-row') {
          this.addTableRow(table, index);
        } else if (action === 'remove-row') {
          this.removeTableRow(table, index);
        } else if (action === 'add-col') {
          this.addTableColumn(table, index);
        } else if (action === 'remove-col') {
          this.removeTableColumn(table, index);
        }
      });
    });
  },

scheduleTableControlShow(table, type, callback) {
    const key = type === 'row' ? '_rowShowTimer' : '_colShowTimer';
    if (table[key]) {
      clearTimeout(table[key]);
    }
    table[key] = setTimeout(() => {
      table[key] = null;
      try {
        callback();
      } catch (_e) {}
    }, 500);
  },

cancelTableControlShow(table, type) {
    const key = type === 'row' ? '_rowShowTimer' : '_colShowTimer';
    if (table[key]) {
      clearTimeout(table[key]);
      table[key] = null;
    }
  },

renderRowControl(table, rowControl) {
    if (!table || !rowControl || !table._pendingRowData) return;
    const { row, rowIndex, isTopBorder } = table._pendingRowData;
    if (!row || !row.parentElement) return;
    
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    const wrapper = table.closest('p') || table.parentElement || table;
    const wrapperRect = wrapper ? wrapper.getBoundingClientRect() : table.getBoundingClientRect();
    const rowRect = row.getBoundingClientRect();
    const rowTop = rowRect.top - wrapperRect.top;
    const rowHeight = rowRect.height;
    const rowWidth = rowRect.width;
    const rowLeft = rowRect.left - wrapperRect.left;
    const wrapperWidth = wrapperRect.width;
    
    rowControl.style.height = 'auto';
    rowControl.style.width = 'auto';
    rowControl.style.display = 'flex';
    if (isTopBorder) {
      row.classList.add('table-row-hover-top');
    } else {
      row.classList.add('table-row-hover-bottom');
    }
    
    const allRows = tbody.querySelectorAll('tr');
    rowControl.innerHTML = `
      <button type="button" class="table-control-btn table-control-add" data-action="add-row" data-index="${isTopBorder ? rowIndex : rowIndex + 1}" title="Add row ${isTopBorder ? 'above' : 'below'}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
      </button>
      <button type="button" class="table-control-btn table-control-remove" data-action="remove-row" data-index="${rowIndex}" title="Remove row" ${allRows.length <= 1 ? 'disabled' : ''}>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
      </button>
    `;
    
    const controlWidth = rowControl.offsetWidth || 0;
    const controlHeight = rowControl.offsetHeight || 0;
    const centerX = rowLeft + (rowWidth / 2) - (controlWidth / 2);
    const borderY = isTopBorder ? rowTop : rowTop + rowHeight;
    const clampedX = Math.max(0, Math.min(wrapperWidth - controlWidth, centerX));
    rowControl.style.left = `${clampedX}px`;
    rowControl.style.top = `${borderY - controlHeight / 2}px`;
    
    this.attachTableControlListeners(table);
  },

renderColControl(table, colControl) {
    if (!table || !colControl || !table._pendingColData) return;
    const { colIndex, isLeftBorder } = table._pendingColData;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (!rows.length) return;
    
    const firstRow = rows[0];
    const firstRowCells = Array.from(firstRow.querySelectorAll('td, th'));
    const targetCell = firstRowCells[colIndex];
    if (!targetCell) return;
    
    const wrapper = table.closest('p') || table.parentElement || table;
    const wrapperRect = wrapper ? wrapper.getBoundingClientRect() : table.getBoundingClientRect();
    const targetCellRect = targetCell.getBoundingClientRect();
    const colLeft = targetCellRect.left - wrapperRect.left;
    const colWidth = targetCellRect.width;
    
    let colTop = Infinity;
    let colBottom = -Infinity;
    rows.forEach(r => {
      const cells = Array.from(r.querySelectorAll('td, th'));
      if (cells[colIndex]) {
        const cellRect = cells[colIndex].getBoundingClientRect();
        colTop = Math.min(colTop, cellRect.top - wrapperRect.top);
        colBottom = Math.max(colBottom, cellRect.bottom - wrapperRect.top);
      }
    });
    const colHeight = colBottom - colTop;
    
    if (isLeftBorder) {
      rows.forEach(r => {
        const cells = Array.from(r.querySelectorAll('td, th'));
        if (cells[colIndex]) {
          cells[colIndex].classList.add('table-col-hover-left');
        }
      });
    } else {
      rows.forEach(r => {
        const cells = Array.from(r.querySelectorAll('td, th'));
        if (cells[colIndex]) {
          cells[colIndex].classList.add('table-col-hover-right');
        }
      });
    }
    
    colControl.style.width = 'auto';
    colControl.style.height = 'auto';
    colControl.style.display = 'flex';
    
    const cols = firstRowCells.length;
    colControl.innerHTML = `
      <button type="button" class="table-control-btn table-control-add" data-action="add-col" data-index="${isLeftBorder ? colIndex : colIndex + 1}" title="Add column ${isLeftBorder ? 'before' : 'after'}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
      </button>
      <button type="button" class="table-control-btn table-control-remove" data-action="remove-col" data-index="${colIndex}" title="Remove column" ${cols <= 1 ? 'disabled' : ''}>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
      </button>
    `;
    
    const controlWidth = colControl.offsetWidth || 0;
    const controlHeight = colControl.offsetHeight || 0;
    const borderX = isLeftBorder ? colLeft : colLeft + colWidth;
    const clampedLeft = Math.max(0, Math.min(wrapperRect.width - controlWidth, borderX - controlWidth / 2));
    const targetTop = colTop + (colHeight / 2) - (controlHeight / 2);
    colControl.style.left = `${clampedLeft}px`;
    colControl.style.top = `${targetTop}px`;
    
    this.attachTableControlListeners(table);
  },

clearRowHoverState(table) {
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    tbody.querySelectorAll('tr').forEach(tr => {
      tr.classList.remove('table-row-hover-top', 'table-row-hover-bottom');
    });
  },

clearColHoverState(table) {
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    tbody.querySelectorAll('td, th').forEach(cell => {
      cell.classList.remove('table-col-hover-left', 'table-col-hover-right');
    });
  },

addTableRow(table, afterIndex) {
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    const rows = tbody.querySelectorAll('tr');
    const firstRow = rows[0];
    if (!firstRow) return;
    
    const cols = firstRow.querySelectorAll('td, th').length;
    
    // Create new row
    const newRow = document.createElement('tr');
    for (let i = 0; i < cols; i++) {
      const td = document.createElement('td');
      td.innerHTML = '<br>';
      newRow.appendChild(td);
    }
    
    // Insert after specified row
    if (afterIndex >= 0 && afterIndex < rows.length) {
      const afterRow = rows[afterIndex];
      tbody.insertBefore(newRow, afterRow.nextSibling);
    } else {
      tbody.appendChild(newRow);
    }
    
    // Re-initialize controls to update them
    if (table._rowControl && table._colControl) {
      // Controls will update on next hover
    }
    
    // Sync
    const editor = table.closest('.content-editor-area');
    if (editor && window.ContentEditorSync) {
      const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
      if (hiddenInput) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
    }
  },

removeTableRow(table, index) {
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    const rows = tbody.querySelectorAll('tr');
    if (rows.length <= 1) return; // Don't remove last row
    
    if (index >= 0 && index < rows.length) {
      rows[index].remove();
      
      // Hide controls after removal
      if (table._rowControl && table._colControl) {
        this.hideTableControls(table, table._rowControl, table._colControl);
      }
      
      // Sync
      const editor = table.closest('.content-editor-area');
      if (editor && window.ContentEditorSync) {
        const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
        if (hiddenInput) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
      }
    }
  },

addTableColumn(table, afterIndex) {
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    const rows = tbody.querySelectorAll('tr');
    
    rows.forEach(row => {
      const cells = row.querySelectorAll('td, th');
      const newCell = document.createElement(cells[0]?.tagName || 'td');
      newCell.innerHTML = '<br>';
      
      if (afterIndex >= 0 && afterIndex < cells.length) {
        row.insertBefore(newCell, cells[afterIndex].nextSibling);
      } else {
        row.appendChild(newCell);
      }
    });
    
    // Re-initialize controls to update them
    if (table._rowControl && table._colControl) {
      // Controls will update on next hover
    }
    
    // Sync
    const editor = table.closest('.content-editor-area');
    if (editor && window.ContentEditorSync) {
      const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
      if (hiddenInput) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
    }
  },

removeTableColumn(table, index) {
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    
    const rows = tbody.querySelectorAll('tr');
    const firstRow = rows[0];
    if (!firstRow) return;
    
    const cells = firstRow.querySelectorAll('td, th');
    if (cells.length <= 1) return; // Don't remove last column
    
    if (index >= 0 && index < cells.length) {
      rows.forEach(row => {
        const rowCells = row.querySelectorAll('td, th');
        if (rowCells[index]) {
          rowCells[index].remove();
        }
      });
      
      // Hide controls after removal
      if (table._rowControl && table._colControl) {
        this.hideTableControls(table, table._rowControl, table._colControl);
      }
      
      // Sync
      const editor = table.closest('.content-editor-area');
      if (editor && window.ContentEditorSync) {
        const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
        if (hiddenInput) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
      }
    }
  }
  };
})();
