# ContentEditor Architecture Proposal

## Current State Analysis

### ✅ What's Working Well
- **Separation of concerns**: PHP vs JavaScript is clear
- **Basic modularity**: Already split into `editor.js`, `commands.js`, `selection.js`
- **Core functionality**: Solid foundation with contentEditable

### ⚠️ Current Issues
1. **`commands.js` is monolithic** (~800+ lines)
   - All formatting logic in one file
   - Inline formatting (bold, italic) mixed with block formatting (headings, lists)
   - Hard to extend without touching existing code

2. **`editor.js` handles too many concerns**
   - Initialization
   - Event handling (keyboard, mouse, toolbar)
   - Toolbar state management
   - Content synchronization
   - Enter/Shift+Enter logic
   - Tab/Shift+Tab for lists

3. **No clear extension points**
   - Adding tables, media embeds, custom blocks would require:
     - Modifying `commands.js` (add new command)
     - Modifying `editor.js` (add toolbar button, keyboard shortcut)
     - Modifying `ContentEditor.php` (add toolbar HTML)
   - High risk of breaking existing functionality

4. **No plugin/block system**
   - Each new block type needs custom logic scattered across files
   - No standardized way to define blocks

## Proposed Modular Architecture

### Core Structure
```
ContentEditor/
├── ContentEditor.php          # Main PHP class (unchanged)
├── core/
│   ├── editor.js              # Core editor initialization & lifecycle
│   ├── selection.js           # Selection utilities (unchanged)
│   ├── dom-utils.js          # DOM manipulation helpers
│   └── event-manager.js      # Centralized event handling
├── commands/
│   ├── registry.js            # Command registry & dispatcher
│   ├── inline/
│   │   ├── bold.js
│   │   ├── italic.js
│   │   ├── underline.js
│   │   └── strikethrough.js
│   ├── block/
│   │   ├── headings.js
│   │   ├── paragraph.js
│   │   └── lists.js
│   └── base-command.js       # Base class for all commands
├── blocks/
│   ├── block-manager.js       # Block lifecycle & management
│   ├── block-registry.js      # Block type registry
│   ├── base-block.js         # Base class for blocks
│   ├── text-block.js         # Text/paragraph blocks
│   └── [future]/
│       ├── table-block.js
│       ├── media-block.js
│       └── custom-block.js
├── toolbar/
│   ├── toolbar-manager.js     # Toolbar state & updates
│   └── toolbar-builder.js    # Dynamic toolbar generation
├── keyboard/
│   ├── keyboard-handler.js    # Keyboard shortcuts
│   └── shortcuts.js          # Shortcut definitions
└── styles/
    └── editor.css            # Styles (unchanged)
```

### Key Design Principles

#### 1. Command Pattern
```javascript
// commands/base-command.js
class BaseCommand {
  constructor(name, options = {}) {
    this.name = name;
    this.options = options;
  }
  
  execute(editor, value = null) {
    throw new Error('execute() must be implemented');
  }
  
  canExecute(editor) {
    return true;
  }
  
  getToolbarButton() {
    // Return toolbar button HTML/config
  }
  
  getKeyboardShortcut() {
    // Return keyboard shortcut config
  }
}

// commands/inline/bold.js
class BoldCommand extends BaseCommand {
  constructor() {
    super('bold', {
      tag: 'b',
      alternatives: ['strong']
    });
  }
  
  execute(editor, value) {
    // Bold-specific logic
  }
  
  getToolbarButton() {
    return {
      html: '<button data-command="bold">B</button>',
      updateState: (button, editor) => {
        // Update button active state
      }
    };
  }
  
  getKeyboardShortcut() {
    return { key: 'b', ctrl: true };
  }
}
```

#### 2. Block System
```javascript
// blocks/base-block.js
class BaseBlock {
  constructor(type, element) {
    this.type = type;
    this.element = element;
  }
  
  render() {
    throw new Error('render() must be implemented');
  }
  
  serialize() {
    return this.element.outerHTML;
  }
  
  canInsertAfter(block) {
    return true;
  }
  
  handleEnter(editor) {
    // Default: create new paragraph
  }
}

// blocks/table-block.js
class TableBlock extends BaseBlock {
  constructor(element) {
    super('table', element);
  }
  
  render() {
    // Render table with controls
  }
  
  handleEnter(editor) {
    // Insert row instead of new block
  }
  
  getToolbar() {
    // Return table-specific toolbar
  }
}
```

#### 3. Command Registry
```javascript
// commands/registry.js
class CommandRegistry {
  constructor() {
    this.commands = new Map();
    this.shortcuts = new Map();
  }
  
  register(command) {
    this.commands.set(command.name, command);
    
    // Register keyboard shortcut if provided
    const shortcut = command.getKeyboardShortcut();
    if (shortcut) {
      this.shortcuts.set(this.getShortcutKey(shortcut), command);
    }
  }
  
  execute(name, editor, value = null) {
    const command = this.commands.get(name);
    if (!command) {
      console.warn(`Command "${name}" not found`);
      return;
    }
    
    if (command.canExecute(editor)) {
      command.execute(editor, value);
    }
  }
  
  getToolbarButtons() {
    return Array.from(this.commands.values())
      .map(cmd => cmd.getToolbarButton())
      .filter(Boolean);
  }
}
```

#### 4. Block Registry
```javascript
// blocks/block-registry.js
class BlockRegistry {
  constructor() {
    this.blocks = new Map();
  }
  
  register(blockType, blockClass) {
    this.blocks.set(blockType, blockClass);
  }
  
  create(type, content = null) {
    const BlockClass = this.blocks.get(type);
    if (!BlockClass) {
      throw new Error(`Block type "${type}" not registered`);
    }
    
    const element = document.createElement('div');
    element.dataset.blockType = type;
    
    if (content) {
      element.innerHTML = content;
    }
    
    return new BlockClass(element);
  }
  
  detect(element) {
    // Detect block type from element
    const type = element.dataset.blockType || 
                 this.inferType(element);
    return this.blocks.get(type);
  }
}
```

### Benefits of This Architecture

1. **Extensibility**
   - Add new commands: Create a class, register it
   - Add new blocks: Create a class, register it
   - No need to modify existing code

2. **Maintainability**
   - Each command/block is self-contained
   - Easy to test individually
   - Clear separation of concerns

3. **Scalability**
   - Can add complex features (tables, media) without bloating core
   - Commands can be lazy-loaded if needed
   - Easy to enable/disable features

4. **Testability**
   - Each module can be tested independently
   - Mock dependencies easily
   - Clear interfaces

### Migration Strategy

**Phase 1: Refactor Core (Non-Breaking)**
1. Extract `dom-utils.js` from `commands.js`
2. Create `event-manager.js` to centralize events
3. Create `command-registry.js` as a wrapper around existing commands
4. Keep existing `commands.js` but route through registry

**Phase 2: Extract Commands (Gradual)**
1. Create `commands/base-command.js`
2. Extract one command (e.g., `bold`) to new structure
3. Register it alongside old system
4. Test thoroughly
5. Repeat for other commands

**Phase 3: Block System (New Feature)**
1. Create block system alongside existing
2. Start with simple blocks (paragraph, heading)
3. Add complex blocks (tables, media) using new system
4. Gradually migrate existing blocks

**Phase 4: Cleanup**
1. Remove old command system
2. Consolidate code
3. Optimize

**P5.5-M4 progress (2026-07-03):** Table/grid layout commands extracted from `commands.js` to `commands/layout/table.js` (`ContentEditorTableCommands`) and `commands/layout/grid.js` (`ContentEditorGridCommands`). Legacy `window.ContentEditorCommands` API preserved via thin delegates; toolbar `data-command="insertTable"` / `insertGrid` unchanged.

### Example: Adding a Table Feature

**Old Way (Current):**
- Modify `commands.js` (add `insertTable` function)
- Modify `editor.js` (add toolbar button handler, keyboard shortcut)
- Modify `ContentEditor.php` (add toolbar HTML)
- Risk: Break existing functionality

**New Way (Proposed):**
```javascript
// blocks/table-block.js
class TableBlock extends BaseBlock {
  // Table-specific logic
}

// commands/block/table.js
class InsertTableCommand extends BaseCommand {
  execute(editor) {
    const tableBlock = BlockRegistry.create('table', {
      rows: 3,
      cols: 3
    });
    BlockManager.insert(editor, tableBlock);
  }
}

// Register
CommandRegistry.register(new InsertTableCommand());
BlockRegistry.register('table', TableBlock);
```

That's it! No modifications to existing code needed.

## Recommendation

**Yes, absolutely refactor to modular architecture before adding complex features.**

The current code is already showing signs of complexity. Adding tables, media embeds, and custom blocks will make it unmaintainable if we don't modularize first.

**Suggested Approach:**
1. Start with Phase 1 (non-breaking refactor)
2. Test thoroughly
3. Then proceed with Phase 2-4 as needed
4. Add new features using the new architecture

This will make the codebase:
- More maintainable
- Easier to extend
- Less prone to bugs
- More testable
- Better organized

