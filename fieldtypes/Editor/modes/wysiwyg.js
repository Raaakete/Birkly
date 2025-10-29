// WYSIWYG helpers
window.EditorModes = window.EditorModes || {};
window.EditorModes.wysiwyg = {
  apply(editor, cmd, value){
    if (!editor) return;
    if (cmd === 'createLink') {
      const url = prompt('Enter URL:');
      if (url) document.execCommand(cmd, false, url);
      return;
    }
    if (cmd === 'formatBlock') {
      document.execCommand(cmd, false, value);
      return;
    }
    document.execCommand(cmd, false, null);
  }
};


