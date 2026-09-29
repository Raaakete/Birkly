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
  function resolveHiddenInput(wrapper, fieldName) {
    if (!wrapper) return null;
    const scoped = wrapper.querySelector('input[type="hidden"]');
    if (scoped) return scoped;
    if (!fieldName) return null;
    const byId = document.getElementById('field_' + fieldName);
    if (byId && wrapper.contains(byId)) {
      return byId;
    }
    return byId;
  }

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
      const hiddenInput = resolveHiddenInput(wrapper, fieldName);
      
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

    if (htmlSource && window.ContentEditorHtmlSource) {
      window.ContentEditorHtmlSource.bind(htmlSource, wrapper);
    }
    
    // Hidden input is the source of truth for stored HTML (avoids escaped markup in the DOM)
    if (hiddenInput && hiddenInput.value) {
      if (window.ContentEditorSync) {
        window.ContentEditorSync.toEditor(editor, hiddenInput);
      } else {
        editor.innerHTML = hiddenInput.value;
      }
    }
    
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

            if (window.ContentEditorBlockManager) {
              window.ContentEditorBlockManager.initializeEditor(editor);
            }

            wrapToolbarScroll(toolbar);
            initToolbarOverflow(toolbar);
            initToolbarTooltips(toolbar);

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

            if (window.ContentEditorLayout) {
              window.ContentEditorLayout.initEditor(editor);
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
      initToolbarTooltips(fullscreenToolbar);
      
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
          window.ContentEditorSync.syncHtmlSourceFromHidden(htmlSource, hiddenInput, { format: true });
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
        document.body.classList.add('content-editor-fullscreen-active');
        
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
      if (!document.querySelector('.content-editor-fullscreen-overlay.active')) {
        document.body.classList.remove('content-editor-fullscreen-active');
      }
      
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
    
    window.ContentEditorFullscreenRegistry = window.ContentEditorFullscreenRegistry || {};
    window.ContentEditorFullscreenRegistry[fieldName] = {
      toggle() {
        if (isFullscreen) {
          closeFullscreen();
        } else {
          openFullscreen();
        }
      },
      open: openFullscreen,
      close: closeFullscreen,
    };

    // F11 key handler to open fullscreen from original editor
    editor.addEventListener('keydown', (e) => {
      if (e.key === 'F11' && !isFullscreen) {
        e.preventDefault();
        openFullscreen();
      }
    });
  }

  function ensureContentEditorTooltipLayer() {
    if (window._contentEditorTooltipController) {
      return window._contentEditorTooltipController;
    }

    const el = document.createElement('div');
    el.id = 'content-editor-toolbar-tooltip';
    el.className = 'content-editor-toolbar-tooltip';
    el.hidden = true;
    el.setAttribute('role', 'tooltip');
    document.body.appendChild(el);

    let activeBtn = null;
    let hideTimer = null;
    const GAP = 8;
    const VIEWPORT_MARGIN = 8;

    const position = (btn) => {
      const rect = btn.getBoundingClientRect();
      el.classList.remove('is-above', 'is-below');

      const tipHeight = el.offsetHeight;
      const tipWidth = el.offsetWidth;
      const spaceAbove = rect.top - VIEWPORT_MARGIN;
      const spaceBelow = window.innerHeight - rect.bottom - VIEWPORT_MARGIN;
      const placeAbove = spaceAbove >= tipHeight + GAP || spaceAbove >= spaceBelow;

      let centerX = rect.left + rect.width / 2;
      const halfW = tipWidth / 2;
      centerX = Math.max(
        VIEWPORT_MARGIN + halfW,
        Math.min(window.innerWidth - VIEWPORT_MARGIN - halfW, centerX)
      );

      el.style.left = `${centerX}px`;

      if (placeAbove) {
        el.classList.add('is-above');
        el.style.top = `${rect.top - GAP}px`;
      } else {
        el.classList.add('is-below');
        el.style.top = `${rect.bottom + GAP}px`;
      }
    };

    const show = (btn) => {
      const text = btn.getAttribute('data-ce-tooltip');
      if (!text) return;
      if (hideTimer) {
        clearTimeout(hideTimer);
        hideTimer = null;
      }
      activeBtn = btn;
      el.textContent = text;
      el.hidden = false;
      el.classList.remove('is-visible');
      el.style.visibility = 'hidden';
      position(btn);
      el.style.visibility = 'visible';
      requestAnimationFrame(() => {
        requestAnimationFrame(() => el.classList.add('is-visible'));
      });
    };

    const hide = () => {
      activeBtn = null;
      el.classList.remove('is-visible');
      hideTimer = setTimeout(() => {
        el.hidden = true;
        el.textContent = '';
        el.classList.remove('is-above', 'is-below');
      }, 140);
    };

    const onReposition = () => {
      if (activeBtn && !el.hidden) {
        position(activeBtn);
      }
    };

    window.addEventListener('scroll', onReposition, true);
    window.addEventListener('resize', onReposition);

    document.addEventListener('pointerdown', (e) => {
      if (e.target.closest('.content-editor-toolbar')) {
        hide();
      }
    }, true);

    window._contentEditorTooltipController = { show, hide, el };
    return window._contentEditorTooltipController;
  }

  function initToolbarTooltips(toolbar) {
    if (!toolbar) return;
    const controller = ensureContentEditorTooltipLayer();

    toolbar.querySelectorAll('.toolbar-btn').forEach((btn) => {
      if (btn.dataset.ceTooltipInit === 'true') return;

      let label = btn.getAttribute('data-ce-tooltip') || btn.getAttribute('title');
      if (!label) return;

      btn.setAttribute('data-ce-tooltip', label);
      btn.removeAttribute('title');
      if (!btn.getAttribute('aria-label')) {
        btn.setAttribute('aria-label', label.split(' - ')[0] || label);
      }
      btn.dataset.ceTooltipInit = 'true';

      btn.addEventListener('pointerenter', () => controller.show(btn));
      btn.addEventListener('pointerleave', () => controller.hide());
      btn.addEventListener('focus', () => controller.show(btn));
      btn.addEventListener('blur', () => controller.hide());
    });
  }

  // Toolbar overflow menu for viewports < 1024px (P44-M9)
  function wrapToolbarScroll(toolbar) {
    if (!toolbar || toolbar.querySelector('.content-editor-toolbar-scroll')) return;
    const scroll = document.createElement('div');
    scroll.className = 'content-editor-toolbar-scroll';
    const overflowWrap = toolbar.querySelector('.content-editor-toolbar-overflow-wrap');
    const movable = [];
    Array.from(toolbar.childNodes).forEach(function (node) {
      if (node.nodeType !== 1) return;
      if (node.classList.contains('content-editor-toolbar-overflow-wrap')) return;
      movable.push(node);
    });
    if (!movable.length) return;
    movable.forEach(function (node) { scroll.appendChild(node); });
    toolbar.insertBefore(scroll, overflowWrap || null);
  }

  function initToolbarOverflow(toolbar) {
    if (!toolbar || toolbar.dataset.overflowReady === 'true') return;
    toolbar.dataset.overflowReady = 'true';

    const wrap = document.createElement('div');
    wrap.className = 'content-editor-toolbar-overflow-wrap';
    wrap.innerHTML = '<button type="button" class="toolbar-btn content-editor-toolbar-overflow-btn" aria-label="More tools" aria-expanded="false" aria-haspopup="true"><span aria-hidden="true">⋯</span></button><div class="content-editor-toolbar-overflow-menu" role="menu" hidden></div>';
    toolbar.appendChild(wrap);

    const btn = wrap.querySelector('.content-editor-toolbar-overflow-btn');
    const menu = wrap.querySelector('.content-editor-toolbar-overflow-menu');
    const scroll = toolbar.querySelector('.content-editor-toolbar-scroll') || toolbar;
    const movableSelector = ':scope > .toolbar-group:not(.toolbar-group-right), :scope > .toolbar-separator, :scope > .toolbar-dropdown';

    function restoreAll() {
      Array.from(menu.querySelectorAll('.content-editor-toolbar-overflow-item')).forEach(function (slot) {
        const el = slot.firstElementChild;
        const placeholder = slot._overflowPlaceholder;
        if (placeholder && el) {
          placeholder.replaceWith(el);
        }
        slot.remove();
      });
      btn.hidden = true;
      menu.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
    }

    function layoutOverflow() {
      restoreAll();
      if (!window.matchMedia('(max-width: 1023px)').matches) return;

      const items = Array.from(scroll.querySelectorAll(movableSelector));
      const reserve = 44;
      const overflowed = [];

      items.forEach(function (el) { el.hidden = false; });

      for (let i = items.length - 1; i >= 0; i--) {
        if (scroll.scrollWidth <= scroll.clientWidth - reserve) break;
        overflowed.unshift(items[i]);
        items[i].hidden = true;
      }

      if (!overflowed.length) return;

      btn.hidden = false;
      overflowed.forEach(function (el) {
        const placeholder = document.createElement('span');
        placeholder.className = 'content-editor-toolbar-overflow-placeholder';
        placeholder.setAttribute('aria-hidden', 'true');
        el.replaceWith(placeholder);

        const slot = document.createElement('div');
        slot.className = 'content-editor-toolbar-overflow-item';
        slot._overflowPlaceholder = placeholder;
        slot.appendChild(el);
        el.hidden = false;
        menu.appendChild(slot);
      });
    }

    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      const open = menu.hidden;
      menu.hidden = !open;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    menu.addEventListener('click', function (e) {
      e.stopPropagation();
    });

    document.addEventListener('click', function () {
      menu.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
    });

    if (typeof ResizeObserver !== 'undefined') {
      const ro = new ResizeObserver(layoutOverflow);
      ro.observe(toolbar);
    }
    window.addEventListener('resize', layoutOverflow);
    layoutOverflow();
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

