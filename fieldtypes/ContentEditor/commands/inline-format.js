// Inline formatting commands for ContentEditor (P2-M3-T7)
window.ContentEditorInlineFormat = {

  getCurrentBlock(element = null) {
    return window.ContentEditorDOMUtils.getCurrentBlock(element);
  },

  getTextNodes(element) {
    return window.ContentEditorDOMUtils.getTextNodes(element);
  },

  isInlineTag(tagName) {
    return window.ContentEditorDOMUtils.isInlineTag(tagName);
  },

  isBlockTag(tagName) {
    return window.ContentEditorDOMUtils.isBlockTag(tagName);
  },

  isWrappedInTag(element, tagName) {
  if (!element) return false;
  
  const tags = Array.isArray(tagName) ? tagName.map(t => t.toUpperCase()) : [tagName.toUpperCase()];
  let current = element.nodeType === Node.TEXT_NODE ? element.parentElement : element;
  
  // Also check for alternative tags (b/strong, i/em, s/del)
  const tagMap = {
    'B': ['B', 'STRONG'],
    'STRONG': ['B', 'STRONG'],
    'I': ['I', 'EM'],
    'EM': ['I', 'EM'],
    'S': ['S', 'DEL', 'STRIKE'],
    'DEL': ['S', 'DEL', 'STRIKE'],
    'U': ['U']
  };
  
  while (current && current !== document.body) {
    const currentTag = current.tagName.toUpperCase();
    
    // Check direct match
    if (tags.includes(currentTag)) {
      return true;
    }
    
    // Check alternative tags
    for (const tag of tags) {
      if (tagMap[tag] && tagMap[tag].includes(currentTag)) {
        return true;
      }
    }
    
    // Don't go beyond contentEditable area
    if (current.contentEditable === 'true') {
      break;
    }
    current = current.parentElement;
  }
  return false;
},

  wrapWithTag(tagName, value = null) {
  const selection = window.getSelection();
  
  // Normalize tag names
  const tagMap = {
    'bold': 'b',
    'italic': 'i',
    'underline': 'u',
    'strikethrough': 's'
  };
  tagName = tagMap[tagName] || tagName;
  
  // Alternative tags for checking
  const altTags = {
    'b': ['b', 'strong'],
    'strong': ['b', 'strong'],
    'i': ['i', 'em'],
    'em': ['i', 'em'],
    's': ['s', 'del'],
    'del': ['s', 'del'],
    'u': ['u']
  };
  const checkTags = altTags[tagName.toLowerCase()] || [tagName];
  
  // For block-level tags (headings, paragraphs), always use formatBlock
  if (this.isBlockTag(tagName)) {
    this.formatBlock(tagName);
    return;
  }
  
  // From here on, we're dealing with inline tags (b, i, u, s)
  
  if (!selection.rangeCount || selection.isCollapsed) {
    // No selection or collapsed: format entire block's CONTENT (not the block itself)
    const block = this.getCurrentBlock();
    if (!block) return;
    
    // Check if block's content is already wrapped in this inline tag
    const isWrapped = checkTags.some(tag => {
      // Check if block itself is wrapped (shouldn't happen, but check anyway)
      if (this.isWrappedInTag(block, tag)) {
        return true;
      }
      // Check if all direct children are wrapped in this tag
      const children = Array.from(block.childNodes);
      if (children.length === 0) return false;
      
      // If there's a single child that is the inline tag, it's wrapped
      if (children.length === 1 && children[0].nodeType === Node.ELEMENT_NODE) {
        const childTag = children[0].tagName.toUpperCase();
        if (checkTags.some(t => t.toUpperCase() === childTag)) {
          return true;
        }
      }
      
      // Check if all text content is wrapped
      const textNodes = this.getTextNodes(block);
      if (textNodes.length > 0) {
        return textNodes.every(tn => {
          let parent = tn.parentElement;
          while (parent && parent !== block) {
            const parentTag = parent.tagName.toUpperCase();
            if (checkTags.some(t => t.toUpperCase() === parentTag)) {
              return true;
            }
            parent = parent.parentElement;
          }
          return false;
        });
      }
      return false;
    });
    
    if (isWrapped) {
      // Unwrap: remove inline tags from block's content
      this.unwrapInlineFromBlock(block, checkTags);
    } else {
      // Wrap: wrap block's CONTENT with inline tag (not the block itself)
      this.wrapBlockContent(block, tagName);
    }
    return;
  }

  const range = selection.getRangeAt(0);
  
  // Check if already wrapped in any alternative tag
  const isWrapped = checkTags.some(tag => 
    this.isWrappedInTag(range.commonAncestorContainer, tag)
  );
  
  if (isWrapped) {
    // Unwrap all alternative tags - but only once
    for (const tag of checkTags) {
      if (this.isWrappedInTag(range.commonAncestorContainer, tag)) {
        this.unwrapSelection(range, tag);
        break; // Only unwrap once
      }
    }
  } else {
    // Wrap selection with inline tag
    try {
      // Ensure range is valid
      if (range.collapsed) {
        // Collapsed selection, wrap the block's content instead
        const block = this.getCurrentBlock();
        if (block) {
          const isWrapped = checkTags.some(tag => this.isWrappedInTag(block, tag));
          if (!isWrapped) {
            this.wrapBlockContent(block, tagName);
          }
        }
        return;
      }
      
      const contents = range.extractContents();
      // Remove empty text nodes
      const fragment = document.createDocumentFragment();
      Array.from(contents.childNodes).forEach(node => {
        if (node.nodeType === Node.TEXT_NODE && !node.textContent.trim()) {
          return; // Skip empty text nodes
        }
        fragment.appendChild(node);
      });
      
      if (fragment.childNodes.length === 0) {
        // No content to wrap, wrap block content instead
        const block = this.getCurrentBlock();
        if (block) {
          const isWrapped = checkTags.some(tag => this.isWrappedInTag(block, tag));
          if (!isWrapped) {
            this.wrapBlockContent(block, tagName);
          }
        }
        return;
      }
      
      const wrapper = document.createElement(tagName);
      if (value) {
        wrapper.setAttribute(tagName === 'a' ? 'href' : 'value', value);
      }
      wrapper.appendChild(fragment);
      range.insertNode(wrapper);
      
      // Select the wrapped content
      const newRange = document.createRange();
      newRange.selectNodeContents(wrapper);
      selection.removeAllRanges();
      selection.addRange(newRange);
    } catch (e) {
      console.error('Error wrapping selection:', e);
      // Fallback: try to wrap block content
      const block = this.getCurrentBlock();
      if (block) {
        const isWrapped = checkTags.some(tag => this.isWrappedInTag(block, tag));
        if (!isWrapped) {
          this.wrapBlockContent(block, tagName);
        }
      }
    }
  }
},

  wrapBlockContent(block, inlineTag) {
  // Don't wrap if block is already wrapped at the block level
  if (this.isWrappedInTag(block, inlineTag)) {
    return;
  }
  
  // Check if all content is already wrapped
  const children = Array.from(block.childNodes);
  if (children.length === 0) {
    // Empty block, just add a br wrapped in the inline tag
    const wrapper = document.createElement(inlineTag);
    wrapper.innerHTML = '<br>';
    block.appendChild(wrapper);
    return;
  }
  
  // Check if all direct children are already wrapped in this tag
  const allWrapped = children.every(child => {
    if (child.nodeType === Node.TEXT_NODE) {
      // Text node - check if parent is wrapped
      return child.parentElement && this.isWrappedInTag(child.parentElement, inlineTag);
    } else if (child.nodeType === Node.ELEMENT_NODE) {
      return this.isWrappedInTag(child, inlineTag);
    }
    return false;
  });
  
  if (allWrapped) {
    // Already wrapped, don't double-wrap
    return;
  }
  
  // Create wrapper
  const wrapper = document.createElement(inlineTag);
  
  // Move all children of block into wrapper
  while (block.firstChild) {
    wrapper.appendChild(block.firstChild);
  }
  
  // Add wrapper to block
  block.appendChild(wrapper);
},

  unwrapInlineFromBlock(block, tags) {
  // Find and unwrap inline tags that wrap block's content
  const children = Array.from(block.childNodes);
  
  children.forEach(child => {
    if (child.nodeType === Node.ELEMENT_NODE) {
      const tagUpper = child.tagName.toUpperCase();
      if (tags.some(t => t.toUpperCase() === tagUpper)) {
        // This is an inline tag we want to unwrap
        while (child.firstChild) {
          block.insertBefore(child.firstChild, child);
        }
        block.removeChild(child);
      } else {
        // Recursively unwrap from child
        this.unwrapInlineFromBlock(child, tags);
      }
    }
  });
},

  wrapElement(element, tagName) {
  // Only use this for inline tags wrapping other inline elements
  // For blocks, use formatBlock. For block content, use wrapBlockContent.
  if (this.isBlockTag(tagName)) {
    console.warn('wrapElement should not be used for block tags. Use formatBlock instead.');
    return;
  }
  const wrapper = document.createElement(tagName);
  element.parentNode.insertBefore(wrapper, element);
  wrapper.appendChild(element);
},

  unwrapTag(element, tagName) {
  const tags = Array.isArray(tagName) ? tagName.map(t => t.toUpperCase()) : [tagName.toUpperCase()];
  
  // Alternative tags
  const altTags = {
    'B': ['B', 'STRONG'],
    'STRONG': ['B', 'STRONG'],
    'I': ['I', 'EM'],
    'EM': ['I', 'EM'],
    'S': ['S', 'DEL', 'STRIKE'],
    'DEL': ['S', 'DEL', 'STRIKE'],
    'U': ['U']
  };
  
  // Expand tags with alternatives
  const allTags = [];
  tags.forEach(tag => {
    allTags.push(tag);
    if (altTags[tag]) {
      allTags.push(...altTags[tag]);
    }
  });
  
  let current = element;
  
  while (current && current !== document.body) {
    if (allTags.includes(current.tagName.toUpperCase())) {
      const parent = current.parentNode;
      while (current.firstChild) {
        parent.insertBefore(current.firstChild, current);
      }
      parent.removeChild(current);
      return;
    }
    current = current.parentElement;
  }
},

  unwrapSelection(range, tagName) {
  const tags = Array.isArray(tagName) ? tagName.map(t => t.toUpperCase()) : [tagName.toUpperCase()];
  
  // Alternative tags
  const altTags = {
    'B': ['B', 'STRONG'],
    'STRONG': ['B', 'STRONG'],
    'I': ['I', 'EM'],
    'EM': ['I', 'EM'],
    'S': ['S', 'DEL', 'STRIKE'],
    'DEL': ['S', 'DEL', 'STRIKE'],
    'U': ['U']
  };
  
  // Expand tags with alternatives
  const allTags = [];
  tags.forEach(tag => {
    allTags.push(tag);
    if (altTags[tag]) {
      allTags.push(...altTags[tag]);
    }
  });
  
  let container = range.commonAncestorContainer;
  
  if (container.nodeType === Node.TEXT_NODE) {
    container = container.parentElement;
  }
  
  while (container && container !== document.body) {
    if (allTags.includes(container.tagName.toUpperCase())) {
      const parent = container.parentNode;
      
      try {
        const beforeRange = document.createRange();
        beforeRange.setStartBefore(container);
        beforeRange.setEnd(range.startContainer, range.startOffset);
        
        const afterRange = document.createRange();
        afterRange.setStart(range.endContainer, range.endOffset);
        afterRange.setEndAfter(container);
        
        const beforeFragment = beforeRange.extractContents();
        const afterFragment = afterRange.extractContents();
        const contents = Array.from(container.childNodes);
        
        parent.insertBefore(beforeFragment, container);
        contents.forEach(child => {
          parent.insertBefore(child, container);
        });
        parent.insertBefore(afterFragment, container);
        parent.removeChild(container);
        
        // Restore selection - try to maintain original selection
        const sel = window.getSelection();
        try {
          const newRange = document.createRange();
          if (beforeFragment.firstChild) {
            newRange.setStartAfter(beforeFragment.lastChild);
          } else if (afterFragment.firstChild) {
            newRange.setStartBefore(afterFragment.firstChild);
          } else {
            newRange.setStart(parent, 0);
          }
          newRange.collapse(true);
          sel.removeAllRanges();
          sel.addRange(newRange);
        } catch (e) {
          // Fallback: select after unwrapped content
          const newRange = document.createRange();
          newRange.selectNodeContents(parent);
          newRange.collapse(false);
          sel.removeAllRanges();
          sel.addRange(newRange);
        }
      } catch (e) {
        console.error('Error unwrapping selection:', e);
      }
      return;
    }
    container = container.parentElement;
  }
}
};
