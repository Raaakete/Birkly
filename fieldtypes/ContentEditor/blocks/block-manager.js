// ContentEditor block lifecycle & management (P3-M12)
window.ContentEditorBlockManager = {
  getRegistry() {
    return window.ContentEditorBlockRegistry || null;
  },

  getBlockFormat() {
    return window.ContentEditorBlockFormat || null;
  },

  getActiveEditor() {
    if (window.ContentEditorState && window.ContentEditorState.activeEditor) {
      return window.ContentEditorState.activeEditor;
    }
    return document.querySelector('.content-editor-area[contenteditable="true"]');
  },

  getCurrentBlock() {
    const dom = window.ContentEditorDOMUtils;
    if (dom && dom.getCurrentBlock) {
      return dom.getCurrentBlock();
    }
    if (window.ContentEditorCommands && window.ContentEditorCommands.getCurrentBlock) {
      return window.ContentEditorCommands.getCurrentBlock();
    }
    return null;
  },

  detectBlock(element) {
    const registry = this.getRegistry();
    return registry ? registry.detect(element) : null;
  },

  /**
   * Annotate top-level blocks in the editor (editor-only metadata).
   */
  initializeEditor(editor) {
    if (!editor || !editor.classList.contains('content-editor-area')) return;

    const registry = this.getRegistry();
    if (!registry) return;

    const topLevelSelector = ':scope > p, :scope > h1, :scope > h2, :scope > h3, :scope > h4, :scope > h5, :scope > h6, :scope > ul, :scope > ol, :scope > hr, :scope > blockquote, :scope > pre';
    editor.querySelectorAll(topLevelSelector).forEach((el) => {
      const type = registry.inferType(el);
      if (type) {
        el.dataset.blockType = type;
      }
    });

    editor.querySelectorAll('table.content-editor-table, table[data-editable-table="true"]').forEach((table) => {
      table.dataset.blockType = 'table';
      if (window.ContentEditorCommands && window.ContentEditorCommands.initTableControls) {
        window.ContentEditorCommands.initTableControls(table);
      }
    });

    editor.querySelectorAll('table').forEach((table) => {
      if (!table.hasAttribute('data-editable-table')) {
        table.className = (table.className ? table.className + ' ' : '') + 'content-editor-table';
        table.setAttribute('data-editable-table', 'true');
        table.dataset.blockType = 'table';
        if (window.ContentEditorCommands && window.ContentEditorCommands.initTableControls) {
          window.ContentEditorCommands.initTableControls(table);
        }
      }
    });
  },

  insertAfterBlock(parent, referenceBlock, nodes) {
    const list = Array.isArray(nodes) ? nodes : [nodes];
    let insertBefore = referenceBlock ? referenceBlock.nextSibling : null;
    list.forEach((node) => {
      if (!node) return;
      parent.insertBefore(node, insertBefore);
      insertBefore = node.nextSibling;
    });
  },

  focusElement(element, collapseToStart) {
    if (!element) return;
    const selection = window.getSelection();
    const range = document.createRange();
    range.selectNodeContents(element);
    range.collapse(Boolean(collapseToStart));
    selection.removeAllRanges();
    selection.addRange(range);
    if (element.focus) {
      element.focus();
    }
  },

  syncEditor(editor) {
    if (!editor) return;
    const hiddenInput = document.getElementById('field_' + (editor.dataset.fieldName || ''));
    if (hiddenInput && window.ContentEditorSync) {
      window.ContentEditorSync.toHidden(editor, hiddenInput, true);
    }
  },

  /**
   * Insert a registered block at the current selection.
   */
  insert(editor, type, options = {}) {
    const targetEditor = editor || this.getActiveEditor();
    const registry = this.getRegistry();
    if (!targetEditor || !registry) return false;

    const created = registry.create(type, options);
    if (!created) return false;

    const nodes = Array.isArray(created) ? created : [created];
    const dom = window.ContentEditorDOMUtils;
    const result = dom && dom.insertNodesAtCaret
      ? dom.insertNodesAtCaret(targetEditor, nodes, { addTrailingParagraph: true })
      : null;

    if (!result) {
      nodes.forEach((node) => targetEditor.appendChild(node));
    }

    nodes.forEach((node) => {
      const inferred = registry.inferType(node) || type;
      if (node.dataset) {
        node.dataset.blockType = inferred;
      }
      if (type === 'table' || inferred === 'table') {
        const table = node.tagName === 'TABLE' ? node : node.querySelector('table');
        if (table && window.ContentEditorCommands && window.ContentEditorCommands.initTableControls) {
          window.ContentEditorCommands.initTableControls(table);
        }
      }
    });

    let followUp = result && result.trailing ? result.trailing : null;
    if (!followUp) {
      followUp = emptyParagraph();
      const lastNode = nodes[nodes.length - 1];
      if (lastNode && lastNode.parentNode) {
        lastNode.parentNode.insertBefore(followUp, lastNode.nextSibling);
      } else {
        targetEditor.appendChild(followUp);
      }
    }

    if (type === 'table') {
      const tableCell = nodes[0] && nodes[0].querySelector ? nodes[0].querySelector('td') : null;
      this.focusElement(tableCell || followUp, true);
    } else if (type === 'divider') {
      this.focusElement(followUp, true);
    } else {
      this.focusElement(nodes[0] || followUp, false);
    }

    this.initializeEditor(targetEditor);
    this.syncEditor(targetEditor);
    return true;
  },

  insertDivider(editor) {
    return this.insert(editor, 'divider');
  },

  insertTable(editor, rows, cols) {
    return this.insert(editor, 'table', { rows, cols });
  },

  /**
   * Format current block via registered types (delegates DOM work to block-format).
   */
  formatBlock(tagName) {
    const registry = this.getRegistry();
    const blockFormat = this.getBlockFormat();
    if (!registry || !blockFormat) {
      return false;
    }

    const type = registry.tagToType(tagName);
    if (!type && tagName !== 'li') {
      console.warn('ContentEditorBlockManager: unregistered block tag:', tagName);
    }

    // Call underlying DOM implementation (avoid re-entry through block-format wrapper)
    blockFormat._inFormatBlock = true;
    try {
      blockFormat.formatBlockImpl(tagName);
    } finally {
      blockFormat._inFormatBlock = false;
    }

    const block = this.getCurrentBlock();
    if (block && block.dataset) {
      const inferred = registry.inferType(block) || registry.tagToType(tagName);
      if (inferred) {
        block.dataset.blockType = inferred;
      }
    }
    return true;
  },

  toggleList(listType) {
    const blockFormat = this.getBlockFormat();
    if (!blockFormat) return false;

    blockFormat._inToggleList = true;
    try {
      blockFormat.toggleListImpl(listType);
    } finally {
      blockFormat._inToggleList = false;
    }

    const block = this.getCurrentBlock();
    const registry = this.getRegistry();
    if (block && registry) {
      const list = block.tagName === 'LI' ? block.parentElement : block;
      if (list && list.dataset) {
        const inferred = registry.inferType(list);
        if (inferred) {
          list.dataset.blockType = inferred;
        }
      }
    }
    return true;
  },
};

function emptyParagraph() {
  const p = document.createElement('p');
  p.innerHTML = '<br>';
  p.dataset.blockType = 'paragraph';
  return p;
}
