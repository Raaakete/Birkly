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
  }
};

