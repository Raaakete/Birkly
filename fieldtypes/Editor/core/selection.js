/* Minimal selection save/restore */
(function(){
  if (window.HaruEditor && window.HaruEditor.Selection) return;
  window.HaruEditor = window.HaruEditor || {};
  const Selection = {
    save(surface){ const sel=window.getSelection(); if (!sel||sel.rangeCount===0) return; surface.__savedSelection = sel.getRangeAt(0).cloneRange(); },
    restore(surface){ const r=surface.__savedSelection; if (!r) return; const sel=window.getSelection(); sel.removeAllRanges(); sel.addRange(r); }
  };
  window.HaruEditor.Selection = Selection;
})();


