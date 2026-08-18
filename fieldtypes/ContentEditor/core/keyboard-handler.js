// Keyboard event handling for ContentEditor
window.ContentEditorKeyboard = {
  /**
   * Handle keyboard events
   * @param {KeyboardEvent} e - The keyboard event
   * @param {Element} editor - The editor element
   * @param {Element} toolbar - The toolbar element
   * @param {Element} hiddenInput - The hidden input element
   */
  handle(e, editor, toolbar, hiddenInput) {
    // Ensure block structure exists before typing
    // If editor is empty or has text directly in it, create a paragraph
    // Only do this for printable characters (not special keys)
    if (this.shouldCreateInitialBlock(editor, e)) {
      this.createInitialBlock(editor);
      // After creating block, let the character be inserted normally
      // Don't prevent default for printable characters
      if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
        // Let the browser handle the character insertion
        return; // Don't process further, let browser insert the character
      }
    }
    
    // Enter/Shift+Enter handling
    if (e.key === 'Enter') {
      if (e.shiftKey) {
        // Shift+Enter: insert <br>
        e.preventDefault();
        this.insertLineBreak(editor);
        if (window.ContentEditorSync) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
      } else {
        // Enter: create new paragraph
        e.preventDefault();
        this.handleEnter(editor);
        if (window.ContentEditorSync) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
      }
      return;
    }
    
    // Tab/Shift+Tab for nested lists
    if (e.key === 'Tab') {
      e.preventDefault();
      this.handleTab(editor, e.shiftKey);
      if (window.ContentEditorSync) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
      return;
    }
    
    // Backspace handling
    if (e.key === 'Backspace') {
      this.handleBackspace(editor, e);
      return;
    }
    
    // Keyboard shortcuts (Ctrl/Cmd + key)
    const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
    const modKey = isMac ? e.metaKey : e.ctrlKey;
    
    if (!modKey) return;
    
    this.handleShortcut(e, editor, toolbar, hiddenInput);
  },

  /**
   * Handle keyboard shortcuts
   */
  handleShortcut(e, editor, toolbar, hiddenInput) {
    // Undo/Redo shortcuts
    if (e.key === 'z' || e.key === 'Z') {
      if (e.shiftKey) {
        // Ctrl+Shift+Z or Cmd+Shift+Z: Redo
        e.preventDefault();
        if (window.ContentEditorHistory) {
          window.ContentEditorHistory.redo();
        }
        return;
      } else {
        // Ctrl+Z or Cmd+Z: Undo
        e.preventDefault();
        if (window.ContentEditorHistory) {
          window.ContentEditorHistory.undo();
        }
        return;
      }
    }
    
    switch (e.key.toLowerCase()) {
      case 'b':
        e.preventDefault();
        window.ContentEditorCommandRegistry.execute('bold', null, editor);
        if (window.ContentEditorToolbar) {
          window.ContentEditorToolbar.updateState(editor, toolbar);
        }
        if (window.ContentEditorSync) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
        break;
      case 'i':
        e.preventDefault();
        window.ContentEditorCommandRegistry.execute('italic', null, editor);
        if (window.ContentEditorToolbar) {
          window.ContentEditorToolbar.updateState(editor, toolbar);
        }
        if (window.ContentEditorSync) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
        break;
      case 'u':
        e.preventDefault();
        window.ContentEditorCommandRegistry.execute('underline', null, editor);
        if (window.ContentEditorToolbar) {
          window.ContentEditorToolbar.updateState(editor, toolbar);
        }
        if (window.ContentEditorSync) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
        break;
      case 's':
        if (e.shiftKey) {
          e.preventDefault();
          window.ContentEditorCommandRegistry.execute('strikethrough', null, editor);
          if (window.ContentEditorToolbar) {
            window.ContentEditorToolbar.updateState(editor, toolbar);
          }
          if (window.ContentEditorSync) {
            window.ContentEditorSync.toHidden(editor, hiddenInput);
          }
        }
        break;
      case '1':
      case '2':
      case '3':
      case '4':
      case '5':
      case '6':
        e.preventDefault();
        const level = parseInt(e.key);
        window.ContentEditorCommandRegistry.execute('formatBlock', 'h' + level, editor);
        if (window.ContentEditorToolbar) {
          window.ContentEditorToolbar.updateState(editor, toolbar);
        }
        if (window.ContentEditorSync) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
        break;
      case 'l':
        if (e.shiftKey) {
          e.preventDefault();
          window.ContentEditorCommandRegistry.execute('insertUnorderedList', null, editor);
          if (window.ContentEditorToolbar) {
            window.ContentEditorToolbar.updateState(editor, toolbar);
          }
          if (window.ContentEditorSync) {
            window.ContentEditorSync.toHidden(editor, hiddenInput);
          }
        }
        break;
      case 'o':
        if (e.shiftKey) {
          e.preventDefault();
          window.ContentEditorCommandRegistry.execute('insertOrderedList', null, editor);
          if (window.ContentEditorToolbar) {
            window.ContentEditorToolbar.updateState(editor, toolbar);
          }
          if (window.ContentEditorSync) {
            window.ContentEditorSync.toHidden(editor, hiddenInput);
          }
        }
        break;
      case 'k':
        e.preventDefault();
        window.ContentEditorCommandRegistry.execute('createLink', null, editor);
        if (window.ContentEditorToolbar) {
          window.ContentEditorToolbar.updateState(editor, toolbar);
        }
        if (window.ContentEditorSync) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
        break;
    }
  },

  /**
   * Handle Enter key - create new paragraph or list item
   */
  handleEnter(editor) {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    
    const range = selection.getRangeAt(0);
    const block = window.ContentEditorDOMUtils?.getCurrentBlock() || 
                  window.ContentEditorCommands?.getCurrentBlock();
    
    if (!block) {
      // No block, create one
      const newP = document.createElement('p');
      newP.innerHTML = '<br>';
      editor.appendChild(newP);
      this.setCursorAt(newP, 0);
      return;
    }
    
    // Check if we're in a list item
    if (block.tagName === 'LI') {
      const list = block.parentElement;
      
      // Check if list item is empty (double-enter to exit list)
      const isEmpty = !block.textContent.trim() || 
                      block.textContent.trim() === '\u200B' ||
                      (block.textContent.trim() === '' && (block.innerHTML.trim() === '<br>' || block.innerHTML.trim() === ''));
      
      if (isEmpty) {
        // Empty list item - exit the list (double-enter behavior)
        const p = document.createElement('p');
        p.innerHTML = '<br>';
        
        // Get the editor area to ensure we insert at the right level
        const editorArea = window.ContentEditorDOMUtils?.findEditorArea(block) || 
                          document.querySelector('.content-editor-area[contenteditable="true"]');
        
        if (list.children.length === 1) {
          // Last item in list, replace list with paragraph
          list.parentNode.replaceChild(p, list);
        } else {
          // Remove empty list item
          block.remove();
          
          // Insert paragraph after the list (not inside it)
          // Make sure we're inserting at the editor level, not inside the list
          if (list.parentNode === editorArea) {
            // List is direct child of editor, insert paragraph after it
            editorArea.insertBefore(p, list.nextSibling);
          } else {
            // List might be nested, insert after list's parent
            list.parentNode.insertBefore(p, list.nextSibling);
          }
        }
        
        this.setCursorAt(p, 0);
        return;
      }
      
      // Create new list item (stays in the list)
      // First, save the current content after cursor
      let contentToMove = null;
      
      try {
        // Check if cursor is in the middle of text (need to split)
        const blockRange = document.createRange();
        blockRange.selectNodeContents(block);
        blockRange.setEnd(range.startContainer, range.startOffset);
        const beforeLength = blockRange.toString().length;
        const totalLength = block.textContent.length;
        
        // Only extract if cursor is not at the end
        if (beforeLength < totalLength) {
          // Create range for content after cursor
          const afterRange = document.createRange();
          
          // Set start to cursor position
          afterRange.setStart(range.startContainer, range.startOffset);
          
          // Set end to end of block content
          // Make sure we stay within the list item
          if (block.lastChild) {
            if (block.lastChild.nodeType === Node.TEXT_NODE) {
              afterRange.setEnd(block.lastChild, block.lastChild.textContent.length);
            } else {
              afterRange.setEndAfter(block.lastChild);
            }
          } else {
            afterRange.setEnd(block, block.childNodes.length);
          }
          
          // Verify range is valid and within the list item
          if (afterRange.startContainer && 
              afterRange.endContainer &&
              (afterRange.startContainer === block || 
               (afterRange.startContainer.parentElement && afterRange.startContainer.parentElement === block)) &&
              (afterRange.endContainer === block || 
               (afterRange.endContainer.parentElement && afterRange.endContainer.parentElement === block))) {
            // Extract content (this removes it from the DOM)
            contentToMove = afterRange.extractContents();
          }
        }
      } catch (e) {
        // If extraction fails, just create empty list item
        console.error('Error extracting content:', e);
      }
      
      // Create new list item
      const newLi = document.createElement('li');
      
      // Move extracted content to new list item, or create empty one
      if (contentToMove && (contentToMove.textContent.trim() || contentToMove.childNodes.length > 0)) {
        newLi.appendChild(contentToMove);
      } else {
        newLi.innerHTML = '<br>';
      }
      
      // Insert new list item after current one (within the same list)
      // This maintains proper DOM order and list numbering
      if (block.nextSibling) {
        list.insertBefore(newLi, block.nextSibling);
      } else {
        list.appendChild(newLi);
      }
      
      // Ensure current block has content if it became empty
      if (!block.textContent.trim() || block.textContent.trim() === '\u200B') {
        block.innerHTML = '<br>';
      }
      
      // Move cursor to new list item
      this.setCursorAt(newLi, 0);
      return;
    }
    
    // Regular block: split at cursor
    try {
      const afterRange = range.cloneRange();
      afterRange.setStart(range.startContainer, range.startOffset);
      afterRange.setEndAfter(block);

      const afterContent = afterRange.extractContents();

      const newP = document.createElement('p');
      if (afterContent.textContent.trim()) {
        newP.appendChild(afterContent);
      } else {
        newP.innerHTML = '<br>';
      }

      // Insert new paragraph after current block
      block.parentNode.insertBefore(newP, block.nextSibling);

      // Ensure current block has content
      if (!block.textContent.trim() || block.textContent.trim() === '\u200B') {
        block.innerHTML = '<br>';
      }

      this.setCursorAt(newP, 0);
    } catch (e) {
      console.error('Error handling Enter:', e);
      // Fallback: just create new paragraph
      const newP = document.createElement('p');
      newP.innerHTML = '<br>';
      block.parentNode.insertBefore(newP, block.nextSibling);
      this.setCursorAt(newP, 0);
    }
  },

  /**
   * Insert line break (Shift+Enter)
   */
  insertLineBreak(editor) {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    
    const range = selection.getRangeAt(0);
    const br = document.createElement('br');
    range.deleteContents();
    range.insertNode(br);
    
    // Move cursor after br
    range.setStartAfter(br);
    range.collapse(true);
    selection.removeAllRanges();
    selection.addRange(range);
  },

  /**
   * Handle Tab key for nested lists
   */
  handleTab(editor, shiftKey) {
    const block = window.ContentEditorDOMUtils?.getCurrentBlock() || 
                  window.ContentEditorCommands?.getCurrentBlock();
    
    if (!block || block.tagName !== 'LI') return;
    
    const list = block.parentElement;
    if (list.tagName !== 'UL' && list.tagName !== 'OL') return;
    
    if (shiftKey) {
      // Shift+Tab: outdent
      const prevItem = block.previousElementSibling;
      if (prevItem) {
        // Move to previous item's sublist or create one
        let sublist = prevItem.querySelector('ul, ol');
        if (!sublist) {
          sublist = document.createElement(list.tagName);
          prevItem.appendChild(sublist);
        }
        sublist.appendChild(block);
      } else {
        // Move to parent list
        const parentList = list.parentElement;
        if (parentList && (parentList.tagName === 'UL' || parentList.tagName === 'OL')) {
          parentList.insertBefore(block, list);
          if (list.children.length === 0) {
            list.remove();
          }
        }
      }
    } else {
      // Tab: indent
      const prevItem = block.previousElementSibling;
      if (prevItem) {
        let sublist = prevItem.querySelector('ul, ol');
        if (!sublist) {
          sublist = document.createElement(list.tagName);
          prevItem.appendChild(sublist);
        }
        sublist.appendChild(block);
      }
    }
  },

  /**
   * Handle Backspace key
   */
  handleBackspace(editor, e) {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    
    const range = selection.getRangeAt(0);
    if (!range.collapsed) return; // Only handle when cursor is collapsed
    
    const block = window.ContentEditorDOMUtils?.getCurrentBlock() || 
                  window.ContentEditorCommands?.getCurrentBlock();
    
    if (!block) return;
    
    // If at start of block and block is empty
    const blockRange = document.createRange();
    blockRange.selectNodeContents(block);
    blockRange.setEnd(range.startContainer, range.startOffset);
    
    if (blockRange.toString().trim() === '' && block.textContent.trim() === '') {
      // Merge with previous block or remove if first
      const prevBlock = block.previousElementSibling;
      if (prevBlock) {
        e.preventDefault();
        const newRange = document.createRange();
        newRange.selectNodeContents(prevBlock);
        newRange.collapse(false);
        selection.removeAllRanges();
        selection.addRange(newRange);
        block.remove();
      } else {
        // First block, just keep it empty
        block.innerHTML = '<br>';
        e.preventDefault();
        this.setCursorAt(block, 0);
      }
    }
  },

  /**
   * Set cursor at position in element
   */
  setCursorAt(element, offset) {
    const range = document.createRange();
    const selection = window.getSelection();
    
    if (element.firstChild && element.firstChild.nodeType === Node.TEXT_NODE) {
      const textNode = element.firstChild;
      const maxOffset = Math.min(offset, textNode.textContent.length);
      range.setStart(textNode, maxOffset);
      range.setEnd(textNode, maxOffset);
    } else {
      range.selectNodeContents(element);
      range.collapse(false);
    }
    
    selection.removeAllRanges();
    selection.addRange(range);
  },

  /**
   * Check if we should create an initial block
   */
  shouldCreateInitialBlock(editor, e) {
    // Only for printable characters (single character keys that aren't special)
    // Skip if it's a special key or modifier key combination
    if (e.key.length > 1 || e.ctrlKey || e.metaKey || e.altKey) {
      return false;
    }
    
    // Check if editor is empty or has text directly in it (no block elements)
    const children = Array.from(editor.childNodes);
    const hasTextNode = children.some(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
    const hasBlockElement = children.some(node => 
      node.nodeType === Node.ELEMENT_NODE && 
      ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'BLOCKQUOTE', 'PRE', 'DIV'].includes(node.tagName)
    );
    
    // If editor is empty or has text nodes but no block elements, create a block
    return (children.length === 0 || hasTextNode) && !hasBlockElement;
  },

  /**
   * Create initial paragraph block
   */
  createInitialBlock(editor) {
    const selection = window.getSelection();
    let range = null;
    
    // Save current selection/cursor position
    if (selection.rangeCount > 0) {
      range = selection.getRangeAt(0).cloneRange();
    }
    
    // Get any existing text content
    const textContent = editor.textContent || '';
    
    // Create paragraph
    const p = document.createElement('p');
    
    // If there's existing text, move it to the paragraph
    if (textContent.trim()) {
      // Clear editor and move content to paragraph
      const fragment = document.createDocumentFragment();
      const children = Array.from(editor.childNodes);
      
      children.forEach(node => {
        if (node.nodeType === Node.TEXT_NODE) {
          fragment.appendChild(node.cloneNode(true));
        } else if (node.nodeType === Node.ELEMENT_NODE) {
          // Move non-block elements into paragraph
          fragment.appendChild(node.cloneNode(true));
        }
      });
      
      p.appendChild(fragment);
      editor.innerHTML = '';
    } else {
      // Empty paragraph
      p.innerHTML = '<br>';
    }
    
    editor.appendChild(p);
    
    // Restore cursor position
    if (range) {
      try {
        // Try to find the text node in the new paragraph
        const textNodes = window.ContentEditorDOMUtils?.getTextNodes(p) || [];
        if (textNodes.length > 0) {
          const textNode = textNodes[0];
          const offset = Math.min(range.startOffset, textNode.textContent.length);
          range.setStart(textNode, offset);
          range.setEnd(textNode, offset);
        } else {
          // No text node, place at end of paragraph
          range.selectNodeContents(p);
          range.collapse(false);
        }
        selection.removeAllRanges();
        selection.addRange(range);
      } catch (e) {
        // Fallback: place cursor at end of paragraph
        const newRange = document.createRange();
        newRange.selectNodeContents(p);
        newRange.collapse(false);
        selection.removeAllRanges();
        selection.addRange(newRange);
      }
    } else {
      // No previous selection, place cursor at end
      const newRange = document.createRange();
      newRange.selectNodeContents(p);
      newRange.collapse(false);
      selection.removeAllRanges();
      selection.addRange(newRange);
    }
    
    p.focus();
  }
};

