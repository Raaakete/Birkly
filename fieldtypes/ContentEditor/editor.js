// Main ContentEditor initialization and event handling
(function() {
  'use strict';

    // Load external CSS for editor preview (scoped to editor only)
    function loadEditorCSS(editor) {
      if (window.ContentEditorPreview && typeof window.ContentEditorPreview.load === 'function') {
        window.ContentEditorPreview.load(editor);
      }
    }
  
  // Initialize all ContentEditor instances
  function initContentEditors() {
    const wrappers = document.querySelectorAll('.content-editor-wrapper');
    wrappers.forEach(wrapper => {
      // SECURITY FIX: Prevent double initialization
      if (wrapper.dataset.initialized === 'true') return;
      
      const fieldName = wrapper.dataset.fieldName;
      if (!fieldName) {
        console.warn('ContentEditor: Wrapper missing fieldName');
        return;
      }
      
      const editor = wrapper.querySelector('.content-editor-area');
      const toolbar = wrapper.querySelector('.content-editor-toolbar');
      const htmlSource = wrapper.querySelector('.content-editor-html-source');
      const hiddenInput = document.getElementById('field_' + fieldName);
      
      if (!editor || !toolbar || !hiddenInput) {
        // Log missing elements for debugging but don't break
        if (!editor) console.warn('ContentEditor: Editor element not found for field:', fieldName);
        if (!toolbar) console.warn('ContentEditor: Toolbar element not found for field:', fieldName);
        if (!hiddenInput) console.warn('ContentEditor: Hidden input not found for field:', fieldName);
        return;
      }
      
      // Mark as initialized BEFORE calling initEditor to prevent race conditions
      wrapper.dataset.initialized = 'true';
      
      try {
        initEditor(editor, toolbar, hiddenInput, fieldName, wrapper, htmlSource);
        initFullscreen(wrapper, editor, toolbar, hiddenInput, fieldName);
      } catch (e) {
        console.error('ContentEditor: Error initializing editor for field:', fieldName, e);
        // Reset initialization flag on error so it can be retried
        wrapper.dataset.initialized = 'false';
      }
    });
  }

  // Initialize a single editor instance
  function initEditor(editor, toolbar, hiddenInput, fieldName, wrapper, htmlSource) {
    // CRITICAL: Ensure editor is always a div with content-editor-area class
    if (!editor.classList.contains('content-editor-area')) {
      console.error('Editor must be a div with content-editor-area class!');
      return;
    }
    
    // Load external CSS for preview if available
    loadEditorCSS(editor);
    
    // Ensure editor has proper block structure
    if (!editor.innerHTML.trim()) {
      // Start with empty content - user will create content when typing
      editor.innerHTML = '';
    } else {
      // Normalize: ensure all direct children are block elements
      const children = Array.from(editor.childNodes);
      let needsNormalization = false;
      
      children.forEach(node => {
        // If we have text nodes or non-block elements directly in editor, wrap them
        if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
          needsNormalization = true;
        } else if (node.nodeType === Node.ELEMENT_NODE) {
          const blockTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'BLOCKQUOTE', 'PRE', 'DIV'];
          if (!blockTags.includes(node.tagName)) {
            needsNormalization = true;
          }
        }
      });
      
      if (needsNormalization) {
        // Wrap loose content in paragraphs
        const fragment = document.createDocumentFragment();
        let currentP = null;
        
        children.forEach(node => {
          if (node.nodeType === Node.TEXT_NODE) {
            if (node.textContent.trim()) {
              if (!currentP) {
                currentP = document.createElement('p');
                fragment.appendChild(currentP);
              }
              currentP.appendChild(node.cloneNode(true));
            }
          } else if (node.nodeType === Node.ELEMENT_NODE) {
            const blockTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'BLOCKQUOTE', 'PRE'];
            if (blockTags.includes(node.tagName)) {
              currentP = null; // Reset paragraph collector
              fragment.appendChild(node.cloneNode(true));
            } else {
              // Non-block element, wrap in paragraph
              if (!currentP) {
                currentP = document.createElement('p');
                fragment.appendChild(currentP);
              }
              currentP.appendChild(node.cloneNode(true));
            }
          }
        });
        
        editor.innerHTML = '';
        editor.appendChild(fragment);
      }
    }

    // Track active editor for commands (supports fullscreen instances)
    window.ContentEditorState = window.ContentEditorState || {};
    const setActiveEditor = () => {
      window.ContentEditorState.activeEditor = editor;
    };
    editor.addEventListener('mousedown', setActiveEditor);
    editor.addEventListener('mouseup', setActiveEditor);
    editor.addEventListener('focusin', setActiveEditor);
    editor.addEventListener('keyup', setActiveEditor);
    editor.addEventListener('focus', setActiveEditor, true);
    editor.addEventListener('click', setActiveEditor);
    
    // Use event manager with per-instance manager if available, otherwise fall back to legacy handler
    if (window.ContentEditorEventManager) {
      const manager = Object.create(window.ContentEditorEventManager);
      manager.init(editor, toolbar, hiddenInput, fieldName);
      editor.__contentEditorManager = manager;
    } else {
      attachLegacyEvents(editor, toolbar, hiddenInput);
    }
    
    // Update toolbar state on selection change
    editor.addEventListener('selectionchange', () => {
      if (window.ContentEditorToolbar) {
        window.ContentEditorToolbar.updateState(editor, toolbar);
      } else {
        updateToolbarState(editor, toolbar);
      }
    });
    
    editor.addEventListener('keyup', () => {
      if (window.ContentEditorToolbar) {
        window.ContentEditorToolbar.updateState(editor, toolbar);
      } else {
        updateToolbarState(editor, toolbar);
      }
    });
    
    editor.addEventListener('mouseup', () => {
      if (window.ContentEditorToolbar) {
        window.ContentEditorToolbar.updateState(editor, toolbar);
      } else {
        updateToolbarState(editor, toolbar);
      }
    });

            // Initialize table controls for existing tables
            const existingTables = editor.querySelectorAll('table.content-editor-table[data-editable-table="true"]');
            existingTables.forEach(table => {
              if (window.ContentEditorCommands && window.ContentEditorCommands.initTableControls) {
                window.ContentEditorCommands.initTableControls(table);
              }
            });
            
            // Also initialize tables that don't have the data attribute yet (for backward compatibility)
            const allTables = editor.querySelectorAll('table');
            allTables.forEach(table => {
              if (!table.hasAttribute('data-editable-table')) {
                table.className = 'content-editor-table';
                table.setAttribute('data-editable-table', 'true');
                if (window.ContentEditorCommands && window.ContentEditorCommands.initTableControls) {
                  window.ContentEditorCommands.initTableControls(table);
                }
              }
            });

            if (window.ContentEditorCommands && window.ContentEditorCommands.enhanceExistingEmbeds) {
              window.ContentEditorCommands.enhanceExistingEmbeds(editor);
            }
            
            // Initialize image controls for existing images
            if (window.ContentEditorCommands && window.ContentEditorCommands.enhanceExistingImages) {
              window.ContentEditorCommands.enhanceExistingImages(editor);
            }
            
            // Initialize grid controls for existing grids
            if (window.ContentEditorCommands && window.ContentEditorCommands.enhanceExistingGrids) {
              window.ContentEditorCommands.enhanceExistingGrids(editor);
            }
            
            if (window.ContentEditorCommands && window.ContentEditorCommands.enhanceExistingToggles) {
              window.ContentEditorCommands.enhanceExistingToggles(editor);
            }
            
            // Initialize component controls for existing components
            // Use setTimeout to ensure DOM is fully updated and content is loaded
            setTimeout(() => {
              if (window.ContentEditorCommands && window.ContentEditorCommands.enhanceExistingComponents) {
                window.ContentEditorCommands.enhanceExistingComponents(editor);
              }
            }, 100);
            
            // Initial sync
            if (window.ContentEditorSync) {
              window.ContentEditorSync.toHidden(editor, hiddenInput, true);
              // Setup automatic syncing on input events
              window.ContentEditorSync.setupAutoSync(editor, hiddenInput);
            } else {
              syncToHidden(editor, hiddenInput);
              // Setup basic auto-sync for legacy mode
              editor.addEventListener('input', () => {
                syncToHidden(editor, hiddenInput);
              });
              editor.addEventListener('blur', () => {
                syncToHidden(editor, hiddenInput);
              });
            }
            
            if (window.ContentEditorToolbar) {
              window.ContentEditorToolbar.updateState(editor, toolbar);
            } else {
              updateToolbarState(editor, toolbar);
            }

            if (window.ContentEditorSync && wrapper) {
              window.ContentEditorSync.setupModeTabs(wrapper, editor, toolbar, hiddenInput, htmlSource);
            }
          }

  // Handle Enter key
  function handleEnter(editor) {
    const selection = window.getSelection();
    
    // Ensure editor is always a div, never replace it
    if (!editor.classList.contains('content-editor-area')) {
      console.error('Editor area is not a div with content-editor-area class!');
      return;
    }
    
    if (!selection.rangeCount) {
      // No selection, create new paragraph at end
      const p = document.createElement('p');
      p.innerHTML = '<br>';
      editor.appendChild(p);
      setCursorAt(p, 0);
      return;
    }
    
    const range = selection.getRangeAt(0);
    const block = window.ContentEditorCommands.getCurrentBlock();
    
    if (!block) {
      // No block found - selection might be directly in editor
      // Check if selection is at editor level
      if (range.commonAncestorContainer === editor || 
          range.commonAncestorContainer.parentElement === editor) {
        // Split at cursor and create new paragraph
        try {
          const afterRange = range.cloneRange();
          afterRange.setStart(range.startContainer, range.startOffset);
          afterRange.setEndAfter(editor.lastChild || editor);
          
          const afterContent = afterRange.extractContents();
          const newP = document.createElement('p');
          
          if (afterContent.textContent.trim()) {
            newP.appendChild(afterContent);
          } else {
            newP.innerHTML = '<br>';
          }
          
          editor.appendChild(newP);
          setCursorAt(newP, 0);
        } catch (e) {
          // Fallback: just create new paragraph
          const p = document.createElement('p');
          p.innerHTML = '<br>';
          editor.appendChild(p);
          setCursorAt(p, 0);
        }
        return;
      }
      
      // Fallback: create new paragraph
      const p = document.createElement('p');
      p.innerHTML = '<br>';
      editor.appendChild(p);
      setCursorAt(p, 0);
      return;
    }

    // Check if we're in a list
    if (block.tagName === 'LI') {
      const list = block.parentElement;
      
      // If list item is empty, exit list
      if (!block.textContent.trim() || block.textContent.trim() === '\u200B') {
        const p = document.createElement('p');
        p.innerHTML = '<br>';
        if (list.children.length === 1) {
          // Last item, replace list with paragraph
          list.parentNode.replaceChild(p, list);
        } else {
          // Replace li with p
          list.replaceChild(p, block);
        }
        setCursorAt(p, 0);
        return;
      }
      
      // Create new list item
      const newLi = document.createElement('li');
      newLi.innerHTML = '<br>';
      
      // Get content after cursor
      const afterRange = range.cloneRange();
      afterRange.setStart(range.startContainer, range.startOffset);
      afterRange.setEndAfter(block);
      
      try {
        const afterContent = afterRange.extractContents();
        if (afterContent.textContent.trim()) {
          newLi.innerHTML = '';
          newLi.appendChild(afterContent);
        }
      } catch (e) {
        // Range might be invalid, just create empty li
      }
      
      block.parentNode.insertBefore(newLi, block.nextSibling);
      setCursorAt(newLi, 0);
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
      
      block.parentNode.insertBefore(newP, block.nextSibling);
      
      // Ensure current block has content
      if (!block.textContent.trim() || block.textContent.trim() === '\u200B') {
        block.innerHTML = '<br>';
      }
      
      setCursorAt(newP, 0);
    } catch (e) {
      // Error splitting, just create new paragraph
      const newP = document.createElement('p');
      newP.innerHTML = '<br>';
      block.parentNode.insertBefore(newP, block.nextSibling);
      setCursorAt(newP, 0);
    }
  }

  // Insert line break
  function insertLineBreak(editor) {
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
  }

  // Handle Tab for nested lists
  function handleTab(editor, shiftKey) {
    const block = window.ContentEditorCommands.getCurrentBlock();
    if (!block || block.tagName !== 'LI') return;
    
    const list = block.parentElement;
    
    if (shiftKey) {
      // Shift+Tab: outdent
      if (list.parentElement.tagName === 'LI') {
        // Move to parent list
        const parentLi = list.parentElement;
        const parentList = parentLi.parentElement;
        const nextSibling = parentLi.nextSibling;
        
        parentList.insertBefore(block, nextSibling);
        
        // Remove empty list if needed
        if (list.children.length === 0) {
          list.remove();
        }
        
        setCursorAt(block, 0);
      }
    } else {
      // Tab: indent (create nested list)
      const prevLi = block.previousElementSibling;
      
      if (prevLi) {
        // Add to previous item's nested list
        let nestedList = prevLi.querySelector('ul, ol');
        if (!nestedList) {
          nestedList = document.createElement(list.tagName);
          prevLi.appendChild(nestedList);
        }
        list.removeChild(block);
        nestedList.appendChild(block);
      } else {
        // Create new nested list in previous item
        const prevLi = list.previousElementSibling;
        if (prevLi && prevLi.tagName === 'LI') {
          let nestedList = prevLi.querySelector('ul, ol');
          if (!nestedList) {
            nestedList = document.createElement(list.tagName);
            prevLi.appendChild(nestedList);
          }
          list.removeChild(block);
          nestedList.appendChild(block);
        }
      }
      
      setCursorAt(block, 0);
    }
  }

  // Handle backspace at start of empty block
  function handleBackspace(editor, e) {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;
    
    const range = selection.getRangeAt(0);
    if (!range.collapsed) return; // Has selection, let browser handle
    
    const block = window.ContentEditorCommands.getCurrentBlock();
    if (!block) return;
    
    // Check if cursor is at start of block
    const blockRange = document.createRange();
    blockRange.selectNodeContents(block);
    blockRange.setEnd(range.startContainer, range.startOffset);
    
    if (blockRange.toString().trim() === '' && block.textContent.trim() === '') {
      // Empty block, delete it if not the only one
      const allBlocks = Array.from(editor.querySelectorAll('p, div, h1, h2, h3, h4, h5, h6'));
      if (allBlocks.length > 1) {
        e.preventDefault();
        const prevBlock = block.previousElementSibling;
        block.remove();
        if (prevBlock) {
          setCursorAt(prevBlock, prevBlock.textContent.length);
        }
      }
    }
  }

  // Set cursor position
  function setCursorAt(element, offset) {
    const range = document.createRange();
    const selection = window.getSelection();
    
    if (element.firstChild && element.firstChild.nodeType === Node.TEXT_NODE) {
      const textNode = element.firstChild;
      const maxOffset = Math.min(offset, textNode.textContent.length);
      range.setStart(textNode, maxOffset);
      range.setEnd(textNode, maxOffset);
    } else {
      range.setStart(element, Math.min(offset, element.childNodes.length));
      range.setEnd(element, Math.min(offset, element.childNodes.length));
    }
    
    selection.removeAllRanges();
    selection.addRange(range);
    element.focus();
  }

  // Sync editor content to hidden input
  function syncToHidden(editor, hiddenInput) {
    let html = editor.innerHTML;
    
    // Normalize empty paragraphs
    // Remove empty paragraphs
    html = html.replace(/<p[^>]*>\s*<\/p>/gi, '');
    
    // Ensure at least one paragraph
    if (!html.trim() || !html.match(/<(p|div|h[1-6]|ul|ol|blockquote|pre)/i)) {
      html = '';
    }
    
    hiddenInput.value = html;
  }

  // Update toolbar state (show active formats)
  function updateToolbarState(editor, toolbar) {
    const selection = window.getSelection();
    if (!selection.rangeCount) {
      // No selection, check block-level formatting
      const block = window.ContentEditorCommands.getCurrentBlock();
      if (block) {
        updateBlockFormatting(block, toolbar);
      }
      return;
    }
    
    const range = selection.getRangeAt(0);
    const block = window.ContentEditorCommands.getCurrentBlock();
    
    // Update format buttons
    const formatButtons = {
      'bold': ['b', 'strong'],
      'italic': ['i', 'em'],
      'underline': ['u'],
      'strikethrough': ['s', 'del']
    };
    
    Object.keys(formatButtons).forEach(command => {
      const btn = toolbar.querySelector(`[data-command="${command}"]`);
      if (!btn) return;
      
      const tags = formatButtons[command];
      const isActive = window.ContentEditorCommands.isWrappedInTag(
        range.commonAncestorContainer,
        tags
      );
      
      btn.classList.toggle('active', isActive);
    });
    
    // Update block formatting
    if (block) {
      updateBlockFormatting(block, toolbar);
    }
  }
  
  // Update block-level formatting (headings, lists)
  function updateBlockFormatting(block, toolbar) {
    // Update heading dropdown
    const headingMatch = block.tagName.match(/^H([1-6])$/);
    const headingBtn = toolbar.querySelector('[data-command="heading"]');
    if (headingBtn) {
      if (headingMatch) {
        headingBtn.textContent = 'H' + headingMatch[1];
        headingBtn.classList.add('active');
      } else {
        headingBtn.innerHTML = 'H<span class="dropdown-arrow">▼</span>';
        headingBtn.classList.remove('active');
      }
    }
    
    // Update list buttons
    if (block.tagName === 'LI') {
      const list = block.parentElement;
      toolbar.querySelectorAll('[data-command^="insert"]').forEach(btn => {
        btn.classList.toggle('active', 
          (list.tagName === 'UL' && btn.dataset.command === 'insertUnorderedList') ||
          (list.tagName === 'OL' && btn.dataset.command === 'insertOrderedList')
        );
      });
    } else {
      toolbar.querySelectorAll('[data-command^="insert"]').forEach(btn => {
        btn.classList.remove('active');
      });
    }
  }

  // Ensure editor content is wrapped in block elements
  // Preserves list structures (UL/OL)
  function ensureBlockStructure(editor) {
    const children = Array.from(editor.childNodes);
    let needsNormalization = false;
    
    // Check if we have text nodes or non-block elements directly in editor
    // But preserve list structures (UL/OL are valid block elements)
    children.forEach(node => {
      if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
        needsNormalization = true;
      } else if (node.nodeType === Node.ELEMENT_NODE) {
        const blockTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'BLOCKQUOTE', 'PRE', 'DIV'];
        if (!blockTags.includes(node.tagName)) {
          needsNormalization = true;
        }
      }
    });
    
    if (needsNormalization) {
      // Wrap loose content in paragraphs
      // IMPORTANT: Preserve list structures (UL/OL) - don't wrap them
      const fragment = document.createDocumentFragment();
      let currentP = null;
      
      children.forEach(node => {
        if (node.nodeType === Node.TEXT_NODE) {
          if (node.textContent.trim()) {
            if (!currentP) {
              currentP = document.createElement('p');
              fragment.appendChild(currentP);
            }
            currentP.appendChild(node.cloneNode(true));
          }
        } else if (node.nodeType === Node.ELEMENT_NODE) {
          const blockTags = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'BLOCKQUOTE', 'PRE'];
          if (blockTags.includes(node.tagName)) {
            currentP = null; // Reset paragraph collector
            // Preserve list structures as-is
            fragment.appendChild(node.cloneNode(true));
          } else {
            // Non-block element, wrap in paragraph
            if (!currentP) {
              currentP = document.createElement('p');
              fragment.appendChild(currentP);
            }
            currentP.appendChild(node.cloneNode(true));
          }
        }
      });
      
      // Save selection before replacing content
      window.ContentEditorSelection.save();
      
      editor.innerHTML = '';
      editor.appendChild(fragment);
      
      // Restore selection with editor as root element
      window.ContentEditorSelection.restore(editor);
    }
  }

  // Legacy event handling (fallback if event manager not available)
  function attachLegacyEvents(editor, toolbar, hiddenInput) {
    // This function is kept for backward compatibility
    // Most functionality is now handled by ContentEditorEventManager
    // Only basic event listeners that aren't handled by the manager
    // For now, this is a no-op since event manager handles everything
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initContentEditors);
  } else {
    initContentEditors();
  }

  // Initialize fullscreen functionality
  function initFullscreen(wrapper, editor, toolbar, hiddenInput, fieldName) {
    const fullscreenBtn = wrapper.querySelector('.toolbar-btn-fullscreen[data-field-name="' + fieldName + '"]');
    if (!fullscreenBtn) return;
    
    let fullscreenOverlay = null;
    let fullscreenEditor = null;
    let fullscreenToolbar = null;
    let isFullscreen = false;
    
    // Create fullscreen overlay structure
    function createFullscreenOverlay() {
      if (fullscreenOverlay) return fullscreenOverlay;
      
      const overlay = document.createElement('div');
      overlay.className = 'content-editor-fullscreen-overlay';
      overlay.innerHTML = `
        <div class="content-editor-fullscreen-header">
          <h3 class="content-editor-fullscreen-title">Edit Content</h3>
          <button type="button" class="content-editor-fullscreen-close" title="Close (ESC)">×</button>
        </div>
        <div class="content-editor-fullscreen-body">
          <div class="content-editor-wrapper content-editor-fullscreen-wrapper" data-field-name="${fieldName}">
            <div class="content-editor-toolbar" id="toolbar_${fieldName}_fullscreen"></div>
            <div class="content-editor-area" 
                 id="editor_${fieldName}_fullscreen" 
                 contenteditable="true"
                 data-field-name="${fieldName}"></div>
          </div>
        </div>
      `;
      
      document.body.appendChild(overlay);
      fullscreenOverlay = overlay;
      fullscreenEditor = overlay.querySelector('#editor_' + fieldName + '_fullscreen');
      fullscreenToolbar = overlay.querySelector('#toolbar_' + fieldName + '_fullscreen');
      
      // Copy toolbar HTML
      fullscreenToolbar.innerHTML = toolbar.innerHTML;
      
      // Copy editor content
      fullscreenEditor.innerHTML = editor.innerHTML;
      
      // Initialize the fullscreen editor (visual surface only)
      const fsWrapper = overlay.querySelector('.content-editor-fullscreen-wrapper');
      initEditor(fullscreenEditor, fullscreenToolbar, hiddenInput, fieldName, fsWrapper, null);
      
      // Close button handler
      const closeBtn = overlay.querySelector('.content-editor-fullscreen-close');
      closeBtn.addEventListener('click', closeFullscreen);
      
      return overlay;
    }
    
    // ESC key handler (global)
    let escHandler = null;
    
    // F11 key handler (global)
    let f11Handler = null;
    
    // Sync function
    let syncContentHandler = null;
    let syncPasteHandler = null;
    
    function syncContent() {
      if (!isFullscreen || !fullscreenEditor) return;
      editor.innerHTML = fullscreenEditor.innerHTML;
      if (window.ContentEditorSync) {
        window.ContentEditorSync.toHidden(editor, hiddenInput, true);
        const htmlSource = wrapper.querySelector('.content-editor-html-source');
        if (htmlSource && window.ContentEditorSync.getMode(wrapper) === 'html') {
          window.ContentEditorSync.syncHtmlSourceFromHidden(htmlSource, hiddenInput);
        }
      } else {
        syncToHidden(editor, hiddenInput);
      }
    }
    
    // Open fullscreen
    function openFullscreen() {
      if (isFullscreen) return;

      if (window.ContentEditorSync) {
        window.ContentEditorSync.syncWrapperToHidden(wrapper, true);
        if (window.ContentEditorSync.getMode(wrapper) === 'html') {
          editor.innerHTML = hiddenInput.value || '';
        }
      }
      
      const overlay = createFullscreenOverlay();
      
      // Sync content from original editor to fullscreen
      fullscreenEditor.innerHTML = editor.innerHTML;
      
      // Real-time sync on input
      syncContentHandler = syncContent;
      syncPasteHandler = () => {
        setTimeout(syncContent, 10);
      };
      fullscreenEditor.addEventListener('input', syncContentHandler);
      fullscreenEditor.addEventListener('paste', syncPasteHandler);
      
      // Show overlay
      setTimeout(() => {
        overlay.classList.add('active');
        isFullscreen = true;
        
        // Focus the fullscreen editor
        setTimeout(() => {
          fullscreenEditor.focus();
          // Move cursor to end
          const selection = window.getSelection();
          const range = document.createRange();
          range.selectNodeContents(fullscreenEditor);
          range.collapse(false);
          selection.removeAllRanges();
          selection.addRange(range);
        }, 100);
      }, 10);
      
      // Add ESC key handler
      escHandler = (e) => {
        if (e.key === 'Escape' && isFullscreen) {
          e.preventDefault();
          closeFullscreen();
        }
      };
      document.addEventListener('keydown', escHandler, true);
      
      // Add F11 key handler
      f11Handler = (e) => {
        if (e.key === 'F11' && isFullscreen) {
          e.preventDefault();
          closeFullscreen();
        }
      };
      document.addEventListener('keydown', f11Handler, true);
    }
    
    // Close fullscreen
    function closeFullscreen() {
      if (!isFullscreen || !fullscreenOverlay) return;
      
      // Final sync from fullscreen to original
      editor.innerHTML = fullscreenEditor.innerHTML;
      if (window.ContentEditorSync) {
        window.ContentEditorSync.toHidden(editor, hiddenInput);
      } else {
        syncToHidden(editor, hiddenInput);
      }
      
      // Remove sync event listeners
      if (fullscreenEditor && syncContentHandler) {
        fullscreenEditor.removeEventListener('input', syncContentHandler);
        syncContentHandler = null;
      }
      if (fullscreenEditor && syncPasteHandler) {
        fullscreenEditor.removeEventListener('paste', syncPasteHandler);
        syncPasteHandler = null;
      }
      
      // Hide overlay
      fullscreenOverlay.classList.remove('active');
      isFullscreen = false;
      
      // Remove keyboard event listeners
      if (escHandler) {
        document.removeEventListener('keydown', escHandler, true);
        escHandler = null;
      }
      if (f11Handler) {
        document.removeEventListener('keydown', f11Handler, true);
        f11Handler = null;
      }
      
      // Focus back to original editor
      setTimeout(() => {
        editor.focus();
      }, 200);
    }
    
    // Fullscreen button click handler
    fullscreenBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (isFullscreen) {
        closeFullscreen();
      } else {
        openFullscreen();
      }
    });
    
    // F11 key handler to open fullscreen from original editor
    editor.addEventListener('keydown', (e) => {
      if (e.key === 'F11' && !isFullscreen) {
        e.preventDefault();
        openFullscreen();
      }
    });
  }

  // Also initialize on dynamic content
  if (typeof MutationObserver !== 'undefined') {
    const observer = new MutationObserver(() => {
      initContentEditors();
    });
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }
})();

