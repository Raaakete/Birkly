// History management for ContentEditor (Undo/Redo)
window.ContentEditorHistory = {
  /**
   * Initialize history for an editor instance
   * @param {Element} editor - The editor element
   * @param {Element} hiddenInput - The hidden input element
   */
  init(editor, hiddenInput) {
    this.editor = editor;
    this.hiddenInput = hiddenInput;
    this.history = [];
    this.currentIndex = -1;
    this.maxHistorySize = 50; // Limit history size
    this.isUndoing = false;
    this.isRedoing = false;
    
    // Save initial state
    this.saveState();
    
    // Debounce timer for input events
    this.debounceTimer = null;
    this.debounceDelay = 300; // ms
  },

  /**
   * Save current editor state to history
   */
  saveState() {
    // Don't save if we're in the middle of undo/redo
    if (this.isUndoing || this.isRedoing) {
      return;
    }
    
    const html = this.editor.innerHTML;
    
    // Don't save if content hasn't changed
    if (this.history.length > 0 && this.history[this.currentIndex] === html) {
      return;
    }
    
    // Remove any states after current index (when user makes new changes after undo)
    if (this.currentIndex < this.history.length - 1) {
      this.history = this.history.slice(0, this.currentIndex + 1);
    }
    
    // Add new state
    this.history.push(html);
    this.currentIndex = this.history.length - 1;
    
    // Limit history size
    if (this.history.length > this.maxHistorySize) {
      this.history.shift();
      this.currentIndex--;
    }
    
    // Update toolbar button states
    this.updateToolbarButtons();
  },

  /**
   * Save state with debouncing (for input events)
   */
  saveStateDebounced() {
    if (this.debounceTimer) {
      clearTimeout(this.debounceTimer);
    }
    
    this.debounceTimer = setTimeout(() => {
      this.saveState();
    }, this.debounceDelay);
  },

  /**
   * Undo last action
   */
  undo() {
    if (this.currentIndex <= 0) {
      return false; // Nothing to undo
    }
    
    this.isUndoing = true;
    this.currentIndex--;
    this.restoreState(this.history[this.currentIndex]);
    this.isUndoing = false;
    
    this.updateToolbarButtons();
    return true;
  },

  /**
   * Redo last undone action
   */
  redo() {
    if (this.currentIndex >= this.history.length - 1) {
      return false; // Nothing to redo
    }
    
    this.isRedoing = true;
    this.currentIndex++;
    this.restoreState(this.history[this.currentIndex]);
    this.isRedoing = false;
    
    this.updateToolbarButtons();
    return true;
  },

  /**
   * Restore editor to a specific state
   */
  restoreState(html) {
    // Save current selection
    const selection = window.getSelection();
    let savedRange = null;
    
    if (selection.rangeCount > 0) {
      try {
        savedRange = selection.getRangeAt(0).cloneRange();
      } catch (e) {
        // Selection might be invalid, ignore
      }
    }
    
    // Restore HTML
    this.editor.innerHTML = html;
    
    // Sync to hidden input
    if (window.ContentEditorSync) {
      window.ContentEditorSync.toHidden(this.editor, this.hiddenInput);
    }
    
    // Try to restore selection (best effort)
    if (savedRange) {
      try {
        selection.removeAllRanges();
        selection.addRange(savedRange);
      } catch (e) {
        // Selection might be invalid after content change, place cursor at end
        const range = document.createRange();
        range.selectNodeContents(this.editor);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
      }
    } else {
      // No saved selection, place cursor at end
      const range = document.createRange();
      range.selectNodeContents(this.editor);
      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
    }
    
    // Update toolbar state
    if (window.ContentEditorToolbar) {
      const toolbar = this.editor.closest('.content-editor-wrapper')?.querySelector('.content-editor-toolbar') ||
                     document.querySelector('.content-editor-toolbar');
      if (toolbar) {
        window.ContentEditorToolbar.updateState(this.editor, toolbar);
      }
    }
  },

  /**
   * Update undo/redo button states in toolbar
   */
  updateToolbarButtons() {
    const toolbar = this.editor.closest('.content-editor-wrapper')?.querySelector('.content-editor-toolbar') ||
                   document.querySelector('.content-editor-toolbar');
    if (!toolbar) return;
    
    const undoBtn = toolbar.querySelector('[data-command="undo"]');
    const redoBtn = toolbar.querySelector('[data-command="redo"]');
    
    if (undoBtn) {
      undoBtn.disabled = this.currentIndex <= 0;
      undoBtn.classList.toggle('disabled', this.currentIndex <= 0);
    }
    
    if (redoBtn) {
      redoBtn.disabled = this.currentIndex >= this.history.length - 1;
      redoBtn.classList.toggle('disabled', this.currentIndex >= this.history.length - 1);
    }
  },

  /**
   * Check if undo is available
   */
  canUndo() {
    return this.currentIndex > 0;
  },

  /**
   * Check if redo is available
   */
  canRedo() {
    return this.currentIndex < this.history.length - 1;
  }
};

