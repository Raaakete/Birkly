/**
 * Studio IDE production features (P25) — design canvas, live preview, layout pro, CSS studio.
 */
(function () {
  'use strict';

  const IDE = () => window.StudioIDE;
  const $ = (s, root) => (root || document).querySelector(s);

  const prod = {
    outlines: true,
    stylesheetTab: 'custom',
    stylesCache: { kit: '', custom: '', generated: '' },
    collectionSchemas: {},
    columnDrag: null,
    boxDrag: null,
  };

  function cmsUrl() {
    if (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function') {
      return window.location.origin + Birkly.url('');
    }
    return window.location.origin + (window.BIRKLY_BASE_PATH || '');
  }

  function ensureCanvasStylesLink() {
    let link = document.getElementById('studio-canvas-styles');
    if (!link) {
      link = document.createElement('style');
      link.id = 'studio-canvas-styles';
      document.head.appendChild(link);
    }
    return link;
  }

  async function loadCanvasStyles() {
    const ide = IDE();
    if (!ide?.state?.project) return;
    try {
      const data = await ide.api('get_canvas_styles', { project_id: ide.state.project.id });
      const link = ensureCanvasStylesLink();
      link.textContent = data.css || '';
      prod.stylesCache.kit = data.kit || '';
      prod.stylesCache.custom = data.custom || '';
      prod.stylesCache.generated = data.generated || '';
    } catch (e) {
      console.warn('[Studio production] canvas styles', e);
    }
  }

  function setOutlines(on) {
    prod.outlines = on;
    const wrap = $('#studio-canvas-wrap');
    const btn = document.querySelector('[data-action="toggle-outlines"]');
    if (wrap) wrap.classList.toggle('studio-canvas--outlines', on);
    if (btn) btn.classList.toggle('active', on);
  }

  // ——— Live preview ———
  async function refreshLivePreview() {
    const ide = IDE();
    if (!ide?.state?.project || !ide.state.activeTab || !ide.isStudioPage(ide.state.activeTab)) return;
    const frame = $('#studio-live-frame');
    if (!frame) return;
    try {
      const slug = ide.pageSlugFromPath(ide.state.activeTab);
      const data = await ide.api('preview_live', {
        project_id: ide.state.project.id,
        page_slug: slug,
      });
      const base = data.cms_url || cmsUrl();
      const styles = data.styles_url
        ? `<link rel="stylesheet" href="${data.styles_url}">`
        : `<style>${data.css || ''}</style>`;
      const doc = `<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${styles}<script src="${base}/birkly-client.js" data-cms-url="${base}"><\/script></head><body class="studio-page">${data.body_html || ''}</body></html>`;
      frame.srcdoc = doc;
    } catch (e) {
      console.warn('[Studio production] live preview', e);
    }
  }

  function patchViewMode() {
    const ide = IDE();
    if (!ide) return;
    const orig = ide.applyViewMode;
    ide.applyViewMode = function applyViewModeProd() {
      const v = ide.state.viewMode;
      const visual = $('#studio-pane-visual');
      const code = $('#studio-pane-code');
      const live = $('#studio-pane-live');
      const panes = $('#studio-panes');
      if (!panes) return;
      panes.classList.remove('split', 'split-live');
      if (visual) visual.hidden = v === 'code' || v === 'live';
      if (code) code.hidden = v === 'visual' || v === 'live';
      if (live) live.hidden = v === 'visual' || v === 'code';
      if (v === 'split') {
        panes.classList.add('split');
        if (visual) visual.hidden = false;
        if (code) code.hidden = false;
        if (live) live.hidden = true;
      }
      if (v === 'live') {
        refreshLivePreview();
      }
    };
    ide._origApplyViewMode = orig;
  }

  function patchSave() {
    const ide = IDE();
    if (!ide) return;
    const orig = ide.saveActiveFile;
    ide.saveActiveFile = async function saveActiveFileProd() {
      await orig.call(ide);
      await loadCanvasStyles();
      if (ide.state.viewMode === 'live' || ide.state.viewMode === 'split') {
        await refreshLivePreview();
      }
      updateBoxOverlay();
      updateAlignToolbar();
      updateRichToolbar();
    };
  }

  function patchOpenProject() {
    const app = $('#studio-app');
    if (!app || app.dataset.prodPatched) return;
    app.dataset.prodPatched = '1';
    app.addEventListener('click', (e) => {
      const ide = IDE();
      if (!ide) return;
      if (e.target.closest('[data-action="toggle-outlines"]')) {
        setOutlines(!prod.outlines);
        return;
      }
      const alignBtn = e.target.closest('.studio-align-btn');
      if (alignBtn) {
        const el = ide.getSelectedElement();
        if (!el) return;
        ide.pushUndo();
        el.styles = el.styles || {};
        el.styles.textAlign = alignBtn.dataset.align;
        ide.markDirty(ide.state.activeTab);
        ide.saveActiveFile().catch(ide.alertErr);
        ide.renderCanvas();
        return;
      }
      const ssTab = e.target.closest('[data-ss-tab]');
      if (ssTab) {
        prod.stylesheetTab = ssTab.dataset.ssTab;
        ide.renderInspector();
        return;
      }
      const chipRemove = e.target.closest('[data-class-remove]');
      if (chipRemove) {
        const el = ide.getSelectedElement();
        if (!el) return;
        ide.pushUndo();
        const cls = chipRemove.dataset.classRemove;
        el.classes = (el.classes || []).filter((c) => c !== cls);
        ide.markDirty(ide.state.activeTab);
        ide.saveActiveFile().catch(ide.alertErr);
        ide.renderCanvas();
        ide.renderInspector();
        return;
      }
      const fieldBtn = e.target.closest('[data-insert-field]');
      if (fieldBtn) {
        insertField(fieldBtn.dataset.insertField);
        return;
      }
      const collToggle = e.target.closest('[data-coll-toggle]');
      if (collToggle) {
        const row = collToggle.closest('.studio-content-coll');
        row?.classList.toggle('expanded');
        if (row?.classList.contains('expanded') && !prod.collectionSchemas[collToggle.dataset.collToggle]) {
          loadCollectionFields(collToggle.dataset.collToggle);
        }
      }
    });
  }

  // ——— Box model overlay ———
  function parseBoxValue(v, fallback) {
    if (v == null || v === '') return fallback;
    const n = parseFloat(String(v));
    return isNaN(n) ? fallback : n;
  }

  function updateBoxOverlay() {
    const ide = IDE();
    const overlay = $('#studio-box-overlay');
    const wrap = $('#studio-canvas-wrap');
    if (!overlay || !wrap || !ide) return;
    const el = ide.getSelectedElement();
    const selectedDom = wrap.querySelector('.studio-el.selected');
    if (!el || !selectedDom) {
      overlay.hidden = true;
      return;
    }
    overlay.hidden = false;
    const wrapRect = wrap.getBoundingClientRect();
    const rect = selectedDom.getBoundingClientRect();
    const top = rect.top - wrapRect.top + wrap.scrollTop;
    const left = rect.left - wrapRect.left + wrap.scrollLeft;
    const m = {
      t: parseBoxValue(el.styles?.marginTop ?? el.styles?.margin, 0),
      r: parseBoxValue(el.styles?.marginRight ?? el.styles?.margin, 0),
      b: parseBoxValue(el.styles?.marginBottom ?? el.styles?.margin, 0),
      l: parseBoxValue(el.styles?.marginLeft ?? el.styles?.margin, 0),
    };
    const p = {
      t: parseBoxValue(el.styles?.paddingTop ?? el.styles?.padding, 0),
      r: parseBoxValue(el.styles?.paddingRight ?? el.styles?.padding, 0),
      b: parseBoxValue(el.styles?.paddingBottom ?? el.styles?.padding, 0),
      l: parseBoxValue(el.styles?.paddingLeft ?? el.styles?.padding, 0),
    };
    overlay.style.top = top + 'px';
    overlay.style.left = left + 'px';
    overlay.style.width = rect.width + 'px';
    overlay.style.height = rect.height + 'px';
    overlay.innerHTML = `
      <div class="studio-box-margin" data-box="margin">
        <span class="studio-box-label studio-box-mt" data-side="marginTop">${m.t}</span>
        <span class="studio-box-label studio-box-mr" data-side="marginRight">${m.r}</span>
        <span class="studio-box-label studio-box-mb" data-side="marginBottom">${m.b}</span>
        <span class="studio-box-label studio-box-ml" data-side="marginLeft">${m.l}</span>
        <div class="studio-box-padding" data-box="padding">
          <span class="studio-box-label studio-box-pt" data-side="paddingTop">${p.t}</span>
          <span class="studio-box-label studio-box-pr" data-side="paddingRight">${p.r}</span>
          <span class="studio-box-label studio-box-pb" data-side="paddingBottom">${p.b}</span>
          <span class="studio-box-label studio-box-pl" data-side="paddingLeft">${p.l}</span>
          <div class="studio-box-content"></div>
        </div>
      </div>
      <span class="studio-box-handle studio-box-handle-mt" data-drag="marginTop" title="Margin top"></span>
      <span class="studio-box-handle studio-box-handle-mr" data-drag="marginRight" title="Margin right"></span>
      <span class="studio-box-handle studio-box-handle-mb" data-drag="marginBottom" title="Margin bottom"></span>
      <span class="studio-box-handle studio-box-handle-ml" data-drag="marginLeft" title="Margin left"></span>
      <span class="studio-box-handle studio-box-handle-pt" data-drag="paddingTop" title="Padding top"></span>
      <span class="studio-box-handle studio-box-handle-pr" data-drag="paddingRight" title="Padding right"></span>
      <span class="studio-box-handle studio-box-handle-pb" data-drag="paddingBottom" title="Padding bottom"></span>
      <span class="studio-box-handle studio-box-handle-pl" data-drag="paddingLeft" title="Padding left"></span>`;
    bindBoxHandles(el, overlay);
  }

  function bindBoxHandles(el, overlay) {
    overlay.querySelectorAll('.studio-box-handle').forEach((handle) => {
      handle.addEventListener('mousedown', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const ide = IDE();
        const side = handle.dataset.drag;
        const startY = e.clientY;
        const startX = e.clientX;
        const startVal = parseBoxValue(el.styles?.[side], 0);
        prod.boxDrag = { el, side, startY, startX, startVal };
      });
    });
  }

  function onBoxDragMove(e) {
    if (!prod.boxDrag) return;
    const ide = IDE();
    const { el, side, startY, startX, startVal } = prod.boxDrag;
    const isVert = side.includes('Top') || side.includes('Bottom');
    const delta = isVert ? e.clientY - startY : e.clientX - startX;
    const sign = side.includes('Bottom') || side.includes('Right') ? 1 : -1;
    const next = Math.max(0, Math.round(startVal + delta * sign));
    el.styles = el.styles || {};
    el.styles[side] = next + 'px';
    ide.markDirty(ide.state.activeTab);
    updateBoxOverlay();
    ide.renderCanvas();
  }

  function onBoxDragEnd() {
    if (!prod.boxDrag) return;
    prod.boxDrag = null;
    const ide = IDE();
    ide.pushUndo();
    ide.saveActiveFile().catch(ide.alertErr);
  }

  // ——— Column resize ———
  function attachColumnResizeHandles() {
    const ide = IDE();
    const wrap = $('#studio-canvas-wrap');
    if (!wrap || !ide?.state?.canvas) return;
    wrap.querySelectorAll('.studio-col-preview').forEach((colEl) => {
      if (colEl.querySelector('.studio-col-resize')) return;
      const handle = document.createElement('span');
      handle.className = 'studio-col-resize';
      handle.title = 'Drag to resize column';
      colEl.appendChild(handle);
      handle.addEventListener('mousedown', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const si = parseInt(colEl.closest('.studio-section-block')?.dataset.si, 10);
        const row = colEl.parentElement;
        const cols = [...row.querySelectorAll('.studio-col-preview')];
        const ci = cols.indexOf(colEl);
        if (isNaN(si) || ci < 0) return;
        const section = ide.state.canvas.sections[si];
        const col = section?.rows?.[0]?.columns?.[ci];
        if (!col) return;
        prod.columnDrag = { si, ci, col, colEl, row, startX: e.clientX, startWidth: colEl.offsetWidth, rowWidth: row.offsetWidth };
      });
    });
  }

  function onColumnDragMove(e) {
    if (!prod.columnDrag) return;
    const { col, colEl, row, startX, startWidth, rowWidth } = prod.columnDrag;
    const delta = e.clientX - startX;
    const newWidth = Math.max(40, startWidth + delta);
    const pct = Math.min(95, Math.max(5, Math.round((newWidth / rowWidth) * 100)));
    col.width = pct + '%';
    colEl.style.flex = '0 0 ' + pct + '%';
    colEl.style.maxWidth = pct + '%';
  }

  function onColumnDragEnd() {
    if (!prod.columnDrag) return;
    prod.columnDrag = null;
    const ide = IDE();
    ide.pushUndo();
    ide.markDirty(ide.state.activeTab);
    ide.saveActiveFile().catch(ide.alertErr);
    ide.renderInspector();
  }

  // ——— Align toolbar ———
  function updateAlignToolbar() {
    const ide = IDE();
    const group = $('#studio-align-group');
    if (!group || !ide) return;
    const el = ide.getSelectedElement();
    const show = el && (el.type === 'text' || el.type === 'heading' || el.type === 'button');
    group.hidden = !show;
    if (show) {
      const align = el.styles?.textAlign || 'left';
      group.querySelectorAll('.studio-align-btn').forEach((btn) => {
        btn.classList.toggle('active', btn.dataset.align === align);
      });
    }
  }

  // ——— Rich text toolbar ———
  function updateRichToolbar() {
    const ide = IDE();
    const bar = $('#studio-rich-toolbar');
    const wrap = $('#studio-canvas-wrap');
    if (!bar || !wrap || !ide) return;
    const el = ide.getSelectedElement();
    const selectedDom = wrap.querySelector('.studio-el.selected');
    if (!el || !selectedDom || !(el.type === 'text' || el.type === 'heading' || el.type === 'button')) {
      bar.hidden = true;
      return;
    }
    bar.hidden = false;
    const rect = selectedDom.getBoundingClientRect();
    bar.style.top = Math.max(8, rect.top - 40) + 'px';
    bar.style.left = Math.max(8, rect.left) + 'px';
  }

  function bindRichToolbar() {
    const bar = $('#studio-rich-toolbar');
    if (!bar || bar.dataset.bound) return;
    bar.dataset.bound = '1';
    bar.addEventListener('mousedown', (e) => e.preventDefault());
    bar.querySelectorAll('[data-rich]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const ide = IDE();
        const wrap = $('#studio-canvas-wrap');
        const selectedDom = wrap?.querySelector('.studio-el.selected');
        const el = ide?.getSelectedElement();
        if (!selectedDom || !el) return;
        selectedDom.focus();
        const cmd = btn.dataset.rich;
        if (cmd === 'link') {
          const url = prompt('Link URL', 'https://');
          if (url) document.execCommand('createLink', false, url);
        } else {
          document.execCommand(cmd, false, null);
        }
        ide.pushUndo();
        el.content = selectedDom.innerHTML;
        ide.markDirty(ide.state.activeTab);
        ide.saveActiveFile().catch(ide.alertErr);
      });
    });
  }

  // ——— Inspector extensions ———
  async function loadStylesheetCache() {
    const ide = IDE();
    if (!ide?.state?.project) return;
    try {
      const [styles, custom] = await Promise.all([
        ide.api('get_canvas_styles', { project_id: ide.state.project.id }),
        ide.api('get_custom_css', { project_id: ide.state.project.id }),
      ]);
      prod.stylesCache.kit = styles.kit || '';
      prod.stylesCache.generated = styles.generated || '';
      prod.stylesCache.custom = custom.css || styles.custom || '';
    } catch (_) { /* ignore */ }
  }

  function patchInspector() {
    const ide = IDE();
    if (!ide) return;
    const orig = ide.renderInspector;
    ide.renderInspector = function renderInspectorProd() {
      const body = $('#studio-inspector-body');
      if (!body) return;
      if (ide.state.inspectorTab === 'stylesheets') {
        renderStylesheetsInspector(body);
        return;
      }
      if (ide.state.inspectorTab === 'page') {
        renderPageInspector(body);
        return;
      }
      orig.call(ide);
      if (ide.state.inspectorTab === 'props') {
        appendClassManager(body);
      }
    };
  }

  function renderStylesheetsInspector(body) {
    const ide = IDE();
    const tab = prod.stylesheetTab;
    loadStylesheetCache().then(() => {
      if (ide.state.inspectorTab !== 'stylesheets') return;
      renderStylesheetsBody(body, tab);
    });
    renderStylesheetsBody(body, tab);
  }

  function renderStylesheetsBody(body, tab) {
    const ide = IDE();
    let html = `<div class="studio-ss-tabs">
      <button type="button" class="studio-ss-tab${tab === 'custom' ? ' active' : ''}" data-ss-tab="custom">Custom</button>
      <button type="button" class="studio-ss-tab${tab === 'generated' ? ' active' : ''}" data-ss-tab="generated">Generated</button>
      <button type="button" class="studio-ss-tab${tab === 'kit' ? ' active' : ''}" data-ss-tab="kit">Kit</button>
    </div>`;
    if (tab === 'custom') {
      html += `<p class="form-hint">Edit <code>styles/custom.css</code> — saved to project.</p>
        <textarea id="studio-custom-css-editor" class="studio-ss-editor" spellcheck="false">${ide.esc(prod.stylesCache.custom)}</textarea>
        <button type="button" class="btn btn-secondary btn-sm" data-action="save-custom-css">Save custom.css</button>`;
    } else {
      const content = tab === 'generated' ? prod.stylesCache.generated : prod.stylesCache.kit;
      html += `<p class="form-hint">Read-only ${tab} stylesheet.</p>
        <textarea class="studio-ss-editor studio-ss-readonly" readonly spellcheck="false">${ide.esc(content)}</textarea>`;
    }
    body.innerHTML = html;
    const saveBtn = body.querySelector('[data-action="save-custom-css"]');
    if (saveBtn) {
      saveBtn.addEventListener('click', async () => {
        const css = $('#studio-custom-css-editor')?.value || '';
        await ide.api('save_custom_css', { project_id: ide.state.project.id, css });
        prod.stylesCache.custom = css;
        await loadCanvasStyles();
        ide.toast('custom.css saved', 'success');
      });
    }
    const ed = $('#studio-custom-css-editor');
    if (ed && !ed.dataset.bound) {
      ed.dataset.bound = '1';
      ed.addEventListener('input', () => { prod.stylesCache.custom = ed.value; });
    }
  }

  function renderPageInspector(body) {
    const ide = IDE();
    ide.ensureCanvasPage();
    const page = ide.state.canvas?.page || {};
    const meta = page.meta || {};
    const slug = page.slug || (ide.state.activeTab ? ide.pageSlugFromPath(ide.state.activeTab) : 'index');
    body.innerHTML = `
      <p class="form-hint">Page SEO and metadata (<code>canvas.page</code>).</p>
      <label>Meta title<input class="input studio-page-field" data-page-key="meta.title" value="${ide.esc(meta.title || page.title || '')}"></label>
      <label>Meta description<textarea class="input studio-page-field" data-page-key="meta.description" rows="3">${ide.esc(meta.description || '')}</textarea></label>
      <label>Slug <span class="form-hint">(display)</span><input class="input" value="${ide.esc(slug)}" readonly></label>`;
    body.querySelectorAll('.studio-page-field').forEach((input) => {
      const handler = () => {
        ide.pushUndo();
        ide.ensureCanvasPage();
        const key = input.dataset.pageKey;
        const val = input.value;
        if (key === 'meta.title') {
          ide.state.canvas.page.meta.title = val;
          ide.state.canvas.page.title = val;
        } else if (key === 'meta.description') {
          ide.state.canvas.page.meta.description = val;
        }
        ide.markDirty(ide.state.activeTab);
        ide.saveActiveFile().catch(ide.alertErr);
      };
      input.addEventListener('change', handler);
      if (input.tagName === 'TEXTAREA' || input.type === 'text') input.addEventListener('input', handler);
    });
  }

  function appendClassManager(body) {
    const ide = IDE();
    const el = ide.getSelectedElement();
    if (!el || body.querySelector('.studio-class-manager')) return;
    const chips = (el.classes || []).map((c) =>
      `<span class="studio-class-chip">${ide.esc(c)}<button type="button" data-class-remove="${ide.esc(c)}">×</button></span>`
    ).join('');
    const block = document.createElement('div');
    block.className = 'studio-class-manager';
    block.innerHTML = `<label>Classes</label>
      <div class="studio-class-chips">${chips || '<span class="form-hint">No classes</span>'}</div>
      <div class="studio-class-add">
        <input type="text" class="input studio-class-input" placeholder="Add class…">
        <button type="button" class="btn btn-secondary btn-sm studio-class-add-btn">Add</button>
      </div>`;
    body.appendChild(block);
    const input = block.querySelector('.studio-class-input');
    const add = () => {
      const cls = input.value.trim();
      if (!cls) return;
      ide.pushUndo();
      el.classes = el.classes || [];
      if (!el.classes.includes(cls)) el.classes.push(cls);
      input.value = '';
      ide.markDirty(ide.state.activeTab);
      ide.saveActiveFile().catch(ide.alertErr);
      ide.renderCanvas();
      ide.renderInspector();
    };
    block.querySelector('.studio-class-add-btn')?.addEventListener('click', add);
    input?.addEventListener('keydown', (e) => { if (e.key === 'Enter') add(); });
  }

  // ——— Content panel ———
  async function renderContentPanel() {
    const ide = IDE();
    const panel = $('#studio-content-panel');
    if (!panel || !ide?.state?.collections?.length) {
      if (panel) panel.innerHTML = '<p class="form-hint">No collections</p>';
      return;
    }
    let html = '';
    for (const coll of ide.state.collections) {
      html += `<div class="studio-content-coll">
        <button type="button" class="studio-content-coll-btn" data-coll-toggle="${ide.esc(coll)}">
          <span class="studio-content-coll-name">${ide.esc(coll)}</span>
          <span class="studio-content-coll-arrow">▸</span>
        </button>
        <div class="studio-content-fields" data-coll-fields="${ide.esc(coll)}"></div>
      </div>`;
    }
    panel.innerHTML = html;
  }

  async function loadCollectionFields(collection) {
    const ide = IDE();
    const container = document.querySelector(`[data-coll-fields="${collection}"]`);
    if (!container || prod.collectionSchemas[collection]) {
      if (container && prod.collectionSchemas[collection]) {
        renderCollectionFields(container, collection, prod.collectionSchemas[collection]);
      }
      return;
    }
    container.innerHTML = '<p class="form-hint">Loading…</p>';
    try {
      const url = (typeof Birkly !== 'undefined' && Birkly.url)
        ? Birkly.url('/api/content.php')
        : '/api/content.php';
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ action: 'get_schema', collection }),
      });
      const json = await res.json();
      const fields = json.data?.schema?.fields || [];
      prod.collectionSchemas[collection] = fields;
      renderCollectionFields(container, collection, fields);
    } catch (_) {
      container.innerHTML = '<p class="form-hint">Could not load fields</p>';
    }
  }

  function renderCollectionFields(container, collection, fields) {
    const ide = IDE();
    if (!fields.length) {
      container.innerHTML = '<p class="form-hint">No fields defined</p>';
      return;
    }
    container.innerHTML = fields.map((f) => {
      const name = f.name || f.slug || f.id || 'field';
      return `<button type="button" class="studio-field-btn" data-insert-field="${ide.esc(name)}">{ ${ide.esc(name)} }</button>`;
    }).join('');
  }

  function insertField(fieldName) {
    const ide = IDE();
    const token = '{ ' + fieldName + ' }';
    const el = ide.getSelectedElement();
    if (el) {
      ide.pushUndo();
      if (el.type === 'binding') {
        el.itemTemplate = (el.itemTemplate || '') + token;
      } else if (el.type === 'text' || el.type === 'heading' || el.type === 'button') {
        el.content = (el.content || '') + token;
      } else if (el.type === 'html') {
        el.content = (el.content || '') + token;
      } else {
        ide.toast('Select a text or binding element', 'info');
        return;
      }
      ide.markDirty(ide.state.activeTab);
      ide.saveActiveFile().catch(ide.alertErr);
      ide.renderCanvas();
      ide.renderInspector();
      ide.toast('Field inserted', 'success');
      return;
    }
    ide.toast('Select an element to insert field', 'info');
  }

  // ——— Binding wizard extensions ———
  function patchBindingWizard() {
    const ide = IDE();
    if (!ide) return;
    const origPreview = ide.bindingCanonicalPreview;
    ide.bindingCanonicalPreview = function bindingPreviewProd(wiz) {
      if (wiz.type === 'conditional') {
        const field = wiz.conditionField || 'status';
        const value = wiz.conditionValue || 'published';
        const template = wiz.itemTemplate || '<div>{ title }</div>';
        return "{if " + field + ' = ' + value + "}\n" + template + "\n{endif}";
      }
      if (wiz.type === 'sub_entry') {
        const parent = wiz.parentCollection || wiz.collection || 'blog';
        const sub = wiz.subCollection || 'comments';
        const template = wiz.itemTemplate || '<div>{ content }</div>';
        return "{for each entry in '" + parent + "'}\n{for each " + sub + " in entry}\n" + template + "\n{endfor}\n{endfor}";
      }
      if (wiz.type === 'template_function') {
        const fn = wiz.templateFunction || 'upcoming_events';
        const template = wiz.itemTemplate || '<div>{ title }</div>';
        return '{' + fn + '}' + "\n" + template + "\n{end" + fn + '}';
      }
      return origPreview.call(ide, wiz);
    };

    const origRender = ide.renderBindingWizard;
    ide.renderBindingWizard = function renderWizardProd() {
      const wiz = ide.state.bindingWizard;
      if (!wiz) return;
      const extraTypes = ['conditional', 'sub_entry', 'template_function'];
      if (extraTypes.includes(wiz.type)) {
        renderExtendedWizard(wiz);
        return;
      }
      origRender.call(ide);
    };

    const origApply = ide.applyBindingWizard;
    ide.applyBindingWizard = function applyWizardProd() {
      const wiz = ide.state.bindingWizard;
      if (!wiz) return;
      const extraTypes = ['conditional', 'sub_entry', 'template_function'];
      if (!extraTypes.includes(wiz.type)) {
        origApply.call(ide);
        return;
      }
      ide.pushUndo();
      let section = ide.state.canvas.sections[ide.state.selected?.si ?? 0];
      if (!section) {
        ide.state.canvas.sections.push({
          id: ide.uid('section'),
          classes: ['studio-section'],
          layout: { type: 'grid', columns: 1, gap: '1rem' },
          responsive: { default: { columns: 1 }, tablet: { columns: 2 }, desktop: { columns: 3 } },
          rows: [{ id: ide.uid('row'), columns: [{ id: ide.uid('col'), width: '100%', elements: [] }] }],
        });
        section = ide.state.canvas.sections[0];
      }
      const ci = ide.state.selected?.ci ?? 0;
      const col = section.rows[0].columns[ci] || section.rows[0].columns[0];
      const el = {
        id: ide.uid('el'),
        type: 'binding',
        bindingType: wiz.type,
        collection: wiz.collection || ide.state.collections[0] || 'blog',
        itemTemplate: wiz.itemTemplate,
        classes: [],
        styles: {},
      };
      if (wiz.type === 'conditional') {
        el.conditionField = wiz.conditionField;
        el.conditionValue = wiz.conditionValue;
      }
      if (wiz.type === 'sub_entry') {
        el.parentCollection = wiz.parentCollection;
        el.subCollection = wiz.subCollection;
      }
      if (wiz.type === 'template_function') {
        el.templateFunction = wiz.templateFunction;
      }
      col.elements.push(el);
      $('#studio-binding-modal').hidden = true;
      ide.state.bindingWizard = null;
      ide.select({ si: ide.state.selected?.si ?? 0, ci, ei: col.elements.length - 1 });
      ide.saveActiveFile().catch(ide.alertErr);
      ide.renderCanvas();
      ide.renderLayers();
      ide.toast('Binding added', 'success');
    };
  }

  function renderExtendedWizard(wiz) {
    const ide = IDE();
    const stepsEl = $('#studio-wizard-steps');
    const bodyEl = $('#studio-wizard-body');
    const prevBtn = $('#studio-wizard-prev');
    const nextBtn = $('#studio-wizard-next');
    const maxStep = wiz.type === 'conditional' ? 3 : wiz.type === 'sub_entry' ? 4 : 3;
    const labels = wiz.type === 'conditional'
      ? ['Type', 'Condition', 'Template']
      : wiz.type === 'sub_entry'
        ? ['Type', 'Collections', 'Sub-entry', 'Template']
        : ['Type', 'Function', 'Template'];
    if (stepsEl) {
      stepsEl.innerHTML = Array.from({ length: maxStep }, (_, i) => {
        const n = i + 1;
        const cls = n === wiz.step ? 'active' : (n < wiz.step ? 'done' : '');
        return `<span class="studio-wizard-step ${cls}">${n}. ${labels[i]}</span>`;
      }).join('');
    }
    if (prevBtn) prevBtn.hidden = wiz.step <= 1;
    if (nextBtn) nextBtn.textContent = wiz.step >= maxStep ? 'Add binding' : 'Next';

    let html = '';
    if (wiz.step === 1) {
      html = '<p class="form-hint">Choose binding type:</p><div class="studio-wizard-types">';
      ide.BINDING_TYPES.forEach((bt) => {
        html += `<button type="button" class="studio-wizard-type-btn${wiz.type === bt.id ? ' active' : ''}" data-action="wizard-set-type" data-type="${bt.id}">
          <strong>${ide.esc(bt.label)}</strong><small>${ide.esc(bt.desc)}</small></button>`;
      });
      html += '</div>';
    } else if (wiz.step === 2 && wiz.type === 'conditional') {
      html = `<label>Field<input class="input studio-wiz-field" data-wiz="conditionField" value="${ide.esc(wiz.conditionField || 'status')}"></label>
        <label>Value<input class="input studio-wiz-field" data-wiz="conditionValue" value="${ide.esc(wiz.conditionValue || 'published')}"></label>`;
    } else if (wiz.step === 2 && wiz.type === 'sub_entry') {
      const cols = ide.state.collections.length ? ide.state.collections : ['blog'];
      html = `<label>Parent collection<select class="input studio-wiz-field" data-wiz="parentCollection">${cols.map((c) => `<option${c === (wiz.parentCollection || wiz.collection) ? ' selected' : ''}>${ide.esc(c)}</option>`).join('')}</select></label>`;
    } else if (wiz.step === 2 && wiz.type === 'template_function') {
      html = `<label>Template function<input class="input studio-wiz-field" data-wiz="templateFunction" value="${ide.esc(wiz.templateFunction || 'upcoming_events')}" placeholder="upcoming_events"></label>
        <p class="form-hint">e.g. <code>upcoming_events</code>, <code>featured_products</code></p>`;
    } else if (wiz.step === 3 && wiz.type === 'sub_entry') {
      html = `<label>Sub-entry collection<input class="input studio-wiz-field" data-wiz="subCollection" value="${ide.esc(wiz.subCollection || 'comments')}"></label>`;
    } else if ((wiz.step === 3 && wiz.type === 'conditional') || (wiz.step === 4 && wiz.type === 'sub_entry') || (wiz.step === 3 && wiz.type === 'template_function')) {
      html = `<label>Item template<textarea class="input studio-wiz-field" data-wiz="itemTemplate" rows="4">${ide.esc(wiz.itemTemplate || '<div>{ title }</div>')}</textarea></label>
        <div class="studio-canonical-preview"><strong>Preview</strong><pre>${ide.esc(ide.bindingCanonicalPreview(wiz))}</pre></div>`;
    }
    if (bodyEl) {
      bodyEl.innerHTML = html;
      bodyEl.querySelectorAll('.studio-wiz-field').forEach((input) => {
        const sync = () => {
          const key = input.dataset.wiz;
          if (key === 'itemTemplate') wiz.itemTemplate = input.value;
          else if (key === 'conditionField') wiz.conditionField = input.value;
          else if (key === 'conditionValue') wiz.conditionValue = input.value;
          else if (key === 'parentCollection') wiz.parentCollection = input.value;
          else if (key === 'subCollection') wiz.subCollection = input.value;
          else if (key === 'templateFunction') wiz.templateFunction = input.value;
          if (wiz.step >= maxStep - 1) ide.renderBindingWizard();
        };
        input.addEventListener('change', sync);
        if (input.tagName === 'TEXTAREA' || input.type === 'text') input.addEventListener('input', sync);
      });
    }
  }

  function patchOpenBindingWizard() {
    const ide = IDE();
    if (!ide) return;
    const orig = ide.openBindingWizard;
    ide.openBindingWizard = function openWizardProd(type) {
      orig.call(ide, type);
      const wiz = ide.state.bindingWizard;
      if (!wiz) return;
      if (type === 'conditional') {
        wiz.conditionField = 'status';
        wiz.conditionValue = 'published';
      }
      if (type === 'sub_entry') {
        wiz.parentCollection = ide.state.collections[0] || 'blog';
        wiz.subCollection = 'comments';
      }
      if (type === 'template_function') {
        wiz.templateFunction = 'upcoming_events';
      }
      if (['conditional', 'sub_entry', 'template_function'].includes(wiz.type)) {
        ide.renderBindingWizard();
      }
    };
  }

  function patchWizardNext() {
    const app = $('#studio-app');
    if (!app || app.dataset.wizardPatched) return;
    app.dataset.wizardPatched = '1';
    app.addEventListener('click', (e) => {
      const ide = IDE();
      if (!ide) return;
      const wiz = ide.state.bindingWizard;
      if (!wiz) return;
      if (e.target.closest('[data-action="wizard-next"]')) {
        const extra = ['conditional', 'sub_entry', 'template_function'];
        if (!extra.includes(wiz.type)) return;
        e.stopImmediatePropagation();
        const maxStep = wiz.type === 'sub_entry' ? 4 : 3;
        if (wiz.step < maxStep) {
          wiz.step += 1;
          ide.renderBindingWizard();
        } else {
          ide.applyBindingWizard();
        }
      }
    }, true);
  }

  // ——— Canvas hooks ———
  function patchRenderCanvas() {
    const ide = IDE();
    if (!ide) return;
    const orig = ide.renderCanvas;
    ide.renderCanvas = function renderCanvasProd() {
      orig.call(ide);
      attachColumnResizeHandles();
      updateBoxOverlay();
      updateAlignToolbar();
      updateRichToolbar();
    };
    ide.afterRenderEl = function afterRenderEl(div, el) {
      if (el.type === 'text' || el.type === 'heading' || el.type === 'button') {
        div.dataset.rich = '1';
      }
    };
  }

  function patchSelect() {
    const ide = IDE();
    if (!ide) return;
    const orig = ide.select;
    ide.select = function selectProd(sel) {
      orig.call(ide, sel);
      updateBoxOverlay();
      updateAlignToolbar();
      updateRichToolbar();
    };
  }

  function patchProjectOpen() {
    const ide = IDE();
    if (!ide) return;
    const origOpen = ide.state;
    const watchProject = () => {
      if (ide.state.project && !prod._stylesLoadedFor) {
        prod._stylesLoadedFor = ide.state.project.id;
        loadCanvasStyles();
        renderContentPanel();
      }
      if (!ide.state.project) prod._stylesLoadedFor = null;
    };
    setInterval(watchProject, 500);
  }

  function init() {
    const ide = IDE();
    if (!ide) {
      setTimeout(init, 50);
      return;
    }
    setOutlines(true);
    patchViewMode();
    patchSave();
    patchInspector();
    patchBindingWizard();
    patchOpenBindingWizard();
    patchWizardNext();
    patchRenderCanvas();
    patchSelect();
    patchOpenProject();
    patchProjectOpen();
    bindRichToolbar();
    document.addEventListener('mousemove', (e) => {
      onBoxDragMove(e);
      onColumnDragMove(e);
    });
    document.addEventListener('mouseup', () => {
      onBoxDragEnd();
      onColumnDragEnd();
    });
    window.addEventListener('resize', () => {
      updateBoxOverlay();
      updateRichToolbar();
    });
    const wrap = $('#studio-canvas-wrap');
    wrap?.addEventListener('scroll', () => {
      updateBoxOverlay();
      updateRichToolbar();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
