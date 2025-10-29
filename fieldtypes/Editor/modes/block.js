// Minimal block editor mode (Phase 1)
window.EditorModes = window.EditorModes || {};
window.EditorModes.block = {
  init(wrapper){
    const surface = wrapper.querySelector('.ed-block-surface');
    if (!surface) return;
    surface.style.display = 'block';
    // Revert: per-block editing hosts for reliable typing
    surface.removeAttribute('contenteditable');
    // Initialize model
    if (!surface.__blocks) {
      surface.__blocks = [ this.makeModel('p','') ];
    }
    this.render(surface);
    // Initialize history for undo/redo
    surface.__history = surface.__history || [];
    surface.__future = surface.__future || [];
    this.historyPush(surface, true);
    if (!Array.isArray(surface.__selectedIds)) surface.__selectedIds = [];
    // Create floating selection bubble
    this.ensureBubble(surface);
    const tryOpenBubble = ()=>{
      const sel = window.getSelection();
      if (!sel || sel.rangeCount===0) { this.hideBubble(surface); return; }
      if (!this.isSelectionInside(surface)) { this.hideBubble(surface); return; }
      const r = sel.getRangeAt(0);
      if (r.collapsed) { this.hideBubble(surface); return; }
    this.positionBubble(surface);
    };
    const updateBubble = ()=>{ if (surface.__isTyping) return; tryOpenBubble(); };
    // When starting a new selection, hide bubble first
    // Hide on mousedown; selectionchange will show again once selection is created
    surface.addEventListener('mousedown', ()=>{ surface.__isTyping = true; surface.classList.add('ed-selecting'); this.hideBubble(surface); });
    surface.addEventListener('mouseup', ()=> { surface.__isTyping = false; surface.classList.remove('ed-selecting'); setTimeout(updateBubble, 0); });
    // Support double-click selection explicitly
    surface.addEventListener('dblclick', ()=> setTimeout(updateBubble, 0));
    surface.addEventListener('keyup', ()=> setTimeout(updateBubble, 0));
    // Fallback: listen at document level as some extensions or UI elements may intercept events
    const docTry = ()=> setTimeout(updateBubble, 0);
    document.addEventListener('mouseup', docTry, true);
    document.addEventListener('keyup', docTry, true);
    surface.addEventListener('scroll', ()=> setTimeout(()=> this.positionBubble(surface), 0));
    // Track selection changes even when clicking toolbar buttons
    const onSelChange = ()=>{
      if (surface.__isTyping) return;
      const sel = window.getSelection();
      if (!sel || sel.rangeCount===0) { this.hideBubble(surface); return; }
      if (!this.isSelectionInside(surface)) { this.hideBubble(surface); return; }
      const r = sel.getRangeAt(0); if (r.collapsed) { this.hideBubble(surface); return; }
      // Ensure bubble exists and force it visible, then position
      this.ensureBubble(surface);
      const b = surface.__bubble; if (b) b.style.display = 'flex';
      setTimeout(()=> this.positionBubble(surface), 0);
    };
    // Reposition on window resize
    window.addEventListener('resize', ()=> setTimeout(()=> this.positionBubble(surface), 0));
    document.addEventListener('selectionchange', onSelChange, {capture:true, passive:true});
    surface.__onSelChange = onSelChange;
    surface.__docTry = docTry;
    // Selection tracking for inline formatting
    const saveSel = ()=>{ this.saveSelection(surface); };
    surface.addEventListener('keyup', saveSel);
    surface.addEventListener('mouseup', saveSel);
    // Keep selection while clicking toolbars/menus
    document.addEventListener('mousedown', (e)=>{
      const inEditor = !!e.target.closest('.ed-bubble,.ed-toolsbar,.ed-tool-btn,.ed-mini-menu,.ed-table-controls,.ed-grid-controls');
      if (inEditor) this.saveSelection(surface);
    }, true);
    document.addEventListener('selectionchange', ()=>{
      try{
        const sel=window.getSelection();
        if (!sel || sel.rangeCount===0) return;
        const anchorEl = sel.anchorNode && (sel.anchorNode.nodeType===1 ? sel.anchorNode : sel.anchorNode.parentElement);
        if (anchorEl && surface.contains(anchorEl)) this.saveSelection(surface);
      }catch(_e){}
    });
    surface.addEventListener('focusin', saveSel);
    // Multi-block selection: Shift+Click range, Cmd/Ctrl+Click toggle
    surface.addEventListener('mousedown', (e)=>{
      const blkEl = e.target && e.target.closest && e.target.closest('.block-control');
      if (!blkEl) return;
      const idx = this.indexOfEl(surface, blkEl);
      const id = surface.__blocks[idx]?.id;
      if (!id) return;
      const isMac = navigator.platform.toUpperCase().indexOf('MAC')>=0;
      const mod = isMac ? e.metaKey : e.ctrlKey;
      surface.__selectedIds = Array.isArray(surface.__selectedIds) ? surface.__selectedIds : [];
      if (e.shiftKey && typeof surface.__anchorIdx==='number'){
        const a = surface.__anchorIdx;
        const [start,end] = a<=idx ? [a,idx] : [idx,a];
        surface.__selectedIds = surface.__blocks.slice(start,end+1).map(b=> b.id);
        // prevent browser text selection takeover when range selecting blocks
        e.preventDefault();
        this.render(surface, idx, false);
      } else if (mod){
        // toggle
        const set = new Set(surface.__selectedIds);
        if (set.has(id)) set.delete(id); else set.add(id);
        surface.__selectedIds = Array.from(set);
        surface.__anchorIdx = idx;
        e.preventDefault();
        this.render(surface, idx, false);
      } else {
        // single select
        surface.__selectedIds = [id];
        surface.__anchorIdx = idx;
      }
    });
    // Ensure typing goes into a block's content area
    surface.addEventListener('click', (e)=>{
      const blkEl = e.target.closest && e.target.closest('.block-control');
      if (!blkEl) return;
      const contentEl = blkEl.querySelector('.ed-li-content') || blkEl.querySelector('.ed-block-content') || blkEl;
      if (!contentEl) return;
      if (!contentEl.contains(e.target)){
        // Move caret into the content element
        const r=document.createRange(); r.selectNodeContents(contentEl); r.collapse(false);
        const sel=window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
        contentEl.focus?.();
      }
    });
    // Capture input anywhere inside surface and persist to the owning block
    surface.addEventListener('input', (e)=>{
      const blkEl = e.target && e.target.closest && e.target.closest('.block-control');
      if (!blkEl) return;
      const idx = this.indexOfEl(surface, blkEl);
      if (idx<0) return;
      // For new structure: content is the block element itself for most types
      const contentEl = blkEl.querySelector('.ed-li-content') || blkEl.querySelector('.ed-block-content') || blkEl;
      if (!contentEl) return;
      // For new structure, use textContent for text blocks, innerHTML for complex blocks
      if (blkEl.tagName === 'P' || blkEl.tagName.startsWith('H') || blkEl.tagName === 'BLOCKQUOTE') {
        // For text blocks, always save as plain text to avoid HTML injection
        surface.__blocks[idx].html = contentEl.textContent || '';
      } else if (blkEl.tagName === 'UL' || blkEl.tagName === 'OL') {
        // For lists, save as clean HTML without button elements
        const cleanList = contentEl.cloneNode(true);
        
        // Remove any button elements that might have been injected
        const buttons = cleanList.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
        buttons.forEach(btn => btn.remove());
        
        // Also remove any contenteditable attributes from list items
        cleanList.querySelectorAll('li').forEach(li => {
          li.removeAttribute('contenteditable');
        });
        
        // Save the cleaned list HTML
        surface.__blocks[idx].html = cleanList.innerHTML || '';
      } else {
        // For other complex blocks (tables, etc.), save as HTML
        surface.__blocks[idx].html = contentEl.innerHTML || '';
      }
      this.historyPush(surface, false, true);
    });
    // Track last focused block index and typing state
    surface.addEventListener('focusin', (e)=>{
      const el = e.target.closest('.block-control');
      if (!el) return; surface.__lastIdx = this.indexOfEl(surface, el);
      surface.__isTyping = true;
    });
    // Undo/redo handlers with selection preservation
    surface.addEventListener('keydown', (e)=>{
      const isMac = navigator.platform.toUpperCase().indexOf('MAC')>=0;
      const mod = isMac ? e.metaKey : e.ctrlKey;
      if (!mod) return;
      if (e.key.toLowerCase()==='z'){
        e.preventDefault();
        if (e.shiftKey) { this.historyRedo(surface); }
        else { this.historyUndo(surface); }
        // Try to restore selection to last focused block
        const idx = typeof surface.__lastIdx==='number' ? surface.__lastIdx : 0;
        const el = surface.children[idx];
        // For new structure: content is the block element itself for most types
        const c = el && (el.querySelector('.ed-li-content')||el.querySelector('.ed-block-content')||el);
        if (c) this.placeCaretAtEnd(c);
      }
    });
    surface.addEventListener('focusout', ()=>{ surface.__isTyping = false; });
    // While selecting inside a grid, disable interactive controls to avoid interfering with text selection
    surface.addEventListener('mousedown', (e)=>{
      const grid = e.target && e.target.closest && e.target.closest('.ed-grid');
      if (grid){ grid.classList.add('ed-selecting'); }
    }, true);
    document.addEventListener('mouseup', ()=>{
      try { surface.querySelectorAll('.ed-grid.ed-selecting').forEach(g=> g.classList.remove('ed-selecting')); } catch(_e){}
    }, true);
    // Slash menu
    surface.addEventListener('keydown', (e)=>{
      // Common inline mark shortcuts
      if ((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey){
        const k = e.key.toLowerCase();
        if (k==='b' || k==='i' || k==='u' || k==='s'){
          e.preventDefault();
          this.restoreSelection(surface);
          document.execCommand('styleWithCSS', false, true);
          if (k==='b') document.execCommand('bold');
          if (k==='i') document.execCommand('italic');
          if (k==='u') document.execCommand('underline');
          if (k==='s') document.execCommand('strikeThrough');
          surface.dispatchEvent(new Event('input'));
          return;
        }
      }
      // Handle grid Enter at the root (delegated, capture ensured earlier guards may miss)
      try{
        const sel = window.getSelection && window.getSelection();
        const anchor = sel && sel.anchorNode;
        const anchorEl = anchor ? (anchor.nodeType===1 ? anchor : anchor.parentElement) : null;
        const inGrid = (anchorEl && anchorEl.closest && anchorEl.closest('.ed-grid')) || (e.target && e.target.closest && e.target.closest('.ed-grid'));
        const inSimpleCol = (anchorEl && anchorEl.closest && anchorEl.closest('.ed-col')) || (e.target && e.target.closest && e.target.closest('.ed-col'));
        if (inGrid || inSimpleCol){
          const grid = (anchorEl && anchorEl.closest && anchorEl.closest('.ed-grid')) || (e.target.closest && e.target.closest('.ed-grid'));
          if (grid) grid.classList.add('ed-selecting');
          if (e.key === 'Enter'){
            e.preventDefault(); e.stopPropagation(); if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            const cell = (anchorEl && anchorEl.closest && (anchorEl.closest('.ed-grid-cell')||anchorEl.closest('.ed-col'))) || (e.target.closest && (e.target.closest('.ed-grid-cell')||e.target.closest('.ed-col')));
            if (cell){
              this.normalizeEditableContainer(cell);
                    const blockEl = (anchorEl.closest && anchorEl.closest('.block-control')) || e.target.closest('.block-control');
      const idx = this.indexOfEl(surface, blockEl);
      const wrapper = cell.querySelector('.ed-grid-cell-content') || cell;
      const currentP = (anchorEl.closest && anchorEl.closest('p')) || wrapper.querySelector('p:last-child');
      const p = document.createElement('p'); p.innerHTML='&nbsp;';
      if (currentP && currentP.parentNode){ currentP.parentNode.insertBefore(p, currentP.nextSibling); } else { (cell.querySelector('.ed-grid-cell-content')||cell).appendChild(p); }
      const r=document.createRange(); const s=window.getSelection(); r.setStart(p,0); r.setEnd(p,0); s.removeAllRanges(); s.addRange(r);
      const blockContent = blockEl && (blockEl.querySelector('.ed-block-content') || blockEl);
              if (blockContent && typeof idx==='number' && idx>=0){ surface.__blocks[idx].html = blockContent.innerHTML; surface.dispatchEvent(new Event('input')); }
            }
            return;
          }
          // For other keys while inside grid, ensure selection stays inside wrapper
          const cell = (anchorEl && anchorEl.closest && (anchorEl.closest('.ed-grid-cell')||anchorEl.closest('.ed-col'))) || (e.target.closest && (e.target.closest('.ed-grid-cell')||e.target.closest('.ed-col')));
          if (cell){ this.normalizeEditableContainer(cell); }
          return; // Skip global handler entirely while typing inside grid
        }
      }catch(err){ /* ignore guard errors */ }
      const el = e.target.closest('.block-control');
      if (!el) return;
      const contentEl = el.querySelector('.ed-li-content') || el.querySelector('.ed-block-content') || el;
      if (e.key === '/' ){
        e.preventDefault();
        this.openInsertMenu(surface, el, e, 'convert');
      } else if (e.key === 'Enter' && e.shiftKey) {
        // Soft break inside block
        e.preventDefault();
        contentEl.focus();
        (window.HaruEditor?.Ops?.insertSoftBreak||(()=>{document.execCommand('insertLineBreak');}))();
        // Sync change for autosave/serialization
        surface.dispatchEvent(new Event('input'));
      } else if (e.key === 'Enter') {
        e.preventDefault();
        const idx = this.indexOfEl(surface, el);
        const blk = surface.__blocks[idx];
        const content = contentEl;
        const isList = blk.type==='ul' || blk.type==='ol';
        const isEmpty = this.getText(content).length === 0;
        if (isList && isEmpty) {
          if ((blk.indent||0) > 0) { blk.indent -= 1; this.render(surface, idx, true); }
          else { blk.type='p'; this.render(surface, idx, true); }
          return;
        }
        // In code blocks, use soft line break behavior
        if (blk.type==='code'){
          (window.HaruEditor?.Ops?.insertSoftBreak||(()=>{document.execCommand('insertLineBreak');}))();
          surface.dispatchEvent(new Event('input'));
          return;
        }
        // split block using shared op
        const split = (window.HaruEditor?.Ops?.splitAtCaret) ? window.HaruEditor.Ops.splitAtCaret(content) : { before: content.innerHTML, after: '' };
        const beforeHTML = split.before;
        const afterHTML = split.after;
        const newType = (/^h[1-6]$/).test(blk.type) ? 'p' : blk.type; // default to paragraph after headings
        const newBlock = this.makeModel(newType, afterHTML);
        newBlock.indent = blk.indent||0;
        // For lists: keep in same block, start a new line item
        if (blk.type==='ul' || blk.type==='ol'){
          // Handle Enter inside lists - create new list item
          const selection = window.getSelection();
          if (selection && selection.rangeCount > 0) {
            const range = selection.getRangeAt(0);
            // Handle both text nodes and element nodes
            let listItem = null;
            if (range.startContainer.nodeType === Node.TEXT_NODE) {
              // If it's a text node, get the parent element and find the closest li
              listItem = range.startContainer.parentElement.closest('li');
            } else {
              // If it's an element node, use closest directly
              listItem = range.startContainer.closest('li');
            }
            
            if (listItem) {
              // Split the current list item at cursor
              const textContent = listItem.textContent || '';
              const cursorOffset = range.startOffset;
              const beforeCursor = textContent.substring(0, cursorOffset);
              const afterCursor = textContent.substring(cursorOffset);
              
              // Update current list item
              listItem.textContent = beforeCursor;
              
              // Create new list item
              const newLi = document.createElement('li');
              newLi.textContent = afterCursor;
              
              // Insert new list item after current one
              if (listItem.nextSibling) {
                listItem.parentNode.insertBefore(newLi, listItem.nextSibling);
              } else {
                listItem.parentNode.appendChild(newLi);
              }
              
              // Focus new list item
              const newRange = document.createRange();
              newRange.setStart(newLi, 0);
              newRange.collapse(true);
              selection.removeAllRanges();
              selection.addRange(newRange);
              
              // Update the block content - use the list element, not contentEl
              const listElement = contentEl.tagName === 'UL' || contentEl.tagName === 'OL' ? contentEl : contentEl.closest('ul, ol');
              if (listElement) {
                const cleanList = listElement.cloneNode(true);
                const buttons = cleanList.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
                buttons.forEach(btn => btn.remove());
                
                // Also remove any contenteditable attributes from list items
                cleanList.querySelectorAll('li').forEach(li => {
                  li.removeAttribute('contenteditable');
                });
                
                surface.__blocks[idx].html = cleanList.innerHTML;
              }
              
              return;
            }
          }
        }
        
        // Handle Enter for non-list blocks - create new block
        const selection = window.getSelection();
        if (selection && selection.rangeCount > 0) {
          const range = selection.getRangeAt(0);
          const cursorOffset = range.startOffset;
          
          // Get text content - always use textContent for text blocks to avoid HTML injection
          let textContent = '';
          if (contentEl.tagName === 'P' || contentEl.tagName.startsWith('H') || contentEl.tagName === 'BLOCKQUOTE') {
            textContent = contentEl.textContent || '';
          } else {
            // For complex blocks, extract text content from HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = contentEl.innerHTML || '';
            textContent = tempDiv.textContent || tempDiv.innerText || '';
          }
          
          // Split content
          const beforeCursor = textContent.substring(0, cursorOffset);
          const afterCursor = textContent.substring(cursorOffset);
          
          // Update current block - always save as plain text to avoid HTML injection
          surface.__blocks[idx].html = beforeCursor || '';
          
          // Create new block
          const newBlock = this.makeModel('p', afterCursor);
          surface.__blocks.splice(idx + 1, 0, newBlock);
          
          // Re-render and focus new block
          this.render(surface, idx + 1, true);
          
          // Focus the new block
          setTimeout(() => {
            const newBlockEl = surface.children[idx + 1];
            if (newBlockEl) {
              newBlockEl.focus();
              // Place cursor at start of new block
              const range = document.createRange();
              const sel = window.getSelection();
              range.setStart(newBlockEl, 0);
              range.collapse(true);
              sel.removeAllRanges();
              sel.addRange(range);
            }
          }, 0);
        }
      } else if (e.key === 'Backspace') {
        const atStart = (window.HaruEditor?.Ops?.caretAtStart) ? window.HaruEditor.Ops.caretAtStart(contentEl) : this.isCaretAtStart(contentEl);
        if (atStart) {
          e.preventDefault();
          const idx = this.indexOfEl(surface, el);
          if (idx > 0) {
            const prev = surface.__blocks[idx-1];
            const cur = surface.__blocks[idx];
            // merge blocks via shared helper
            prev.html = (window.HaruEditor?.Ops?.mergeHtml) ? window.HaruEditor.Ops.mergeHtml(prev.html, cur.html) : (String(prev.html||'') + String(cur.html||''));
            surface.__blocks.splice(idx,1);
            this.render(surface, idx-1, true, 'end'); this.historyPush(surface, false, true);
          }
        }
      } else if (e.key === 'Delete') {
        const atEnd = (window.HaruEditor?.Ops?.caretAtEnd) ? window.HaruEditor.Ops.caretAtEnd(contentEl) : this.isCaretAtEnd(contentEl);
        if (atEnd) {
          e.preventDefault();
          const idx = this.indexOfEl(surface, el);
          if (idx < surface.__blocks.length-1) {
            const cur = surface.__blocks[idx];
            const next = surface.__blocks[idx+1];
            // merge forward via shared helper
            cur.html = (window.HaruEditor?.Ops?.mergeHtml) ? window.HaruEditor.Ops.mergeHtml(cur.html, next.html) : (String(cur.html||'') + String(next.html||''));
            surface.__blocks.splice(idx+1,1);
            this.render(surface, idx, true, 'end'); this.historyPush(surface, false, true);
          }
        }
      } else if (e.key === 'Tab') {
        e.preventDefault();
        const idx = this.indexOfEl(surface, el);
        const block = surface.__blocks[idx];
        const isList = block.type==='ul' || block.type==='ol';
        if (!isList) return; // only indent for lists
        const nextIndent = (block.indent||0) + (e.shiftKey ? -1 : 1);
        block.indent = Math.max(0, Math.min(8, nextIndent));
        this.render(surface, idx, true); this.historyPush(surface);
      }
    });
    // Click-to-focus ensures there's always a block to type in
    surface.addEventListener('mousedown', (e)=>{
      const block = e.target.closest('.block-control');
      if (block) return; // clicked inside a block already
      if (!surface.__blocks || surface.__blocks.length===0) { surface.__blocks=[this.makeModel('p','')]; }
      this.render(surface, surface.__blocks.length-1, true, 'end');
    });
    // Listen to toolbar commands
    this.unsubscribe?.();
    this.unsubscribe = window.EditorCore.Bus.on((intent)=>{
      const t = intent.type;
      let idx = this.getActiveIndex(surface);
      if (idx<0) idx = (typeof surface.__lastIdx==='number') ? surface.__lastIdx : 0;
      const block = surface.__blocks[idx];
      const selectedIds = Array.isArray(surface.__selectedIds) && surface.__selectedIds.length>1 ? surface.__selectedIds.slice() : null;
      const applyToSelectedBlocks = (fn)=>{
        if (selectedIds){
          selectedIds.forEach((selId)=>{
            const bi = surface.__blocks.findIndex(b=> b.id===selId);
            if (bi>=0) fn(bi);
          });
          // Don't render here - let the individual commands handle rendering
          surface.dispatchEvent(new Event('input'));
          return true;
        }
        return false;
      };
      if (t === window.EditorCore.Commands.HEADING){
        const level = Math.max(1, Math.min(3, Number(intent.level)||1));
        if (applyToSelectedBlocks((bi)=>{ 
          // Save current content before converting
          const currentEl = surface.children[bi];
          if (currentEl) {
            const contentEl = currentEl.querySelector('.ed-li-content') || currentEl.querySelector('.ed-block-content') || currentEl;
            if (contentEl.tagName === 'P' || contentEl.tagName.startsWith('H') || contentEl.tagName === 'BLOCKQUOTE') {
              surface.__blocks[bi].html = contentEl.textContent || '';
            } else {
              // For complex blocks, extract clean text content
              const tempDiv = document.createElement('div');
              tempDiv.innerHTML = contentEl.innerHTML || '';
              
              // Remove any button elements that might have been injected
              const buttons = tempDiv.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
              buttons.forEach(btn => btn.remove());
              
              surface.__blocks[bi].html = tempDiv.textContent || tempDiv.innerText || '';
            }
          }
          surface.__blocks[bi].type='h'+level; 
          surface.__blocks[bi].indent=0; 
          
          // Render this specific block after conversion
          this.render(surface, bi, true);
        })) return;
        
        // Save current content before converting
        const currentEl = surface.children[idx];
        if (currentEl) {
          const contentEl = currentEl.querySelector('.ed-li-content') || currentEl.querySelector('.ed-block-content') || currentEl;
          if (contentEl.tagName === 'P' || contentEl.tagName.startsWith('H') || contentEl.tagName === 'BLOCKQUOTE') {
            block.html = contentEl.textContent || '';
          } else {
            // For complex blocks, extract clean text content
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = contentEl.innerHTML || '';
            
            // Remove any button elements that might have been injected
            const buttons = tempDiv.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
            buttons.forEach(btn => btn.remove());
            
            block.html = tempDiv.textContent || tempDiv.innerText || '';
          }
        }
        block.type = 'h' + level; 
        block.indent = 0; 
        this.render(surface, idx, true); 
        surface.dispatchEvent(new Event('input')); 
        return;
      }
      else if (t === window.EditorCore.Commands.UL){
        // Convert to UL - preserve content before conversion
        if (applyToSelectedBlocks((bi)=>{ 
          // Save current content before converting
          const currentEl = surface.children[bi];
          if (currentEl) {
            const contentEl = currentEl.querySelector('.ed-li-content') || currentEl.querySelector('.ed-block-content') || currentEl;
            if (contentEl.tagName === 'P' || contentEl.tagName.startsWith('H') || contentEl.tagName === 'BLOCKQUOTE') {
              surface.__blocks[bi].html = contentEl.textContent || '';
            } else {
              // For complex blocks, extract clean text content
              const tempDiv = document.createElement('div');
              tempDiv.innerHTML = contentEl.innerHTML || '';
              
              // Remove any button elements that might have been injected
              const buttons = tempDiv.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
              buttons.forEach(btn => btn.remove());
              
              surface.__blocks[bi].html = tempDiv.textContent || tempDiv.innerText || '';
            }
          }
          surface.__blocks[bi].type='ul'; 
          
          // Render this specific block after conversion
          this.render(surface, bi, true);
        })) return;
        
        // Save current content before converting
        const currentEl = surface.children[idx];
        if (currentEl) {
          const contentEl = currentEl.querySelector('.ed-li-content') || currentEl.querySelector('.ed-block-content') || currentEl;
          if (contentEl.tagName === 'P' || contentEl.tagName.startsWith('H') || contentEl.tagName === 'BLOCKQUOTE') {
            // For text blocks, always use textContent to avoid HTML injection
            block.html = contentEl.textContent || '';
          } else {
            // For complex blocks, extract clean text content
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = contentEl.innerHTML || '';
            
            // Remove any button elements that might have been injected
            const buttons = tempDiv.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
            buttons.forEach(btn => btn.remove());
            
            block.html = tempDiv.textContent || tempDiv.innerText || '';
          }
        }
        block.type='ul'; 
        this.render(surface, idx, true); 
        surface.dispatchEvent(new Event('input')); 
        return;
      }
      else if (t === window.EditorCore.Commands.OL){
        if (applyToSelectedBlocks((bi)=>{ 
          // Save current content before converting
          const currentEl = surface.children[bi];
          if (currentEl) {
            const contentEl = currentEl.querySelector('.ed-li-content') || currentEl.querySelector('.ed-block-content') || currentEl;
            if (contentEl.tagName === 'P' || contentEl.tagName.startsWith('H') || contentEl.tagName === 'BLOCKQUOTE') {
              surface.__blocks[bi].html = contentEl.textContent || '';
            } else {
              // For complex blocks, extract clean text content
              const tempDiv = document.createElement('div');
              tempDiv.innerHTML = contentEl.innerHTML || '';
              
              // Remove any button elements that might have been injected
              const buttons = tempDiv.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
              buttons.forEach(btn => btn.remove());
              
              surface.__blocks[bi].html = tempDiv.textContent || tempDiv.innerText || '';
            }
          }
          surface.__blocks[bi].type='ol'; 
          
          // Render this specific block after conversion
          this.render(surface, bi, true);
        })) return;
        
        // Save current content before converting
        const currentEl = surface.children[idx];
        if (currentEl) {
          const contentEl = currentEl.querySelector('.ed-li-content') || currentEl.querySelector('.ed-block-content') || currentEl;
          if (contentEl.tagName === 'P' || contentEl.tagName.startsWith('H') || contentEl.tagName === 'BLOCKQUOTE') {
            // For text blocks, always use textContent to avoid HTML injection
            block.html = contentEl.textContent || '';
          } else {
            // For complex blocks, extract clean text content
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = contentEl.innerHTML || '';
            
            // Remove any button elements that might have been injected
            const buttons = tempDiv.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
            buttons.forEach(btn => btn.remove());
            
            block.html = tempDiv.textContent || tempDiv.innerText || '';
          }
        }
        block.type='ol'; 
        this.render(surface, idx, true); 
        surface.dispatchEvent(new Event('input')); 
        return;
      }
      else if (t === window.EditorCore.Commands.QUOTE){ 
        if (applyToSelectedBlocks((bi)=>{ 
          // Save current content before converting
          const currentEl = surface.children[bi];
          if (currentEl) {
            const contentEl = currentEl.querySelector('.ed-li-content') || currentEl.querySelector('.ed-block-content') || currentEl;
            if (contentEl.tagName === 'P' || contentEl.tagName.startsWith('H') || contentEl.tagName === 'BLOCKQUOTE') {
              surface.__blocks[bi].html = contentEl.textContent || '';
            } else {
              // For complex blocks, extract clean text content
              const tempDiv = document.createElement('div');
              tempDiv.innerHTML = contentEl.innerHTML || '';
              
              // Remove any button elements that might have been injected
              const buttons = tempDiv.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
              buttons.forEach(btn => btn.remove());
              
              surface.__blocks[bi].html = tempDiv.textContent || tempDiv.innerText || '';
            }
          }
          surface.__blocks[bi].type='quote'; 
          
          // Render this specific block after conversion
          this.render(surface, bi, true);
        })) return; 
        
        // Save current content before converting
        const currentEl = surface.children[idx];
        if (currentEl) {
          const contentEl = currentEl.querySelector('.ed-li-content') || currentEl.querySelector('.ed-block-content') || currentEl;
          if (contentEl.tagName === 'P' || contentEl.tagName.startsWith('H') || contentEl.tagName === 'BLOCKQUOTE') {
            // For text blocks, always use textContent to avoid HTML injection
            block.html = contentEl.textContent || '';
          } else {
            // For complex blocks, extract clean text content
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = contentEl.innerHTML || '';
            
            // Remove any button elements that might have been injected
            const buttons = tempDiv.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
            buttons.forEach(btn => btn.remove());
            
            block.html = tempDiv.textContent || tempDiv.innerText || '';
          }
        }
        block.type='quote'; 
        this.render(surface, idx, true); 
        surface.dispatchEvent(new Event('input')); 
        return; 
      }
      else if (t === window.EditorCore.Commands.CODE){ 
        if (applyToSelectedBlocks((bi)=>{ 
          // Save current content before converting
          const currentEl = surface.children[bi];
          if (currentEl) {
            const contentEl = currentEl.querySelector('.ed-li-content') || currentEl.querySelector('.ed-block-content') || currentEl;
            if (contentEl.tagName === 'P' || contentEl.tagName.startsWith('H') || contentEl.tagName === 'BLOCKQUOTE') {
              surface.__blocks[bi].html = contentEl.textContent || '';
            } else {
              // For complex blocks, extract clean text content
              const tempDiv = document.createElement('div');
              tempDiv.innerHTML = contentEl.innerHTML || '';
              
              // Remove any button elements that might have been injected
              const buttons = tempDiv.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
              buttons.forEach(btn => btn.remove());
              
              surface.__blocks[bi].html = tempDiv.textContent || tempDiv.innerText || '';
            }
          }
          surface.__blocks[bi].type='code'; 
          
          // Render this specific block after conversion
          this.render(surface, bi, true);
        })) return; 
        
        // Save current content before converting
        const currentEl = surface.children[idx];
        if (currentEl) {
          const contentEl = currentEl.querySelector('.ed-li-content') || currentEl.querySelector('.ed-block-content') || currentEl;
          if (contentEl.tagName === 'P' || contentEl.tagName.startsWith('H') || contentEl.tagName === 'BLOCKQUOTE') {
            // For text blocks, always use textContent to avoid HTML injection
            block.html = contentEl.textContent || '';
          } else {
            // For complex blocks, extract clean text content
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = contentEl.innerHTML || '';
            
            // Remove any button elements that might have been injected
            const buttons = tempDiv.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
            buttons.forEach(btn => btn.remove());
            
            block.html = tempDiv.textContent || tempDiv.innerText || '';
          }
        }
        block.type='code'; 
        this.render(surface, idx, true); 
        surface.dispatchEvent(new Event('input')); 
        return; 
      }
      else if (t === window.EditorCore.Commands.PARAGRAPH){ 
        if (applyToSelectedBlocks((bi)=>{ 
          // Save current content before converting
          const currentEl = surface.children[bi];
          if (currentEl) {
            const contentEl = currentEl.querySelector('.ed-li-content') || currentEl.querySelector('.ed-block-content') || currentEl;
            if (contentEl.tagName === 'LI') {
              // Convert list items to text
              const items = Array.from(currentEl.parentElement.querySelectorAll('li')).map(li => li.textContent.trim()).filter(Boolean);
              surface.__blocks[bi].html = items.join('\n');
            } else if (contentEl.tagName === 'BLOCKQUOTE') {
              surface.__blocks[bi].html = contentEl.textContent || '';
            } else if (contentEl.tagName === 'CODE') {
              surface.__blocks[bi].html = contentEl.textContent || '';
            } else {
              // For complex blocks, extract clean text content
              const tempDiv = document.createElement('div');
              tempDiv.innerHTML = contentEl.innerHTML || '';
              
              // Remove any button elements that might have been injected
              const buttons = tempDiv.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
              buttons.forEach(btn => btn.remove());
              
              surface.__blocks[bi].html = tempDiv.textContent || tempDiv.innerText || '';
            }
          }
          surface.__blocks[bi].type='p'; 
          
          // Render this specific block after conversion
          this.render(surface, bi, true);
        })) return; 
        
        // Save current content before converting
        const currentEl = surface.children[idx];
        if (currentEl) {
          const contentEl = currentEl.querySelector('.ed-li-content') || currentEl.querySelector('.ed-block-content') || currentEl;
          if (contentEl.tagName === 'LI') {
            // Convert list items to text
            const items = Array.from(currentEl.parentElement.querySelectorAll('li')).map(li => li.textContent.trim()).filter(Boolean);
            block.html = items.join('\n');
          } else if (contentEl.tagName === 'BLOCKQUOTE') {
            block.html = contentEl.textContent || '';
          } else if (contentEl.tagName === 'CODE') {
            block.html = contentEl.textContent || '';
          } else {
            // For complex blocks, extract clean text content
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = contentEl.innerHTML || '';
            
            // Remove any button elements that might have been injected
            const buttons = tempDiv.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
            buttons.forEach(btn => btn.remove());
            
            block.html = tempDiv.textContent || tempDiv.innerText || '';
          }
        }
        block.type='p'; 
        this.render(surface, idx, true); 
        surface.dispatchEvent(new Event('input')); 
        return; 
      }
      else if (t === window.EditorCore.Commands.ALIGN){ if (applyToSelectedBlocks((bi)=>{ surface.__blocks[bi].align = intent.value; })) return; block.align = intent.value; this.render(surface, idx, true); surface.dispatchEvent(new Event('input')); return; }
      else if (t === window.EditorCore.Commands.CLEAR){ 
        const el = surface.children[idx]?.querySelector('.ed-li-content') || surface.children[idx]?.querySelector('.ed-block-content') || surface.children[idx]; 
        if (el){ 
          if (el.tagName === 'P' || el.tagName.startsWith('H') || el.tagName === 'BLOCKQUOTE') {
            // For text blocks, always use textContent to avoid HTML injection
            el.textContent = el.textContent || '';
            surface.__blocks[idx].html = el.textContent;
          } else {
            // For complex blocks, extract text content from HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = el.innerHTML || '';
            const cleanText = tempDiv.textContent || tempDiv.innerText || '';
            el.innerHTML = cleanText;
            surface.__blocks[idx].html = cleanText;
          }
          surface.__blocks[idx].textColor=''; 
          surface.__blocks[idx].bgColor=''; 
        } 
        surface.dispatchEvent(new Event('input')); 
        return; 
      }
      else if (t === window.EditorCore.Commands.BOLD){ 
        this.restoreSelection(surface); 
        try {
          document.execCommand('styleWithCSS', false, true); 
          document.execCommand('bold'); 
        } catch(e) {
          // Fallback: manually wrap selection in <strong>
          this.wrapSelectionInTag(surface, 'strong');
        }
        surface.dispatchEvent(new Event('input')); 
        setTimeout(()=>this.positionBubble(surface),0); 
        return; 
      }
      else if (t === window.EditorCore.Commands.ITALIC){ this.restoreSelection(surface); document.execCommand('styleWithCSS', false, true); document.execCommand('italic'); surface.dispatchEvent(new Event('input')); setTimeout(()=>this.positionBubble(surface),0); return; }
      else if (t === window.EditorCore.Commands.UNDERLINE){ this.restoreSelection(surface); document.execCommand('styleWithCSS', false, true); document.execCommand('underline'); surface.dispatchEvent(new Event('input')); setTimeout(()=>this.positionBubble(surface),0); return; }
      else if (t === window.EditorCore.Commands.STRIKE){ this.restoreSelection(surface); document.execCommand('styleWithCSS', false, true); document.execCommand('strikeThrough'); surface.dispatchEvent(new Event('input')); setTimeout(()=>this.positionBubble(surface),0); return; }
      else if (t === window.EditorCore.Commands.LINK){ this.openLinkDialog(surface, idx); return; }
      else if (t === window.EditorCore.Commands.CLEAR_LINK){ this.restoreSelection(surface); document.execCommand('unlink', false, null); surface.dispatchEvent(new Event('input')); return; }
      else if (t === window.EditorCore.Commands.IMAGE){ this.openImageDialog(surface, idx); return; }
      else if (t === window.EditorCore.Commands.EMBED){ this.openEmbedDialog(surface, idx); return; }
      else if (t === window.EditorCore.Commands.CALLOUT){ const v=(intent&&intent.variant)||'note'; surface.__blocks[idx].type='callout'; surface.__blocks[idx].className=`callout-${v}`; if (!surface.__blocks[idx].html) surface.__blocks[idx].html='<strong>Note:</strong> '; this.render(surface, idx, true); surface.dispatchEvent(new Event('input')); return; }
      else if (t === window.EditorCore.Commands.VIDEO){ this.openVideoDialog(surface, idx); return; }
      else if (t === window.EditorCore.Commands.AUDIO){ this.openAudioDialog(surface, idx); return; }
      else if (t === window.EditorCore.Commands.FILE){ this.openFileDialog(surface, idx); return; }
      else if (t === window.EditorCore.Commands.TABLE){ this.openTableDialog(surface, idx); return; }
      else if (t === window.EditorCore.Commands.GRID){
        // Insert a simple grid (2 cols) at caret using new lightweight grid
        try{
          // For new structure: content is the block element itself for most types
        const el = surface.children[idx]?.querySelector('.ed-block-content') || surface.children[idx];
          if (!el) return; el.focus();
          const sel = window.getSelection(); if (!sel || sel.rangeCount===0){
            const g = (window.HaruEditor && window.HaruEditor.Grid && window.HaruEditor.Grid.createGrid) ? window.HaruEditor.Grid.createGrid(2) : null;
            if (g){ el.appendChild(g); surface.__blocks[idx].html = el.innerHTML; this.render(surface, idx, true); }
            return;
          }
          const r = sel.getRangeAt(0);
          const g = (window.HaruEditor && window.HaruEditor.Grid && window.HaruEditor.Grid.createGrid) ? window.HaruEditor.Grid.createGrid(2) : null;
          if (g){ r.deleteContents(); r.insertNode(g); r.setStart(g, 0); r.setEnd(g, 0); sel.removeAllRanges(); sel.addRange(r); surface.__blocks[idx].html = el.innerHTML; this.render(surface, idx, true); }
        }catch(e){}
        return;
      }
      else if (t === 'insert-block'){ const el = surface.children[idx] || surface.lastElementChild; this.openInsertMenu(surface, el, null, 'insert'); return; }
      else if (t === window.EditorCore.Commands.TEXT_COLOR){ this.restoreSelection(surface); document.execCommand('foreColor', false, intent.value); surface.dispatchEvent(new Event('input')); return; }
      else if (t === window.EditorCore.Commands.BG_COLOR){ this.restoreSelection(surface); try{ document.execCommand('hiliteColor', false, intent.value); }catch(e){ document.execCommand('backColor', false, intent.value); } surface.dispatchEvent(new Event('input')); return; }
      else if (t === 'insert-divider'){ const after={ id:String(Date.now()+Math.random()), type:'html', html:'<hr/>', indent:0 }; surface.__blocks.splice(idx+1,0,after); this.render(surface, idx+1, true); surface.dispatchEvent(new Event('input')); return; }
    });

    // Multi-select: click behavior (Cmd/Ctrl toggles, Shift selects range)
    surface.addEventListener('click', (ev)=>{
      const blk = ev.target.closest('.block-control');
      if (!blk){ 
        if (!ev.metaKey && !ev.ctrlKey && !ev.shiftKey){ 
          surface.__selectedIds = []; 
          this.render(surface); 
          
          // Check if click is in empty space at bottom to create new block
          if (this.isClickInEmptyBottomSpace(surface, ev)) {
            this.createNewBlockAtBottom(surface);
          }
        } 
        return; 
      }
      const id = blk.getAttribute('data-id');
      const ids = surface.__selectedIds || [];
      if (ev.metaKey || ev.ctrlKey){
        const pos = ids.indexOf(id);
        if (pos>=0) ids.splice(pos,1); else ids.push(id);
        surface.__selectedIds = ids;
        this.render(surface);
        return;
      }
      if (ev.shiftKey){
        const allIds = surface.__blocks.map(b=>b.id);
        const anchor = surface.__lastSelectId || id;
        const a = allIds.indexOf(anchor);
        const b = allIds.indexOf(id);
        if (a>=0 && b>=0){
          const [from,to] = a<b ? [a,b] : [b,a];
          surface.__selectedIds = allIds.slice(from, to+1);
          this.render(surface);
          return;
        }
      }
      // Plain click inside block: update selection but DO NOT re-render to avoid losing caret
      surface.__selectedIds = [id];
      surface.__lastSelectId = id;
      // Manually toggle selected class for visual feedback without re-render
      Array.from(surface.children).forEach(child=> child.classList.remove('selected'));
      blk.classList.add('selected');
    }, true);

    // Multi-select: keyboard shortcuts (Delete to remove, Cmd/Ctrl+D to duplicate)
    surface.addEventListener('keydown', (ev)=>{
      const ids = surface.__selectedIds || [];
      if (ids.length <= 1) return;
      if (ev.key==='Backspace' || ev.key==='Delete'){
        ev.preventDefault();
        const keep = surface.__blocks.filter(b=> !ids.includes(b.id));
        surface.__blocks = keep.length ? keep : [ this.makeModel('p','') ];
        surface.__selectedIds = [];
        this.render(surface, 0, true);
      } else if ((ev.key==='d' || ev.key==='D') && (ev.metaKey || ev.ctrlKey)){
        ev.preventDefault();
        const order = surface.__blocks.map(b=>b.id);
        const sorted = ids.slice().sort((a,b)=> order.indexOf(a)-order.indexOf(b));
        const lastIndex = Math.max(...sorted.map(id=> order.indexOf(id)));
        const clones = sorted.map(id=>{ const src = surface.__blocks.find(b=> b.id===id); return { ...src, id: String(Date.now()+Math.random()) }; });
        surface.__blocks.splice(lastIndex+1, 0, ...clones);
        surface.__selectedIds = clones.map(c=>c.id);
        this.render(surface, lastIndex+1, true);
      }
    }, true);


  },
  // Helper function to wrap selection in HTML tags when execCommand fails
  wrapSelectionInTag(surface, tagName) {
    try {
      const selection = window.getSelection();
      if (!selection || selection.rangeCount === 0) return;
      
      const range = selection.getRangeAt(0);
      const selectedContent = range.extractContents();
      
      const wrapper = document.createElement(tagName);
      wrapper.appendChild(selectedContent);
      
      range.insertNode(wrapper);
      selection.removeAllRanges();
      selection.addRange(range);
      
      // Update the block content
      const activeEl = document.activeElement.closest('.block-control');
      if (activeEl) {
        const idx = this.indexOfEl(surface, activeEl);
        if (idx >= 0) {
          const contentEl = activeEl.querySelector('.ed-li-content') || activeEl.querySelector('.ed-block-content') || activeEl;
          if (contentEl.tagName === 'P' || contentEl.tagName.startsWith('H') || contentEl.tagName === 'BLOCKQUOTE') {
            surface.__blocks[idx].html = contentEl.textContent;
          } else {
            surface.__blocks[idx].html = contentEl.innerHTML;
          }
        }
      }
    } catch(e) {
      // Failed to wrap selection in tag: ${e}
    }
  },
  
  // Check if click is in empty space at the bottom of the editor
  isClickInEmptyBottomSpace(surface, ev) {
    const surfaceRect = surface.getBoundingClientRect();
    const clickY = ev.clientY;
    const surfaceBottom = surfaceRect.bottom;
    
    // If click is below the surface or very close to bottom, consider it empty space
    if (clickY >= surfaceBottom - 20) {
      return true;
    }
    
    // Check if click is below the last block
    const lastBlock = surface.lastElementChild;
    if (lastBlock && lastBlock.classList.contains('block-control')) {
      const lastBlockRect = lastBlock.getBoundingClientRect();
      const lastBlockBottom = lastBlockRect.bottom;
      
      // If click is below the last block with some margin, it's empty space
      if (clickY > lastBlockBottom + 10) {
        return true;
      }
    }
    
    return false;
  },
  
  // Create a new paragraph block at the bottom of the editor
  createNewBlockAtBottom(surface) {
    // Create a new paragraph block
    const newBlock = this.makeModel('p', '');
    
    // Add it to the blocks array
    surface.__blocks.push(newBlock);
    
    // Render the new block
    this.render(surface, surface.__blocks.length - 1, true);
    
    // Focus the new block
    const newBlockEl = surface.lastElementChild;
    if (newBlockEl && newBlockEl.classList.contains('block-control')) {
      // Place caret at the beginning of the new block
      this.placeCaretAtEnd(newBlockEl);
      newBlockEl.focus();
      
      // Update selection to the new block
      surface.__selectedIds = [newBlock.id];
      surface.__lastSelectId = newBlock.id;
      
      // Add selected class for visual feedback
      Array.from(surface.children).forEach(child => child.classList.remove('selected'));
      newBlockEl.classList.add('selected');
    }
  },
  destroy(){ this.unsubscribe?.(); },
  makeModel(type, html){ return { id: String(Date.now()+Math.random()), type, html: html||'', indent: 0 }; },
  applyTypeEl(blockEl, type){ blockEl.setAttribute('data-block-type', type); blockEl.setAttribute('placeholder', type==='p'?'Paragraph':type.toUpperCase()); },
  openInsertMenu(surface, target, evt, mode){
    this.closeInsertMenu(surface);
    const menu = document.createElement('div');
    menu.className = 'ed-block-insert';
    const items = ['p','h1','h2','h3','ul','ol','quote','code','html','todo','callout (note)','callout (info)','callout (warn)','callout (tip)','callout (success)','callout (error)','image','embed','video','audio','file','classes'];
    // focus management: remember focused block content element
    const beforeFocus = document.activeElement;
    const input = document.createElement('input'); input.type='text'; input.placeholder='Type to filter…'; input.style.marginBottom='6px'; input.className='ed-input';
    menu.appendChild(input);
    const list = document.createElement('div'); list.className='ed-list';
    const draw=(q='')=>{ list.innerHTML=''; items.filter(t=>t.includes(q)).forEach((t, idx)=>{
      const btn = document.createElement('button'); btn.type='button'; btn.textContent=t; btn.className='ed-btn';
      if (idx===0) btn.classList.add('active');
      const handler = (ev)=>{
        ev.preventDefault(); ev.stopPropagation();
        const i = this.indexOfEl(surface, target);
        if (t === 'classes' || t === 'add class') { this.promptEditClasses(surface, i); this.closeInsertMenu(surface); return; }
        if (t === 'image'){ this.openImageDialog(surface, i); this.closeInsertMenu(surface); return; }
        if (t === 'embed'){ this.openEmbedDialog(surface, i); this.closeInsertMenu(surface); return; }
        if (t === 'video'){ this.openVideoDialog(surface, i); this.closeInsertMenu(surface); return; }
        if (t === 'audio'){ this.openAudioDialog(surface, i); this.closeInsertMenu(surface); return; }
        if (t === 'file'){ this.openFileDialog(surface, i); this.closeInsertMenu(surface); return; }
        if (mode === 'insert') {
          let type = t; let initialHtml = '';
          if (t==='todo'){ initialHtml = '<input type="checkbox"/> <span></span>'; }
          else if (t.startsWith('callout')){ type='callout'; initialHtml = '<strong>Note:</strong> '; }
          const newBlock = this.makeModel(type, initialHtml);
          if (t.startsWith('callout')){
            const variant = t.match(/\(([^)]+)\)/)?.[1];
            if (variant){ newBlock.className = `callout-${variant}`; }
          }
          surface.__blocks.splice(i+1,0,newBlock);
          this.render(surface, i+1, true);
        } else {
          let type = t;
          if (t.startsWith('callout')){ type='callout'; const variant=t.match(/\(([^)]+)\)/)?.[1]; if (variant){ surface.__blocks[i].className=`callout-${variant}`; } }
          surface.__blocks[i].type = type; // keep content so it can be reverted with 'p'
          if (type==='todo' && !surface.__blocks[i].html) surface.__blocks[i].html = '<input type="checkbox"/> <span></span>';
          this.render(surface, i, true);
        }
        this.closeInsertMenu(surface);
        // restore focus back to content after menu closes
        // restore focus back to content after menu closes
        setTimeout(()=>{ 
          const newEl = surface.children[Math.min(i + (mode==='insert'?1:0), surface.children.length-1)]; 
          // For new structure: content is the block element itself for most types
          const c=newEl && newEl.querySelector('.ed-block-content'); 
          (c||newEl)?.focus?.(); 
          surface.dispatchEvent(new Event('input')); 
        }, 0);
      };
      btn.addEventListener('mousedown', handler);
      btn.addEventListener('click', handler);
      list.appendChild(btn);
    }); };
    draw();
    input.addEventListener('input', ()=> draw(input.value.toLowerCase()));
    input.addEventListener('keydown', (e)=>{
      let active = list.querySelector('.ed-btn.active') || list.querySelector('.ed-btn');
      if (e.key==='ArrowDown'){ e.preventDefault(); const next = active?.nextElementSibling; if (next){ active.classList.remove('active'); next.classList.add('active'); } }
      if (e.key==='ArrowUp'){ e.preventDefault(); const prev = active?.previousElementSibling; if (prev){ active.classList.remove('active'); prev.classList.add('active'); } }
      if (e.key==='Enter'){ e.preventDefault(); active?.dispatchEvent(new MouseEvent('click',{bubbles:true})); }
      if (e.key==='Escape'){ e.preventDefault(); this.closeInsertMenu(surface); (beforeFocus||surface).focus?.(); }
    });
    menu.appendChild(list);
    surface.appendChild(menu);
    const caretRect = this.caretRect(target);
    const srect = surface.getBoundingClientRect();
    const prefLeft = caretRect ? caretRect.left - srect.left : (evt && evt.target ? evt.target.getBoundingClientRect().left - srect.left : 0);
    const prefTop = caretRect ? caretRect.top - srect.top : (evt && evt.target ? evt.target.getBoundingClientRect().bottom - srect.top : 0);
    menu.style.left = Math.max(8, Math.min(prefLeft, surface.clientWidth - 340)) + 'px';
    menu.style.top = Math.max(8, Math.min(prefTop + 6, surface.clientHeight - 260)) + 'px';
    menu.style.display = 'flex';
    input.focus();
    const closeOnOutside = (ev)=>{ if (!menu.contains(ev.target)) { this.closeInsertMenu(surface); document.removeEventListener('click', closeOnOutside, true); } };
    setTimeout(()=> document.addEventListener('click', closeOnOutside, true),0);
  },
  closeInsertMenu(surface){ const m = surface.querySelector('.ed-block-insert'); if (m) m.remove(); },
  // Rendering from model with simplified semantic HTML structure
  render(surface, focusIndex = 0, focus = false, caretPos = 'start'){
    surface.innerHTML = '';
    let runningOl = 0; // sequential numbering across consecutive ol blocks
    surface.__blocks.forEach((b, i)=>{
      // Create semantic HTML element based on block type
      let el, contentEl;
      
      if (b.type === 'paragraph' || b.type === 'p') {
        el = document.createElement('p');
        contentEl = el;
      } else if (b.type.startsWith('heading') || b.type.startsWith('h')) {
        const level = b.type.replace('heading', '').replace('h', '') || '1';
        el = document.createElement(`h${level}`);
        contentEl = el;
      } else if (b.type === 'quote') {
        el = document.createElement('blockquote');
        contentEl = el;
        // Set content for quote blocks
        if (b.html && b.html.trim()) {
          el.textContent = b.html;
        } else {
          el.textContent = '';
        }
      } else if (b.type === 'code') {
        el = document.createElement('pre');
        el.setAttribute('data-lang', 'text');
        contentEl = document.createElement('code');
        el.appendChild(contentEl);
        // Set content for code blocks
        if (b.html && b.html.trim()) {
          contentEl.textContent = b.html;
        } else {
          contentEl.textContent = '';
        }
      } else if (b.type === 'image') {
        el = document.createElement('figure');
        contentEl = el;
        // Create image structure
        const img = document.createElement('img');
        img.src = '';
        img.alt = '';
        img.style.cssText = 'max-width: 100%; height: auto;';
        const figcaption = document.createElement('figcaption');
        figcaption.contentEditable = 'true';
        figcaption.textContent = '';
        el.appendChild(img);
        el.appendChild(figcaption);
      } else if (b.type === 'ul' || b.type === 'ol') {
        el = document.createElement(b.type);
        contentEl = el;
        if (b.type === 'ol') runningOl += 1; else runningOl = 0;
        
        // Handle content conversion for lists - keep as single block
        if (b.html && b.html.trim()) {
          // Check if the content is already HTML (contains <li> tags)
          if (b.html.includes('<li>')) {
            // Clean the HTML to remove any injected button code
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = b.html;
            
            // Remove any button elements that might have been injected
            const buttons = tempDiv.querySelectorAll('button, .blk-menu, .ed-insert-btn, .blk-handle');
            buttons.forEach(btn => btn.remove());
            
            // Extract only the list items
            const listItems = tempDiv.querySelectorAll('li');
            if (listItems.length > 0) {
              listItems.forEach(li => {
                const cleanLi = document.createElement('li');
                cleanLi.contentEditable = 'true';
                cleanLi.textContent = li.textContent || '';
                el.appendChild(cleanLi);
              });
            } else {
              // Fallback: create default list item
              const li = document.createElement('li');
              li.contentEditable = 'true';
              li.textContent = '';
              el.appendChild(li);
            }
          } else {
            // Convert plain text to list items
            const lines = b.html.split('\n').filter(line => line.trim());
            if (lines.length > 0) {
              lines.forEach(line => {
                const li = document.createElement('li');
                li.contentEditable = 'true';
                li.textContent = line.trim();
                el.appendChild(li);
              });
            } else {
              // Create default list item
              const li = document.createElement('li');
              li.contentEditable = 'true';
              li.textContent = '';
              el.appendChild(li);
            }
          }
        } else {
          // Create default list item
          const li = document.createElement('li');
          li.contentEditable = 'true';
          li.textContent = '';
          el.appendChild(li);
        }
        
        // IMPORTANT: Set contenteditable on the list element itself, not individual items
        el.setAttribute('contenteditable', 'true');
      } else if (b.type === 'table') {
        el = document.createElement('table');
        contentEl = el;
        // Create table structure
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        const th1 = document.createElement('th');
        th1.contentEditable = 'true';
        th1.textContent = '';
        const th2 = document.createElement('th');
        th2.contentEditable = 'true';
        th2.textContent = '';
        headerRow.appendChild(th1);
        headerRow.appendChild(th2);
        thead.appendChild(headerRow);
        
        const tbody = document.createElement('tbody');
        const bodyRow = document.createElement('tr');
        const td1 = document.createElement('td');
        td1.contentEditable = 'true';
        td1.textContent = 'Cell 1';
        const td2 = document.createElement('td');
        td2.contentEditable = 'true';
        td2.textContent = 'Cell 2';
        bodyRow.appendChild(td1);
        bodyRow.appendChild(td2);
        tbody.appendChild(bodyRow);
        
        el.appendChild(thead);
        el.appendChild(tbody);
      } else if (b.type === 'callout') {
        // Handle callout blocks
        el = document.createElement('div');
        el.className = 'callout';
        if (b.className) {
          el.classList.add(b.className);
        }
        contentEl = el;
        // Set content for callout blocks
        if (b.html && b.html.trim()) {
          el.innerHTML = b.html;
        } else {
          el.innerHTML = '<strong>Note:</strong> ';
        }
      } else {
        // Fallback for other types (html, etc.)
        el = document.createElement('div');
        contentEl = document.createElement('div');
        contentEl.className = 'ed-block-content';
        el.appendChild(contentEl);
      }
      
              // Add common attributes and classes
        el.className = el.className ? el.className + ' block-control' : 'block-control';
        el.setAttribute('data-id', b.id);
        el.setAttribute('data-block-type', b.type);
        
        // Set contenteditable based on block type
        if (b.type === 'ul' || b.type === 'ol') {
          // For lists, set contenteditable on the list element itself
          el.setAttribute('contenteditable', 'true');
          // Remove contenteditable from individual list items
          el.querySelectorAll('li').forEach(li => {
            li.removeAttribute('contenteditable');
          });
        } else {
          // For other blocks, set contenteditable normally
          el.setAttribute('contenteditable', 'true');
        }
        
        // Force cursor styling for editable blocks (after contenteditable is set)
        if (el.getAttribute('contenteditable') === 'true') {
          el.style.cursor = 'text';
        }
      
      if (Array.isArray(surface.__selectedIds) && surface.__selectedIds.includes(b.id)) el.classList.add('selected');
      if (b.className){ el.classList.add(...String(b.className).split(/\s+/).filter(Boolean)); }
      if (b.align){ el.setAttribute('data-align', b.align); }
      if (b.textColor){ el.setAttribute('data-text-color', b.textColor); el.style.color = b.textColor; }
      if (b.bgColor){ el.setAttribute('data-bg-color', b.bgColor); el.style.backgroundColor = b.bgColor; }
      
      // Add class badge if needed
      if (b.className){ 
        const badge=document.createElement('div'); 
        badge.className='ed-class-badge'; 
        badge.textContent=String(b.className); 
        const close=document.createElement('span'); 
        close.className='x'; 
        close.textContent='×'; 
        close.title='Remove classes'; 
        close.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation(); surface.__blocks[i].className=''; this.render(surface, i, true); }); 
        badge.appendChild(close); 
        el.appendChild(badge); 
      }
      
      // Add block controls (drag handle and insert button)
      const handle = document.createElement('span');
      handle.className = 'blk-handle';
      handle.textContent = '⋮⋮';
      handle.setAttribute('contenteditable', 'false');
      handle.style.cssText = 'cursor: move; user-select: none; margin-right: 8px; color: #666;';
      handle.addEventListener('mousedown', (e)=>{ e.preventDefault(); this.beginDrag(surface, i, e); });
      el.insertBefore(handle, el.firstChild);
      
      const insertBtn = document.createElement('button');
      insertBtn.className = 'ed-insert-btn';
      insertBtn.textContent = '+';
      insertBtn.type = 'button';
      insertBtn.setAttribute('contenteditable', 'false');
      insertBtn.addEventListener('click', ()=>{ this.openInsertMenu(surface, el, null, 'insert'); });
      el.insertBefore(insertBtn, el.firstChild);
      
      // Set content for text-based blocks
      if (contentEl && contentEl === el && b.type !== 'ul' && b.type !== 'ol' && b.type !== 'table' && b.type !== 'image') {
        if (b.type === 'code') {
          contentEl.textContent = b.html || '';
        } else {
          const defaultContent = this.getDefaultContent(b.type);
          // For text blocks, ensure we only use text content, not HTML
          let cleanContent = '';
          if (b.html && b.html.trim()) {
            // Strip any HTML tags to get clean text
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = b.html;
            cleanContent = tempDiv.textContent || tempDiv.innerText || '';
          }
          contentEl.textContent = cleanContent || defaultContent;
          // Ensure the content is visible
          if (!contentEl.textContent.trim()) {
            contentEl.textContent = defaultContent;
          }
        }
      }
      
      // Add event listeners for content editing
      if (b.type === 'ul' || b.type === 'ol') {
        const listItems = el.querySelectorAll('li');
        listItems.forEach(li => {
          li.addEventListener('input', ()=>{ 
            surface.__blocks[i].html = el.innerHTML; 
            this.historyPush(surface); 
          });
          li.addEventListener('paste', (ev)=> this.handlePaste(ev, surface, i));
        });
      } else {
        // For all other block types (paragraphs, headings, quotes, code, etc.)
        el.addEventListener('input', ()=>{ 
          surface.__blocks[i].html = el.textContent; 
          this.historyPush(surface); 
        });
        el.addEventListener('paste', (ev)=> this.handlePaste(ev, surface, i));
      }
      
      // Add block menu button
      const menuBtn = document.createElement('button');
      menuBtn.className = 'blk-menu';
      menuBtn.type = 'button';
      menuBtn.title = 'Block menu';
      menuBtn.textContent = '⋯';
      menuBtn.setAttribute('contenteditable', 'false');
      menuBtn.style.cssText = 'background: none; border: none; cursor: pointer; padding: 2px 6px; color: #666; font-size: 16px; margin-left: 8px;';
      menuBtn.addEventListener('click', (ev)=>{ ev.preventDefault(); ev.stopPropagation(); this.openBlockMenu(surface, i, menuBtn); });
      el.appendChild(menuBtn);
      
      // Indentation styling
      if (typeof b.indent === 'number') { 
        el.dataset.indent = String(b.indent); 
        el.style.setProperty('--indent', String(b.indent)); 
      }
      
      surface.appendChild(el);
    });
    // Recreate bubble after render since innerHTML clearing removes it
    this.ensureBubble(surface);
    if (focus){
      const el = surface.children[focusIndex];
      if (el){
        // Find the appropriate content element to focus
        let content = el;
        if (el.tagName === 'PRE') {
          content = el.querySelector('code') || el;
        } else if (el.tagName === 'FIGURE') {
          content = el.querySelector('figcaption') || el;
        } else if (el.tagName === 'UL' || el.tagName === 'OL') {
          content = el.querySelector('li') || el;
        } else if (el.tagName === 'TABLE') {
          content = el.querySelector('th, td') || el;
        }
        
        // Defer focus to end of event loop and guard against re-render focus loss
        setTimeout(()=>{
          // Only focus if surface still exists and element is still in DOM
          if (!document.body.contains(surface) || !surface.contains(el)) return;
          content.focus(); 
          if (caretPos==='end') this.placeCaretAtEnd(content);
        }, 0);
      }
    }
  },

  // Helper function to get default content for block types
  getDefaultContent(type) {
    const defaults = {
      paragraph: '',
      p: '',
      heading1: 'Heading 1',
      heading2: 'Heading 2', 
      heading3: 'Heading 3',
      heading4: 'Heading 4',
      heading5: 'Heading 5',
      heading6: 'Heading 6',
      h1: 'Heading 1',
      h2: 'Heading 2',
      h3: 'Heading 3', 
      h4: 'Heading 4',
      h5: 'Heading 5',
      h6: 'Heading 6',
      quote: '',
      code: ''
    };
    return defaults[type] || '';
  },

  openBlockMenu(surface, index, anchor){
    // Close existing
    this.closeInsertMenu(surface);
    const menu = document.createElement('div'); menu.className='ed-mini-menu';
    function item(label, action){ const b=document.createElement('button'); b.type='button'; b.textContent=label; b.className='ed-btn'; b.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation(); action(); }); return b; }
    menu.appendChild(item('Duplicate', ()=>{
      const blk = surface.__blocks[index]; const clone = {...blk, id:String(Date.now()+Math.random())}; surface.__blocks.splice(index+1,0,clone); surface.__selectedIds=[clone.id];
      surface.__lastIdx = index+1; this.render(surface, index+1, true); this.historyPush(surface);
    }));
    menu.appendChild(item('Delete', ()=>{
      if (surface.__blocks.length>1){ surface.__blocks.splice(index,1); this.render(surface, Math.max(0,index-1), true, 'end'); } else { surface.__blocks[0]={...surface.__blocks[0], type:'p', html:''}; this.render(surface,0,true); }
      this.historyPush(surface);
    }));
    const blk = surface.__blocks[index];
    if (blk && blk.type==='callout'){
      const variants = ['note','info','warn','tip','success','error'];
      variants.forEach(v=> menu.appendChild(item(`Callout: ${v}`, ()=>{ surface.__blocks[index].className = `callout-${v}`; this.render(surface, index, true); })));
    }
    menu.appendChild(item('Properties', ()=>{ this.openBlockPropsDialog(surface, index); }));
    surface.appendChild(menu);
    const r = anchor.getBoundingClientRect(); const s = surface.getBoundingClientRect();
    menu.style.left = Math.max(8, Math.min(r.right - s.left - 160, surface.clientWidth - 170)) + 'px';
    menu.style.top = Math.max(8, r.bottom - s.top + surface.scrollTop) + 'px';
    menu.style.display='flex';
    const closeOnOutside = (ev)=>{ if (!menu.contains(ev.target) && ev.target!==anchor){ menu.remove(); document.removeEventListener('click', closeOnOutside, true); }};
    setTimeout(()=> document.addEventListener('click', closeOnOutside, true),0);
  },
  openToolMenu(surface, anchor, items){
    this.closeInsertMenu(surface);
    const menu = document.createElement('div'); menu.className='ed-mini-menu';
    items.forEach(it=>{
      if (it.type==='color'){
        const row=document.createElement('div'); row.style.display='flex'; row.style.alignItems='center'; row.style.gap='6px';
        const label=document.createElement('span'); label.textContent=it.title||'Color'; label.style.fontSize='12px'; label.style.color='var(--text-secondary)';
        const input=document.createElement('input'); input.type='color'; input.addEventListener('input', ()=> it.onInput && it.onInput(input.value));
        row.appendChild(label); row.appendChild(input); menu.appendChild(row);
      } else {
        const b=document.createElement('button'); b.type='button'; b.textContent=it.label; b.className='ed-btn'; b.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation(); it.run && it.run(); menu.remove(); }); menu.appendChild(b);
      }
    });
    surface.appendChild(menu);
    const r = anchor.getBoundingClientRect(); const s = surface.getBoundingClientRect();
    menu.style.left = Math.max(8, Math.min(r.right - s.left - 160, surface.clientWidth - 170)) + 'px';
    menu.style.top = Math.max(8, r.bottom - s.top + surface.scrollTop) + 'px';
    menu.style.display='flex';
    const closeOnOutside = (ev)=>{ if (!menu.contains(ev.target) && ev.target!==anchor){ menu.remove(); document.removeEventListener('click', closeOnOutside, true); }};
    setTimeout(()=> document.addEventListener('click', closeOnOutside, true),0);
    return menu;
  },
  bindGridReorder(surface, content, blockIndex){
    try{
      const grids = Array.from(content.querySelectorAll('.ed-grid'));
      grids.forEach((g)=>{
        // Always support drag reordering via a small handle; no menu toggle required
        const cells = Array.from(g.querySelectorAll('.ed-grid-cell'));
        cells.forEach((c)=>{
          // Ensure content wrapper exists first
          let wrap = c.querySelector('.ed-grid-cell-content');
          if (!wrap){
            wrap = document.createElement('div');
            wrap.className='ed-grid-cell-content';
            c.appendChild(wrap);
          }
          // Ensure drag handle exists (absolutely positioned)
          let h = c.querySelector('.ed-grid-col-drag');
          if (!h){
            h = document.createElement('span');
            h.className='ed-grid-col-drag';
            h.title='Drag to reorder column';
            // Prevent caret from entering the handle
            h.setAttribute('contenteditable','false');
            h.setAttribute('aria-hidden','true');
            h.setAttribute('tabindex','-1');
            h.addEventListener('mousedown', (ev)=>{ ev.preventDefault(); });
            c.appendChild(h);
          } else {
            // Ensure existing handle cannot receive caret
            h.setAttribute('contenteditable','false');
            h.setAttribute('aria-hidden','true');
            h.setAttribute('tabindex','-1');
          }
          // If any content accidentally lives inside the handle, move it back into content wrapper
          if (h.childNodes && h.childNodes.length){
            const childNodes = Array.from(h.childNodes);
            childNodes.forEach((n)=>{
              h.removeChild(n);
              if (n.nodeType === Node.TEXT_NODE){
                const text = n.nodeValue;
                if (text && text.trim().length){
                  const p=document.createElement('p');
                  p.textContent=text;
                  wrap.appendChild(p);
                }
              } else {
                wrap.appendChild(n);
              }
            });
          }
          // Move any stray nodes into the content wrapper (never into handle)
          const toMove = Array.from(c.childNodes).filter(n=> n!==wrap && n!==h);
          toMove.forEach((n)=>{
            if (n.nodeType === Node.TEXT_NODE){
              const text = n.nodeValue;
              if (text && text.trim().length){
                const p=document.createElement('p');
                p.textContent=text;
                wrap.appendChild(p);
              }
              n.remove();
            } else {
              wrap.appendChild(n);
            }
          });
        });
        // Inject container-level separators at exact boundaries; add button + resize
        const cellsLive = Array.from(g.querySelectorAll('.ed-grid-cell'));
        // Clear old separators on the grid container
        g.querySelectorAll(':scope > .ed-grid-sep').forEach(s=> s.remove());
        const gridRect = g.getBoundingClientRect();
        for (let i=0; i<cellsLive.length-1; i++){
          const sep = document.createElement('div'); sep.className='ed-grid-sep';
          const positionSep = ()=>{
            const gridBox = g.getBoundingClientRect();
            const cellBox = cellsLive[i].getBoundingClientRect();
            const leftPx = cellBox.right - gridBox.left;
            sep.style.left = Math.round(leftPx) + 'px';
          };
          positionSep();
          setTimeout(positionSep, 0);
          g.addEventListener('mouseenter', positionSep);
          window.addEventListener('resize', positionSep);
          // Create separate hit zones: left side = resize, right side = + button
          // Only add button in the gap; resize will be on column edges, not here
          const addBtn = document.createElement('span'); addBtn.className='ed-grid-add'; sep.appendChild(addBtn);
          g.appendChild(sep);
          let startX=0; let dragging=false; let moved=false;
          addBtn.addEventListener('click', (ev)=>{ ev.preventDefault(); ev.stopPropagation(); const b = surface.__blocks[blockIndex]; b.html = EditorModes.block.gridInsertCol(b.html, 0, i+1); surface.__blocks[blockIndex].html=b.html; EditorModes.block.render(surface, blockIndex, true); });
          // no resize from the gap; handled on column edges below
          const onMove=(ev)=>{
            if (!dragging) return; moved = moved || Math.abs(ev.clientX-startX) > 2;
            const widthsPx = Array.from(g.querySelectorAll('.ed-grid-cell')).map(c=> c.getBoundingClientRect().width);
            const total = widthsPx.reduce((a,b)=>a+b,0) || 1;
            const widths = widthsPx.map(w=> (w/total)*100);
            const pairTotal = widths[i] + widths[i+1];
            const dxPct = ((ev.clientX-startX) / (widthsPx[i] + widthsPx[i+1])) * pairTotal;
            const minPct = 10;
            let w1 = Math.max(minPct, Math.min(pairTotal - minPct, widths[i] + dxPct));
            let w2 = pairTotal - w1;
            const newWidths = widths.slice(); newWidths[i]=w1; newWidths[i+1]=w2;
            g.style.gridTemplateColumns = newWidths.map(p=> `minmax(0, ${p}%)`).join(' ');
          };
          const onUp=()=>{ if (!dragging) return; dragging=false; g.classList.remove('dragging-sep'); const b = surface.__blocks[blockIndex]; const snapshot = content.cloneNode(true); snapshot.querySelectorAll('.ed-grid-col-drag, .ed-drop-indicator, .ed-grid-sep').forEach(el=> el.remove()); b.html = snapshot.innerHTML; surface.__blocks[blockIndex].html = b.html; surface.dispatchEvent(new Event('input')); };
          const onUpOnce=()=>{ document.removeEventListener('mousemove', onMove); document.removeEventListener('mouseup', onUpOnce); onUp(); };
          document.addEventListener('mousemove', onMove);
          document.addEventListener('mouseup', onUpOnce);
        }
        // Add column-edge resizers and controls (duplicate/delete) per column
        cellsLive.forEach((cell, idx)=>{
          // clear
          cell.querySelectorAll('.ed-col-resize, .ed-col-controls').forEach(n=> n.remove());
          // edge resizers: left (if not first) and right (if not last)
          const addEdgeResizer = (edge)=>{
            const handle = document.createElement('div'); handle.className='ed-col-resize'+(edge==='left'?' left':''); cell.appendChild(handle);
            let startX=0, dragging=false, rafId=null;
            const leftIdx = edge==='left' ? idx-1 : idx;
            const rightIdx = edge==='left' ? idx : idx+1;
            const onDown=(ev)=>{
              ev.preventDefault(); dragging=true; startX=ev.clientX;
              // create overlay
              const overlay = g.querySelector(':scope > .ed-grid-overlay') || (()=>{ const o=document.createElement('div'); o.className='ed-grid-overlay'; g.appendChild(o); return o; })();
              const line = overlay.querySelector('.ed-resize-line') || (()=>{ const l=document.createElement('div'); l.className='ed-resize-line'; overlay.appendChild(l); return l; })();
              const gridBox = g.getBoundingClientRect();
              line.style.left = (ev.clientX - gridBox.left) + 'px';
              const widthsPxInit = Array.from(g.querySelectorAll('.ed-grid-cell')).map(c=> c.getBoundingClientRect().width);
              const totalInit = widthsPxInit.reduce((a,b)=>a+b,0) || 1;
              const widthsPctInit = widthsPxInit.map(w=> (w/totalInit)*100);
              const colCount = widthsPxInit.length;
              const minPx = 120;
              const onMove=(e)=>{
                if (!dragging) return;
                const run=()=>{
                  const dx = e.clientX - startX;
                  // Clamp so remaining columns can each be at least minPx
                  const maxLeftPx = totalInit - (colCount - 1) * minPx;
                  const newLeftPx = Math.max(minPx, Math.min(maxLeftPx, widthsPxInit[leftIdx] + dx));
                  const remainingPx = totalInit - newLeftPx;
                  const eachOtherPx = remainingPx / (colCount - 1);
                  const newPct = new Array(colCount);
                  for (let j=0; j<colCount; j++){
                    const px = (j===leftIdx) ? newLeftPx : eachOtherPx;
                    newPct[j] = (px/totalInit)*100;
                  }
                  g.style.gridTemplateColumns = newPct.map(p=>`minmax(0, ${p}%)`).join(' ');
                  line.style.left = (e.clientX - gridBox.left) + 'px';
                };
                if (rafId) cancelAnimationFrame(rafId); rafId = requestAnimationFrame(run);
              };
              const onUp=()=>{
                if (!dragging) return; dragging=false; if (rafId) cancelAnimationFrame(rafId);
                overlay.remove();
                const b = surface.__blocks[blockIndex];
                const snapshot=content.cloneNode(true);
                snapshot.querySelectorAll('.ed-grid-col-drag, .ed-drop-indicator, .ed-grid-sep').forEach(el=> el.remove());
                b.html=snapshot.innerHTML; surface.__blocks[blockIndex].html=b.html;
                // Re-render to rebuild gap separators (+) after resize
                EditorModes.block.render(surface, blockIndex, true);
                surface.dispatchEvent(new Event('input'));
                document.removeEventListener('mousemove', onMove); document.removeEventListener('mouseup', onUp);
              };
              document.addEventListener('mousemove', onMove);
              document.addEventListener('mouseup', onUp);
            };
            handle.addEventListener('mousedown', onDown);
          };
          if (idx>0) addEdgeResizer('left');
          if (idx<cellsLive.length-1) addEdgeResizer('right');
          // controls: duplicate/delete column
          const controls=document.createElement('div'); controls.className='ed-col-controls';
          const dup=document.createElement('button'); dup.type='button'; dup.className='ed-col-btn'; dup.title='Duplicate column'; dup.textContent='⎘';
          const del=document.createElement('button'); del.type='button'; del.className='ed-col-btn'; del.title='Delete column'; del.textContent='×';
          controls.appendChild(dup); controls.appendChild(del); cell.appendChild(controls);
          dup.addEventListener('click', (ev)=>{ ev.preventDefault(); const b = surface.__blocks[blockIndex]; const d=document.createElement('div'); d.innerHTML=b.html; const grids=Array.from(d.querySelectorAll('.ed-grid')); const gEl=grids[0]; if (!gEl) return; const cells=Array.from(gEl.querySelectorAll('.ed-grid-cell')); const clone=cells[idx].cloneNode(true); gEl.insertBefore(clone, cells[idx+1]||null); const cls=Array.from(gEl.classList).find(c=>/^cols-\d$/.test(c)); let n=cls?parseInt(cls.split('-')[1],10):cells.length; n=Math.min(4, n+1); if (cls) gEl.classList.remove(cls); gEl.classList.add('cols-'+n); b.html=d.innerHTML; surface.__blocks[blockIndex].html=b.html; EditorModes.block.render(surface, blockIndex, true); });
          del.addEventListener('click', (ev)=>{ ev.preventDefault(); const b = surface.__blocks[blockIndex]; const d=document.createElement('div'); d.innerHTML=b.html; const grids=Array.from(d.querySelectorAll('.ed-grid')); const gEl=grids[0]; if (!gEl) return; const cells=Array.from(gEl.querySelectorAll('.ed-grid-cell')); if (cells.length<=1) return; cells[idx].remove(); const cls=Array.from(gEl.classList).find(c=>/^cols-\d$/.test(c)); let n=cls?parseInt(cls.split('-')[1],10):cells.length; n=Math.max(1, n-1); if (cls) gEl.classList.remove(cls); gEl.classList.add('cols-'+n); b.html=d.innerHTML; surface.__blocks[blockIndex].html=b.html; EditorModes.block.render(surface, blockIndex, true); });
        });
        // Inline grid controls (add/remove cols + live slider)
        // Remove the temporary top-right toolbar to keep UX clean
        g.querySelectorAll(':scope > .ed-grid-controls').forEach(x=> x.remove());
        // Pointer-based drag with live indicator for column reordering
        if (g.__gridHandlers){
          g.removeEventListener('mousedown', g.__gridHandlers.down);
          document.removeEventListener('mousemove', g.__gridHandlers.move);
          document.removeEventListener('mouseup', g.__gridHandlers.up);
          g.__gridHandlers = null;
        }
        const indicator = g.__gridIndicator || (()=>{ const line=document.createElement('div'); line.className='ed-drop-indicator'; line.style.display='none'; g.appendChild(line); g.__gridIndicator=line; return line; })();
        let dragging=false, fromIndex=-1;
        const onDown = (e)=>{ const handle=e.target.closest('.ed-grid-col-drag'); if (!handle) return; e.preventDefault(); const cell=handle.closest('.ed-grid-cell'); fromIndex=Array.from(g.children).indexOf(cell); dragging=true; indicator.style.display='block'; };
        const onMove = (e)=>{ if (!dragging) return; const over=document.elementFromPoint(e.clientX,e.clientY)?.closest?.('.ed-grid-cell'); if (!over || over.parentElement!==g) return; const rect=over.getBoundingClientRect(); const gridRect=g.getBoundingClientRect(); const before=e.clientX < rect.left + rect.width/2; indicator.style.left=((before?rect.left:rect.right)-gridRect.left)+'px'; };
        const onUp = (e)=>{ if (!dragging) return; dragging=false; indicator.style.display='none'; const over=document.elementFromPoint(e.clientX,e.clientY)?.closest?.('.ed-grid-cell'); if (!over || over.parentElement!==g) return; const toIndex=Array.from(g.children).indexOf(over); if (toIndex<0 || fromIndex===toIndex) return; const moving=g.children[fromIndex]; if (!moving) return; const rect=over.getBoundingClientRect(); const before=e.clientX < rect.left + rect.width/2; if (before) over.before(moving); else over.after(moving); const b = surface.__blocks[blockIndex]; const snapshot = content.cloneNode(true); snapshot.querySelectorAll('.ed-grid-col-drag, .ed-drop-indicator').forEach(el=> el.remove()); b.html = snapshot.innerHTML; surface.__blocks[blockIndex].html = b.html; surface.dispatchEvent(new Event('input')); };
        g.addEventListener('mousedown', onDown);
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
        g.__gridHandlers = { down:onDown, move:onMove, up:onUp };
      });
    }catch(e){}
  },
  // Paste handling: split pasted multi-paragraph HTML/text into multiple blocks
  handlePaste(ev, surface, index){
    try {
      ev.preventDefault();
      const html = ev.clipboardData.getData('text/html');
      let text = ev.clipboardData.getData('text/plain');
      // Autolink URLs in plain text
      if (!html && text){
        const urlRe = /(https?:\/\/[^\s]+)|(www\.[^\s]+)/g;
        if (urlRe.test(text)){
          const safe = text.replace(urlRe, (m)=> `<a href="${m.startsWith('http')?m:'https://'+m}" target="_blank" rel="noopener">${m}</a>`);
          surface.__blocks[index].html = safe; this.render(surface, index, true, 'end'); return;
        }
      }
      let blocks = [];
      if (html) blocks = this.htmlToBlocks(html);
      else if (text) blocks = this.textToBlocks(text);
      if (!Array.isArray(blocks) || blocks.length===0){ return; }
      // Apply into model: replace current block with first, insert rest after
      const first = blocks.shift();
      surface.__blocks[index] = { id: surface.__blocks[index].id, type: first.type, html: first.html, indent: surface.__blocks[index].indent||0 };
      if (blocks.length){
        const after = blocks.map(b=>({ id: String(Date.now()+Math.random()), type: b.type, html: b.html, indent: 0 }));
        surface.__blocks.splice(index+1, 0, ...after);
      }
      this.render(surface, index + (blocks.length||0), true, 'end');
    } catch(e) { /* noop */ }
  },
  sanitizeHTML(raw){
    // basic sanitize: strip script tags
    return String(raw||'').replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '');
  },
  cleanHTML(raw){
    try{
      const wrapper=document.createElement('div'); wrapper.innerHTML=raw;
      const ALLOW = new Set(['p','h1','h2','h3','ul','ol','li','blockquote','pre','code','img','table','tbody','tr','td','th','a','strong','em','u','s','br','hr','figure','figcaption','div','span']);
      const walk=(el)=>{
        if (el.nodeType!==1) return;
        const tag=el.tagName.toLowerCase();
        if ((tag==='span'||tag==='div') && el.getAttribute('style')){
          const style=el.style;
          if (style.fontWeight && parseInt(style.fontWeight,10)>=600){ const st=document.createElement('strong'); st.innerHTML=el.innerHTML; el.parentNode.replaceChild(st, el); walk(st); return; }
          if (style.fontStyle==='italic'){ const em=document.createElement('em'); em.innerHTML=el.innerHTML; el.parentNode.replaceChild(em, el); walk(em); return; }
          if (/(underline)/i.test(style.textDecoration||'')){ const u=document.createElement('u'); u.innerHTML=el.innerHTML; el.parentNode.replaceChild(u, el); walk(u); return; }
          if (/(line-through|strikethrough)/i.test(style.textDecoration||'')){ const s=document.createElement('s'); s.innerHTML=el.innerHTML; el.parentNode.replaceChild(s, el); walk(s); return; }
        }
        if (!ALLOW.has(tag)){
          const frag=document.createDocumentFragment(); while(el.firstChild){ frag.appendChild(el.firstChild); }
          el.parentNode && el.parentNode.replaceChild(frag, el); return;
        }
        const attrs=Array.from(el.attributes);
        attrs.forEach(a=>{
          if (tag==='a' && (a.name==='href' || a.name==='target' || a.name==='rel')) return;
          if (tag==='img' && (a.name==='src' || a.name==='alt')) return;
          if (/^data-/.test(a.name)) return;
          el.removeAttribute(a.name);
        });
        Array.from(el.childNodes).forEach(walk);
        if (tag==='div'){
          const p=document.createElement('p'); p.innerHTML=el.innerHTML; el.parentNode && el.parentNode.replaceChild(p, el);
        }
      };
      Array.from(wrapper.childNodes).forEach(walk);
      return wrapper.innerHTML;
    }catch(e){ return raw; }
  },
  htmlToBlocks(rawHtml){
    const html = this.cleanHTML(this.sanitizeHTML(rawHtml));
    const container = document.createElement('div');
    container.innerHTML = html;
    const out = [];
    const esc = (v)=> String(v||'');
    const collectCommonAttrs = (el)=>{
      const attrs = {};
      try{
        const cls = el.getAttribute && el.getAttribute('class'); if (cls) attrs.className = cls;
        const id = el.getAttribute && el.getAttribute('id'); if (id) attrs.domId = id;
        const role = el.getAttribute && el.getAttribute('role'); if (role) attrs.role = role;
        const dataPairs = [];
        if (el.attributes){
          Array.from(el.attributes).forEach(a=>{
            if (/^data-/.test(a.name)) dataPairs.push(`${a.name}=${a.value}`);
          });
        }
        if (dataPairs.length) attrs.dataAttrs = dataPairs.join('\n');
      }catch(_e){}
      return attrs;
    };
    const walk = (node)=>{
      if (!node) return;
      if (node.nodeType === Node.TEXT_NODE){
        const t = node.nodeValue.trim();
        if (t) out.push({type:'p', html: node.nodeValue});
        return;
      }
      if (node.nodeType !== Node.ELEMENT_NODE) return;
      const tag = node.tagName.toLowerCase();
      if (tag === 'p'){ out.push({type:'p', html: node.innerHTML, ...collectCommonAttrs(node)}); return; }
      if (/^h[1-6]$/.test(tag)){ out.push({type: tag, html: node.innerHTML, ...collectCommonAttrs(node)}); return; }
      if (tag === 'blockquote'){ out.push({type:'quote', html: node.innerHTML, ...collectCommonAttrs(node)}); return; }
      if (tag === 'pre'){ const lang = node.getAttribute('data-lang') || (node.dataset && node.dataset.lang) || ''; out.push({type:'code', html: node.innerHTML, codeLang: lang, ...collectCommonAttrs(node)}); return; }
      if (tag === 'ul'){ const listCls = node.getAttribute('class')||''; Array.from(node.querySelectorAll(':scope > li')).forEach(li=> out.push({type:'ul', html: li.innerHTML, listClassName: listCls})); return; }
      if (tag === 'ol'){ const listCls = node.getAttribute('class')||''; Array.from(node.querySelectorAll(':scope > li')).forEach(li=> out.push({type:'ol', html: li.innerHTML, listClassName: listCls})); return; }
      if (tag === 'img' || tag === 'figure'){ out.push({type:'html', html: node.outerHTML}); return; }
      // generic container: walk children
      Array.from(node.childNodes).forEach(walk);
    };
    Array.from(container.childNodes).forEach(walk);
    // Fallback: if nothing recognized, make a single paragraph from text
    if (out.length===0){
      const text = container.textContent || '';
      return this.textToBlocks(text);
    }
    return out;
  },
  textToBlocks(text){
    if (!text) return [];
    const lines = String(text).replace(/\r\n?/g, '\n').split('\n');
    const blocks = [];
    let para = [];
    const flushPara = ()=>{ if (para.length){ blocks.push({type:'p', html: para.join(' ') }); para = []; } };
    lines.forEach(line=>{
      const l = line.trimEnd();
      if (l.trim()===''){ flushPara(); return; }
      // headings
      if (/^###\s+/.test(l)){ flushPara(); blocks.push({type:'h3', html: l.replace(/^###\s+/, '')}); return; }
      if (/^##\s+/.test(l)){ flushPara(); blocks.push({type:'h2', html: l.replace(/^##\s+/, '')}); return; }
      if (/^#\s+/.test(l)){ flushPara(); blocks.push({type:'h1', html: l.replace(/^#\s+/, '')}); return; }
      // list detection
      if (/^[-\u2013\u2014]\s+/.test(l)){ flushPara(); blocks.push({type:'ul', html: l.replace(/^[-\u2013\u2014]\s+/, '')}); return; }
      if (/^\d+\.\s+/.test(l)){ flushPara(); blocks.push({type:'ol', html: l.replace(/^\d+\.\s+/, '')}); return; }
      if (/^>\s+/.test(l)){ flushPara(); blocks.push({type:'quote', html: l.replace(/^>\s+/, '')}); return; }
      para.push(l);
    });
    flushPara();
    return blocks;
  },
  openLinkDialog(surface, index){
    const wrap = document.createElement('div'); wrap.className='ed-block-insert';
    const href = document.createElement('input'); href.className='ed-input'; href.placeholder='https://…';
    const target = document.createElement('input'); target.className='ed-input'; target.placeholder='target (e.g., _blank)';
    const rel = document.createElement('input'); rel.className='ed-input'; rel.placeholder='rel (e.g., noopener)';
    // Presets
    const presets = document.createElement('div'); presets.style.display='flex'; presets.style.gap='6px'; presets.style.margin='4px 0';
    const sameTab = document.createElement('button'); sameTab.type='button'; sameTab.className='ed-btn'; sameTab.textContent='Same tab';
    const newTab = document.createElement('button'); newTab.type='button'; newTab.className='ed-btn'; newTab.textContent='New tab';
    const mailtoBtn = document.createElement('button'); mailtoBtn.type='button'; mailtoBtn.className='ed-btn'; mailtoBtn.textContent='mailto:';
    const telBtn = document.createElement('button'); telBtn.type='button'; telBtn.className='ed-btn'; telBtn.textContent='tel:';
    presets.appendChild(sameTab); presets.appendChild(newTab); presets.appendChild(mailtoBtn); presets.appendChild(telBtn);
    wrap.appendChild(href); wrap.appendChild(target); wrap.appendChild(rel);
    wrap.appendChild(presets);
    const actions=document.createElement('div'); actions.style.display='flex'; actions.style.gap='6px'; actions.style.marginTop='6px';
    const apply=document.createElement('button'); apply.type='button'; apply.className='ed-btn'; apply.textContent='Apply'; actions.appendChild(apply);
    const cancel=document.createElement('button'); cancel.type='button'; cancel.className='ed-btn'; cancel.textContent='Cancel'; actions.appendChild(cancel);
    wrap.appendChild(actions);
    const modal = this.showModal(wrap);
    const close=()=> modal.close();
    const applyFn = ()=>{
      this.restoreSelection(surface);
      const url = href.value ? href.value.trim() : '';
      if (url){ document.execCommand('createLink', false, url); }
      // Post-process current anchor
      const a = this.getAnchorAtSelection(surface);
      if (a){
        const t = (target.value||'').trim();
        const r = (rel.value||'').trim();
        if (t) a.setAttribute('target', t); else a.removeAttribute('target');
        // auto noopener/noreferrer when opening in new tab
        let relFinal = r;
        if (t === '_blank'){
          const need = ['noopener','noreferrer'];
          need.forEach(flag=>{ if (!new RegExp('(?:^|\\s)'+flag+'(?:$|\\s)').test(relFinal)) relFinal = (relFinal? relFinal+' ' : '') + flag; });
        }
        if (relFinal) a.setAttribute('rel', relFinal); else a.removeAttribute('rel');
      }
      surface.dispatchEvent(new Event('input'));
      close();
    };
    apply.addEventListener('click', applyFn);
    sameTab.addEventListener('click', ()=>{ target.value=''; rel.value=''; });
    newTab.addEventListener('click', ()=>{ target.value='_blank'; if (!/(?:^|\s)noopener(?:$|\s)/.test(rel.value||'')) rel.value = (rel.value? rel.value+' ' : '') + 'noopener'; if (!/(?:^|\s)noreferrer(?:$|\s)/.test(rel.value||'')) rel.value = (rel.value? rel.value+' ' : '') + 'noreferrer'; });
    mailtoBtn.addEventListener('click', ()=>{ if (!href.value || !href.value.startsWith('mailto:')) href.value = 'mailto:'; target.value=''; rel.value=''; href.focus(); });
    telBtn.addEventListener('click', ()=>{ if (!href.value || !href.value.startsWith('tel:')) href.value = 'tel:'; target.value=''; rel.value=''; href.focus(); });
    cancel.addEventListener('click', close);
    href.addEventListener('keydown', (e)=>{ if (e.key==='Enter'){ apply.click(); } if (e.key==='Escape'){ close(); }});
    setTimeout(()=> href.focus(), 0);
  },
  openImageDialog(surface, index){
    const wrap = document.createElement('div'); wrap.className='ed-block-insert';
    const url = document.createElement('input'); url.className='ed-input'; url.placeholder='Image URL';
    const alt = document.createElement('input'); alt.className='ed-input'; alt.placeholder='Alt text';
    wrap.appendChild(url); wrap.appendChild(alt);
    const actions=document.createElement('div'); actions.style.display='flex'; actions.style.gap='6px'; actions.style.marginTop='6px';
    const apply=document.createElement('button'); apply.type='button'; apply.className='ed-btn'; apply.textContent='Insert'; actions.appendChild(apply);
    const choose=document.createElement('button'); choose.type='button'; choose.className='ed-btn'; choose.textContent='Choose media…'; actions.appendChild(choose);
    const cancel=document.createElement('button'); cancel.type='button'; cancel.className='ed-btn'; cancel.textContent='Cancel'; actions.appendChild(cancel);
    wrap.appendChild(actions);
    const modal = this.showModal(wrap);
    const close=()=> modal.close();
    apply.addEventListener('click', ()=>{ if (!url.value.trim()){ close(); return; } const imgHtml = `<img src="${url.value.trim().replace(/"/g,'&quot;')}" alt="${(alt.value||'').replace(/"/g,'&quot;')}"/>`; const after={ id:String(Date.now()+Math.random()), type:'html', html: imgHtml, indent:0 }; surface.__blocks.splice(index+1,0,after); this.render(surface, index+1, true); surface.dispatchEvent(new Event('input')); close(); });
    choose.addEventListener('click', ()=>{
      try{
        if (window.HaruCMS && window.HaruCMS.MediaModal && typeof window.HaruCMS.MediaModal.open==='function'){
          window.HaruCMS.MediaModal.open((media)=>{
            if (!media || !media.url) return;
            // Directly insert chosen image as a new HTML block after current block
            const src = String(media.url).replace(/"/g,'&quot;');
            const altText = String(media.title || media.name || '').replace(/"/g,'&quot;');
            const imgHtml = `<img src="${src}" alt="${altText}"/>`;
            const after = { id:String(Date.now()+Math.random()), type:'html', html: imgHtml, indent:0 };
            surface.__blocks.splice(index+1, 0, after);
            this.render(surface, index+1, true);
            surface.dispatchEvent(new Event('input'));
            close();
          });
        }
      }catch(e){}
    });
    cancel.addEventListener('click', close);
    setTimeout(()=> url.focus(), 0);
  },
  openEmbedDialog(surface, index){
    const wrap = document.createElement('div'); wrap.className='ed-block-insert';
    const url = document.createElement('input'); url.className='ed-input'; url.placeholder='Embed URL (YouTube)'; wrap.appendChild(url);
    const actions=document.createElement('div'); actions.style.display='flex'; actions.style.gap='6px'; actions.style.marginTop='6px';
    const apply=document.createElement('button'); apply.type='button'; apply.className='ed-btn'; apply.textContent='Embed'; actions.appendChild(apply);
    const cancel=document.createElement('button'); cancel.type='button'; cancel.className='ed-btn'; cancel.textContent='Cancel'; actions.appendChild(cancel);
    wrap.appendChild(actions);
    const modal = this.showModal(wrap);
    const close=()=> modal.close();
    const yt = (u)=>{ try{ const m=String(u).match(/(?:v=|\.be\/)([\w-]{11})/); if (!m) return null; const id=m[1]; return `<iframe width="560" height="315" src="https://www.youtube.com/embed/${id}" frameborder="0" allowfullscreen></iframe>`; }catch(e){ return null; } };
    apply.addEventListener('click', ()=>{ const val=url.value.trim(); if (!val){ close(); return; } let embedHtml = yt(val); if (!embedHtml){ embedHtml = `<a href="${val.replace(/"/g,'&quot;')}" target="_blank" rel="noopener">${val}</a>`; } const after={ id:String(Date.now()+Math.random()), type:'html', html: embedHtml, indent:0 }; surface.__blocks.splice(index+1,0,after); this.render(surface, index+1, true); surface.dispatchEvent(new Event('input')); close(); });
    cancel.addEventListener('click', close);
    setTimeout(()=> url.focus(), 0);
  },
  openVideoDialog(surface, index){
    const wrap = document.createElement('div'); wrap.className='ed-block-insert';
    const url = document.createElement('input'); url.className='ed-input'; url.placeholder='Video URL (mp4) or embed URL'; wrap.appendChild(url);
    const actions=document.createElement('div'); actions.style.display='flex'; actions.style.gap='6px'; actions.style.marginTop='6px';
    const apply=document.createElement('button'); apply.type='button'; apply.className='ed-btn'; apply.textContent='Insert'; actions.appendChild(apply);
    const choose=document.createElement('button'); choose.type='button'; choose.className='ed-btn'; choose.textContent='Choose media…'; actions.appendChild(choose);
    const cancel=document.createElement('button'); cancel.type='button'; cancel.className='ed-btn'; cancel.textContent='Cancel'; actions.appendChild(cancel);
    wrap.appendChild(actions);
    const modal = this.showModal(wrap);
    const close=()=> modal.close();
    apply.addEventListener('click', ()=>{ const val=url.value.trim(); if (!val){ close(); return; } let html=''; if (/\.mp4($|\?)/i.test(val)) html = `<video controls src="${val.replace(/"/g,'&quot;')}"></video>`; else html = `<iframe src="${val.replace(/"/g,'&quot;')}" frameborder="0" allowfullscreen></iframe>`; const after={ id:String(Date.now()+Math.random()), type:'html', html, indent:0 }; surface.__blocks.splice(index+1,0,after); this.render(surface, index+1, true); surface.dispatchEvent(new Event('input')); close(); });
    choose.addEventListener('click', ()=>{ try{ if (window.HaruCMS && window.HaruCMS.MediaModal && typeof window.HaruCMS.MediaModal.open==='function'){ window.HaruCMS.MediaModal.open((media)=>{ if (!media || !media.url) return; url.value=media.url; }); } }catch(e){} });
    cancel.addEventListener('click', close);
    setTimeout(()=> url.focus(), 0);
  },
  openAudioDialog(surface, index){
    const wrap = document.createElement('div'); wrap.className='ed-block-insert';
    const url = document.createElement('input'); url.className='ed-input'; url.placeholder='Audio URL (mp3/ogg)'; wrap.appendChild(url);
    const actions=document.createElement('div'); actions.style.display='flex'; actions.style.gap='6px'; actions.style.marginTop='6px';
    const apply=document.createElement('button'); apply.type='button'; apply.className='ed-btn'; apply.textContent='Insert'; actions.appendChild(apply);
    const choose=document.createElement('button'); choose.type='button'; choose.className='ed-btn'; choose.textContent='Choose media…'; actions.appendChild(choose);
    const cancel=document.createElement('button'); cancel.type='button'; cancel.className='ed-btn'; cancel.textContent='Cancel'; actions.appendChild(cancel);
    wrap.appendChild(actions);
    const modal = this.showModal(wrap);
    const close=()=> modal.close();
    apply.addEventListener('click', ()=>{ const val=url.value.trim(); if (!val){ close(); return; } const html = `<audio controls src="${val.replace(/"/g,'&quot;')}"></audio>`; const after={ id:String(Date.now()+Math.random()), type:'html', html, indent:0 }; surface.__blocks.splice(index+1,0,after); this.render(surface, index+1, true); surface.dispatchEvent(new Event('input')); close(); });
    choose.addEventListener('click', ()=>{ try{ if (window.HaruCMS && window.HaruCMS.MediaModal && typeof window.HaruCMS.MediaModal.open==='function'){ window.HaruCMS.MediaModal.open((media)=>{ if (!media || !media.url) return; url.value=media.url; }); } }catch(e){} });
    cancel.addEventListener('click', close);
    setTimeout(()=> url.focus(), 0);
  },
  openFileDialog(surface, index){
    const wrap = document.createElement('div'); wrap.className='ed-block-insert';
    const url = document.createElement('input'); url.className='ed-input'; url.placeholder='File URL'; wrap.appendChild(url);
    const title = document.createElement('input'); title.className='ed-input'; title.placeholder='Label (optional)'; wrap.appendChild(title);
    const actions=document.createElement('div'); actions.style.display='flex'; actions.style.gap='6px'; actions.style.marginTop='6px';
    const apply=document.createElement('button'); apply.type='button'; apply.className='ed-btn'; apply.textContent='Insert'; actions.appendChild(apply);
    const choose=document.createElement('button'); choose.type='button'; choose.className='ed-btn'; choose.textContent='Choose file…'; actions.appendChild(choose);
    const cancel=document.createElement('button'); cancel.type='button'; cancel.className='ed-btn'; cancel.textContent='Cancel'; actions.appendChild(cancel);
    wrap.appendChild(actions);
    const modal = this.showModal(wrap);
    const close=()=> modal.close();
    apply.addEventListener('click', ()=>{ const u=url.value.trim(); if (!u){ close(); return; } const label=(title.value||u).replace(/"/g,'&quot;'); const html = `<a href="${u.replace(/"/g,'&quot;')}" download>${label}</a>`; const after={ id:String(Date.now()+Math.random()), type:'html', html, indent:0 }; surface.__blocks.splice(index+1,0,after); this.render(surface, index+1, true); surface.dispatchEvent(new Event('input')); close(); });
    choose.addEventListener('click', ()=>{ try{ if (window.HaruCMS && window.HaruCMS.MediaModal && typeof window.HaruCMS.MediaModal.open==='function'){ window.HaruCMS.MediaModal.open((media)=>{ if (!media || !media.url) return; url.value=media.url; title.value = media.title || media.name || ''; }); } }catch(e){} });
    cancel.addEventListener('click', close);
    setTimeout(()=> url.focus(), 0);
  },
  openTableDialog(surface, index){
    const wrap=document.createElement('div'); wrap.className='ed-block-insert';
    const rows=document.createElement('input'); rows.className='ed-input'; rows.placeholder='Rows'; rows.type='number'; rows.min='1'; rows.value='2';
    const cols=document.createElement('input'); cols.className='ed-input'; cols.placeholder='Columns'; cols.type='number'; cols.min='1'; cols.value='2';
    wrap.appendChild(rows); wrap.appendChild(cols);
    const actions=document.createElement('div'); actions.style.display='flex'; actions.style.gap='6px'; actions.style.marginTop='6px';
    const insert=document.createElement('button'); insert.type='button'; insert.className='ed-btn'; insert.textContent='Insert'; actions.appendChild(insert);
    const cancel=document.createElement('button'); cancel.type='button'; cancel.className='ed-btn'; cancel.textContent='Cancel'; actions.appendChild(cancel);
    wrap.appendChild(actions);
    const modal = this.showModal(wrap);
    const close=()=> modal.close();
    insert.addEventListener('click', ()=>{ const r=Math.max(1, parseInt(rows.value||'2',10)); const c=Math.max(1, parseInt(cols.value||'2',10)); let html='<table class="ed-table"><tbody>'; for(let i=0;i<r;i++){ html+='<tr>'; for(let j=0;j<c;j++){ html+='<td><p>&nbsp;</p></td>'; } html+='</tr>'; } html+='</tbody></table>'; const after={ id:String(Date.now()+Math.random()), type:'html', html, indent:0 }; surface.__blocks.splice(index+1,0,after); this.render(surface, index+1, true); surface.dispatchEvent(new Event('input')); close(); });
    cancel.addEventListener('click', close); setTimeout(()=> rows.focus(), 0);
  },
  openGridDialog(surface, index){
    const wrap=document.createElement('div'); wrap.className='ed-block-insert';
    const cols=document.createElement('input'); cols.className='ed-input'; cols.placeholder='Columns (2-4)'; cols.type='number'; cols.min='1'; cols.max='4'; cols.value='2'; wrap.appendChild(cols);
    const actions=document.createElement('div'); actions.style.display='flex'; actions.style.gap='6px'; actions.style.marginTop='6px';
    const insert=document.createElement('button'); insert.type='button'; insert.className='ed-btn'; insert.textContent='Insert'; actions.appendChild(insert);
    const cancel=document.createElement('button'); cancel.type='button'; cancel.className='ed-btn'; cancel.textContent='Cancel'; actions.appendChild(cancel);
    wrap.appendChild(actions);
    const modal = this.showModal(wrap);
    const close=()=> modal.close();
    insert.addEventListener('click', ()=>{ let n=Math.max(1, Math.min(4, parseInt(cols.value||'2',10))); let html=`<div class="ed-grid cols-${n}">`; for(let i=0;i<n;i++){ html+=`<div class="ed-grid-cell"> </div>`; } html+='</div>'; const after={ id:String(Date.now()+Math.random()), type:'html', html, indent:0 }; surface.__blocks.splice(index+1,0,after); this.render(surface, index+1, true); surface.dispatchEvent(new Event('input')); close(); });
    cancel.addEventListener('click', close); setTimeout(()=> cols.focus(), 0);
  },
  // Table helpers
  tableAddRow(html, rowIndex){ try{ const d=document.createElement('div'); d.innerHTML=html; const rows=Array.from(d.querySelectorAll('tr')); if (!rows.length) return html; const ref = (typeof rowIndex==='number' && rowIndex>=0 && rowIndex<rows.length) ? rows[rowIndex] : rows[rows.length-1]; const cols=ref.children.length||1; const tr=document.createElement('tr'); for(let i=0;i<cols;i++){ const td=document.createElement('td'); td.innerHTML='<p>&nbsp;</p>'; tr.appendChild(td);} ref.parentElement.insertBefore(tr, ref.nextSibling); return d.innerHTML; }catch(e){ return html; } },
  tableRemoveRow(html, rowIndex){ try{ const d=document.createElement('div'); d.innerHTML=html; const rows=Array.from(d.querySelectorAll('tr')); if (rows.length>1){ const ref=(typeof rowIndex==='number' && rowIndex>=0 && rowIndex<rows.length) ? rows[rowIndex] : rows[rows.length-1]; ref.remove(); } return d.innerHTML; }catch(e){ return html; } },
  tableAddCol(html, colIndex){ try{ const d=document.createElement('div'); d.innerHTML=html; const rows=Array.from(d.querySelectorAll('tr')); rows.forEach(r=>{ const cols=Array.from(r.children); const td=document.createElement('td'); td.innerHTML='<p>&nbsp;</p>'; const idx=(typeof colIndex==='number' && colIndex>=0 && colIndex<=cols.length) ? colIndex+1 : cols.length; r.insertBefore(td, cols[idx]||null); }); return d.innerHTML; }catch(e){ return html; } },
  tableRemoveCol(html, colIndex){ try{ const d=document.createElement('div'); d.innerHTML=html; const rows=Array.from(d.querySelectorAll('tr')); if (!rows.length) return html; const colsCount=rows[0].children.length; if (colsCount>1){ rows.forEach(r=>{ const cols=Array.from(r.children); const idx=(typeof colIndex==='number' && colIndex>=0 && colIndex<cols.length) ? colIndex : cols.length-1; if (cols[idx]) cols[idx].remove(); }); } return d.innerHTML; }catch(e){ return html; } },
  tableToggleHeader(html){ try{ const d=document.createElement('div'); d.innerHTML=html; const firstRow=d.querySelector('tr'); if (!firstRow) return html; const isHeader = firstRow.querySelector('th'); const cells=Array.from(firstRow.children); cells.forEach(cell=>{ const rep=document.createElement(isHeader? 'td':'th'); rep.innerHTML = cell.innerHTML||' '; firstRow.replaceChild(rep, cell); }); return d.innerHTML; }catch(e){ return html; } },
  tableAlignCell(html, pos, align){ try{ if (!pos) return html; const d=document.createElement('div'); d.innerHTML=html; const rows=Array.from(d.querySelectorAll('tr')); const row=rows[pos.rowIndex]; if (!row) return html; const cells=Array.from(row.children); const cell=cells[pos.colIndex]; if (!cell) return html; cell.style.textAlign = align; return d.innerHTML; }catch(e){ return html; } },
  tableToggleFreeze(html){ try{ const d=document.createElement('div'); d.innerHTML=html; const table=d.querySelector('table'); if (!table) return html; const hasThead = !!table.querySelector('thead'); if (hasThead){ // unfreeze: move thead row(s) back to tbody start
      const thead=table.querySelector('thead'); const tbody=table.querySelector('tbody')||table.appendChild(document.createElement('tbody'));
      const headRows=Array.from(thead.querySelectorAll('tr'));
      headRows.reverse().forEach(r=>{ // convert th to td
        Array.from(r.children).forEach(cell=>{ if (cell.tagName.toLowerCase()==='th'){ const td=document.createElement('td'); td.innerHTML=cell.innerHTML; Array.from(cell.attributes).forEach(a=> td.setAttribute(a.name, a.value)); r.replaceChild(td, cell); } });
        tbody.insertBefore(r, tbody.firstChild);
      });
      thead.remove();
      table.classList.remove('ed-freeze-header');
    } else {
      // freeze: move first row from tbody into thead and convert to th
      const tbody=table.querySelector('tbody')||table.appendChild(document.createElement('tbody'));
      const firstRow=tbody.querySelector('tr'); if (!firstRow) return html;
      const thead=document.createElement('thead');
      const tr=document.createElement('tr');
      Array.from(firstRow.children).forEach(cell=>{ const th=document.createElement('th'); th.innerHTML=cell.innerHTML; Array.from(cell.attributes).forEach(a=> th.setAttribute(a.name, a.value)); tr.appendChild(th); });
      thead.appendChild(tr);
      tbody.removeChild(firstRow);
      table.insertBefore(thead, tbody);
      table.classList.add('ed-freeze-header');
    }
    return d.innerHTML; }catch(e){ return html; } },
  tableAddHeaderRow(html){ try{ const d=document.createElement('div'); d.innerHTML=html; const table=d.querySelector('table'); if (!table) return html; let thead=table.querySelector('thead'); const tbody=table.querySelector('tbody')||table.appendChild(document.createElement('tbody')); const firstBodyRow=tbody.querySelector('tr'); const cols = (firstBodyRow && firstBodyRow.children.length) || 1; if (!thead){ thead=document.createElement('thead'); table.insertBefore(thead, tbody); } const tr=document.createElement('tr'); for(let i=0;i<cols;i++){ const th=document.createElement('th'); th.innerHTML = '<p>&nbsp;</p>'; tr.appendChild(th); } thead.appendChild(tr); return d.innerHTML; }catch(e){ return html; } },
  tableRemoveHeaderRow(html){ try{ const d=document.createElement('div'); d.innerHTML=html; const thead=d.querySelector('thead'); if (!thead) return html; const rows=thead.querySelectorAll('tr'); if (rows.length){ rows[rows.length-1].remove(); if (!thead.querySelector('tr')) thead.remove(); } return d.innerHTML; }catch(e){ return html; } },
  tableInsertHeaderRowAt(html, headerRowIndex){ try{ const d=document.createElement('div'); d.innerHTML=html; const table=d.querySelector('table'); if (!table) return html; let thead=table.querySelector('thead'); const tbody=table.querySelector('tbody')||table.appendChild(document.createElement('tbody')); const bodyFirst=tbody.querySelector('tr'); const cols=(bodyFirst&&bodyFirst.children.length)||1; if (!thead){ thead=document.createElement('thead'); table.insertBefore(thead, tbody); } const rows=Array.from(thead.querySelectorAll('tr')); const tr=document.createElement('tr'); for(let i=0;i<cols;i++){ const th=document.createElement('th'); th.innerHTML='<p>&nbsp;</p>'; tr.appendChild(th);} const idx=Math.max(0, Math.min(headerRowIndex|0, rows.length)); thead.insertBefore(tr, rows[idx]||null); return d.innerHTML; }catch(e){ return html; } },
  tableRemoveHeaderRowAt(html, headerRowIndex){ try{ const d=document.createElement('div'); d.innerHTML=html; const thead=d.querySelector('thead'); if (!thead) return html; const rows=Array.from(thead.querySelectorAll('tr')); if (!rows.length) return html; const idx=Math.max(0, Math.min(headerRowIndex|0, rows.length-1)); rows[idx].remove(); if (!thead.querySelector('tr')) thead.remove(); return d.innerHTML; }catch(e){ return html; } },
  tableMergeRight(html, pos){ try{ if (!pos) return html; const d=document.createElement('div'); d.innerHTML=html; const rows=Array.from(d.querySelectorAll('tr')); const row=rows[pos.rowIndex]; if (!row) return html; const cells=Array.from(row.children); const cell=cells[pos.colIndex]; const next=cells[pos.colIndex+1]; if (!cell || !next) return html; const span = parseInt(cell.getAttribute('colspan')||'1',10) + parseInt(next.getAttribute('colspan')||'1',10); cell.setAttribute('colspan', String(span)); cell.innerHTML = (cell.innerHTML||'') + (next.innerHTML||''); next.remove(); return d.innerHTML; }catch(e){ return html; } },
  tableSplitCell(html, pos){ try{ if (!pos) return html; const d=document.createElement('div'); d.innerHTML=html; const rows=Array.from(d.querySelectorAll('tr')); const row=rows[pos.rowIndex]; if (!row) return html; const cells=Array.from(row.children); const cell=cells[pos.colIndex]; if (!cell) return html; const span = parseInt(cell.getAttribute('colspan')||'1',10); if (span<=1){ const clone=cell.cloneNode(true); clone.innerHTML = '<p>&nbsp;</p>'; cell.parentNode.insertBefore(clone, cell.nextSibling); } else { cell.setAttribute('colspan', String(span-1)); const clone=document.createElement(cell.tagName.toLowerCase()); clone.innerHTML='<p>&nbsp;</p>'; cell.parentNode.insertBefore(clone, cell.nextSibling); } return d.innerHTML; }catch(e){ return html; } },
  tableMergeDown(html, pos){ try{ if (!pos) return html; const d=document.createElement('div'); d.innerHTML=html; const rows=Array.from(d.querySelectorAll('tr')); const row=rows[pos.rowIndex]; const nextRow=rows[pos.rowIndex+1]; if (!row || !nextRow) return html; const cell=row.children[pos.colIndex]; const below=nextRow.children[pos.colIndex]; if (!cell || !below) return html; const span=parseInt(cell.getAttribute('rowspan')||'1',10)+parseInt(below.getAttribute('rowspan')||'1',10); cell.setAttribute('rowspan', String(span)); cell.innerHTML=(cell.innerHTML||'')+(below.innerHTML||''); below.remove(); return d.innerHTML; }catch(e){ return html; } },
  tableSplitDown(html, pos){ try{ if (!pos) return html; const d=document.createElement('div'); d.innerHTML=html; const rows=Array.from(d.querySelectorAll('tr')); const row=rows[pos.rowIndex]; const nextRow=rows[pos.rowIndex+1]; if (!row) return html; const cell=row.children[pos.colIndex]; if (!cell) return html; const span=parseInt(cell.getAttribute('rowspan')||'1',10); if (span<=1){ if (!nextRow){ const tr=document.createElement('tr'); const cols=row.children.length||1; for(let i=0;i<cols;i++){ const td=document.createElement('td'); td.innerHTML=i===pos.colIndex?'<p>&nbsp;</p>':' '; tr.appendChild(td);} row.parentElement.insertBefore(tr, row.nextSibling); } else { const td=document.createElement(cell.tagName.toLowerCase()); td.innerHTML='<p>&nbsp;</p>'; nextRow.insertBefore(td, nextRow.children[pos.colIndex]||null); } } else { cell.setAttribute('rowspan', String(span-1)); if (!nextRow){ const tr=document.createElement('tr'); const cols=row.children.length||1; for(let i=0;i<cols;i++){ const td=document.createElement('td'); td.innerHTML='<p>&nbsp;</p>'; tr.appendChild(td);} row.parentElement.insertBefore(tr, row.nextSibling); } else { const td=document.createElement(cell.tagName.toLowerCase()); td.innerHTML='<p>&nbsp;</p>'; nextRow.insertBefore(td, nextRow.children[pos.colIndex]||null); } } return d.innerHTML; }catch(e){ return html; } },
  tableSetCellBg(html, pos, color){ try{ if (!pos) return html; const d=document.createElement('div'); d.innerHTML=html; const rows=Array.from(d.querySelectorAll('tr')); const row=rows[pos.rowIndex]; if (!row) return html; const cells=Array.from(row.children); const cell=cells[pos.colIndex]; if (!cell) return html; cell.style.backgroundColor = color || ''; return d.innerHTML; }catch(e){ return html; } },
  gridAddCol(html, gridIndex){ try{ const d=document.createElement('div'); d.innerHTML=html; const grids=Array.from(d.querySelectorAll('.ed-grid')); if (!grids.length) return html; const g = grids[(gridIndex>=0 && gridIndex<grids.length)? gridIndex : 0]; if (!g) return html; const cls = Array.from(g.classList).find(c=>/^cols-\d$/.test(c)); let n = cls ? parseInt(cls.split('-')[1],10) : g.querySelectorAll('.ed-grid-cell').length || 1; n = Math.min(4, n+1); if (cls) g.classList.remove(cls); g.classList.add('cols-'+n); const cell=document.createElement('div'); cell.className='ed-grid-cell'; cell.innerHTML='<p>&nbsp;</p>'; g.appendChild(cell); return d.innerHTML; }catch(e){ return html; } },
  gridInsertCol(html, gridIndex, at){ try{ const d=document.createElement('div'); d.innerHTML=html; const grids=Array.from(d.querySelectorAll('.ed-grid')); if (!grids.length) return html; const g = grids[(gridIndex>=0 && gridIndex<grids.length)? gridIndex : 0]; if (!g) return html; const cells=Array.from(g.querySelectorAll('.ed-grid-cell')); const cls=Array.from(g.classList).find(c=>/^cols-\d$/.test(c)); let n=cls?parseInt(cls.split('-')[1],10):cells.length; if (n>=4) return html; const cell=document.createElement('div'); cell.className='ed-grid-cell'; cell.innerHTML='<p>&nbsp;</p>'; const ref=cells[at] || null; g.insertBefore(cell, ref); n=Math.min(4, n+1); if (cls) g.classList.remove(cls); g.classList.add('cols-'+n); 
    // Recalculate widths without layout: parse existing grid-template-columns inline style
    const styleAttr = g.getAttribute('style') || '';
    const matchPcts = [];
    const re = /minmax\(0,\s*([0-9.]+)%\)/g; let m;
    while ((m = re.exec(styleAttr))) { matchPcts.push(parseFloat(m[1])); }
    const oldFirst = (matchPcts.length>0 ? matchPcts[0] : (100 / (n))); // n is previous count before +1
    const newCount = g.querySelectorAll('.ed-grid-cell').length;
    const rest = Math.max(0, 100 - oldFirst);
    const each = rest / (newCount - 1 || 1);
    const pcts = Array.from({length:newCount}, (_,i)=> i===0 ? oldFirst : each);
    g.style.gridTemplateColumns = pcts.map(p=>`minmax(0, ${p}%)`).join(' ');
    return d.innerHTML; }catch(e){ return html; } },
  gridRemoveCol(html, gridIndex){ try{ const d=document.createElement('div'); d.innerHTML=html; const grids=Array.from(d.querySelectorAll('.ed-grid')); if (!grids.length) return html; const g = grids[(gridIndex>=0 && gridIndex<grids.length)? gridIndex : 0]; if (!g) return html; const cells = g.querySelectorAll('.ed-grid-cell'); if (cells.length>1){ g.removeChild(cells[cells.length-1]); } const cls = Array.from(g.classList).find(c=>/^cols-\d$/.test(c)); const count = g.querySelectorAll('.ed-grid-cell').length; const n = Math.max(1, Math.min(4, count)); if (cls) g.classList.remove(cls); g.classList.add('cols-'+n); return d.innerHTML; }catch(e){ return html; } },
  gridSetCols(html, gridIndex, n){ try{ n=Math.max(1,Math.min(4,parseInt(n,10)||1)); const d=document.createElement('div'); d.innerHTML=html; const grids=Array.from(d.querySelectorAll('.ed-grid')); if (!grids.length) return html; const g=grids[(gridIndex>=0&&gridIndex<grids.length)?gridIndex:0]; if (!g) return html; const cls=Array.from(g.classList).find(c=>/^cols-\d$/.test(c)); if (cls) g.classList.remove(cls); g.classList.add('cols-'+n); const cells=Array.from(g.querySelectorAll('.ed-grid-cell')); while(cells.length<n){ const c=document.createElement('div'); c.className='ed-grid-cell'; c.innerHTML='<p>&nbsp;</p>'; g.appendChild(c); cells.push(c);} while(cells.length>n){ const c=cells.pop(); c&&c.remove(); } return d.innerHTML; }catch(e){ return html; } },
  gridToggleCollapse(html, gridIndex){ try{ const d=document.createElement('div'); d.innerHTML=html; const grids=Array.from(d.querySelectorAll('.ed-grid')); if (!grids.length) return html; const g=grids[(gridIndex>=0&&gridIndex<grids.length)?gridIndex:0]; if (!g) return html; g.classList.toggle('ed-grid-collapse'); return d.innerHTML; }catch(e){ return html; } },
  gridToggleAuto(html, gridIndex){ try{ const d=document.createElement('div'); d.innerHTML=html; const grids=Array.from(d.querySelectorAll('.ed-grid')); if (!grids.length) return html; const g=grids[(gridIndex>=0&&gridIndex<grids.length)?gridIndex:0]; if (!g) return html; g.classList.toggle('ed-grid-auto'); return d.innerHTML; }catch(e){ return html; } },
  gridToggleReorder(html, gridIndex){ try{ const d=document.createElement('div'); d.innerHTML=html; const grids=Array.from(d.querySelectorAll('.ed-grid')); if (!grids.length) return html; const g=grids[(gridIndex>=0&&gridIndex<grids.length)?gridIndex:0]; if (!g) return html; g.classList.toggle('ed-grid-reorder'); return d.innerHTML; }catch(e){ return html; } },
  gridAdjustFirstCol(html, gridIndex, delta){ try{ const d=document.createElement('div'); d.innerHTML=html; const grids=Array.from(d.querySelectorAll('.ed-grid')); if (!grids.length) return html; const g=grids[(gridIndex>=0&&gridIndex<grids.length)?gridIndex:0]; if (!g) return html; const style=g.getAttribute('style')||''; const m=style.match(/--col1:(\d+)%/); let v=m?parseInt(m[1],10):100/ (g.className.match(/cols-(\d)/)?.[1] ? parseInt(g.className.match(/cols-(\d)/)[1],10) : 1); v=Math.max(10, Math.min(90, v + (delta*5))); const newStyle=style.replace(/--col1:\d+%/, '').trim(); g.setAttribute('style', (newStyle?newStyle+';':'')+`--col1:${v}%`); return d.innerHTML; }catch(e){ return html; } },
  gridResetWidths(html, gridIndex){ try{ const d=document.createElement('div'); d.innerHTML=html; const grids=Array.from(d.querySelectorAll('.ed-grid')); if (!grids.length) return html; const g=grids[(gridIndex>=0&&gridIndex<grids.length)?gridIndex:0]; if (!g) return html; g.style.removeProperty('--col1'); return d.innerHTML; }catch(e){ return html; } },
  gridSetFirstCol(html, gridIndex, value){ try{ const v=Math.max(10,Math.min(90,parseInt(value,10)||50)); const d=document.createElement('div'); d.innerHTML=html; const grids=Array.from(d.querySelectorAll('.ed-grid')); if (!grids.length) return html; const g=grids[(gridIndex>=0&&gridIndex<grids.length)?gridIndex:0]; if (!g) return html; const style=g.getAttribute('style')||''; const newStyle=style.replace(/--col1:\d+%/, '').trim(); g.setAttribute('style', (newStyle?newStyle+';':'')+`--col1:${v}%`); return d.innerHTML; }catch(e){ return html; } },
  linkifyContent(content){ try{ const urlRe=/(https?:\/\/[^\s]+)|(www\.[^\s]+)/g; const walker=document.createTreeWalker(content, NodeFilter.SHOW_TEXT, null); const toWrap=[]; while(walker.nextNode()){ const node=walker.currentNode; const t=node.nodeValue; if (t && urlRe.test(t)){ toWrap.push(node);} } toWrap.forEach(node=>{ const frag=document.createDocumentFragment(); const parts=String(node.nodeValue).split(/(https?:\/\/[^\s]+|www\.[^\s]+)/); parts.forEach(p=>{ if (!p) return; if (/^(https?:\/\/|www\.)/.test(p)){ const a=document.createElement('a'); a.href=p.startsWith('http')?p:`https://${p}`; a.target='_blank'; a.rel='noopener'; a.textContent=p; frag.appendChild(a);} else { frag.appendChild(document.createTextNode(p)); } }); node.parentNode.replaceChild(frag, node); }); }catch(e){} },
  handleHtmlBlockKeys(ev, surface, index){
    const target = ev.target;
    if (!target.closest('table') && !target.closest('.ed-grid')) return;
    if (ev.key==='Enter'){
      const table = target.closest('table');
      if (table){
        const lastRow = table.tBodies[0]?.rows[table.tBodies[0].rows.length-1];
        const lastCell = lastRow?.cells[lastRow.cells.length-1];
        const isInLastCell = lastCell && lastCell.contains(target);
        if (isInLastCell){
          ev.preventDefault();
          surface.__blocks[index].html = this.tableAddRow(surface.__blocks[index].html);
          this.render(surface, index, true);
          // Focus first cell of newly added last row
          setTimeout(()=>{
            // For new structure: content is the block element itself for most types
            const content = surface.children[index]?.querySelector('.ed-block-content') || surface.children[index];
            const tbl = content && content.querySelector('table');
            const tbody = tbl && tbl.tBodies[0];
            const newLastRow = tbody && tbody.rows[tbody.rows.length-1];
            const firstCell = newLastRow && newLastRow.cells[0];
            if (firstCell){
              this.normalizeTableCell(firstCell);
              const p = firstCell.querySelector('p') || firstCell;
              const r = document.createRange(); r.selectNodeContents(p); r.collapse(true);
              const sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
              firstCell.focus();
            }
          }, 0);
          return;
        }
      }
      const grid = target.closest('.ed-grid');
      if (grid){
        // [grid-enter-handler] key: ${ev.key}, cell: ${grid && grid.className}
        // Keep Enter inside the current grid cell; normalize structure first
        ev.preventDefault(); ev.stopPropagation(); if (ev.stopImmediatePropagation) ev.stopImmediatePropagation();
        const sel = window.getSelection();
        const node = sel && (sel.anchorNode || sel.focusNode) || target;
        const anchorEl = (node && node.nodeType===1 ? node : node.parentElement) || target;
        const cell = anchorEl && anchorEl.closest ? anchorEl.closest('.ed-grid-cell') : target.closest('.ed-grid-cell');
        if (cell){
          this.normalizeGridCell(cell);
          // Determine current paragraph and insert a new paragraph after it
          const currentP = anchorEl.closest('p') || cell.querySelector('p:last-child');
          const p = document.createElement('p'); p.innerHTML='&nbsp;';
          if (currentP && currentP.parentNode){ currentP.parentNode.insertBefore(p, currentP.nextSibling); }
          else { cell.querySelector('.ed-grid-cell-content')?.appendChild(p) || cell.appendChild(p); }
          const r=document.createRange(); const s=window.getSelection(); r.setStart(p,0); r.setEnd(p,0); s.removeAllRanges(); s.addRange(r);
          // For new structure: content is the block element itself for most types
          const blockContent = cell.closest('.block-control')?.querySelector('.ed-block-content') || cell.closest('.block-control');
          if (blockContent){ surface.__blocks[index].html = blockContent.innerHTML; surface.dispatchEvent(new Event('input')); }
        }
        return;
      }
    }
    if (ev.key==='Tab'){
      const table = target.closest('table');
      if (table){
        ev.preventDefault();
        const cells = Array.from(table.querySelectorAll('td,th'));
        const pos = cells.findIndex(c=> c.contains(target));
        if (pos>=0){
          let next = pos + (ev.shiftKey ? -1 : 1);
          if (next >= cells.length){
            surface.__blocks[index].html = this.tableAddRow(surface.__blocks[index].html);
            this.render(surface, index, true);
            // Focus first cell of newly added last row
            setTimeout(()=>{
              // For new structure: content is the block element itself for most types
              const content = surface.children[index]?.querySelector('.ed-block-content') || surface.children[index];
              const tbl = content && content.querySelector('table');
              const tbody = tbl && tbl.tBodies[0];
              const newLastRow = tbody && tbody.rows[tbody.rows.length-1];
              const firstCell = newLastRow && newLastRow.cells[0];
              if (firstCell){
                this.normalizeTableCell(firstCell);
                const p = firstCell.querySelector('p') || firstCell;
                const r = document.createRange(); r.selectNodeContents(p); r.collapse(true);
                const sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
                firstCell.focus();
              }
            }, 0);
            return;
          }
          if (next < 0) next = 0;
          const nextCell = cells[next];
          // Place caret at start of first paragraph or content
          const p = nextCell.querySelector('p') || nextCell;
          const r = document.createRange();
          r.selectNodeContents(p);
          r.collapse(true);
          const sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
          nextCell.focus();
        }
      }
    }
  },
  normalizeTableCell(cell){
    try{
      if (!cell || !(cell.tagName && /^(TD|TH)$/i.test(cell.tagName))) return;
      // Wrap stray text nodes in <p>
      const toMove = [];
      Array.from(cell.childNodes).forEach(n=>{
        if (n.nodeType === Node.TEXT_NODE){ const txt = n.nodeValue; if (txt && txt.trim().length){ const p=document.createElement('p'); p.textContent = txt; cell.insertBefore(p, n); } cell.removeChild(n); }
      });
      if (!cell.querySelector('p')){ const p=document.createElement('p'); p.innerHTML='&nbsp;'; cell.appendChild(p); }
      cell.setAttribute('contenteditable','true');
    }catch(_e){}
  },
  normalizeGridCell(cell){
    try{
      if (!cell || !cell.classList) return;
      let wrap = cell.querySelector('.ed-grid-cell-content');
      if (!wrap){ wrap = document.createElement('div'); wrap.className='ed-grid-cell-content'; cell.insertBefore(wrap, cell.firstChild); }
      const isControl = (n)=> n.classList && (n.classList.contains('ed-grid-col-drag') || n.classList.contains('ed-col-resize') || n.classList.contains('ed-col-controls'));
      const move = [];
      Array.from(cell.childNodes).forEach(n=>{ if (n!==wrap && !isControl(n)) move.push(n); });
      move.forEach(n=>{
        if (n.nodeType===Node.TEXT_NODE){ const txt=n.nodeValue; if (txt && txt.trim().length){ const p=document.createElement('p'); p.textContent=txt; wrap.appendChild(p);} n.remove(); }
        else { wrap.appendChild(n); }
      });
      // Ensure at least one paragraph exists for caret logic
      if (!wrap.querySelector('p')){ const p=document.createElement('p'); p.innerHTML='&nbsp;'; wrap.appendChild(p); }
      // Restrict edit surface to the content wrapper only
      cell.setAttribute('contenteditable', 'false');
      wrap.setAttribute('contenteditable', 'true');
      // Remove accidental contenteditable on descendants other than wrapper
      Array.from(cell.querySelectorAll('[contenteditable]')).forEach(el=>{ if (el!==wrap) el.removeAttribute('contenteditable'); });
    }catch(e){}
  },
  normalizeEditableContainer(el){
    try{
      if (!el) return; if (el.classList.contains('ed-grid-cell')) return this.normalizeGridCell(el);
      // simple grid column .ed-col
      if (el.classList.contains('ed-col')){
        let wrap = el; // wrapper is the column itself
        // Wrap stray text nodes in <p>
        const move = [];
        Array.from(el.childNodes).forEach(n=>{ if (n.nodeType===Node.TEXT_NODE){ const txt=n.nodeValue; if (txt && txt.trim().length){ const p=document.createElement('p'); p.textContent=txt; el.insertBefore(p, n); } el.removeChild(n); } });
        if (!el.querySelector('p')){ const p=document.createElement('p'); p.innerHTML='&nbsp;'; el.appendChild(p); }
        el.setAttribute('contenteditable','true');
      }
    }catch(e){}
  },
  handleHtmlBlockClick(ev, surface, index){
    // Do not override an active text selection
    try {
      const sel = window.getSelection && window.getSelection();
      if (sel && sel.rangeCount && !sel.isCollapsed) return;
    } catch(_e) {}
    const cell = ev.target.closest('td,th,.ed-grid-cell');
    if (cell){
      // For grid cells, normalize structure and focus the content wrapper
      if (cell.classList.contains('ed-grid-cell')){
        this.normalizeGridCell(cell);
        const grid = cell.closest('.ed-grid');
        if (grid){ const grids = Array.from(grid.parentElement.querySelectorAll('.ed-grid')); surface.__activeGridIndex = grids.indexOf(grid); }
      }
      // For table cells, normalize and ensure caret is placed inside text
      if (/^(TD|TH)$/i.test(cell.tagName)){
        this.normalizeTableCell(cell);
      }
      // Store active cell position for table relative ops
      const td = ev.target.closest('td,th');
      if (td){
        const tr = td.parentElement; const table = tr.closest('table');
        const rowIndex = Array.from(table.querySelectorAll('tr')).indexOf(tr);
        const colIndex = Array.from(tr.children).indexOf(td);
        const thead = table.querySelector('thead');
        let inHeader = false; let headerRowIndex = -1;
        if (thead && thead.contains(tr)){
          inHeader = true;
          headerRowIndex = Array.from(thead.querySelectorAll('tr')).indexOf(tr);
        }
        surface.__activeTablePos = { rowIndex, colIndex, inHeader, headerRowIndex };
      }
      const focusEl = cell.querySelector('.ed-grid-cell-content') || cell;
      const lastP = (/^(TD|TH)$/i.test(cell.tagName) ? (cell.querySelector('p')||cell) : (focusEl.querySelector('p:last-child')));
      const r=document.createRange();
      if (lastP) { r.selectNodeContents(lastP); r.collapse(false); }
      else { r.selectNodeContents(focusEl); r.collapse(true); }
      const sel=window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
    }
  },
  promptEditClasses(surface, index){
    const current = (surface.__blocks[index]?.className || '').trim();
    const prev = (surface.__classHistory || []).slice(-12);
    const wrap = document.createElement('div'); wrap.className='ed-block-insert';
    const title = document.createElement('div'); title.textContent='Edit classes'; title.style.fontWeight='600'; title.style.marginBottom='4px'; wrap.appendChild(title);
    const input = document.createElement('input'); input.className='ed-input'; input.placeholder='space-separated classes…'; input.value=current; wrap.appendChild(input);
    const hint = document.createElement('div'); hint.style.fontSize='11px'; hint.style.color='var(--text-tertiary)'; hint.textContent='Enter to apply; Esc to cancel'; wrap.appendChild(hint);
    const list = document.createElement('div'); list.className='ed-list';
    prev.forEach(cls=>{ const b=document.createElement('button'); b.type='button'; b.className='ed-btn'; b.textContent=cls; b.addEventListener('click', ()=>{ const set=new Set([...(input.value.trim().split(/\s+/).filter(Boolean)), cls]); input.value=Array.from(set).join(' '); }); list.appendChild(b); });
    if (prev.length) wrap.appendChild(list);
    const actions = document.createElement('div'); actions.style.display='flex'; actions.style.gap='6px'; actions.style.marginTop='6px';
    const clearBtn = document.createElement('button'); clearBtn.type='button'; clearBtn.className='ed-btn'; clearBtn.textContent='Clear'; clearBtn.addEventListener('click', ()=>{ input.value=''; }); actions.appendChild(clearBtn);
    const applyBtn = document.createElement('button'); applyBtn.type='button'; applyBtn.className='ed-btn'; applyBtn.textContent='Apply'; applyBtn.addEventListener('click', ()=> apply()); actions.appendChild(applyBtn);
    wrap.appendChild(actions);
    document.body.appendChild(wrap);
    wrap.style.position='fixed'; wrap.style.left='50%'; wrap.style.top='20%'; wrap.style.transform='translate(-50%, 0)'; wrap.style.display='flex';
    const close=()=> wrap.remove();
    const apply=()=>{ const val=input.value.trim(); surface.__blocks[index].className = val; if (val){ const parts=val.split(/\s+/).filter(Boolean); surface.__classHistory = Array.from(new Set([...(surface.__classHistory||[]), ...parts])); } close(); this.render(surface, index, true); };
    input.addEventListener('keydown', (e)=>{ if (e.key==='Enter'){ apply(); } if (e.key==='Escape'){ close(); }});
    setTimeout(()=> input.focus(), 0);
  },
  // Helpers
  historySnapshot(surface){ return JSON.stringify(surface.__blocks); },
  historyPush(surface, initial=false, coalesce=false){
    try{
      const snap = this.historySnapshot(surface);
      const last = surface.__history[surface.__history.length-1];
      if (initial || last !== snap){
        if (coalesce){ surface.__history[surface.__history.length-1] = snap; }
        else { surface.__history.push(snap); }
        if (!initial) surface.__future = [];
      }
    }catch(e){}
  },
  historyUndo(surface){
    if (!surface.__history || surface.__history.length<=1) return;
    const cur = surface.__history.pop();
    surface.__future = surface.__future || [];
    surface.__future.push(cur);
    const prev = surface.__history[surface.__history.length-1];
    try{ surface.__blocks = JSON.parse(prev); }catch(e){}
    // Re-render and try to restore caret to last known block
    const idx = typeof surface.__lastIdx==='number' ? surface.__lastIdx : 0;
    this.render(surface, Math.min(idx, surface.__blocks.length-1), true, 'end');
  },
  historyRedo(surface){
    if (!surface.__future || surface.__future.length===0) return;
    const next = surface.__future.pop();
    surface.__history.push(next);
    try{ surface.__blocks = JSON.parse(next); }catch(e){}
    const idx = typeof surface.__lastIdx==='number' ? surface.__lastIdx : 0;
    this.render(surface, Math.min(idx, surface.__blocks.length-1), true, 'end');
  },
  getText(el){ return (el && el.textContent) ? el.textContent.replace(/\u00A0/g, ' ').trim() : ''; },
  autoConvert(surface, index, content){
    const blk = surface.__blocks[index];
    const raw = content.textContent || '';
    // Only convert when prefix matches and caret near start
    const sel = window.getSelection();
    if (!sel || sel.rangeCount===0) return;
    const atStart = this.isCaretAtStart(content);
    if (!atStart) return;
    const dash = /^\s*[-–—]\s+/; // -, en/em dash
    const ordered = /^\s*\d+\.\s+/;
    const quote = /^\s*>\s+/;
    const fence = /^\s*```\s*$/;
    if ((blk.type==='p' || blk.type==='h1'||blk.type==='h2'||blk.type==='h3') && dash.test(raw)){
      blk.type = 'ul';
      const stripped = raw.replace(dash,'');
      content.textContent = stripped;
      blk.html = content.innerHTML;
      this.render(surface, index, true);
    } else if ((blk.type==='p' || blk.type==='h1'||blk.type==='h2'||blk.type==='h3') && ordered.test(raw)){
      blk.type = 'ol';
      const stripped = raw.replace(ordered,'');
      content.textContent = stripped;
      blk.html = content.innerHTML;
      this.render(surface, index, true);
    } else if ((blk.type==='p') && quote.test(raw)){
      blk.type = 'quote';
      const stripped = raw.replace(quote,'');
      content.textContent = stripped;
      blk.html = content.innerHTML;
      this.render(surface, index, true);
    } else if (fence.test(raw)){
      blk.type = 'code';
      content.textContent = '';
      blk.html = '';
      this.render(surface, index, true);
    }
  },
  extractAfterCaretHTML(content){
    const sel = window.getSelection(); if (!sel || sel.rangeCount===0) return '';
    const r = sel.getRangeAt(0);
    const endRange = document.createRange();
    endRange.selectNodeContents(content);
    endRange.setStart(r.startContainer, r.startOffset);
    const frag = endRange.cloneContents();
    // Remove after part from original
    endRange.deleteContents();
    const div = document.createElement('div'); div.appendChild(frag);
    return div.innerHTML;
  },
  indexOfEl(surface, el){ return Array.prototype.indexOf.call(surface.children, el); },
  getActiveIndex(surface){ const el = document.activeElement && document.activeElement.closest('.block-control'); if (!el) return -1; return this.indexOfEl(surface, el); },
  getActiveOrLastIndex(surface){ const i = this.getActiveIndex(surface); return i>=0 ? i : (typeof surface.__lastIdx==='number' ? surface.__lastIdx : 0); },
  isCaretAtStart(el){ const sel = window.getSelection(); if (!sel || sel.rangeCount===0) return false; const r=sel.getRangeAt(0); return r.collapsed && this.rangeOffsetIn(el, r).offset===0; },
  isCaretAtEnd(el){ const sel = window.getSelection(); if (!sel || sel.rangeCount===0) return false; const r=sel.getRangeAt(0); if (!r.collapsed) return false; const info=this.rangeOffsetIn(el,r); return info.offset===info.length; },
  rangeOffsetIn(el, range){
    // Compute character offset in element by walking text nodes
    const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
    let length = 0, offset = 0, found = false;
    while (walker.nextNode()){
      const node = walker.currentNode;
      const textLen = node.nodeValue.length;
      if (!found && node === range.startContainer){ offset = length + range.startOffset; found = true; }
      length += textLen;
    }
    return {length, offset};
  },
  placeCaretAtEnd(el){ el.focus(); const range=document.createRange(); range.selectNodeContents(el); range.collapse(false); const sel=window.getSelection(); sel.removeAllRanges(); sel.addRange(range); }
};

// Serialization to canonical HTML
window.EditorModes.block.serializeBlocks = function(blocks){
  let html = '';
  const stack = []; // each item: { type: 'ul'|'ol', className?: string }
  function openList(type, className){ const attr = className ? ` class="${String(className).replace(/\"/g,'&quot;')}"` : ''; html += `<${type}${attr}>`; stack.push({type, className}); }
  function closeList(){ const ctx = stack.pop(); if (ctx) html += `</${ctx.type}>`; }
  function closeToDepth(depth){ while (stack.length > depth) closeList(); }
  function currentType(){ return stack.length? stack[stack.length-1].type : null; }
  for (const b of blocks){
    const isList = b.type==='ul' || b.type==='ol';
    const depth = (b.indent||0);
    if (!isList){
      // close all open lists
      closeToDepth(0);
      const cls = b.className ? ` class="${String(b.className).replace(/"/g,'&quot;')}"` : '';
      if (b.type==='p'){ html += `<p${cls}>${b.html||''}</p>`; }
      else if (/^h[1-6]$/.test(b.type)){ const level=b.type.substring(1); html += `<h${level}${cls}>${b.html||''}</h${level}>`; }
      else if (b.type==='quote'){ html += `<blockquote${cls}>${b.html||''}</blockquote>`; }
      else if (b.type==='code'){ const langAttr = b.codeLang ? ` data-lang="${String(b.codeLang).replace(/"/g,'&quot;')}"` : ''; html += `<pre${cls}${langAttr}><code>${(b.html||'').replace(/<br\s*\/?>(?=\n?)/gi,'\n')}</code></pre>`; }
      else if (b.type==='html'){ html += b.html||''; }
      else { html += `<div${cls}>${b.html||''}</div>`; }
      continue;
    }
    // list item
    // Ensure stack depth = depth+1 (so indent 0 => one list wrapper)
    const neededDepth = depth + 1;
    if (stack.length < neededDepth){
      // open lists until depth reached
      while (stack.length < neededDepth) openList(b.type, b.listClassName);
    } else if (stack.length > neededDepth){
      closeToDepth(neededDepth);
    }
    // Ensure current list type matches
    if (currentType() !== b.type){
      // close one and open the other
      closeToDepth(neededDepth-1);
      openList(b.type, b.listClassName);
    }
    // list item with optional attrs
    const esc=(v)=> String(v).replace(/\"/g,'&quot;').replace(/"/g,'&quot;');
    const parts=[];
    if (b.className) parts.push(`class="${esc(b.className)}"`);
    if (b.domId) parts.push(`id="${esc(b.domId)}"`);
    if (b.role) parts.push(`role="${esc(b.role)}"`);
    if (b.dataAttrs){ String(b.dataAttrs).split(/\n+/).forEach(line=>{ const m=String(line).trim().match(/^([a-zA-Z][\w-]*)\s*=\s*(.+)$/); if (m){ parts.push(`${m[1].startsWith('data-')?m[1]:('data-'+m[1])}="${esc(m[2])}"`);} }); }
    const styles=[]; if (b.textColor) styles.push(`color:${b.textColor}`); if (b.bgColor) styles.push(`background-color:${b.bgColor}`);
    if (styles.length) parts.push(`style="${esc(styles.join(';'))}"`);
    const aLi = parts.length? (' '+parts.join(' ')) : '';
    html += `<li${aLi}>${b.html||''}</li>`;
  }
  closeToDepth(0);
  return html;
};

window.EditorModes.block.toHTML = function(surface){
  if (!surface || !surface.__blocks) return '';
  return this.serializeBlocks(surface.__blocks);
};
// Floating bubble helpers
window.EditorModes.block.ensureBubble = function(surface){
  if (surface.__bubble && document.body.contains(surface.__bubble)) return;
  const b = document.createElement('div'); b.className='ed-bubble';
  function btn(label, cmd){ const d=document.createElement('button'); d.type='button'; d.textContent=label; d.className='ed-btn'; d.title=label; d.addEventListener('mousedown', (e)=>{ e.preventDefault(); e.stopPropagation(); }); d.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation(); window.EditorModes && window.EditorModes.block && window.EditorModes.block.restoreSelection && window.EditorModes.block.restoreSelection(surface); document.execCommand('styleWithCSS', false, true); window.EditorCore && window.EditorCore.Bus.emit(cmd); setTimeout(()=> window.EditorModes && window.EditorModes.block && window.EditorModes.block.positionBubble && window.EditorModes.block.positionBubble(surface), 0); }); return d; }
  const C = (window.EditorCore && window.EditorCore.Commands) || {};
  b.appendChild(btn('B', {type:C.BOLD}));
  b.appendChild(btn('I', {type:C.ITALIC}));
  b.appendChild(btn('U', {type:C.UNDERLINE}));
  b.appendChild(btn('S', {type:C.STRIKE}));
  b.appendChild(btn('H1', {type:C.HEADING, level:1}));
  b.appendChild(btn('•', {type:C.UL}));
  b.appendChild(btn('1.', {type:C.OL}));
  // Link preview + actions
  const linkWrap=document.createElement('span'); linkWrap.style.display='inline-flex'; linkWrap.style.alignItems='center'; linkWrap.style.gap='6px';
  const linkBtn=btn('🔗', {type:C.LINK});
  const unlinkBtn=btn('⨯', {type:C.CLEAR_LINK});
  const hrefPreview=document.createElement('a'); hrefPreview.textContent='(link)'; hrefPreview.target='_blank'; hrefPreview.rel='noopener noreferrer'; hrefPreview.style.maxWidth='160px'; hrefPreview.style.whiteSpace='nowrap'; hrefPreview.style.overflow='hidden'; hrefPreview.style.textOverflow='ellipsis';
  linkWrap.appendChild(linkBtn); linkWrap.appendChild(unlinkBtn); linkWrap.appendChild(hrefPreview);
  b.appendChild(linkWrap);
  // Color pickers
  const colorWrap=document.createElement('span'); colorWrap.style.display='inline-flex'; colorWrap.style.alignItems='center'; colorWrap.style.gap='4px';
  const fg=document.createElement('input'); fg.type='color'; fg.title='Text color'; fg.addEventListener('input', ()=> window.EditorCore && window.EditorCore.Bus.emit({type:C.TEXT_COLOR, value: fg.value}));
  const bg=document.createElement('input'); bg.type='color'; bg.title='Background color'; bg.addEventListener('input', ()=> window.EditorCore && window.EditorCore.Bus.emit({type:C.BG_COLOR, value: bg.value}));
  colorWrap.appendChild(fg); colorWrap.appendChild(bg); b.appendChild(colorWrap);
  // Quick callout conversions
  const callWrap=document.createElement('span'); callWrap.style.display='inline-flex'; callWrap.style.alignItems='center'; callWrap.style.gap='4px';
  const callBtn=(label,variant)=>{ const d=document.createElement('button'); d.type='button'; d.textContent=label; d.className='ed-btn'; d.title=`Callout: ${variant}`; d.addEventListener('mousedown', (e)=>{ e.preventDefault(); e.stopPropagation(); }); d.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation(); window.EditorModes && window.EditorModes.block && window.EditorModes.block.restoreSelection && window.EditorModes.block.restoreSelection(surface); window.EditorCore && window.EditorCore.Bus.emit({type:C.CALLOUT, variant}); }); return d; };
  callWrap.appendChild(callBtn('Note','note')); callWrap.appendChild(callBtn('Info','info')); callWrap.appendChild(callBtn('Warn','warn'));
  b.appendChild(callWrap);
  document.body.appendChild(b); surface.__bubble = b;
};
window.EditorModes.block.positionBubble = function(surface){
  const sel = window.getSelection(); if (!sel || sel.rangeCount===0) { this.hideBubble(surface); return; }
  if (!this.isSelectionInside(surface)) { this.hideBubble(surface); return; }
  const r = sel.getRangeAt(0); if (r.collapsed) { this.hideBubble(surface); return; }
  // Use exact caret rect for centering above selection
  const rects = r.getClientRects();
  const primary = rects.length ? rects[0] : r.getBoundingClientRect();
  const b = surface.__bubble; if (!b) return;
  // Force measurement by temporarily displaying if needed
  const prevDisplay = b.style.display; b.style.display = 'flex';
  const bubbleW = b.offsetWidth || 220; const bubbleH = b.offsetHeight || 36; b.style.display = prevDisplay || 'flex';
  const centerX = primary.left + (primary.width/2);
  const top = primary.top - (bubbleH + 16);
  const left = centerX - bubbleW/2;
  b.style.top = Math.max(6, top) + 'px'; b.style.left = Math.max(6, Math.min(left, window.innerWidth - bubbleW - 6)) + 'px';
  b.style.display='flex';
  // Update link preview
  const a = this.getAnchorAtSelection(surface);
  const preview = b.querySelector('a'); if (preview){ if (a && a.href){ preview.href=a.href; preview.textContent=a.href; preview.style.display='inline'; } else { preview.removeAttribute('href'); preview.textContent=''; preview.style.display='none'; } }
};
window.EditorModes.block.hideBubble = function(surface){ const b=surface.__bubble; if (b) b.style.display='none'; };

// caret rect helper for aligning palettes to current caret/line
window.EditorModes.block.caretRect = function(target){
  const sel = window.getSelection();
  if (!sel || sel.rangeCount===0) return null;
  const r = sel.getRangeAt(0);
  const rects = r.getClientRects();
  if (rects && rects.length) return rects[0];
  const dummy = document.createElement('span');
  dummy.textContent='\u200b';
  r.insertNode(dummy);
  const rect = dummy.getBoundingClientRect();
  dummy.parentNode && dummy.parentNode.removeChild(dummy);
  return rect;
};

// Selection helpers
window.EditorModes.block.isSelectionInside = function(surface){
  const sel = window.getSelection();
  if (!sel || sel.rangeCount===0) return false;
  const range = sel.getRangeAt(0);
  const node = range.commonAncestorContainer;
  return surface.contains(node.nodeType === 1 ? node : node.parentNode);
};

window.EditorModes.block.isSelectionInsideBubble = function(surface){
  const sel = window.getSelection(); if (!sel || sel.rangeCount===0) return false;
  const node = sel.getRangeAt(0).commonAncestorContainer;
  const bubble = surface.__bubble; if (!bubble) return false;
  const el = node.nodeType === 1 ? node : node.parentNode;
  return bubble.contains(el);
};

window.EditorModes.block.saveSelection = function(surface){
  const sel = window.getSelection();
  if (!sel || sel.rangeCount===0) return;
  if (!this.isSelectionInside(surface)) return;
  const r = sel.getRangeAt(0).cloneRange();
  surface.__savedSelection = { startContainer: r.startContainer, startOffset: r.startOffset, endContainer: r.endContainer, endOffset: r.endOffset };
};

window.EditorModes.block.restoreSelection = function(surface){
  const s = surface.__savedSelection; if (!s) return;
  const r = document.createRange();
  try {
    r.setStart(s.startContainer, s.startOffset);
    r.setEnd(s.endContainer, s.endOffset);
    const sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
  } catch(e) {}
};
// Simple modal wrapper with backdrop + focus trap
window.EditorModes.block.showModal = function(wrap){
  const backdrop = document.createElement('div'); backdrop.className='ed-dialog-backdrop';
  const panel = document.createElement('div'); panel.className='ed-dialog-panel';
  // Click-outside close affordance
  panel.setAttribute('role','dialog'); panel.setAttribute('aria-modal','true');
  const titleEl = wrap.querySelector('h1,h2,h3,div[role="heading"],.ed-title');
  if (titleEl && titleEl.id){ panel.setAttribute('aria-labelledby', titleEl.id); }
  // Ensure legacy popover styles are neutralized inside modal
  try {
    wrap.style.display = 'flex';
    wrap.style.position = 'static';
    wrap.style.boxShadow = 'none';
    wrap.style.zIndex = 'auto';
  } catch(e) {}
  panel.appendChild(wrap); backdrop.appendChild(panel);
  document.body.appendChild(backdrop);
  // Focus trap inside panel
  const focusables = ()=> Array.from(panel.querySelectorAll('button,[href],input,select,textarea,[tabindex]:not([tabindex="-1"])')).filter(el=> !el.hasAttribute('disabled'));
  const first = ()=> focusables()[0];
  const last = ()=> { const f = focusables(); return f[f.length-1]; };
  const keyHandler = (e)=>{
    if (e.key === 'Tab'){
      const f = focusables(); if (!f.length) return;
      const current = document.activeElement;
      const idx = f.indexOf(current);
      if (e.shiftKey){ if (idx<=0){ e.preventDefault(); last()?.focus(); } }
      else { if (idx===f.length-1){ e.preventDefault(); first()?.focus(); } }
    }
    if (e.key === 'Escape'){ e.preventDefault(); close(); }
  };
  backdrop.addEventListener('keydown', keyHandler);
  setTimeout(()=> first()?.focus(), 0);
  const onClick = (e)=>{ if (e.target === backdrop) close(); };
  backdrop.addEventListener('click', onClick);
  // Close button support
  const addCloseBtn = ()=>{ const btn = document.createElement('button'); btn.type='button'; btn.className='ed-btn'; btn.textContent='Close'; btn.style.position='absolute'; btn.style.top='8px'; btn.style.right='8px'; btn.addEventListener('click', (ev)=>{ ev.preventDefault(); close(); }); panel.appendChild(btn); };
  addCloseBtn();
  const close = ()=>{ backdrop.removeEventListener('keydown', keyHandler); backdrop.removeEventListener('click', onClick); backdrop.remove(); };
  return { close };
};
window.EditorModes.block.getAnchorAtSelection = function(surface){
  try{
    const sel=window.getSelection(); if (!sel||sel.rangeCount===0) return null;
    let node = sel.getRangeAt(0).commonAncestorContainer;
    node = node.nodeType===1 ? node : node.parentNode;
    return node.closest && node.closest('a');
  }catch(e){ return null; }
};
// Block properties dialog
window.EditorModes.block.openBlockPropsDialog = function(surface, index){
  const blk = surface.__blocks[index]; if (!blk) return;
  const wrap = document.createElement('div'); wrap.className='ed-block-insert';
  const title=document.createElement('div'); title.textContent='Block properties'; title.style.fontWeight='600'; title.style.marginBottom='6px'; wrap.appendChild(title);
  const idIn=document.createElement('input'); idIn.className='ed-input'; idIn.placeholder='id'; idIn.value=blk.domId||''; wrap.appendChild(idIn);
  const roleIn=document.createElement('input'); roleIn.className='ed-input'; roleIn.placeholder='role'; roleIn.value=blk.role||''; wrap.appendChild(roleIn);
  const dataIn=document.createElement('textarea'); dataIn.className='ed-input'; dataIn.placeholder='data attributes (key=value per line)'; dataIn.style.minHeight='80px'; dataIn.value=blk.dataAttrs||''; wrap.appendChild(dataIn);
  // Optional list wrapper classes for list items
  let listClsIn = null;
  if (blk.type==='ul' || blk.type==='ol'){
    listClsIn = document.createElement('input'); listClsIn.className='ed-input'; listClsIn.placeholder='list wrapper classes (applies to <ul>/<ol>)'; listClsIn.value=blk.listClassName||''; wrap.appendChild(listClsIn);
  }
  const actions=document.createElement('div'); actions.style.display='flex'; actions.style.gap='6px'; actions.style.marginTop='6px';
  const apply=document.createElement('button'); apply.type='button'; apply.className='ed-btn'; apply.textContent='Apply'; actions.appendChild(apply);
  const cancel=document.createElement('button'); cancel.type='button'; cancel.className='ed-btn'; cancel.textContent='Cancel'; actions.appendChild(cancel);
  wrap.appendChild(actions); document.body.appendChild(wrap);
  wrap.style.position='fixed'; wrap.style.left='50%'; wrap.style.top='20%'; wrap.style.transform='translate(-50%, 0)'; wrap.style.display='flex';
  const close=()=> wrap.remove();
  const applyFn=()=>{ blk.domId=idIn.value.trim()||''; blk.role=roleIn.value.trim()||''; blk.dataAttrs=dataIn.value; if (listClsIn){ blk.listClassName = listClsIn.value.trim(); } this.historyPush(surface); this.render(surface, index, true); close(); };
  apply.addEventListener('click', applyFn);
  cancel.addEventListener('click', close);
  setTimeout(()=> idIn.focus(), 0);
};
// Drag and drop helpers
window.EditorModes.block.beginDrag = function(surface, fromIndex, evt){
  surface.classList.add('dragging');
  const selectedIds = Array.isArray(surface.__selectedIds) ? surface.__selectedIds : [];
  const idAt = surface.__blocks[fromIndex]?.id;
  let group = [];
  if (selectedIds.length > 1 && idAt && selectedIds.includes(idAt)){
    // Drag as a group
    group = selectedIds.map(id=> surface.__blocks.findIndex(b=> b.id===id)).filter(i=> i>=0).sort((a,b)=> a-b);
  } else {
    group = [fromIndex];
  }
  surface.__dragGroup = group;
  // Visually mark all dragged blocks
  group.forEach(i=>{ const el = surface.children[i]; if (el) el.setAttribute('data-dragging','true'); });
  this.ensureDropIndicator(surface);
  surface.__dragFrom = fromIndex;
  surface.__dropTarget = { index: fromIndex, place: 'before' };
  surface.__dragActive = true;
  this.onDrag(surface, evt);
  const move = (e)=> this.onDrag(surface, e);
  const up = (e)=> { document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up); this.finishDrag(surface, e); };
  document.addEventListener('mousemove', move);
  document.addEventListener('mouseup', up);
};

window.EditorModes.block.onDrag = function(surface, evt){
  if (!surface.__dragActive) return;
  const target = this.computeDropTarget(surface, evt.clientY);
  surface.__dropTarget = target;
  const di = surface.__dropIndicator; if (!di) return;
  const refEl = surface.children[target.index]; if (!refEl) return;
  const sRect = surface.getBoundingClientRect(); const r = refEl.getBoundingClientRect();
  const y = (target.place==='before'? r.top : r.bottom) - sRect.top + surface.scrollTop;
  di.style.display = 'block';
  di.style.top = Math.max(0, y - 1) + 'px';
};

window.EditorModes.block.finishDrag = function(surface){
  const from = surface.__dragFrom;
  const target = surface.__dropTarget;
  surface.classList.remove('dragging');
  // Clear dragging markers
  Array.from(surface.children).forEach(el=> el.removeAttribute('data-dragging'));
  if (surface.__dropIndicator) surface.__dropIndicator.style.display='none';
  surface.__dragActive = false;
  if (!Number.isInteger(from) || !target) { this.render(surface, 0, false); return; }
  // Compute insertion index relative to original positions
  let insertIndex = target.index + (target.place === 'after' ? 1 : 0);
  const group = Array.isArray(surface.__dragGroup) && surface.__dragGroup.length ? surface.__dragGroup.slice().sort((a,b)=>a-b) : [from];
  // If inserting after last block, clamp
  insertIndex = Math.max(0, Math.min(insertIndex, surface.__blocks.length));
  // Count how many dragged indices are before the insertion index to adjust after removal
  const countBefore = group.filter(i=> i < insertIndex).length;
  // Extract blocks in original order
  const moved = group.map(i=> surface.__blocks[i]);
  // Remove in reverse order to avoid shifting
  group.slice().reverse().forEach(i=>{ surface.__blocks.splice(i,1); });
  // Adjust insert index after removals
  insertIndex -= countBefore;
  // Insert moved blocks maintaining order
  surface.__blocks.splice(insertIndex, 0, ...moved);
  // Preserve selection on moved blocks
  surface.__selectedIds = moved.map(b=> b.id);
  this.render(surface, insertIndex, true);
};

window.EditorModes.block.ensureDropIndicator = function(surface){
  if (!surface.__dropIndicator){ const d=document.createElement('div'); d.className='ed-drop-indicator'; surface.appendChild(d); surface.__dropIndicator = d; }
};

window.EditorModes.block.computeDropTarget = function(surface, clientY){
  const blocks = Array.from(surface.children);
  if (blocks.length === 0) return { index: 0, place: 'before' };
  for (let i=0;i<blocks.length;i++){
    const r = blocks[i].getBoundingClientRect();
    const mid = (r.top + r.bottom) / 2;
    if (clientY < mid) return { index: i, place: 'before' };
  }
  return { index: blocks.length - 1, place: 'after' };
};


