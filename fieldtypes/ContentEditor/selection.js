// Simple selection management for ContentEditor
window.ContentEditorSelection = {
  savedRange: null,
  savedSelectionData: null, // Store as serializable data for more robust restoration

  // Save current selection
  save() {
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      try {
        const range = selection.getRangeAt(0);
        this.savedRange = range.cloneRange();
        
        // Also save as serializable data for more robust restoration
        this.savedSelectionData = this.serializeRange(range);
        
        return true;
      } catch (e) {
        this.savedRange = null;
        this.savedSelectionData = null;
        return false;
      }
    }
    return false;
  },

  // Serialize range to a more robust format
  serializeRange(range) {
    try {
      const startContainer = range.startContainer;
      const endContainer = range.endContainer;
      
      // Get paths to containers
      const startPath = this.getNodePath(startContainer);
      const endPath = this.getNodePath(endContainer);
      
      return {
        startPath: startPath,
        startOffset: range.startOffset,
        endPath: endPath,
        endOffset: range.endOffset,
        collapsed: range.collapsed
      };
    } catch (e) {
      return null;
    }
  },

  // Get path to a node (array of indices from root to node)
  getNodePath(node) {
    const path = [];
    let current = node;
    
    while (current && current.nodeType !== Node.DOCUMENT_NODE) {
      if (current.parentNode) {
        const index = Array.from(current.parentNode.childNodes).indexOf(current);
        path.unshift(index);
        current = current.parentNode;
      } else {
        break;
      }
    }
    
    return path;
  },

  // Get node from path
  getNodeFromPath(root, path) {
    let node = root;
    for (const index of path) {
      if (node && node.childNodes && node.childNodes[index]) {
        node = node.childNodes[index];
      } else {
        return null;
      }
    }
    return node;
  },

  // Restore saved selection
  restore(rootElement = null) {
    // Try to restore from serialized data first (more robust)
    if (this.savedSelectionData && rootElement) {
      try {
        const startNode = this.getNodeFromPath(rootElement, this.savedSelectionData.startPath);
        const endNode = this.getNodeFromPath(rootElement, this.savedSelectionData.endPath);
        
        if (startNode && endNode) {
          const range = document.createRange();
          range.setStart(startNode, Math.min(this.savedSelectionData.startOffset, startNode.textContent ? startNode.textContent.length : 0));
          range.setEnd(endNode, Math.min(this.savedSelectionData.endOffset, endNode.textContent ? endNode.textContent.length : 0));
          
          const selection = window.getSelection();
          selection.removeAllRanges();
          selection.addRange(range);
          this.savedRange = range.cloneRange();
          return true;
        }
      } catch (e) {
        console.warn('Failed to restore from serialized data:', e);
      }
    }
    
    // Fallback to saved range
    if (!this.savedRange) {
      return false;
    }
    try {
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(this.savedRange.cloneRange());
      return true;
    } catch (e) {
      this.savedRange = null;
      this.savedSelectionData = null;
      return false;
    }
  },

  // Clear saved selection
  clear() {
    this.savedRange = null;
    this.savedSelectionData = null;
  },

  // Get current selection range
  getRange() {
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      return selection.getRangeAt(0);
    }
    return null;
  },

  // Check if there's a selection
  hasSelection() {
    const selection = window.getSelection();
    return selection.rangeCount > 0 && !selection.isCollapsed;
  }
};

