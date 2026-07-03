// Simple selection management for ContentEditor
window.ContentEditorSelection = {
  savedRange: null,
  savedSelectionData: null,
  savedEditorRoot: null,

  findEditorRoot(node) {
    let current = node;
    if (current && current.nodeType === Node.TEXT_NODE) {
      current = current.parentNode;
    }
    while (current && current.nodeType !== Node.DOCUMENT_NODE) {
      if (current.classList && current.classList.contains('content-editor-area')) {
        return current;
      }
      current = current.parentNode;
    }
    return null;
  },

  // Save current selection
  save() {
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      try {
        const range = selection.getRangeAt(0);
        this.savedRange = range.cloneRange();
        this.savedEditorRoot = this.findEditorRoot(range.startContainer)
          || this.findEditorRoot(range.endContainer);
        this.savedSelectionData = this.serializeRange(range, this.savedEditorRoot);
        return true;
      } catch (e) {
        this.savedRange = null;
        this.savedSelectionData = null;
        this.savedEditorRoot = null;
        return false;
      }
    }
    return false;
  },

  // Serialize range to a more robust format (paths relative to editor root when available)
  serializeRange(range, editorRoot = null) {
    try {
      const startContainer = range.startContainer;
      const endContainer = range.endContainer;
      const root = editorRoot || this.findEditorRoot(startContainer) || this.findEditorRoot(endContainer);

      const startPath = this.getNodePath(startContainer, root);
      const endPath = this.getNodePath(endContainer, root);

      if (root && (startPath === null || endPath === null)) {
        return null;
      }

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

  // Get path to a node (array of child indices from root to node)
  getNodePath(node, root = null) {
    const path = [];
    let current = node;

    while (current && current !== root && current.nodeType !== Node.DOCUMENT_NODE) {
      if (current.parentNode) {
        const index = Array.from(current.parentNode.childNodes).indexOf(current);
        if (index < 0) {
          return null;
        }
        path.unshift(index);
        current = current.parentNode;
      } else {
        break;
      }
    }

    if (root && current !== root) {
      return null;
    }

    return path;
  },

  // Get node from path
  getNodeFromPath(root, path) {
    if (!root || !path) return null;
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

  cloneSavedData() {
    return this.savedSelectionData
      ? JSON.parse(JSON.stringify(this.savedSelectionData))
      : null;
  },

  cloneSavedRange() {
    return this.savedRange ? this.savedRange.cloneRange() : null;
  },

  restoreFromSnapshot(editor, data, range) {
    if (data && editor) {
      this.savedSelectionData = data;
      this.savedEditorRoot = editor;
      if (this.restore(editor)) {
        return true;
      }
    }
    if (range) {
      try {
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range.cloneRange());
        this.savedRange = range.cloneRange();
        return true;
      } catch (e) {
        return false;
      }
    }
    return false;
  },

  // Restore saved selection
  restore(rootElement = null) {
    const selection = window.getSelection();

    if (!rootElement) {
      rootElement = this.savedEditorRoot;
    }

    if (!rootElement && this.savedRange) {
      try {
        const commonAncestor = this.savedRange.commonAncestorContainer;
        if (commonAncestor) {
          rootElement = this.findEditorRoot(commonAncestor);
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
          const startMaxOffset = startNode.nodeType === Node.TEXT_NODE
            ? startNode.textContent.length
            : (startNode.childNodes ? startNode.childNodes.length : 0);
          const endMaxOffset = endNode.nodeType === Node.TEXT_NODE
            ? endNode.textContent.length
            : (endNode.childNodes ? endNode.childNodes.length : 0);

          range.setStart(startNode, Math.min(this.savedSelectionData.startOffset, startMaxOffset));
          range.setEnd(endNode, Math.min(this.savedSelectionData.endOffset, endMaxOffset));

          selection.removeAllRanges();
          selection.addRange(range);
          this.savedRange = range.cloneRange();
          this.savedEditorRoot = rootElement;
          return true;
        }
      } catch (e) {
        // Fall through to saved range fallback
      }
    }

    if (!this.savedRange) {
      return false;
    }

    try {
      const startContainer = this.savedRange.startContainer;
      const endContainer = this.savedRange.endContainer;

      if (startContainer && endContainer) {
        let startInDOM = false;
        let endInDOM = false;

        try {
          startInDOM = document.contains(startContainer) || startContainer === document || startContainer.nodeType === Node.DOCUMENT_NODE;
          endInDOM = document.contains(endContainer) || endContainer === document || endContainer.nodeType === Node.DOCUMENT_NODE;
        } catch (e) {
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
      this.savedRange = null;
      this.savedSelectionData = null;
      this.savedEditorRoot = null;
      return false;
    }

    return false;
  },

  clear() {
    this.savedRange = null;
    this.savedSelectionData = null;
    this.savedEditorRoot = null;
  },

  getRange() {
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      return selection.getRangeAt(0);
    }
    return null;
  },

  hasSelection() {
    const selection = window.getSelection();
    return selection.rangeCount > 0 && !selection.isCollapsed;
  }
};
