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
    const selection = window.getSelection();
    
    // Try to restore from serialized data first (more robust)
    if (this.savedSelectionData) {
      // If rootElement not provided, try to find it from saved range
      if (!rootElement && this.savedRange) {
        try {
          const commonAncestor = this.savedRange.commonAncestorContainer;
          if (commonAncestor) {
            // Find the editor element (content-editor-area)
            let editor = commonAncestor;
            while (editor && !editor.classList?.contains('content-editor-area')) {
              editor = editor.parentElement;
            }
            if (editor) {
              rootElement = editor;
            }
          }
        } catch (e) {
          // Ignore errors
        }
      }
      
      if (this.savedSelectionData && rootElement) {
        try {
          const startNode = this.getNodeFromPath(rootElement, this.savedSelectionData.startPath);
          const endNode = this.getNodeFromPath(rootElement, this.savedSelectionData.endPath);
          
          if (startNode && endNode) {
            const range = document.createRange();
            const startMaxOffset = startNode.textContent ? startNode.textContent.length : (startNode.childNodes ? startNode.childNodes.length : 0);
            const endMaxOffset = endNode.textContent ? endNode.textContent.length : (endNode.childNodes ? endNode.childNodes.length : 0);
            
            range.setStart(startNode, Math.min(this.savedSelectionData.startOffset, startMaxOffset));
            range.setEnd(endNode, Math.min(this.savedSelectionData.endOffset, endMaxOffset));
            
            selection.removeAllRanges();
            selection.addRange(range);
            this.savedRange = range.cloneRange();
            return true;
          }
        } catch (e) {
          // Fall through to saved range fallback
        }
      }
    }
    
    // Fallback to saved range
    if (!this.savedRange) {
      return false;
    }
    
    try {
      // Validate that the range is still in the DOM
      const startContainer = this.savedRange.startContainer;
      const endContainer = this.savedRange.endContainer;
      
      // Check if containers are still in the DOM
      if (startContainer && endContainer) {
        let startInDOM = false;
        let endInDOM = false;
        
        // Check if node is in document
        try {
          startInDOM = document.contains(startContainer) || startContainer === document || startContainer.nodeType === Node.DOCUMENT_NODE;
          endInDOM = document.contains(endContainer) || endContainer === document || endContainer.nodeType === Node.DOCUMENT_NODE;
        } catch (e) {
          // If document.contains throws, try parentNode traversal
          startInDOM = startContainer.parentNode !== null || startContainer === document;
          endInDOM = endContainer.parentNode !== null || endContainer === document;
        }
        
        if (startInDOM && endInDOM) {
          selection.removeAllRanges();
          selection.addRange(this.savedRange.cloneRange());
          return true;
        }
      }
    } catch (e) {
      // Range is invalid, clear it
      this.savedRange = null;
      this.savedSelectionData = null;
      return false;
    }
    
    return false;
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

