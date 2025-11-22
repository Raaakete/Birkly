// Content synchronization for ContentEditor
window.ContentEditorSync = {
  /**
   * Sync editor content to hidden input
   * @param {Element} editor - The editor element
   * @param {Element} hiddenInput - The hidden input element
   */
  toHidden(editor, hiddenInput) {
    let html = editor.innerHTML;
    
    // Remove empty paragraphs
    html = html.replace(/<p[^>]*>\s*<\/p>/gi, '');
    
    // Ensure at least one block element if there's content
    if (!html.trim() || !html.match(/<(p|div|h[1-6]|ul|ol|blockquote|pre)/i)) {
      html = '';
    }
    
    hiddenInput.value = html;
  },

  /**
   * Sync hidden input to editor (for loading saved content)
   * @param {Element} editor - The editor element
   * @param {Element} hiddenInput - The hidden input element
   */
  toEditor(editor, hiddenInput) {
    const html = hiddenInput.value || '';
    editor.innerHTML = html;
  }
};

