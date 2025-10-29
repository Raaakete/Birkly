// Basic Block editor toolbar (placeholder for slash menu later)
(function(){
  const C = (window.EditorCore && window.EditorCore.Commands) || {};
  function button(label, intent, title){ const b=document.createElement('button'); b.type='button'; b.textContent=label; b.className='ed-btn'; if (title) b.setAttribute('data-tip', title); b.addEventListener('click', (e)=>{ e.preventDefault(); window.EditorCore.Bus.emit(intent); }); return b; }
  function group(children){ const g=document.createElement('div'); g.className='ed-group'; children.forEach(c=>g.appendChild(c)); return g; }
  window.EditorUI = window.EditorUI || {};
  window.EditorUI.renderBlockToolbar = function(container){
    container.innerHTML=''; container.classList.add('ed-tools'); container.style.display='flex';
    const row=document.createElement('div'); row.className='ed-row'; row.style.display='flex'; row.style.gap='6px'; row.style.alignItems='center';
    // Block type dropdown
    const select = document.createElement('select'); select.className='ed-select';
    [['p','Paragraph'],['h1','Heading 1'],['h2','Heading 2'],['h3','Heading 3'],['h4','Heading 4'],['h5','Heading 5'],['h6','Heading 6']]
      .forEach(([v,l])=>{ const o=document.createElement('option'); o.value=v; o.textContent=l; select.appendChild(o); });
    select.title = 'Block type';
    select.addEventListener('change', ()=>{
      const v = select.value;
      if (v==='p') window.EditorCore.Bus.emit({type:C.PARAGRAPH});
      else if (/^h[1-6]$/.test(v)) window.EditorCore.Bus.emit({type:C.HEADING, level: Number(v.substring(1))});
    });
    row.appendChild(select);
    // Inline formatting group
    const inlineGrp = group([
      button('B', {type:C.BOLD}, 'Bold'),
      button('I', {type:C.ITALIC}, 'Italic'),
      button('U', {type:C.UNDERLINE}, 'Underline'),
      button('S', {type:C.STRIKE}, 'Strikethrough')
    ]);
    row.appendChild(inlineGrp);
    // Color pickers
    const colWrap = document.createElement('div'); colWrap.className='ed-group'; colWrap.style.display='flex'; colWrap.style.gap='4px';
    const txt = document.createElement('input'); txt.type='color'; txt.setAttribute('data-tip','Text color'); txt.title='Text color'; txt.addEventListener('input', ()=> { window.EditorUI.__recentColors = window.EditorUI.__recentColors||{text:[],bg:[]}; if (!window.EditorUI.__recentColors.text.includes(txt.value)) window.EditorUI.__recentColors.text.push(txt.value); window.EditorCore.Bus.emit({type:C.TEXT_COLOR, value: txt.value}); renderSwatches(); });
    const bg = document.createElement('input'); bg.type='color'; bg.setAttribute('data-tip','Background color'); bg.title='Background color'; bg.addEventListener('input', ()=> { window.EditorUI.__recentColors = window.EditorUI.__recentColors||{text:[],bg:[]}; if (!window.EditorUI.__recentColors.bg.includes(bg.value)) window.EditorUI.__recentColors.bg.push(bg.value); window.EditorCore.Bus.emit({type:C.BG_COLOR, value: bg.value}); renderSwatches(); });
    const swatches = document.createElement('div'); swatches.style.display='flex'; swatches.style.gap='4px';
    function renderSwatches(){
      swatches.innerHTML='';
      const rec = window.EditorUI.__recentColors||{text:[],bg:[]};
      [...rec.text.slice(-4)].forEach(c=>{ const s=document.createElement('button'); s.className='ed-btn'; s.setAttribute('data-tip',`Apply ${c}`); s.style.background=c; s.textContent=' '; s.addEventListener('click', ()=> window.EditorCore.Bus.emit({type:C.TEXT_COLOR, value:c})); swatches.appendChild(s); });
      [...rec.bg.slice(-4)].forEach(c=>{ const s=document.createElement('button'); s.className='ed-btn'; s.setAttribute('data-tip',`Background ${c}`); s.style.background=c; s.textContent=' '; s.addEventListener('click', ()=> window.EditorCore.Bus.emit({type:C.BG_COLOR, value:c})); swatches.appendChild(s); });
    }
    renderSwatches();
    colWrap.appendChild(txt); colWrap.appendChild(bg); colWrap.appendChild(swatches); row.appendChild(colWrap);
    // Block-level group
    row.appendChild(group([
      button('•', {type:C.UL}, 'Bulleted list'),
      button('1.', {type:C.OL}, 'Numbered list'),
      button('❝', {type:C.QUOTE}, 'Quote block'),
      button('{ }', {type:C.CODE}, 'Code block'),
      button('—', {type:'insert-divider'}, 'Divider')
    ]));
    // Alignment group
    row.appendChild(group([
      button('⟸', {type:C.ALIGN, value:'left'}, 'Align left'),
      button('⇔', {type:C.ALIGN, value:'center'}, 'Align center'),
      button('⟹', {type:C.ALIGN, value:'right'}, 'Align right')
    ]));
    // Insert/Custom group
    const insertBtn = button('+', {type:'insert-block'}, 'Insert block');
    insertBtn.setAttribute('aria-haspopup','menu');
    const linkBtn = button('🔗', {type:C.LINK}, 'Add link');
    const unlinkBtn = button('⛔', {type:C.CLEAR_LINK}, 'Remove link');
    const imgBtn = button('🖼️', {type:C.IMAGE}, 'Insert image');
    const embedBtn = button('▶️', {type:C.EMBED}, 'Embed (YouTube)');
    const videoBtn = button('🎬', {type:C.VIDEO}, 'Insert video');
    const audioBtn = button('🎵', {type:C.AUDIO}, 'Insert audio');
    const fileBtn = button('📎', {type:C.FILE}, 'Insert file');
    const tableBtn = button('▦', {type:C.TABLE}, 'Insert table');
    const gridBtn = button('☰', {type:C.GRID}, 'Insert grid');
    row.appendChild(group([ insertBtn, linkBtn, unlinkBtn, imgBtn, embedBtn, videoBtn, audioBtn, fileBtn, tableBtn, gridBtn, button('</>', {type:C.CUSTOM_HTML}, 'Custom HTML'), button('Tx', {type:C.CLEAR}, 'Clear formatting') ]));
    container.appendChild(row);
    // Simple tooltip implementation
    let tip = document.querySelector('.ed-tip'); if (!tip){ tip=document.createElement('div'); tip.className='ed-tip'; document.body.appendChild(tip); }
    row.addEventListener('mouseover', (e)=>{ const btn=e.target.closest('[data-tip]'); if (!btn) return; const rect=btn.getBoundingClientRect(); tip.textContent=btn.getAttribute('data-tip'); tip.style.left=(rect.left+rect.width/2)+'px'; tip.style.top=(rect.top)+'px'; tip.classList.add('show'); });
    row.addEventListener('mousemove', (e)=>{ const btn=e.target.closest('[data-tip]'); if (!btn) return; const rect=btn.getBoundingClientRect(); tip.style.left=(rect.left+rect.width/2)+'px'; tip.style.top=(rect.top)+'px'; });
    row.addEventListener('mouseleave', ()=> tip.classList.remove('show'));
  };
})();


