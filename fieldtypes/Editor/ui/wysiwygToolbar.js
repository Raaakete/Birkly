// WYSIWYG toolbar renderer
(function(){
  const C = (window.EditorCore && window.EditorCore.Commands) || {};
  function button(label, intent){
    const b = document.createElement('button');
    b.type = 'button'; b.textContent = label; b.className = 'ed-btn';
    b.addEventListener('click', ()=> window.EditorCore.Bus.emit(intent));
    return b;
  }
  function group(children){ const g = document.createElement('div'); g.className='ed-group'; children.forEach(c=>g.appendChild(c)); return g; }
  window.EditorUI = window.EditorUI || {};
  window.EditorUI.renderWysiwygToolbar = function(container){
    container.innerHTML = '';
    container.classList.add('ed-tools');
    container.style.display = 'flex';
    const row = document.createElement('div'); row.className='ed-row'; row.style.display='flex'; row.style.gap='6px';
    row.appendChild(group([
      button('B', {type:C.BOLD}), button('I', {type:C.ITALIC}), button('U', {type:C.UNDERLINE}), button('S', {type:C.STRIKE})
    ]));
    row.appendChild(group([
      button('H1', {type:C.HEADING, level:1}), button('H2', {type:C.HEADING, level:2}), button('H3', {type:C.HEADING, level:3})
    ]));
    row.appendChild(group([
      button('• List', {type:C.UL}), button('1. List', {type:C.OL})
    ]));
    row.appendChild(group([
      button('🔗', {type:C.LINK}), button('</>', {type:C.CUSTOM_HTML}), button('Tx', {type:C.CLEAR})
    ]));
    container.appendChild(row);
  };
})();


