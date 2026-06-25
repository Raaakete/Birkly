// Content synchronization for ContentEditor
window.ContentEditorSync = {
  // Debounce timers per editor instance
  _debounceTimers: new WeakMap(),
  _htmlDebounceTimers: new WeakMap(),

  getWrapper(element) {
    if (!element) return null;
    if (element.classList && element.classList.contains('content-editor-wrapper')) {
      return element;
    }
    return element.closest ? element.closest('.content-editor-wrapper') : null;
  },

  getMode(wrapper) {
    return wrapper && wrapper.dataset.editorMode === 'html' ? 'html' : 'visual';
  },

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
        const hasBlockElements = html.match(/<(p|div|h[1-6]|ul|ol|blockquote|pre|table|hr)/i);
        
        if (!hasText && !hasBlockElements) {
          html = '';
        }
        
        // Strip editor-only block metadata before save
        html = html.replace(/\s*data-block-type="[^"]*"/gi, '');

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

  syncHtmlSourceFromHidden(htmlSource, hiddenInput) {
    if (!htmlSource || !hiddenInput) return;
    htmlSource.value = hiddenInput.value || '';
  },

  syncHiddenFromHtmlSource(htmlSource, hiddenInput, immediate = false) {
    if (!htmlSource || !hiddenInput) return;

    const existingTimer = this._htmlDebounceTimers.get(htmlSource);
    if (existingTimer) {
      clearTimeout(existingTimer);
    }

    const sync = () => {
      const html = htmlSource.value || '';
      if (hiddenInput.value !== html) {
        hiddenInput.value = html;
        if (hiddenInput.dispatchEvent) {
          hiddenInput.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
        }
      }
    };

    if (immediate) {
      sync();
    } else {
      const timer = setTimeout(sync, 100);
      this._htmlDebounceTimers.set(htmlSource, timer);
    }
  },

  setMode(wrapper, mode, editor, hiddenInput, htmlSource, toolbar) {
    if (!wrapper || !editor || !hiddenInput) return;

    const nextMode = mode === 'html' ? 'html' : 'visual';
    const currentMode = this.getMode(wrapper);
    if (currentMode === nextMode) return;

    if (nextMode === 'html') {
      this.toHidden(editor, hiddenInput, true);
      if (htmlSource) {
        this.syncHtmlSourceFromHidden(htmlSource, hiddenInput);
        editor.classList.add('is-mode-hidden');
        htmlSource.hidden = false;
        htmlSource.classList.remove('is-mode-hidden');
      }
      if (toolbar) {
        toolbar.classList.add('is-html-mode');
      }
    } else {
      if (htmlSource) {
        this.syncHiddenFromHtmlSource(htmlSource, hiddenInput, true);
        this.toEditor(editor, hiddenInput);
        htmlSource.hidden = true;
        htmlSource.classList.add('is-mode-hidden');
        editor.classList.remove('is-mode-hidden');
      } else {
        this.toEditor(editor, hiddenInput);
      }
      if (toolbar) {
        toolbar.classList.remove('is-html-mode');
      }
    }

    wrapper.dataset.editorMode = nextMode;

    wrapper.querySelectorAll('.content-editor-mode-tab').forEach((tab) => {
      const active = tab.dataset.mode === nextMode;
      tab.classList.toggle('is-active', active);
      tab.setAttribute('aria-selected', active ? 'true' : 'false');
      tab.setAttribute('tabindex', active ? '0' : '-1');
    });

    if (nextMode === 'html' && htmlSource) {
      htmlSource.focus();
    } else if (nextMode === 'visual' && editor) {
      editor.focus();
    }
  },

  focusModeTab(wrapper, direction) {
    const tabs = Array.from(wrapper.querySelectorAll('.content-editor-mode-tab'));
    if (!tabs.length) return;
    const currentIndex = tabs.findIndex((tab) => tab.classList.contains('is-active'));
    let nextIndex = currentIndex;
    if (direction === 'next') {
      nextIndex = (currentIndex + 1) % tabs.length;
    } else if (direction === 'prev') {
      nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
    } else if (direction === 'first') {
      nextIndex = 0;
    } else if (direction === 'last') {
      nextIndex = tabs.length - 1;
    }
    tabs[nextIndex].focus();
  },

  setupModeTabs(wrapper, editor, toolbar, hiddenInput, htmlSource) {
    if (!wrapper || wrapper.dataset.modeTabsBound === 'true') return;
    wrapper.dataset.modeTabsBound = 'true';
    wrapper.dataset.editorMode = wrapper.dataset.editorMode || 'visual';

    if (htmlSource) {
      this.syncHtmlSourceFromHidden(htmlSource, hiddenInput);
      htmlSource.addEventListener('input', () => {
        this.syncHiddenFromHtmlSource(htmlSource, hiddenInput);
      });
      htmlSource.addEventListener('blur', () => {
        this.syncHiddenFromHtmlSource(htmlSource, hiddenInput, true);
      });
    }

    wrapper.querySelectorAll('.content-editor-mode-tab').forEach((tab) => {
      tab.addEventListener('click', (event) => {
        event.preventDefault();
        const mode = tab.dataset.mode || 'visual';
        this.setMode(wrapper, mode, editor, hiddenInput, htmlSource, toolbar);
      });
      tab.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight') {
          event.preventDefault();
          this.focusModeTab(wrapper, 'next');
        } else if (event.key === 'ArrowLeft') {
          event.preventDefault();
          this.focusModeTab(wrapper, 'prev');
        } else if (event.key === 'Home') {
          event.preventDefault();
          this.focusModeTab(wrapper, 'first');
        } else if (event.key === 'End') {
          event.preventDefault();
          this.focusModeTab(wrapper, 'last');
        } else if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          const mode = tab.dataset.mode || 'visual';
          this.setMode(wrapper, mode, editor, hiddenInput, htmlSource, toolbar);
        }
      });
    });
  },

  syncWrapperToHidden(wrapper, immediate = true) {
    if (!wrapper) return;

    const fieldName = wrapper.dataset.fieldName;
    const editor = wrapper.querySelector('.content-editor-area');
    const hiddenInput = fieldName ? document.getElementById('field_' + fieldName) : null;
    const htmlSource = wrapper.querySelector('.content-editor-html-source');
    if (!hiddenInput) return;

    if (this.getMode(wrapper) === 'html' && htmlSource) {
      this.syncHiddenFromHtmlSource(htmlSource, hiddenInput, immediate);
    } else if (editor) {
      this.toHidden(editor, hiddenInput, immediate);
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
      if (this.getMode(this.getWrapper(editor)) === 'html') return;
      this.toHidden(editor, hiddenInput);
    });
    
    // Sync on paste events (with slight delay to ensure content is processed)
    editor.addEventListener('paste', () => {
      if (this.getMode(this.getWrapper(editor)) === 'html') return;
      setTimeout(() => {
        this.toHidden(editor, hiddenInput, true);
      }, 10);
    });
    
    // Sync on blur (when editor loses focus)
    editor.addEventListener('blur', () => {
      if (this.getMode(this.getWrapper(editor)) === 'html') return;
      this.toHidden(editor, hiddenInput, true);
    });
  }
};
