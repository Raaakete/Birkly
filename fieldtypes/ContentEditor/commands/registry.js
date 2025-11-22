// Command registry for ContentEditor
// This is a wrapper around the existing ContentEditorCommands system
// Eventually, individual commands will be registered here
window.ContentEditorCommandRegistry = {
  /**
   * Registry of commands
   */
  commands: new Map(),

  /**
   * Register a command
   * @param {string} name - Command name
   * @param {Object} command - Command object with execute method
   */
  register(name, command) {
    this.commands.set(name, command);
  },

  /**
   * Execute a command
   * @param {string} name - Command name
   * @param {*} value - Optional value for the command
   * @param {Element} editor - The editor element (optional, will be detected)
   */
  execute(name, value = null, editor = null) {
    // First, try to find command in registry
    const registeredCommand = this.commands.get(name);
    if (registeredCommand && registeredCommand.execute) {
      if (!editor) {
        editor = window.ContentEditorDOMUtils?.findEditorArea();
      }
      return registeredCommand.execute(editor, value);
    }
    
    // Fallback to legacy ContentEditorCommands system
    if (window.ContentEditorCommands && window.ContentEditorCommands.execute) {
      return window.ContentEditorCommands.execute(name, value);
    }
    
    console.warn(`Command "${name}" not found`);
  },

  /**
   * Check if a command exists
   * @param {string} name - Command name
   * @returns {boolean}
   */
  has(name) {
    return this.commands.has(name) || 
           (window.ContentEditorCommands && typeof window.ContentEditorCommands.execute === 'function');
  },

  /**
   * Get all registered command names
   * @returns {string[]}
   */
  getCommandNames() {
    const registered = Array.from(this.commands.keys());
    // Add legacy commands if they exist
    if (window.ContentEditorCommands) {
      const legacyCommands = ['bold', 'italic', 'underline', 'strikethrough', 'formatBlock', 
                             'insertUnorderedList', 'insertOrderedList'];
      legacyCommands.forEach(cmd => {
        if (!registered.includes(cmd)) {
          registered.push(cmd);
        }
      });
    }
    return registered;
  }
};

