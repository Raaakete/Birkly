window.EditorModes = window.EditorModes || {};
window.EditorModes.markdown = {
  wrap(textarea, mark){
    const start = textarea.selectionStart || 0;
    const end = textarea.selectionEnd || 0;
    const sel = textarea.value.substring(start, end);
    let inserted = sel;
    if (mark === '[]()') inserted = `[${sel}]()`; else if (mark === '`') inserted = '`'+sel+'`'; else inserted = mark+sel+mark;
    textarea.value = textarea.value.substring(0,start) + inserted + textarea.value.substring(end);
    textarea.focus();
    textarea.selectionStart = start; textarea.selectionEnd = start + inserted.length;
  }
};


