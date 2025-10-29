// Self-contained Wysiwyg Fieldtype for HaruCMS
window.HaruFieldtypes = window.HaruFieldtypes || {};

window.HaruFieldtypes['wysiwyg'] = {
  label: 'WYSIWYG',
  category: 'content',
  dynamicItemsEligible: true,
  render: function(fieldConfig, value) {
    // fieldConfig: {name, label, required, help, ...}
    // value: initial value (HTML string)
    let html = value || '';
    if (window.HaruMediaIndex) {
      html = html.replace(/<img([^>]+)data-media-id=["']([a-zA-Z0-9]+)["']([^>]*)>/g, function(_, pre, id, post) {
        const url = window.HaruMediaIndex[id] ? window.HaruMediaIndex[id].url : '';
        return `<img${pre}src=\"${url}\" data-media-id=\"${id}\"${post}>`;
      });
    }
    
    setTimeout(function() {
      const wrapper = document.getElementById('field_' + fieldConfig.name + '_wrapper');
      if (!wrapper) return;
      const editor = wrapper.querySelector('.wysiwyg-editor');
      const toolbar = wrapper.querySelector('.wysiwyg-toolbar');
      if (!toolbar || !editor) return;
      
      // Remove any previous handler
      if (toolbar._wysiwygHandler) {
        toolbar.removeEventListener('click', toolbar._wysiwygHandler);
      }
      
      // Define and add the new handler
      toolbar._wysiwygHandler = function wysiwygToolbarHandler(e) {
        if (e.target.dataset.cmd === 'insertImage') {
          if (window.openMediaPicker) {
            window.openMediaPicker(function(selected) {
              let url = '';
              if (selected.type === 'url') url = selected.url;
              else if (selected.type === 'library' && selected.id && window.HaruMediaIndex && window.HaruMediaIndex[selected.id]) url = window.HaruMediaIndex[selected.id].url;
              if (url) {
                document.execCommand('insertImage', false, url);
              }
            });
            e.preventDefault();
            return false;
          }
        }
        
        // Handle other toolbar commands
        if (e.target.dataset.cmd) {
          e.preventDefault();
          const cmd = e.target.dataset.cmd;
          const value = e.target.dataset.value || null;
          
          if (cmd === 'formatBlock' && e.target.tagName === 'SELECT') {
            document.execCommand(cmd, false, e.target.value);
          } else if (cmd === 'foreColor' || cmd === 'hiliteColor') {
            document.execCommand(cmd, false, e.target.value);
          } else {
            document.execCommand(cmd, false, value);
          }
          
          // Update hidden input
          const wrapper = e.target.closest('.wysiwyg-field');
          if (wrapper) {
            const editor = wrapper.querySelector('.wysiwyg-editor');
            const input = wrapper.querySelector('input[type="hidden"]');
            if (editor && input) {
              input.value = editor.innerHTML;
            }
          }
        }
      };
      toolbar.addEventListener('click', toolbar._wysiwygHandler);
      
      // Update hidden input when editor content changes
      editor.addEventListener('input', function() {
        const input = wrapper.querySelector('input[type="hidden"]');
        if (input) {
          input.value = editor.innerHTML;
        }
      });
    }, 0);
    
    return `
      <div class="wysiwyg-field" id="field_${fieldConfig.name}_wrapper">
        <div class="wysiwyg-toolbar">
          <select data-cmd="formatBlock" title="Heading" class="wysiwyg-heading">
            <option value="<p>">Normal</option>
            <option value="<h1>">H1</option>
            <option value="<h2>">H2</option>
            <option value="<h3>">H3</option>
            <option value="<h4>">H4</option>
            <option value="<blockquote>">Blockquote</option>
          </select>
          <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
          <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
          <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
          <button type="button" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
          <button type="button" data-cmd="superscript" title="Superscript">x<sup>2</sup></button>
          <button type="button" data-cmd="subscript" title="Subscript">x<sub>2</sub></button>
          <input type="color" data-cmd="foreColor" title="Text Color" class="wysiwyg-color" style="width:24px;height:24px;vertical-align:middle;">
          <input type="color" data-cmd="hiliteColor" title="Background Color" class="wysiwyg-bgcolor" style="width:24px;height:24px;vertical-align:middle;">
          <button type="button" data-cmd="insertOrderedList" title="Numbered List">1.</button>
          <button type="button" data-cmd="insertUnorderedList" title="Bullet List">•</button>
          <button type="button" data-cmd="outdent" title="Outdent">←</button>
          <button type="button" data-cmd="indent" title="Indent">→</button>
          <button type="button" data-cmd="justifyLeft" title="Align Left">⯇</button>
          <button type="button" data-cmd="justifyCenter" title="Align Center">≡</button>
          <button type="button" data-cmd="justifyRight" title="Align Right">⯈</button>
          <button type="button" data-cmd="justifyFull" title="Justify">☰</button>
          <button type="button" data-cmd="insertHorizontalRule" title="Horizontal Rule">―</button>
          <button type="button" data-cmd="formatBlock" data-value="pre" title="Code Block">{ }</button>
          <button type="button" data-cmd="insertLink" title="Insert Link">🔗</button>
          <button type="button" data-cmd="removeLink" title="Remove Link">❌🔗</button>
          <button type="button" data-cmd="insertImage" title="Insert Image">🖼️</button>
          <button type="button" data-cmd="insertVideo" title="Insert Video">🎬</button>
          <button type="button" data-cmd="insertTable" title="Insert Table">▦</button>
          <button type="button" data-cmd="insertGrid" title="Insert Grid">☰☰</button>
          <button type="button" data-cmd="undo" title="Undo">⎌</button>
          <button type="button" data-cmd="redo" title="Redo">⎌⎌</button>
          <button type="button" data-cmd="removeFormat" title="Clear Formatting">Tx</button>
        </div>
        <div class="wysiwyg-editor" id="field_${fieldConfig.name}" contenteditable="true" style="min-height:120px;">${html}</div>
        <input type="hidden" name="${fieldConfig.name}" id="field_${fieldConfig.name}_value" value="${value || ''}">
      </div>
    `;
  },
  
  getValue: function(fieldName) {
    var input = document.getElementById('field_' + fieldName + '_value');
    if (!input) return '';
    // Save: replace <img src="..." data-media-id="..."> with <img data-media-id="...">
    let html = input.value || '';
    html = html.replace(/<img([^>]+)src=["'][^"']*["']([^>]+)data-media-id=["']([a-zA-Z0-9]+)["']([^>]*)>/g, function(_, pre, between, id, post) {
      return `<img${pre}${between}data-media-id=\"${id}\"${post}>`;
    });
    return html;
  },
  
  setValue: function(fieldName, value) {
    var editor = document.getElementById('field_' + fieldName);
    var input = document.getElementById('field_' + fieldName + '_value');
    if (editor) editor.innerHTML = value || '';
    if (input) input.value = value || '';
  },
  
  renderConfig: function(config, onChange) {
    return null;
  }
}; 