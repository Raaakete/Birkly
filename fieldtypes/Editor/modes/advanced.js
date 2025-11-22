// Advanced WYSIWYG Editor Mode - Simple and Robust
window.EditorModes = window.EditorModes || {};
window.EditorModes.advanced = {
  
  // Initialize the advanced editor
  init(container) {
    this.container = container;
    // Find Advanced pane: has both .ed-advanced and .editor-surface classes
    this.surface = container.querySelector('.ed-advanced') || container.querySelector('.editor-surface') || container;
    this.toolbar = null;
    this.content = null;
    
    // Preserve existing content if Advanced pane already has content
    var existingContent = '';
    if (this.surface && this.surface !== container) {
      // Check if Advanced pane already has content (not just toolbar)
      var existingContentEl = this.surface.querySelector('.advanced-content');
      if (existingContentEl) {
        existingContent = existingContentEl.innerHTML || '';
      } else if (this.surface.querySelector('.advanced-toolbar')) {
        // Toolbar exists but content might be in the surface itself
        var childNodes = Array.from(this.surface.childNodes);
        childNodes.forEach(function(node) {
          if (node.nodeType === 1 && !node.classList.contains('advanced-toolbar')) {
            existingContent = node.innerHTML || existingContent;
          }
        });
      }
    }
    
    this.render();
    this.attachEvents();
    
    // Restore existing content if we had it
    if (existingContent && this.content) {
      this.content.innerHTML = existingContent;
      this.container.dataset.value = existingContent;
    }
    
    return this;
  },
  
  // Render the editor interface
  render() {
    // Clear existing content
    this.surface.innerHTML = '';
    
    // Create toolbar
    this.toolbar = document.createElement('div');
    this.toolbar.className = 'advanced-toolbar';
    this.toolbar.innerHTML = `
      <div class="toolbar-group">
        <button type="button" data-command="bold" title="Bold (Ctrl+B)">B</button>
        <button type="button" data-command="italic" title="Italic (Ctrl+I)">I</button>
        <button type="button" data-command="underline" title="Underline (Ctrl+U)">U</button>
        <button type="button" data-command="strikeThrough" title="Strikethrough">S</button>
      </div>
              <div class="toolbar-group">
          <button type="button" data-command="formatBlock" data-value="h1" title="Heading 1">H1</button>
          <button type="button" data-command="formatBlock" data-value="h2" title="Heading 2">H2</button>
          <button type="button" data-command="formatBlock" data-value="h3" title="Heading 3">H3</button>
          <button type="button" data-command="formatBlock" data-value="h4" title="Heading 4">H4</button>
          <button type="button" data-command="formatBlock" data-value="h5" title="Heading 5">H5</button>
          <button type="button" data-command="formatBlock" data-value="h6" title="Heading 6">H6</button>
          <button type="button" data-command="formatBlock" data-value="p" title="Paragraph">P</button>
        </div>
              <div class="toolbar-group">
          <button type="button" data-command="insertUnorderedList" title="Bullet List">•</button>
          <button type="button" data-command="insertOrderedList" title="Numbered List">1.</button>
          <button type="button" data-command="createLink" title="Insert Link">🔗</button>
          <button type="button" data-command="insertTable" title="Insert Table">📊</button>
          <button type="button" data-command="insertEmbed" title="Insert Embed">📺</button>
          <button type="button" data-command="insertQuote" title="Insert Quote">💬</button>
          <button type="button" data-command="insertCode" title="Insert Code">💻</button>
        </div>
      <div class="toolbar-group">
        <button type="button" data-command="justifyLeft" title="Align Left">⫷</button>
        <button type="button" data-command="justifyCenter" title="Align Center">⫸</button>
        <button type="button" data-command="justifyRight" title="Align Right">⫹</button>
      </div>
      <div class="toolbar-group">
        <button type="button" data-command="foreColor" title="Text Color">🎨</button>
        <button type="button" data-command="hiliteColor" title="Background Color">🖍️</button>
      </div>
    `;
    
    // Create content area
    this.content = document.createElement('div');
    this.content.className = 'advanced-content';
    this.content.setAttribute('contenteditable', 'true');
    this.content.setAttribute('data-placeholder', 'Start typing here...');
    
    // Add to surface
    this.surface.appendChild(this.toolbar);
    this.surface.appendChild(this.content);
    
    // Set initial content with proper paragraph structure
    this.setInitialContent();
  },
  
  // Attach event listeners
  attachEvents() {
    // Toolbar button clicks - save selection BEFORE click to prevent loss
    this.toolbar.addEventListener('mousedown', (e) => {
      if (e.target.tagName === 'BUTTON') {
        // Save selection immediately on mousedown, before focus changes
        this.saveSelection();
      }
    });
    
    this.toolbar.addEventListener('click', (e) => {
      if (e.target.tagName === 'BUTTON') {
        e.preventDefault();
        e.stopPropagation();
        
        // CRITICAL: Save selection right before executing
        // Use a small delay to ensure selection is captured after any focus changes
        setTimeout(() => {
          this.saveSelection();
          this.executeCommand(e.target.dataset.command, e.target.dataset.value);
        }, 0);
      }
    });
    

    
    // Content area events
    this.content.addEventListener('input', () => {
      // Fix paragraph spacing on input
      this.fixParagraphSpacing();
      this.saveContent();
    });
    
    this.content.addEventListener('paste', (e) => {
      this.handlePaste(e);
    });
    
    this.content.addEventListener('keydown', (e) => {
      this.handleKeydown(e);
    });
    
    // Save selection on mouse/keyboard activity to ensure it's available for toolbar
    this.content.addEventListener('mouseup', () => {
      setTimeout(() => {
        this.saveSelection();
      }, 0);
    });
    
    this.content.addEventListener('keyup', () => {
      setTimeout(() => {
        this.saveSelection();
      }, 0);
    });
    
    // Handle clicks to create new paragraphs below content blocks
    this.content.addEventListener('click', (e) => {
      this.handleContentClick(e);
    });
    
    // Focus management
    this.content.focus();
    this.container.classList.add('focused');
    
    this.content.addEventListener('focus', () => {
      this.container.classList.add('focused');
    });
    
    this.content.addEventListener('blur', () => {
      this.container.classList.remove('focused');
      this.saveContent();
    });
  },

  // Set initial content with proper paragraph structure
  setInitialContent() {
    if (this.container.dataset.value) {
      // Load existing content without modifying structure
      this.content.innerHTML = this.container.dataset.value;
      
      // Fix paragraph spacing in loaded content
      this.fixParagraphSpacing();
    } else {
      // Create initial paragraph structure only for empty content
      this.content.innerHTML = '<p style="margin:0;padding:0;"><br></p>';
    }
  },
  
  // Fix paragraph spacing by adding inline styles
  fixParagraphSpacing() {
    const paragraphs = this.content.querySelectorAll('p');
    paragraphs.forEach((p, index) => {
      // Force inline styles using cssText to override any CSS
      if (index === 0) {
        p.style.cssText = 'margin: 0 !important; padding: 0 !important; margin-top: 0 !important; margin-bottom: 0 !important;';
      } else {
        p.style.cssText = 'margin: 0 !important; padding: 0 !important; margin-top: 0.25em !important; margin-bottom: 0 !important;';
      }
    });
  },

  // Ensure content has proper paragraph structure
  ensureParagraphStructure() {
    // If content is empty or just whitespace, wrap in paragraph
    if (!this.content.textContent.trim()) {
      this.content.innerHTML = '<p><br></p>';
      return;
    }
    
    // Check if content needs paragraph wrapping
    const needsWrapping = this.contentNeedsParagraphWrapping();
    
    if (needsWrapping) {
      // Only wrap loose text, not lists or other block elements
      this.wrapLooseTextInParagraphs();
    }
  },

  // Check if content needs paragraph wrapping
  contentNeedsParagraphWrapping() {
    const children = Array.from(this.content.children);
    
    // If no children, needs wrapping
    if (children.length === 0) return true;
    
    // Check if any child is not a block element
    return children.some(child => !this.isBlockElement(child));
  },

  // Wrap only loose text in paragraphs, preserve lists and other blocks
  wrapLooseTextInParagraphs() {
    const children = Array.from(this.content.children);
    const newContent = [];
    
    children.forEach(child => {
      if (this.isBlockElement(child)) {
        // Keep block elements as-is (lists, headings, etc.)
        newContent.push(child);
      } else {
        // Wrap non-block elements (text nodes, spans, etc.) in paragraph
        const wrapper = document.createElement('p');
        wrapper.appendChild(child.cloneNode(true));
        newContent.push(wrapper);
      }
    });
    
    // Replace content with properly structured elements
    this.content.innerHTML = '';
    newContent.forEach(element => this.content.appendChild(element));
    
    
  },

  // Check if element is a block element
  isBlockElement(element) {
    const blockTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'BLOCKQUOTE', 'PRE', 'DIV'];
    return blockTags.includes(element.tagName);
  },
  
  // Centralized selection management
  saveSelection() {
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      try {
        const range = selection.getRangeAt(0);
        const ancestor = range.commonAncestorContainer;
        
        // More robust check - selection is within our content area
        if (this.content && (
          this.content.contains(ancestor) || 
          this.content === ancestor ||
          (ancestor.nodeType === Node.TEXT_NODE && this.content.contains(ancestor.parentNode)) ||
          (ancestor.nodeType === Node.ELEMENT_NODE && this.content.contains(ancestor))
        )) {
          this.savedSelection = range.cloneRange();
          return true;
        }
      } catch (e) {
        // Selection save failed
        this.clearSavedSelection();
        return false;
      }
    }
    // No selection - don't clear saved one, might still be valid
    return false;
  },
  
  restoreSelection() {
    if (!this.savedSelection) {
      // If no saved selection, create one at cursor position
      const selection = window.getSelection();
      const range = document.createRange();
      range.selectNodeContents(this.content);
      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
      return false;
    }
    
    try {
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(this.savedSelection);
      this.content.focus();
      return true;
    } catch (e) {
      // Selection might be invalid (e.g., DOM changed), create new one
      const selection = window.getSelection();
      const range = document.createRange();
      range.selectNodeContents(this.content);
      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
      return false;
    }
  },
  
  clearSavedSelection() {
    this.savedSelection = null;
  },
  
  // Execute toolbar commands
  executeCommand(command, value) {
    // Ensure content area is focused
    this.content.focus();
    
    // Get current selection
    const selection = window.getSelection();
    let range = null;
    
    // First, try to restore saved selection if we have one
    if (this.savedSelection) {
      try {
        const savedAncestor = this.savedSelection.commonAncestorContainer;
        // More robust check for saved selection validity
        if (this.content.contains(savedAncestor) || 
            this.content === savedAncestor ||
            (savedAncestor.nodeType === Node.TEXT_NODE && this.content.contains(savedAncestor.parentNode)) ||
            (savedAncestor.nodeType === Node.ELEMENT_NODE && this.content.contains(savedAncestor))) {
          selection.removeAllRanges();
          selection.addRange(this.savedSelection.cloneRange());
          range = selection.getRangeAt(0);
        }
      } catch (e) {
        // Saved selection is invalid, ignore it
      }
    }
    
    // If no valid saved selection, use current selection
    if (!range && selection.rangeCount > 0) {
      try {
        range = selection.getRangeAt(0);
        const ancestor = range.commonAncestorContainer;
        // More robust validation
        if (!(this.content.contains(ancestor) || 
              this.content === ancestor ||
              (ancestor.nodeType === Node.TEXT_NODE && this.content.contains(ancestor.parentNode)) ||
              (ancestor.nodeType === Node.ELEMENT_NODE && this.content.contains(ancestor)))) {
          range = null;
        }
      } catch (e) {
        range = null;
      }
    }
    
    // If still no valid selection, create one at end of content
    if (!range) {
      range = document.createRange();
      range.selectNodeContents(this.content);
      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
    }
    
    // Now execute the command
    switch (command) {
      case 'createLink':
        this.createLink();
        break;
      case 'foreColor':
        this.showColorModal('foreColor');
        break;
      case 'hiliteColor':
        this.showColorModal('hiliteColor');
        break;
      case 'insertTable':
        this.insertTable();
        break;
      case 'insertEmbed':
        this.insertEmbed();
        break;
      case 'insertQuote':
        this.insertQuote();
        break;
      case 'insertCode':
        this.insertCode();
        break;
      case 'formatBlock':
        if (value && ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'].includes(value)) {
          this.applyFormatBlock(value);
        }
        break;
      case 'insertUnorderedList':
      case 'insertOrderedList':
        this.createList(command === 'insertUnorderedList' ? 'ul' : 'ol');
        break;
      default:
        // For formatting commands (bold, italic, etc.), use execCommand
        // Ensure selection is active
        if (selection.rangeCount === 0 && range) {
          selection.addRange(range);
        }
        
        // Execute the command
        try {
          document.execCommand(command, false, value || null);
        } catch (e) {
          // Command execution failed - try once more with fresh selection
          try {
            if (range) {
              selection.removeAllRanges();
              selection.addRange(range);
              document.execCommand(command, false, value || null);
            }
          } catch (e2) {
            // Retry also failed
          }
        }
        
        // Restore selection after command if we had one saved
        if (this.savedSelection && !this.savedSelection.collapsed) {
          try {
            setTimeout(() => {
              const sel = window.getSelection();
              if (sel.rangeCount === 0 || sel.isCollapsed) {
                sel.removeAllRanges();
                sel.addRange(this.savedSelection.cloneRange());
                this.content.focus();
              }
            }, 0);
          } catch (e) {
            // Could not restore selection
          }
        }
        break;
    }
    
    // Clear saved selection after use
    this.clearSavedSelection();
    
    // Update toolbar state
    setTimeout(() => {
      this.updateToolbarState();
    }, 10);
    
    // Save content
    this.saveContent();
  },
  
  // Create a link
  createLink() {
    // Selection should already be saved by executeCommand, but ensure it's saved
    if (!this.savedSelection) {
      this.saveSelection();
    }
    
    // Create professional link modal
    const modal = document.createElement('div');
    modal.id = 'link-modal';
    modal.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    `;
    
    modal.innerHTML = `
      <div style="
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        max-width: 500px;
        width: 90vw;
        overflow: hidden;
        animation: slideIn 0.3s ease;
      ">
        <div style="
          padding: 24px;
          border-bottom: 1px solid #e9ecef;
          display: flex;
          justify-content: space-between;
          align-items: center;
        ">
          <h2 style="margin: 0; color: #333; font-size: 20px; font-weight: 600;">Insert Link</h2>
          <button id="close-link-modal" style="
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            color: #666;
            transition: all 0.2s;
          ">✕</button>
        </div>
        <div style="padding: 24px;">
          <div style="margin-bottom: 20px;">
            <label style="
              display: block;
              margin-bottom: 8px;
              font-weight: 500;
              color: #333;
              font-size: 14px;
            ">URL</label>
            <input type="url" id="link-url" placeholder="https://example.com" value="https://" style="
              width: 100%;
              padding: 12px;
              border: 1px solid #ddd;
              border-radius: 6px;
              font-size: 14px;
              box-sizing: border-box;
              transition: border-color 0.2s;
            ">
          </div>
          <div style="
            padding: 12px;
            background: #f3f4f6;
            border-radius: 6px;
            border-left: 4px solid #3b82f6;
            margin-bottom: 20px;
          ">
            <p style="margin: 0; font-size: 13px; color: #6b7280;">
              💡 <strong>Tip:</strong> Select text first, then click the link button to turn it into a link.
            </p>
          </div>
          <div style="margin-bottom: 24px;">
            <label style="
              display: block;
              margin-bottom: 8px;
              font-weight: 500;
              color: #333;
              font-size: 14px;
            ">Target</label>
            <select id="link-target" style="
              width: 100%;
              padding: 12px;
              border: 1px solid #ddd;
              border-radius: 6px;
              font-size: 14px;
              box-sizing: border-box;
              transition: border-color 0.2s;
            ">
              <option value="_self">Same Window</option>
              <option value="_blank">New Window</option>
            </select>
          </div>
          <div style="
            display: flex;
            gap: 12px;
            justify-content: flex-end;
          ">
            <button id="cancel-link" style="
              padding: 12px 24px;
              border: 1px solid #ddd;
              background: white;
              color: #333;
              border-radius: 6px;
              cursor: pointer;
              font-size: 14px;
              transition: all 0.2s;
            ">Cancel</button>
            <button id="insert-link" style="
              padding: 12px 24px;
              border: none;
              background: #007cba;
              color: white;
              border-radius: 6px;
              cursor: pointer;
              font-size: 14px;
              font-weight: 500;
              transition: all 0.2s;
            ">Insert Link</button>
          </div>
        </div>
      </div>
    `;
    
    // Add event listeners
    modal.addEventListener('click', (e) => {
      if (e.target === modal || e.target.id === 'close-link-modal' || e.target.id === 'cancel-link') {
        this.closeLinkModal();
      } else if (e.target.id === 'insert-link') {
        this.insertLinkFromModal();
      }
    });
    
    // Add focus styles
    const inputs = modal.querySelectorAll('input, select');
    inputs.forEach(input => {
      input.addEventListener('focus', () => {
        input.style.borderColor = '#007cba';
        input.style.boxShadow = '0 0 0 3px rgba(0, 123, 186, 0.1)';
      });
      
      input.addEventListener('blur', () => {
        input.style.borderColor = '#ddd';
        input.style.boxShadow = 'none';
      });
    });
    
    // Add button hover effects
    const buttons = modal.querySelectorAll('button');
    buttons.forEach(button => {
      if (button.id === 'insert-link') {
        button.addEventListener('mouseenter', () => {
          button.style.background = '#005a8b';
        });
        button.addEventListener('mouseleave', () => {
          button.style.background = '#007cba';
        });
      } else if (button.id === 'cancel-link') {
        button.addEventListener('mouseenter', () => {
          button.style.background = '#f8f9fa';
        });
        button.addEventListener('mouseleave', () => {
          button.style.background = 'white';
        });
      }
    });
    
    document.body.appendChild(modal);
    
    // Focus URL input
    setTimeout(() => {
      const urlInput = document.getElementById('link-url');
      if (urlInput) {
        urlInput.focus();
        urlInput.select();
      }
    }, 100);
  },
  
  // Insert link from modal
  insertLinkFromModal() {
    const url = document.getElementById('link-url').value;
    const target = document.getElementById('link-target').value;
    
    if (url && url !== 'https://') {
      
      
      // Restore the saved selection if available
      if (this.savedSelection) {
        this.restoreSelection();
        
        // Ensure content is focused
        this.content.focus();
        
        try {
          // Use createLink command to wrap selected text
          const success = document.execCommand('createLink', false, url);
          
          
          if (success) {
            // Set target attribute on the newly created link
            const range = selection.getRangeAt(0);
            const linkElement = range.startContainer.parentElement.closest('a') || 
                               range.endContainer.parentElement.closest('a');
            
            if (linkElement && target !== '_self') {
              linkElement.setAttribute('target', target);
            }
          } else {
            
            this.insertLinkFallback(url, target);
          }
        } catch (e) {
          
          this.insertLinkFallback(url, target);
        }
      } else {
        
      }
      
      // Clear saved selection
      this.clearSavedSelection();
      
      this.closeLinkModal();
    } else {
      // Highlight the URL input to show it's required
      const urlInput = document.getElementById('link-url');
      urlInput.style.borderColor = '#dc3545';
      urlInput.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
      setTimeout(() => {
        urlInput.style.borderColor = '#ddd';
        urlInput.style.boxShadow = 'none';
      }, 2000);
    }
  },
  
  // Close link modal
  closeLinkModal() {
    const modal = document.getElementById('link-modal');
    if (modal) {
      modal.style.animation = 'slideOut 0.2s ease';
      setTimeout(() => modal.remove(), 200);
    }
  },
  

  
  // Fallback link insertion method
  insertLinkFallback(url, target) {
    
    
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      const selectedText = range.toString();
      
      // Create link element
      const linkElement = document.createElement('a');
      linkElement.href = url;
      if (target !== '_self') {
        linkElement.target = target;
      }
      linkElement.textContent = selectedText;
      
      // Replace selection with link
      range.deleteContents();
      range.insertNode(linkElement);
      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
      
    } else {
      
    }
  },
  
  // Show color modal
  showColorModal(colorType) {
    
    
    // Store the current selection before opening modal
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      this.savedSelection = selection.getRangeAt(0).cloneRange();
    } else {
      
      this.clearSavedSelection();
    }
    
    // Remove any existing modal first
    const existingModal = document.getElementById('color-modal');
    if (existingModal) {
      existingModal.remove();
    }
    
    // Create modal with inline styles (no CSS dependencies)
    const modal = document.createElement('div');
    modal.id = 'color-modal';
    modal.style.cssText = `
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      width: 100vw !important;
      height: 100vh !important;
      background: rgba(0, 0, 0, 0.7) !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      z-index: 999999 !important;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    `;
    
    
    
    const title = colorType === 'foreColor' ? 'Text Color' : 'Background Color';
    
    modal.innerHTML = `
      <div style="
        background: white !important;
        border-radius: 12px !important;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
        max-width: 90vw !important;
        max-height: 90vh !important;
        overflow: hidden !important;
        width: 400px !important;
      ">
        <div style="
          padding: 24px;
          border-bottom: 1px solid #e9ecef;
          display: flex;
          justify-content: space-between;
          align-items: center;
        ">
          <h2 style="margin: 0; color: #333; font-size: 20px; font-weight: 600;">${title}</h2>
          <button id="close-color-modal" style="
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            color: #666;
            transition: all 0.2s;
          ">✕</button>
        </div>
        <div style="padding: 24px;">
          <!-- Color Picker Section -->
          <div style="margin-bottom: 24px;">
            <label style="
              display: block;
              margin-bottom: 8px;
              font-weight: 500;
              color: #333;
              font-size: 14px;
            ">Choose Color</label>
            <div style="
              display: flex;
              align-items: center;
              gap: 12px;
            ">
              <input type="color" id="color-picker" style="
                width: 60px;
                height: 60px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                background: none;
              " value="#3b82f6">
              <div style="
                flex: 1;
                padding: 12px;
                background: #f8f9fa;
                border-radius: 8px;
                border: 1px solid #e9ecef;
                font-family: monospace;
                font-size: 14px;
                color: #333;
              " id="color-preview">
                #3b82f6
              </div>
            </div>
          </div>
          
          <!-- Hex Input Section -->
          <div style="margin-bottom: 24px;">
            <label style="
              display: block;
              margin-bottom: 8px;
              font-weight: 500;
              color: #333;
              font-size: 14px;
            ">Hex Color Code</label>
            <input type="text" id="hex-input" placeholder="#3b82f6" style="
              width: 100%;
              padding: 12px;
              border: 1px solid #ddd;
              border-radius: 6px;
              font-size: 14px;
              font-family: monospace;
              box-sizing: border-box;
            " value="#3b82f6">
          </div>
          
          <!-- RGBA Input Section -->
          <div style="margin-bottom: 24px;">
            <label style="
              display: block;
              margin-bottom: 8px;
              font-weight: 500;
              color: #333;
              font-size: 14px;
            ">RGBA Values</label>
            <div style="
              display: grid;
              grid-template-columns: 1fr 1fr 1fr 1fr;
              gap: 8px;
            ">
              <div>
                <label style="font-size: 12px; color: #666;">R</label>
                <input type="number" id="rgba-r" min="0" max="255" style="
                  width: 100%;
                  padding: 8px;
                  border: 1px solid #ddd;
                  border-radius: 4px;
                  font-size: 12px;
                  text-align: center;
                  box-sizing: border-box;
                " value="59">
              </div>
              <div>
                <label style="font-size: 12px; color: #666;">G</label>
                <input type="number" id="rgba-g" min="0" max="255" style="
                  width: 100%;
                  padding: 8px;
                  border: 1px solid #ddd;
                  border-radius: 4px;
                  font-size: 12px;
                  text-align: center;
                  box-sizing: border-box;
                " value="130">
              </div>
              <div>
                <label style="font-size: 12px; color: #666;">B</label>
                <input type="number" id="rgba-b" min="0" max="255" style="
                  width: 100%;
                  padding: 8px;
                  border: 1px solid #ddd;
                  border-radius: 4px;
                  font-size: 12px;
                  text-align: center;
                  box-sizing: border-box;
                " value="246">
              </div>
              <div>
                <label style="font-size: 12px; color: #666;">A</label>
                <input type="number" id="rgba-a" min="0" max="1" step="0.1" style="
                  width: 100%;
                  padding: 8px;
                  border: 1px solid #ddd;
                  border-radius: 4px;
                  font-size: 12px;
                  text-align: center;
                  box-sizing: border-box;
                " value="1">
              </div>
            </div>
          </div>
          
          <!-- Quick Colors Section -->
          <div style="margin-bottom: 24px;">
            <label style="
              display: block;
              margin-bottom: 8px;
              font-weight: 500;
              color: #333;
              font-size: 14px;
            ">Quick Colors</label>
            <div style="
              display: grid;
              grid-template-columns: repeat(8, 1fr);
              gap: 8px;
            ">
              ${['#ff0000', '#ff8000', '#ffff00', '#80ff00', '#00ff00', '#00ff80', '#00ffff', '#0080ff', '#0000ff', '#8000ff', '#ff00ff', '#ff0080', '#ff4000', '#ffbf00', '#80ff40', '#40ff80', '#00ff40', '#00ffbf', '#00bfff', '#0040ff', '#4000ff', '#bf00ff', '#ff40bf', '#ff0040'].map(color => `
                <div class="quick-color" data-color="${color}" style="
                  width: 32px;
                  height: 32px;
                  background: ${color};
                  border-radius: 4px;
                  cursor: pointer;
                  border: 2px solid transparent;
                  transition: all 0.2s;
                  box-shadow: 0 1px 4px rgba(0,0,0,0.1);
                " title="${color}"></div>
              `).join('')}
            </div>
          </div>
          
          <!-- Action Buttons -->
          <div style="
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            border-top: 1px solid #e9ecef;
            padding-top: 20px;
            margin-top: 20px;
          ">
            <button id="cancel-color" style="
              padding: 10px 20px;
              border: 1px solid #ddd;
              background: white;
              color: #333;
              border-radius: 6px;
              cursor: pointer;
              font-size: 14px;
              transition: all 0.2s;
            ">Cancel</button>
            <button id="apply-color" style="
              padding: 10px 20px;
              background: #3b82f6;
              color: white;
              border: none;
              border-radius: 6px;
              cursor: pointer;
              font-size: 14px;
              font-weight: 500;
              transition: all 0.2s;
            ">Apply Color</button>
          </div>
        </div>
      </div>
    `;
    
    
    
    // Append modal to DOM first
    document.body.appendChild(modal);
    
    // Now add event listeners after modal is in DOM
    const colorPicker = document.getElementById('color-picker');
    const hexInput = document.getElementById('hex-input');
    const rgbaR = document.getElementById('rgba-r');
    const rgbaG = document.getElementById('rgba-g');
    const rgbaB = document.getElementById('rgba-b');
    const rgbaA = document.getElementById('rgba-a');
    const colorPreview = document.getElementById('color-preview');
    
    // Color picker change event
    if (colorPicker) {
      colorPicker.addEventListener('input', (e) => {
        const color = e.target.value;
        hexInput.value = color;
        colorPreview.textContent = color;
        colorPreview.style.background = color;
        colorPreview.style.color = this.getContrastColor(color);
        
        // Update RGBA values
        const rgb = this.hexToRgb(color);
        if (rgb) {
          rgbaR.value = rgb.r;
          rgbaG.value = rgb.g;
          rgbaB.value = rgb.b;
        }
      });
    }
    
    // Hex input change event
    if (hexInput) {
      hexInput.addEventListener('input', (e) => {
        let color = e.target.value;
        if (!color.startsWith('#')) {
          color = '#' + color;
        }
        if (this.isValidHex(color)) {
          colorPicker.value = color;
          colorPreview.textContent = color;
          colorPreview.style.background = color;
          colorPreview.style.color = this.getContrastColor(color);
          
          // Update RGBA values
          const rgb = this.hexToRgb(color);
          if (rgb) {
            rgbaR.value = rgb.r;
            rgbaG.value = rgb.g;
            rgbaB.value = rgb.b;
          }
        }
      });
    }
    
    // RGBA input change events
    [rgbaR, rgbaG, rgbaB, rgbaA].forEach(input => {
      if (input) {
        input.addEventListener('input', () => {
          const r = parseInt(rgbaR.value) || 0;
          const g = parseInt(rgbaG.value) || 0;
          const b = parseInt(rgbaB.value) || 0;
          const a = parseFloat(rgbaA.value) || 1;
          
          const hex = this.rgbToHex(r, g, b);
          const rgba = `rgba(${r}, ${g}, ${b}, ${a})`;
          
          hexInput.value = hex;
          colorPicker.value = hex;
          colorPreview.textContent = a < 1 ? rgba : hex;
          colorPreview.style.background = rgba;
          colorPreview.style.color = this.getContrastColor(hex);
        });
      }
    });
    
    // Quick color clicks
    modal.addEventListener('click', (e) => {
      if (e.target.classList.contains('quick-color')) {
        const color = e.target.dataset.color;
        colorPicker.value = color;
        hexInput.value = color;
        colorPreview.textContent = color;
        colorPreview.style.background = color;
        colorPreview.style.color = this.getContrastColor(color);
        
        // Update RGBA values
        const rgb = this.hexToRgb(color);
        if (rgb) {
          rgbaR.value = rgb.r;
          rgbaG.value = rgb.g;
          rgbaB.value = rgb.b;
        }
      }
    });
    
    // Button event listeners
    const cancelBtn = document.getElementById('cancel-color');
    const applyBtn = document.getElementById('apply-color');
    
    if (cancelBtn) {
      cancelBtn.addEventListener('click', () => {
        this.closeColorModal();
      });
    }
    
    if (applyBtn) {
      applyBtn.addEventListener('click', () => {
        
        const color = hexInput.value;
        
        
        if (this.isValidHex(color)) {
          
          this.applyColor(colorType, color);
          this.closeColorModal();
        } else {
          // Try RGBA
          const r = parseInt(rgbaR.value) || 0;
          const g = parseInt(rgbaG.value) || 0;
          const b = parseInt(rgbaB.value) || 0;
          const a = parseFloat(rgbaA.value) || 1;
          const rgba = `rgba(${r}, ${g}, ${b}, ${a})`;
          
          this.applyColor(colorType, rgba);
          this.closeColorModal();
        }
      });
    }
    
    // Close modal on background click
    modal.addEventListener('click', (e) => {
      if (e.target === modal || e.target.id === 'close-color-modal') {
        this.closeColorModal();
      }
    });
    
    // Add hover effects for quick colors
    modal.querySelectorAll('.quick-color').forEach(color => {
      color.addEventListener('mouseenter', () => {
        color.style.transform = 'scale(1.2)';
        color.style.boxShadow = '0 4px 16px rgba(0,0,0,0.3)';
        color.style.borderColor = '#3b82f6';
      });
      
      color.addEventListener('mouseleave', () => {
        color.style.transform = 'scale(1)';
        color.style.boxShadow = '0 1px 4px rgba(0,0,0,0.1)';
        color.style.borderColor = 'transparent';
      });
    });
    
    
    
    // Add CSS animation
    const style = document.createElement('style');
    style.textContent = `
      @keyframes slideIn {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
      }
      @keyframes slideOut {
        from { opacity: 1; transform: scale(1); }
        to { opacity: 0; transform: scale(0.9); }
      }
    `;
    document.head.appendChild(style);
    
    
  },
  
  // Close color modal
  closeColorModal() {
    const modal = document.getElementById('color-modal');
    if (modal) {
      modal.style.animation = 'slideOut 0.2s ease';
      setTimeout(() => modal.remove(), 200);
    }
  },
  

  

  
  // Apply color to selection
  applyColor(colorType, color) {
    
    
    // Ensure content is focused
    this.content.focus();
    
    if (color === 'default') {
      // Remove color styling
      if (colorType === 'foreColor') {
        const success = document.execCommand('removeFormat', false, null);
        
      } else if (colorType === 'hiliteColor') {
        const success = document.execCommand('hiliteColor', false, 'transparent');
        
      }
    } else {
      // Handle both hex colors and color names
      let hexColor;
      if (color.startsWith('#')) {
        // Direct hex color
        hexColor = color;
      } else if (color.startsWith('rgba')) {
        // RGBA color - convert to hex for execCommand
        hexColor = this.rgbaToHex(color);
      } else {
        // Color name - use color map
        hexColor = this.getColorHex(color);
      }
      
      
      
      // Use the simple approach: just wrap selected text in a span
      
      this.applyColorSimple(colorType, hexColor);
    }
    
    // Clear saved selection
    this.clearSavedSelection();
  },
  
  // Get hex color value
  getColorHex(colorName) {
    const colorMap = {
      'red': '#dc3545',
      'orange': '#fd7e14',
      'yellow': '#ffc107',
      'green': '#28a745',
      'blue': '#007bff',
      'purple': '#6f42c1',
      'pink': '#e83e8c',
      'brown': '#795548',
      'gray': '#6c757d',
      'black': '#000000',
      'white': '#ffffff',
      'light-gray': '#f8f9fa'
    };
    return colorMap[colorName] || '#333333';
  },
  
  // Simple color application - just wrap selected text in a span
  applyColorSimple(colorType, hexColor) {
    
    
    if (!this.savedSelection) {
      
      return;
    }
    
    // Clone the saved range to avoid modifying the original
    const range = this.savedSelection.cloneRange();
    const selectedText = range.toString();
    
    if (!selectedText.trim()) {
      
      return;
    }
    
    
    
    // Create a span element with the color
    const span = document.createElement('span');
    
    if (colorType === 'foreColor') {
      span.style.color = hexColor;
      
    } else if (colorType === 'hiliteColor') {
      span.style.backgroundColor = hexColor;
      
      
      // Ensure text is readable
      const rgb = this.hexToRgb(hexColor);
      if (rgb) {
        const brightness = (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000;
        span.style.color = brightness > 128 ? '#000' : '#fff';
      }
    }
    
    // Put the selected text inside the span
    span.textContent = selectedText;
    
    // CRITICAL: Be more precise about span insertion
    // Instead of using range.deleteContents(), we'll manually split the text
    
    const startContainer = range.startContainer;
    const endContainer = range.endContainer;
    const startOffset = range.startOffset;
    const endOffset = range.endOffset;
    
    
    if (startContainer === endContainer && startContainer.nodeType === Node.TEXT_NODE) {
      // Same text node - split it precisely
      const textNode = startContainer;
      const beforeText = textNode.textContent.substring(0, startOffset);
      const afterText = textNode.textContent.substring(endOffset);
      
      // Create new text nodes
      const beforeNode = document.createTextNode(beforeText);
      const afterNode = document.createTextNode(afterText);
      
      // Replace the text node with: before + colored span + after
      textNode.parentNode.insertBefore(beforeNode, textNode);
      textNode.parentNode.insertBefore(span, textNode);
      textNode.parentNode.insertBefore(afterNode, textNode);
      textNode.remove();
      
      
    } else {
      // Different nodes or complex selection - use fallback method
      range.deleteContents();
      range.insertNode(span);
      
    }
    
    // CRITICAL: Move cursor after the span to prevent new text from being captured
    const newRange = document.createRange();
    newRange.setStartAfter(span);
    newRange.setEndAfter(span);
    
    // Set the selection to this new position
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(newRange);
    
    // Ensure the content area is focused
    this.content.focus();
    
    
    
    
  },

  // Isolated color application method - doesn't affect global selection
  applyColorIsolated(colorType, hexColor) {
    
    
    if (!this.savedSelection) {
      
      return;
    }
    
    // Clone the saved range to avoid modifying the original
    const range = this.savedSelection.cloneRange();
    
    // Create a span element to wrap only the selected text
    const span = document.createElement('span');
    
    if (colorType === 'foreColor') {
      span.style.color = hexColor;
      
    } else if (colorType === 'hiliteColor') {
      span.style.backgroundColor = hexColor;
      
      
      // Ensure text is readable by setting appropriate text color
      const rgb = this.hexToRgb(hexColor);
      if (rgb) {
        const brightness = (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000;
        span.style.color = brightness > 128 ? '#000' : '#fff';
      }
    }
    
    try {
      // Extract the selected text content
      const selectedText = range.toString();
      if (!selectedText.trim()) {
        
        return;
      }
      
      
      
      // Create a text node with the selected content
      const textNode = document.createTextNode(selectedText);
      span.appendChild(textNode);
      
      // Replace the selected content with our colored span
      range.deleteContents();
      range.insertNode(span);
      
      
      
    } catch (e) {
      
    }
  },

  // Fallback color application method (kept for compatibility)
  applyColorFallback(colorType, color, hexColor) {
    
    
    // Use saved selection if available, otherwise current selection
    let range;
    if (this.savedSelection) {
      range = this.savedSelection.cloneRange();
      
    } else {
      const selection = window.getSelection();
      if (selection.rangeCount > 0) {
        range = selection.getRangeAt(0);
      } else {
        
        return;
      }
    }
    
    // Create a span element to wrap only the selected text
    const span = document.createElement('span');
    
    if (colorType === 'foreColor') {
      span.style.color = hexColor;
      
    } else if (colorType === 'hiliteColor') {
      span.style.backgroundColor = hexColor;
      
      
      // Ensure text is readable by setting appropriate text color
      const rgb = this.hexToRgb(hexColor);
      if (rgb) {
        const brightness = (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000;
        span.style.color = brightness > 128 ? '#000' : '#fff';
      }
    }
    
    try {
      
      // Extract the selected text content
      const selectedText = range.toString();
      if (!selectedText.trim()) {
        
        return;
      }
      
      // Create a text node with the selected content
      const textNode = document.createTextNode(selectedText);
      span.appendChild(textNode);
      
      // Replace the selected content with our colored span
      range.deleteContents();
      range.insertNode(span);
      
      // Clear the selection to prevent it from affecting new text
      const selection = window.getSelection();
      selection.removeAllRanges();
      
      
      
    } catch (e) {
      
    }
  },
  
  // Insert table
  insertTable() {
    // Selection should already be saved by executeCommand, but ensure it's saved
    if (!this.savedSelection) {
      this.saveSelection();
    }
    
    // Create professional table modal
    const modal = document.createElement('div');
    modal.id = 'table-modal';
    modal.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    `;
    
    modal.innerHTML = `
      <div style="
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        max-width: 400px;
        width: 90vw;
        overflow: hidden;
        animation: slideIn 0.3s ease;
      ">
        <div style="
          padding: 24px;
          border-bottom: 1px solid #e9ecef;
          display: flex;
          justify-content: space-between;
          align-items: center;
        ">
          <h2 style="margin: 0; color: #333; font-size: 20px; font-weight: 600;">Insert Table</h2>
          <button id="close-table-modal" style="
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            color: #666;
            transition: all 0.2s;
          ">✕</button>
        </div>
        <div style="padding: 24px;">
          <div style="margin-bottom: 20px;">
            <label style="
              display: block;
              margin-bottom: 8px;
              font-weight: 500;
              color: #333;
              font-size: 14px;
            ">Number of Rows</label>
            <input type="number" id="table-rows" min="2" max="10" value="3" style="
              width: 100%;
              padding: 12px;
              border: 1px solid #ddd;
              border-radius: 6px;
              font-size: 14px;
              box-sizing: border-box;
              transition: border-color 0.2s;
            ">
          </div>
          <div style="margin-bottom: 20px;">
            <label style="
              display: block;
              margin-bottom: 8px;
              font-weight: 500;
              color: #333;
              font-size: 14px;
            ">Number of Columns</label>
            <input type="number" id="table-cols" min="2" max="10" value="3" style="
              width: 100%;
              padding: 12px;
              border: 1px solid #ddd;
              border-radius: 6px;
              font-size: 14px;
              box-sizing: border-box;
              transition: border-color 0.2s;
            ">
          </div>
          <div style="margin-bottom: 24px;">
            <label style="
              display: flex;
              align-items: center;
              cursor: pointer;
              font-weight: 500;
              color: #333;
              font-size: 14px;
            ">
              <input type="checkbox" id="table-header" checked style="
                margin-right: 8px;
                width: 16px;
                height: 16px;
              ">
              Include Header Row
            </label>
          </div>
          <div style="
            display: flex;
            gap: 12px;
            justify-content: flex-end;
          ">
            <button id="cancel-table" style="
              padding: 12px 24px;
              border: 1px solid #ddd;
              background: white;
              color: #333;
              border-radius: 6px;
              cursor: pointer;
              font-size: 14px;
              transition: all 0.2s;
            ">Cancel</button>
            <button id="insert-table" style="
              padding: 12px 24px;
              border: none;
              background: #007cba;
              color: white;
              border-radius: 6px;
              cursor: pointer;
              font-size: 14px;
              font-weight: 500;
              transition: all 0.2s;
            ">Insert Table</button>
          </div>
        </div>
      </div>
    `;
    
    // Add event listeners
    modal.addEventListener('click', (e) => {
      if (e.target === modal || e.target.id === 'close-table-modal' || e.target.id === 'cancel-table') {
        this.closeTableModal();
      } else if (e.target.id === 'insert-table') {
        this.insertTableFromModal();
      }
    });
    
    // Add focus styles
    const inputs = modal.querySelectorAll('input');
    inputs.forEach(input => {
      input.addEventListener('focus', () => {
        input.style.borderColor = '#007cba';
        input.style.boxShadow = '0 0 0 3px rgba(0, 123, 186, 0.1)';
      });
      
      input.addEventListener('blur', () => {
        input.style.borderColor = '#ddd';
        input.style.boxShadow = 'none';
      });
    });
    
    // Add button hover effects
    const buttons = modal.querySelectorAll('button');
    buttons.forEach(button => {
      if (button.id === 'insert-table') {
        button.addEventListener('mouseenter', () => {
          button.style.background = '#005a8b';
        });
        button.addEventListener('mouseleave', () => {
          button.style.background = '#007cba';
        });
      } else if (button.id === 'cancel-table') {
        button.addEventListener('mouseenter', () => {
          button.style.background = '#f8f9fa';
        });
        button.addEventListener('mouseleave', () => {
          button.style.background = 'white';
        });
      }
    });
    
    document.body.appendChild(modal);
    
    // Focus rows input
    setTimeout(() => {
      const rowsInput = document.getElementById('table-rows');
      if (rowsInput) {
        rowsInput.focus();
        rowsInput.select();
      }
    }, 100);
  },
  
  // Insert table from modal
  insertTableFromModal() {
    const rows = parseInt(document.getElementById('table-rows').value) || 3;
    const cols = parseInt(document.getElementById('table-cols').value) || 3;
    const hasHeader = document.getElementById('table-header').checked;
    
    const rowCount = Math.min(Math.max(rows, 2), 10);
    const colCount = Math.min(Math.max(cols, 2), 10);
    
    
    
    const table = document.createElement('table');
    table.className = 'advanced-table';
    
    // Create header row if requested
    if (hasHeader) {
      const thead = document.createElement('thead');
      const headerRow = document.createElement('tr');
      for (let i = 0; i < colCount; i++) {
        const th = document.createElement('th');
        th.textContent = `Header ${i + 1}`;
        th.contentEditable = true;
        headerRow.appendChild(th);
      }
      thead.appendChild(headerRow);
      table.appendChild(thead);
    }
    
    // Create body rows
    const tbody = document.createElement('tbody');
    const bodyRows = hasHeader ? rowCount - 1 : rowCount;
    for (let i = 0; i < bodyRows; i++) {
      const row = document.createElement('tr');
      for (let j = 0; j < colCount; j++) {
        const td = document.createElement('td');
        td.textContent = `Cell ${i + 1}-${j + 1}`;
        td.contentEditable = true;
        row.appendChild(td);
      }
      tbody.appendChild(row);
    }
    table.appendChild(tbody);
    
    // Insert at cursor position
    
    
    // Restore the saved selection if available
    if (this.savedSelection) {
      this.restoreSelection();
    }
    
    // Ensure content is focused
    this.content.focus();
    
    try {
      const success = document.execCommand('insertHTML', false, table.outerHTML);
      
      if (!success) {
        
        this.insertTableFallback(table.outerHTML);
      }
    } catch (e) {
      
      this.insertTableFallback(table.outerHTML);
    }
    
    // Clear saved selection
    this.clearSavedSelection();
    
    this.closeTableModal();
  },
  
  // Close table modal
  closeTableModal() {
    const modal = document.getElementById('table-modal');
    if (modal) {
      modal.style.animation = 'slideOut 0.2s ease';
      setTimeout(() => modal.remove(), 200);
    }
  },
  

  
  // Fallback table insertion method
  insertTableFallback(tableHtml) {
    
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      const tableElement = document.createElement('div');
      tableElement.innerHTML = tableHtml;
      range.insertNode(tableElement.firstChild);
      selection.removeAllRanges();
      selection.addRange(range);
    } else {
      // No selection, insert at cursor
      this.content.innerHTML += tableHtml;
    }
  },
  
    // Insert embed
  insertEmbed() {
    // Selection should already be saved by executeCommand, but ensure it's saved
    if (!this.savedSelection) {
      this.saveSelection();
    }
    
    // Create professional embed modal with multiple sources
    const modal = document.createElement('div');
    modal.id = 'embed-modal';
    modal.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    `;
    
    modal.innerHTML = `
      <div style="
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        max-width: 700px;
        width: 90vw;
        max-height: 90vh;
        overflow: hidden;
        animation: slideIn 0.3s ease;
      ">
        <div style="
          background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          color: white;
          padding: 20px;
          text-align: center;
        ">
          <h2 style="margin: 0; font-size: 24px; font-weight: 600;">Insert Embed</h2>
          <p style="margin: 8px 0 0 0; opacity: 0.9;">Add rich content from popular platforms</p>
        </div>
        
        <div style="padding: 24px;">
          <!-- Quick Source Buttons -->
          <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 12px; font-weight: 600; color: #333;">Quick Sources</label>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 8px;">
              <button class="source-btn" data-source="twitter" style="
                padding: 8px 12px;
                border: 2px solid #e1e5e9;
                background: white;
                color: #333;
                border-radius: 6px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
              ">🐦 Twitter</button>
              <button class="source-btn" data-source="youtube" style="
                padding: 8px 12px;
                border: 2px solid #e1e5e9;
                background: white;
                color: #333;
                border-radius: 6px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
              ">📺 YouTube</button>
              <button class="source-btn" data-source="instagram" style="
                padding: 8px 12px;
                border: 2px solid #e1e5e9;
                background: white;
                color: #333;
                border-radius: 6px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
              ">📷 Instagram</button>
              <button class="source-btn" data-source="facebook" style="
                padding: 8px 12px;
                border: 2px solid #e1e5e9;
                background: white;
                color: #333;
                border-radius: 6px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
              ">📘 Facebook</button>
              <button class="source-btn" data-source="linkedin" style="
                padding: 8px 12px;
                border: 2px solid #e1e5e9;
                background: white;
                color: #333;
                border-radius: 6px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
              ">💼 LinkedIn</button>
              <button class="source-btn" data-source="tiktok" style="
                padding: 8px 12px;
                border: 2px solid #e1e5e9;
                background: white;
                color: #333;
                border-radius: 6px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
              ">🎵 TikTok</button>
              <button class="source-btn" data-source="vimeo" style="
                padding: 8px 12px;
                border: 2px solid #e1e5e9;
                background: white;
                color: #333;
                border-radius: 6px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
              ">🎬 Vimeo</button>
              <button class="source-btn" data-source="spotify" style="
                padding: 8px 12px;
                border: 2px solid #e1e5e9;
                background: white;
                color: #333;
                border-radius: 6px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
              ">🎵 Spotify</button>
              <button class="source-btn" data-source="github" style="
                padding: 8px 12px;
                border: 2px solid #e1e5e9;
                background: white;
                color: #333;
                border-radius: 6px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 4px;
              ">💻 GitHub</button>
            </div>
          </div>
          
          <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">URL or Embed Code</label>
            <textarea id="embed-url" placeholder="Paste URL or embed code here... (or click a source above for examples)" style="
              width: 100%;
              height: 80px;
              padding: 12px;
              border: 2px solid #e1e5e9;
              border-radius: 8px;
              font-family: inherit;
              font-size: 14px;
              resize: vertical;
              box-sizing: border-box;
            "></textarea>
          </div>
          
          <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Preview</label>
            <div id="embed-preview" style="
              min-height: 100px;
              border: 2px dashed #e1e5e9;
              border-radius: 8px;
              display: flex;
              align-items: center;
              justify-content: center;
              background: #f8f9fa;
              color: #6c757d;
            ">
              <div style="text-align: center;">
                <div style="font-size: 24px; margin-bottom: 8px;">📺</div>
                <div>Preview will appear here</div>
              </div>
            </div>
          </div>
        </div>
        
        <div style="
          background: #f8f9fa;
          padding: 20px;
          display: flex;
          gap: 12px;
          justify-content: flex-end;
        ">
          <button id="embed-cancel" style="
            padding: 10px 20px;
            border: 2px solid #e1e5e9;
            background: white;
            color: #6c757d;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
          ">Cancel</button>
          <button id="embed-insert" style="
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
          ">Insert Embed</button>
        </div>
      </div>
    `;
    
    // Add keyframes for animation
    const style = document.createElement('style');
    style.textContent = `
      @keyframes slideIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
      }
    `;
    document.head.appendChild(style);
    
    // Add modal to page
    document.body.appendChild(modal);
    
    // Attach event listeners
    const urlInput = modal.querySelector('#embed-url');
    const previewDiv = modal.querySelector('#embed-preview');
    const cancelBtn = modal.querySelector('#embed-cancel');
    const insertBtn = modal.querySelector('#embed-insert');
    const sourceBtns = modal.querySelectorAll('.source-btn');
    
    // Source button clicks
    sourceBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const source = btn.dataset.source;
        this.handleSourceButtonClick(source, urlInput, previewDiv);
      });
    });
    
    // Auto-preview as user types
    urlInput.addEventListener('input', () => {
      this.updateEmbedPreview(urlInput.value, previewDiv);
    });
    
    // Cancel button
    cancelBtn.addEventListener('click', () => {
      this.closeEmbedModal();
    });
    
    // Insert button
    insertBtn.addEventListener('click', () => {
      this.insertEmbedFromModal();
    });
    
    // Close on outside click
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        this.closeEmbedModal();
      }
    });
    
    // Focus URL input
    urlInput.focus();
  },

  // Handle source button clicks (Twitter, YouTube, etc.)
  handleSourceButtonClick(source, urlInput, previewDiv) {
    
    
    // Define example URLs and embed codes for each source
    const sourceExamples = {
      twitter: {
        url: 'https://twitter.com/username/status/1234567890123456789',
        embed: '<blockquote class="twitter-tweet"><a href="https://twitter.com/username/status/1234567890123456789"></a></blockquote><script async src="https://platform.twitter.com/widgets.js"></script>',
        preview: '🐦 Twitter Post Preview'
      },
      youtube: {
        url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        embed: '<iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allowfullscreen></iframe>',
        preview: '📺 YouTube Video Preview'
      },
      instagram: {
        url: 'https://www.instagram.com/p/ABC123/',
        embed: '<blockquote class="instagram-media" data-instgrm-permalink="https://www.instagram.com/p/ABC123/"></blockquote><script async src="//www.instagram.com/embed.js"></script>',
        preview: '📷 Instagram Post Preview'
      },
      facebook: {
        url: 'https://www.facebook.com/username/posts/123456789012345',
        embed: '<div id="fb-root"></div><script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v18.0"></script><div class="fb-post" data-href="https://www.facebook.com/username/posts/123456789012345"></div>',
        preview: '📘 Facebook Post Preview'
      },
      linkedin: {
        url: 'https://www.linkedin.com/posts/username_activity-1234567890123456789',
        embed: '<iframe src="https://www.linkedin.com/embed/feed/update/urn:li:activity:1234567890123456789" height="400" width="550" frameborder="0" allowfullscreen></iframe>',
        preview: '💼 LinkedIn Post Preview'
      },
      tiktok: {
        url: 'https://www.tiktok.com/@username/video/1234567890123456789',
        embed: '<blockquote class="tiktok-embed" cite="https://www.tiktok.com/@username/video/1234567890123456789"></blockquote><script async src="https://www.tiktok.com/embed.js"></script>',
        preview: '🎵 TikTok Video Preview'
      },
      vimeo: {
        url: 'https://vimeo.com/123456789',
        embed: '<iframe src="https://player.vimeo.com/video/123456789" width="640" height="360" frameborder="0" allowfullscreen></iframe>',
        preview: '🎬 Vimeo Video Preview'
      },
      spotify: {
        url: 'https://open.spotify.com/track/1234567890abcdef',
        embed: '<iframe style="border-radius:12px" src="https://open.spotify.com/embed/track/1234567890abcdef" width="100%" height="352" frameborder="0" allowfullscreen></iframe>',
        preview: '🎵 Spotify Track Preview'
      },
      github: {
        url: 'https://github.com/username/repository',
        embed: '<iframe src="https://ghbtns.com/github-btn.html?user=username&repo=repository&type=star&count=true" frameborder="0" scrolling="0" width="150" height="20"></iframe>',
        preview: '💻 GitHub Repository Preview'
      }
    };
    
    const example = sourceExamples[source];
    if (example) {
      // Update URL input with example
      urlInput.value = example.url;
      
      // Update preview
      this.updateEmbedPreview(example.url, previewDiv);
      
      // Show success message
      previewDiv.innerHTML = `
        <div style="text-align: center; color: #059669;">
          <div style="font-size: 24px; margin-bottom: 8px;">✅</div>
          <div style="font-weight: 600; margin-bottom: 4px;">${example.preview}</div>
          <div style="font-size: 12px; opacity: 0.8;">Example URL loaded</div>
        </div>
      `;
      
      
    }
  },

  // Update embed preview based on URL
  updateEmbedPreview(url, previewDiv) {
    if (!url.trim()) {
      previewDiv.innerHTML = `
        <div style="text-align: center;">
          <div style="font-size: 24px; margin-bottom: 8px;">📺</div>
          <div>Preview will appear here</div>
        </div>
      `;
      return;
    }
    
    // Detect platform and show appropriate preview
    if (url.includes('youtube.com') || url.includes('youtu.be')) {
      previewDiv.innerHTML = `
        <div style="text-align: center; color: #dc2626;">
          <div style="font-size: 24px; margin-bottom: 8px;">📺</div>
          <div style="font-weight: 600; margin-bottom: 4px;">YouTube Video</div>
          <div style="font-size: 12px; opacity: 0.8;">Will embed as responsive video player</div>
        </div>
      `;
    } else if (url.includes('twitter.com') || url.includes('x.com')) {
      previewDiv.innerHTML = `
        <div style="text-align: center; color: #1da1f2;">
          <div style="font-size: 24px; margin-bottom: 8px;">🐦</div>
          <div style="font-weight: 600; margin-bottom: 4px;">Twitter Post</div>
          <div style="font-size: 12px; opacity: 0.8;">Will embed as interactive tweet</div>
        </div>
      `;
    } else if (url.includes('instagram.com')) {
      previewDiv.innerHTML = `
        <div style="text-align: center; color: #e4405f;">
          <div style="font-size: 24px; margin-bottom: 8px;">📷</div>
          <div style="font-weight: 600; margin-bottom: 4px;">Instagram Post</div>
          <div style="font-size: 12px; opacity: 0.8;">Will embed as interactive post</div>
        </div>
      `;
    } else if (url.includes('facebook.com')) {
      previewDiv.innerHTML = `
        <div style="text-align: center; color: #1877f2;">
          <div style="font-size: 24px; margin-bottom: 8px;">📘</div>
          <div style="font-weight: 600; margin-bottom: 4px;">Facebook Post</div>
          <div style="font-size: 12px; opacity: 0.8;">Will embed as interactive post</div>
        </div>
      `;
    } else if (url.includes('linkedin.com')) {
      previewDiv.innerHTML = `
        <div style="text-align: center; color: #0a66c2;">
          <div style="font-size: 24px; margin-bottom: 8px;">💼</div>
          <div style="font-weight: 600; margin-bottom: 4px;">LinkedIn Post</div>
          <div style="font-size: 12px; opacity: 0.8;">Will embed as interactive post</div>
        </div>
      `;
    } else if (url.includes('tiktok.com')) {
      previewDiv.innerHTML = `
        <div style="text-align: center; color: #000000;">
          <div style="font-size: 24px; margin-bottom: 8px;">🎵</div>
          <div style="font-weight: 600; margin-bottom: 4px;">TikTok Video</div>
          <div style="font-size: 12px; opacity: 0.8;">Will embed as interactive video</div>
        </div>
      `;
    } else if (url.includes('vimeo.com')) {
      previewDiv.innerHTML = `
        <div style="text-align: center; color: #1ab7ea;">
          <div style="font-size: 24px; margin-bottom: 8px;">🎬</div>
          <div style="font-weight: 600; margin-bottom: 4px;">Vimeo Video</div>
          <div style="font-size: 12px; opacity: 0.8;">Will embed as responsive video player</div>
        </div>
      `;
    } else if (url.includes('spotify.com')) {
      previewDiv.innerHTML = `
        <div style="text-align: center; color: #1db954;">
          <div style="font-size: 24px; margin-bottom: 8px;">🎵</div>
          <div style="font-weight: 600; margin-bottom: 4px;">Spotify Track</div>
          <div style="font-size: 12px; opacity: 0.8;">Will embed as interactive player</div>
        </div>
      `;
    } else if (url.includes('github.com')) {
      previewDiv.innerHTML = `
        <div style="text-align: center; color: #333333;">
          <div style="font-size: 24px; margin-bottom: 8px;">💻</div>
          <div style="font-weight: 600; margin-bottom: 4px;">GitHub Repository</div>
          <div style="font-size: 12px; opacity: 0.8;">Will embed as repository widget</div>
        </div>
      `;
    } else {
      previewDiv.innerHTML = `
        <div style="text-align: center; color: #6b7280;">
          <div style="font-size: 24px; margin-bottom: 8px;">🔗</div>
          <div style="font-weight: 600; margin-bottom: 4px;">Custom URL</div>
          <div style="font-size: 12px; opacity: 0.8;">Will attempt to embed as iframe</div>
        </div>
      `;
    }
  },

  // Insert embed from modal
  insertEmbedFromModal() {
    const url = document.getElementById('embed-url').value;
    if (!url.trim()) {
      alert('Please enter a URL or embed code');
      return;
    }
    
    
    
    // Generate appropriate embed HTML based on URL
    let embedHtml = this.generateEmbedHtml(url);
    
    if (embedHtml) {
      
      
      // Restore the saved selection if available
      if (this.savedSelection) {
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(this.savedSelection);
      }
      
      // Ensure content is focused
      this.content.focus();
      
      try {
        const success = document.execCommand('insertHTML', false, embedHtml);
        
        if (success) {
          // execCommand succeeded, find the inserted embed and fetch metadata
          setTimeout(() => {
            this.findAndUpdateEmbedMetadata(url);
          }, 100);
        } else {
          
          this.insertEmbedFallback(embedHtml);
        }
      } catch (e) {
        
        this.insertEmbedFallback(embedHtml);
      }
      
      // Clear saved selection
      this.clearSavedSelection();
      
      this.closeEmbedModal();
    } else {
      alert('Could not generate embed for this URL. Please check the URL format.');
    }
  },

  // Generate appropriate embed HTML based on URL
  generateEmbedHtml(url) {
    
    
    // YouTube
    if (url.includes('youtube.com') || url.includes('youtu.be')) {
      const videoId = this.extractYouTubeId(url);
      if (videoId) {
        
        return `<div class="embed-container youtube-embed" style="
          position: relative; 
          padding-bottom: 56.25%; 
          height: 0; 
          overflow: hidden; 
          max-width: 100%; 
          border-radius: 8px; 
          margin: 10px 0;
          border: 1px solid #e1e5e9;
          background: #000;
        ">
          <iframe style="
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            border-radius: 8px;
          " 
            src="https://www.youtube.com/embed/${videoId}?rel=0&modestbranding=1&showinfo=0" 
            frameborder="0" 
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
            allowfullscreen>
          </iframe>
        </div>`;
      }
    }
    
    // Vimeo
    if (url.includes('vimeo.com')) {
      const videoId = this.extractVimeoId(url);
      if (videoId) {
        
        return `<div class="embed-container vimeo-embed" style="
          position: relative; 
          padding-bottom: 56.25%; 
          height: 0; 
          overflow: hidden; 
          max-width: 100%; 
          border-radius: 8px;
          margin: 10px 0;
          border: 1px solid #e1e5e9;
          background: #000;
        ">
          <iframe style="
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            border-radius: 8px;
          " 
            src="https://player.vimeo.com/video/${videoId}?title=0&byline=0&portrait=0&color=ffffff" 
            frameborder="0" 
            allow="autoplay; fullscreen; picture-in-picture" 
            allowfullscreen>
          </iframe>
        </div>`;
      }
    }
    
    // Twitter/X
    if (url.includes('twitter.com') || url.includes('x.com')) {
      
      
      // Extract tweet ID for better embedding
      const tweetId = this.extractTweetId(url);
      if (tweetId) {
        
        
        // Use Twitter's embed URL directly - this should work without external scripts
        const embedUrl = `https://platform.twitter.com/embed/Tweet.html?id=${tweetId}`;
        
        return `<div class="embed-container twitter-embed" style="max-width: 550px; margin: 10px auto; border: 1px solid #e1e8ed; border-radius: 8px; overflow: hidden;">
          <iframe 
            src="${embedUrl}"
            width="550" 
            height="400"
            frameborder="0"
            scrolling="no"
            allowtransparency="true"
            style="border: none; max-width: 100%; height: auto; min-height: 400px;">
          </iframe>
        </div>`;
      } else {
        // Fallback for URLs without tweet ID
        return `<div class="embed-container twitter-embed" style="max-width: 550px; margin: 10px auto; border: 1px solid #e1e8ed; border-radius: 8px; padding: 20px; text-align: center; background: #f7f9fa;">
          <p style="margin: 0; color: #657786;">Twitter embed not available for this URL</p>
          <a href="${url}" target="_blank" style="color: #1da1f2; text-decoration: none;">View on Twitter</a>
        </div>`;
      }
    }
    
    // Instagram
    if (url.includes('instagram.com')) {
      
      return `<div class="embed-container instagram-embed" style="
        max-width: 550px; 
        margin: 10px auto;
        border: 1px solid #e1e5e9;
        border-radius: 8px;
        padding: 20px;
        background: white;
        text-align: center;
      ">
        <div style="margin-bottom: 15px;">
          <div style="font-size: 24px; margin-bottom: 8px;">📷</div>
          <div style="font-weight: 600; color: #e4405f;">Instagram Post</div>
          <div style="font-size: 12px; color: #6c757d; margin-top: 4px;">Loading post...</div>
        </div>
        
        <blockquote class="instagram-media" 
          data-instgrm-permalink="${url}" 
          data-instgrm-version="14"
          data-instgrm-captioned>
        </blockquote>
        
        <script>
          // Load Instagram script if not already loaded
          if (!window.instgrm) {
            var script = document.createElement('script');
            script.src = '//www.instagram.com/embed.js';
            script.async = true;
            document.head.appendChild(script);
          }
        </script>
      </div>`;
    }
    
    // Facebook
    if (url.includes('facebook.com')) {
      
      return `<div class="embed-container facebook-embed" style="max-width: 550px; margin: 10px auto;">
        <div id="fb-root"></div>
        <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v18.0"></script>
        <div class="fb-post" data-href="${url}" data-width="550" data-show-text="true"></div>
      </div>`;
    }
    
    // LinkedIn
    if (url.includes('linkedin.com')) {
      const postId = this.extractLinkedInId(url);
      if (postId) {
        
        return `<div class="embed-container linkedin-embed" style="max-width: 550px; margin: 10px auto;">
          <iframe src="https://www.linkedin.com/embed/feed/update/urn:li:activity:${postId}" 
            height="400" width="550" frameborder="0" allowfullscreen></iframe>
        </div>`;
      }
    }
    
    // TikTok
    if (url.includes('tiktok.com')) {
      const videoId = this.extractTikTokId(url);
      if (videoId) {
        
        return `<div class="embed-container tiktok-embed" style="max-width: 325px; margin: 10px auto;">
          <blockquote class="tiktok-embed" cite="${url}" data-video-id="${videoId}">
          </blockquote>
          <script async src="https://www.tiktok.com/embed.js"></script>
        </div>`;
      }
    }
    
    // Spotify
    if (url.includes('spotify.com')) {
      
      const embedUrl = this.convertSpotifyUrl(url);
      return `<div class="embed-container spotify-embed" style="max-width: 300px; margin: 10px auto;">
        <iframe style="border-radius:12px" src="${embedUrl}" 
          width="100%" height="352" frameborder="0" allowfullscreen></iframe>
      </div>`;
    }
    
    // GitHub
    if (url.includes('github.com')) {
      const user = this.extractGitHubUser(url);
      const repo = this.extractGitHubRepo(url);
      if (user && repo) {
        
        return `<div class="embed-container github-embed" style="text-align: center; padding: 20px; border: 1px solid #e1e5e9; border-radius: 8px; background: #f8f9fa;">
          <h3 style="margin: 0 0 10px 0; color: #333;">GitHub Repository</h3>
          <p style="margin: 0 0 15px 0; color: #666;">${user}/${repo}</p>
          <iframe src="https://ghbtns.com/github-btn.html?user=${user}&repo=${repo}&type=star&count=true&size=large" 
            frameborder="0" scrolling="0" width="170" height="30"></iframe>
          <div style="margin-top: 15px;">
            <a href="${url}" target="_blank" style="
              display: inline-block;
              padding: 8px 16px;
              background: #24292e;
              color: white;
              text-decoration: none;
              border-radius: 6px;
              font-weight: 500;
            ">View on GitHub</a>
          </div>
        </div>`;
      }
    }
    
    // General website embed with meta tags
    
    return this.createGeneralWebsiteEmbed(url);
  },

  // Create general website embed for unknown URLs
  createGeneralWebsiteEmbed(url) {
    // Extract domain from URL for display
    let domain = '';
    try {
      const urlObj = new URL(url);
      domain = urlObj.hostname;
    } catch (e) {
      domain = url.replace(/^https?:\/\//, '').split('/')[0];
    }
    
    const embedId = 'website-embed-' + Date.now();
    
    return `<div class="embed-container website-embed" id="${embedId}" style="
      border: 1px solid #e1e5e9;
      border-radius: 8px;
      padding: 16px;
      background: white;
      max-width: 100%;
      margin: 10px 0;
      overflow: hidden;
      cursor: pointer;
      transition: all 0.2s ease;
    " onmouseover="this.style.borderColor='#1a73e8'; this.style.boxShadow='0 2px 8px rgba(26,115,184,0.2)'" onmouseout="this.style.borderColor='#e1e5e9'; this.style.boxShadow='none'" onclick="window.open('${url}', '_blank')">
      <div style="display: flex; align-items: flex-start; gap: 12px;">
        <div style="
          width: 60px;
          height: 60px;
          background: #f8f9fa;
          border-radius: 8px;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 24px;
          color: #6c757d;
          flex-shrink: 0;
          overflow: hidden;
        " id="image-${embedId}">🔗</div>
        <div style="flex: 1; min-width: 0;">
          <div style="
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            line-height: 1.3;
            word-break: break-all;
          " id="title-${embedId}">${domain}</div>
          <div style="
            font-size: 14px;
            color: #666;
            line-height: 1.4;
            margin-bottom: 8px;
          " id="desc-${embedId}">Loading website information...</div>
          <div style="
            font-size: 12px;
            color: #999;
            line-height: 1.3;
          ">${url}</div>
        </div>
      </div>
    </div>`;
  },
  
  // Extract YouTube video ID
  extractYouTubeId(url) {
    const regex = /(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/)|youtu\.be\/)([^&\n?#]+)/;
    const match = url.match(regex);
    return match ? match[1] : null;
  },
  
  // Extract Twitter tweet ID
  extractTweetId(url) {
    const regex = /(?:twitter\.com|x\.com)\/[^\/]+\/status\/(\d+)/;
    const match = url.match(regex);
    return match ? match[1] : null;
  },
  
  // Extract Vimeo video ID
  extractVimeoId(url) {
    const regex = /vimeo\.com\/(\d+)/;
    const match = url.match(regex);
    return match ? match[1] : null;
  },

  // Extract LinkedIn post ID
  extractLinkedInId(url) {
    const regex = /linkedin\.com\/posts\/[^\/]+\/activity-(\d+)/;
    const match = url.match(regex);
    return match ? match[1] : null;
  },

  // Extract GitHub username and repository
  extractGitHubUser(url) {
    const regex = /github\.com\/([^\/]+)\//;
    const match = url.match(regex);
    return match ? match[1] : null;
  },

  extractGitHubRepo(url) {
    const regex = /github\.com\/[^\/]+\/([^\/\?#]+)/;
    const match = url.match(regex);
    return match ? match[1] : null;
  },

  // Extract TikTok video ID
  extractTikTokId(url) {
    const regex = /tiktok\.com\/@[^\/]+\/video\/(\d+)/;
    const match = url.match(regex);
    return match ? match[1] : null;
  },

  // Convert Spotify URL to embed format
  convertSpotifyUrl(url) {
    // Convert various Spotify URL formats to embed format
    if (url.includes('/track/')) {
      return url.replace('open.spotify.com', 'open.spotify.com/embed');
    } else if (url.includes('/album/')) {
      return url.replace('open.spotify.com', 'open.spotify.com/embed');
    } else if (url.includes('/playlist/')) {
      return url.replace('open.spotify.com', 'open.spotify.com/embed');
    }
    return url;
  },
  
  // Close embed modal
  closeEmbedModal() {
    const modal = document.getElementById('embed-modal');
    if (modal) {
      modal.remove();
    }
  },

  // Insert embed fallback method
  insertEmbedFallback(embedHtml) {
    
    
    // Create a temporary container
    const temp = document.createElement('div');
    temp.innerHTML = embedHtml;
    const embedElement = temp.firstElementChild;
    
    // Insert at cursor position using range
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      range.deleteContents();
      range.insertNode(embedElement);
      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
      
    } else {
      // Fallback: append to content
      this.content.appendChild(embedElement);
      
    }
    
    // After embed is inserted, fetch metadata if it's a website embed
    this.fetchEmbedMetadata(embedElement);
  },
  
  // Fetch metadata for website embeds
  fetchEmbedMetadata(embedElement) {
    // Check if this is a website embed
    if (!embedElement || !embedElement.classList.contains('website-embed')) {
      return;
    }
    
    const embedId = embedElement.id;
    if (!embedId) {
      
      return;
    }
    
    
    
    // Find the URL from the embed
    const urlElement = embedElement.querySelector('[style*="color: #999"]');
    if (!urlElement) {
      
      return;
    }
    
    const url = urlElement.textContent.trim();
    if (!url) {
      
      return;
    }
    
    
    
    // Fetch metadata from our PHP endpoint
              const metadataUrl = '/api/fetch-metadata.php?url=' + encodeURIComponent(url);
    
    
    fetch(metadataUrl)
      .then(response => {
        
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      })
      .then(data => {
        
        
        if (data.success && data.data) {
          // Update title if available
          const titleElement = embedElement.querySelector('#title-' + embedId);
          if (titleElement && data.data.title) {
            titleElement.textContent = data.data.title;
            
          }
          
          // Update description if available
          const descElement = embedElement.querySelector('#desc-' + embedId);
          if (descElement && data.data.description) {
            descElement.textContent = data.data.description;
            
          }
          
          // Update image if available
          const imageElement = embedElement.querySelector('#image-' + embedId);
          if (imageElement && data.data.image) {
            imageElement.innerHTML = '<img src="' + data.data.image + '" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.parentElement.innerHTML=\'🔗\'">';
            
          } else if (imageElement && data.data.favicon) {
            imageElement.innerHTML = '<img src="' + data.data.favicon + '" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.parentElement.innerHTML=\'🔗\'">';
            
          }
          
          // Update favicon in URL section if available
          const faviconElement = embedElement.querySelector('#favicon-' + embedId);
          if (faviconElement && data.data.favicon) {
            faviconElement.innerHTML = '<img src="' + data.data.favicon + '" style="width: 100%; height: 100%; object-fit: cover; border-radius: 3px;" onerror="this.parentElement.innerHTML=\'🔗\'">';
            
          }
          
          
        } else {
          throw new Error(data.error || 'Unknown error');
        }
      })
      .catch(error => {
        // Metadata fetch failed - continue without metadata
        
        // Simple fallback - just show favicon
        const imageElement = embedElement.querySelector('#image-' + embedId);
        if (imageElement) {
          const faviconUrl = 'https://www.google.com/s2/favicons?domain=' + encodeURIComponent(url) + '&sz=64';
          imageElement.innerHTML = '<img src="' + faviconUrl + '" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.parentElement.innerHTML=\'🔗\'">';
          
        }
        
        // Also update favicon in URL section
        const faviconElement = embedElement.querySelector('#favicon-' + embedId);
        if (faviconElement) {
          const faviconUrl = 'https://www.google.com/s2/favicons?domain=' + encodeURIComponent(url) + '&sz=16';
          faviconElement.innerHTML = '<img src="' + faviconUrl + '" style="width: 100%; height: 100%; object-fit: cover; border-radius: 3px;" onerror="this.parentElement.innerHTML=\'🔗\'">';
          
        }
        
        const descElement = embedElement.querySelector('#desc-' + embedId);
        if (descElement) {
          descElement.textContent = 'Website link';
          
        }
      });
  },
  
  // Find and update metadata for embeds inserted via execCommand
  findAndUpdateEmbedMetadata(url) {
    
    
    // Find all website embeds in the content
    const embeds = this.content.querySelectorAll('.website-embed');
    if (embeds.length === 0) {
      
      return;
    }
    
    // Get the most recently inserted embed (last one)
    const latestEmbed = embeds[embeds.length - 1];
    
    
    // Fetch metadata for this embed
    this.fetchEmbedMetadata(latestEmbed);
  },
  
  // Handle paste events with smart URL detection
  handlePaste(e) {
    e.preventDefault();
    
    try {
      // Get plain text from clipboard
      const text = e.clipboardData.getData('text/plain');
      
      
      
      // Check if we're pasting into a code block
      const selection = window.getSelection();
      
      
      if (selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        
        
        
        
        const codeBlock = this.getCurrentCodeBlock(range.startContainer);
        
        
        if (codeBlock) {
          
          
          
          // Insert text directly into code block without URL detection
          const codeElement = codeBlock.querySelector('code') || codeBlock.querySelector('code[contenteditable]');
          
          
          
          if (codeElement) {
            // Get current cursor position
            const selection = window.getSelection();
            
            
            if (selection.rangeCount > 0) {
              const range = selection.getRangeAt(0);
              
              // Delete any selected content first
              range.deleteContents();
              
              // Insert the text as a text node
              const textNode = document.createTextNode(text);
              
              
              range.insertNode(textNode);
              
              
              // Move cursor to end of inserted text
              range.setStartAfter(textNode);
              range.setEndAfter(textNode);
              selection.removeAllRanges();
              selection.addRange(range);
              
              
              
              // Trigger syntax highlighting after paste with longer delay to ensure DOM update
              setTimeout(() => {
                this.rehighlightCode(codeElement);
              }, 50);
              
              // Primary method succeeded, no need for fallback
              
              return;
            } else {
              
            }
          } else {
            
          }
          
          // Fallback: try direct text insertion (only if primary method failed)
          
          try {
            const codeElement = codeBlock.querySelector('code') || codeBlock.querySelector('code[contenteditable]');
            
            
            if (codeElement) {
              // Simple approach: append text to existing content
              const currentText = codeElement.textContent || '';
              codeElement.textContent = currentText + text;
              
              
              // Trigger syntax highlighting
              setTimeout(() => {
                this.rehighlightCode(codeElement);
              }, 10);
            } else {
              
            }
          } catch (e) {
            // Fallback text insertion failed - allow default behavior
          }
          
          return;
        }
      }
      
      // Check if pasted content is a URL
      if (text && this.isValidUrl(text)) {
        
        this.showPasteMenu(text, e);
      } else {
        // Regular text paste
        
        document.execCommand('insertText', false, text);
        
        // Fix paragraph spacing after paste
        setTimeout(() => {
          this.fixParagraphSpacing();
        }, 0);
      }
    } catch (error) {
      // Paste error occurred - allow default paste behavior
      // Fallback: allow default paste behavior
      e.stopPropagation();
      return true;
    }
  },

  // Check if text is a valid URL
  isValidUrl(text) {
    if (!text || typeof text !== 'string') return false;
    
    // Simple URL validation - more permissive
    const urlPattern = /^https?:\/\/.+/i;
    return urlPattern.test(text.trim());
  },

  // Show paste menu for URLs
  showPasteMenu(url, pasteEvent) {
    // Get cursor position for menu placement
    const selection = window.getSelection();
    if (selection.rangeCount === 0) return;
    
    const range = selection.getRangeAt(0);
    const rect = range.getBoundingClientRect();
    
    // Get the content area position for proper positioning
    const contentRect = this.content.getBoundingClientRect();
    
    // Calculate menu position relative to the content area
    const menuTop = rect.bottom - contentRect.top + 10;
    const menuLeft = rect.left - contentRect.left;
    
    // Ensure menu stays within content area bounds
    const menuWidth = 220; // Approximate menu width
    const contentWidth = contentRect.width;
    const contentHeight = contentRect.height;
    
    let finalTop = menuTop;
    let finalLeft = menuLeft;
    
    // Adjust if menu would go off the right edge
    if (menuLeft + menuWidth > contentWidth) {
      finalLeft = contentWidth - menuWidth - 20;
    }
    
    // Adjust if menu would go off the bottom edge
    if (menuTop + 200 > contentHeight) { // Approximate menu height
      finalTop = rect.top - contentRect.top - 220; // Show above cursor instead
    }
    
    // Ensure menu doesn't go off the left edge
    if (finalLeft < 0) {
      finalLeft = 10;
    }
    
    // Ensure menu doesn't go off the top edge
    if (finalTop < 0) {
      finalTop = 10;
    }
    
    // Create floating menu
    const menu = document.createElement('div');
    menu.id = 'paste-menu';
    menu.style.cssText = `
      position: absolute;
      top: ${finalTop}px;
      left: ${finalLeft}px;
      background: white;
      border: 1px solid #e1e5e9;
      border-radius: 8px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
      padding: 8px;
      z-index: 10001;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      font-size: 14px;
      min-width: 200px;
      animation: slideInUp 0.2s ease;
    `;
    
    // Truncate long URLs for display
    const displayUrl = url.length > 40 ? url.substring(0, 37) + '...' : url;
    
    menu.innerHTML = `
      <div style="
        padding: 8px 12px;
        border-bottom: 1px solid #f1f3f4;
        margin-bottom: 8px;
        color: #5f6368;
        font-size: 12px;
        font-weight: 500;
      ">Paste URL</div>
      
      <div style="
        padding: 8px 12px;
        color: #1a73e8;
        cursor: pointer;
        border-radius: 4px;
        transition: background-color 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
      " data-action="link">
        🔗 <span>Paste as Link</span>
      </div>
      
      <div style="
        padding: 8px 12px;
        color: #1a73e8;
        cursor: pointer;
        border-radius: 4px;
        transition: background-color 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
      " data-action="embed">
        📺 <span>Embed Content</span>
      </div>
      
      <div style="
        padding: 8px 12px;
        color: #5f6368;
        cursor: pointer;
        border-radius: 4px;
        transition: background-color 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
      " data-action="text">
        📝 <span>Paste as Plain Text</span>
      </div>
      
      <div style="
        padding: 8px 12px;
        color: #5f6368;
        cursor: pointer;
        border-radius: 4px;
        transition: background-color 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
      " data-action="cancel">
        ❌ <span>Cancel</span>
      </div>
    `;
    
    // Add keyframes for animation
    const style = document.createElement('style');
    style.textContent = `
      @keyframes slideInUp {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
      }
    `;
    document.head.appendChild(style);
    
    // Add menu to content area for proper positioning
    this.content.appendChild(menu);
    
    // Position menu near cursor
    const cursorSelection = window.getSelection();
    if (cursorSelection.rangeCount > 0) {
      const cursorRange = cursorSelection.getRangeAt(0);
      const cursorRect = cursorRange.getBoundingClientRect();
      
      // Position menu below the cursor
      menu.style.position = 'absolute';
      menu.style.top = (cursorRect.bottom + 10) + 'px';
      menu.style.left = cursorRect.left + 'px';
    } else {
      // Fallback positioning
      menu.style.position = 'absolute';
      menu.style.top = '10px';
      menu.style.left = '10px';
    }
    
    // Add hover effects
    const menuItems = menu.querySelectorAll('[data-action]');
    menuItems.forEach(item => {
      item.addEventListener('mouseenter', () => {
        if (item.dataset.action !== 'cancel') {
          item.style.backgroundColor = '#f8f9fa';
        }
      });
      
      item.addEventListener('mouseleave', () => {
        item.style.backgroundColor = 'transparent';
      });
      
      // Handle clicks
      item.addEventListener('click', () => {
        this.handlePasteMenuAction(item.dataset.action, url);
        this.hidePasteMenu();
      });
    });
    
    // Handle keyboard navigation
    let selectedIndex = 0;
    const updateMenuSelection = () => {
      menuItems.forEach((item, index) => {
        if (index === selectedIndex) {
          item.style.backgroundColor = '#e8f0fe';
          item.style.color = '#1a73e8';
        } else {
          item.style.backgroundColor = 'transparent';
          item.style.color = item.dataset.action === 'cancel' ? '#5f6368' : '#1a73e8';
        }
      });
    };
    
    updateMenuSelection();
    
    // Keyboard event handler
    const handleKeydown = (e) => {
      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault();
          selectedIndex = (selectedIndex + 1) % menuItems.length;
          updateMenuSelection();
          break;
        case 'ArrowUp':
          e.preventDefault();
          selectedIndex = selectedIndex === 0 ? menuItems.length - 1 : selectedIndex - 1;
          updateMenuSelection();
          break;
        case 'Enter':
          e.preventDefault();
          this.handlePasteMenuAction(menuItems[selectedIndex].dataset.action, url);
          this.hidePasteMenu();
          break;
        case 'Escape':
          e.preventDefault();
          this.hidePasteMenu();
          break;
      }
    };
    
    document.addEventListener('keydown', handleKeydown);
    
    // Store cleanup function
    this.pasteMenuCleanup = () => {
      document.removeEventListener('keydown', handleKeydown);
    };
    
    // Auto-hide after 5 seconds
    this.pasteMenuTimeout = setTimeout(() => {
      this.hidePasteMenu();
    }, 5000);
    
    // Hide on outside click
    setTimeout(() => {
      document.addEventListener('click', (e) => {
        if (!menu.contains(e.target)) {
          this.hidePasteMenu();
        }
      }, 0);
    }, 0);
  },

  // Handle paste menu actions
  handlePasteMenuAction(action, url) {
    
    
    switch (action) {
      case 'link':
        // Create a link with the URL as text
        this.createLinkFromUrl(url);
        break;
      case 'embed':
        // Open embed modal with URL pre-filled
        this.openEmbedModalWithUrl(url);
        break;
      case 'text':
        // Paste as plain text
        document.execCommand('insertText', false, url);
        break;
      case 'cancel':
        // Do nothing
        break;
    }
  },

  // Create link from URL
  createLinkFromUrl(url) {
    // Get current selection
    const selection = window.getSelection();
    if (selection.rangeCount === 0) return;
    
    const range = selection.getRangeAt(0);
    
    // If text is selected, turn it into a link
    if (selection.toString().length > 0) {
      document.execCommand('createLink', false, url);
    } else {
      // If no text selected, insert URL as link text
      const linkHtml = `<a href="${url}" target="_blank">${url}</a>`;
      document.execCommand('insertHTML', false, linkHtml);
    }
  },



  // Open embed modal with URL pre-filled
  openEmbedModalWithUrl(url) {
    // Store the URL to pre-fill the embed modal
    this.pendingEmbedUrl = url;
    
    // Open the embed modal
    this.insertEmbed();
    
    // Pre-fill the URL field
    setTimeout(() => {
      const urlInput = document.getElementById('embed-url');
      if (urlInput) {
        urlInput.value = url;
        // Trigger preview update
        const previewDiv = document.getElementById('embed-preview');
        if (previewDiv) {
          this.updateEmbedPreview(url, previewDiv);
        }
      }
    }, 100);
  },

  // Hide paste menu
  hidePasteMenu() {
    const menu = document.getElementById('paste-menu');
    if (menu) {
      menu.remove();
    }
    
    // Clear timeout
    if (this.pasteMenuTimeout) {
      clearTimeout(this.pasteMenuTimeout);
      this.pasteMenuTimeout = null;
    }
    
    // Clean up event listeners
    if (this.pasteMenuCleanup) {
      this.pasteMenuCleanup();
      this.pasteMenuCleanup = null;
    }
    
    // Clear any stored references
    this.pasteMenuTimeout = null;
    this.pasteMenuCleanup = null;
  },

  // Color utility methods
  hexToRgb(hex) {
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
      r: parseInt(result[1], 16),
      g: parseInt(result[2], 16),
      b: parseInt(result[3], 16)
    } : null;
  },

  rgbToHex(r, g, b) {
    return '#' + [r, g, b].map(x => {
      const hex = x.toString(16);
      return hex.length === 1 ? '0' + hex : hex;
    }).join('');
  },

  isValidHex(hex) {
    return /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(hex);
  },

  getContrastColor(hex) {
    const rgb = this.hexToRgb(hex);
    if (!rgb) return '#000';
    
    const brightness = (rgb.r * 299 + rgb.g * 587 + rgb.b * 114) / 1000;
    return brightness > 128 ? '#000' : '#fff';
  },

  rgbaToHex(rgba) {
    // Parse rgba(r, g, b, a) format
    const match = rgba.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\)/);
    if (match) {
      const r = parseInt(match[1]);
      const g = parseInt(match[2]);
      const b = parseInt(match[3]);
      return this.rgbToHex(r, g, b);
    }
    return '#000000';
  },
  
  // Handle keyboard shortcuts with proper paragraph behavior
  handleKeydown(e) {
    // Handle slash commands (/) - Notion-like
    if (e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey) {
      const selection = window.getSelection();
      if (selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        const textBeforeCursor = this.getTextBeforeCursor(range);
        
        // Only show slash menu if at start of block or after space/newline
        if (textBeforeCursor.endsWith('/') || textBeforeCursor.match(/\s\/$/)) {
          e.preventDefault();
          this.showSlashMenu(range);
          return;
        }
      }
    }
    
    // Handle Escape to close menus
    if (e.key === 'Escape') {
      this.hideSlashMenu();
    }
    
    // Handle Enter key for proper paragraph creation
    if (e.key === 'Enter') {
      // Execute selected slash command if menu is open
      if (this.slashMenuVisible && this.slashMenu) {
        e.preventDefault();
        const items = this.slashMenu.querySelectorAll('.slash-menu-item');
        if (items.length > 0 && this.slashMenuSelectedIndex >= 0) {
          const selectedItem = items[this.slashMenuSelectedIndex];
          if (selectedItem) {
            const action = selectedItem.dataset.action;
            const range = selection.getRangeAt(0);
            this.executeSlashCommand(action, range);
          }
        }
        return;
      }
      
      e.preventDefault();
      
      if (e.shiftKey) {
        // Shift+Enter: Insert line break within current paragraph
        this.insertLineBreak();
      } else {
        // Enter: Create new paragraph (resets all formatting)
        this.createNewParagraph();
      }
      return;
    }
    
    // Handle Tab key for nested lists
    if (e.key === 'Tab') {
      e.preventDefault();
      
      if (e.shiftKey) {
        // Shift+Tab: Outdent list item
        this.outdentListItem();
      } else {
        // Tab: Indent list item (create sub-list)
        this.indentListItem();
      }
      return;
    }
    
    // Ctrl+B / Cmd+B for bold
    if ((e.ctrlKey || e.metaKey) && e.key === 'b' && !e.shiftKey && !e.altKey) {
      e.preventDefault();
      this.saveSelection();
      document.execCommand('bold', false, null);
      this.updateToolbarState();
      return;
    }
    
    // Ctrl+I / Cmd+I for italic
    if ((e.ctrlKey || e.metaKey) && e.key === 'i' && !e.shiftKey && !e.altKey) {
      e.preventDefault();
      this.saveSelection();
      document.execCommand('italic', false, null);
      this.updateToolbarState();
      return;
    }
    
    // Ctrl+U / Cmd+U for underline
    if ((e.ctrlKey || e.metaKey) && e.key === 'u' && !e.shiftKey && !e.altKey) {
      e.preventDefault();
      this.saveSelection();
      document.execCommand('underline', false, null);
      this.updateToolbarState();
      return;
    }
    
    // Handle arrow keys in slash menu
    if (this.slashMenuVisible && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
      e.preventDefault();
      this.navigateSlashMenu(e.key === 'ArrowDown' ? 1 : -1);
      return;
    }
  },

  // Create new paragraph (Enter key)
  createNewParagraph() {
    
    
    const selection = window.getSelection();
    if (selection.rangeCount === 0) return;
    
    const range = selection.getRangeAt(0);
    const currentBlock = this.getCurrentBlock(range.startContainer);
    
    
    
    
    if (currentBlock) {
      // Check if we're inside a list
      const listItem = this.getCurrentListItem(range.startContainer);
      
      
      if (listItem) {
        // We're inside a list - create new list item
        this.createNewListItem(listItem);
      } else {
        // Check if we're inside a quote
        const quote = this.getCurrentQuote(range.startContainer);
        
        
        if (quote) {
          // We're inside a quote - create new paragraph within quote
          this.createNewQuoteParagraph(quote, range);
        } else {
          // Check if we're inside a code block
          const codeBlock = this.getCurrentCodeBlock(range.startContainer);
          
          
          if (codeBlock) {
            // We're inside a code block - create new line within code
            this.createNewLineWithinCode(codeBlock, range);
          } else {
            // We're not in a list, quote, or code block - create new paragraph
            this.createNewParagraphElement(currentBlock, range);
          }
        }
      }
    }
  },

  // Create new list item when Enter is pressed inside a list
  createNewListItem(currentListItem) {
    
    
    // Check if current list item is empty (just <br> or empty)
    const isCurrentItemEmpty = this.isListItemEmpty(currentListItem);
    
    if (isCurrentItemEmpty) {
      // Current item is empty - exit the list and create new paragraph
      this.exitListAndCreateParagraph(currentListItem);
    } else {
      // Current item has content - create new list item
      this.createNewListItemElement(currentListItem);
    }
  },

  // Check if a list item is empty (just <br> or no content)
  isListItemEmpty(listItem) {
    const content = listItem.textContent.trim();
    const hasOnlyBr = listItem.innerHTML === '<br>' || listItem.innerHTML === '<br/>';
    return content === '' || hasOnlyBr;
  },

  // Exit list and create new paragraph
  exitListAndCreateParagraph(currentListItem) {
    
    
    const list = currentListItem.parentNode; // UL or OL
    const newParagraph = document.createElement('p');
    newParagraph.style.cssText = 'margin: 0 !important; padding: 0 !important; margin-top: 0.25em !important; margin-bottom: 0 !important;';
    newParagraph.innerHTML = '<br>';
    
    // Insert new paragraph after the list
    list.parentNode.insertBefore(newParagraph, list.nextSibling);
    
    // Remove the empty list item
    currentListItem.remove();
    
    // If list is now empty, remove the entire list
    if (list.children.length === 0) {
      list.remove();
    }
    
    // Move cursor to new paragraph
    const newRange = document.createRange();
    newRange.setStart(newParagraph, 0);
    newRange.collapse(true);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(newRange);
    
    
  },

  // Create new list item element (when current item has content)
  createNewListItemElement(currentListItem) {
    
    
    const list = currentListItem.parentNode; // UL or OL
    const newListItem = document.createElement('li');
    newListItem.innerHTML = '<br>';
    
    // Insert new list item after current one
    list.insertBefore(newListItem, currentListItem.nextSibling);
    
    // Move cursor to new list item
    const newRange = document.createRange();
    newRange.setStart(newListItem, 0);
    newRange.collapse(true);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(newRange);
    
    
  },

  // Create new paragraph element (when not in a list)
  createNewParagraphElement(currentBlock, range) {
    // Get text after cursor
    const clonedRange = range.cloneRange();
    clonedRange.setEndAfter(currentBlock);
    const afterContent = clonedRange.extractContents();
    
    // Create new paragraph with inline styles to prevent spacing
    const newParagraph = document.createElement('p');
    newParagraph.style.cssText = 'margin: 0 !important; padding: 0 !important; margin-top: 0.25em !important; margin-bottom: 0 !important;';
    
    // If there's content after cursor, move it to new paragraph
    if (afterContent.textContent.trim()) {
      while (afterContent.firstChild) {
        newParagraph.appendChild(afterContent.firstChild);
      }
    } else {
      newParagraph.innerHTML = '<br>';
    }
    
    // Ensure current block has inline styles
    const existingStyle = currentBlock.style.cssText || '';
    currentBlock.style.cssText = existingStyle + 'margin: 0 !important; padding: 0 !important; margin-bottom: 0 !important;';
    
    // Insert new paragraph after current block
    if (currentBlock.nextSibling) {
      currentBlock.parentNode.insertBefore(newParagraph, currentBlock.nextSibling);
    } else {
      currentBlock.parentNode.appendChild(newParagraph);
    }
    
    // Ensure current block has content (at least <br>)
    if (!currentBlock.textContent.trim()) {
      currentBlock.innerHTML = '<br>';
    }
    
    // Move cursor to start of new paragraph
    const newRange = document.createRange();
    const textNode = newParagraph.firstChild;
    if (textNode && textNode.nodeType === Node.TEXT_NODE) {
      newRange.setStart(textNode, 0);
    } else {
      newRange.setStart(newParagraph, 0);
    }
    newRange.collapse(true);
    
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(newRange);
    
    this.content.focus();
    // Fix paragraph spacing after creating new paragraph
    this.fixParagraphSpacing();
    this.saveContent();
  },

  // Create a list with proper structure (no p wrapping)
  createList(listType) {
    const selection = window.getSelection();
    if (selection.rangeCount === 0) {
      // Create selection at cursor if none exists
      const range = document.createRange();
      range.selectNodeContents(this.content);
      range.collapse(false);
      selection.removeAllRanges();
      selection.addRange(range);
    }
    
    const range = selection.getRangeAt(0);
    const currentBlock = this.getCurrentBlock(range.startContainer);
    
    if (currentBlock) {
      // If we're in a paragraph, replace it with a list
      if (currentBlock.tagName === 'P') {
        // Create the list
        const list = document.createElement(listType);
        const listItem = document.createElement('li');
        
        // Move content from paragraph to list item
        listItem.innerHTML = currentBlock.innerHTML;
        
        // Replace paragraph with list
        list.appendChild(listItem);
        currentBlock.parentNode.replaceChild(list, currentBlock);
        
        // Move cursor to list item
        const newRange = document.createRange();
        newRange.selectNodeContents(listItem);
        newRange.collapse(false);
        selection.removeAllRanges();
        selection.addRange(newRange);
        
        
      } else {
        // If not in a paragraph, create list at cursor position
        const list = document.createElement(listType);
        const listItem = document.createElement('li');
        listItem.innerHTML = '<br>';
        
        // Insert list at cursor position
        range.deleteContents();
        range.insertNode(list);
        list.appendChild(listItem);
        
        // Move cursor to list item
        const newRange = document.createRange();
        newRange.setStart(listItem, 0);
        newRange.collapse(true);
        selection.removeAllRanges();
        selection.addRange(newRange);
        
        
      }
    }
  },

  // Get the current list item if we're inside a list
  getCurrentListItem(node) {
    while (node && node !== this.content) {
      if (node.tagName === 'LI') {
        return node;
      }
      node = node.parentElement;
    }
    return null;
  },
  
  // Get the current quote if we're inside a quote
  getCurrentQuote(node) {
    while (node && node !== this.content) {
      if (node.tagName === 'BLOCKQUOTE') {
        return node;
      }
      node = node.parentElement;
    }
    return null;
  },
  
  // Create new paragraph within quote when Enter is pressed
  createNewQuoteParagraph(quote, range) {
    
    
    // Find the current block element within the quote
    const currentBlock = this.getCurrentBlockWithinQuote(range.startContainer, quote);
    
    
    if (!currentBlock) {
      // No current block found, create a new paragraph
      this.createNewParagraphWithinQuote(quote, null);
      return;
    }
    
    // Check if current block is empty (just <br> or empty)
    const isCurrentBlockEmpty = this.isBlockEmpty(currentBlock);
    
    
    if (isCurrentBlockEmpty) {
      // Current block is empty - exit the quote and create new paragraph
      this.exitQuoteAndCreateParagraph(quote, currentBlock);
    } else {
      // Current block has content - create new paragraph within quote
      this.createNewParagraphWithinQuote(quote, currentBlock);
    }
  },
  
  // Check if a paragraph is empty (just <br> or no content)
  isParagraphEmpty(paragraph) {
    const content = paragraph.textContent.trim();
    const hasOnlyBr = paragraph.innerHTML === '<br>' || paragraph.innerHTML === '<br/>';
    return content === '' || hasOnlyBr;
  },
  
  // Exit quote and create new paragraph
  exitQuoteAndCreateParagraph(quote, currentParagraph) {
    
    
    // Create new paragraph after the quote
    const newParagraph = document.createElement('p');
    newParagraph.style.cssText = 'margin: 0 !important; padding: 0 !important; margin-top: 0.25em !important; margin-bottom: 0 !important;';
    newParagraph.innerHTML = '<br>';
    
    // Insert new paragraph after the quote
    quote.parentNode.insertBefore(newParagraph, quote.nextSibling);
    
    // Remove the empty paragraph from quote
    currentParagraph.remove();
    
    // If quote is now empty, remove the entire quote
    if (quote.children.length === 0) {
      quote.remove();
    }
    
    // Move cursor to new paragraph
    const newRange = document.createRange();
    newRange.setStart(newParagraph, 0);
    newRange.collapse(true);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(newRange);
    
    
  },
  
  // Create new paragraph within quote (when current paragraph has content)
  createNewParagraphWithinQuote(quote, currentBlock) {
    
    
    const newParagraph = document.createElement('p');
    newParagraph.style.cssText = 'margin: 0 !important; padding: 0 !important; margin-top: 0.25em !important; margin-bottom: 0 !important;';
    newParagraph.innerHTML = '<br>';
    
    if (currentBlock) {
      // Insert new paragraph after current one within the quote
      quote.insertBefore(newParagraph, currentBlock.nextSibling);
    } else {
      // No current block, append to the end of the quote
      quote.appendChild(newParagraph);
    }
    
    // Move cursor to new paragraph
    const newRange = document.createRange();
    newRange.setStart(newParagraph, 0);
    newRange.collapse(true);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(newRange);
    
    
  },
  
  // Get the current block element within a quote
  getCurrentBlockWithinQuote(node, quote) {
    while (node && node !== quote && node !== this.content) {
      if (node.tagName === 'P' || node.tagName === 'DIV') {
        return node;
      }
      node = node.parentElement;
    }
    return null;
  },
  
  // Check if a block element is empty (just <br> or no content)
  isBlockEmpty(block) {
    if (!block) return true;
    const content = block.textContent.trim();
    const hasOnlyBr = block.innerHTML === '<br>' || block.innerHTML === '<br/>';
    return content === '' || hasOnlyBr;
  },
  
  // Get the current paragraph if we're inside a quote (legacy method)
  getCurrentParagraph(node) {
    while (node && node !== this.content) {
      if (node.tagName === 'P' || node.tagName === 'DIV') {
        return node;
      }
      node = node.parentElement;
    }
    return null;
  },
  
  // Get the current code block if we're inside a code block
  getCurrentCodeBlock(node) {
    
    
    
    
    let currentNode = node;
    let depth = 0;
    
    while (currentNode && currentNode !== this.content && depth < 10) {
      
      
      if (currentNode.tagName === 'PRE' && currentNode.classList.contains('advanced-code')) {
        
        return currentNode;
      }
      
      currentNode = currentNode.parentElement;
      depth++;
    }
    
    
    return null;
  },
  
  // Create new line within code block when Enter is pressed
  createNewLineWithinCode(codeBlock, range) {
    
    
    const codeElement = codeBlock.querySelector('code');
    if (!codeElement) {
      
      return;
    }
    
    // For code blocks, always insert line breaks on Enter
    // Only exit when the user explicitly deletes all content
    
    document.execCommand('insertLineBreak', false, null);
  },
  

  
  // Exit code block and create new paragraph
  exitCodeBlockAndCreateParagraph(codeBlock) {
    
    
    // Create new paragraph after the code block
    const newParagraph = document.createElement('p');
    newParagraph.style.cssText = 'margin: 0 !important; padding: 0 !important; margin-top: 0.25em !important; margin-bottom: 0 !important;';
    newParagraph.innerHTML = '<br>';
    
    // Insert new paragraph after the code block
    codeBlock.parentNode.insertBefore(newParagraph, codeBlock.nextSibling);
    
    // Remove the entire code block
    codeBlock.remove();
    
    // Move cursor to new paragraph
    const newRange = document.createRange();
    newRange.setStart(newParagraph, 0);
    newRange.collapse(true);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(newRange);
    
    
  },

  // Indent list item (create sub-list)
  indentListItem() {
    
    
    const selection = window.getSelection();
    if (selection.rangeCount === 0) return;
    
    const range = selection.getRangeAt(0);
    const currentListItem = this.getCurrentListItem(range.startContainer);
    
    if (!currentListItem) {
      
      return;
    }
    
    const list = currentListItem.parentNode; // UL or OL
    const previousListItem = currentListItem.previousElementSibling;
    
    if (previousListItem && previousListItem.tagName === 'LI') {
      // Create sub-list under previous item
      let subList = previousListItem.querySelector('ul, ol');
      
      if (!subList) {
        // Create new sub-list
        subList = document.createElement(list.tagName); // Same type as parent list
        previousListItem.appendChild(subList);
      }
      
      // Move current item to sub-list
      list.removeChild(currentListItem);
      subList.appendChild(currentListItem);
      
      
    } else {
      
    }
  },

  // Outdent list item (move to parent list)
  outdentListItem() {
    
    
    const selection = window.getSelection();
    if (selection.rangeCount === 0) return;
    
    const range = selection.getRangeAt(0);
    const currentListItem = this.getCurrentListItem(range.startContainer);
    
    if (!currentListItem) {
      
      return;
    }
    
    const currentList = currentListItem.parentNode; // Current UL/OL
    const parentListItem = currentList.parentElement;
    
    if (parentListItem && parentListItem.tagName === 'LI') {
      // Move to parent list
      const parentList = parentListItem.parentNode;
      
      // Insert after parent list item
      parentList.insertBefore(currentListItem, parentListItem.nextSibling);
      
      // Remove empty sub-list if it's now empty
      if (currentList.children.length === 0) {
        parentListItem.removeChild(currentList);
      }
      
      
    } else {
      
    }
  },

  // Insert line break within current paragraph (Shift+Enter)
  insertLineBreak() {
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      const br = document.createElement('br');
      range.deleteContents();
      range.insertNode(br);
      
      // Move cursor after the break
      range.setStartAfter(br);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);
    }
    this.saveContent();
  },

  // Get the current block element (P, H1, H2, etc.)
  getCurrentBlock(node) {
    while (node && node !== this.content) {
      if (this.isBlockElement(node)) {
        return node;
      }
      node = node.parentElement;
    }
    return null;
  },

  // Apply format block (H1-H6, P, etc.) with proper replacement
  applyFormatBlock(tagName) {
    // Restore saved selection if available
    const selection = window.getSelection();
    let range = null;
    
    if (this.savedSelection) {
      try {
        if (this.content.contains(this.savedSelection.commonAncestorContainer)) {
          selection.removeAllRanges();
          selection.addRange(this.savedSelection.cloneRange());
          range = selection.getRangeAt(0);
        }
      } catch (e) {
        // Saved selection invalid
      }
    }
    
    // If no valid selection, get current one
    if (!range) {
      if (selection.rangeCount > 0) {
        range = selection.getRangeAt(0);
        if (!this.content.contains(range.commonAncestorContainer)) {
          range = null;
        }
      }
    }
    
    // If still no selection, create one at cursor
    if (!range) {
      range = document.createRange();
      const walker = document.createTreeWalker(
        this.content,
        NodeFilter.SHOW_TEXT,
        null,
        false
      );
      
      let lastTextNode = null;
      let node;
      while (node = walker.nextNode()) {
        lastTextNode = node;
      }
      
      if (lastTextNode) {
        range.setStart(lastTextNode, lastTextNode.textContent.length);
        range.collapse(true);
      } else {
        range.selectNodeContents(this.content);
        range.collapse(false);
      }
      
      selection.removeAllRanges();
      selection.addRange(range);
    }
    
    // Get the current block element
    const currentBlock = this.getCurrentBlock(range.startContainer);
    
    if (currentBlock && this.isBlockElement(currentBlock)) {
      // Create new element with the desired tag
      const newElement = document.createElement(tagName);
      
      // Copy the content from current block
      newElement.innerHTML = currentBlock.innerHTML;
      
      // Copy any attributes that should be preserved
      if (currentBlock.hasAttributes()) {
        Array.from(currentBlock.attributes).forEach(attr => {
          if (attr.name !== 'class' || !attr.value.includes('advanced-')) {
            newElement.setAttribute(attr.name, attr.value);
          }
        });
      }
      
      // Replace the current block with the new element
      currentBlock.parentNode.replaceChild(newElement, currentBlock);
      
      // Move cursor to the new element
      const newRange = document.createRange();
      newRange.selectNodeContents(newElement);
      newRange.collapse(false); // Move to end
      selection.removeAllRanges();
      selection.addRange(newRange);
      
      this.content.focus();
      this.saveContent();
    } else {
      // No block found, try using execCommand as fallback
      try {
        document.execCommand('formatBlock', false, tagName);
        this.saveContent();
      } catch (e) {
        // Fallback failed
      }
    }
  },
  
  // Update toolbar button states
  updateToolbarState() {
    const buttons = this.toolbar.querySelectorAll('button');
    
    buttons.forEach(button => {
      const command = button.dataset.command;
      const value = button.dataset.value;
      
      if (command === 'formatBlock') {
        // Check if current block matches this format
        const isActive = document.queryCommandValue('formatBlock') === value;
        button.classList.toggle('active', isActive);
      } else if (command === 'insertUnorderedList' || command === 'insertOrderedList') {
        // Check if cursor is in a list
        const isInList = document.queryCommandState(command);
        button.classList.toggle('active', isInList);
      } else {
        // Check if command is active
        const isActive = document.queryCommandState(command);
        button.classList.toggle('active', isActive);
      }
    });
  },
  
  // Save content to container
  saveContent() {
    if (this.content) {
      const html = this.content.innerHTML;
      
      this.container.dataset.value = html;
      
      // Trigger change event for CMS
      const event = new CustomEvent('editor:change', {
        detail: { value: html }
      });
      this.container.dispatchEvent(event);
    }
  },


  
  // Get current content
  getValue() {
    // First try to read from this.content (the Advanced mode's managed content div)
    if (this.content && this.content.parentNode) {
      return this.content.innerHTML || '';
    }
    // Fallback: try to find Advanced content in the DOM directly
    if (this.surface) {
      var contentEl = this.surface.querySelector('.advanced-content');
      if (contentEl) return contentEl.innerHTML || '';
      // If no .advanced-content found, try reading from surface itself (if it's contenteditable)
      if (this.surface.classList.contains('ed-advanced') && this.surface.contentEditable === 'true') {
        return this.surface.innerHTML || '';
      }
    }
    // Last resort: check container
    if (this.container) {
      var advPane = this.container.querySelector('.ed-advanced');
      if (advPane) {
        var contentEl = advPane.querySelector('.advanced-content');
        if (contentEl) return contentEl.innerHTML || '';
        if (advPane.contentEditable === 'true') return advPane.innerHTML || '';
      }
    }
    return '';
  },
  
  // Set content
  setValue(html) {
    var val = html || '';
    // Set in managed content element
    if (this.content) {
      this.content.innerHTML = val;
      this.container.dataset.value = val;
    } else {
      // Fallback: find Advanced content in DOM and set it
      if (this.surface) {
        var contentEl = this.surface.querySelector('.advanced-content');
        if (contentEl) {
          contentEl.innerHTML = val;
        } else if (this.surface.classList.contains('ed-advanced') && this.surface.contentEditable === 'true') {
          this.surface.innerHTML = val;
        }
      }
      // Also try to find it via container
      if (this.container) {
        var advPane = this.container.querySelector('.ed-advanced');
        if (advPane) {
          var contentEl = advPane.querySelector('.advanced-content');
          if (contentEl) {
            contentEl.innerHTML = val;
          } else if (advPane.contentEditable === 'true') {
            advPane.innerHTML = val;
          }
        }
      }
      if (this.container) this.container.dataset.value = val;
    }
  },
  
  // Focus the editor
  focus() {
    if (this.content) {
      this.content.focus();
    }
  },
  
  // Insert quote block
  insertQuote() {
    // Selection should already be saved by executeCommand, but ensure it's saved
    if (!this.savedSelection) {
      this.saveSelection();
    }
    
    // Create quote HTML
    const quoteHtml = `
      <blockquote class="advanced-quote" style="
        margin: 10px 0;
        padding: 8px 12px;
        border-left: 3px solid #007acc;
      ">
        <p contenteditable="true"></p>
      </blockquote>
    `;
    
    // Insert at cursor position
    
    
    // Restore the saved selection if available
    if (this.savedSelection) {
      this.restoreSelection();
    }
    
    // Ensure content is focused
    this.content.focus();
    
    try {
      const success = document.execCommand('insertHTML', false, quoteHtml);
      
      if (!success) {
        
        this.insertQuoteFallback(quoteHtml);
      }
    } catch (e) {
      
      this.insertQuoteFallback(quoteHtml);
    }
    
    // Clear saved selection
    this.clearSavedSelection();
  },
  
  // Fallback quote insertion method
  insertQuoteFallback(quoteHtml) {
    
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      const quoteElement = document.createElement('div');
      quoteElement.innerHTML = quoteHtml;
      range.insertNode(quoteElement.firstChild);
      selection.removeAllRanges();
      selection.addRange(range);
    } else {
      // No selection, insert at cursor
      this.content.innerHTML += quoteHtml;
    }
  },
  
  // Insert code block
  insertCode() {
    // Selection should already be saved by executeCommand, but ensure it's saved
    if (!this.savedSelection) {
      this.saveSelection();
    }
    
    // Create code HTML with syntax highlighting
    const codeHtml = `
      <pre class="advanced-code" style="
        margin: 20px 0;
        padding: 20px;
        background: #f6f8fa;
        border: 1px solid #e1e4e8;
        border-radius: 8px;
        font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
        font-size: 14px;
        line-height: 1.5;
        color: #24292e;
        overflow-x: auto;
        position: relative;
      ">
        <code contenteditable="true" style="
          display: block;
          white-space: pre-wrap;
          word-wrap: break-word;
          text-indent: 0;
        " data-language="javascript"></code>
      </pre>
    `;
    
    // Insert at cursor position
    
    
    // Restore the saved selection if available
    if (this.savedSelection) {
      this.restoreSelection();
    }
    
    // Ensure content is focused
    this.content.focus();
    
    try {
      const success = document.execCommand('insertHTML', false, codeHtml);
      
      if (!success) {
        
        this.insertCodeFallback(codeHtml);
      }
    } catch (e) {
      
      this.insertCodeFallback(codeHtml);
    }
    
    // Clear saved selection
    this.clearSavedSelection();
    
    // Apply syntax highlighting after a short delay to ensure DOM is ready
    setTimeout(() => {
      this.applySyntaxHighlighting();
    }, 100);
  },
  
  // Fallback code insertion method
  insertCodeFallback(codeHtml) {
    
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
      const range = selection.getRangeAt(0);
      const codeElement = document.createElement('div');
      codeElement.innerHTML = codeHtml;
      range.insertNode(codeElement.firstChild);
      selection.removeAllRanges();
      selection.addRange(range);
    } else {
      // No selection, insert at cursor
      this.content.innerHTML += codeHtml;
    }
    
    // Apply syntax highlighting after fallback insertion
    setTimeout(() => {
      this.applySyntaxHighlighting();
    }, 100);
  },
  
  // Handle clicks to create new paragraphs below content blocks
  handleContentClick(e) {
    
    
    
    
    
    // Check if we clicked below a content block (quote, code, table, etc.)
    const clickedElement = e.target;
    const contentBlock = this.findContentBlockAbove(clickedElement);
    
    
    
    if (contentBlock) {
      
      
      // Check if we need to create a new paragraph below
      const nextElement = contentBlock.nextElementSibling;
      
      
      if (!nextElement || nextElement.tagName !== 'P') {
        // Create new paragraph below the content block
        const newParagraph = document.createElement('p');
        newParagraph.style.cssText = 'margin: 0 !important; padding: 0 !important; margin-top: 0.25em !important; margin-bottom: 0 !important;';
        newParagraph.innerHTML = '<br>';
        newParagraph.className = 'click-created-paragraph';
        
        // Insert after the content block
        contentBlock.parentNode.insertBefore(newParagraph, contentBlock.nextSibling);
        
        // Move cursor to the new paragraph
        const newRange = document.createRange();
        newRange.setStart(newParagraph, 0);
        newRange.collapse(true);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(newRange);
        
        // Focus the new paragraph
        newParagraph.focus();
        
        
      } else {
        
      }
    } else {
      // If no content block found, check if we clicked at the end of content
      // This handles clicking at the very bottom of the editor
      
      this.handleClickAtEndOfContent(e);
    }
  },
  
  // Handle clicks at the end of content (when no content block is found)
  handleClickAtEndOfContent(e) {
    const contentArea = this.content;
    const rect = contentArea.getBoundingClientRect();
    const clickY = e.clientY;
    const contentBottom = rect.bottom;
    
    
    
    // If click is below the content area, create a new paragraph at the end
    if (clickY >= contentBottom - 10) { // 10px tolerance
      
      
      const newParagraph = document.createElement('p');
      newParagraph.style.cssText = 'margin: 0 !important; padding: 0 !important; margin-top: 0.25em !important; margin-bottom: 0 !important;';
      newParagraph.innerHTML = '<br>';
      newParagraph.className = 'click-created-paragraph';
      
      // Append to the end of content
      contentArea.appendChild(newParagraph);
      
      // Move cursor to the new paragraph
      const newRange = document.createRange();
      newRange.setStart(newParagraph, 0);
      newRange.collapse(true);
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(newRange);
      
      // Focus the new paragraph
      newParagraph.focus();
      
      
    }
  },
  
  // Find content block above the clicked element
  findContentBlockAbove(element) {
    let current = element;
    
    // Look for content blocks going up the DOM tree
    while (current && current !== this.content) {
      if (this.isContentBlock(current)) {
        return current;
      }
      current = current.parentElement;
    }
    
    return null;
  },
  
  // Check if an element is a content block that should have paragraphs below
  isContentBlock(element) {
    const contentBlockTags = ['BLOCKQUOTE', 'PRE', 'TABLE', 'DIV'];
    const contentBlockClasses = ['advanced-quote', 'advanced-code'];
    
    return contentBlockTags.includes(element.tagName) || 
           contentBlockClasses.some(className => element.classList.contains(className));
  },
  
  // Apply syntax highlighting to code blocks
  applySyntaxHighlighting() {
    const codeBlocks = this.content.querySelectorAll('.advanced-code code');
    
    codeBlocks.forEach(codeElement => {
      if (!codeElement.dataset.highlighted) {
        this.highlightCode(codeElement);
        codeElement.dataset.highlighted = 'true';
      }
    });
  },
  
  // Highlight individual code element
  highlightCode(codeElement) {
    const text = codeElement.textContent;
    const language = codeElement.dataset.language || 'javascript';
    
    // Simple syntax highlighting for JavaScript
    if (language === 'javascript') {
      const highlighted = this.highlightJavaScript(text);
      // Store original text and apply highlighting
      codeElement.dataset.originalText = text;
      codeElement.innerHTML = highlighted;
      
      // Add input listener to re-highlight on changes
      codeElement.addEventListener('input', (e) => {
        // Debounce the highlighting to avoid performance issues
        clearTimeout(this.highlightTimeout);
        this.highlightTimeout = setTimeout(() => {
          this.rehighlightCode(codeElement);
        }, 100);
      });
    }
  },
  
  // Re-highlight code when content changes
  rehighlightCode(codeElement) {
    
    
    // Try multiple ways to get the text content
    let text = codeElement.textContent || codeElement.innerText || '';
    
    const language = codeElement.dataset.language || 'javascript';
    
    
    if (language === 'javascript') {
      // Store current cursor position
      const selection = window.getSelection();
      let cursorOffset = 0;
      if (selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        cursorOffset = range.startOffset;
      }
      
      
      // If text is empty, try to get it from the DOM structure
      if (!text.trim()) {
        
        const walker = document.createTreeWalker(
          codeElement,
          NodeFilter.SHOW_TEXT,
          null,
          false
        );
        
        let node;
        let allText = '';
        while (node = walker.nextNode()) {
          allText += node.textContent;
        }
        text = allText;
        
      }
      
      const highlighted = this.highlightJavaScript(text);
      
      
      codeElement.innerHTML = highlighted;
      
      
      // Restore cursor position after highlighting
      this.restoreCursorPositionInCode(codeElement, cursorOffset);
      
    } else {
      
    }
  },
  
  // Simple JavaScript syntax highlighting
  highlightJavaScript(code) {
    
    
    let highlighted = code
      // Keywords
      .replace(/\b(const|let|var|function|return|if|else|for|while|class|import|export|from|default)\b/g, '<span class="js-keyword">$1</span>');
    
    
    highlighted = highlighted
      // Strings
      .replace(/(["'`])((?:\\.|(?!\1)[^\\])*?)\1/g, '<span class="js-string">$1$2$1</span>');
    
    
    highlighted = highlighted
      // Numbers
      .replace(/\b(\d+(?:\.\d+)?)\b/g, '<span class="js-number">$1</span>');
    
    
    highlighted = highlighted
      // Comments
      .replace(/(\/\/.*$)/gm, '<span class="js-comment">$1</span>')
      .replace(/(\/\*[\s\S]*?\*\/)/g, '<span class="js-comment">$1</span>');
    
    
    highlighted = highlighted
      // Function calls
      .replace(/\b(\w+)(?=\s*\()/g, '<span class="js-function">$1</span>');
    
    
    highlighted = highlighted
      // Line breaks
      .replace(/\n/g, '<br>');
    
    
    
    return highlighted;
  },
  
  // Restore cursor position after highlighting
  restoreCursorPosition(codeElement) {
    // Simple cursor restoration - move to end
    const range = document.createRange();
    range.selectNodeContents(codeElement);
    range.collapse(false);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
  },
  
  // Restore cursor position in code element after highlighting
  restoreCursorPositionInCode(codeElement, offset) {
    try {
      const textContent = codeElement.textContent;
      const range = document.createRange();
      const selection = window.getSelection();
      
      // Find the text node and position within it
      let currentOffset = 0;
      let targetNode = null;
      let targetOffset = 0;
      
      const walker = document.createTreeWalker(
        codeElement,
        NodeFilter.SHOW_TEXT,
        null,
        false
      );
      
      let node;
      while (node = walker.nextNode()) {
        const nodeLength = node.textContent.length;
        if (currentOffset + nodeLength >= offset) {
          targetNode = node;
          targetOffset = offset - currentOffset;
          break;
        }
        currentOffset += nodeLength;
      }
      
      if (targetNode) {
        range.setStart(targetNode, Math.min(targetOffset, targetNode.textContent.length));
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
      } else {
        // Fallback to end of element
        this.restoreCursorPosition(codeElement);
      }
    } catch (e) {
      // Could not restore cursor position - fallback to end of element
      // Fallback to end of element
      this.restoreCursorPosition(codeElement);
    }
  },
  
  // Destroy the editor
  destroy() {
    // Clean up paste menu if it exists
    this.hidePasteMenu();
    
    if (this.toolbar) {
      this.toolbar.remove();
    }
    if (this.content) {
      this.content.remove();
    }
  },
  
  // Get text before cursor position
  getTextBeforeCursor(range) {
    const container = range.startContainer;
    if (container.nodeType === Node.TEXT_NODE) {
      return container.textContent.substring(0, range.startOffset);
    }
    return '';
  },
  
  // Show slash command menu (Notion-like)
  showSlashMenu(range) {
    // Remove any existing slash menu
    this.hideSlashMenu();
    
    // Get cursor position
    const rect = range.getBoundingClientRect();
    const contentRect = this.content.getBoundingClientRect();
    
    // Create slash menu
    const menu = document.createElement('div');
    menu.className = 'slash-menu';
    menu.style.cssText = `
      position: absolute;
      top: ${rect.top - contentRect.top + rect.height + 4}px;
      left: ${rect.left - contentRect.left}px;
      background: white;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      padding: 4px;
      z-index: 10000;
      min-width: 240px;
      max-height: 300px;
      overflow-y: auto;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    `;
    
    // Command list
    const commands = [
      { icon: '📝', label: 'Text', action: 'text', description: 'Plain text paragraph' },
      { icon: '📄', label: 'Heading 1', action: 'h1', description: 'Large heading' },
      { icon: '📄', label: 'Heading 2', action: 'h2', description: 'Medium heading' },
      { icon: '📄', label: 'Heading 3', action: 'h3', description: 'Small heading' },
      { icon: '•', label: 'Bullet List', action: 'ul', description: 'Unordered list' },
      { icon: '1.', label: 'Numbered List', action: 'ol', description: 'Ordered list' },
      { icon: '🔗', label: 'Link', action: 'link', description: 'Insert link' },
      { icon: '📊', label: 'Table', action: 'table', description: 'Insert table' },
      { icon: '📺', label: 'Embed', action: 'embed', description: 'Insert embed' },
      { icon: '💬', label: 'Quote', action: 'quote', description: 'Insert quote' },
      { icon: '💻', label: 'Code', action: 'code', description: 'Insert code block' },
    ];
    
    commands.forEach((cmd, index) => {
      const item = document.createElement('div');
      item.className = 'slash-menu-item';
      item.dataset.action = cmd.action;
      item.dataset.index = index;
      item.style.cssText = `
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 12px;
        cursor: pointer;
        border-radius: 4px;
        font-size: 14px;
        transition: background-color 0.15s;
      `;
      
      item.innerHTML = `
        <span style="font-size: 18px; width: 24px; text-align: center;">${cmd.icon}</span>
        <div style="flex: 1;">
          <div style="font-weight: 500; color: #333;">${cmd.label}</div>
          <div style="font-size: 12px; color: #666; margin-top: 2px;">${cmd.description}</div>
        </div>
      `;
      
      item.addEventListener('mouseenter', () => {
        item.style.backgroundColor = '#f0f0f0';
        this.slashMenuSelectedIndex = index;
      });
      
      item.addEventListener('mouseleave', () => {
        if (this.slashMenuSelectedIndex !== index) {
          item.style.backgroundColor = 'transparent';
        }
      });
      
      item.addEventListener('click', () => {
        this.executeSlashCommand(cmd.action, range);
      });
      
      menu.appendChild(item);
    });
    
    // Append to content area (positioned relative)
    this.content.style.position = 'relative';
    this.content.appendChild(menu);
    
    this.slashMenu = menu;
    this.slashMenuVisible = true;
    this.slashMenuSelectedIndex = 0;
    this.updateSlashMenuSelection();
    
    // Remove the "/" character
    if (range.startContainer.nodeType === Node.TEXT_NODE) {
      const textNode = range.startContainer;
      const text = textNode.textContent;
      const beforeSlash = text.substring(0, range.startOffset - 1);
      const afterSlash = text.substring(range.startOffset);
      textNode.textContent = beforeSlash + afterSlash;
      range.setStart(textNode, range.startOffset - 1);
      range.collapse(true);
    }
  },
  
  // Hide slash menu
  hideSlashMenu() {
    if (this.slashMenu) {
      this.slashMenu.remove();
      this.slashMenu = null;
      this.slashMenuVisible = false;
      this.slashMenuSelectedIndex = -1;
    }
  },
  
  // Navigate slash menu with arrow keys
  navigateSlashMenu(direction) {
    if (!this.slashMenu) return;
    
    const items = this.slashMenu.querySelectorAll('.slash-menu-item');
    if (items.length === 0) return;
    
    this.slashMenuSelectedIndex += direction;
    
    if (this.slashMenuSelectedIndex < 0) {
      this.slashMenuSelectedIndex = items.length - 1;
    } else if (this.slashMenuSelectedIndex >= items.length) {
      this.slashMenuSelectedIndex = 0;
    }
    
    this.updateSlashMenuSelection();
  },
  
  // Update slash menu selection highlight
  updateSlashMenuSelection() {
    if (!this.slashMenu) return;
    
    const items = this.slashMenu.querySelectorAll('.slash-menu-item');
    items.forEach((item, index) => {
      if (index === this.slashMenuSelectedIndex) {
        item.style.backgroundColor = '#e8f0fe';
        item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      } else {
        item.style.backgroundColor = 'transparent';
      }
    });
  },
  
  // Execute slash command
  executeSlashCommand(action, range) {
    this.hideSlashMenu();
    
    // Restore selection if range provided
    if (range) {
      const selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(range);
      this.content.focus();
    } else {
      // Get current selection if no range provided
      const selection = window.getSelection();
      if (selection.rangeCount === 0) {
        const newRange = document.createRange();
        newRange.selectNodeContents(this.content);
        newRange.collapse(false);
        selection.removeAllRanges();
        selection.addRange(newRange);
      }
      this.content.focus();
    }
    
    // Execute command
    switch (action) {
      case 'text':
        this.executeCommand('formatBlock', 'p');
        break;
      case 'h1':
      case 'h2':
      case 'h3':
        this.executeCommand('formatBlock', action);
        break;
      case 'ul':
        this.executeCommand('insertUnorderedList');
        break;
      case 'ol':
        this.executeCommand('insertOrderedList');
        break;
      case 'link':
        this.createLink();
        break;
      case 'table':
        this.insertTable();
        break;
      case 'embed':
        this.insertEmbed();
        break;
      case 'quote':
        this.insertQuote();
        break;
      case 'code':
        this.insertCode();
        break;
    }
  },
  
};
