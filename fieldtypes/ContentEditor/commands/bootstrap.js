// Register Phase 2 modular commands with ContentEditorCommandRegistry
(function registerModularCommands() {
  const registry = window.ContentEditorCommandRegistry;
  const base = window.ContentEditorBaseCommand;
  if (!registry || !base || !window.ContentEditorInlineFormat || !window.ContentEditorBlockFormat) {
    console.warn('ContentEditor: modular commands not registered — dependencies missing');
    return;
  }

  const inlineCommands = [
    ['bold', 'b', { key: 'b', ctrl: true }],
    ['italic', 'i', { key: 'i', ctrl: true }],
    ['underline', 'u', { key: 'u', ctrl: true }],
    ['strikethrough', 's', { key: 's', ctrl: true, shift: true }],
  ];

  inlineCommands.forEach(([name, tag, shortcut]) => {
    registry.register(name, base.createInline(tag, name, shortcut));
  });

  registry.register('formatBlock', base.createFormatBlock());

  registry.register(
    'insertUnorderedList',
    base.createList('insertUnorderedList', 'ul', { key: 'l', ctrl: true, shift: true })
  );

  registry.register(
    'insertOrderedList',
    base.createList('insertOrderedList', 'ol', { key: 'o', ctrl: true, shift: true })
  );
})();
