// Minimal toolbar for Markdown (can be expanded with a palette)
(function(){
  const C = (window.EditorCore && window.EditorCore.Commands) || {};
  function button(label, intent){ const b=document.createElement('button'); b.type='button'; b.textContent=label; b.className='ed-btn'; b.addEventListener('click', ()=> window.EditorCore.Bus.emit(intent)); return b; }
  function group(children){ const g=document.createElement('div'); g.className='ed-group'; children.forEach(c=>g.appendChild(c)); return g; }
  window.EditorUI = window.EditorUI || {};
  window.EditorUI.renderMarkdownToolbar = function(container){
    container.innerHTML=''; container.classList.add('ed-tools'); container.style.display='flex';
    const row=document.createElement('div'); row.className='ed-row'; row.style.display='flex'; row.style.gap='6px';
    row.appendChild(group([ button('Bold', {type:C.BOLD}), button('Italic', {type:C.ITALIC}), button('Code', {type:C.CODE}), button('Link', {type:C.LINK}), button('</>', {type:C.CUSTOM_HTML}) ]));
    container.appendChild(row);
  };
})();


