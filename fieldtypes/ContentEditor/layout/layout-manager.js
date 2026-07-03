// P5.5-M1 — Layout studio orchestrator
window.ContentEditorLayout = {
  initEditor(editor) {
    if (!editor || editor.dataset.layoutStudioInit === 'true') return;
    editor.dataset.layoutStudioInit = 'true';

    const wrapper = editor.closest('.content-editor-wrapper');
    if (window.ContentEditorLayoutBlock) {
      window.ContentEditorLayoutBlock.enhanceEditor(editor);
    }
    if (window.ContentEditorLayoutInspector && wrapper) {
      window.ContentEditorLayoutInspector.bind(wrapper, editor);
    }
    if (window.ContentEditorResizeController) {
      window.ContentEditorResizeController.bindEditor(editor);
    }
  },

  initAll(root) {
    const scope = root || document;
    scope.querySelectorAll('.content-editor-area[contenteditable="true"]').forEach((editor) => {
      this.initEditor(editor);
    });
  },

  stripEditorMetadata(html) {
    if (window.ContentEditorLayoutBlock && window.ContentEditorLayoutBlock.stripEditorMetadata) {
      return window.ContentEditorLayoutBlock.stripEditorMetadata(html);
    }
    return html;
  },
};
