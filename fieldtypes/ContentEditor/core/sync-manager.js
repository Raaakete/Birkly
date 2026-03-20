// Content synchronization for ContentEditor
window.ContentEditorSync = {
  // Debounce timers per editor instance
  _debounceTimers: new WeakMap(),
  
  /**
   * Sync editor content to hidden input with debouncing
   * @param {Element} editor - The editor element
   * @param {Element} hiddenInput - The hidden input element
   * @param {boolean} immediate - If true, sync immediately without debouncing
   */
  toHidden(editor, hiddenInput, immediate = false) {
    // Clear existing debounce timer
    const existingTimer = this._debounceTimers.get(editor);
    if (existingTimer) {
      clearTimeout(existingTimer);
    }
    
    const sync = () => {
      try {
        let html = editor.innerHTML;
        
        // Normalize whitespace in empty elements
        html = html.replace(/<p[^>]*>\s*<\/p>/gi, '');
        html = html.replace(/<div[^>]*>\s*<\/div>/gi, '');
        
        // Remove zero-width spaces and other invisible characters
        html = html.replace(/\u200B/g, ''); // Zero-width space
        html = html.replace(/\u200C/g, ''); // Zero-width non-joiner
        html = html.replace(/\u200D/g, ''); // Zero-width joiner
        html = html.replace(/\uFEFF/g, ''); // Zero-width no-break space
        
        // Ensure at least one block element if there's content
        const hasText = html.replace(/<[^>]+>/g, '').trim().length > 0;
        const hasBlockElements = html.match(/<(p|div|h[1-6]|ul|ol|blockquote|pre|table)/i);
        
        if (!hasText && !hasBlockElements) {
          html = '';
        }
        
        // Only update if content actually changed
        if (hiddenInput.value !== html) {
          hiddenInput.value = html;
          
          // Trigger input event for form validation
          if (hiddenInput.dispatchEvent) {
            const event = new Event('input', { bubbles: true, cancelable: true });
            hiddenInput.dispatchEvent(event);
          }
        }
      } catch (e) {
        console.error('Error syncing editor content:', e);
      }
    };
    
    if (immediate) {
      sync();
    } else {
      // Debounce: wait 100ms before syncing
      const timer = setTimeout(sync, 100);
      this._debounceTimers.set(editor, timer);
    }
  },

  /**
   * Sync hidden input to editor (for loading saved content)
   * @param {Element} editor - The editor element
   * @param {Element} hiddenInput - The hidden input element
   */
  toEditor(editor, hiddenInput) {
    try {
      const html = hiddenInput.value || '';
      
      // Save selection before updating content
      if (window.ContentEditorSelection) {
        window.ContentEditorSelection.save();
      }
      
      // Update editor content
      editor.innerHTML = html;
      
      // Restore selection after content update
      if (window.ContentEditorSelection && editor) {
        setTimeout(() => {
          window.ContentEditorSelection.restore(editor);
        }, 0);
      }
    } catch (e) {
      console.error('Error loading content into editor:', e);
    }
  },
  
  /**
   * Setup automatic syncing on input events
   * @param {Element} editor - The editor element
   * @param {Element} hiddenInput - The hidden input element
   */
  setupAutoSync(editor, hiddenInput) {
    // Sync on input events (typing, pasting, etc.)
    editor.addEventListener('input', () => {
      this.toHidden(editor, hiddenInput);
    });
    
    // Sync on paste events (with slight delay to ensure content is processed)
    editor.addEventListener('paste', () => {
      setTimeout(() => {
        this.toHidden(editor, hiddenInput, true);
      }, 10);
    });
    
    // Sync on blur (when editor loses focus)
    editor.addEventListener('blur', () => {
      this.toHidden(editor, hiddenInput, true);
    });
  }
};

