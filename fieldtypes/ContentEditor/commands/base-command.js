// Base command factory for ContentEditor modular commands (P2-M3-T7)
window.ContentEditorBaseCommand = {
  /**
   * @param {string} name
   * @param {function(Element|null, *): void} executeFn
   * @param {{key?:string,ctrl?:boolean,shift?:boolean,meta?:boolean}|null} shortcut
   */
  create(name, executeFn, shortcut = null) {
    return {
      name,
      execute(editor, value = null) {
        executeFn(editor, value);
      },
      getKeyboardShortcut() {
        return shortcut;
      },
    };
  },

  createInline(tagName, commandName, shortcut = null) {
    const inline = window.ContentEditorInlineFormat;
    return this.create(commandName, () => {
      inline.wrapWithTag(tagName);
    }, shortcut);
  },

  createFormatBlock(shortcut = null) {
    const block = window.ContentEditorBlockFormat;
    return this.create('formatBlock', (_editor, value) => {
      const tagName = (value || 'p').toLowerCase();
      if (window.ContentEditorDOMUtils.isBlockTag(tagName)) {
        block.formatBlock(tagName);
      } else {
        console.warn('formatBlock called with invalid block tag:', value);
      }
    }, shortcut);
  },

  createList(commandName, listType, shortcut = null) {
    const block = window.ContentEditorBlockFormat;
    return this.create(commandName, () => {
      block.toggleList(listType);
    }, shortcut);
  },
};
