/* Haru Editor minimal model scaffolding
 * Phase 1: DOM-backed model with HTML serialization only.
 * Evolves to a proper tree in later phases.
 */
(function(){
  if (window.HaruEditor && window.HaruEditor.Model) return;
  window.HaruEditor = window.HaruEditor || {};
  const Model = {
    /** Serialize editor surface DOM to HTML string */
    toHTML(surface){
      try{ const html = surface.querySelector('.ed-block-content')?.innerHTML || ''; return html; }catch(e){ return ''; }
    },
    /** Replace editor content from HTML (destructive) */
    fromHTML(surface, html){
      const el = surface.querySelector('.ed-block-content'); if (!el) return; el.innerHTML = html || '';
    }
  };
  window.HaruEditor.Model = Model;
})();


