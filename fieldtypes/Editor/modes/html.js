window.EditorModes = window.EditorModes || {};
window.EditorModes.html = {
  lineHeight: 20,
  bind(wrapper){
    const ht = wrapper.querySelector('.ed-html');
    const gutter = wrapper.querySelector('.code-gutter');
    if (!ht || !gutter) return;
    // normalize line-height
    ht.style.lineHeight = this.lineHeight + 'px';
    gutter.style.lineHeight = this.lineHeight + 'px';
    // sync scroll
    ht.addEventListener('scroll', () => { gutter.scrollTop = ht.scrollTop; });
    // update on input and resize
    ht.addEventListener('input', () => this.update(wrapper));
    if (window.ResizeObserver) {
      const ro = new ResizeObserver(() => this.update(wrapper));
      ro.observe(ht);
    }
    this.update(wrapper);
  },
  update(wrapper){
    const ht = wrapper.querySelector('.ed-html');
    const gutter = wrapper.querySelector('.code-gutter');
    if (!ht || !gutter) return;
    const lines = Math.max(1, ht.value.split('\n').length);
    let html='';
    for(let i=1;i<=lines;i++){ html += `<div style="height:${this.lineHeight}px">${i}</div>`; }
    gutter.innerHTML = html;
    // Match gutter height to textarea's client height (no forced wrapper height)
    gutter.style.height = ht.clientHeight + 'px';
    gutter.scrollTop = ht.scrollTop;
  }
};


