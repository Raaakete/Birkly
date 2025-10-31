// Editor fieldtype entry point
(function(){
  // ensure mode helpers are loaded
  function ensureModes(cb){
    if (window.EditorModes && window.EditorModes.advanced && window.EditorModes.wysiwyg && window.EditorModes.markdown && window.EditorModes.html && window.EditorModes.block) return cb();
    // If modes not attached yet, retry shortly (in case assets inject asynchronously)
    let attempts = 10;
    (function wait(){
      if (window.EditorModes && window.EditorModes.advanced && window.EditorModes.wysiwyg && window.EditorModes.markdown && window.EditorModes.html && window.EditorModes.block) return cb();
      if (attempts-- <= 0) return cb();
      setTimeout(wait, 50);
    })();
  }
  function ensureUI(cb){
    if (window.EditorUI && window.EditorUI.renderWysiwygToolbar && window.EditorUI.renderMarkdownToolbar && window.EditorUI.renderBlockToolbar) return cb();
    let attempts = 10;
    (function wait(){
      if (window.EditorUI && window.EditorUI.renderWysiwygToolbar && window.EditorUI.renderMarkdownToolbar && window.EditorUI.renderBlockToolbar) return cb();
      if (attempts-- <= 0) return cb();
      setTimeout(wait, 50);
    })();
  }
  function sanitize(html){
    return String(html).replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi,'');
  }
  function mdToHtml(md){
    let h = md
      .replace(/^###\s+(.*)$/gm,'<h3>$1</h3>')
      .replace(/^##\s+(.*)$/gm,'<h2>$1</h2>')
      .replace(/^#\s+(.*)$/gm,'<h1>$1</h1>')
      .replace(/^\*\s+(.*)$/gm,'<ul><li>$1</li></ul>')
      .replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')
      .replace(/\*(.*?)\*/g,'<em>$1</em>')
      .replace(/`([^`]+)`/g,'<code>$1</code>');
    h = h.replace(/\n{2,}/g,'</p><p>');
    return '<p>' + h + '</p>';
  }
  function htmlToMd(html){
    let t = String(html)
      .replace(/<\/?p>/g,'\n')
      .replace(/<h1>(.*?)<\/h1>/g,'# $1\n')
      .replace(/<h2>(.*?)<\/h2>/g,'## $1\n')
      .replace(/<h3>(.*?)<\/h3>/g,'### $1\n')
      .replace(/<strong>(.*?)<\/strong>/g,'**$1**')
      .replace(/<em>(.*?)<\/em>/g,'*$1*')
      .replace(/<code>(.*?)<\/code>/g,'`$1`')
      .replace(/<[^>]+>/g,'');
    return t.trim();
  }
  function initEditor(wrapper){
    if (!wrapper || wrapper.dataset.initialized === 'true') return;
    const name = wrapper.getAttribute('data-name');
    const advanced = wrapper.querySelector('.ed-advanced');
    const w = wrapper.querySelector('.ed-wysiwyg');
    const md = wrapper.querySelector('.ed-markdown');
    const ht = wrapper.querySelector('.ed-html');
    const htWrap = wrapper.querySelector('#field_'+name+'_html_wrap');
    const blockSurface = wrapper.querySelector('.ed-block-surface');
    const hidden = document.getElementById('field_'+name+'_value');

    // Ensure Advanced surface is focusable and interactive
    try {
      if (advanced) {
        if (!advanced.getAttribute('contenteditable')) advanced.setAttribute('contenteditable', 'true');
        if (!advanced.hasAttribute('tabindex')) advanced.setAttribute('tabindex', '0');
        advanced.style.pointerEvents = 'auto';
        advanced.style.minHeight = advanced.style.minHeight || '180px';
        advanced.addEventListener('mousedown', function(){ advanced.focus({ preventScroll: true }); });
        // Change detection for saving
        const advObserver = new MutationObserver(function(){ syncHidden(); });
        advObserver.observe(advanced, { childList: true, subtree: true, characterData: true });
        ['input','keyup'].forEach(function(ev){ advanced.addEventListener(ev, syncHidden); });
        advanced.addEventListener('paste', function(){ setTimeout(syncHidden, 0); });
        // Advanced toolbar wiring (fallback)
        const advToolbar = wrapper.querySelector('.advanced-toolbar');
        if (advToolbar) {
          advToolbar.addEventListener('click', function(e){
            const btn = e.target.closest('[data-command]');
            if (!btn) return;
            e.preventDefault();
            const cmd = btn.getAttribute('data-command');
            const val = btn.getAttribute('data-value') || null;
            try {
              advanced.focus({ preventScroll: true });
              document.execCommand(cmd, false, val);
              syncHidden();
            } catch(_e) {}
          });
        }
      }
      const tabsElTmp = wrapper.querySelector('.ed-toolbar');
      const toolsElTmp = wrapper.querySelector('.ed-toolsbar');
      if (tabsElTmp) tabsElTmp.style.pointerEvents = 'auto';
      if (toolsElTmp) toolsElTmp.style.pointerEvents = 'auto';
    } catch(_e) {}

    function setMode(mode){
      if (advanced) advanced.style.display = mode==='advanced' ? 'block' : 'none';
      if (w) w.style.display = mode==='wysiwyg' ? 'block' : 'none';
      if (md) md.style.display = mode==='markdown' ? 'block' : 'none';
      if (htWrap) htWrap.style.display = mode==='html' ? 'block' : 'none';
      if (ht) ht.style.display = mode==='html' ? 'block' : 'none';
      if (blockSurface) blockSurface.style.display = mode==='block' ? 'block' : 'none';
      // show tools row for current mode if we use in-template tools regions
      wrapper.querySelectorAll('.ed-tools').forEach(t=>{ t.style.display = 'none'; });
      const tools = wrapper.querySelector(`.ed-tools[data-for="${mode}"]`);
      if (tools) tools.style.display = '';
      if (mode==='advanced' && window.EditorModes?.advanced) window.EditorModes.advanced.init(wrapper);
      if (mode==='block' && window.EditorModes?.block) window.EditorModes.block.init(wrapper);
      if (mode==='html' && window.EditorModes?.html) window.EditorModes.html.bind(wrapper);
      // Plugin blocks can initialize per mode
      try{ if (window.HaruEditor && Array.isArray(window.HaruEditor.plugins?.blocks)){
        window.HaruEditor.plugins.blocks.forEach(fn=>{ try{ fn({wrapper, mode}); }catch(_e){} });
      }}catch(_e){}
    }
    function currentMode(){
      if (advanced && advanced.style.display==='block') return 'advanced';
      if (w && w.style.display==='block') return 'wysiwyg';
      if (md && md.style.display==='block') return 'markdown';
      if (ht && ht.style.display==='block') return 'html';
      if (blockSurface && blockSurface.style.display==='block') return 'block';
      return 'advanced';
    }
    // Autosave indicator
    let saveTimer = null; let savingEl = null; let lastEmitted = 0;
    function showSaving(state){
      if (!savingEl){ savingEl = document.createElement('div'); savingEl.className='ed-saving'; savingEl.textContent='Saving…'; savingEl.style.cssText='position:sticky; bottom:4px; left:0; font-size:12px; color: var(--text-tertiary);'; wrapper.appendChild(savingEl); }
      savingEl.style.display = state ? 'block' : 'none';
      savingEl.textContent = state ? 'Saving…' : 'Saved';
      if (!state) setTimeout(()=>{ if (savingEl) savingEl.style.display='none'; }, 1000);
    }
    function emitChange(){ const now=Date.now(); if (now - lastEmitted > 1000){ wrapper.dispatchEvent(new CustomEvent('editor:change', {detail:{name}})); lastEmitted = now; } }
    function syncHidden(){
      const mode = currentMode();
      if (mode==='advanced') {
        if (window.EditorModes?.advanced?.getValue) {
          hidden.value = sanitize(window.EditorModes.advanced.getValue());
        } else {
          hidden.value = sanitize(advanced.innerHTML);
        }
      } else if (mode==='wysiwyg') hidden.value = sanitize(w.innerHTML);
      else if (mode==='markdown') hidden.value = sanitize(mdToHtml(md.value));
      else if (mode==='html') hidden.value = sanitize(ht.value);
      else if (mode==='block') {
        if (window.EditorModes?.block?.toHTML) hidden.value = sanitize(window.EditorModes.block.toHTML(blockSurface));
      }
      // Throttled autosave signal with coalescing
      showSaving(true);
      clearTimeout(saveTimer);
      saveTimer = setTimeout(()=>{ showSaving(false); emitChange(); }, 500);
    }
    const tabsEl = wrapper.querySelector('.ed-toolbar');
    const toolbarEl = wrapper.querySelector('.ed-toolsbar');
    function renderToolbarFor(mode){
      ensureUI(()=>{
        if (!toolbarEl) return;
        if (mode==='advanced') {
          // Advanced editor has its own toolbar, hide the external one
          toolbarEl.style.display = 'none';
        } else if (mode==='wysiwyg' && window.EditorUI?.renderWysiwygToolbar) window.EditorUI.renderWysiwygToolbar(toolbarEl);
        else if (mode==='markdown' && window.EditorUI?.renderMarkdownToolbar) window.EditorUI.renderMarkdownToolbar(toolbarEl);
        else if (mode==='block' && window.EditorUI?.renderBlockToolbar) window.EditorUI.renderBlockToolbar(toolbarEl);
        // Plugin hooks: allow external tools to augment toolbar
        try{ if (window.HaruEditor && Array.isArray(window.HaruEditor.plugins?.tools)){
          window.HaruEditor.plugins.tools.forEach(fn=>{ try{ fn({toolbarEl, mode, wrapper}); }catch(_e){} });
        }}catch(_e){}
      });
    }
  // Initial toolbar render based on active tab and populate surface from hidden value
    const activeTab = (tabsEl.querySelector('.ed-mode.active') || {}).textContent || 'Advanced';
    const initialMode = activeTab.toLowerCase().includes('advanced') ? 'advanced' : activeTab.toLowerCase().includes('block') ? 'block' : activeTab.toLowerCase().includes('markdown') ? 'markdown' : 'wysiwyg';
    // Do NOT re-initialize or switch modes here; only populate if Advanced is already active and empty
    function decodeHtml(s){ try { var ta = document.createElement('textarea'); ta.innerHTML = s||''; return ta.value; } catch(_e){ return s; } }
    function restoreIfNeeded(){
      try {
        if (initialMode === 'advanced') {
          const surfaceEmpty = advanced && (!advanced.innerHTML || !advanced.innerHTML.trim());
          if (surfaceEmpty && hidden) {
            const val = decodeHtml(hidden.value || '');
            if (window.EditorModes?.advanced?.setValue) window.EditorModes.advanced.setValue(val);
            else if (advanced) advanced.innerHTML = val;
          }
        }
      } catch(_e){}
    }
    restoreIfNeeded();
    try { requestAnimationFrame(restoreIfNeeded); } catch(_e) {}

    renderToolbarFor(initialMode);
    // Handle mode switching on tabs row
    tabsEl.addEventListener('click', (e)=>{
      const btn = e.target.closest('[data-mode]');
      if (btn){
        e.preventDefault();
        const mode = btn.getAttribute('data-mode');
        tabsEl.querySelectorAll('.ed-mode').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        if (mode==='advanced') {
          if (window.EditorModes?.advanced?.setValue) {
            window.EditorModes.advanced.setValue(hidden.value || '');
          } else {
            advanced.innerHTML = decodeHtml(hidden.value || '');
          }
        } else if (mode==='markdown') md.value = htmlToMd(hidden.value || '');
        else if (mode==='wysiwyg') w.innerHTML = decodeHtml(hidden.value || '');
        else if (mode==='html') ht.value = decodeHtml(hidden.value || '');
        setMode(mode);
        renderToolbarFor(mode);
        // After switching modes, keep hidden in sync
        syncHidden();
        return;
      }
      if (e.target.closest('.ed-fullscreen')){
        e.preventDefault();
        document.documentElement.classList.add('editor-fs-open');
        const modal = document.createElement('div');
        modal.className = 'editor-fs-modal';
        modal.innerHTML = '<div class="editor-fs-content"><button class="ed-fs-close" aria-label="Close">✕</button><div class="ed-fs-host"></div></div>';
        const originalParent = wrapper.parentElement;
        const placeholder = document.createElement('div');
        placeholder.style.display = 'contents';
        originalParent.insertBefore(placeholder, wrapper);
        modal.querySelector('.ed-fs-host').appendChild(wrapper);
        document.body.appendChild(modal);
        const close = ()=>{
          modal.remove();
          document.documentElement.classList.remove('editor-fs-open');
          if (placeholder.parentElement) { placeholder.parentElement.insertBefore(wrapper, placeholder); placeholder.remove(); }
        };
        modal.addEventListener('click', (ev)=>{ if (ev.target===modal) close(); });
        modal.querySelector('.ed-fs-close').addEventListener('click', (ev)=>{ ev.preventDefault(); close(); });
        document.addEventListener('keydown', function esc(ev){ if(ev.key==='Escape'){ close(); document.removeEventListener('keydown', esc);} });
      }
    });

    // Commands on toolbar area only
    toolbarEl.addEventListener('click', (e)=>{
      const cmdBtn = e.target.closest('[data-cmd]');
      if (cmdBtn) {
        e.preventDefault();
        const cmd = cmdBtn.getAttribute('data-cmd');
        const value = cmdBtn.getAttribute('data-value');
        if (window.EditorModes?.wysiwyg) window.EditorModes.wysiwyg.apply(w, cmd, value);
        else document.execCommand(cmd, false, value || null);
        syncHidden();
        w.focus();
      }
      const mdBtn = e.target.closest('[data-md]');
      if (mdBtn) {
        e.preventDefault();
        const mark = mdBtn.getAttribute('data-md');
        if (window.EditorModes?.markdown) window.EditorModes.markdown.wrap(md, mark);
        syncHidden();
      }
      // Custom HTML modal across modes
      if (e.target.closest('button') && e.target.textContent === '</>') {
        e.preventDefault();
        openCustomHtmlModal();
      }
    });
    w.addEventListener('input', syncHidden);
    md.addEventListener('input', syncHidden);
    if (window.EditorModes?.html) window.EditorModes.html.bind(wrapper);
    if (blockSurface) blockSurface.addEventListener('input', syncHidden);
    ht.addEventListener('input', ()=>{ syncHidden(); });
    syncHidden();
    
    // Initialize Advanced Editor if it's the active mode
    if (currentMode() === 'advanced' && window.EditorModes?.advanced) {
      window.EditorModes.advanced.init(wrapper);
      // Populate Advanced surface from hidden value after init (init may clear surface)
      try {
        var raw = hidden ? hidden.value : '';
        var ta = document.createElement('textarea'); ta.innerHTML = raw; var decoded = ta.value;
        if (typeof window.EditorModes.advanced.setValue === 'function') {
          window.EditorModes.advanced.setValue(decoded || '');
        } else if (advanced) {
          advanced.innerHTML = decoded || '';
        }
      } catch(_e) {}
    }
    
    wrapper.dataset.initialized = 'true';

    function openCustomHtmlModal(){
      const modal = document.createElement('div');
      modal.className = 'editor-fs-modal';
      modal.innerHTML = '<div class="editor-fs-content"><button class="ed-fs-close" aria-label="Close">✕</button><div style="display:flex;flex-direction:column;gap:8px;height:100%"><textarea class="ed-pane" style="flex:1;min-height:0"></textarea><div><button class="btn-apply">Apply</button><button class="btn-insert">Insert As-Is</button></div></div></div>';
      document.body.appendChild(modal);
      const textarea = modal.querySelector('textarea');
      modal.addEventListener('click', (ev)=>{ if (ev.target.classList.contains('ed-fs-close') || ev.target===modal) { modal.remove(); } });
      modal.querySelector('.btn-apply').addEventListener('click', ()=>{ insertCustomHtml(textarea.value, true); modal.remove(); });
      modal.querySelector('.btn-insert').addEventListener('click', ()=>{ insertCustomHtml(textarea.value, false); modal.remove(); });
    }
    function insertCustomHtml(html, tryConvert){
      const mode = (wrapper.querySelector('.ed-toolbar .ed-mode.active')||{}).textContent || 'Advanced';
      const sanitized = html; // keep simple; sanitize upstream if needed
      if (mode.toLowerCase().includes('advanced')) {
        if (window.EditorModes?.advanced?.setValue) {
          const currentValue = window.EditorModes.advanced.getValue();
          window.EditorModes.advanced.setValue(currentValue + sanitized);
        }
      } else if (mode.toLowerCase().includes('wysiwyg')) {
        document.execCommand('insertHTML', false, sanitized);
      } else if (mode.toLowerCase().includes('markdown')) {
        const block = `\n\n\`\`\`html\n${sanitized}\n\`\`\``;
        md.value += block; md.dispatchEvent(new Event('input'));
      } else if (mode.toLowerCase().includes('block')) {
        // No-op fallback for block mode
      } else if (mode.toLowerCase().includes('html')) {
        if (ht) { ht.value += `\n${sanitized}`; ht.dispatchEvent(new Event('input')); }
      }
    }
  }
  function initAll(){ ensureModes(()=>{ document.querySelectorAll('.editor-field').forEach(initEditor); }); }
  document.addEventListener('DOMContentLoaded', initAll);
  document.addEventListener('haru:pageRendered', initAll);
  setTimeout(initAll, 0);
})();