// Shared command model
window.EditorCore = window.EditorCore || {};
window.EditorCore.Commands = {
  // Simple semantic intents
  BOLD: 'bold', ITALIC: 'italic', UNDERLINE: 'underline', STRIKE: 'strike',
  HEADING: 'heading', // payload: { level: 1|2|3 }
  UL: 'ul', OL: 'ol',
  LINK: 'link', CODE: 'code', QUOTE: 'quote', CLEAR: 'clear'
};

// Extended intents
window.EditorCore.Commands.CUSTOM_HTML = 'custom-html';
// Block-type + layout intents
window.EditorCore.Commands.PARAGRAPH = 'paragraph';
window.EditorCore.Commands.ALIGN = 'align'; // payload: { value: 'left'|'center'|'right' }
window.EditorCore.Commands.TEXT_COLOR = 'text-color'; // payload: { value: '#hex' }
window.EditorCore.Commands.BG_COLOR = 'bg-color'; // payload: { value: '#hex' }
window.EditorCore.Commands.CLEAR_LINK = 'clear-link';
window.EditorCore.Commands.IMAGE = 'image';
window.EditorCore.Commands.EMBED = 'embed';
window.EditorCore.Commands.VIDEO = 'video';
window.EditorCore.Commands.AUDIO = 'audio';
window.EditorCore.Commands.FILE = 'file';
window.EditorCore.Commands.CALLOUT = 'callout'; // payload: { variant: 'note'|'info'|'warn'|'tip'|'success'|'error' }
window.EditorCore.Commands.TABLE = 'table';
window.EditorCore.Commands.GRID = 'grid';


