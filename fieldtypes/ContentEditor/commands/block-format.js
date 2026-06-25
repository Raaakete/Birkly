// Block formatting commands for ContentEditor (P2-M3-T7)
window.ContentEditorBlockFormat = {

  getCurrentBlock(element = null) {
    return window.ContentEditorDOMUtils.getCurrentBlock(element);
  },

  getTextNodes(element) {
    return window.ContentEditorDOMUtils.getTextNodes(element);
  },

  formatBlock(tagName) {
  if (window.ContentEditorBlockManager && !this._inFormatBlock) {
    return window.ContentEditorBlockManager.formatBlock(tagName);
  }

  return this.formatBlockImpl(tagName);
  },

  formatBlockImpl(tagName) {
  // Normalize tag name to lowercase
  tagName = (tagName || 'p').toLowerCase();
  
  // Validate tag name
  const validBlockTags = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'pre'];
  if (!validBlockTags.includes(tagName)) {
    console.error('Invalid block tag:', tagName);
    return;
  }
  
  const selection = window.getSelection();
  let block = this.getCurrentBlock();
  
  // Find the editor area (should be div.content-editor-area)
  let editorArea = null;
  if (selection.rangeCount > 0) {
    let element = selection.getRangeAt(0).commonAncestorContainer;
    while (element && element.nodeType === Node.ELEMENT_NODE) {
      if (element.classList && element.classList.contains('content-editor-area')) {
        editorArea = element;
        break;
      }
      element = element.parentElement;
    }
  }
  
  // Fallback: query selector
  if (!editorArea) {
    editorArea = document.querySelector('.content-editor-area[contenteditable="true"]');
  }
  
  if (!editorArea || !editorArea.classList || !editorArea.classList.contains('content-editor-area')) {
    console.error('Editor area not found');
    return;
  }
  
  // CRITICAL: Never replace the editor area itself!
  // The editor area must always remain a div with class content-editor-area
  
  if (!block) {
    // No block found - we need to create one or wrap existing content
    if (!selection.rangeCount) {
      // No selection, create at end of editor
      const newBlock = document.createElement(tagName);
      newBlock.innerHTML = '<br>';
      editorArea.appendChild(newBlock);
      
      const newRange = document.createRange();
      newRange.selectNodeContents(newBlock);
      newRange.collapse(false);
      selection.removeAllRanges();
      selection.addRange(newRange);
      newBlock.focus();
      return;
    }
    
    // We have a selection but no block - wrap selection in new block
    const range = selection.getRangeAt(0);
    
    // Check if selection is directly in editor area (no block wrapper)
    const ancestor = range.commonAncestorContainer;
    const isDirectInEditor = ancestor === editorArea || 
                              (ancestor.parentElement && ancestor.parentElement === editorArea) ||
                              (ancestor.nodeType === Node.TEXT_NODE && ancestor.parentElement === editorArea);
    
    if (isDirectInEditor) {
      // Selection is at editor level, wrap it in a block
      try {
        // Get all content from selection
        const contents = range.extractContents();
        const newBlock = document.createElement(tagName);
        
        // Move all contents to new block
        if (contents.childNodes.length > 0) {
          while (contents.firstChild) {
            newBlock.appendChild(contents.firstChild);
          }
        } else {
          // Empty selection, just add br
          newBlock.innerHTML = '<br>';
        }
        
        // Insert the new block at the range position
        range.insertNode(newBlock);
        
        // If there's content after, we might need to clean up
        // Select the new block
        const newRange = document.createRange();
        newRange.selectNodeContents(newBlock);
        newRange.collapse(false);
        selection.removeAllRanges();
        selection.addRange(newRange);
        newBlock.focus();
      } catch (e) {
        console.error('Error creating block:', e);
        // Fallback: create at end
        const newBlock = document.createElement(tagName);
        newBlock.innerHTML = '<br>';
        editorArea.appendChild(newBlock);
        const newRange = document.createRange();
        newRange.selectNodeContents(newBlock);
        newRange.collapse(false);
        selection.removeAllRanges();
        selection.addRange(newRange);
        newBlock.focus();
      }
      return;
    }
    
    // Fallback: create new block at end
    const newBlock = document.createElement(tagName);
    newBlock.innerHTML = '<br>';
    editorArea.appendChild(newBlock);
    
    const newRange = document.createRange();
    newRange.selectNodeContents(newBlock);
    newRange.collapse(false);
    selection.removeAllRanges();
    selection.addRange(newRange);
    newBlock.focus();
    return;
  }
  
  // CRITICAL CHECK: Never replace the editor area!
  // Ensure block is a valid Element before checking properties
  if (!block || block.nodeType !== Node.ELEMENT_NODE) {
    // Block is not valid, create a new one
    const newBlock = document.createElement(tagName);
    newBlock.innerHTML = '<br>';
    editorArea.appendChild(newBlock);
    
    const newRange = document.createRange();
    newRange.selectNodeContents(newBlock);
    newRange.collapse(false);
    selection.removeAllRanges();
    selection.addRange(newRange);
    newBlock.focus();
    return;
  }
  
  if (block === editorArea || (block.classList && block.classList.contains('content-editor-area'))) {
    // This should never happen, but if it does, create a new block instead
    const newBlock = document.createElement(tagName);
    newBlock.innerHTML = '<br>';
    editorArea.appendChild(newBlock);
    
    const newRange = document.createRange();
    newRange.selectNodeContents(newBlock);
    newRange.collapse(false);
    selection.removeAllRanges();
    selection.addRange(newRange);
    newBlock.focus();
    return;
  }
  
  // Ensure block has tagName property (it's an Element)
  if (!block.tagName) {
    console.error('Block is not a valid element:', block);
    return;
  }
  
  // Special handling: if block is a list item (LI) and we're converting to a non-list tag
  // We need to remove it from the list structure first
  if (block.tagName === 'LI' && tagName !== 'li' && tagName !== 'ul' && tagName !== 'ol') {
    const list = block.parentElement;
    if (list && (list.tagName === 'UL' || list.tagName === 'OL')) {
      // Create new block with the desired tag
      const newBlock = document.createElement(tagName);
      newBlock.innerHTML = block.innerHTML;
      
      // Remove any inline styles that might cause indentation
      newBlock.style.marginLeft = '0';
      newBlock.style.paddingLeft = '0';
      newBlock.style.margin = '';
      newBlock.style.padding = '';
      
      // Save selection position
      let offset = 0;
      if (selection.rangeCount > 0) {
        try {
          const range = selection.getRangeAt(0);
          const blockRange = document.createRange();
          blockRange.selectNodeContents(block);
          blockRange.setEnd(range.startContainer, range.startOffset);
          offset = blockRange.toString().length;
        } catch (e) {
          offset = 0;
        }
      }
      
      // Replace list item with new block
      if (list.children.length === 1) {
        // Last item in list, replace entire list with new block
        list.parentNode.replaceChild(newBlock, list);
      } else {
        // Multiple items in list - remove the list item and place new block after the list
        // First, remove the list item
        block.remove();
        // Then insert the new block after the list
        if (list.nextSibling) {
          list.parentNode.insertBefore(newBlock, list.nextSibling);
        } else {
          list.parentNode.appendChild(newBlock);
        }
      }
      
      // Restore cursor position
      const newRange = document.createRange();
      const textNodes = this.getTextNodes(newBlock);
      
      if (textNodes.length > 0 && offset > 0) {
        let currentOffset = 0;
        for (const textNode of textNodes) {
          const nodeLength = textNode.textContent.length;
          if (currentOffset + nodeLength >= offset) {
            const pos = Math.min(offset - currentOffset, nodeLength);
            newRange.setStart(textNode, pos);
            newRange.setEnd(textNode, pos);
            break;
          }
          currentOffset += nodeLength;
        }
        if (!newRange.startContainer || !newRange.endContainer) {
          newRange.selectNodeContents(newBlock);
          newRange.collapse(false);
        }
      } else {
        newRange.selectNodeContents(newBlock);
        newRange.collapse(false);
      }
      
      selection.removeAllRanges();
      selection.addRange(newRange);
      newBlock.focus();
      return;
    }
  }
  
  // Don't change if already the same tag (except for paragraph, allow toggle)
  if (block.tagName.toUpperCase() === tagName.toUpperCase() && tagName.toUpperCase() !== 'P') {
    // Already the same tag, do nothing
    return;
  }
  
  // Save selection position before replacing block
  let offset = 0;
  let range = null;
  if (selection.rangeCount > 0) {
    range = selection.getRangeAt(0);
    try {
      const blockRange = document.createRange();
      blockRange.selectNodeContents(block);
      blockRange.setEnd(range.startContainer, range.startOffset);
      offset = blockRange.toString().length;
    } catch (e) {
      offset = 0;
    }
  }
  
  // Create new block with same content
  const newBlock = document.createElement(tagName);
  
  // Copy innerHTML (content) from old block
  newBlock.innerHTML = block.innerHTML;
  
  // Remove any inline styles that might cause indentation (especially from lists)
  // This ensures paragraphs/headings don't inherit list indentation
  newBlock.style.marginLeft = '0';
  newBlock.style.paddingLeft = '0';
  newBlock.style.margin = '';
  newBlock.style.padding = '';
  
  // Copy attributes (except style, class, id which might conflict)
  if (block.attributes && block.attributes.length > 0) {
    Array.from(block.attributes).forEach(attr => {
      if (!['style', 'class', 'id'].includes(attr.name)) {
        newBlock.setAttribute(attr.name, attr.value);
      }
    });
  }
  
  // Ensure block has a parent before replacing
  if (!block.parentNode) {
    console.error('Block has no parent node:', block);
    return;
  }
  
  // Replace block with new block (but never replace editor area!)
  try {
    block.parentNode.replaceChild(newBlock, block);
  } catch (e) {
    console.error('Error replacing block:', e, { block, newBlock, tagName });
    return;
  }
  
  // Restore cursor position
  const newRange = document.createRange();
  const textNodes = this.getTextNodes(newBlock);
  
  if (textNodes.length > 0 && offset > 0) {
    let currentOffset = 0;
    for (const textNode of textNodes) {
      const nodeLength = textNode.textContent.length;
      if (currentOffset + nodeLength >= offset) {
        const pos = Math.min(offset - currentOffset, nodeLength);
        newRange.setStart(textNode, pos);
        newRange.setEnd(textNode, pos);
        break;
      }
      currentOffset += nodeLength;
    }
    if (!newRange.startContainer || !newRange.endContainer) {
      // Fallback: end of block
      newRange.selectNodeContents(newBlock);
      newRange.collapse(false);
    }
  } else {
    // No text nodes or offset is 0, place at end
    newRange.selectNodeContents(newBlock);
    newRange.collapse(false);
  }
  
  selection.removeAllRanges();
  selection.addRange(newRange);
  
  // Focus the new block
  newBlock.focus();
  
  // Ensure editor area maintains focus
  editorArea.focus();
},

  toggleList(listType) {
  if (window.ContentEditorBlockManager && !this._inToggleList) {
    return window.ContentEditorBlockManager.toggleList(listType);
  }

  return this.toggleListImpl(listType);
  },

  toggleListImpl(listType) {
  const block = this.getCurrentBlock();
  if (!block) return;
  
  const listTag = listType.toUpperCase();
  const isListItem = block.tagName === 'LI';
  
  if (isListItem) {
    // Already in a list, check if we should remove it
    const list = block.parentElement;
    if (list.tagName === listTag) {
      // Remove from list
      this.removeFromList(block);
    } else {
      // Change list type
      this.changeListType(block, listType);
    }
  } else {
    // Create new list
    this.createList(block, listType);
  }
},

  createList(block, listType) {
  const list = document.createElement(listType);
  const li = document.createElement('li');
  li.innerHTML = block.innerHTML;
  list.appendChild(li);
  block.parentNode.replaceChild(list, block);
  
  // Move cursor to list item
  const selection = window.getSelection();
  const range = document.createRange();
  range.selectNodeContents(li);
  range.collapse(false);
  selection.removeAllRanges();
  selection.addRange(range);
},

  removeFromList(li) {
  const list = li.parentElement;
  const p = document.createElement('p');
  p.innerHTML = li.innerHTML;
  
  if (list.children.length === 1) {
    // Last item, replace list with paragraph
    list.parentNode.replaceChild(p, list);
  } else {
    // Replace li with p
    list.replaceChild(p, li);
  }
  
  // Move cursor
  const selection = window.getSelection();
  const range = document.createRange();
  range.selectNodeContents(p);
  range.collapse(false);
  selection.removeAllRanges();
  selection.addRange(range);
},

  changeListType(li, newType) {
  const oldList = li.parentElement;
  const newList = document.createElement(newType);
  
  // Move all items
  while (oldList.firstChild) {
    newList.appendChild(oldList.firstChild);
  }
  
  oldList.parentNode.replaceChild(newList, oldList);
}
};
