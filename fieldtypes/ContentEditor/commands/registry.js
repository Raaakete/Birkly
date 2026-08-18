// Command registry for ContentEditor (P2-M3-T7 Phase 1–2)
window.ContentEditorCommandRegistry = {
  commands: new Map(),

  /**
   * Commands routed through the modular registry (Phase 2).
   */
  modularCommands: new Set([
    'bold', 'italic', 'underline', 'strikethrough',
    'formatBlock', 'insertUnorderedList', 'insertOrderedList',
  ]),

  register(name, command) {
    this.commands.set(name, command);
  },

  execute(name, value = null, editor = null) {
    const registeredCommand = this.commands.get(name);
    if (registeredCommand && typeof registeredCommand.execute === 'function') {
      if (!editor) {
        editor = (window.ContentEditorState && window.ContentEditorState.activeEditor) ||
          window.ContentEditorDOMUtils?.findEditorArea();
      }

      if (window.ContentEditorSelection) {
        window.ContentEditorSelection.restore(editor);
      }

      try {
        registeredCommand.execute(editor, value);
        if (window.ContentEditorHistory && name !== 'undo' && name !== 'redo') {
          setTimeout(() => window.ContentEditorHistory.saveState(), 50);
        }
      } catch (e) {
        console.error('Error executing command:', name, e);
      } finally {
        if (window.ContentEditorSelection) {
          window.ContentEditorSelection.clear();
        }
      }
      return;
    }

    if (window.ContentEditorCommands && window.ContentEditorCommands.execute) {
      return window.ContentEditorCommands.execute(name, value);
    }

    console.warn(`Command "${name}" not found`);
  },

  has(name) {
    return this.commands.has(name) ||
      (window.ContentEditorCommands && typeof window.ContentEditorCommands.execute === 'function');
  },

  isModular(name) {
    return this.commands.has(name) && this.modularCommands.has(name);
  },

  getCommandNames() {
    const registered = Array.from(this.commands.keys());
    if (window.ContentEditorCommands) {
      ['createLink', 'justifyLeft', 'insertTable', 'insertComponent'].forEach((cmd) => {
        if (!registered.includes(cmd)) {
          registered.push(cmd);
        }
      });
    }
    return registered;
  },
};
