// Formatting commands for ContentEditor
// NOTE: This file maintains backward compatibility
// New code should use window.ContentEditorDOMUtils and window.ContentEditorCommandRegistry
window.ContentEditorCommands = {
  // Get current block element (p, div, h1, etc.)
  // Delegates to DOMUtils if available, otherwise uses legacy implementation
  getCurrentBlock(element = null) {
    if (window.ContentEditorDOMUtils && window.ContentEditorDOMUtils.getCurrentBlock) {
      return window.ContentEditorDOMUtils.getCurrentBlock(element);
    }
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
  
    resolveGridCellStyles(...args) {
    return window.ContentEditorGridCommands.resolveGridCellStyles(...args);
  },
  
    applyGridCellStyles(...args) {
    return window.ContentEditorGridCommands.applyGridCellStyles(...args);
  },
  
    parseNumericValue(...args) {
    return window.ContentEditorGridCommands.parseNumericValue(...args);
  },

  // Check if element or selection is wrapped in tag
  isWrappedInTag(element, tagName) {
    if (window.ContentEditorInlineFormat) {
      return window.ContentEditorInlineFormat.isWrappedInTag(element, tagName);
    }
  },

  // Check if tag is inline or block
  // Delegates to DOMUtils if available
  isInlineTag(tagName) {
    if (window.ContentEditorDOMUtils && window.ContentEditorDOMUtils.isInlineTag) {
      return window.ContentEditorDOMUtils.isInlineTag(tagName);
    }
    // Legacy implementation
    const inlineTags = ['B', 'STRONG', 'I', 'EM', 'U', 'S', 'DEL', 'A', 'SPAN', 'CODE'];
    return inlineTags.includes(tagName.toUpperCase());
  },
  
  isBlockTag(tagName) {
    if (window.ContentEditorDOMUtils && window.ContentEditorDOMUtils.isBlockTag) {
      return window.ContentEditorDOMUtils.isBlockTag(tagName);
    }
    // Legacy implementation
    const blockTags = ['P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'PRE'];
    return blockTags.includes((tagName || '').toUpperCase());
  },

  // Wrap selection or element with tag
  wrapWithTag(tagName, value = null) {
    if (window.ContentEditorInlineFormat) {
      return window.ContentEditorInlineFormat.wrapWithTag(tagName, value);
    }
  },
  
  // Wrap block's content with inline tag (not the block itself)
  wrapBlockContent(block, inlineTag) {
    if (window.ContentEditorInlineFormat) {
      return window.ContentEditorInlineFormat.wrapBlockContent(block, inlineTag);
    }
  },
  
  // Unwrap inline tags from block's content
  unwrapInlineFromBlock(block, tags) {
    if (window.ContentEditorInlineFormat) {
      return window.ContentEditorInlineFormat.unwrapInlineFromBlock(block, tags);
    }
  },

  // Wrap element with tag (deprecated - use wrapBlockContent for inline tags)
  wrapElement(element, tagName) {
    if (window.ContentEditorInlineFormat) {
      return window.ContentEditorInlineFormat.wrapElement(element, tagName);
    }
  },

  // Unwrap tag from element
  unwrapTag(element, tagName) {
    if (window.ContentEditorInlineFormat) {
      return window.ContentEditorInlineFormat.unwrapTag(element, tagName);
    }
  },

  // Unwrap selection
  unwrapSelection(range, tagName) {
    if (window.ContentEditorInlineFormat) {
      return window.ContentEditorInlineFormat.unwrapSelection(range, tagName);
    }
  },

  // Format block (change tag)
  formatBlock(tagName) {
    if (window.ContentEditorBlockFormat) {
      return window.ContentEditorBlockFormat.formatBlock(tagName);
    }
  },
  
  // Get all text nodes in element
  // Delegates to DOMUtils if available
  getTextNodes(element) {
    if (window.ContentEditorDOMUtils && window.ContentEditorDOMUtils.getTextNodes) {
      return window.ContentEditorDOMUtils.getTextNodes(element);
    }
    // Legacy implementation
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

  // Apply formatting command
  execute(command, value = null) {
    // Save selection before executing
    window.ContentEditorSelection.save();
    
    try {
      switch (command) {
        case 'bold':
          this.wrapWithTag('b');
          break;
        case 'italic':
          this.wrapWithTag('i');
          break;
        case 'underline':
          this.wrapWithTag('u');
          break;
        case 'strikethrough':
          this.wrapWithTag('s');
          break;
        case 'formatBlock':
          // Ensure we have a valid tag name
          const tagName = value || 'p';
          // Normalize to lowercase for comparison
          const normalizedTag = tagName.toLowerCase();
          if (this.isBlockTag(normalizedTag)) {
            this.formatBlock(normalizedTag);
          } else {
            console.warn('formatBlock called with invalid block tag:', tagName);
          }
          break;
        case 'insertUnorderedList':
          this.toggleList('ul');
          break;
        case 'insertOrderedList':
          this.toggleList('ol');
          break;
        case 'createLink':
          this.createLink();
          break;
        case 'justifyLeft':
        case 'justifyCenter':
        case 'justifyRight':
        case 'justifyFull':
          this.justify(command);
          break;
        case 'code':
          this.toggleCode();
          break;
        case 'insertHorizontalRule':
          this.insertHorizontalRule();
          break;
        case 'insertTable':
          this.insertTable();
          break;
        case 'insertGrid':
          this.insertGrid();
          break;
      case 'insertToggle':
        this.insertToggle();
        break;
        case 'insertImage':
          this.insertImage();
          break;
        case 'insertVideo':
          this.insertVideo();
          break;
        case 'insertAudio':
          this.insertAudio();
          break;
        case 'insertMaps':
          this.insertMaps();
          break;
        case 'insertLinkPreview':
          this.insertLinkPreview();
          break;
        case 'insertIframe':
          this.insertIframe();
          break;
        case 'insertCustomEmbed':
          this.insertCustomEmbed();
          break;
        case 'insertComponent':
          this.insertComponent();
          break;
        case 'clearFormatting':
          this.clearFormatting();
          break;
        case 'textColor':
          // Color picker is handled by dropdown, this is just a placeholder
          break;
        case 'applyTextColor':
          this.applyTextColor(value);
          break;
        case 'applyBgColor':
          this.applyBgColor(value);
          break;
        case 'removeTextColor':
          this.removeTextColor();
          break;
        case 'removeBgColor':
          this.removeBgColor();
          break;
        default:
          console.warn('Unknown command:', command);
      }
      
      // Save state to history after command execution
      if (window.ContentEditorHistory && command !== 'undo' && command !== 'redo') {
        setTimeout(() => {
          window.ContentEditorHistory.saveState();
        }, 50);
      }
    } catch (e) {
      console.error('Error executing command:', command, e);
    } finally {
      // Clear saved selection after use
      window.ContentEditorSelection.clear();
    }
  },

  // Toggle list
  toggleList(listType) {
    if (window.ContentEditorBlockFormat) {
      return window.ContentEditorBlockFormat.toggleList(listType);
    }
  },

  // Create list from block
  createList(block, listType) {
    if (window.ContentEditorBlockFormat) {
      return window.ContentEditorBlockFormat.createList(block, listType);
    }
  },

  // Remove from list
  removeFromList(li) {
    if (window.ContentEditorBlockFormat) {
      return window.ContentEditorBlockFormat.removeFromList(li);
    }
  },

  // Change list type
  changeListType(li, newType) {
    if (window.ContentEditorBlockFormat) {
      return window.ContentEditorBlockFormat.changeListType(li, newType);
    }
  },

  // Create or edit link
  createLink() {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    
    const range = selection.getRangeAt(0);
    const selectedText = range.toString();
    
    // Check if we're already in a link
    let linkElement = null;
    let container = range.commonAncestorContainer;
    
    if (container.nodeType === Node.TEXT_NODE) {
      container = container.parentElement;
    }
    
    while (container && container !== document.body) {
      if (container.tagName === 'A') {
        linkElement = container;
        break;
      }
      container = container.parentElement;
    }
    
    // Save selection info before opening dialog (it will be lost when dialog opens)
    // Save the range and also store editor reference
    const editor = container?.closest('.content-editor-area') || this.getActiveEditor();
    
    // Save selection with editor context
    window.ContentEditorSelection.save();
    
    // Store additional context in the dialog
    const dialogData = {
      editor: editor,
      selectedText: selectedText,
      savedRange: range.cloneRange() // Clone the range now while it's still valid
    };
    
    // Create link dialog
    const dialog = this.createLinkDialog(linkElement, selectedText, dialogData);
    document.body.appendChild(dialog);
    
    // Show dialog
    setTimeout(() => {
      dialog.classList.add('active');
      const urlInput = dialog.querySelector('.link-url-input');
      if (urlInput) {
        urlInput.focus();
        urlInput.select();
      }
    }, 10);
  },

  // Create link dialog
  createLinkDialog(existingLink, selectedText, dialogData = {}) {
    const dialog = document.createElement('div');
    dialog.className = 'content-editor-link-dialog';
    
    // Store dialog data for later use
    dialog._dialogData = dialogData;
    
    const currentUrl = existingLink ? (existingLink.getAttribute('href') || '') : '';
    const currentText = existingLink ? existingLink.textContent : selectedText;
    
    dialog.innerHTML = `
      <div class="link-dialog-overlay"></div>
      <div class="link-dialog-content">
        <div class="link-dialog-header">
          <h3>${existingLink ? 'Edit Link' : 'Insert Link'}</h3>
          <button type="button" class="link-dialog-close" title="Close">×</button>
        </div>
        <div class="link-dialog-body">
          <div class="link-dialog-field">
            <label for="link-url">URL</label>
            <input type="text" id="link-url" class="link-url-input" placeholder="https://example.com" value="${this.escapeHtml(currentUrl)}">
          </div>
          <div class="link-dialog-field">
            <label for="link-text">Link Text</label>
            <input type="text" id="link-text" class="link-text-input" placeholder="Link text (optional)" value="${this.escapeHtml(currentText)}">
          </div>
        </div>
        <div class="link-dialog-footer">
          <button type="button" class="link-dialog-btn link-dialog-cancel">Cancel</button>
          ${existingLink ? '<button type="button" class="link-dialog-btn link-dialog-remove">Remove Link</button>' : ''}
          <button type="button" class="link-dialog-btn link-dialog-save link-dialog-primary">${existingLink ? 'Update' : 'Insert'}</button>
        </div>
      </div>
    `;
    
    // Close handlers
    const closeDialog = () => {
      dialog.classList.remove('active');
      setTimeout(() => {
        document.body.removeChild(dialog);
      }, 200);
    };
    
    dialog.querySelector('.link-dialog-overlay').addEventListener('click', closeDialog);
    dialog.querySelector('.link-dialog-close').addEventListener('click', closeDialog);
    dialog.querySelector('.link-dialog-cancel').addEventListener('click', closeDialog);
    
    // Remove link handler
    if (existingLink) {
      dialog.querySelector('.link-dialog-remove').addEventListener('click', () => {
        this.unwrapTag(existingLink, 'a');
        closeDialog();
        
        // Update toolbar and sync - find editor from the link element
        let editor = existingLink.closest('.content-editor-area') || this.getActiveEditor();
        
        if (editor && window.ContentEditorToolbar) {
          const toolbar = editor.closest('.content-editor-wrapper')?.querySelector('.content-editor-toolbar') ||
                         document.querySelector('.content-editor-toolbar');
          if (toolbar) {
            window.ContentEditorToolbar.updateState(editor, toolbar);
          }
        }
        
        if (editor && window.ContentEditorSync) {
          const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
          if (hiddenInput) {
            window.ContentEditorSync.toHidden(editor, hiddenInput);
          }
        }
      });
    }
    
    // Save handler
    const saveBtn = dialog.querySelector('.link-dialog-save');
    if (!saveBtn) {
      console.error('Save button not found in link dialog');
      return dialog;
    }
    
    saveBtn.addEventListener('click', () => {
      const urlInput = dialog.querySelector('.link-url-input');
      const textInput = dialog.querySelector('.link-text-input');
      
      if (!urlInput || !textInput) {
        console.error('Link dialog inputs not found');
        return;
      }
      
      const url = urlInput.value.trim();
      const text = textInput.value.trim();
      
      if (!url) {
        urlInput.focus();
        return;
      }
      
      // Use URL as text if text is empty
      const linkText = text || url;
      
      
      if (existingLink) {
        // Edit existing link
        existingLink.setAttribute('href', url);
        existingLink.textContent = linkText;
      } else {
        // Create new link
    const editor = dialog._dialogData?.editor || this.getActiveEditor();
        const originalSelectedText = dialog._dialogData?.selectedText || '';
        const savedRange = dialog._dialogData?.savedRange;
        
        if (!editor) {
          console.error('Editor not found');
          alert('Error: Editor not found. Please try again.');
          closeDialog();
          window.ContentEditorSelection.clear();
          return;
        }
        
        // Create the link element
        const link = document.createElement('a');
        link.href = url;
        link.textContent = linkText;
        
        let linkInserted = false;
        const selection = window.getSelection();
        
        // Strategy 1: Try to use saved range directly
        if (savedRange) {
          try {
            // Check if range containers are still in the DOM
            const startContainer = savedRange.startContainer;
            const endContainer = savedRange.endContainer;
            
            if (startContainer && endContainer && 
                (startContainer.parentNode || startContainer === editor || editor.contains(startContainer)) &&
                (endContainer.parentNode || endContainer === editor || editor.contains(endContainer))) {
              
              // Restore the range
              selection.removeAllRanges();
              selection.addRange(savedRange.cloneRange());
              
              const range = selection.getRangeAt(0);
              const rangeText = range.toString();
              
              if (rangeText || originalSelectedText) {
                // Replace selected text with link
                range.deleteContents();
                range.insertNode(link);
                linkInserted = true;
                
                // Move cursor after link
                const newRange = document.createRange();
                newRange.setStartAfter(link);
                newRange.collapse(true);
                selection.removeAllRanges();
                selection.addRange(newRange);
              } else {
                // Insert link at cursor
                range.insertNode(link);
                linkInserted = true;
                
                // Select the link text
                const newRange = document.createRange();
                newRange.selectNodeContents(link);
                selection.removeAllRanges();
                selection.addRange(newRange);
              }
            } else {
            }
          } catch (e) {
            console.error('Error using saved range:', e);
          }
        }
        
        // Strategy 2: Try to restore selection using ContentEditorSelection
        if (!linkInserted) {
          if (window.ContentEditorSelection.restore(editor)) {
            if (selection.rangeCount > 0) {
              try {
                const range = selection.getRangeAt(0);
                const rangeText = range.toString();
                
                if (rangeText) {
                  range.deleteContents();
                  range.insertNode(link);
                  linkInserted = true;
                  
                  const newRange = document.createRange();
                  newRange.setStartAfter(link);
                  newRange.collapse(true);
                  selection.removeAllRanges();
                  selection.addRange(newRange);
                } else {
                  range.insertNode(link);
                  linkInserted = true;
                  
                  const newRange = document.createRange();
                  newRange.selectNodeContents(link);
                  selection.removeAllRanges();
                  selection.addRange(newRange);
                }
              } catch (e) {
                console.error('Error using restored selection:', e);
              }
            }
          }
        }
        
        // Strategy 3: Search for selected text and replace it
        if (!linkInserted && originalSelectedText) {
          try {
            // Focus editor
            editor.focus();
            
            // Use TreeWalker to find the text
            const walker = document.createTreeWalker(
              editor,
              NodeFilter.SHOW_TEXT,
              null,
              false
            );
            
            let textNode;
            while (textNode = walker.nextNode()) {
              const nodeText = textNode.textContent;
              const index = nodeText.indexOf(originalSelectedText);
              
              if (index !== -1) {
                // Found the text, replace it
                const beforeText = nodeText.substring(0, index);
                const afterText = nodeText.substring(index + originalSelectedText.length);
                
                // Create text nodes
                if (beforeText) {
                  const beforeNode = document.createTextNode(beforeText);
                  textNode.parentNode.insertBefore(beforeNode, textNode);
                }
                
                // Insert link
                textNode.parentNode.insertBefore(link, textNode);
                
                if (afterText) {
                  const afterNode = document.createTextNode(afterText);
                  textNode.parentNode.insertBefore(afterNode, textNode);
                }
                
                // Remove original text node
                textNode.remove();
                
                linkInserted = true;
                
                // Move cursor after link
                const newRange = document.createRange();
                newRange.setStartAfter(link);
                newRange.collapse(true);
                selection.removeAllRanges();
                selection.addRange(newRange);
                
                break;
              }
            }
          } catch (e) {
            console.error('Error finding text:', e);
          }
        }
        
        // Strategy 4: Final fallback - insert at current block or end of editor
        if (!linkInserted && editor) {
          try {
            // Focus editor first
            editor.focus();
            
            const block = this.getCurrentBlock();
            if (block) {
              // Insert at end of current block
              block.appendChild(link);
              linkInserted = true;
              
              const newRange = document.createRange();
              newRange.selectNodeContents(link);
              newRange.collapse(false);
              selection.removeAllRanges();
              selection.addRange(newRange);
            } else {
              // No block, create paragraph
              const p = document.createElement('p');
              p.appendChild(link);
              editor.appendChild(p);
              linkInserted = true;
              
              const newRange = document.createRange();
              newRange.selectNodeContents(link);
              newRange.collapse(false);
              selection.removeAllRanges();
              selection.addRange(newRange);
            }
          } catch (e) {
            console.error('Error with fallback insertion:', e);
          }
        }
        
        if (!linkInserted) {
          console.error('Failed to insert link - no valid insertion point found');
          alert('Error: Could not insert link. Please try selecting text and clicking the link button again.');
        } else {
        }
      }
      
      // Clear saved selection after use
      window.ContentEditorSelection.clear();
      
      closeDialog();
      
      // Update toolbar and sync - find editor from selection or link element
      let editor = null;
      
      if (existingLink) {
        // Find editor from existing link
        editor = existingLink.closest('.content-editor-area') || this.getActiveEditor();
      } else {
        // Find editor from current selection
        const finalSelection = window.getSelection();
        if (finalSelection.rangeCount > 0) {
          const range = finalSelection.getRangeAt(0);
          let container = range.commonAncestorContainer;
          while (container && container !== document.body) {
            if (container.classList && container.classList.contains('content-editor-area')) {
              editor = container;
              break;
            }
            container = container.parentElement;
          }
        }
      }
      
      if (!editor) {
        editor = this.getActiveEditor();
      }
      
      if (editor && window.ContentEditorToolbar) {
        const toolbar = editor.closest('.content-editor-wrapper')?.querySelector('.content-editor-toolbar') ||
                       document.querySelector('.content-editor-toolbar');
        if (toolbar) {
          window.ContentEditorToolbar.updateState(editor, toolbar);
        }
      }
      
      if (editor && window.ContentEditorSync) {
        const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
        if (hiddenInput) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
      }
    });
    
    // Enter key to save
    dialog.querySelectorAll('input').forEach(input => {
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          const saveBtn = dialog.querySelector('.link-dialog-save');
          if (saveBtn) {
            saveBtn.click();
          }
        }
        if (e.key === 'Escape') {
          e.preventDefault();
          closeDialog();
        }
      });
    });
    
    return dialog;
  },

  // Escape HTML for safe insertion
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },

  // Text alignment
  justify(alignment) {
    const block = this.getCurrentBlock();
    if (!block) return;
    
    // Map command to CSS value
    const alignMap = {
      'justifyLeft': 'left',
      'justifyCenter': 'center',
      'justifyRight': 'right',
      'justifyFull': 'justify'
    };
    
    const alignValue = alignMap[alignment] || 'left';
    const currentAlign = block.style.textAlign || block.getAttribute('data-align') || '';
    
    // If already has the same alignment, remove it (toggle to default/left)
    if (currentAlign === alignValue) {
      block.style.textAlign = '';
      block.removeAttribute('data-align');
    } else {
      // Apply new alignment
      block.style.textAlign = alignValue;
      block.setAttribute('data-align', alignValue);
    }
  },

  // Toggle inline code
  toggleCode() {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    
    const range = selection.getRangeAt(0);
    const selectedText = range.toString();
    
    // Check if already in code tag
    let codeElement = null;
    let container = range.commonAncestorContainer;
    
    if (container.nodeType === Node.TEXT_NODE) {
      container = container.parentElement;
    }
    
    while (container && container !== document.body) {
      if (container.tagName === 'CODE') {
        codeElement = container;
        break;
      }
      container = container.parentElement;
    }
    
    if (codeElement) {
      // Remove code formatting
      this.unwrapTag(codeElement, 'code');
    } else {
      // Apply code formatting
      if (selectedText) {
        const code = document.createElement('code');
        code.textContent = selectedText;
        
        try {
          range.deleteContents();
          range.insertNode(code);
          
          // Move cursor after code
          const newRange = document.createRange();
          newRange.setStartAfter(code);
          newRange.collapse(true);
          selection.removeAllRanges();
          selection.addRange(newRange);
        } catch (e) {
          console.error('Error creating code:', e);
        }
      } else {
        // No selection, wrap current word or create empty code
        const code = document.createElement('code');
        code.textContent = 'code';
        
        try {
          range.insertNode(code);
          
          // Select the code text
          const newRange = document.createRange();
          newRange.selectNodeContents(code);
          selection.removeAllRanges();
          selection.addRange(newRange);
        } catch (e) {
          console.error('Error creating code:', e);
        }
      }
    }
  },

  // Insert horizontal rule
  insertHorizontalRule() {
    if (window.ContentEditorBlockManager) {
      return window.ContentEditorBlockManager.insertDivider();
    }

    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    
    const range = selection.getRangeAt(0);
    const block = this.getCurrentBlock();
    
    if (!block) return;
    
    // Create HR element
    const hr = document.createElement('hr');
    
    // Insert after current block
    if (block.parentNode) {
      block.parentNode.insertBefore(hr, block.nextSibling);
      
      // Create new paragraph after HR
      const newP = document.createElement('p');
      newP.innerHTML = '<br>';
      block.parentNode.insertBefore(newP, hr.nextSibling);
      
      // Move cursor to new paragraph
      const newRange = document.createRange();
      newRange.selectNodeContents(newP);
      newRange.collapse(false);
      selection.removeAllRanges();
      selection.addRange(newRange);
      newP.focus();
    }
  },

  // Insert table
    insertTable(...args) {
    return window.ContentEditorTableCommands.insertTable(...args);
  },

  // Create table dialog
    createTableDialog(...args) {
    return window.ContentEditorTableCommands.createTableDialog(...args);
  },

  // Create table element
    createTableElement(...args) {
    return window.ContentEditorTableCommands.createTableElement(...args);
  },

  // Insert grid layout
    insertGrid(...args) {
    return window.ContentEditorGridCommands.insertGrid(...args);
  },

  // Create grid element with inline CSS Grid styles
    createGridElement(...args) {
    return window.ContentEditorGridCommands.createGridElement(...args);
  },

  // Extract grid data from existing grid element
    extractGridData(...args) {
    return window.ContentEditorGridCommands.extractGridData(...args);
  },

  // Update existing grid element
    updateGridElement(...args) {
    return window.ContentEditorGridCommands.updateGridElement(...args);
  },
  
  defaultToggleItems() {
    return [{
      title: 'Toggle Title',
      content: '',
      open: true
    }];
  },

  // Insert / edit toggle blocks
  insertToggle(context = null) {
    const items = Array.isArray(context?.items) && context.items.length
      ? context.items
      : this.defaultToggleItems();
    this.openToggleDialog({
      title: context && context.target ? 'Edit Toggle / Accordion' : 'Insert Toggle / Accordion',
      items,
      onSubmit: (submittedItems) => {
        if (!submittedItems || !submittedItems.length) return;
        if (context && context.target) {
          this.replaceToggleElements(context.target, submittedItems);
        } else {
          this.insertToggleElements(submittedItems);
        }
      }
    });
  },

  openToggleDialog(config = {}) {
    const dialogTitle = config.title || 'Insert Toggle / Accordion';
    const dialog = document.createElement('div');
    dialog.className = 'content-editor-dialog';
    dialog.innerHTML = `
      <div class="content-editor-dialog-overlay"></div>
      <div class="content-editor-dialog-content">
        <div class="content-editor-dialog-header">
          <h3>${dialogTitle}</h3>
          <button type="button" class="content-editor-dialog-close" aria-label="Close">×</button>
        </div>
        <div class="content-editor-dialog-body">
          <form class="content-editor-embed-form" novalidate>
            <div class="form-group">
              <label>Toggles</label>
              <p class="form-hint">Add one or more toggles. Titles are required; content is optional.</p>
              <div class="toggle-items"></div>
              <button type="button" class="content-editor-dialog-btn toggle-add-btn">Add Toggle</button>
            </div>
          </form>
        </div>
        <div class="content-editor-dialog-footer">
          <button type="button" class="content-editor-dialog-btn content-editor-dialog-cancel">Cancel</button>
          <button type="button" class="content-editor-dialog-btn content-editor-dialog-submit">${config.submitLabel || 'Insert Toggle'}</button>
        </div>
      </div>
    `;
    
    document.body.appendChild(dialog);
    
    const overlay = dialog.querySelector('.content-editor-dialog-overlay');
    const closeBtn = dialog.querySelector('.content-editor-dialog-close');
    const cancelBtn = dialog.querySelector('.content-editor-dialog-cancel');
    const submitBtn = dialog.querySelector('.content-editor-dialog-submit');
    const addBtn = dialog.querySelector('.toggle-add-btn');
    const listEl = dialog.querySelector('.toggle-items');
    
    const state = {
      items: (Array.isArray(config.items) && config.items.length ? config.items : this.defaultToggleItems()).map((item, index) => ({
        title: item.title || `Toggle ${index + 1}`,
        content: typeof item.content === 'string' ? item.content : '',
        open: !!item.open
      }))
    };
    
    const syncStateFromDom = () => {
      const rows = Array.from(listEl.querySelectorAll('.toggle-item'));
      if (!rows.length) return;
      state.items = rows.map(row => ({
        title: row.querySelector('.toggle-title-input')?.value || '',
        content: row.querySelector('.toggle-content-input')?.value || '',
        open: row.querySelector('.toggle-open-input')?.checked || false
      }));
      if (!state.items.length) state.items = this.defaultToggleItems();
    };
    
    const renderItems = () => {
      listEl.innerHTML = '';
      state.items.forEach((item, index) => {
        const row = document.createElement('div');
        row.className = 'toggle-item';
        
        const header = document.createElement('div');
        header.className = 'toggle-item-header';
        const titleLabel = document.createElement('span');
        titleLabel.textContent = `Toggle ${index + 1}`;
        header.appendChild(titleLabel);
        
        const actions = document.createElement('div');
        actions.className = 'toggle-item-actions';
        
        const openLabel = document.createElement('label');
        openLabel.className = 'toggle-open-label';
        openLabel.style.display = 'flex';
        openLabel.style.alignItems = 'center';
        openLabel.style.gap = '6px';
        const openCheckbox = document.createElement('input');
        openCheckbox.type = 'checkbox';
        openCheckbox.className = 'toggle-open-input';
        openCheckbox.checked = !!item.open;
        openLabel.appendChild(openCheckbox);
        openLabel.appendChild(document.createTextNode('Start open'));
        actions.appendChild(openLabel);
        
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'content-editor-dialog-btn content-editor-dialog-btn-text toggle-remove-btn';
        removeBtn.textContent = 'Remove';
        if (state.items.length <= 1) {
          removeBtn.disabled = true;
        }
        removeBtn.addEventListener('click', (e) => {
          e.preventDefault();
          if (state.items.length <= 1) return;
          syncStateFromDom();
          state.items.splice(index, 1);
          renderItems();
        });
        actions.appendChild(removeBtn);
        header.appendChild(actions);
        row.appendChild(header);
        
        const titleInput = document.createElement('input');
        titleInput.type = 'text';
        titleInput.className = 'toggle-title-input';
        titleInput.placeholder = 'Toggle title';
        titleInput.value = item.title || '';
        row.appendChild(titleInput);
        
        const contentInput = document.createElement('textarea');
        contentInput.className = 'toggle-content-input';
        contentInput.placeholder = 'Toggle content...';
        contentInput.rows = 3;
        contentInput.value = item.content || '';
        row.appendChild(contentInput);
        
        listEl.appendChild(row);
      });
    };
    
    renderItems();
    
    const closeDialog = () => {
      dialog.remove();
    };
    
    addBtn.addEventListener('click', (e) => {
      e.preventDefault();
      syncStateFromDom();
      state.items.push({ title: `Toggle ${state.items.length + 1}`, content: '', open: false });
      renderItems();
    });
    
    const handleSubmit = (e) => {
      if (e) {
        e.preventDefault();
        e.stopPropagation();
      }
      syncStateFromDom();
      const items = state.items
        .map(item => ({
          title: item.title.trim(),
          content: item.content,
          open: !!item.open
        }))
        .filter(item => item.title || item.content);
      if (!items.length) {
        alert('Please add at least one toggle with a title or content.');
        return;
      }
      if (typeof config.onSubmit === 'function') {
        config.onSubmit(items);
      }
      closeDialog();
    };
    
    submitBtn.addEventListener('click', handleSubmit);
    cancelBtn.addEventListener('click', (e) => {
      e.preventDefault();
      closeDialog();
    });
    closeBtn.addEventListener('click', (e) => {
      e.preventDefault();
      closeDialog();
    });
    overlay.addEventListener('click', closeDialog);
  },
  
  insertToggleElements(items = []) {
    if (!Array.isArray(items) || !items.length) return;
    const editor = this.getActiveEditor();
    if (!editor) return;
    const doc = editor.ownerDocument || document;
    const blockElement = this.createToggleBlock(items, doc);
    if (!blockElement) return;
    const insertResult = this.insertBlockElement(blockElement);
    if (!insertResult) return;
    this.enhanceToggleElements(blockElement);
    this.syncEditorAfterToggleChange(blockElement);
    
    const summary = blockElement.matches && blockElement.matches('summary')
      ? blockElement
      : blockElement.querySelector && blockElement.querySelector('summary');
    if (summary) {
      summary.setAttribute('tabindex', '0');
      try {
        const range = document.createRange();
        range.selectNodeContents(summary);
        range.collapse(false);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        summary.focus();
      } catch (_e) {}
    }
  },
  
  createToggleBlock(items = [], doc = document) {
    if (!items.length) return null;
    const toggles = items.map((item, index) => this.buildToggleElement(item, !!item.open, doc, index));
    if (toggles.length === 1) {
      return toggles[0];
    }
    const group = doc.createElement('div');
    group.className = 'content-editor-toggle-group';
    toggles.forEach(toggle => group.appendChild(toggle));
    return group;
  },
  
  buildToggleElement(item = {}, shouldOpen = false, doc = document) {
    const details = doc.createElement('details');
    details.className = 'content-editor-toggle';
    if (shouldOpen) {
      details.setAttribute('open', 'open');
    }
    const summary = doc.createElement('summary');
    summary.textContent = (item.title || '').trim() || 'Toggle';
    details.appendChild(summary);
    details.appendChild(this.buildToggleBody(item.content || '', doc));
    return details;
  },
  
  buildToggleBody(content = '', doc = document) {
    const wrapper = doc.createElement('div');
    wrapper.className = 'content-editor-toggle-body';
    const normalized = typeof content === 'string' ? content : '';
    const trimmed = normalized.trim();
    if (!trimmed) {
      const paragraph = doc.createElement('p');
      paragraph.innerHTML = '<br>';
      wrapper.appendChild(paragraph);
      return wrapper;
    }
    const blocks = trimmed.split(/\n{2,}/).map(block => block.trim()).filter(Boolean);
    if (!blocks.length) {
      const paragraph = doc.createElement('p');
      paragraph.innerHTML = this.escapeHtml(trimmed).replace(/\n/g, '<br>') || '<br>';
      wrapper.appendChild(paragraph);
      return wrapper;
    }
    blocks.forEach(block => {
      const paragraph = doc.createElement('p');
      const html = this.escapeHtml(block).replace(/\n/g, '<br>');
      paragraph.innerHTML = html || '<br>';
      wrapper.appendChild(paragraph);
    });
    return wrapper;
  },
  
  replaceToggleElements(target, items = []) {
    if (!target || !items.length) return;
    const doc = target.ownerDocument || document;
    const parent = target.parentNode;
    if (!parent) return;
    const newBlock = this.createToggleBlock(items, doc);
    if (!newBlock) return;
    parent.insertBefore(newBlock, target);
    target.remove();
    this.enhanceToggleElements(newBlock);
    this.syncEditorAfterToggleChange(newBlock);
  },
  
  deleteToggleElement(target) {
    if (!target) return;
    const parent = target.parentNode;
    if (!parent) {
      target.remove();
      return;
    }
    const nextSibling = target.nextSibling;
    const doc = target.ownerDocument || document;
    target.remove();
    if (!nextSibling || nextSibling.nodeType !== Node.ELEMENT_NODE || nextSibling.tagName !== 'P') {
      const paragraph = doc.createElement('p');
      paragraph.innerHTML = '<br>';
      if (nextSibling && nextSibling.parentNode) {
        nextSibling.parentNode.insertBefore(paragraph, nextSibling);
      } else {
        parent.appendChild(paragraph);
      }
    }
    this.syncEditorAfterToggleChange(parent);
  },
  
  extractToggleData(target) {
    if (!target) return [];
    const doc = target.ownerDocument || document;
    const collectFromDetails = (details) => {
      const summary = details.querySelector('summary');
      const body = details.querySelector('.content-editor-toggle-body') || details;
      return {
        title: (summary?.textContent || '').trim(),
        content: this.serializeToggleBody(body),
        open: details.hasAttribute('open')
      };
    };
    
    if (target.classList && target.classList.contains('content-editor-toggle-group')) {
      return Array.from(target.querySelectorAll('details.content-editor-toggle')).map(collectFromDetails);
    }
    if (target.matches && target.matches('details.content-editor-toggle')) {
      return [collectFromDetails(target)];
    }
    const closestToggle = target.closest && target.closest('details.content-editor-toggle, .content-editor-toggle-group');
    if (closestToggle) {
      return this.extractToggleData(closestToggle);
    }
    return [];
  },
  
  serializeToggleBody(bodyEl) {
    if (!bodyEl) return '';
    const paragraphs = Array.from(bodyEl.querySelectorAll('p'));
    if (!paragraphs.length) {
      const html = bodyEl.innerHTML || '';
      return this.unescapeHtml(html.replace(/<br\s*\/?>/gi, '\n')).trim();
    }
    return paragraphs
      .map(p => this.unescapeHtml((p.innerHTML || '').replace(/<br\s*\/?>/gi, '\n')).trim())
      .join('\n\n')
      .trim();
  },
  
  enhanceToggleElements(root) {
    if (!root) return;
    if (root.classList && root.classList.contains('content-editor-toggle-group')) {
      this.addToggleControls(root);
      root.querySelectorAll('details').forEach(detail => detail.classList.add('content-editor-toggle'));
      return;
    }
    if (root.matches && root.matches('details.content-editor-toggle')) {
      this.addToggleControls(root);
      return;
    }
    root.querySelectorAll && root.querySelectorAll('.content-editor-toggle-group, details.content-editor-toggle').forEach(el => this.addToggleControls(el));
  },
  
  addToggleControls(host) {
    if (!host) return;
    const isGroup = host.classList && host.classList.contains('content-editor-toggle-group');
    const isSingle = host.matches && host.matches('details.content-editor-toggle');
    if (!isGroup && !isSingle) return;
    
    if (getComputedStyle(host).position === 'static') {
      host.style.position = 'relative';
    }
    const existing = host.querySelector('.content-editor-toggle-controls');
    if (existing) existing.remove();
    
    const controls = document.createElement('div');
    controls.className = 'content-editor-toggle-controls';
    controls.innerHTML = `
      <button type="button" class="toggle-control-btn" data-action="edit" title="Edit toggles">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 20h9"></path>
          <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
        </svg>
      </button>
      <button type="button" class="toggle-control-btn" data-action="delete" title="Delete toggles">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="3 6 5 6 21 6"></polyline>
          <path d="M19 6l-1 14H6L5 6"></path>
          <path d="M10 11v6"></path>
          <path d="M14 11v6"></path>
          <path d="M9 6V4h6v2"></path>
        </svg>
      </button>
    `;
    
    controls.querySelector('[data-action="edit"]').addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const items = this.extractToggleData(host);
      this.openToggleDialog({
        title: 'Edit Toggle / Accordion',
        submitLabel: 'Save Changes',
        items,
        onSubmit: (newItems) => this.replaceToggleElements(host, newItems)
      });
    });
    
    controls.querySelector('[data-action="delete"]').addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.deleteToggleElement(host);
    });
    
    host.appendChild(controls);
  },
  
  enhanceExistingToggles(editor) {
    if (!editor) return;
    const groups = editor.querySelectorAll('.content-editor-toggle-group');
    groups.forEach(group => {
      group.querySelectorAll('details').forEach(detail => detail.classList.add('content-editor-toggle'));
      this.addToggleControls(group);
    });
    const singles = editor.querySelectorAll('details.content-editor-toggle');
    singles.forEach(detail => {
      if (detail.closest('.content-editor-toggle-group')) return;
      this.addToggleControls(detail);
    });
  },
  
  syncEditorAfterToggleChange(element) {
    const editor = element && element.closest ? element.closest('.content-editor-area') : this.getActiveEditor();
    if (editor && window.ContentEditorSync) {
      const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
      if (hiddenInput) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
    }
    if (window.ContentEditorHistory) {
      setTimeout(() => {
        window.ContentEditorHistory.saveState();
      }, 50);
    }
  },
  
  insertBlockElement(blockElement) {
    const editor = this.getActiveEditor();
    if (!editor || !blockElement) return null;
    const doc = editor.ownerDocument || document;
    const selection = window.getSelection();
    let range = null;
    
    if (selection && selection.rangeCount > 0) {
      const currentRange = selection.getRangeAt(0);
      const container = currentRange.commonAncestorContainer;
      const host = container && container.nodeType === Node.TEXT_NODE ? container.parentNode : container;
      if (host && editor.contains(host)) {
        range = currentRange;
      }
    }
    
    if (!range) {
      const lastChild = editor.lastElementChild || editor.lastChild;
      if (lastChild && lastChild.nodeType === Node.ELEMENT_NODE) {
        range = doc.createRange();
        range.setStartAfter(lastChild);
        range.collapse(true);
      } else {
        const placeholder = doc.createElement('p');
        placeholder.innerHTML = '<br>';
        editor.appendChild(placeholder);
        range = doc.createRange();
        range.selectNodeContents(placeholder);
        range.collapse(false);
      }
    }
    
    let block = range.commonAncestorContainer;
    if (block && block.nodeType === Node.TEXT_NODE) {
      block = block.parentNode;
    }
    const blockTags = ['p','div','h1','h2','h3','h4','h5','h6','blockquote','pre','ul','ol','li'];
    while (block && block !== editor && block.nodeType === Node.ELEMENT_NODE && !blockTags.includes(block.tagName.toLowerCase())) {
      block = block.parentElement;
    }
    
    if (block && block !== editor && editor.contains(block)) {
      if (block.nextSibling) {
        block.parentNode.insertBefore(blockElement, block.nextSibling);
      } else {
        block.parentNode.appendChild(blockElement);
      }
    } else {
      editor.appendChild(blockElement);
    }
    
    const trailingParagraph = doc.createElement('p');
    trailingParagraph.innerHTML = '<br>';
    if (blockElement.nextSibling) {
      blockElement.parentNode.insertBefore(trailingParagraph, blockElement.nextSibling);
    } else {
      blockElement.parentNode.appendChild(trailingParagraph);
    }
    
    return { editor, blockElement, trailingParagraph };
  },

  // Add controls to grid (edit/delete buttons)
    addGridControls(...args) {
    return window.ContentEditorGridCommands.addGridControls(...args);
  },

  // Delete grid element
    deleteGridElement(...args) {
    return window.ContentEditorGridCommands.deleteGridElement(...args);
  },

  // Initialize table controls (add/remove rows/columns on hover)
    initTableControls(...args) {
    return window.ContentEditorTableCommands.initTableControls(...args);
  },
  
    addTableControls(...args) {
    return window.ContentEditorTableCommands.addTableControls(...args);
  },
  
    editTableElement(...args) {
    return window.ContentEditorTableCommands.editTableElement(...args);
  },
  
    resizeTableElement(...args) {
    return window.ContentEditorTableCommands.resizeTableElement(...args);
  },
  
    deleteTableElement(...args) {
    return window.ContentEditorTableCommands.deleteTableElement(...args);
  },
  
  // Hide table controls with a small delay
    hideTableControls(...args) {
    return window.ContentEditorTableCommands.hideTableControls(...args);
  },

  // Handle table hover to show controls at specific position
    handleTableHover(...args) {
    return window.ContentEditorTableCommands.handleTableHover(...args);
  },

  // Attach event listeners to table control buttons
    attachTableControlListeners(...args) {
    return window.ContentEditorTableCommands.attachTableControlListeners(...args);
  },
  
    scheduleTableControlShow(...args) {
    return window.ContentEditorTableCommands.scheduleTableControlShow(...args);
  },
  
    cancelTableControlShow(...args) {
    return window.ContentEditorTableCommands.cancelTableControlShow(...args);
  },
  
    renderRowControl(...args) {
    return window.ContentEditorTableCommands.renderRowControl(...args);
  },
  
    renderColControl(...args) {
    return window.ContentEditorTableCommands.renderColControl(...args);
  },
  
    clearRowHoverState(...args) {
    return window.ContentEditorTableCommands.clearRowHoverState(...args);
  },
  
    clearColHoverState(...args) {
    return window.ContentEditorTableCommands.clearColHoverState(...args);
  },

  // Add a row to the table
    addTableRow(...args) {
    return window.ContentEditorTableCommands.addTableRow(...args);
  },

  // Remove a row from the table
    removeTableRow(...args) {
    return window.ContentEditorTableCommands.removeTableRow(...args);
  },

  // Add a column to the table
    addTableColumn(...args) {
    return window.ContentEditorTableCommands.addTableColumn(...args);
  },

  // Remove a column from the table
    removeTableColumn(...args) {
    return window.ContentEditorTableCommands.removeTableColumn(...args);
  },

  // Insert image
  insertImage() {
    const editor = this.getActiveEditor();
    if (!editor) return;
    
    // Save selection before opening modal
    window.ContentEditorSelection.save();
    
    // Check if media modal is available
    if (window.Birkly && window.Birkly.MediaModal && typeof window.Birkly.MediaModal.open === 'function') {
      window.Birkly.MediaModal.open((media) => {
        if (media && media.url) {
          // Use media title/name as alt text if available
          const altText = media.title || media.name || '';
          this.insertImageElement(media.url, altText);
        }
      });
    } else {
      // Fallback: prompt for URL if media modal is not available
      const url = prompt('Enter image URL:');
      if (url && url.trim()) {
        this.insertImageElement(url.trim(), '');
      }
    }
  },

  // Insert image element at cursor position
  insertImageElement(url, alt = '') {
    const editor = this.getActiveEditor();
    if (!editor) return;
    
    // Restore selection with editor as root element
    window.ContentEditorSelection.restore(editor);
    
    const selection = window.getSelection();
    let range = null;
    
    if (selection.rangeCount > 0) {
      range = selection.getRangeAt(0);
    } else {
      // No selection, create range at end of current block
      const block = this.getCurrentBlock();
      if (block) {
        range = document.createRange();
        range.selectNodeContents(block);
        range.collapse(false);
      } else {
        // No block, create new paragraph
        const newP = document.createElement('p');
        editor.appendChild(newP);
        range = document.createRange();
        range.selectNodeContents(newP);
        range.collapse(false);
      }
    }
    
    // Create image wrapper
    const wrapper = document.createElement('div');
    wrapper.className = 'content-editor-image-wrapper content-editor-image-with-controls';
    wrapper.style.position = 'relative';
    wrapper.style.display = 'inline-block';
    wrapper.style.maxWidth = '100%';
    
    // Create image element
    const img = document.createElement('img');
    img.src = url;
    img.alt = alt || '';
    img.style.maxWidth = '100%';
    img.style.height = 'auto';
    img.style.display = 'block';
    img.className = 'content-editor-image';
    
    // Store original data
    wrapper.dataset.imageUrl = url;
    wrapper.dataset.imageAlt = alt || '';
    wrapper.dataset.imageLightbox = wrapper.dataset.imageLightbox || 'false';
    
    // Add image to wrapper
    wrapper.appendChild(img);
    
    // Add controls
    this.addImageControls(wrapper);
    this.setImageLightbox(wrapper, wrapper.dataset.imageLightbox === 'true');
    
    // Insert image
    try {
      // If range is collapsed, insert at cursor
      if (range.collapsed) {
        // Check if we're in a text node
        if (range.startContainer.nodeType === Node.TEXT_NODE) {
          const textNode = range.startContainer;
          const offset = range.startOffset;
          
          // Split text node if needed
          if (offset < textNode.textContent.length) {
            const afterText = textNode.splitText(offset);
            textNode.parentNode.insertBefore(wrapper, afterText);
          } else {
            textNode.parentNode.insertBefore(wrapper, textNode.nextSibling);
          }
        } else {
          // Insert into element
          const container = range.startContainer;
          if (container.nodeType === Node.ELEMENT_NODE) {
            // Insert at the specified offset
            if (container.childNodes[range.startOffset]) {
              container.insertBefore(wrapper, container.childNodes[range.startOffset]);
            } else {
              container.appendChild(wrapper);
            }
          } else {
            // Fallback: append to editor
            editor.appendChild(wrapper);
          }
        }
      } else {
        // Has selection, replace with image
        range.deleteContents();
        range.insertNode(wrapper);
      }
      
      // Move cursor after image
      const newRange = document.createRange();
      newRange.setStartAfter(wrapper);
      newRange.collapse(true);
      selection.removeAllRanges();
      selection.addRange(newRange);
      
      // Create new paragraph after image if needed
      const nextSibling = wrapper.nextSibling;
      if (!nextSibling || (nextSibling.nodeType === Node.TEXT_NODE && !nextSibling.textContent.trim())) {
        const newP = document.createElement('p');
        newP.innerHTML = '<br>';
        if (nextSibling) {
          wrapper.parentNode.insertBefore(newP, nextSibling);
        } else {
          wrapper.parentNode.appendChild(newP);
        }
        // Move cursor to new paragraph
        const paraRange = document.createRange();
        paraRange.selectNodeContents(newP);
        paraRange.collapse(true);
        selection.removeAllRanges();
        selection.addRange(paraRange);
      }
    } catch (e) {
      console.error('Error inserting image:', e);
      // Fallback: append to editor
      const newP = document.createElement('p');
      newP.appendChild(img);
      editor.appendChild(newP);
      
      // Move cursor after image
      const newRange = document.createRange();
      newRange.setStartAfter(img);
      newRange.collapse(true);
      selection.removeAllRanges();
      selection.addRange(newRange);
    }
    
    // Sync and update toolbar
    if (window.ContentEditorSync) {
      const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
      if (hiddenInput) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
    }
    if (window.ContentEditorToolbar) {
      const toolbar = editor.closest('.content-editor-wrapper')?.querySelector('.content-editor-toolbar') ||
                     document.querySelector('.content-editor-toolbar');
      if (toolbar) {
        window.ContentEditorToolbar.updateState(editor, toolbar);
      }
    }
    
    // Clear saved selection
    window.ContentEditorSelection.clear();
  },

  // Insert video embed
  insertVideo(initialData = null, existingElement = null) {
    const dialogTitle = existingElement ? 'Edit Video' : 'Insert Video';
    const dialogConfig = {
      title: dialogTitle,
      submitLabel: existingElement ? 'Update' : 'Insert',
      initialData,
      onSubmit: existingElement ? (data) => this.updateEmbedElement('video', data, existingElement) : null,
      fields: [
        { name: 'url', label: 'Video URL', type: 'url', placeholder: 'https://example.com/video.mp4 or YouTube/Vimeo URL', required: true },
        { name: 'type', label: 'Video Type', type: 'select', options: [
          { value: 'native', label: 'Native Video (MP4, WebM, etc.)' },
          { value: 'youtube', label: 'YouTube' },
          { value: 'vimeo', label: 'Vimeo' },
          { value: 'iframe', label: 'Custom Iframe' }
        ], value: 'native', required: true },
        { name: 'width', label: 'Width', type: 'text', placeholder: 'e.g., 640 or 100%' },
        { name: 'height', label: 'Height', type: 'text', placeholder: 'e.g., 360 or auto' },
        { name: 'autoplay', label: 'Autoplay', type: 'checkbox' },
        { name: 'controls', label: 'Show Controls', type: 'checkbox', checked: true },
        { name: 'loop', label: 'Loop', type: 'checkbox' },
        { name: 'muted', label: 'Muted', type: 'checkbox' }
      ]
    };
    
    this.createMediaEmbedDialog('video', dialogConfig);
  },

  // Insert audio embed
  insertAudio(initialData = null, existingElement = null) {
    const dialogTitle = existingElement ? 'Edit Audio' : 'Insert Audio';
    const dialogConfig = {
      title: dialogTitle,
      submitLabel: existingElement ? 'Update' : 'Insert',
      initialData,
      onSubmit: existingElement ? (data) => this.updateEmbedElement('audio', data, existingElement) : null,
      fields: [
        { name: 'url', label: 'Audio URL', type: 'url', placeholder: 'https://example.com/audio.mp3', required: true },
        { name: 'autoplay', label: 'Autoplay', type: 'checkbox' },
        { name: 'controls', label: 'Show Controls', type: 'checkbox', checked: true },
        { name: 'loop', label: 'Loop', type: 'checkbox' },
        { name: 'preload', label: 'Preload', type: 'select', options: [
          { value: 'auto', label: 'Auto' },
          { value: 'metadata', label: 'Metadata' },
          { value: 'none', label: 'None' }
        ] }
      ]
    };
    
    this.createMediaEmbedDialog('audio', dialogConfig);
  },

  // Insert maps embed
  insertMaps(initialData = null, existingElement = null) {
    const dialogTitle = existingElement ? 'Edit Map' : 'Insert Map';
    const dialogConfig = {
      title: dialogTitle,
      submitLabel: existingElement ? 'Update' : 'Insert',
      initialData,
      onSubmit: existingElement ? (data) => this.updateEmbedElement('maps', data, existingElement) : null,
      fields: [
        { name: 'provider', label: 'Map Provider', type: 'select', options: [
          { value: 'google', label: 'Google Maps' },
          { value: 'openstreetmap', label: 'OpenStreetMap' },
          { value: 'embed', label: 'Custom Embed URL' }
        ], value: 'google', required: true },
        { name: 'input_method', label: 'Input Method', type: 'select', options: [
          { value: 'address', label: 'Address / Location Search' },
          { value: 'coordinates', label: 'Coordinates (Lat, Lng)' },
          { value: 'embed_url', label: 'Embed URL' }
        ], value: 'address', required: true },
        { name: 'address', label: 'Address or Location', type: 'text', placeholder: 'e.g., New York, NY or 123 Main St, City', condition: { field: 'input_method', value: 'address' } },
        { name: 'latitude', label: 'Latitude', type: 'text', placeholder: 'e.g., 40.7128', condition: { field: 'input_method', value: 'coordinates' } },
        { name: 'longitude', label: 'Longitude', type: 'text', placeholder: 'e.g., -74.0060', condition: { field: 'input_method', value: 'coordinates' } },
        { name: 'embed_url', label: 'Map Embed URL', type: 'url', placeholder: 'https://www.google.com/maps/embed?pb=...', condition: { field: 'input_method', value: 'embed_url' } },
        { name: 'zoom', label: 'Zoom Level', type: 'number', placeholder: '1-20', value: '15', min: 1, max: 20 },
        { name: 'width', label: 'Width', type: 'text', placeholder: 'e.g., 640 or 100%', value: '100%' },
        { name: 'height', label: 'Height', type: 'text', placeholder: 'e.g., 400', value: '400' },
        { name: 'api_key', label: 'Google Maps API Key (optional)', type: 'text', placeholder: 'Only needed for Google Maps', condition: { field: 'provider', value: 'google' } }
      ]
    };
    
    this.createMediaEmbedDialog('maps', dialogConfig);
  },

  // Insert link preview
  insertLinkPreview(initialData = null, existingElement = null) {
    const dialogTitle = existingElement ? 'Edit Link Preview' : 'Insert Link Preview';
    const dialogConfig = {
      title: dialogTitle,
      submitLabel: existingElement ? 'Update' : 'Insert',
      initialData,
      enableMetadata: true,
      onSubmit: existingElement ? (data) => this.updateEmbedElement('link-preview', data, existingElement) : null,
      fields: [
        { name: 'url', label: 'Link URL', type: 'url', placeholder: 'https://example.com', required: true },
        { name: 'title', label: 'Title', type: 'text', placeholder: 'Link title' },
        { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Link description' },
        { name: 'image', label: 'Preview Image URL', type: 'url', placeholder: 'https://example.com/image.jpg' },
        { name: 'site_name', label: '', type: 'hidden' }
      ]
    };
    
    this.createMediaEmbedDialog('link-preview', dialogConfig);
  },

  // Insert iframe embed
  insertIframe(initialData = null, existingElement = null) {
    const dialogTitle = existingElement ? 'Edit Iframe' : 'Insert Iframe';
    const dialogConfig = {
      title: dialogTitle,
      submitLabel: existingElement ? 'Update' : 'Insert',
      initialData,
      onSubmit: existingElement ? (data) => this.updateEmbedElement('iframe', data, existingElement) : null,
      fields: [
        { name: 'url', label: 'Iframe URL', type: 'url', placeholder: 'https://example.com/embed', required: true },
        { name: 'width', label: 'Width', type: 'text', placeholder: 'e.g., 640 or 100%', value: '100%' },
        { name: 'height', label: 'Height', type: 'text', placeholder: 'e.g., 360', value: '400' },
        { name: 'allowfullscreen', label: 'Allow Fullscreen', type: 'checkbox', checked: true },
        { name: 'sandbox', label: 'Sandbox Attributes', type: 'text', placeholder: 'allow-scripts allow-same-origin' }
      ]
    };
    
    this.createMediaEmbedDialog('iframe', dialogConfig);
  },

  // Insert custom embed
  insertCustomEmbed(initialData = null, existingElement = null) {
    const dialogTitle = existingElement ? 'Edit Custom Embed' : 'Insert Custom Embed';
    const dialogConfig = {
      title: dialogTitle,
      submitLabel: existingElement ? 'Update' : 'Insert',
      initialData,
      onSubmit: existingElement ? (data) => this.updateEmbedElement('custom', data, existingElement) : null,
      fields: [
        { name: 'html', label: 'Custom HTML', type: 'textarea', placeholder: '<div>Custom embed HTML</div>', required: true },
        { name: 'label', label: 'Label (for editor preview)', type: 'text', placeholder: 'Custom Embed' }
      ]
    };
    
    this.createMediaEmbedDialog('custom', dialogConfig);
  },

  // Insert component
  async insertComponent(existingComponent = null) {
    const editor = this.getActiveEditor();
    if (!editor) {
      console.error('ContentEditor: Editor not found');
      return;
    }

    // Save selection
    window.ContentEditorSelection.save();

    try {
      const registry = window.ContentEditorComponentRegistry;
      const components = await registry.list();
      
      // If editing existing component, find it
      let selectedComponent = null;
      if (existingComponent) {
        const componentSlug = existingComponent.dataset.componentSlug;
        try {
          selectedComponent = await registry.get(componentSlug);
        } catch (e) {
          console.warn('ContentEditor: Could not load component', componentSlug);
        }
      }

      // Show component library modal
      this.showComponentLibrary(components, selectedComponent, existingComponent);
    } catch (error) {
      console.error('ContentEditor: Error loading components', error);
      alert('Failed to load components. Please try again.');
    }
  },

  // Show component library modal
  showComponentLibrary(components, selectedComponent = null, existingElement = null) {
    const editor = this.getActiveEditor();
    if (!editor) return;

    // Group components by category
    const grouped = {};
    components.forEach(comp => {
      const category = comp.category || 'uncategorized';
      if (!grouped[category]) {
        grouped[category] = [];
      }
      grouped[category].push(comp);
    });

    // Create modal with tabs
    const overlay = document.createElement('div');
    overlay.className = 'content-editor-dialog-overlay';
    
    const dialog = document.createElement('div');
    dialog.className = 'content-editor-dialog';
    dialog.innerHTML = `
      <div class="content-editor-dialog-overlay"></div>
      <div class="content-editor-dialog-content" style="max-width: 900px; max-height: 85vh;">
        <div class="content-editor-dialog-header">
          <h3>Components</h3>
          <button type="button" class="content-editor-dialog-close" aria-label="Close">×</button>
        </div>
        <div class="component-modal-tabs" style="display: flex; border-bottom: 1px solid #e5e7eb; padding: 0 16px;">
          <button type="button" class="component-tab-btn active" data-tab="library" style="
            padding: 12px 16px;
            border: none;
            background: transparent;
            border-bottom: 2px solid #3b82f6;
            color: #3b82f6;
            font-weight: 500;
            cursor: pointer;
            font-size: 14px;
          ">Library</button>
          <button type="button" class="component-tab-btn" data-tab="manage" style="
            padding: 12px 16px;
            border: none;
            background: transparent;
            border-bottom: 2px solid transparent;
            color: #6b7280;
            font-weight: 500;
            cursor: pointer;
            font-size: 14px;
          ">Manage</button>
        </div>
        <div class="content-editor-dialog-body" style="overflow-y: auto; max-height: calc(85vh - 180px); padding: 16px;">
          <!-- Library Tab -->
          <div class="component-tab-content" data-tab-content="library" style="display: block;">
            ${components.length === 0 ? `
              <div style="text-align: center; padding: 40px;">
                <p>No components available.</p>
                <p style="color: #6b7280; font-size: 14px; margin-top: 8px;">Create your first component in the "Manage" tab.</p>
              </div>
            ` : `
              <div class="component-library">
                ${Object.keys(grouped).map(category => `
                  <div class="component-category" style="margin-bottom: 24px;">
                    <h4 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 600; color: #374151; text-transform: capitalize;">${category}</h4>
                    <div class="component-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px;">
                      ${grouped[category].map(comp => `
                        <button type="button" class="component-item" data-slug="${comp.slug}" style="
                          padding: 16px;
                          border: 2px solid ${selectedComponent && selectedComponent.slug === comp.slug ? '#3b82f6' : '#e5e7eb'};
                          border-radius: 8px;
                          background: ${selectedComponent && selectedComponent.slug === comp.slug ? '#eff6ff' : '#ffffff'};
                          cursor: pointer;
                          text-align: left;
                          transition: all 0.15s ease;
                        ">
                          <div style="font-weight: 500; color: #111827; margin-bottom: 4px;">${comp.name}</div>
                          ${comp.description ? `<div style="font-size: 12px; color: #6b7280;">${comp.description}</div>` : ''}
                        </button>
                      `).join('')}
                    </div>
                  </div>
                `).join('')}
              </div>
            `}
          </div>
          
          <!-- Manage Tab -->
          <div class="component-tab-content" data-tab-content="manage" style="display: none;">
            <div style="margin-bottom: 16px;">
              <button type="button" class="content-editor-dialog-btn content-editor-dialog-btn-primary" data-action="create-component" style="margin-bottom: 0;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; vertical-align: middle;">
                  <line x1="12" y1="5" x2="12" y2="19"></line>
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Create New Component
              </button>
            </div>
            ${components.length === 0 ? `
              <div style="text-align: center; padding: 40px; color: #6b7280;">
                <p>No components yet. Create your first component to get started.</p>
              </div>
            ` : `
              <div class="component-list" style="display: flex; flex-direction: column; gap: 8px;">
                ${components.map(comp => {
                  const isBundled = window.ContentEditorComponentRegistry && window.ContentEditorComponentRegistry.isBundled(comp);
                  return `
                  <div class="component-list-item" data-slug="${comp.slug}" data-bundled="${isBundled ? 'true' : 'false'}" style="
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 12px 16px;
                    background: #ffffff;
                    border: 1px solid #e5e7eb;
                    border-radius: 6px;
                  ">
                    <div style="flex: 1;">
                      <div style="font-weight: 500; color: #111827; margin-bottom: 4px;">${comp.name}${isBundled ? ' <span style="font-size:10px;font-weight:600;color:#6b7280;background:#f3f4f6;padding:2px 6px;border-radius:4px;margin-left:6px;">Built-in</span>' : ''}</div>
                      ${comp.description ? `<div style="font-size: 12px; color: #6b7280;">${comp.description}</div>` : ''}
                      <div style="font-size: 11px; color: #9ca3af; margin-top: 4px;">Category: ${comp.category || 'uncategorized'}</div>
                    </div>
                    ${isBundled ? '' : `<div style="display: flex; gap: 8px;">
                      <button type="button" class="component-action-btn component-edit-action" data-slug="${comp.slug}" title="Edit" style="
                        width: 32px;
                        height: 32px;
                        border: 1px solid #e5e7eb;
                        background: #ffffff;
                        border-radius: 4px;
                        cursor: pointer;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        color: #374151;
                        transition: all 0.15s ease;
                      " onmouseover="this.style.background='#dbeafe'; this.style.borderColor='#93c5fd'; this.style.color='#1e40af';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#e5e7eb'; this.style.color='#374151';">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                      </button>
                      <button type="button" class="component-action-btn component-delete-action" data-slug="${comp.slug}" title="Delete" style="
                        width: 32px;
                        height: 32px;
                        border: 1px solid #fee2e2;
                        background: #ffffff;
                        border-radius: 4px;
                        cursor: pointer;
                        display: inline-flex;
                        align-items: center;
                        justify-content: center;
                        color: #dc2626;
                        transition: all 0.15s ease;
                      " onmouseover="this.style.background='#fee2e2'; this.style.borderColor='#fca5a5'; this.style.color='#991b1b';" onmouseout="this.style.background='#ffffff'; this.style.borderColor='#fee2e2'; this.style.color='#dc2626';">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <polyline points="3 6 5 6 21 6"></polyline>
                          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                      </button>
                    </div>`}
                  </div>
                `;
                }).join('')}
              </div>
            `}
          </div>
        </div>
        <div class="content-editor-dialog-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 16px; border-top: 1px solid #e5e7eb;">
          <div>
            <button type="button" class="content-editor-dialog-btn content-editor-dialog-btn-secondary" data-action="manage-components" style="display: none;">
              Manage Components
            </button>
          </div>
          <div style="display: flex; gap: 8px;">
            <button type="button" class="content-editor-dialog-btn content-editor-dialog-btn-secondary" data-action="cancel">Cancel</button>
            <button type="button" class="content-editor-dialog-btn content-editor-dialog-btn-primary" data-action="insert" ${selectedComponent || existingElement ? '' : 'disabled'} style="display: ${components.length > 0 || existingElement ? 'inline-block' : 'none'};">${existingElement ? 'Update' : 'Insert'}</button>
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(overlay);
    document.body.appendChild(dialog);

    // Tab switching
    let currentTab = 'library';
    const tabBtns = dialog.querySelectorAll('.component-tab-btn');
    const tabContents = dialog.querySelectorAll('.component-tab-content');
    const insertBtn = dialog.querySelector('[data-action="insert"]');
    const manageBtn = dialog.querySelector('[data-action="manage-components"]');

    tabBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const tab = btn.dataset.tab;
        currentTab = tab;
        
        // Update tab buttons
        tabBtns.forEach(b => {
          b.style.borderBottomColor = 'transparent';
          b.style.color = '#6b7280';
        });
        btn.style.borderBottomColor = '#3b82f6';
        btn.style.color = '#3b82f6';
        
        // Update tab contents
        tabContents.forEach(content => {
          content.style.display = content.dataset.tabContent === tab ? 'block' : 'none';
        });
        
        // Show/hide buttons based on tab
        if (tab === 'library') {
          if (insertBtn) {
            insertBtn.style.display = components.length > 0 ? 'inline-block' : 'none';
          }
          if (manageBtn) {
            manageBtn.style.display = 'none';
          }
        } else {
          if (insertBtn) {
            insertBtn.style.display = 'none';
          }
          if (manageBtn) {
            manageBtn.style.display = 'none';
          }
        }
      });
    });

    // Component selection (Library tab)
    let currentSelection = selectedComponent ? selectedComponent.slug : null;
    dialog.querySelectorAll('.component-item').forEach(btn => {
      btn.addEventListener('click', () => {
        // Update selection
        dialog.querySelectorAll('.component-item').forEach(item => {
          item.style.borderColor = '#e5e7eb';
          item.style.background = '#ffffff';
        });
        btn.style.borderColor = '#3b82f6';
        btn.style.background = '#eff6ff';
        currentSelection = btn.dataset.slug;
        
        // Enable insert button
        if (insertBtn) {
          insertBtn.disabled = false;
        }
      });
    });

    // Create component button
    dialog.querySelector('[data-action="create-component"]')?.addEventListener('click', () => {
      this.showComponentEditor(dialog, overlay, null, components);
    });

    // Edit component buttons
    dialog.querySelectorAll('.component-edit-action').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const slug = btn.dataset.slug;
        try {
          const component = await window.ContentEditorComponentRegistry.get(slug);
          this.showComponentEditor(dialog, overlay, component, components);
        } catch (error) {
          console.error('Error loading component', error);
          alert('Failed to load component');
        }
      });
    });

    // Delete component buttons
    dialog.querySelectorAll('.component-delete-action').forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const slug = btn.dataset.slug;
        if (!confirm(`Delete component "${slug}"? This cannot be undone.`)) {
          return;
        }
        
        try {
          const result = await window.ContentEditorComponentRegistry.delete(slug);
          
          if (result.success) {
            // Remove from list
            btn.closest('.component-list-item').remove();
            // Refresh library tab if needed
            const libraryTab = dialog.querySelector('[data-tab-content="library"]');
            if (libraryTab && libraryTab.style.display !== 'none') {
              // Reload the entire modal
              this.insertComponent(null);
            }
          } else {
            alert('Failed to delete component: ' + (result.error || 'Unknown error'));
          }
        } catch (error) {
          console.error('Error deleting component', error);
          alert('Failed to delete component');
        }
      });
    });

    // Close handlers
    const closeDialog = () => {
      document.body.removeChild(overlay);
      document.body.removeChild(dialog);
      // Restore selection with active editor
      const editor = this.getActiveEditor();
      if (editor) {
        window.ContentEditorSelection.restore(editor);
      } else {
        window.ContentEditorSelection.restore();
      }
    };

    dialog.querySelector('.content-editor-dialog-close').addEventListener('click', closeDialog);
    dialog.querySelector('[data-action="cancel"]').addEventListener('click', closeDialog);
    overlay.addEventListener('click', closeDialog);

    // Insert handler
    if (insertBtn) {
      insertBtn.addEventListener('click', async () => {
        if (!currentSelection) return;

        try {
          const component = await window.ContentEditorComponentRegistry.get(currentSelection);
          
          // Get entry data for templating (from the form or current entry)
          const entryData = this.getEntryDataForComponent(editor);
          
          // Create component element
          const componentElement = this.createComponentElement(component, entryData);
          
          // Insert or update
          if (existingElement) {
            existingElement.parentNode.replaceChild(componentElement, existingElement);
          } else {
            this.insertComponentElement(componentElement, editor);
          }
          
          // Sync and save history
          if (window.ContentEditorSync) {
            const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
            if (hiddenInput) {
              window.ContentEditorSync.toHidden(editor, hiddenInput);
            }
          }
          if (window.ContentEditorHistory) {
            window.ContentEditorHistory.saveState();
          }
          
          closeDialog();
        } catch (error) {
          console.error('ContentEditor: Error inserting component', error);
          alert('Failed to insert component. Please try again.');
        }
      });
    }
  },

  // Show component editor (create/edit)
  showComponentEditor(parentDialog, parentOverlay, component = null, allComponents = []) {
    const isEditing = component !== null;
    const editor = this.getActiveEditor();
    
    // Create editor modal
    const editorOverlay = document.createElement('div');
    editorOverlay.className = 'content-editor-dialog-overlay';
    editorOverlay.style.zIndex = '12000';
    
    const editorDialog = document.createElement('div');
    editorDialog.className = 'content-editor-dialog';
    editorDialog.style.zIndex = '12001';
    editorDialog.innerHTML = `
      <div class="content-editor-dialog-overlay"></div>
      <div class="content-editor-dialog-content" style="max-width: 1000px; max-height: 90vh;">
        <div class="content-editor-dialog-header">
          <h3>${isEditing ? 'Edit Component' : 'Create Component'}</h3>
          <button type="button" class="content-editor-dialog-close" aria-label="Close">×</button>
        </div>
        <div class="content-editor-dialog-body" style="overflow-y: auto; max-height: calc(90vh - 140px); padding: 20px;">
          <form id="component-editor-form" style="display: flex; flex-direction: column; gap: 16px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
              <div>
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #374151; font-size: 14px;">Name *</label>
                <input type="text" id="component-name" name="name" value="${component?.name || ''}" required style="
                  width: 100%;
                  padding: 8px 12px;
                  border: 1px solid #d1d5db;
                  border-radius: 6px;
                  font-size: 14px;
                " placeholder="My Component">
              </div>
              <div>
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #374151; font-size: 14px;">Slug *</label>
                <input type="text" id="component-slug" name="slug" value="${component?.slug || ''}" required style="
                  width: 100%;
                  padding: 8px 12px;
                  border: 1px solid #d1d5db;
                  border-radius: 6px;
                  font-size: 14px;
                " placeholder="my-component">
                <small style="color: #6b7280; font-size: 12px; margin-top: 4px; display: block;">Lowercase letters, numbers, and hyphens only</small>
              </div>
            </div>
            
            <div>
              <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #374151; font-size: 14px;">Description</label>
              <input type="text" id="component-description" name="description" value="${component?.description || ''}" style="
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-size: 14px;
              " placeholder="A brief description of this component">
            </div>
            
            <div>
              <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #374151; font-size: 14px;">Category</label>
              <input type="text" id="component-category" name="category" value="${component?.category || 'uncategorized'}" style="
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-size: 14px;
              " placeholder="uncategorized">
            </div>
            
            <div>
              <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #374151; font-size: 14px;">Template (HTML) *</label>
              <textarea id="component-template" name="template" required style="
                width: 100%;
                min-height: 200px;
                padding: 12px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
                font-size: 13px;
                line-height: 1.5;
                resize: vertical;
              " placeholder="<div class=&quot;my-component&quot;>
  <h2>{ title }</h2>
  <p>{ description }</p>
</div>">${component?.template || ''}</textarea>
              <small style="color: #6b7280; font-size: 12px; margin-top: 4px; display: block;">Use { variable } to access entry field values</small>
            </div>
            
            <div>
              <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #374151; font-size: 14px;">Style (CSS)</label>
              <textarea id="component-style" name="style" style="
                width: 100%;
                min-height: 150px;
                padding: 12px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
                font-size: 13px;
                line-height: 1.5;
                resize: vertical;
              " placeholder=".my-component {
  padding: 20px;
  background: #f3f4f6;
}">${component?.style || ''}</textarea>
            </div>
            
            <div>
              <label style="display: block; margin-bottom: 6px; font-weight: 500; color: #374151; font-size: 14px;">Script (JavaScript)</label>
              <textarea id="component-script" name="script" style="
                width: 100%;
                min-height: 150px;
                padding: 12px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
                font-size: 13px;
                line-height: 1.5;
                resize: vertical;
              " placeholder="// Component JavaScript
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
              <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" id="component-auto-scope" name="auto_scope_css" ${component?.auto_scope_css !== false ? 'checked' : ''} style="width: 18px; height: 18px;">
                <span style="font-weight: 500; color: #374151; font-size: 14px;">Auto-scope CSS</span>
              </label>
              <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" id="component-execute-js" name="execute_js" ${component?.execute_js !== false ? 'checked' : ''} style="width: 18px; height: 18px;">
                <span style="font-weight: 500; color: #374151; font-size: 14px;">Execute JavaScript</span>
              </label>
            </div>
          </form>
        </div>
        <div class="content-editor-dialog-footer" style="display: flex; justify-content: flex-end; gap: 8px; padding: 16px; border-top: 1px solid #e5e7eb;">
          <button type="button" class="content-editor-dialog-btn content-editor-dialog-btn-secondary" data-action="cancel-editor">Cancel</button>
          <button type="button" class="content-editor-dialog-btn content-editor-dialog-btn-primary" data-action="save-component">${isEditing ? 'Update' : 'Create'}</button>
        </div>
      </div>
    `;

    document.body.appendChild(editorOverlay);
    document.body.appendChild(editorDialog);

    // Auto-generate slug from name
    const nameInput = editorDialog.querySelector('#component-name');
    const slugInput = editorDialog.querySelector('#component-slug');
    
    if (!isEditing) {
      nameInput.addEventListener('input', () => {
        if (!slugInput.dataset.manualEdit) {
          const slug = nameInput.value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
          slugInput.value = slug;
        }
      });
      
      slugInput.addEventListener('focus', () => {
        slugInput.dataset.manualEdit = 'true';
      });
    }

    // Close handlers
    const closeEditor = () => {
      document.body.removeChild(editorOverlay);
      document.body.removeChild(editorDialog);
    };

    editorDialog.querySelector('.content-editor-dialog-close').addEventListener('click', closeEditor);
    editorDialog.querySelector('[data-action="cancel-editor"]').addEventListener('click', closeEditor);
    editorOverlay.addEventListener('click', closeEditor);

    // Save handler
    editorDialog.querySelector('[data-action="save-component"]').addEventListener('click', async () => {
      const form = editorDialog.querySelector('#component-editor-form');
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      const componentData = {
        name: editorDialog.querySelector('#component-name').value.trim(),
        slug: editorDialog.querySelector('#component-slug').value.trim(),
        description: editorDialog.querySelector('#component-description').value.trim(),
        category: editorDialog.querySelector('#component-category').value.trim() || 'uncategorized',
        template: editorDialog.querySelector('#component-template').value,
        style: editorDialog.querySelector('#component-style').value,
        script: editorDialog.querySelector('#component-script').value,
        auto_scope_css: editorDialog.querySelector('#component-auto-scope').checked,
        execute_js: editorDialog.querySelector('#component-execute-js').checked
      };

      // Validate slug
      if (!/^[a-z0-9-]+$/.test(componentData.slug)) {
        alert('Slug can only contain lowercase letters, numbers, and hyphens');
        return;
      }

      // Check for duplicate slug (if creating new)
      if (!isEditing && allComponents.some(c => c.slug === componentData.slug)) {
        alert('A component with this slug already exists');
        return;
      }

      try {
        // First, check if updating and if there are entries using this component
        let shouldUpdateEntries = false;
        let entryCount = 0;
        
        if (isEditing) {
          const entriesResult = await window.ContentEditorComponentRegistry.findEntries(componentData.slug);
          
          if (entriesResult.success && entriesResult.entries && entriesResult.entries.length > 0) {
            entryCount = entriesResult.entries.length;
            const entryList = entriesResult.entries.slice(0, 5).map(e => `• ${e.title} (${e.collection})`).join('\n');
            const moreText = entryCount > 5 ? `\n... and ${entryCount - 5} more` : '';
            
            shouldUpdateEntries = confirm(
              `This component is used in ${entryCount} entr${entryCount === 1 ? 'y' : 'ies'}:\n\n${entryList}${moreText}\n\n` +
              `Do you want to update all entries using this component?\n\n` +
              `Click "OK" to update all entries, or "Cancel" to update only new instances.`
            );
          }
        }
        
        // Save the component first
        const result = await window.ContentEditorComponentRegistry.save(componentData);

        if (!result.success) {
          alert('Failed to save component: ' + (result.error || 'Unknown error'));
          return;
        }
        
        // Now update entries if requested (component is already saved at this point)
        if (shouldUpdateEntries) {
          const updateResult = await window.ContentEditorComponentRegistry.updateEntries(componentData.slug);
          if (updateResult.success) {
            alert(`Component updated successfully!\n\nUpdated ${updateResult.updated} entr${updateResult.updated === 1 ? 'y' : 'ies'}.`);
          } else {
            alert('Component saved, but failed to update entries: ' + (updateResult.error || 'Unknown error'));
          }
        }

        closeEditor();
        // Close parent modal and reload
        if (parentDialog && parentOverlay) {
          document.body.removeChild(parentOverlay);
          document.body.removeChild(parentDialog);
        }
        // Reload component library
        this.insertComponent(null);
      } catch (error) {
        console.error('Error saving component', error);
        alert('Failed to save component. Please try again.');
      }
    });
  },

  // Get entry data for component templating
  getEntryDataForComponent(editor) {
    // Try to get entry data from the form
    const form = editor.closest('form');
    if (!form) return {};

    const entryData = {};
    
    // Get all input fields in the form
    const inputs = form.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
      const name = input.name || input.id;
      if (name && name !== 'csrf_token') {
        if (input.type === 'checkbox' || input.type === 'radio') {
          if (input.checked) {
            entryData[name] = input.value;
          }
        } else {
          entryData[name] = input.value;
        }
      }
    });

    return entryData;
  },

  // Create component element
  createComponentElement(component, entryData = {}) {
    const wrapper = document.createElement('div');
    wrapper.className = 'content-editor-component';
    wrapper.dataset.componentSlug = component.slug;
    wrapper.dataset.componentName = component.name;
    // Store raw template for processing later
    // Use textContent to avoid double-escaping issues
    wrapper.setAttribute('data-component-template', component.template || '');
    wrapper.setAttribute('data-component-style', component.style || '');
    wrapper.setAttribute('data-component-script', component.script || '');
    wrapper.setAttribute('data-component-auto-scope', component.auto_scope_css !== false ? 'true' : 'false');
    wrapper.setAttribute('data-component-execute-js', component.execute_js !== false ? 'true' : 'false');
    // Store updated_at timestamp for version checking
    wrapper.setAttribute('data-component-updated-at', component.updated_at || '');
    
    // Create component ID for scoping
    const componentId = `component-${component.slug}-${Date.now()}`;
    wrapper.id = componentId;
    
    // Check if template contains templating engine syntax
    const hasTemplatingSyntax = /\{for\s+each|\{if\s+|\{endfor|\{endif|\{else/i.test(component.template || '');
    
    if (hasTemplatingSyntax) {
      // Template uses advanced syntax - process it immediately
      // Show a loading indicator first
      const loadingDiv = document.createElement('div');
      loadingDiv.style.cssText = 'padding: 20px; text-align: center; color: #6b7280;';
      loadingDiv.innerHTML = `
        <div style="margin-bottom: 8px;">Loading component...</div>
        <div style="font-size: 12px;">${component.name}</div>
      `;
      wrapper.innerHTML = '';
      wrapper.appendChild(loadingDiv);
      
      // Apply CSS and JS immediately (processComponentTemplate will re-apply, but this ensures they're available)
      if (component.style) {
        const styleId = `component-style-${componentId}`;
        let styleEl = document.getElementById(styleId);
        if (!styleEl) {
          styleEl = document.createElement('style');
          styleEl.id = styleId;
          document.head.appendChild(styleEl);
        }
        let scopedCSS = component.style;
        if (component.auto_scope_css !== false) {
          scopedCSS = this.scopeComponentCSS(scopedCSS, `#${componentId}`);
        }
        styleEl.textContent = scopedCSS;
      }
      
      if (component.script && component.execute_js !== false) {
        const scriptId = `component-script-${componentId}`;
        let scriptEl = document.getElementById(scriptId);
        if (!scriptEl) {
          scriptEl = document.createElement('script');
          scriptEl.id = scriptId;
          scriptEl.type = 'text/javascript';
          document.head.appendChild(scriptEl);
        }
        const scopedScript = `
          (function() {
            const componentElement = document.getElementById('${componentId}');
            if (!componentElement) return;
            ${component.script}
          })();
        `;
        scriptEl.textContent = scopedScript;
      }
      
      // Process the template asynchronously
      this.processComponentTemplate(wrapper, component.template || '').then(async () => {
        // Add controls after processing
        await this.addComponentControls(wrapper);
      }).catch(async error => {
        console.error('ContentEditor: Error processing component template on insert', error);
        // Show error message
        wrapper.innerHTML = `
          <div style="padding: 20px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; color: #991b1b;">
            <p style="margin: 0 0 8px 0; font-weight: 600;">Error loading component</p>
            <p style="margin: 0; font-size: 12px;">${component.name}</p>
          </div>
        `;
        // Still add controls even on error
        await this.addComponentControls(wrapper);
      });
    } else {
      // Simple template - try to render with variable replacement
      let renderedHTML = component.template || '';
      
      // Simple variable replacement: { variable } or { field_name }
      renderedHTML = renderedHTML.replace(/\{([^}]+)\}/g, (match, varName) => {
        varName = varName.trim();
        
        // Check entry data first
        if (entryData[varName] !== undefined) {
          return entryData[varName];
        }
        
        // If it's a simple variable name, return empty or placeholder
        if (/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(varName)) {
          return `<span style="color: #9ca3af; font-style: italic;">[${varName}]</span>`;
        }
        
        // Preserve complex syntax
        return match;
      });
      
      wrapper.innerHTML = renderedHTML;
      
      // If still no visible content, show placeholder
      setTimeout(() => {
        const textContent = (wrapper.textContent || wrapper.innerText || '').trim();
        const hasElements = wrapper.querySelector('h1, h2, h3, h4, h5, h6, p, div, span, img, a, ul, ol, li, blockquote, pre, code, table');
        
        if (!textContent && !hasElements) {
          const placeholder = document.createElement('div');
          placeholder.style.cssText = 'padding: 20px; text-align: center; color: #6b7280; font-style: italic;';
          placeholder.innerHTML = `
            <p style="margin: 0 0 8px 0; font-weight: 500; color: #111827; font-style: normal;">${component.name}</p>
            <p style="margin: 0; font-size: 12px;">Component template</p>
          `;
          wrapper.innerHTML = '';
          wrapper.appendChild(placeholder);
        }
      }, 50);
    }
    
    // Add CSS if exists
    if (component.style) {
      const styleId = `component-style-${componentId}`;
      let styleEl = document.getElementById(styleId);
      if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = styleId;
        document.head.appendChild(styleEl);
      }
      
      // Auto-scope CSS if enabled
      let scopedCSS = component.style;
      if (component.auto_scope_css !== false) {
        scopedCSS = this.scopeComponentCSS(scopedCSS, `#${componentId}`);
      }
      
      styleEl.textContent = scopedCSS;
    }
    
    // Add JavaScript if exists and enabled
    if (component.script && component.execute_js !== false) {
      const scriptId = `component-script-${componentId}`;
      let scriptEl = document.getElementById(scriptId);
      if (!scriptEl) {
        scriptEl = document.createElement('script');
        scriptEl.id = scriptId;
        scriptEl.type = 'text/javascript';
        document.head.appendChild(scriptEl);
      }
      
      // Wrap script to scope to this component
      const scopedScript = `
        (function() {
          const componentElement = document.getElementById('${componentId}');
          if (!componentElement) return;
          ${component.script}
        })();
      `;
      
      scriptEl.textContent = scopedScript;
    }
    
    // Add controls
    this.addComponentControls(wrapper);
    
    return wrapper;
  },

  // Scope component CSS
  scopeComponentCSS(css, scopeSelector) {
    if (!css || !scopeSelector) return css;
    
    // Handle @ rules (media queries, keyframes, etc.) separately
    // Match @ rules with nested braces
    const atRuleRegex = /(@[^{]+)\{((?:[^{}]|\{(?:[^{}]|\{[^{}]*\})*\})*)\}/g;
    const processedCSS = css.replace(atRuleRegex, (match, atRule, content) => {
      // Scope selectors inside @ rule content
      const scopedContent = content.replace(/([^{}]+)\{([^{}]*)\}/g, (ruleMatch, selector, declarations) => {
        // Skip if already scoped
        if (selector.includes(scopeSelector)) {
          return ruleMatch;
        }
        const scopedSelector = this.scopeCSSSelector(selector.trim(), scopeSelector);
        return `${scopedSelector} {${declarations}}`;
      });
      return `${atRule}{${scopedContent}}`;
    });
    
    // Process regular CSS rules (not inside @ rules)
    // Match rules that are not inside @ rules
    const regularRuleRegex = /([^{}@]+)\{([^{}]*)\}/g;
    const finalCSS = processedCSS.replace(regularRuleRegex, (match, selector, declarations) => {
      // Skip if already scoped or is part of @ rule
      if (selector.includes(scopeSelector) || selector.trim().startsWith('@')) {
        return match;
      }
      const scopedSelector = this.scopeCSSSelector(selector.trim(), scopeSelector);
      return `${scopedSelector} {${declarations}}`;
    });
    
    return finalCSS;
  },

  // Scope a CSS selector
  scopeCSSSelector(selector, scope) {
    selector = selector.trim();
    if (!selector) return selector;
    
    // Handle comma-separated selectors
    const selectors = selector.split(',').map(s => s.trim());
    const scoped = selectors.map(sel => {
      // Don't scope :root, html, body at root level
      if (sel === ':root' || sel === 'html' || sel === 'body') {
        return `${scope} ${sel}`;
      }
      
      // Don't scope if already scoped
      if (sel.includes(scope)) {
        return sel;
      }
      
      // Scope the selector
      return `${scope} ${sel}`;
    });
    
    return scoped.join(', ');
  },

  // Insert component element into editor
  insertComponentElement(element, editor) {
    const selection = window.getSelection();
    let range = null;
    
    if (selection.rangeCount > 0) {
      range = selection.getRangeAt(0);
    } else {
      const block = this.getCurrentBlock();
      if (block) {
        range = document.createRange();
        range.selectNodeContents(block);
        range.collapse(false);
      }
    }
    
    if (range) {
      // Find block element
      let block = range.commonAncestorContainer;
      while (block && block !== editor) {
        if (block.nodeType === Node.ELEMENT_NODE) {
          const tagName = block.tagName.toLowerCase();
          if (['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'ul', 'ol', 'li'].includes(tagName)) {
            break;
          }
        }
        block = block.parentNode;
      }
      
      if (block && block !== editor && editor.contains(block)) {
        if (block.nextSibling) {
          block.parentNode.insertBefore(element, block.nextSibling);
        } else {
          block.parentNode.appendChild(element);
        }
      } else {
        editor.appendChild(element);
      }
    } else {
      editor.appendChild(element);
    }
    
    // Move cursor after component
    const newRange = document.createRange();
    newRange.setStartAfter(element);
    newRange.collapse(true);
    selection.removeAllRanges();
    selection.addRange(newRange);
  },

  // Add edit/delete controls to component
  async addComponentControls(componentElement) {
    // Remove existing controls
    const existing = componentElement.querySelector('.content-editor-component-controls');
    if (existing) {
      existing.remove();
    }
    
    const componentSlug = componentElement.dataset.componentSlug;
    if (!componentSlug) return;
    
    // Check for component updates
    let hasUpdate = false;
    const storedUpdatedAt = componentElement.getAttribute('data-component-updated-at');
    
    try {
      const component = await window.ContentEditorComponentRegistry.get(componentSlug);
      const currentUpdatedAt = component.updated_at || '';
      if (!storedUpdatedAt || (currentUpdatedAt && currentUpdatedAt > storedUpdatedAt)) {
        hasUpdate = true;
      }
    } catch (error) {
      console.warn('ContentEditor: Could not check for component updates', error);
    }
    
    const controls = document.createElement('div');
    controls.className = 'content-editor-component-controls';
    
    let controlsHTML = '';
    
    // Add update button if there's an update available
    if (hasUpdate) {
      controlsHTML += `
        <button type="button" class="component-control-btn component-update-btn" title="Update Available - Click to update to latest version">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"></path>
          </svg>
        </button>
      `;
    }
    
    controlsHTML += `
      <button type="button" class="component-control-btn component-edit-btn" title="Edit Component">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
          <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
        </svg>
      </button>
      <button type="button" class="component-control-btn component-delete-btn" title="Delete Component">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="3 6 5 6 21 6"></polyline>
          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
        </svg>
      </button>
    `;
    
    controls.innerHTML = controlsHTML;
    
    componentElement.style.position = 'relative';
    componentElement.appendChild(controls);
    
    // Update button
    const updateBtn = controls.querySelector('.component-update-btn');
    if (updateBtn) {
      updateBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        if (!confirm('Update this component to the latest version?')) {
          return;
        }
        
        try {
          const component = await window.ContentEditorComponentRegistry.get(componentSlug);
          
          const editor = this.getActiveEditor();
          if (!editor) return;
          
          // Get entry data for templating
          const entryData = this.getEntryDataForComponent(editor);
          
          // Create updated component element
          const updatedElement = this.createComponentElement(component, entryData);
          
          // Replace the old component
          componentElement.parentNode.replaceChild(updatedElement, componentElement);
          
          // Sync and save history
          if (window.ContentEditorSync) {
            const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
            if (hiddenInput) {
              window.ContentEditorSync.toHidden(editor, hiddenInput);
            }
          }
          if (window.ContentEditorHistory) {
            window.ContentEditorHistory.saveState();
          }
        } catch (error) {
          console.error('ContentEditor: Error updating component', error);
          alert('Failed to update component. Please try again.');
        }
      });
    }
    
    // Edit button
    controls.querySelector('.component-edit-btn').addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.insertComponent(componentElement);
    });
    
    // Delete button
    controls.querySelector('.component-delete-btn').addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (confirm('Delete this component?')) {
        componentElement.remove();
        const editor = this.getActiveEditor();
        if (editor && window.ContentEditorSync) {
          const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
          if (hiddenInput) {
            window.ContentEditorSync.toHidden(editor, hiddenInput);
          }
        }
        if (window.ContentEditorHistory) {
          window.ContentEditorHistory.saveState();
        }
      }
    });
  },

  // Create media embed dialog
  createMediaEmbedDialog(type, config = {}) {
    const editor = this.getActiveEditor();
    if (!editor) {
      console.error('ContentEditor: Editor not found');
      return;
    }
    const wrapper = editor.closest('.content-editor-wrapper');
    const metadataEndpoint = wrapper?.dataset.linkMetadata || '/fieldtypes/ContentEditor/link-metadata.php';
    const initialData = config.initialData || {};
    const dialogFields = (config.fields || []).map(field => {
      const fieldCopy = { ...field };
      if (Object.prototype.hasOwnProperty.call(initialData, field.name)) {
        if (fieldCopy.type === 'checkbox') {
          fieldCopy.checked = Boolean(initialData[field.name]);
        } else {
          fieldCopy.value = initialData[field.name];
        }
      }
      return fieldCopy;
    });
    const submitLabel = config.submitLabel || (config.onSubmit ? 'Update' : 'Insert');
    
    // Save selection
    window.ContentEditorSelection.save();
    
    // Store reference to this for use in closures
    const self = this;
    
    // Create dialog
    const dialog = document.createElement('div');
    dialog.className = 'content-editor-dialog';
    dialog.innerHTML = `
      <div class="content-editor-dialog-overlay"></div>
      <div class="content-editor-dialog-content">
        <div class="content-editor-dialog-header">
          <h3>${config.title}</h3>
          <button type="button" class="content-editor-dialog-close" aria-label="Close">×</button>
        </div>
        <div class="content-editor-dialog-body">
          <form class="content-editor-embed-form">
            ${dialogFields.map(field => self.renderEmbedField(field)).join('')}
          </form>
        </div>
        <div class="content-editor-dialog-footer">
          <button type="button" class="content-editor-dialog-btn content-editor-dialog-cancel">Cancel</button>
          <button type="button" class="content-editor-dialog-btn content-editor-dialog-submit">${submitLabel}</button>
        </div>
      </div>
    `;
    
    document.body.appendChild(dialog);
    
    // Handle form submission
    const form = dialog.querySelector('.content-editor-embed-form');
    const submitBtn = dialog.querySelector('.content-editor-dialog-submit');
    const cancelBtn = dialog.querySelector('.content-editor-dialog-cancel');
    const closeBtn = dialog.querySelector('.content-editor-dialog-close');
    const overlay = dialog.querySelector('.content-editor-dialog-overlay');
    
    const closeDialog = () => {
      document.body.removeChild(dialog);
      window.ContentEditorSelection.clear();
    };
    
    submitBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      // Validate required fields
      const requiredFields = dialogFields.filter(f => f.required);
      let isValid = true;
      requiredFields.forEach(field => {
        const input = form.querySelector(`[name="${field.name}"]`);
        if (input && !input.value.trim()) {
          isValid = false;
          input.style.borderColor = '#ef4444';
          setTimeout(() => {
            input.style.borderColor = '';
          }, 2000);
        }
      });
      
      if (!isValid) {
        return;
      }
      
      const formData = new FormData(form);
      const data = {};
      dialogFields.forEach(field => {
        if (field.type === 'checkbox') {
          const checkbox = form.querySelector(`[name="${field.name}"]`);
          data[field.name] = checkbox ? checkbox.checked : false;
        } else {
          data[field.name] = formData.get(field.name) || '';
        }
      });
      
      if (config.onSubmit) {
        config.onSubmit(data);
      } else if (window.ContentEditorCommands && window.ContentEditorCommands.insertMediaEmbed) {
        window.ContentEditorCommands.insertMediaEmbed(type, data);
      } else {
        console.error('ContentEditorCommands.insertMediaEmbed not found');
      }

      closeDialog();
    });
    
    cancelBtn.addEventListener('click', closeDialog);
    closeBtn.addEventListener('click', closeDialog);
    overlay.addEventListener('click', closeDialog);
    
    // Initialize metadata helper for link previews
    if (type === 'link-preview') {
      this.initLinkPreviewMetadata(form, {
        endpoint: metadataEndpoint,
        initialData,
        fields: dialogFields
      });
    }
    
    // Handle conditional fields (show/hide based on other field values)
    const conditionalFields = form.querySelectorAll('.content-editor-conditional-field');
    const updateConditionalFields = () => {
      conditionalFields.forEach(conditionalField => {
        const conditionField = conditionalField.dataset.conditionField;
        const conditionValue = conditionalField.dataset.conditionValue;
        if (conditionField && conditionValue) {
          const triggerField = form.querySelector(`[name="${conditionField}"]`);
          if (triggerField) {
            const triggerValue = triggerField.value || triggerField.checked;
            const shouldShow = triggerValue === conditionValue;
            conditionalField.style.display = shouldShow ? '' : 'none';
            // Also handle required attribute
            const requiredInput = conditionalField.querySelector('input[required], select[required], textarea[required]');
            if (requiredInput) {
              if (shouldShow && dialogFields.find(f => f.name === requiredInput.name && f.required)) {
                requiredInput.setAttribute('required', 'required');
              } else {
                requiredInput.removeAttribute('required');
              }
            }
          }
        }
      });
    };
    
    // Update on any field change
    form.querySelectorAll('input, select').forEach(input => {
      input.addEventListener('change', updateConditionalFields);
      input.addEventListener('input', updateConditionalFields);
    });
    
    // Initial update
    updateConditionalFields();
    
    // Focus first visible input
    const firstInput = form.querySelector('input:not([type="hidden"]), textarea, select');
    if (firstInput) {
      setTimeout(() => firstInput.focus(), 100);
    }
  },

  // Render embed form field
  renderEmbedField(field) {
    // Build condition data attribute if field has condition
    const conditionAttr = field.condition ? ` data-condition-field="${field.condition.field}" data-condition-value="${field.condition.value}"` : '';
    const fieldWrapperClass = field.condition ? 'content-editor-form-field content-editor-conditional-field' : 'content-editor-form-field';
    const fieldWrapperStyle = field.condition ? 'display: none;' : '';
    
    if (field.type === 'select') {
      return `
        <div class="${fieldWrapperClass}"${conditionAttr} style="${fieldWrapperStyle}">
          <label>${field.label}${field.required ? ' <span class="required">*</span>' : ''}</label>
          <select name="${field.name}" class="content-editor-input" ${field.required ? 'required' : ''}>
            ${field.options.map(opt => `<option value="${opt.value}" ${opt.value === field.value ? 'selected' : ''}>${opt.label}</option>`).join('')}
          </select>
        </div>
      `;
    } else if (field.type === 'checkbox') {
      return `
        <div class="${fieldWrapperClass} content-editor-form-field-checkbox"${conditionAttr} style="${fieldWrapperStyle}">
          <label>
            <input type="checkbox" name="${field.name}" ${field.checked ? 'checked' : ''}>
            <span>${field.label}</span>
          </label>
        </div>
      `;
    } else if (field.type === 'hidden') {
      return `<input type="hidden" name="${field.name}" value="${field.value || ''}">`;
    } else if (field.type === 'textarea') {
      return `
        <div class="${fieldWrapperClass}"${conditionAttr} style="${fieldWrapperStyle}">
          <label>${field.label}${field.required ? ' <span class="required">*</span>' : ''}</label>
          <textarea name="${field.name}" class="content-editor-input" placeholder="${field.placeholder || ''}" ${field.required ? 'required' : ''} rows="4">${field.value || ''}</textarea>
        </div>
      `;
    } else {
      return `
        <div class="${fieldWrapperClass}"${conditionAttr} style="${fieldWrapperStyle}">
          <label>${field.label}${field.required ? ' <span class="required">*</span>' : ''}</label>
          <input type="${field.type || 'text'}" name="${field.name}" class="content-editor-input" placeholder="${field.placeholder || ''}" value="${field.value || ''}" ${field.required ? 'required' : ''} ${field.min !== undefined ? `min="${field.min}"` : ''} ${field.max !== undefined ? `max="${field.max}"` : ''}>
        </div>
      `;
    }
  },

  initLinkPreviewMetadata(form, options = {}) {
    const commands = this;
    const urlInput = form.querySelector('input[name="url"]');
    if (!urlInput) return;
    const titleInput = form.querySelector('input[name="title"]');
    const descriptionInput = form.querySelector('textarea[name="description"]');
    const imageInput = form.querySelector('input[name="image"]');
    const siteInput = form.querySelector('input[name="site_name"]');
    const urlField = urlInput.closest('.content-editor-form-field');
    if (!urlField) return;
    
    const status = document.createElement('div');
    status.className = 'content-editor-link-meta-status';
    urlField.appendChild(status);
    
    const actions = document.createElement('div');
    actions.className = 'content-editor-link-meta-actions';
    const fetchBtn = document.createElement('button');
    fetchBtn.type = 'button';
    fetchBtn.textContent = 'Fetch Metadata';
    actions.appendChild(fetchBtn);
    urlField.appendChild(actions);
    
    const endpoint = options.endpoint || '/fieldtypes/ContentEditor/link-metadata.php';
    let debounceTimer = null;
    let lastFetched = '';
    
    const updateStatus = (message, type = 'info') => {
      status.textContent = message;
      status.dataset.status = type;
    };
    
    const applyMetadata = (metadata, force = false) => {
      if (!metadata) return;
      if (titleInput && (force || !titleInput.value.trim())) {
        titleInput.value = metadata.title || '';
      }
      if (descriptionInput && (force || !descriptionInput.value.trim())) {
        descriptionInput.value = metadata.description || '';
      }
      if (imageInput && (force || !imageInput.value.trim())) {
        imageInput.value = metadata.image || '';
      }
      if (siteInput && (force || !siteInput.value.trim())) {
        siteInput.value = metadata.site_name || commands.extractHostname(metadata.url || urlInput.value) || '';
      }
    };
    
    const fetchMetadata = (force = false) => {
      const originalValue = urlInput.value.trim();
      if (!originalValue) {
        updateStatus('Enter a URL to fetch metadata', 'info');
        return;
      }
      if (!commands.isLikelyExternalUrl(originalValue)) {
        updateStatus('Please enter a valid URL (http/https)', 'warning');
        return;
      }
      const normalizedUrl = commands.normalizeExternalUrl(originalValue);
      urlInput.value = normalizedUrl;
      if (!force && normalizedUrl === lastFetched) {
        return;
      }
      lastFetched = normalizedUrl;
      updateStatus('Fetching metadata…', 'loading');
      
      fetch(`${endpoint}?url=${encodeURIComponent(normalizedUrl)}`)
        .then(res => res.json())
        .then(result => {
          if (result && result.success && result.data) {
            applyMetadata(result.data, force);
            updateStatus('Metadata loaded', 'success');
          } else {
            updateStatus('No metadata found', 'warning');
          }
        })
        .catch(() => {
          updateStatus('Failed to fetch metadata', 'error');
        });
    };
    
    fetchBtn.addEventListener('click', (e) => {
      e.preventDefault();
      fetchMetadata(true);
    });
    
    if (!options.initialData || Object.keys(options.initialData).length === 0) {
      urlInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
          if (urlInput.value.trim().length >= 8 && commands.isLikelyExternalUrl(urlInput.value.trim())) {
            fetchMetadata(false);
          }
        }, 900);
      });
    }
  },

  // Insert media embed element
  insertMediaEmbed(type, data) {
    
    const editor = this.getActiveEditor();
    if (!editor) {
      console.error('ContentEditor: Editor not found in insertMediaEmbed');
      return;
    }
    
    const insertWithPayload = (payload) => {
      // Restore selection right before insertion
      const editor = this.getActiveEditor();
      if (editor) {
        window.ContentEditorSelection.restore(editor);
      } else {
        window.ContentEditorSelection.restore();
      }
      
      const selection = window.getSelection();
      let range = null;
      
      if (selection.rangeCount > 0) {
        range = selection.getRangeAt(0);
      } else {
        const block = window.ContentEditorCommands.getCurrentBlock();
        if (block) {
          range = document.createRange();
          range.selectNodeContents(block);
          range.collapse(false);
        } else {
          const newP = document.createElement('p');
          newP.innerHTML = '<br>';
          editor.appendChild(newP);
          range = document.createRange();
          range.selectNodeContents(newP);
          range.collapse(false);
        }
      }
      
      if (!range) {
        console.error('ContentEditor: Failed to create range');
        return;
      }
      
      let embedElement = null;
      
      const finalData = { ...payload };
      if (finalData && typeof finalData.url === 'string' && ['video', 'audio', 'link-preview', 'iframe', 'custom'].includes(type)) {
        if (type === 'audio' || type === 'custom') {
          finalData.url = finalData.url.trim();
        } else {
          finalData.url = this.normalizeExternalUrl(finalData.url);
        }
      }
      
      const commands = window.ContentEditorCommands;
      
      switch (type) {
        case 'video':
          embedElement = commands.createVideoEmbed(finalData);
          break;
        case 'audio':
          embedElement = commands.createAudioEmbed(finalData);
          break;
        case 'maps':
          embedElement = commands.createMapEmbed(finalData);
          break;
        case 'link-preview':
          embedElement = commands.createLinkPreviewEmbed(finalData);
          break;
        case 'iframe':
          embedElement = commands.createIframeEmbed(finalData);
          break;
        case 'custom':
          embedElement = commands.createCustomEmbed(finalData);
          break;
      }
      
      if (!embedElement) {
        console.error('ContentEditor: Failed to create embed element');
        return;
      }
      
      this.setupEmbedElement(embedElement, type, finalData);
    
    // Insert embed as a block element
    // Embeds should be inserted as block-level elements, not inline
    try {
      // Find the current block that's inside the editor
      let currentBlock = null;
      let container = range.startContainer;
      
      if (container.nodeType === Node.TEXT_NODE) {
        container = container.parentNode;
      }
      
      // Walk up to find a block element that's inside the editor
      // IMPORTANT: Stop at editor, don't go beyond it - check contains() first
      if (!editor.contains(container)) {
        // Container is not in editor, start from editor
        container = editor;
      }
      
      while (container && container !== editor) {
        // Safety check: ensure we're still inside editor
        if (!editor.contains(container)) {
          break;
        }
        if (container.nodeType === Node.ELEMENT_NODE) {
          const tagName = container.tagName.toLowerCase();
          // Only consider blocks that are valid content blocks
          if (tagName === 'p' || tagName === 'div' || tagName.startsWith('h') || 
              tagName === 'ul' || tagName === 'ol' || tagName === 'blockquote' ||
              tagName === 'li') {
            currentBlock = container;
            // If it's a direct child of editor, use it; otherwise continue to find one closer
            if (container.parentNode === editor) {
              break;
            }
          }
        }
        container = container.parentNode;
      }
      
      // Always ensure we're inserting into the editor, never into document/HTML
      // Safety check: ensure editor is a valid element node
      if (!editor || editor.nodeType !== Node.ELEMENT_NODE) {
        console.error('ContentEditor: Editor is not a valid element!');
        return;
      }
      
      // Find the last child of editor to insert after, or append
      let insertAfter = null;
      
      if (currentBlock && editor.contains(currentBlock)) {
        // Find the top-level block (direct child of editor)
        let topBlock = currentBlock;
        while (topBlock && topBlock.parentNode !== editor) {
          topBlock = topBlock.parentNode;
          // Safety: ensure we don't go beyond editor
          if (!editor.contains(topBlock)) {
            topBlock = null;
            break;
          }
        }
        
        if (topBlock && topBlock.parentNode === editor) {
          insertAfter = topBlock;
        }
      }
      
      // Insert into editor
      if (insertAfter && insertAfter.nextSibling) {
        editor.insertBefore(embedElement, insertAfter.nextSibling);
      } else if (insertAfter) {
        editor.appendChild(embedElement);
      } else {
        // No block found, append to editor
        editor.appendChild(embedElement);
      }
      
      // Verify embed was inserted and is inside editor
      if (!embedElement.parentNode) {
        console.error('ContentEditor: Embed was not inserted - no parent node!');
        // Fallback: append directly to editor
        editor.appendChild(embedElement);
      } else if (!editor.contains(embedElement)) {
        console.error('ContentEditor: Embed inserted outside editor! Parent:', embedElement.parentNode.nodeName, 
                     'Moving to editor...');
        // Remove from wrong parent and add to editor
        embedElement.parentNode.removeChild(embedElement);
        editor.appendChild(embedElement);
      }
      
      // Move cursor after embed
      const newRange = document.createRange();
      newRange.setStartAfter(embedElement);
      newRange.collapse(true);
      selection.removeAllRanges();
      selection.addRange(newRange);
      
      // Create new paragraph after embed if needed
      const nextSibling = embedElement.nextSibling;
      if (!nextSibling || (nextSibling.nodeType === Node.TEXT_NODE && !nextSibling.textContent.trim())) {
        const newP = document.createElement('p');
        newP.innerHTML = '<br>';
        if (nextSibling) {
          embedElement.parentNode.insertBefore(newP, nextSibling);
        } else {
          embedElement.parentNode.appendChild(newP);
        }
        const paraRange = document.createRange();
        paraRange.selectNodeContents(newP);
        paraRange.collapse(true);
        selection.removeAllRanges();
        selection.addRange(paraRange);
      }
    } catch (e) {
      console.error('Error inserting embed:', e);
      const newP = document.createElement('p');
      newP.appendChild(embedElement);
      editor.appendChild(newP);
    }
    
    // Sync
    if (window.ContentEditorSync) {
      const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
      if (hiddenInput) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
    }
    
    // Save history
    if (window.ContentEditorHistory) {
      window.ContentEditorHistory.saveState();
    }
    
    window.ContentEditorSelection.clear();
    };
    
    if (type === 'link-preview') {
      const needsMetadata = !data.title || !data.description || !data.image || !data.site_name;
      const endpoint = this.getMetadataEndpoint();
      if (needsMetadata && endpoint) {
        this.fetchLinkMetadata(endpoint, data.url)
          .then(meta => {
            if (meta) {
              insertWithPayload({ ...data, ...meta });
            } else {
              insertWithPayload(data);
            }
          })
          .catch(() => insertWithPayload(data));
        return;
      }
    }
    
    insertWithPayload(data);
  },

  setupEmbedElement(element, type, data = {}) {
    if (!element) return;
    const embedType = type || element.dataset.embedType || element.getAttribute('data-embed-type') || '';
    element.dataset.embedType = embedType;
    try {
      element.dataset.embedConfig = JSON.stringify(data || {});
    } catch (e) {
      element.dataset.embedConfig = '{}';
    }
    element.classList.add('content-editor-embed-with-controls');
    this.addEmbedControls(element);
  },

  addEmbedControls(element) {
    if (!element) return;
    const existingControls = element.querySelector('.content-editor-embed-controls');
    if (existingControls) {
      existingControls.remove();
    }
    const controls = document.createElement('div');
    controls.className = 'content-editor-embed-controls';
    controls.innerHTML = `
      <button type="button" class="embed-control-btn embed-control-edit" title="Edit embed" data-action="edit">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 20h9"></path>
          <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
        </svg>
      </button>
      <button type="button" class="embed-control-btn embed-control-delete" title="Remove embed" data-action="delete">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="3 6 5 6 21 6"></polyline>
          <path d="M19 6l-1 14H6L5 6"></path>
          <path d="M10 11v6"></path>
          <path d="M14 11v6"></path>
          <path d="M9 6V4h6v2"></path>
        </svg>
      </button>
    `;
    const editBtn = controls.querySelector('[data-action="edit"]');
    const deleteBtn = controls.querySelector('[data-action="delete"]');
    editBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.openEmbedEditor(element);
    });
    deleteBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.deleteEmbedElement(element);
    });
    element.appendChild(controls);
  },

  // Add controls to image wrapper
  addImageControls(wrapper) {
    if (!wrapper) return;
    const existingControls = wrapper.querySelector('.content-editor-image-controls');
    if (existingControls) {
      existingControls.remove();
    }
    const existingResizeHandles = wrapper.querySelector('.content-editor-image-resize-handles');
    if (existingResizeHandles) {
      existingResizeHandles.remove();
    }
    
    const controls = document.createElement('div');
    controls.className = 'content-editor-image-controls';
    controls.innerHTML = `
      <button type="button" class="image-control-btn image-control-edit" title="Edit image" data-action="edit">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 20h9"></path>
          <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
        </svg>
      </button>
      <button type="button" class="image-control-btn image-control-resize" title="Resize image" data-action="resize">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 3l-7 7M3 21l7-7M14 3l7 7M10 14l7 7"></path>
        </svg>
      </button>
      <button type="button" class="image-control-btn image-control-lightbox" title="Enable fullscreen preview" data-action="lightbox">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
          <polyline points="8 3 8 8 3 8"></polyline>
          <polyline points="16 21 16 16 21 16"></polyline>
        </svg>
      </button>
      <button type="button" class="image-control-btn image-control-delete" title="Delete image" data-action="delete">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="3 6 5 6 21 6"></polyline>
          <path d="M19 6l-1 14H6L5 6"></path>
          <path d="M10 11v6"></path>
          <path d="M14 11v6"></path>
          <path d="M9 6V4h6v2"></path>
        </svg>
      </button>
    `;
    
    const editBtn = controls.querySelector('[data-action="edit"]');
    const resizeBtn = controls.querySelector('[data-action="resize"]');
    const lightboxBtn = controls.querySelector('[data-action="lightbox"]');
    const deleteBtn = controls.querySelector('[data-action="delete"]');
    
    editBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.openImageEditor(wrapper);
    });
    
    resizeBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.toggleImageResizeHandles(wrapper);
    });
    
    lightboxBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const enable = wrapper.dataset.imageLightbox === 'true' ? false : true;
      this.setImageLightbox(wrapper, enable);
    });
    
    deleteBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.deleteImageElement(wrapper);
    });
    
    wrapper.appendChild(controls);
    
    // Initialize resize handles (hidden by default)
    this.initImageResizeHandles(wrapper);
    this.setImageLightbox(wrapper, wrapper.dataset.imageLightbox === 'true');
  },

  setImageLightbox(wrapper, enable) {
    if (!wrapper) return;
    const img = wrapper.querySelector('img');
    if (!img) return;
    const shouldEnable = !!enable;
    const previousHandler = wrapper._lightboxHandler;
    if (previousHandler) {
      img.removeEventListener('click', previousHandler);
      wrapper._lightboxHandler = null;
    }
    wrapper.dataset.imageLightbox = shouldEnable ? 'true' : 'false';
    if (shouldEnable) {
      const handler = (e) => {
        // Allow selecting when holding modifier? maybe only when plain click
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        e.preventDefault();
        this.openImageLightboxOverlay(wrapper);
      };
      img.addEventListener('click', handler);
      wrapper._lightboxHandler = handler;
      img.style.cursor = 'zoom-in';
    } else {
      img.style.cursor = '';
    }
    const toggleBtn = wrapper.querySelector('.image-control-btn.image-control-lightbox');
    if (toggleBtn) {
      toggleBtn.classList.toggle('is-active', shouldEnable);
      toggleBtn.title = shouldEnable ? 'Disable fullscreen preview' : 'Enable fullscreen preview';
    }
  },
  
  openImageLightboxOverlay(wrapper) {
    if (!wrapper) return;
    const img = wrapper.querySelector('img');
    if (!img) return;
    const url = wrapper.dataset.imageUrl || img.src;
    if (!url) return;
    const alt = wrapper.dataset.imageAlt || img.alt || '';
    
    const overlay = document.createElement('div');
    overlay.className = 'content-editor-image-lightbox';
    overlay.innerHTML = `
      <div class="content-editor-image-lightbox-inner">
        <button type="button" class="content-editor-image-lightbox-close" aria-label="Close">&times;</button>
        <img src="${this.escapeHtml(url)}" alt="${this.escapeHtml(alt)}">
        ${alt ? `<p>${this.escapeHtml(alt)}</p>` : ''}
      </div>
    `;
    
    const closeOverlay = () => {
      document.body.style.overflow = '';
      overlay.remove();
      document.removeEventListener('keydown', keyHandler, true);
    };
    
    const keyHandler = (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        closeOverlay();
      }
    };
    
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        closeOverlay();
      }
    });
    overlay.querySelector('.content-editor-image-lightbox-close').addEventListener('click', (e) => {
      e.preventDefault();
      closeOverlay();
    });
    
    document.body.appendChild(overlay);
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', keyHandler, true);
  },

  // Initialize resize handles for drag-to-resize
  initImageResizeHandles(wrapper) {
    if (!wrapper) return;
    const img = wrapper.querySelector('img');
    if (!img) return;
    
    const handles = document.createElement('div');
    handles.className = 'content-editor-image-resize-handles';
    handles.style.display = 'none';
    
    // Create 8 handles: 4 corners + 4 edges
    const positions = [
      { class: 'resize-handle resize-handle-nw', cursor: 'nw-resize' },
      { class: 'resize-handle resize-handle-n', cursor: 'n-resize' },
      { class: 'resize-handle resize-handle-ne', cursor: 'ne-resize' },
      { class: 'resize-handle resize-handle-e', cursor: 'e-resize' },
      { class: 'resize-handle resize-handle-se', cursor: 'se-resize' },
      { class: 'resize-handle resize-handle-s', cursor: 's-resize' },
      { class: 'resize-handle resize-handle-sw', cursor: 'sw-resize' },
      { class: 'resize-handle resize-handle-w', cursor: 'w-resize' }
    ];
    
    positions.forEach((pos, index) => {
      const handle = document.createElement('div');
      handle.className = pos.class;
      handle.style.cursor = pos.cursor;
      handle.dataset.handleIndex = index;
      handles.appendChild(handle);
      
      // Add drag functionality
      this.setupResizeHandle(wrapper, handle, index);
    });
    
    wrapper.appendChild(handles);
  },

  // Setup drag functionality for a resize handle
  setupResizeHandle(wrapper, handle, handleIndex) {
    let isDragging = false;
    let startX, startY, startWidth, startHeight, startLeft, startTop;
    let aspectRatio = 1;
    const img = wrapper.querySelector('img');
    
    const getAspectRatio = () => {
      if (img.naturalWidth && img.naturalHeight) {
        return img.naturalWidth / img.naturalHeight;
      }
      const rect = img.getBoundingClientRect();
      return rect.width / rect.height;
    };
    
    handle.addEventListener('mousedown', (e) => {
      e.preventDefault();
      e.stopPropagation();
      isDragging = true;
      
      const rect = wrapper.getBoundingClientRect();
      startX = e.clientX;
      startY = e.clientY;
      startWidth = rect.width;
      startHeight = rect.height;
      startLeft = rect.left;
      startTop = rect.top;
      
      aspectRatio = getAspectRatio();
      
      document.addEventListener('mousemove', handleMouseMove);
      document.addEventListener('mouseup', handleMouseUp);
      
      wrapper.style.userSelect = 'none';
      document.body.style.cursor = handle.style.cursor;
    });
    
    const handleMouseMove = (e) => {
      if (!isDragging) return;
      
      const deltaX = e.clientX - startX;
      const deltaY = e.clientY - startY;
      
      let newWidth = startWidth;
      let newHeight = startHeight;
      
      // Determine which edges to resize based on handle position
      const isCorner = handleIndex % 2 === 0; // 0, 2, 4, 6 are corners
      const isNorth = handleIndex <= 2; // 0, 1, 2
      const isSouth = handleIndex >= 4 && handleIndex <= 6; // 4, 5, 6
      const isWest = handleIndex === 0 || handleIndex === 6 || handleIndex === 7; // 0, 6, 7
      const isEast = handleIndex >= 2 && handleIndex <= 4; // 2, 3, 4
      
      if (isCorner) {
        // Corner handles: maintain aspect ratio
        if (isNorth && isWest) {
          // NW corner
          newWidth = startWidth - deltaX;
          newHeight = newWidth / aspectRatio;
        } else if (isNorth && isEast) {
          // NE corner
          newWidth = startWidth + deltaX;
          newHeight = newWidth / aspectRatio;
        } else if (isSouth && isEast) {
          // SE corner
          newWidth = startWidth + deltaX;
          newHeight = newWidth / aspectRatio;
        } else if (isSouth && isWest) {
          // SW corner
          newWidth = startWidth - deltaX;
          newHeight = newWidth / aspectRatio;
        }
      } else {
        // Edge handles: resize one dimension
        if (isNorth) {
          newHeight = startHeight - deltaY;
          newWidth = newHeight * aspectRatio;
        } else if (isSouth) {
          newHeight = startHeight + deltaY;
          newWidth = newHeight * aspectRatio;
        } else if (isWest) {
          newWidth = startWidth - deltaX;
          newHeight = newWidth / aspectRatio;
        } else if (isEast) {
          newWidth = startWidth + deltaX;
          newHeight = newWidth / aspectRatio;
        }
      }
      
      // Apply minimum size constraints
      const minSize = 50;
      if (newWidth < minSize) {
        newWidth = minSize;
        newHeight = newWidth / aspectRatio;
      }
      if (newHeight < minSize) {
        newHeight = minSize;
        newWidth = newHeight * aspectRatio;
      }
      
      // Apply maximum size (100% of container)
      const maxWidth = wrapper.parentElement ? wrapper.parentElement.offsetWidth : window.innerWidth;
      if (newWidth > maxWidth) {
        newWidth = maxWidth;
        newHeight = newWidth / aspectRatio;
      }
      
      // Apply the new size
      img.style.width = newWidth + 'px';
      img.style.height = newHeight + 'px';
      img.style.maxWidth = 'none';
      img.style.maxHeight = 'none';
    };
    
    const handleMouseUp = () => {
      if (isDragging) {
        isDragging = false;
        document.removeEventListener('mousemove', handleMouseMove);
        document.removeEventListener('mouseup', handleMouseUp);
        wrapper.style.userSelect = '';
        document.body.style.cursor = '';
        
        // Sync content
        const editor = this.getActiveEditor();
        if (editor && window.ContentEditorSync) {
          const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
          if (hiddenInput) {
            window.ContentEditorSync.toHidden(editor, hiddenInput);
          }
        }
      }
    };
  },

  // Toggle resize handles visibility
  toggleImageResizeHandles(wrapper) {
    if (!wrapper) return;
    const handles = wrapper.querySelector('.content-editor-image-resize-handles');
    if (!handles) return;
    
    if (handles.style.display === 'none') {
      handles.style.display = 'block';
      wrapper.classList.add('resizing');
      
      // Hide handles when clicking outside
      const hideHandles = (e) => {
        if (!wrapper.contains(e.target) && !handles.contains(e.target)) {
          handles.style.display = 'none';
          wrapper.classList.remove('resizing');
          document.removeEventListener('mousedown', hideHandles);
        }
      };
      
      // Use setTimeout to avoid immediate hiding
      setTimeout(() => {
        document.addEventListener('mousedown', hideHandles);
      }, 100);
    } else {
      handles.style.display = 'none';
      wrapper.classList.remove('resizing');
    }
  },

  // Open image editor dialog
  openImageEditor(wrapper) {
    if (!wrapper) return;
    const img = wrapper.querySelector('img');
    if (!img) return;
    
    const currentUrl = wrapper.dataset.imageUrl || img.src;
    const currentAlt = wrapper.dataset.imageAlt || img.alt || '';
    const currentLightbox = wrapper.dataset.imageLightbox === 'true';
    
    // Create dialog overlay
    const overlay = document.createElement('div');
    overlay.className = 'content-editor-image-dialog-overlay';
    overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; z-index: 12000;';
    
    const dialog = document.createElement('div');
    dialog.className = 'content-editor-image-dialog';
    dialog.style.cssText = 'background: white; border-radius: 8px; padding: 24px; max-width: 500px; width: 90%; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);';
    
    dialog.innerHTML = `
      <h3 style="margin: 0 0 16px 0; font-size: 18px; font-weight: 600; color: #1f2937;">Edit Image</h3>
      <div style="margin-bottom: 16px;">
        <label style="display: block; margin-bottom: 6px; font-size: 14px; font-weight: 500; color: #374151;">Image URL</label>
        <input type="text" id="image-edit-url" value="${this.escapeHtml(currentUrl)}" placeholder="https://..." style="
          width: 100%;
          padding: 10px 12px;
          border: 1px solid #e5e7eb;
          border-radius: 6px;
          font-size: 14px;
          box-sizing: border-box;
        " autofocus>
      </div>
      <div style="margin-bottom: 16px;">
        <label style="display: block; margin-bottom: 6px; font-size: 14px; font-weight: 500; color: #374151;">Alt Text</label>
        <input type="text" id="image-edit-alt" value="${this.escapeHtml(currentAlt)}" placeholder="Description of image" style="
          width: 100%;
          padding: 10px 12px;
          border: 1px solid #e5e7eb;
          border-radius: 6px;
          font-size: 14px;
          box-sizing: border-box;
        ">
      </div>
      <div style="margin-bottom: 16px;">
        <label style="display:flex; align-items:center; gap:8px; font-size:14px; color:#374151;">
          <input type="checkbox" id="image-edit-lightbox" ${currentLightbox ? 'checked' : ''}>
          Enable click-to-zoom
        </label>
      </div>
      <div style="display: flex; gap: 8px; justify-content: flex-end;">
        <button type="button" class="image-dialog-cancel" style="
          padding: 8px 16px;
          border: 1px solid #e5e7eb;
          border-radius: 6px;
          background: white;
          cursor: pointer;
          font-size: 14px;
          color: #374151;
        ">Cancel</button>
        <button type="button" class="image-dialog-apply" style="
          padding: 8px 16px;
          border: none;
          border-radius: 6px;
          background: #3b82f6;
          cursor: pointer;
          font-size: 14px;
          color: white;
          font-weight: 500;
        ">Apply</button>
      </div>
    `;
    
    overlay.appendChild(dialog);
    document.body.appendChild(overlay);
    
    const urlInput = dialog.querySelector('#image-edit-url');
    const altInput = dialog.querySelector('#image-edit-alt');
    const lightboxInput = dialog.querySelector('#image-edit-lightbox');
    const cancelBtn = dialog.querySelector('.image-dialog-cancel');
    const applyBtn = dialog.querySelector('.image-dialog-apply');
    
    const closeDialog = () => {
      document.body.removeChild(overlay);
    };
    
    cancelBtn.addEventListener('click', closeDialog);
    applyBtn.addEventListener('click', () => {
      const newUrl = urlInput.value.trim();
      const newAlt = altInput.value.trim();
      const enableLightbox = lightboxInput.checked;
      
      if (newUrl) {
        img.src = newUrl;
        img.alt = newAlt;
        wrapper.dataset.imageUrl = newUrl;
        wrapper.dataset.imageAlt = newAlt;
        this.setImageLightbox(wrapper, enableLightbox);
        
        // Sync content
        const editor = this.getActiveEditor();
        if (editor && window.ContentEditorSync) {
          const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
          if (hiddenInput) {
            window.ContentEditorSync.toHidden(editor, hiddenInput);
          }
        }
      }
      
      closeDialog();
    });
    
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        closeDialog();
      }
    });
    
    urlInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        applyBtn.click();
      } else if (e.key === 'Escape') {
        closeDialog();
      }
    });
  },

  // Delete image element
  deleteImageElement(wrapper) {
    if (!wrapper) return;
    
    // Remove resize handles if visible
    const handles = wrapper.querySelector('.content-editor-image-resize-handles');
    if (handles && handles.style.display !== 'none') {
      this.toggleImageResizeHandles(wrapper);
    }
    
    // Remove the wrapper (which contains the image and controls)
    wrapper.remove();
    
    // Sync content
    const editor = this.getActiveEditor();
    if (editor && window.ContentEditorSync) {
      const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
      if (hiddenInput) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      }
    }
    
    // Save history
    if (window.ContentEditorHistory) {
      setTimeout(() => {
        window.ContentEditorHistory.saveState();
      }, 50);
    }
  },

  // Helper to escape HTML
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  },

  // Helper to convert RGB/RGBA color to hex
  rgbToHex(color) {
    if (!color) return '#ffffff';
    
    // If already hex, return as is
    if (color.startsWith('#')) {
      return color;
    }
    
    // If rgb or rgba, convert to hex
    const rgbMatch = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
    if (rgbMatch) {
      const r = parseInt(rgbMatch[1]).toString(16).padStart(2, '0');
      const g = parseInt(rgbMatch[2]).toString(16).padStart(2, '0');
      const b = parseInt(rgbMatch[3]).toString(16).padStart(2, '0');
      return `#${r}${g}${b}`;
    }
    
    // Fallback
    return '#ffffff';
  },

  openEmbedEditor(element) {
    if (!element) return;
    const type = element.dataset.embedType;
    if (!type) return;
    let data = {};
    if (element.dataset.embedConfig) {
      try {
        data = JSON.parse(element.dataset.embedConfig);
      } catch (e) {
        data = {};
      }
    }
    if (!Object.keys(data).length) {
      data = this.extractEmbedData(element, type);
    }
    switch (type) {
      case 'video':
        this.insertVideo(data, element);
        break;
      case 'audio':
        this.insertAudio(data, element);
        break;
      case 'link-preview':
        this.insertLinkPreview(data, element);
        break;
      case 'iframe':
        this.insertIframe(data, element);
        break;
      case 'custom':
        this.insertCustomEmbed(data, element);
        break;
      default:
        console.warn('ContentEditor: Unknown embed type for editing', type);
    }
  },

  updateEmbedElement(type, data, existingElement) {
    if (!existingElement) return;
    const commands = {
      'video': 'createVideoEmbed',
      'audio': 'createAudioEmbed',
      'maps': 'createMapEmbed',
      'link-preview': 'createLinkPreviewEmbed',
      'iframe': 'createIframeEmbed',
      'custom': 'createCustomEmbed'
    };
    const method = commands[type];
    if (!method || typeof this[method] !== 'function') {
      console.error('ContentEditor: Unknown embed type for update', type);
      return;
    }
    const newElement = this[method](data);
    if (!newElement) {
      console.error('ContentEditor: Failed to rebuild embed element');
      return;
    }
    this.setupEmbedElement(newElement, type, data);
    existingElement.parentNode.insertBefore(newElement, existingElement);
    existingElement.remove();
    
    const editor = this.getActiveEditor();
    if (editor) {
      const selection = window.getSelection();
      const range = document.createRange();
      range.setStartAfter(newElement);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);
      
      if (window.ContentEditorSync) {
        const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
        if (hiddenInput) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
      }
      if (window.ContentEditorHistory) {
        window.ContentEditorHistory.saveState();
      }
    }
  },

  deleteEmbedElement(element) {
    if (!element) return;
    const editor = this.getActiveEditor();
    const parent = element.parentNode;
    const nextSibling = element.nextSibling;
    element.remove();
    if (editor && parent) {
      const placeholder = document.createElement('p');
      placeholder.innerHTML = '<br>';
      if (nextSibling) {
        parent.insertBefore(placeholder, nextSibling);
      } else {
        parent.appendChild(placeholder);
      }
      const selection = window.getSelection();
      const range = document.createRange();
      range.selectNodeContents(placeholder);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);
      
      if (window.ContentEditorSync) {
        const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
        if (hiddenInput) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
      }
      if (window.ContentEditorHistory) {
        window.ContentEditorHistory.saveState();
      }
    }
  },

  enhanceExistingEmbeds(editor) {
    if (!editor) return;
    const embeds = editor.querySelectorAll('.content-editor-embed');
    embeds.forEach(embed => {
      const type = embed.dataset.embedType || embed.getAttribute('data-embed-type');
      if (!type) return;
      let data = {};
      if (embed.dataset.embedConfig) {
        try {
          data = JSON.parse(embed.dataset.embedConfig);
        } catch (e) {
          data = {};
        }
      } else {
        data = this.extractEmbedData(embed, type);
      }
      this.setupEmbedElement(embed, type, data);
    });
  },

  enhanceExistingImages(editor) {
    if (!editor) return;
    const images = editor.querySelectorAll('img.content-editor-image, img:not(.content-editor-image)');
    images.forEach(img => {
      // Check if image is already wrapped
      if (img.parentElement && img.parentElement.classList.contains('content-editor-image-wrapper')) {
        // Already wrapped, just add controls
        const wrapper = img.parentElement;
        if (!wrapper.dataset.imageLightbox) {
          wrapper.dataset.imageLightbox = 'false';
        }
        if (!wrapper.querySelector('.content-editor-image-controls')) {
          this.addImageControls(wrapper);
        } else {
          this.setImageLightbox(wrapper, wrapper.dataset.imageLightbox === 'true');
        }
      } else {
        // Not wrapped, wrap it and add controls
        const wrapper = document.createElement('div');
        wrapper.className = 'content-editor-image-wrapper content-editor-image-with-controls';
        wrapper.style.position = 'relative';
        wrapper.style.display = 'inline-block';
        wrapper.style.maxWidth = '100%';
        
        // Store image data
        wrapper.dataset.imageUrl = img.src;
        wrapper.dataset.imageAlt = img.alt || '';
        wrapper.dataset.imageLightbox = wrapper.dataset.imageLightbox || 'false';
        
        // Wrap the image
        img.parentNode.insertBefore(wrapper, img);
        wrapper.appendChild(img);
        
        // Ensure image has correct class
        img.className = 'content-editor-image';
        img.style.maxWidth = '100%';
        img.style.height = 'auto';
        img.style.display = 'block';
        
        // Add controls
        this.addImageControls(wrapper);
      }
    });
  },

    enhanceExistingGrids(...args) {
    return window.ContentEditorGridCommands.enhanceExistingGrids(...args);
  },

  // Enhance existing components in editor
  async enhanceExistingComponents(editor) {
    if (!editor) return;
    
    // Find components by class OR by data attribute (more robust)
    const componentsByClass = editor.querySelectorAll('.content-editor-component');
    const componentsByData = editor.querySelectorAll('[data-component-slug]');
    
    // Combine and deduplicate
    const componentSet = new Set();
    componentsByClass.forEach(c => componentSet.add(c));
    componentsByData.forEach(c => componentSet.add(c));
    const components = Array.from(componentSet);
    
    
    // If no components found, check the raw HTML and try to restore them
    if (components.length === 0) {
      const html = editor.innerHTML;
      const hasComponentSlug = html.includes('data-component-slug');
      const hasComponentClass = html.includes('content-editor-component');
      
      if (hasComponentSlug || hasComponentClass) {
        
        // Try to restore escaped components
        // The HTML contains escaped component tags like &lt;div class="content-editor-component"...
        // We need to decode and restore them as actual DOM elements
        
        // Create a temporary container to parse the escaped HTML
        const tempContainer = document.createElement('div');
        tempContainer.innerHTML = html;
        
        // Find all text nodes that contain escaped component HTML
        const walker = document.createTreeWalker(
          tempContainer,
          NodeFilter.SHOW_TEXT,
          null
        );
        
        const textNodesToReplace = [];
        let textNode;
        while ((textNode = walker.nextNode())) {
          if (textNode.textContent.includes('content-editor-component') && 
              textNode.textContent.includes('&lt;div')) {
            textNodesToReplace.push(textNode);
          }
        }
        
        // Replace escaped HTML in text nodes
        textNodesToReplace.forEach(textNode => {
          const parent = textNode.parentNode;
          if (!parent) return;
          
          // Decode HTML entities and parse as HTML
          const decoded = textNode.textContent
            .replace(/&amp;lt;/g, '<')
            .replace(/&amp;gt;/g, '>')
            .replace(/&amp;amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>');
          
          // Create a temporary div to parse the decoded HTML
          const parseDiv = document.createElement('div');
          parseDiv.innerHTML = decoded;
          
          // Replace the text node with the parsed elements
          while (parseDiv.firstChild) {
            parent.insertBefore(parseDiv.firstChild, textNode);
          }
          parent.removeChild(textNode);
        });
        
        // Now update the editor with the restored HTML
        editor.innerHTML = tempContainer.innerHTML;
        
        // Re-run component detection
        const restoredComponents = editor.querySelectorAll('.content-editor-component, [data-component-slug]');
        
        if (restoredComponents.length > 0) {
          // Process restored components
          for (const component of restoredComponents) {
            const componentSlug = component.dataset.componentSlug;
            if (componentSlug) {
              const rawTemplate = component.getAttribute('data-component-template');
              if (rawTemplate) {
                await this.processComponentTemplate(component, rawTemplate);
              } else {
                // Fetch from API
                try {
                  const comp = await window.ContentEditorComponentRegistry.get(componentSlug);
                  component.setAttribute('data-component-template', comp.template || '');
                  component.setAttribute('data-component-style', comp.style || '');
                  component.setAttribute('data-component-script', comp.script || '');
                  component.setAttribute('data-component-auto-scope', comp.auto_scope_css !== false ? 'true' : 'false');
                  component.setAttribute('data-component-execute-js', comp.execute_js !== false ? 'true' : 'false');
                  component.setAttribute('data-component-updated-at', comp.updated_at || '');
                  await this.processComponentTemplate(component, comp.template || '');
                } catch (error) {
                  console.warn('ContentEditor: Could not reload component', componentSlug, error);
                }
              }
              
              // Add controls after processing
              await this.addComponentControls(component);
            }
          }
          
          return; // Exit early since we've processed restored components
        }
      }
    }
    
    for (const component of components) {
      const componentSlug = component.dataset.componentSlug;
      
      // Add controls if not present
      if (!component.querySelector('.content-editor-component-controls')) {
        await this.addComponentControls(component);
      }
      
      // Check if component has raw template stored (from data attribute)
      // Use getAttribute to get raw value, not dataset which auto-decodes
      const rawTemplate = component.getAttribute('data-component-template');
      if (rawTemplate) {
        // Component has template stored - try to process it
        await this.processComponentTemplate(component, rawTemplate);
        // Re-add controls after processing (processComponentTemplate replaces innerHTML)
        await this.addComponentControls(component);
        continue;
      }
      
      // Otherwise, fetch from API and process
      if (componentSlug) {
        try {
          const comp = await window.ContentEditorComponentRegistry.get(componentSlug);
          
          component.setAttribute('data-component-template', comp.template || '');
          component.setAttribute('data-component-style', comp.style || '');
          component.setAttribute('data-component-script', comp.script || '');
          component.setAttribute('data-component-auto-scope', comp.auto_scope_css !== false ? 'true' : 'false');
          component.setAttribute('data-component-execute-js', comp.execute_js !== false ? 'true' : 'false');
          component.setAttribute('data-component-updated-at', comp.updated_at || '');
          
          await this.processComponentTemplate(component, comp.template || '');
          await this.addComponentControls(component);
        } catch (error) {
          console.warn('ContentEditor: Could not reload component', componentSlug, error);
        }
      }
    }
  },

  // Process component template with templating engine
  async processComponentTemplate(componentElement, template) {
    
    // Decode HTML entities
    const templateText = this.unescapeHtml(template);
    
    // Check if template has templating syntax
    const hasTemplatingSyntax = /\{for\s+each|\{if\s+|\{endfor|\{endif|\{else/i.test(templateText);
    
    if (!hasTemplatingSyntax) {
      // Simple template - just render with variable replacement
      const editor = this.getActiveEditor();
      const entryData = this.getEntryDataForComponent(editor);
      let rendered = templateText.replace(/\{([^}]+)\}/g, (match, varName) => {
        varName = varName.trim();
        return entryData[varName] !== undefined ? entryData[varName] : '';
      });
      componentElement.innerHTML = rendered;
      return;
    }
    
    // Extract collection names from template
    const collectionMatches = templateText.matchAll(/\{for\s+(?:each\s+)?[^\s]+\s+in\s+['"]([^'"]+)['"]\}/gi);
    const collectionNames = [...new Set(Array.from(collectionMatches, m => m[1]))];
    
    // Fetch collection data
    const templateData = {};
    for (const collectionName of collectionNames) {
      try {
        const response = await fetch(`/api/content.php?action=list_entries&collection=${encodeURIComponent(collectionName)}`);
        const result = await response.json();
        
        // API returns {status: 'ok', data: {entries: [...]}}
        let entries = [];
        if (result.status === 'ok' && result.data) {
          if (Array.isArray(result.data)) {
            // Direct array
            entries = result.data;
          } else if (result.data.entries && Array.isArray(result.data.entries)) {
            // Nested in entries property
            entries = result.data.entries;
          }
        }
        
        templateData[collectionName] = entries;
        
        if (entries.length > 0) {
        }
      } catch (error) {
        console.warn(`ContentEditor: Could not fetch collection data for ${collectionName}`, error);
        templateData[collectionName] = [];
      }
    }
    
    // Get current entry data
    const editor = this.getActiveEditor();
    const entryData = this.getEntryDataForComponent(editor);
    
    // Merge entry data with collection data
    const allData = { ...entryData, ...templateData };
    
    // Process template using the client-side renderer
    let rendered = templateText;
    
    // Use the render function from birkly-client.js if available
    if (window.Birkly && typeof window.Birkly.render === 'function') {
      rendered = window.Birkly.render(templateText, allData);
    } else {
      // Fallback: basic processing
      rendered = this.processTemplateBasic(templateText, allData);
    }
    
    
    // Update component content
    componentElement.innerHTML = rendered;
    
    // Re-apply CSS and JS if needed
    const style = componentElement.getAttribute('data-component-style') || '';
    const script = componentElement.getAttribute('data-component-script') || '';
    const autoScope = componentElement.getAttribute('data-component-auto-scope') === 'true';
    const executeJs = componentElement.getAttribute('data-component-execute-js') === 'true';
    
    if (style) {
      const styleId = `component-style-${componentElement.id}`;
      let styleEl = document.getElementById(styleId);
      if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = styleId;
        document.head.appendChild(styleEl);
      }
      let scopedCSS = style;
      if (autoScope) {
        scopedCSS = this.scopeComponentCSS(style, `#${componentElement.id}`);
      }
      styleEl.textContent = scopedCSS;
    }
    
    if (script && executeJs) {
      const scriptId = `component-script-${componentElement.id}`;
      let scriptEl = document.getElementById(scriptId);
      if (!scriptEl) {
        scriptEl = document.createElement('script');
        scriptEl.id = scriptId;
        scriptEl.type = 'text/javascript';
        document.head.appendChild(scriptEl);
      }
      const scopedScript = `
        (function() {
          const componentElement = document.getElementById('${componentElement.id}');
          if (!componentElement) return;
          ${script}
        })();
      `;
      scriptEl.textContent = scopedScript;
    }
  },

  // Basic template processing (fallback)
  processTemplateBasic(template, data) {
    let output = template;
    
    // Process {for each entry in 'collection'} loops
    const loopRegex = /\{for\s+(?:each\s+)?([a-zA-Z0-9_]+)\s+in\s+['"]([^'"]+)['"]\}([\s\S]*?)\{endfor\}/g;
    output = output.replace(loopRegex, (match, itemVar, collectionName, loopContent) => {
      const items = data[collectionName] || [];
      if (!Array.isArray(items)) {
        console.warn('ContentEditor: Collection', collectionName, 'is not an array:', typeof items);
        return '';
      }
      
      if (items.length === 0) {
        // Show a message when collection is empty
        return `<div style="padding: 16px; background: #f9fafb; border: 1px dashed #d1d5db; border-radius: 4px; color: #6b7280; font-size: 14px; text-align: center;">
          <p style="margin: 0;">No entries found in collection "${collectionName}"</p>
        </div>`;
      }
      
      
      return items.map((item, index) => {
        let itemOutput = loopContent;
        // Replace { variable } with item properties
        itemOutput = itemOutput.replace(/\{([^}]+)\}/g, (varMatch, varName) => {
          varName = varName.trim();
          
          // Skip templating keywords
          if (['for', 'each', 'in', 'if', 'else', 'endif', 'endfor'].includes(varName.toLowerCase())) {
            return varMatch;
          }
          
          // Check if it's a property of the item
          if (item[varName] !== undefined && item[varName] !== null) {
            const value = item[varName];
            // If it's HTML content, return as-is, otherwise escape
            if (typeof value === 'string' && value.includes('<')) {
              return value;
            }
            return String(value);
          }
          
          // Check if it's in the main data
          if (data[varName] !== undefined && data[varName] !== null) {
            return String(data[varName]);
          }
          
          return '';
        });
        return itemOutput;
      }).join('');
    });
    
    // Process simple variables (after loops to avoid replacing loop variables)
    output = output.replace(/\{([^}]+)\}/g, (match, varName) => {
      varName = varName.trim();
      // Skip templating keywords
      if (['for', 'each', 'in', 'if', 'else', 'endif', 'endfor'].includes(varName.toLowerCase())) {
        return match;
      }
      return data[varName] !== undefined && data[varName] !== null ? String(data[varName]) : '';
    });
    
    return output;
  },

  // Unescape HTML entities (handles single and double escaping)
  unescapeHtml(text) {
    if (!text) return '';
    // First decode any double-escaped entities
    let decoded = text
      .replace(/&amp;amp;/g, '&')
      .replace(/&amp;lt;/g, '<')
      .replace(/&amp;gt;/g, '>')
      .replace(/&amp;quot;/g, '"')
      .replace(/&amp;#39;/g, "'");
    
    // Then decode standard entities
    const textarea = document.createElement('textarea');
    textarea.innerHTML = decoded;
    return textarea.value || decoded;
  },

  extractEmbedData(element, type) {
    const data = {};
    if (!element || !type) return data;
    switch (type) {
      case 'link-preview':
        data.url = element.getAttribute('data-link-url') || element.querySelector('.content-editor-link-preview-url')?.textContent?.trim() || '';
        data.title = element.querySelector('.content-editor-link-preview-title')?.textContent?.trim() || '';
        data.description = element.querySelector('.content-editor-link-preview-description')?.textContent?.trim() || '';
        data.image = element.querySelector('.content-editor-link-preview-image')?.src || '';
        break;
      case 'video':
      case 'iframe': {
        const iframe = element.querySelector('iframe');
        if (iframe) {
          data.url = iframe.src || '';
          data.width = iframe.width || '';
          data.height = iframe.height || '';
          data.allowfullscreen = iframe.hasAttribute('allowfullscreen');
          if (type === 'video') {
            if (/youtube\.com|youtu\.be/.test(iframe.src)) {
              data.type = 'youtube';
            } else if (/vimeo\.com/.test(iframe.src)) {
              data.type = 'vimeo';
            } else {
              data.type = 'iframe';
            }
          }
        } else if (type === 'video') {
          const videoEl = element.querySelector('video');
          if (videoEl) {
            data.url = videoEl.querySelector('source')?.src || videoEl.src || '';
            data.type = 'native';
            data.autoplay = videoEl.autoplay;
            data.controls = videoEl.controls;
            data.loop = videoEl.loop;
            data.muted = videoEl.muted;
          }
        }
        break;
      }
      case 'audio': {
        const audio = element.querySelector('audio');
        if (audio) {
          data.url = audio.querySelector('source')?.src || audio.src || '';
          data.autoplay = audio.autoplay;
          data.controls = audio.controls;
          data.loop = audio.loop;
          data.preload = audio.preload || 'auto';
        }
        break;
      }
      case 'maps': {
        const iframe = element.querySelector('iframe');
        if (iframe) {
          data.embed_url = iframe.src || '';
          data.width = iframe.style.width || iframe.width || '100%';
          data.height = iframe.style.height || iframe.height || '400';
          // Try to extract provider and input method from URL
          if (iframe.src.includes('google.com/maps')) {
            data.provider = 'google';
          } else if (iframe.src.includes('openstreetmap.org')) {
            data.provider = 'openstreetmap';
          } else {
            data.provider = 'embed';
            data.input_method = 'embed_url';
          }
        }
        // Try to get data from embedConfig if available
        if (element.dataset.embedConfig) {
          try {
            const config = JSON.parse(element.dataset.embedConfig);
            Object.assign(data, config);
          } catch (e) {
            // Ignore parse errors
          }
        }
        break;
      }
      case 'custom':
        data.html = element.querySelector('.content-editor-custom-embed-content')?.innerHTML || '';
        data.label = element.querySelector('.content-editor-custom-embed-label')?.textContent?.trim() || '';
        break;
    }
    return data;
  },

  normalizeExternalUrl(value) {
    if (!value) return '';
    let normalized = value.trim();
    if (!normalized) return '';
    if (!/^https?:\/\//i.test(normalized)) {
      normalized = 'https://' + normalized;
    }
    return normalized;
  },

  isLikelyExternalUrl(value) {
    const normalized = this.normalizeExternalUrl(value);
    if (!normalized) return false;
    try {
      const parsed = new URL(normalized);
      return parsed.protocol === 'http:' || parsed.protocol === 'https:';
    } catch (e) {
      return false;
    }
  },

  extractHostname(url) {
    if (!url) return '';
    try {
      const parsed = new URL(url);
      return parsed.hostname || url;
    } catch (e) {
      return url;
    }
  },

  getActiveEditor() {
    if (window.ContentEditorState && window.ContentEditorState.activeEditor) {
      return window.ContentEditorState.activeEditor;
    }
    const active = document.activeElement ? document.activeElement.closest('.content-editor-area') : null;
    if (active) return active;
    return document.querySelector('.content-editor-area');
  },

  getMetadataEndpoint() {
    const editor = this.getActiveEditor();
    const wrapper = editor?.closest('.content-editor-wrapper');
    return wrapper?.dataset.linkMetadata || '/fieldtypes/ContentEditor/link-metadata.php';
  },

  fetchLinkMetadata(endpoint, url) {
    if (!endpoint || !url) return Promise.resolve(null);
    const normalized = this.normalizeExternalUrl(url);
    return fetch(`${endpoint}?url=${encodeURIComponent(normalized)}`)
      .then(res => res.json())
      .then(result => {
        if (result && result.success && result.data) {
          return result.data;
        }
        return null;
      })
      .catch(() => null);
  },

  // Create video embed element
  createVideoEmbed(data) {
    const wrapper = document.createElement('div');
    wrapper.className = 'content-editor-embed content-editor-video-embed';
    wrapper.setAttribute('data-embed-type', 'video');
    
    if (data.type === 'youtube') {
      const videoId = this.extractYouTubeId(data.url);
      if (videoId) {
        // Create responsive wrapper for YouTube embed
        wrapper.style.position = 'relative';
        wrapper.style.paddingBottom = '56.25%'; // 16:9 aspect ratio
        wrapper.style.height = '0';
        wrapper.style.overflow = 'hidden';
        wrapper.style.maxWidth = '100%';
        wrapper.style.margin = '10px 0';
        wrapper.style.borderRadius = '8px';
        wrapper.style.border = '1px solid #e1e5e9';
        wrapper.style.background = '#000';
        
        // Create iframe
        const iframe = document.createElement('iframe');
        const autoplayParam = data.autoplay ? '&autoplay=1' : '';
        iframe.src = `https://www.youtube.com/embed/${videoId}?rel=0&modestbranding=1&playsinline=1${autoplayParam}`;
        iframe.style.position = 'absolute';
        iframe.style.top = '0';
        iframe.style.left = '0';
        iframe.style.width = '100%';
        iframe.style.height = '100%';
        iframe.style.borderRadius = '8px';
        iframe.style.border = 'none';
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen');
        iframe.setAttribute('loading', 'lazy');
        iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        iframe.setAttribute('title', 'YouTube video player');
        
        const fallbackBanner = document.createElement('div');
        fallbackBanner.className = 'content-editor-video-fallback';
        fallbackBanner.innerHTML = `
          <div>
            <p>Video can’t be embedded here.</p>
            <a href="${data.url}" target="_blank" rel="noopener noreferrer">Watch on YouTube</a>
          </div>
        `;
        fallbackBanner.style.display = 'flex';
        
        const toggleFallback = (visible) => {
          fallbackBanner.style.display = visible ? 'flex' : 'none';
        };
        
        iframe.addEventListener('load', () => {
          toggleFallback(false);
        });
        
        iframe.addEventListener('error', () => {
          toggleFallback(true);
        });
        
        wrapper.appendChild(iframe);
        wrapper.appendChild(fallbackBanner);
        wrapper.setAttribute('data-video-id', videoId);
        wrapper.setAttribute('data-video-type', 'youtube');
      } else {
        wrapper.textContent = 'Invalid YouTube URL';
        wrapper.className += ' content-editor-embed-error';
      }
    } else if (data.type === 'vimeo') {
      const videoId = this.extractVimeoId(data.url);
      if (videoId) {
        const iframe = document.createElement('iframe');
        iframe.src = `https://player.vimeo.com/video/${videoId}`;
        iframe.width = data.width || '100%';
        iframe.height = data.height || '400';
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allowfullscreen', '');
        iframe.setAttribute('allow', 'autoplay; fullscreen; picture-in-picture');
        iframe.setAttribute('loading', 'lazy');
        wrapper.appendChild(iframe);
        wrapper.setAttribute('data-video-id', videoId);
        wrapper.setAttribute('data-video-type', 'vimeo');
      } else {
        wrapper.textContent = 'Invalid Vimeo URL';
        wrapper.className += ' content-editor-embed-error';
      }
    } else if (data.type === 'iframe') {
      const iframe = document.createElement('iframe');
      iframe.src = data.url;
      iframe.width = data.width || '100%';
      iframe.height = data.height || '400';
      iframe.setAttribute('frameborder', '0');
      iframe.setAttribute('allowfullscreen', '');
      iframe.setAttribute('loading', 'lazy');
      if (data.sandbox) {
        iframe.setAttribute('sandbox', data.sandbox);
      }
      wrapper.appendChild(iframe);
    } else {
      // Native video
      const video = document.createElement('video');
      video.src = data.url;
      video.controls = data.controls !== false;
      if (data.width) video.width = data.width;
      if (data.height) video.height = data.height;
      if (data.autoplay) video.autoplay = true;
      if (data.loop) video.loop = true;
      if (data.muted) video.muted = true;
      wrapper.appendChild(video);
    }
    
    return wrapper;
  },

  // Create audio embed element
  createAudioEmbed(data) {
    const wrapper = document.createElement('div');
    wrapper.className = 'content-editor-embed content-editor-audio-embed';
    wrapper.setAttribute('data-embed-type', 'audio');
    
    const audio = document.createElement('audio');
    audio.src = data.url;
    audio.controls = data.controls !== false;
    if (data.autoplay) audio.autoplay = true;
    if (data.loop) audio.loop = true;
    if (data.preload) audio.preload = data.preload;
    wrapper.appendChild(audio);
    
    return wrapper;
  },

  // Create map embed element
  createMapEmbed(data) {
    const wrapper = document.createElement('div');
    wrapper.className = 'content-editor-embed content-editor-maps-embed';
    wrapper.setAttribute('data-embed-type', 'maps');
    
    const provider = data.provider || 'google';
    const inputMethod = data.input_method || 'address';
    const width = data.width || '100%';
    const height = data.height || '400';
    const zoom = data.zoom || '15';
    
    const iframe = document.createElement('iframe');
    iframe.setAttribute('frameborder', '0');
    iframe.setAttribute('allowfullscreen', '');
    iframe.setAttribute('loading', 'lazy');
    iframe.style.width = width;
    iframe.style.height = height;
    iframe.style.border = '1px solid #e5e7eb';
    iframe.style.borderRadius = '8px';
    
    let mapUrl = '';
    
    if (provider === 'embed' || inputMethod === 'embed_url') {
      // Custom embed URL
      mapUrl = data.embed_url || data.url || '';
    } else if (provider === 'google') {
      // Google Maps - using the standard embed format (no API key required)
      // If we have an embed_url, use it directly (from pasted URL conversion)
      if (inputMethod === 'embed_url' && data.embed_url) {
        mapUrl = data.embed_url;
      } else if (inputMethod === 'address' && data.address) {
        const address = encodeURIComponent(data.address);
        // Try using www.google.com/maps format with output=embed
        mapUrl = `https://www.google.com/maps?q=${address}&output=embed&z=${zoom}`;
      } else if (inputMethod === 'coordinates' && data.latitude && data.longitude) {
        const lat = parseFloat(data.latitude);
        const lng = parseFloat(data.longitude);
        // Use coordinates with standard embed format
        mapUrl = `https://www.google.com/maps?q=${lat},${lng}&output=embed&z=${zoom}`;
      } else if (data.embed_url) {
        // Fallback to embed_url if available
        mapUrl = data.embed_url;
      } else if (data.address) {
        // Fallback to address
        const address = encodeURIComponent(data.address);
        mapUrl = `https://www.google.com/maps?q=${address}&output=embed&z=${zoom}`;
      } else if (data.latitude && data.longitude) {
        // Fallback to coordinates
        const lat = parseFloat(data.latitude);
        const lng = parseFloat(data.longitude);
        mapUrl = `https://www.google.com/maps?q=${lat},${lng}&output=embed&z=${zoom}`;
      }
    } else if (provider === 'openstreetmap') {
      // OpenStreetMap using Leaflet or direct embed
      if (inputMethod === 'address' && data.address) {
        const address = encodeURIComponent(data.address);
        mapUrl = `https://www.openstreetmap.org/export/embed.html?bbox=-0.1,51.5,0.1,51.5&layer=mapnik&marker=${encodeURIComponent(data.address)}`;
      } else if (inputMethod === 'coordinates' && data.latitude && data.longitude) {
        const lat = parseFloat(data.latitude);
        const lng = parseFloat(data.longitude);
        const zoomLevel = parseInt(zoom) || 15;
        // OpenStreetMap embed URL
        mapUrl = `https://www.openstreetmap.org/export/embed.html?bbox=${lng-0.01},${lat-0.01},${lng+0.01},${lat+0.01}&layer=mapnik&marker=${lat},${lng}`;
      } else {
        // Fallback - use OpenStreetMap with default location
        mapUrl = `https://www.openstreetmap.org/export/embed.html?bbox=-0.1,51.5,0.1,51.5&layer=mapnik`;
      }
    }
    
    if (mapUrl) {
      iframe.src = mapUrl;
      iframe.setAttribute('title', 'Map');
      wrapper.appendChild(iframe);
    } else {
      // Error state
      const errorDiv = document.createElement('div');
      errorDiv.className = 'content-editor-maps-error';
      errorDiv.style.cssText = 'padding: 40px; text-align: center; color: #6b7280; background: #f9fafb; border-radius: 8px;';
      errorDiv.textContent = 'Invalid map configuration. Please provide address, coordinates, or embed URL.';
      wrapper.appendChild(errorDiv);
    }
    
    return wrapper;
  },

  // Create link preview embed element
  createLinkPreviewEmbed(data) {
    const wrapper = document.createElement('div');
    wrapper.className = 'content-editor-embed content-editor-link-preview';
    wrapper.setAttribute('data-embed-type', 'link-preview');
    wrapper.setAttribute('data-link-url', data.url);
    
    const normalizedUrl = this.normalizeExternalUrl(data.url || '');
    const hostname = this.extractHostname(normalizedUrl);
    const previewTitle = data.title && data.title.trim() ? data.title.trim() : hostname || 'Link';
    let previewDesc = data.description && data.description.trim() ? data.description.trim() : '';
    const siteName = data.site_name && data.site_name.trim() ? data.site_name.trim() : hostname;
    let imageSrc = data.image && data.image.trim() ? data.image.trim() : '';
    
    // Fallback metadata for known providers (YouTube, Vimeo, etc.)
    if (!imageSrc) {
      const youtubeId = this.extractYouTubeId(normalizedUrl);
      if (youtubeId) {
        imageSrc = `https://img.youtube.com/vi/${youtubeId}/hqdefault.jpg`;
        if (!previewDesc) {
          previewDesc = 'Watch this video on YouTube.';
        }
      }
    }
    
    const visual = document.createElement('div');
    visual.className = 'content-editor-link-preview-visual';
    
    if (imageSrc) {
      const img = document.createElement('img');
      img.src = imageSrc;
      img.className = 'content-editor-link-preview-image';
      img.alt = previewTitle;
      visual.appendChild(img);
    } else {
      const placeholder = document.createElement('div');
      placeholder.className = 'content-editor-link-preview-image placeholder';
      placeholder.textContent = (siteName || hostname || '?').charAt(0).toUpperCase();
      visual.appendChild(placeholder);
    }
    
    const content = document.createElement('div');
    content.className = 'content-editor-link-preview-content';
    
    const site = document.createElement('div');
    site.className = 'content-editor-link-preview-site';
    site.textContent = siteName || hostname || '';
    content.appendChild(site);
    
    const title = document.createElement('div');
    title.className = 'content-editor-link-preview-title';
    title.textContent = previewTitle;
    content.appendChild(title);
    
    if (previewDesc) {
      const desc = document.createElement('div');
      desc.className = 'content-editor-link-preview-description';
      desc.textContent = previewDesc;
      content.appendChild(desc);
    }
    
    wrapper.appendChild(visual);
    wrapper.appendChild(content);
    
    return wrapper;
  },

  // Create iframe embed element
  createIframeEmbed(data) {
    const wrapper = document.createElement('div');
    wrapper.className = 'content-editor-embed content-editor-iframe-embed';
    wrapper.setAttribute('data-embed-type', 'iframe');
    
    const iframe = document.createElement('iframe');
    iframe.src = data.url;
    iframe.width = data.width || '100%';
    iframe.height = data.height || '400';
    iframe.setAttribute('frameborder', '0');
    iframe.setAttribute('allowfullscreen', '');
    iframe.setAttribute('loading', 'lazy');
    if (data.sandbox) {
      iframe.setAttribute('sandbox', data.sandbox);
    }
    wrapper.appendChild(iframe);
    
    return wrapper;
  },

  // Create custom embed element
  createCustomEmbed(data) {
    const wrapper = document.createElement('div');
    wrapper.className = 'content-editor-embed content-editor-custom-embed';
    wrapper.setAttribute('data-embed-type', 'custom');
    
    if (data.label) {
      const label = document.createElement('div');
      label.className = 'content-editor-custom-embed-label';
      label.textContent = data.label;
      wrapper.appendChild(label);
    }
    
    const content = document.createElement('div');
    content.className = 'content-editor-custom-embed-content';
    content.innerHTML = data.html;
    wrapper.appendChild(content);
    
    return wrapper;
  },

  // Extract YouTube video ID from URL
  extractYouTubeId(url) {
    const patterns = [
      /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([^&\n?#]+)/,
      /youtube\.com\/watch\?.*v=([^&\n?#]+)/
    ];
    
    for (const pattern of patterns) {
      const match = url.match(pattern);
      if (match && match[1]) {
        return match[1];
      }
    }
    return null;
  },

  // Extract Vimeo video ID from URL
  extractVimeoId(url) {
    const patterns = [
      /vimeo\.com\/(\d+)/,
      /vimeo\.com\/video\/(\d+)/
    ];
    
    for (const pattern of patterns) {
      const match = url.match(pattern);
      if (match && match[1]) {
        return match[1];
      }
    }
    return null;
  },

  // Clear formatting (remove inline formatting, keep block structure)
  clearFormatting() {
    const selection = window.getSelection();
    if (!selection.rangeCount) {
      // No selection, clear formatting from current block
      const block = this.getCurrentBlock();
      if (block) {
        this.clearInlineFormatting(block);
      }
      return;
    }
    
    const range = selection.getRangeAt(0);
    
    // If selection is collapsed, clear formatting from current block
    if (range.collapsed) {
      const block = this.getCurrentBlock();
      if (block) {
        this.clearInlineFormatting(block);
      }
      return;
    }
    
    // Get all nodes in the selection
    const contents = range.extractContents();
    const fragment = document.createDocumentFragment();
    
    // Process all nodes in the selection
    const processNode = (node) => {
      if (node.nodeType === Node.TEXT_NODE) {
        // Text node - keep as is
        fragment.appendChild(node.cloneNode(true));
      } else if (node.nodeType === Node.ELEMENT_NODE) {
        const tagName = node.tagName.toLowerCase();
        
        // Block elements - keep structure but clear inline formatting inside
        const blockTags = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'pre'];
        if (blockTags.includes(tagName)) {
          const cloned = node.cloneNode(false); // Clone without children
          // Process children and clear inline formatting
          Array.from(node.childNodes).forEach(child => {
            const processed = this.processNodeForClearFormatting(child);
            if (processed) {
              if (processed.nodeType === Node.DOCUMENT_FRAGMENT_NODE) {
                // Fragment - append all children
                Array.from(processed.childNodes).forEach(c => cloned.appendChild(c));
              } else {
                cloned.appendChild(processed);
              }
            }
          });
          fragment.appendChild(cloned);
        } else {
          // Inline element - extract text content only
          const textContent = node.textContent;
          if (textContent) {
            const textNode = document.createTextNode(textContent);
            fragment.appendChild(textNode);
          }
        }
      }
    };
    
    // Process all nodes
    Array.from(contents.childNodes).forEach(node => {
      processNode(node);
    });
    
    // Insert the cleaned content
    range.insertNode(fragment);
    
    // Collapse range to end
    range.collapse(false);
    selection.removeAllRanges();
    selection.addRange(range);
    
    // Sync and update toolbar
    const editor = this.getActiveEditor();
    if (editor) {
      if (window.ContentEditorSync) {
        const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
        if (hiddenInput) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
      }
      if (window.ContentEditorToolbar) {
        const toolbar = editor.closest('.content-editor-wrapper')?.querySelector('.content-editor-toolbar') ||
                       document.querySelector('.content-editor-toolbar');
        if (toolbar) {
          window.ContentEditorToolbar.updateState(editor, toolbar);
        }
      }
    }
  },

  // Process a node for clear formatting (recursive helper)
  processNodeForClearFormatting(node) {
    if (node.nodeType === Node.TEXT_NODE) {
      return node.cloneNode(true);
    }
    
    if (node.nodeType === Node.ELEMENT_NODE) {
      const tagName = node.tagName.toLowerCase();
      
      // Inline formatting tags to remove
      const inlineTags = ['b', 'strong', 'i', 'em', 'u', 's', 'del', 'strike', 'code', 'a', 'span', 'mark', 'small', 'sub', 'sup'];
      
      if (inlineTags.includes(tagName)) {
        // Extract text content only
        const fragment = document.createDocumentFragment();
        Array.from(node.childNodes).forEach(child => {
          const processed = this.processNodeForClearFormatting(child);
          if (processed) {
            if (processed.nodeType === Node.DOCUMENT_FRAGMENT_NODE) {
              Array.from(processed.childNodes).forEach(c => fragment.appendChild(c));
            } else {
              fragment.appendChild(processed);
            }
          }
        });
        return fragment;
      }
      
      // Block elements - keep structure
      const blockTags = ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'pre', 'table', 'tr', 'td', 'th', 'thead', 'tbody', 'tfoot'];
      if (blockTags.includes(tagName)) {
        const cloned = node.cloneNode(false);
        Array.from(node.childNodes).forEach(child => {
          const processed = this.processNodeForClearFormatting(child);
          if (processed) {
            if (processed.nodeType === Node.DOCUMENT_FRAGMENT_NODE) {
              Array.from(processed.childNodes).forEach(c => cloned.appendChild(c));
            } else {
              cloned.appendChild(processed);
            }
          }
        });
        return cloned;
      }
      
      // Other elements - extract text
      const fragment = document.createDocumentFragment();
      Array.from(node.childNodes).forEach(child => {
        const processed = this.processNodeForClearFormatting(child);
        if (processed) {
          if (processed.nodeType === Node.DOCUMENT_FRAGMENT_NODE) {
            Array.from(processed.childNodes).forEach(c => fragment.appendChild(c));
          } else {
            fragment.appendChild(processed);
          }
        }
      });
      return fragment;
    }
    
    return null;
  },

  // Clear inline formatting from a block element
  clearInlineFormatting(block) {
    if (!block || block.nodeType !== Node.ELEMENT_NODE) return;
    
    // Inline tags to remove
    const inlineTags = ['b', 'strong', 'i', 'em', 'u', 's', 'del', 'strike', 'code', 'a', 'span', 'mark', 'small', 'sub', 'sup'];
    
    // Find all inline elements
    const inlineElements = [];
    const walker = document.createTreeWalker(
      block,
      NodeFilter.SHOW_ELEMENT,
      {
        acceptNode: (node) => {
          const tagName = node.tagName.toLowerCase();
          if (inlineTags.includes(tagName)) {
            return NodeFilter.FILTER_ACCEPT;
          }
          return NodeFilter.FILTER_SKIP;
        }
      }
    );
    
    let node;
    while (node = walker.nextNode()) {
      inlineElements.push(node);
    }
    
    // Unwrap all inline elements (replace with their text content)
    inlineElements.forEach(element => {
      const parent = element.parentNode;
      while (element.firstChild) {
        parent.insertBefore(element.firstChild, element);
      }
      parent.removeChild(element);
      parent.normalize(); // Merge adjacent text nodes
    });
    
    // Also remove inline styles
    const allElements = block.querySelectorAll('*');
    allElements.forEach(el => {
      // Remove style attribute
      el.removeAttribute('style');
      // Remove data-align attribute
      el.removeAttribute('data-align');
    });
    
    // Sync and update toolbar
    const editor = this.getActiveEditor();
    if (editor) {
      if (window.ContentEditorSync) {
        const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
        if (hiddenInput) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
      }
      if (window.ContentEditorToolbar) {
        const toolbar = editor.closest('.content-editor-wrapper')?.querySelector('.content-editor-toolbar') ||
                       document.querySelector('.content-editor-toolbar');
        if (toolbar) {
          window.ContentEditorToolbar.updateState(editor, toolbar);
        }
      }
    }
  },

  // Apply text color
  applyTextColor(color) {
    if (!color) return;
    
    // Restore selection first
    const editor = this.getActiveEditor();
    if (editor) {
      window.ContentEditorSelection.restore(editor);
    } else {
      window.ContentEditorSelection.restore();
    }
    
    const selection = window.getSelection();
    let range = null;
    
    if (selection.rangeCount > 0) {
      range = selection.getRangeAt(0);
    } else {
      // No selection, try to get current block
      const block = this.getCurrentBlock();
      if (block) {
        range = document.createRange();
        range.selectNodeContents(block);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
      } else {
        return;
      }
    }
    
    if (!range) return;
    
    if (range.collapsed) {
      // Collapsed selection, apply to current block
      const block = this.getCurrentBlock();
      if (block) {
        block.style.color = color;
      }
    } else {
      // Has selection, wrap in span with color
      const span = document.createElement('span');
      span.style.color = color;
      
      try {
        range.surroundContents(span);
      } catch (e) {
        // If surroundContents fails, extract and wrap
        const contents = range.extractContents();
        span.appendChild(contents);
        range.insertNode(span);
      }
      
      // Collapse range to end
      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
    }
    
    // Sync and update toolbar
    if (editor) {
      if (window.ContentEditorSync) {
        const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
        if (hiddenInput) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
      }
      if (window.ContentEditorToolbar) {
        const toolbar = editor.closest('.content-editor-wrapper')?.querySelector('.content-editor-toolbar') ||
                       document.querySelector('.content-editor-toolbar');
        if (toolbar) {
          window.ContentEditorToolbar.updateState(editor, toolbar);
        }
      }
    }
  },

  // Apply background color
  applyBgColor(color) {
    if (!color) return;
    
    // Restore selection first
    const editor = this.getActiveEditor();
    if (editor) {
      window.ContentEditorSelection.restore(editor);
    } else {
      window.ContentEditorSelection.restore();
    }
    
    const selection = window.getSelection();
    let range = null;
    
    if (selection.rangeCount > 0) {
      range = selection.getRangeAt(0);
    } else {
      // No selection, try to get current block
      const block = this.getCurrentBlock();
      if (block) {
        range = document.createRange();
        range.selectNodeContents(block);
        range.collapse(false);
        selection.removeAllRanges();
        selection.addRange(range);
      } else {
        return;
      }
    }
    
    if (!range) return;
    
    if (range.collapsed) {
      // Collapsed selection, apply to current block
      const block = this.getCurrentBlock();
      if (block) {
        block.style.backgroundColor = color;
      }
    } else {
      // Has selection, wrap in span with background color
      const span = document.createElement('span');
      span.style.backgroundColor = color;
      
      try {
        range.surroundContents(span);
      } catch (e) {
        // If surroundContents fails, extract and wrap
        const contents = range.extractContents();
        span.appendChild(contents);
        range.insertNode(span);
      }
      
      // Collapse range to end
      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
    }
    
    // Sync and update toolbar
    if (editor) {
      if (window.ContentEditorSync) {
        const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
        if (hiddenInput) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
      }
      if (window.ContentEditorToolbar) {
        const toolbar = editor.closest('.content-editor-wrapper')?.querySelector('.content-editor-toolbar') ||
                       document.querySelector('.content-editor-toolbar');
        if (toolbar) {
          window.ContentEditorToolbar.updateState(editor, toolbar);
        }
      }
    }
  },

  // Remove text color
  removeTextColor() {
    const selection = window.getSelection();
    if (!selection.rangeCount) {
      const block = this.getCurrentBlock();
      if (block) {
        block.style.color = '';
      }
      return;
    }
    
    const range = selection.getRangeAt(0);
    
    if (range.collapsed) {
      const block = this.getCurrentBlock();
      if (block) {
        block.style.color = '';
      }
      return;
    }
    
    // Find all elements with color style in selection
    const walker = document.createTreeWalker(
      range.commonAncestorContainer,
      NodeFilter.SHOW_ELEMENT,
      null
    );
    
    const elementsToProcess = [];
    let node;
    while (node = walker.nextNode()) {
      if (range.intersectsNode(node) && node.style && node.style.color) {
        elementsToProcess.push(node);
      }
    }
    
    // Remove color from elements
    elementsToProcess.forEach(el => {
      el.style.color = '';
      // If element has no other styles, unwrap if it's a span
      if (el.tagName.toLowerCase() === 'span' && !el.style.cssText.trim()) {
        const parent = el.parentNode;
        while (el.firstChild) {
          parent.insertBefore(el.firstChild, el);
        }
        parent.removeChild(el);
        parent.normalize();
      }
    });
    
    // Sync and update toolbar
    const editor = this.getActiveEditor();
    if (editor) {
      if (window.ContentEditorSync) {
        const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
        if (hiddenInput) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
      }
      if (window.ContentEditorToolbar) {
        const toolbar = editor.closest('.content-editor-wrapper')?.querySelector('.content-editor-toolbar') ||
                       document.querySelector('.content-editor-toolbar');
        if (toolbar) {
          window.ContentEditorToolbar.updateState(editor, toolbar);
        }
      }
    }
  },

  // Remove background color
  removeBgColor() {
    const selection = window.getSelection();
    if (!selection.rangeCount) {
      const block = this.getCurrentBlock();
      if (block) {
        block.style.backgroundColor = '';
      }
      return;
    }
    
    const range = selection.getRangeAt(0);
    
    if (range.collapsed) {
      const block = this.getCurrentBlock();
      if (block) {
        block.style.backgroundColor = '';
      }
      return;
    }
    
    // Find all elements with background color style in selection
    const walker = document.createTreeWalker(
      range.commonAncestorContainer,
      NodeFilter.SHOW_ELEMENT,
      null
    );
    
    const elementsToProcess = [];
    let node;
    while (node = walker.nextNode()) {
      if (range.intersectsNode(node) && node.style && node.style.backgroundColor) {
        elementsToProcess.push(node);
      }
    }
    
    // Remove background color from elements
    elementsToProcess.forEach(el => {
      el.style.backgroundColor = '';
      // If element has no other styles, unwrap if it's a span
      if (el.tagName.toLowerCase() === 'span' && !el.style.cssText.trim()) {
        const parent = el.parentNode;
        while (el.firstChild) {
          parent.insertBefore(el.firstChild, el);
        }
        parent.removeChild(el);
        parent.normalize();
      }
    });
    
    // Sync and update toolbar
    const editor = this.getActiveEditor();
    if (editor) {
      if (window.ContentEditorSync) {
        const hiddenInput = document.getElementById('field_' + editor.dataset.fieldName);
        if (hiddenInput) {
          window.ContentEditorSync.toHidden(editor, hiddenInput);
        }
      }
      if (window.ContentEditorToolbar) {
        const toolbar = editor.closest('.content-editor-wrapper')?.querySelector('.content-editor-toolbar') ||
                       document.querySelector('.content-editor-toolbar');
        if (toolbar) {
          window.ContentEditorToolbar.updateState(editor, toolbar);
        }
      }
    }
  }

};

