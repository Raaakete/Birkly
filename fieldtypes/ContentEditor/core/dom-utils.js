// DOM manipulation utilities for ContentEditor
window.ContentEditorDOMUtils = {
  /**
   * Get current block element (p, div, h1, etc.)
   * @param {Element|null} element - Starting element, or null to use selection
   * @returns {Element|null} - The current block element or null
   */
  getCurrentBlock(element = null) {
    if (!element) {
      const selection = window.getSelection();
      if (selection.rangeCount > 0) {
        element = selection.getRangeAt(0).commonAncestorContainer;
      } else {
        return null;
      }
    }
    
    // Walk up the DOM tree to find block element
    while (element && element.nodeType !== Node.ELEMENT_NODE) {
      element = element.parentNode;
    }
    
    if (!element) return null;
    
    // Find the contentEditable editor area (should be a div with class content-editor-area)
    let editorArea = element;
    while (editorArea && editorArea.nodeType === Node.ELEMENT_NODE) {
      if (editorArea.classList && editorArea.classList.contains('content-editor-area')) {
        break;
      }
      editorArea = editorArea.parentElement;
    }
    
    if (!editorArea || !editorArea.classList || !editorArea.classList.contains('content-editor-area')) {
      return null;
    }
    
    // Block tags that are valid content blocks (NOT the editor area itself)
    const blockTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'LI', 'BLOCKQUOTE', 'PRE'];
    
    // Walk up from element to find a block, but stop at editor area
    let current = element;
    while (current && current !== editorArea && current.nodeType === Node.ELEMENT_NODE) {
      if (current.tagName && blockTags.includes(current.tagName)) {
        return current;
      }
      // If we hit a div that's not the editor area, check if it's a content div
      if (current.tagName === 'DIV' && current !== editorArea) {
        // Check if this div contains block elements or is a content container
        const hasBlocks = Array.from(current.children).some(child => 
          child.nodeType === Node.ELEMENT_NODE && child.tagName && blockTags.includes(child.tagName)
        );
        if (!hasBlocks && current.textContent && current.textContent.trim()) {
          // This div has content but no block children, treat it as a block
          return current;
        }
      }
      current = current.parentElement;
    }
    
    // If no block found, check if we're directly in the editor area
    if (element === editorArea || element.parentElement === editorArea) {
      // We're at the editor level, need to wrap in a paragraph
      return null;
    }
    
    return null;
  },

  /**
   * Get all text nodes in an element
   * @param {Element} element - The element to search
   * @returns {Text[]} - Array of text nodes
   */
  getTextNodes(element) {
    const textNodes = [];
    const walker = document.createTreeWalker(
      element,
      NodeFilter.SHOW_TEXT,
      null,
      false
    );
    let node;
    while (node = walker.nextNode()) {
      textNodes.push(node);
    }
    return textNodes;
  },

  /**
   * Find the editor area element
   * @param {Element} element - Starting element
   * @returns {Element|null} - The editor area element or null
   */
  findEditorArea(element) {
    if (!element) {
      const selection = window.getSelection();
      if (selection.rangeCount > 0) {
        element = selection.getRangeAt(0).commonAncestorContainer;
      } else {
        return document.querySelector('.content-editor-area[contenteditable="true"]');
      }
    }
    
    while (element && element.nodeType === Node.ELEMENT_NODE) {
      if (element.classList && element.classList.contains('content-editor-area')) {
        return element;
      }
      element = element.parentElement;
    }
    
    return document.querySelector('.content-editor-area[contenteditable="true"]');
  },

  /**
   * Check if tag is inline or block
   * @param {string} tagName - Tag name to check
   * @returns {boolean} - True if inline tag
   */
  isInlineTag(tagName) {
    const inlineTags = ['B', 'STRONG', 'I', 'EM', 'U', 'S', 'DEL', 'A', 'SPAN', 'CODE'];
    return inlineTags.includes((tagName || '').toUpperCase());
  },

  /**
   * Check if tag is block-level
   * @param {string} tagName - Tag name to check
   * @returns {boolean} - True if block tag
   */
  isBlockTag(tagName) {
    const blockTags = ['P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'PRE'];
    return blockTags.includes((tagName || '').toUpperCase());
  },

  /**
   * Restore saved selection when present, then resolve insert anchor in editor.
   * @returns {{ editor: Element, block: Element|null, range: Range|null }}
   */
  resolveInsertAnchor(editor) {
    const targetEditor = editor || this.findEditorArea();
    if (!targetEditor) {
      return { editor: null, block: null, range: null };
    }

    if (window.ContentEditorSelection) {
      window.ContentEditorSelection.restore(targetEditor);
    }

    const selection = window.getSelection();
    let range = null;
    if (selection && selection.rangeCount > 0) {
      const candidate = selection.getRangeAt(0);
      const host = candidate.commonAncestorContainer.nodeType === Node.TEXT_NODE
        ? candidate.commonAncestorContainer.parentNode
        : candidate.commonAncestorContainer;
      if (host && targetEditor.contains(host)) {
        range = candidate;
      }
    }

    let block = this.getCurrentBlock(range ? range.commonAncestorContainer : null);
    return { editor: targetEditor, block, range };
  },

  /**
   * Insert one or more nodes at the current caret / block in the editor.
   * @returns {{ editor: Element, inserted: Element[], trailing: Element|null }|null}
   */
  insertNodesAtCaret(editor, nodes, options = {}) {
    const nodeList = (Array.isArray(nodes) ? nodes : [nodes]).filter(Boolean);
    if (!nodeList.length) return null;

    const addTrailingParagraph = options.addTrailingParagraph !== false;
    const { editor: targetEditor, block, range } = this.resolveInsertAnchor(editor);
    if (!targetEditor) return null;

    const doc = targetEditor.ownerDocument || document;
    let referenceNode = null;

    if (block && block.parentNode && targetEditor.contains(block)) {
      referenceNode = block;
    } else if (range) {
      const marker = doc.createElement('span');
      marker.setAttribute('data-ce-insert-marker', 'true');
      marker.style.display = 'none';
      try {
        range.collapse(true);
        range.insertNode(marker);
        referenceNode = marker;
      } catch (e) {
        marker.remove();
      }
    }

    const parent = referenceNode && referenceNode.parentNode ? referenceNode.parentNode : targetEditor;
    let insertBefore = referenceNode ? referenceNode.nextSibling : null;

    nodeList.forEach((node) => {
      parent.insertBefore(node, insertBefore);
      insertBefore = node.nextSibling;
    });

    if (referenceNode && referenceNode.getAttribute && referenceNode.getAttribute('data-ce-insert-marker') === 'true') {
      referenceNode.remove();
    }

    let trailing = null;
    if (addTrailingParagraph) {
      trailing = doc.createElement('p');
      trailing.innerHTML = '<br>';
      const lastNode = nodeList[nodeList.length - 1];
      if (lastNode.parentNode) {
        lastNode.parentNode.insertBefore(trailing, lastNode.nextSibling);
      }
    }

    return { editor: targetEditor, inserted: nodeList, trailing };
  }
};

