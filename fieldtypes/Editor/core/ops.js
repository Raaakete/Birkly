/* Haru Editor Ops: shared caret helpers and basic edit operations */
(function(){
  if (window.HaruEditor && window.HaruEditor.Ops) return;
  window.HaruEditor = window.HaruEditor || {};

  function getRangeOffsetIn(el, range){
    try{
      const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null);
      let length = 0, offset = 0, found = false;
      while (walker.nextNode()){
        const node = walker.currentNode;
        const textLen = node.nodeValue.length;
        if (!found && node === range.startContainer){ offset = length + range.startOffset; found = true; }
        length += textLen;
      }
      return {length, offset};
    }catch(_e){ return {length: 0, offset: 0}; }
  }

  const Ops = {
    // Inline formatting
    applyMark(cmd){ try{ document.execCommand('styleWithCSS', false, true); document.execCommand(cmd); }catch(e){} },
    removeFormatting(){ try{ document.execCommand('removeFormat'); }catch(e){} },

    // Paragraph helpers
    insertSoftBreak(){ try{ document.execCommand('insertLineBreak'); }catch(e){} },
    insertParagraphNative(){ try{ document.execCommand('insertParagraph'); }catch(e){} },

    // Caret location within an element
    caretAtStart(el){ const sel = window.getSelection(); if (!el || !sel || sel.rangeCount===0) return false; const r=sel.getRangeAt(0); return r.collapsed && getRangeOffsetIn(el, r).offset===0; },
    caretAtEnd(el){ const sel = window.getSelection(); if (!el || !sel || sel.rangeCount===0) return false; const r=sel.getRangeAt(0); if (!r.collapsed) return false; const info=getRangeOffsetIn(el,r); return info.offset===info.length; },

    // Split an element's HTML at the current caret; mutates DOM to keep before part
    splitAtCaret(content){
      const sel = window.getSelection(); if (!content || !sel || sel.rangeCount===0) return {before: content?.innerHTML||'', after: ''};
      const r = sel.getRangeAt(0);
      const endRange = document.createRange();
      endRange.selectNodeContents(content);
      endRange.setStart(r.startContainer, r.startOffset);
      const frag = endRange.cloneContents();
      endRange.deleteContents();
      const before = content.innerHTML;
      const div = document.createElement('div'); div.appendChild(frag);
      const after = div.innerHTML;
      return { before, after };
    },

    // Merge two HTML strings (simple concatenation hook)
    mergeHtml(a, b){ return String(a||'') + String(b||''); },

    // Caret placement convenience
    placeCaretAtEnd(el){ if (!el) return; el.focus(); const range=document.createRange(); range.selectNodeContents(el); range.collapse(false); const sel=window.getSelection(); sel.removeAllRanges(); sel.addRange(range); },
    placeCaretAtStart(el){ if (!el) return; el.focus(); const range=document.createRange(); range.selectNodeContents(el); range.collapse(true); const sel=window.getSelection(); sel.removeAllRanges(); sel.addRange(range); }
  };

  window.HaruEditor.Ops = Ops;
})();


