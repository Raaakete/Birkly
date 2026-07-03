// Grid layout commands for ContentEditor (P5.5-M4)
(function () {
  const getActiveEditor = () =>
    window.ContentEditorCommands && window.ContentEditorCommands.getActiveEditor
      ? window.ContentEditorCommands.getActiveEditor()
      : null;

  const rgbToHex = (color) => {
    if (!color) return '#ffffff';
    if (color.startsWith('#')) return color;
    const rgbMatch = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    if (rgbMatch) {
      const r = parseInt(rgbMatch[1], 10).toString(16).padStart(2, '0');
      const g = parseInt(rgbMatch[2], 10).toString(16).padStart(2, '0');
      const b = parseInt(rgbMatch[3], 10).toString(16).padStart(2, '0');
      return `#${r}${g}${b}`;
    }
    return '#ffffff';
  };

  window.ContentEditorGridCommands = {
resolveGridCellStyles(styles = {}) {
    const hasNumber = (val) => typeof val === 'number' && Number.isFinite(val);
    return {
      bgColor: styles.bgColor || '#f9fafb',
      borderColor: styles.borderColor || '#e5e7eb',
      borderWidth: hasNumber(styles.borderWidth) ? styles.borderWidth : 1,
      borderRadius: hasNumber(styles.borderRadius) ? styles.borderRadius : 4,
      padding: hasNumber(styles.padding) ? styles.padding : 12
    };
  },

applyGridCellStyles(cell, resolvedStyles) {
    if (!cell) return;
    const config = resolvedStyles || this.resolveGridCellStyles({});
    cell.style.backgroundColor = config.bgColor;
    const paddingValue = Math.max(0, config.padding);
    cell.style.padding = `${paddingValue}px`;
    if (config.borderWidth > 0) {
      cell.style.border = `${config.borderWidth}px solid ${config.borderColor}`;
    } else {
      cell.style.border = 'none';
    }
    const radiusValue = Math.max(0, config.borderRadius);
    cell.style.borderRadius = `${radiusValue}px`;
  },

parseNumericValue(value) {
    if (typeof value === 'number') return value;
    if (!value || typeof value !== 'string') return NaN;
    const parsed = parseFloat(value);
    return Number.isNaN(parsed) ? NaN : parsed;
  },

insertGrid(existingGrid = null) {
    const editor = getActiveEditor();
    if (!editor) return;
    
    // Save selection
    window.ContentEditorSelection.save();
    
    // Get existing grid data if editing
    const existingData = existingGrid ? this.extractGridData(existingGrid) : null;
    
    // Create grid dialog using the same structure as embed dialogs
    const dialog = document.createElement('div');
    dialog.className = 'content-editor-dialog';
    dialog.innerHTML = `
      <div class="content-editor-dialog-overlay"></div>
      <div class="content-editor-dialog-content">
        <div class="content-editor-dialog-header">
          <h3>${existingGrid ? 'Edit Grid' : 'Insert Grid'}</h3>
          <button type="button" class="content-editor-dialog-close" aria-label="Close">×</button>
        </div>
        <div class="content-editor-dialog-body">
          <form class="content-editor-embed-form" novalidate>
            <div class="form-group">
              <label>Columns</label>
              <input type="number" id="grid-cols" value="${existingData ? existingData.cols : '3'}" min="1" max="12" autofocus>
            </div>
            <div class="form-group">
              <label>Rows</label>
              <input type="number" id="grid-rows" value="${existingData ? existingData.rows : '2'}" min="1" max="20">
            </div>
            <div class="form-group">
              <label>Gap (px)</label>
              <input type="number" id="grid-gap" value="${existingData ? existingData.gap : '16'}" min="0" max="100">
            </div>
            <div class="form-group">
              <label>Column Size</label>
              <select id="grid-column-size">
                <option value="1fr" ${existingData && existingData.columnSize === '1fr' ? 'selected' : ''}>Equal (1fr each)</option>
                <option value="auto" ${existingData && existingData.columnSize === 'auto' ? 'selected' : ''}>Auto (content-based)</option>
                <option value="minmax(200px, 1fr)" ${existingData && existingData.columnSize === 'minmax(200px, 1fr)' ? 'selected' : ''}>Min 200px, then equal</option>
              </select>
            </div>
            <div class="form-group">
              <label>Background Color</label>
              <input type="color" id="grid-bg-color" value="${existingData && existingData.bgColor ? existingData.bgColor : '#ffffff'}" style="width: 100%; height: 40px; border: 1px solid #e5e7eb; border-radius: 6px; cursor: pointer;">
            </div>
            <div class="form-group">
              <label>Border Color</label>
              <input type="color" id="grid-border-color" value="${existingData && existingData.borderColor ? existingData.borderColor : '#e5e7eb'}" style="width: 100%; height: 40px; border: 1px solid #e5e7eb; border-radius: 6px; cursor: pointer;">
            </div>
            <div class="form-group">
              <label>Border Width (px)</label>
              <input type="number" id="grid-border-width" value="${existingData && existingData.borderWidth !== undefined ? existingData.borderWidth : '1'}" min="0" max="10" step="1">
            </div>
            <div class="form-group">
              <label>Border Radius (px)</label>
              <input type="number" id="grid-border-radius" value="${existingData && existingData.borderRadius !== undefined ? existingData.borderRadius : '4'}" min="0" max="50" step="1">
            </div>
            <div class="form-group">
              <label>Padding (px)</label>
              <input type="number" id="grid-padding" value="${existingData && existingData.padding !== undefined ? existingData.padding : '12'}" min="0" max="50" step="1">
            </div>
          </form>
        </div>
        <div class="content-editor-dialog-footer">
          <button type="button" class="content-editor-dialog-btn content-editor-dialog-cancel">Cancel</button>
          <button type="button" class="content-editor-dialog-btn content-editor-dialog-submit">${existingGrid ? 'Update Grid' : 'Insert Grid'}</button>
        </div>
      </div>
    `;
    
    document.body.appendChild(dialog);
    
    const colsInput = dialog.querySelector('#grid-cols');
    const rowsInput = dialog.querySelector('#grid-rows');
    const gapInput = dialog.querySelector('#grid-gap');
    const columnSizeSelect = dialog.querySelector('#grid-column-size');
    const bgColorInput = dialog.querySelector('#grid-bg-color');
    const borderColorInput = dialog.querySelector('#grid-border-color');
    const borderWidthInput = dialog.querySelector('#grid-border-width');
    const borderRadiusInput = dialog.querySelector('#grid-border-radius');
    const paddingInput = dialog.querySelector('#grid-padding');
    const cancelBtn = dialog.querySelector('.content-editor-dialog-cancel');
    const submitBtn = dialog.querySelector('.content-editor-dialog-submit');
    const closeBtn = dialog.querySelector('.content-editor-dialog-close');
    const overlay = dialog.querySelector('.content-editor-dialog-overlay');
    
    const closeDialog = () => {
      document.body.removeChild(dialog);
      window.ContentEditorSelection.clear();
    };
    
    cancelBtn.addEventListener('click', closeDialog);
    closeBtn.addEventListener('click', closeDialog);
    overlay.addEventListener('click', closeDialog);
    
    // Prevent form submission
    const form = dialog.querySelector('.content-editor-embed-form');
    if (form) {
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        e.stopPropagation();
        return false;
      });
    }
    
    const handleSubmit = (e) => {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      
      // Read values with fallbacks
      const cols = parseInt(colsInput.value, 10) || 3;
      const rows = parseInt(rowsInput.value, 10) || 2;
      const parsedGap = this.parseNumericValue(gapInput.value);
      const gap = Number.isFinite(parsedGap) && parsedGap >= 0 ? parsedGap : 16;
      const columnSize = columnSizeSelect.value || '1fr';
      const bgColor = bgColorInput.value || '#ffffff';
      const borderColor = borderColorInput.value || '#e5e7eb';
      const borderWidth = parseInt(borderWidthInput.value) || 0;
      const borderRadius = parseInt(borderRadiusInput.value) || 0;
      const padding = parseInt(paddingInput.value) || 0;
      
      
      const styles = {
        bgColor,
        borderColor,
        borderWidth,
        borderRadius,
        padding
      };
      
      try {
        if (existingGrid) {
          this.updateGridElement(existingGrid, cols, rows, gap, columnSize, styles);
        } else {
          this.createGridElement(cols, rows, gap, columnSize, styles);
        }
        
        // Verify grid was inserted before closing
        const editor = getActiveEditor();
        if (editor) {
          const grids = editor.querySelectorAll('.content-editor-grid');
          if (grids.length > 0) {
          }
        }
        
        // Small delay to ensure DOM is updated
        setTimeout(() => {
          closeDialog();
        }, 10);
      } catch (error) {
        console.error('ContentEditor: Error creating/updating grid:', error);
        alert('Error creating grid. Please check the console for details.');
      }
      
      return false;
    };
    
    // Use capture phase to ensure we catch the event
    submitBtn.addEventListener('click', handleSubmit, true);
    submitBtn.addEventListener('mousedown', (e) => {
      e.preventDefault();
      e.stopPropagation();
    });
    
    // Also handle form submission directly
    if (form) {
      form.onsubmit = (e) => {
        e.preventDefault();
        e.stopPropagation();
        handleSubmit(e);
        return false;
      };
    }
    
    colsInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        e.stopPropagation();
        handleSubmit(e);
      } else if (e.key === 'Escape') {
        e.preventDefault();
        closeDialog();
      }
    });
  },

createGridElement(cols, rows, gap, columnSize, styles = {}) {
    const editor = getActiveEditor();
    if (!editor) {
      console.error('ContentEditor: No active editor found');
      return;
    }

    window.ContentEditorSelection.restore(editor);

    const selection = window.getSelection();

    // Create grid container with inline CSS Grid styles
    const gridContainer = document.createElement('div');
    gridContainer.className = 'content-editor-grid';

    // Build grid-template-columns value
    const columnsValue = `repeat(${cols}, ${columnSize})`;

    // Apply inline CSS Grid styles
    gridContainer.style.display = 'grid';
    gridContainer.style.gridTemplateColumns = columnsValue;
    const resolvedGap = Number.isFinite(gap) ? gap : 16;
    const gapValue = `${resolvedGap}px`;
    gridContainer.style.gap = gapValue;
    gridContainer.style.rowGap = gapValue;
    gridContainer.style.columnGap = gapValue;
    gridContainer.style.width = '100%';
    gridContainer.style.margin = '16px 0';

    const cellStyles = this.resolveGridCellStyles(styles);

    // Create grid items
    const totalItems = cols * rows;
    for (let i = 0; i < totalItems; i++) {
      const gridItem = document.createElement('div');
      gridItem.className = 'content-editor-grid-item';
      gridItem.style.minHeight = '100px';
      this.applyGridCellStyles(gridItem, cellStyles);
      gridItem.contentEditable = 'true';
      gridItem.innerHTML = '<p><br></p>';
      gridContainer.appendChild(gridItem);
    }

    const dom = window.ContentEditorDOMUtils;
    const insertResult = dom && dom.insertNodesAtCaret
      ? dom.insertNodesAtCaret(editor, [gridContainer], { addTrailingParagraph: true })
      : null;

    if (!insertResult) {
      editor.appendChild(gridContainer);
    }

    // Move cursor to first grid item
    const firstItem = gridContainer.querySelector('.content-editor-grid-item');
    if (firstItem) {
      const newRange = document.createRange();
      newRange.selectNodeContents(firstItem);
      newRange.collapse(true);
      selection.removeAllRanges();
      selection.addRange(newRange);
      firstItem.focus();
    }

    // Sync and update toolbar
    if (window.ContentEditorSync) {
      const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
      if (hiddenInput) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
    }

    // Add controls to grid
    this.addGridControls(gridContainer);

    if (window.ContentEditorLayout) {
      window.ContentEditorLayout.initEditor(editor);
    }

    // Save history
    if (window.ContentEditorHistory) {
      setTimeout(() => {
        window.ContentEditorHistory.saveState();
      }, 50);
    }
  },

extractGridData(gridElement) {
    if (!gridElement) return null;
    
    const style = gridElement.style;
    const computedStyle = window.getComputedStyle(gridElement);
    const gridTemplateColumns = style.gridTemplateColumns || '';
    let gap = this.parseNumericValue(style.gap);
    if (Number.isNaN(gap)) {
      gap = this.parseNumericValue(computedStyle.gap);
    }
    if (Number.isNaN(gap)) {
      gap = this.parseNumericValue(computedStyle.rowGap);
    }
    if (Number.isNaN(gap)) {
      gap = 16;
    }
    
    // Parse columns from grid-template-columns
    let cols = 3;
    let columnSize = '1fr';
    
    if (gridTemplateColumns.includes('repeat(')) {
      const match = gridTemplateColumns.match(/repeat\((\d+),\s*(.+?)\)/);
      if (match) {
        cols = parseInt(match[1]);
        columnSize = match[2];
      }
    } else if (gridTemplateColumns) {
      // Count columns by splitting
      const columns = gridTemplateColumns.split(/\s+/).filter(c => c.trim());
      cols = columns.length;
      columnSize = columns[0] || '1fr';
    }
    
    // Count rows by counting grid items
    const items = gridElement.querySelectorAll('.content-editor-grid-item');
    const rows = Math.ceil(items.length / cols) || 2;
    
    // Extract styling values
    const defaults = this.resolveGridCellStyles({});
    let bgColor = defaults.bgColor;
    let borderColor = defaults.borderColor;
    let borderWidth = defaults.borderWidth;
    let borderRadius = defaults.borderRadius;
    let padding = defaults.padding;
    
    const firstItem = gridElement.querySelector('.content-editor-grid-item');
    if (firstItem) {
      const itemStyle = firstItem.style;
      const itemComputedStyle = window.getComputedStyle(firstItem);
      
      const cellBg = itemStyle.backgroundColor || itemComputedStyle.backgroundColor;
      if (cellBg && cellBg !== 'rgba(0, 0, 0, 0)' && cellBg !== 'transparent') {
        bgColor = cellBg;
      }
      
      const cellBorderColor = itemStyle.borderColor || itemComputedStyle.borderColor || style.borderColor || computedStyle.borderColor;
      if (cellBorderColor && cellBorderColor !== 'rgba(0, 0, 0, 0)' && cellBorderColor !== 'transparent') {
        borderColor = cellBorderColor;
      }
      
      const cellBorderWidth = this.parseNumericValue(itemStyle.borderWidth);
      if (!Number.isNaN(cellBorderWidth)) {
        borderWidth = cellBorderWidth;
      } else {
        const legacyBorderWidth = this.parseNumericValue(style.borderWidth);
        const legacyComputedBorderWidth = this.parseNumericValue(computedStyle.borderWidth);
        if (!Number.isNaN(legacyBorderWidth) && legacyBorderWidth > 0) {
          borderWidth = legacyBorderWidth;
        } else if (!Number.isNaN(legacyComputedBorderWidth) && legacyComputedBorderWidth > 0) {
          borderWidth = legacyComputedBorderWidth;
        }
      }
      
      const cellBorderRadius = this.parseNumericValue(itemStyle.borderRadius);
      if (!Number.isNaN(cellBorderRadius)) {
        borderRadius = cellBorderRadius;
      } else {
        const legacyRadius = this.parseNumericValue(style.borderRadius);
        const legacyComputedRadius = this.parseNumericValue(computedStyle.borderRadius);
        if (!Number.isNaN(legacyRadius) && legacyRadius > 0) {
          borderRadius = legacyRadius;
        } else if (!Number.isNaN(legacyComputedRadius) && legacyComputedRadius > 0) {
          borderRadius = legacyComputedRadius;
        }
      }
      
      const cellPadding = this.parseNumericValue(itemStyle.padding);
      if (!Number.isNaN(cellPadding)) {
        padding = cellPadding;
      } else {
        const legacyPadding = this.parseNumericValue(style.padding);
        const legacyComputedPadding = this.parseNumericValue(computedStyle.padding);
        if (!Number.isNaN(legacyPadding)) {
          padding = legacyPadding;
        } else if (!Number.isNaN(legacyComputedPadding)) {
          padding = legacyComputedPadding;
        }
      }
    }
    
    return {
      cols,
      rows,
      gap,
      columnSize,
      bgColor: rgbToHex(bgColor),
      borderColor: rgbToHex(borderColor),
      borderWidth,
      borderRadius,
      padding
    };
  },

updateGridElement(gridElement, cols, rows, gap, columnSize, styles = {}) {
    if (!gridElement) return;
    
    // Update grid styles
    const columnsValue = `repeat(${cols}, ${columnSize})`;
    gridElement.style.gridTemplateColumns = columnsValue;
    const resolvedGap = Number.isFinite(gap) ? gap : 16;
    const gapValue = `${resolvedGap}px`;
    gridElement.style.gap = gapValue;
    gridElement.style.rowGap = gapValue;
    gridElement.style.columnGap = gapValue;
    gridElement.style.gridGap = gapValue;
    gridElement.style.gridRowGap = gapValue;
    gridElement.style.gridColumnGap = gapValue;
    
    // Ensure legacy container styles are cleared (styling now happens on cells)
    gridElement.style.borderColor = '';
    gridElement.style.borderWidth = '';
    gridElement.style.borderStyle = '';
    gridElement.style.borderRadius = '';
    gridElement.style.padding = '';
    
    // Get current items
    const currentItems = Array.from(gridElement.querySelectorAll('.content-editor-grid-item'));
    const currentCount = currentItems.length;
    const newCount = cols * rows;
    
    const cellStyles = this.resolveGridCellStyles(styles);
    currentItems.forEach(item => {
      this.applyGridCellStyles(item, cellStyles);
    });
    
    if (newCount > currentCount) {
      // Add new items
      for (let i = currentCount; i < newCount; i++) {
        const gridItem = document.createElement('div');
        gridItem.className = 'content-editor-grid-item';
        gridItem.style.minHeight = '100px';
        this.applyGridCellStyles(gridItem, cellStyles);
        gridItem.contentEditable = 'true';
        gridItem.innerHTML = '<p><br></p>';
        gridElement.appendChild(gridItem);
      }
    } else if (newCount < currentCount) {
      // Remove excess items
      for (let i = currentCount - 1; i >= newCount; i--) {
        currentItems[i].remove();
      }
    }
    
    // Sync content
    const editor = getActiveEditor();
    if (editor && window.ContentEditorSync) {
      const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
      if (hiddenInput) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
    }
    
    // Save history
    if (window.ContentEditorHistory) {
      setTimeout(() => {
        window.ContentEditorHistory.saveState();
      }, 50);
    }
  },

addGridControls(gridElement) {
    if (!gridElement) return;
    const existingControls = gridElement.querySelector('.content-editor-grid-controls');
    if (existingControls) {
      existingControls.remove();
    }
    
    // Make grid position relative for controls
    if (getComputedStyle(gridElement).position === 'static') {
      gridElement.style.position = 'relative';
    }
    
    const controls = document.createElement('div');
    controls.className = 'content-editor-grid-controls';
    controls.innerHTML = `
      <button type="button" class="grid-control-btn grid-control-edit" title="Edit grid" data-action="edit">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 20h9"></path>
          <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
        </svg>
      </button>
      <button type="button" class="grid-control-btn grid-control-delete" title="Delete grid" data-action="delete">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="3 6 5 6 21 6"></polyline>
          <path d="M19 6l-1 14H6L5 6"></path>
          <path d="M10 11v6"></path>
          <path d="M14 11v6"></path>
          <path d="M9 6V4h6v2"></path>
        </svg>
      </button>
    `;
    
    const editBtn = controls.querySelector('[data-action="edit"]');
    const deleteBtn = controls.querySelector('[data-action="delete"]');
    
    editBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.insertGrid(gridElement);
    });
    
    deleteBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.deleteGridElement(gridElement);
    });
    
    gridElement.appendChild(controls);
  },

deleteGridElement(gridElement) {
    if (!gridElement) return;
    
    // Remove the grid
    gridElement.remove();
    
    // Sync content
    const editor = getActiveEditor();
    if (editor && window.ContentEditorSync) {
      const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
      if (hiddenInput) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
    }
    
    // Save history
    if (window.ContentEditorHistory) {
      setTimeout(() => {
        window.ContentEditorHistory.saveState();
      }, 50);
    }
  },

enhanceExistingGrids(editor) {
    if (!editor) return;
    const grids = editor.querySelectorAll('.content-editor-grid');
    grids.forEach(grid => {
      // Ensure grid has position relative for controls
      if (getComputedStyle(grid).position === 'static') {
        grid.style.position = 'relative';
      }
      
      // Add controls if not present
      if (!grid.querySelector('.content-editor-grid-controls')) {
        this.addGridControls(grid);
      }
      
      // Ensure grid items have proper styling
      const items = grid.querySelectorAll('.content-editor-grid-item');
      items.forEach(item => {
        if (!item.style.minHeight) {
          item.style.minHeight = '100px';
        }
        if (!item.style.padding) {
          item.style.padding = '12px';
        }
        if (!item.style.border) {
          item.style.border = '1px dashed #e5e7eb';
        }
        if (!item.style.borderRadius) {
          item.style.borderRadius = '4px';
        }
        if (!item.style.backgroundColor) {
          item.style.backgroundColor = '#f9fafb';
        }
        if (item.contentEditable !== 'true') {
          item.contentEditable = 'true';
        }
      });
    });
  }
  };
})();
