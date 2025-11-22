// Toolbar state management for ContentEditor
window.ContentEditorToolbar = {
  /**
   * Update toolbar state based on current selection
   * @param {Element} editor - The editor element
   * @param {Element} toolbar - The toolbar element
   */
  updateState(editor, toolbar) {
    const selection = window.getSelection();
    if (!selection.rangeCount) {
      // No selection, check block-level formatting
      const block = window.ContentEditorDOMUtils?.getCurrentBlock() || 
                    window.ContentEditorCommands?.getCurrentBlock();
      if (block) {
        this.updateBlockFormatting(block, toolbar);
      }
      return;
    }
    
    const range = selection.getRangeAt(0);
    const block = window.ContentEditorDOMUtils?.getCurrentBlock() || 
                  window.ContentEditorCommands?.getCurrentBlock();
    
    // Update format buttons
    this.updateFormatButtons(range, toolbar);
    
    // Update block formatting
    if (block) {
      this.updateBlockFormatting(block, toolbar);
    }
  },

  /**
   * Update format buttons (bold, italic, etc.)
   */
  updateFormatButtons(range, toolbar) {
    const formatButtons = {
      'bold': ['b', 'strong'],
      'italic': ['i', 'em'],
      'underline': ['u'],
      'strikethrough': ['s', 'del'],
      'code': ['code']
    };
    
    // Check for links
    const linkBtn = toolbar.querySelector('[data-command="createLink"]');
    if (linkBtn) {
      let container = range.commonAncestorContainer;
      if (container.nodeType === Node.TEXT_NODE) {
        container = container.parentElement;
      }
      let isInLink = false;
      while (container && container !== document.body) {
        if (container.tagName === 'A') {
          isInLink = true;
          break;
        }
        container = container.parentElement;
      }
      linkBtn.classList.toggle('active', isInLink);
    }
    
    Object.keys(formatButtons).forEach(command => {
      const btn = toolbar.querySelector(`[data-command="${command}"]`);
      if (!btn) return;
      
      const tags = formatButtons[command];
      const isActive = this.isWrappedInTag(range.commonAncestorContainer, tags);
      btn.classList.toggle('active', isActive);
    });
  },

  /**
   * Update block-level formatting (headings, lists, alignment, blockquote)
   */
  updateBlockFormatting(block, toolbar) {
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
    
    // Update blockquote button
    const blockquoteBtn = toolbar.querySelector('[data-command="formatBlock"][data-value="blockquote"]');
    if (blockquoteBtn) {
      blockquoteBtn.classList.toggle('active', block.tagName === 'BLOCKQUOTE');
    }
    
    // Update code block button
    const codeBlockBtn = toolbar.querySelector('[data-command="formatBlock"][data-value="pre"]');
    if (codeBlockBtn) {
      codeBlockBtn.classList.toggle('active', block.tagName === 'PRE');
    }
    
    // Update alignment buttons
    const textAlign = block.style.textAlign || block.getAttribute('data-align') || '';
    const alignMap = {
      'left': 'justifyLeft',
      'center': 'justifyCenter',
      'right': 'justifyRight',
      'justify': 'justifyFull'
    };
    
    toolbar.querySelectorAll('[data-command^="justify"]').forEach(btn => {
      btn.classList.remove('active');
      if (alignMap[textAlign] === btn.dataset.command) {
        btn.classList.add('active');
      }
    });
  },

  /**
   * Check if element is wrapped in tag
   */
  isWrappedInTag(element, tags) {
    if (!element) return false;
    
    const tagArray = Array.isArray(tags) ? tags.map(t => t.toUpperCase()) : [tags.toUpperCase()];
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
      if (tagArray.includes(currentTag)) {
        return true;
      }
      
      // Check alternative tags
      for (const tag of tagArray) {
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
  }
};

