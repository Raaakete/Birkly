/* Simple flex-based grid with per-column resizers and reorder */
(function(){
  if (window.HaruEditor && window.HaruEditor.Grid) return;
  window.HaruEditor = window.HaruEditor || {};

  function createGrid(cols){
    const n=Math.min(4,Math.max(1,parseInt(cols||'2',10))); const g=document.createElement('div');
    g.className='ed-grid'; g.setAttribute('data-cols', String(n));
    for(let i=0;i<n;i++){
      const c=document.createElement('div'); c.className='ed-col'; c.setAttribute('contenteditable','true'); c.style.setProperty('--basis', (100/n)+'%'); c.innerHTML='<p>&nbsp;</p>';
      if (i < n-1){ const r=document.createElement('div'); r.className='ed-col-resizer'; r.contentEditable='false'; c.appendChild(r); }
      const h=document.createElement('div'); h.className='ed-col-drag'; h.contentEditable='false'; c.appendChild(h);
      g.appendChild(c);
    }
    bindAll(g);
    return g;
  }

  function bindAll(grid){ bindResizers(grid); bindReorder(grid); observeGrid(grid); }

  function bindResizers(grid){
    const cols = Array.from(grid.querySelectorAll('.ed-col'));
    const resizers = Array.from(grid.querySelectorAll('.ed-col-resizer'));
    resizers.forEach((r, idx)=>{
      let dragging=false, startX=0, leftBasis=0, rightBasis=0;
      const leftCol = cols[idx];
      const rightCol = cols[idx+1];
      r.onmousedown = (e)=>{
        e.preventDefault(); e.stopPropagation(); dragging=true; startX=e.clientX;
        leftBasis = basisPct(grid, leftCol); rightBasis = basisPct(grid, rightCol);
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp, { once:true });
      };
      function onMove(e){ if (!dragging) return; const dx = (e.clientX - startX) / grid.offsetWidth * 100; const min=10; let newLeft = Math.max(min, Math.min(leftBasis + dx, leftBasis + rightBasis - min)); let newRight = leftBasis + rightBasis - newLeft; leftCol.style.setProperty('--basis', newLeft+'%'); rightCol.style.setProperty('--basis', newRight+'%'); }
      function onUp(){ dragging=false; document.removeEventListener('mousemove', onMove); }
    });
  }

  function bindReorder(grid){
    const handleSelector = '.ed-col-drag';
    const cols = ()=> Array.from(grid.querySelectorAll('.ed-col'));
    grid.querySelectorAll(handleSelector).forEach((h)=>{
      let dragging=false, startIndex=-1, ghost=null;
      h.onmousedown = (e)=>{
        e.preventDefault(); e.stopPropagation(); dragging=true;
        const col = h.parentElement; const list = cols(); startIndex = list.indexOf(col);
        ghost = col.cloneNode(true); ghost.style.opacity='0.5'; ghost.style.pointerEvents='none'; col.parentElement.insertBefore(ghost, col.nextSibling);
        col.style.opacity='0.6'; col.style.cursor='grabbing';
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp, { once:true });
      };
      function onMove(e){ if (!dragging) return; const list = cols(); const col = grid.querySelectorAll('.ed-col')[startIndex]; let target = document.elementFromPoint(e.clientX, e.clientY); target = target && target.closest && target.closest('.ed-col'); if (!target || target===col) return; const arr = list; const tIdx = arr.indexOf(target); if (tIdx<0) return; if (tIdx > startIndex) target.after(col); else target.before(col); startIndex = tIdx; }
      function onUp(){ const col = h.parentElement; if (ghost){ ghost.remove(); ghost=null; } dragging=false; col.style.opacity=''; col.style.cursor=''; document.removeEventListener('mousemove', onMove); }
    });
  }

  function basisPct(grid, col){ const b = parseFloat(getComputedStyle(col).getPropertyValue('--basis')); if (!isNaN(b)) return b; return col.offsetWidth / grid.offsetWidth * 100; }

  function observeGrid(grid){ if (grid.__gridObserver) return; const ro=new ResizeObserver(()=> bindAll(grid)); ro.observe(grid); grid.__gridObserver=ro; }

  function refresh(grid){ bindAll(grid); }

  window.HaruEditor.Grid = { createGrid, refresh };
})();


