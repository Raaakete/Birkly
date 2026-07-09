/**
 * Studio IDE (P23) — filesystem, tabs, visual/code/split, canvas v2.
 */
(function () {
  'use strict';

  const SESSION_KEY = 'studio-ide-tabs';

  const CSS_STYLE_KEYS = ['textAlign', 'fontSize', 'color', 'padding', 'margin', 'backgroundColor'];

  const BINDING_TYPES = [
    { id: 'loop', label: 'Collection loop', desc: 'Iterate all entries in a collection' },
    { id: 'filtered', label: 'Filtered list', desc: 'Filter, order, and limit entries' },
    { id: 'single', label: 'Single entry', desc: 'One entry by slug' },
    { id: 'site_element', label: 'Site element', desc: 'Reusable site_elements entry' },
    { id: 'conditional', label: 'Conditional', desc: 'Show block when field matches' },
    { id: 'sub_entry', label: 'Sub-entry loop', desc: 'Iterate child entries' },
    { id: 'template_function', label: 'Template function', desc: 'Call a template function block' },
  ];

  const state = {
    projects: [],
    project: null,
    fileTree: [],
    tabs: [],
    activeTab: null,
    viewMode: 'visual',
    breakpoint: 'default',
    canvas: null,
    selected: null,
    inspectorTab: 'props',
    collections: [],
    blocks: [],
    undoStack: [],
    redoStack: [],
    dirtyPaths: new Set(),
    busy: false,
    ssrHealth: null,
    bindingWizard: null,
  };

  const $ = (s, root) => (root || document).querySelector(s);

  function apiUrl() {
    return (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function')
      ? Birkly.url('/api/index.php?route=studio')
      : '/api/index.php?route=studio';
  }

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function toast(message, type) {
    setStatus(message, type === 'error' ? 'error' : 'success');
    const el = document.createElement('div');
    el.className = 'studio-toast studio-toast-' + (type || 'info');
    el.textContent = message;
    document.body.appendChild(el);
    requestAnimationFrame(() => el.classList.add('studio-toast-visible'));
    setTimeout(() => {
      el.classList.remove('studio-toast-visible');
      setTimeout(() => el.remove(), 300);
    }, 4500);
  }

  function absUrl(path) {
    if (!path) return window.location.origin;
    if (/^https?:\/\//i.test(path)) return path;
    return window.location.origin + (path.startsWith('/') ? path : '/' + path);
  }

  function setStatus(message, type) {
    const el = $('#studio-status');
    if (!el) return;
    if (!message) {
      el.hidden = true;
      el.textContent = '';
      return;
    }
    el.hidden = false;
    el.className = 'studio-status studio-status-' + (type || 'info');
    el.textContent = message;
  }

  function alertErr(e) {
    const msg = e?.message || String(e);
    console.error('[Studio]', e);
    setStatus(msg, 'error');
    toast(msg, 'error');
  }

  async function api(action, body = {}) {
    const payload = { action, ...body };
    const token = csrfToken();
    if (token) payload._csrf = token;

    const res = await fetch(apiUrl(), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });

    if (action === 'export_project') {
      if (!res.ok) throw new Error('Export failed (' + res.status + ')');
      const blob = await res.blob();
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = (state.project?.slug || 'studio') + '-export.zip';
      a.click();
      URL.revokeObjectURL(a.href);
      return {};
    }

    const text = await res.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch {
      throw new Error('Server error (' + res.status + '): ' + text.slice(0, 120));
    }
    if (json.status !== 'ok') {
      throw new Error(json.message || 'API error (' + res.status + ')');
    }
    return json;
  }

  function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function uid(p) {
    return p + '-' + Math.random().toString(36).slice(2, 8);
  }

  function isStudioPage(path) {
    return path && path.endsWith('.studio.json');
  }

  function pageSlugFromPath(path) {
    return (path.split('/').pop() || '').replace(/\.studio\.json$/, '');
  }

  function pushUndo() {
    if (!state.canvas) return;
    state.undoStack.push(JSON.stringify(state.canvas));
    if (state.undoStack.length > 30) state.undoStack.shift();
    state.redoStack = [];
  }

  function ensureCanvasPage() {
    if (!state.canvas) return;
    state.canvas.page = state.canvas.page || {};
    state.canvas.page.meta = state.canvas.page.meta || {};
  }

  async function withBusy(fn) {
    if (state.busy) {
      toast('Please wait…', 'info');
      return;
    }
    state.busy = true;
    setStatus('Working…', 'info');
    try {
      await fn();
      setStatus('');
    } catch (e) {
      alertErr(e);
    } finally {
      state.busy = false;
    }
  }

  // ——— Dashboard ———
  async function loadProjects() {
    const data = await api('list_projects');
    state.projects = data.projects || [];
    renderProjects();
  }

  function renderProjects() {
    const el = $('#studio-project-list');
    if (!el) return;
    if (!state.projects.length) {
      el.innerHTML = '<p class="form-hint">No projects yet. Click <strong>New project</strong> to start.</p>';
      return;
    }
    el.innerHTML = state.projects.map((p) => `
      <div class="studio-project-card">
        <div><h4>${esc(p.name)}</h4><div class="meta">/${esc(p.slug)} · ${p.published ? 'Published' : 'Draft'}</div></div>
        <button type="button" class="btn btn-primary" data-action="open-project" data-id="${esc(p.id)}">Open IDE</button>
        <button type="button" class="btn btn-secondary btn-sm" data-action="dup-project" data-id="${esc(p.id)}">Duplicate</button>
        <button type="button" class="btn btn-secondary btn-sm" data-action="del-project" data-id="${esc(p.id)}">Delete</button>
      </div>`).join('');
  }

  async function openProject(id) {
    const data = await api('get_project', { id });
    state.project = data.project;
    state.undoStack = [];
    state.redoStack = [];
    restoreTabs(id);
    $('#studio-dashboard').hidden = true;
    $('#studio-workspace').hidden = false;
    $('#studio-project-name').textContent = state.project.name;
    await Promise.all([loadFileTree(), loadBlocks(), loadSections(), loadTokens(), loadCollections(), loadSsrHealth()]);
    if (!state.tabs.length) {
      openTab('pages/index.studio.json');
    } else {
      await activateTab(state.activeTab || state.tabs[0].path);
    }
    persistTabs();
    toast('Project opened', 'success');
  }

  // ——— File tree ———
  async function loadFileTree() {
    const data = await api('list_files', { project_id: state.project.id });
    state.fileTree = data.tree || [];
    renderFileTree();
  }

  function renderFileTree(nodes, container) {
    const root = container || $('#studio-file-tree');
    if (!root) return;
    if (!container) root.innerHTML = '';
    (nodes || state.fileTree).forEach((node) => {
      const row = document.createElement('div');
      row.className = 'studio-tree-node studio-tree-' + node.type;
      row.style.paddingLeft = ((node.depth || 0) * 12 + 4) + 'px';
      if (node.type === 'dir') {
        row.innerHTML = `<span class="studio-tree-icon">📁</span> ${esc(node.name)}`;
        root.appendChild(row);
        if (node.children) {
          node.children.forEach((c) => { c.depth = (node.depth || 0) + 1; });
          renderFileTree(node.children, root);
        }
      } else {
        const active = node.path === state.activeTab ? ' active' : '';
        row.innerHTML = `<button type="button" class="studio-tree-file${active}" data-action="open-tab" data-path="${esc(node.path)}">${esc(node.name)}</button>`;
        root.appendChild(row);
      }
    });
  }

  // ——— Tabs ———
  function openTab(path) {
    if (!state.tabs.find((t) => t.path === path)) {
      state.tabs.push({ path, dirty: false });
    }
    activateTab(path).catch(alertErr);
    persistTabs();
  }

  async function activateTab(path) {
    state.activeTab = path;
    renderTabBar();
    renderFileTree();
    if (isStudioPage(path)) {
      const slug = pageSlugFromPath(path);
      const data = await api('get_page', { project_id: state.project.id, page_slug: slug });
      state.canvas = data.canvas;
      ensureCanvasNav();
      ensureCanvasPage();
      $('#studio-code-editor').value = data.html || '';
      renderCanvas();
      renderLayers();
      renderInspector();
      updateInspectorTabs();
    } else {
      state.canvas = null;
      state.selected = null;
      const file = await api('read_file', { project_id: state.project.id, path });
      $('#studio-code-editor').value = file.file.content;
      $('#studio-canvas').innerHTML = '<p class="form-hint">Code file — use Code or Split view.</p>';
      renderLayers();
      renderInspector();
    }
    applyViewMode();
  }

  function renderTabBar() {
    const bar = $('#studio-tabbar');
    if (!bar) return;
    bar.innerHTML = state.tabs.map((t) => `
      <button type="button" class="studio-tab${t.path === state.activeTab ? ' active' : ''}${t.dirty ? ' dirty' : ''}" data-action="activate-tab" data-path="${esc(t.path)}">
        ${esc(t.path.split('/').pop())}${t.dirty ? ' •' : ''}
        <span class="studio-tab-close" data-action="close-tab" data-path="${esc(t.path)}">×</span>
      </button>`).join('');
  }

  function closeTab(path) {
    state.tabs = state.tabs.filter((t) => t.path !== path);
    if (state.activeTab === path) {
      state.activeTab = state.tabs[0]?.path || null;
      if (state.activeTab) activateTab(state.activeTab).catch(alertErr);
      else {
        renderTabBar();
        $('#studio-canvas').innerHTML = '';
      }
    } else {
      renderTabBar();
    }
    persistTabs();
  }

  function markDirty(path) {
    if (!path) return;
    state.dirtyPaths.add(path);
    const t = state.tabs.find((x) => x.path === path);
    if (t) t.dirty = true;
    renderTabBar();
  }

  function persistTabs() {
    if (!state.project) return;
    sessionStorage.setItem(SESSION_KEY, JSON.stringify({
      projectId: state.project.id,
      tabs: state.tabs.map((t) => t.path),
      activeTab: state.activeTab,
    }));
  }

  function restoreTabs(projectId) {
    try {
      const saved = JSON.parse(sessionStorage.getItem(SESSION_KEY) || 'null');
      if (saved && saved.projectId === projectId) {
        state.tabs = (saved.tabs || []).map((p) => ({ path: p, dirty: false }));
        state.activeTab = saved.activeTab;
        return;
      }
    } catch (_) { /* ignore */ }
    state.tabs = [];
    state.activeTab = null;
  }

  // ——— Save ———
  async function saveActiveFile() {
    const path = state.activeTab;
    if (!path || !state.project) return;
    if (isStudioPage(path)) {
      if (!state.canvas) return;
      const slug = pageSlugFromPath(path);
      await api('save_page', { project_id: state.project.id, page_slug: slug, canvas: state.canvas });
      const compiled = await api('compile_page', { project_id: state.project.id, page_slug: slug });
      $('#studio-code-editor').value = compiled.html || '';
      const htmlPath = 'pages/' + slug + '.html';
      if (state.tabs.find((t) => t.path === htmlPath)) {
        await api('write_file', { project_id: state.project.id, path: htmlPath, content: compiled.html });
      }
    } else {
      await api('write_file', {
        project_id: state.project.id,
        path,
        content: $('#studio-code-editor').value,
      });
    }
    const t = state.tabs.find((x) => x.path === path);
    if (t) t.dirty = false;
    state.dirtyPaths.delete(path);
    renderTabBar();
    toast('Saved', 'success');
  }

  // ——— Canvas helpers ———
  function ensureCanvasNav() {
    if (!state.canvas) return;
    if (!Array.isArray(state.canvas.nav)) {
      state.canvas.nav = [];
    }
  }

  function getSelectedElement() {
    if (!state.selected || state.selected.ei === undefined || !state.canvas) return null;
    const sec = state.canvas.sections[state.selected.si];
    return sec?.rows?.[0]?.columns?.[state.selected.ci ?? 0]?.elements?.[state.selected.ei] ?? null;
  }

  function getSelectedSection() {
    if (!state.selected || state.selected.si === undefined || !state.canvas) return null;
    return state.canvas.sections[state.selected.si] ?? null;
  }

  function getSelectedColumn() {
    if (!state.selected || state.selected.ci === undefined || !state.canvas) return null;
    const sec = state.canvas.sections[state.selected.si];
    return sec?.rows?.[0]?.columns?.[state.selected.ci] ?? null;
  }

  async function loadSsrHealth() {
    if (!state.project) return;
    try {
      const data = await api('diagnose_public', { project_id: state.project.id });
      state.ssrHealth = data.route || {};
    } catch (_) {
      state.ssrHealth = { route_ready: false, hints: ['Could not check SSR route'] };
    }
    renderStatusBar();
  }

  function renderStatusBar() {
    const hint = $('#studio-statusbar-hint');
    const meta = $('#studio-statusbar-meta');
    if (!hint) return;
    const h = state.ssrHealth;
    if (!h) {
      hint.textContent = 'SSR route: —';
      hint.className = 'studio-statusbar-hint';
      return;
    }
    const ready = h.route_ready;
    hint.className = 'studio-statusbar-hint' + (ready ? ' studio-statusbar-ok' : ' studio-statusbar-warn');
    const routeHint = ready
      ? '/site/' + (state.project?.slug || '…') + '/ ready'
      : (h.hints?.[0] || 'SSR route not configured');
    hint.textContent = 'SSR: ' + routeHint;
    if (meta && state.project) {
      meta.textContent = state.project.published ? 'Published' : 'Draft';
    }
  }

  function setInspectorTab(tab) {
    state.inspectorTab = tab;
    updateInspectorTabs();
    renderInspector();
  }

  function updateInspectorTabs() {
    const hasEl = !!getSelectedElement();
    $('#studio-inspector')?.querySelectorAll('.studio-insp-tab').forEach((btn) => {
      const t = btn.dataset.inspTab;
      btn.classList.toggle('active', t === state.inspectorTab);
      if (t === 'css') {
        btn.disabled = !hasEl;
        btn.title = hasEl ? '' : 'Select an element to edit CSS';
      }
    });
    if (state.inspectorTab === 'css' && !hasEl) {
      state.inspectorTab = 'props';
    }
  }

  function bindingCanonicalPreview(wiz) {
    const collection = wiz.collection || state.collections[0] || 'blog';
    const template = wiz.itemTemplate || '<div>{ title }</div>';
    const order = wiz.orderBy && wiz.orderBy !== 'newest' ? ' ordered by ' + wiz.orderBy : ' ordered by newest';
    const limit = wiz.limit > 0 ? ' limit ' + wiz.limit : '';
    if (wiz.type === 'single') {
      const slug = wiz.slug || 'example';
      return "{from '" + collection + "' get entry '" + slug + "'}\n" + template + "\n{endget}";
    }
    if (wiz.type === 'site_element') {
      const slug = wiz.slug || 'hero';
      return "{from 'site_elements' get entry '" + slug + "'}\n" + template + "\n{endget}";
    }
    if (wiz.type === 'filtered') {
      const field = wiz.filterField || 'status';
      const value = wiz.filterValue || 'published';
      return "{for each entry in '" + collection + "' with " + field + ' = ' + value + order + limit + "}\n" + template + "\n{endfor}";
    }
    return "{for each entry in '" + collection + "'" + order + limit + "}\n" + template + "\n{endfor}";
  }

  function syncSectionColumns(section, targetCols) {
    const cols = Math.max(1, Math.min(4, targetCols));
    section.layout = section.layout || { type: 'grid', columns: cols, gap: '1rem' };
    section.layout.columns = cols;
    const row = section.rows[0];
    if (!row) return;
    row.columns = row.columns || [];
    while (row.columns.length < cols) {
      row.columns.push({ id: uid('col'), width: Math.floor(100 / cols) + '%', elements: [] });
    }
    while (row.columns.length > cols) {
      const removed = row.columns.pop();
      if (removed?.elements?.length && row.columns.length) {
        row.columns[row.columns.length - 1].elements.push(...removed.elements);
      }
    }
    row.columns.forEach((c) => {
      if (!c.width || c.width === '100%') {
        c.width = Math.floor(100 / cols) + '%';
      }
    });
  }

  function select(sel) {
    state.selected = sel;
    updateInspectorTabs();
    renderCanvas();
    renderLayers();
    renderInspector();
  }

  // ——— Visual canvas ———
  function renderCanvas() {
    const root = $('#studio-canvas');
    if (!root) return;
    root.innerHTML = '';
    if (!state.canvas) {
      root.innerHTML = '<p class="form-hint">Open a <code>.studio.json</code> page to edit visually.</p>';
      return;
    }
    const bp = state.breakpoint;
    (state.canvas.sections || []).forEach((section, si) => {
      const sec = document.createElement('div');
      const selSec = state.selected?.si === si && state.selected.ei === undefined && state.selected.ci === undefined;
      sec.className = 'studio-section-block' + (selSec ? ' selected' : '');
      sec.draggable = true;
      sec.dataset.si = si;
      const layout = section.responsive?.[bp] || section.layout || {};
      const layoutType = layout.type || section.layout?.type || 'grid';
      const cols = layout.columns || section.layout?.columns || 1;
      const gap = layout.gap || section.layout?.gap || '1rem';
      sec.innerHTML = `<div class="studio-section-label">Section ${si + 1} · ${layoutType} · ${cols} col · ${bp}</div>`;
      const rowWrap = document.createElement('div');
      rowWrap.className = 'studio-row-preview';
      if (layoutType === 'flex') {
        rowWrap.style.display = 'flex';
        rowWrap.style.flexWrap = 'wrap';
        rowWrap.style.gap = gap;
      } else {
        rowWrap.style.display = 'grid';
        rowWrap.style.gridTemplateColumns = `repeat(${cols}, minmax(0, 1fr))`;
        rowWrap.style.gap = gap;
      }
      (section.rows || []).forEach((row) => {
        (row.columns || []).forEach((col, ci) => {
          const colEl = document.createElement('div');
          const selCol = state.selected?.si === si && state.selected.ci === ci && state.selected.ei === undefined;
          colEl.className = 'studio-col-preview' + (selCol ? ' selected' : '');
          if (col.width && col.width !== '100%') {
            colEl.style.flex = '0 0 ' + col.width;
            if (layoutType === 'grid') colEl.style.maxWidth = col.width;
          }
          colEl.addEventListener('click', (e) => {
            if (e.target.closest('.studio-el')) return;
            e.stopPropagation();
            select({ si, ci });
          });
          (col.elements || []).forEach((el, ei) => {
            colEl.appendChild(renderElPreview(el, si, ci, ei));
          });
          rowWrap.appendChild(colEl);
        });
      });
      sec.appendChild(rowWrap);
      sec.addEventListener('click', (e) => {
        if (e.target.closest('.studio-el') || e.target.closest('.studio-col-preview')) return;
        e.stopPropagation();
        select({ si });
      });
      sec.addEventListener('dragstart', (e) => e.dataTransfer.setData('text/plain', String(si)));
      sec.addEventListener('dragover', (e) => e.preventDefault());
      sec.addEventListener('drop', (e) => {
        e.preventDefault();
        const from = parseInt(e.dataTransfer.getData('text/plain'), 10);
        if (!isNaN(from) && from !== si) reorderSection(from, si);
      });
      root.appendChild(sec);
    });
  }

  function stylesToCss(styles) {
    if (!styles || typeof styles !== 'object') return '';
    return Object.entries(styles)
      .filter(([, v]) => v != null && v !== '')
      .map(([k, v]) => {
        const prop = k.replace(/([A-Z])/g, '-$1').toLowerCase();
        return prop + ':' + v;
      })
      .join(';');
  }

  function renderElPreview(el, si, ci, ei) {
    const div = document.createElement('div');
    const sel = state.selected?.si === si && state.selected?.ci === ci && state.selected?.ei === ei;
    div.className = 'studio-el' + (sel ? ' selected' : '');
    if (el.classes?.length) {
      div.className += ' ' + el.classes.join(' ');
    }
    const inlineCss = stylesToCss(el.styles);
    if (inlineCss) div.style.cssText = inlineCss;
    if (el.type === 'binding') {
      div.className += ' studio-binding';
      div.textContent = `[${el.bindingType || 'loop'}] ${el.collection || ''}`;
    } else if (el.type === 'html') {
      div.innerHTML = el.content || '<em>HTML</em>';
    } else if (el.type === 'image') {
      div.innerHTML = `<img src="${esc(el.src || '')}" alt="${esc(el.alt || '')}" style="max-width:100%;height:auto">`;
    } else if (el.type === 'spacer') {
      div.style.height = (el.height || 24) + 'px';
      div.className += ' studio-spacer-preview';
    } else if (el.type === 'divider') {
      div.innerHTML = '<hr>';
    } else {
      const tag = el.tag || 'p';
      div.innerHTML = `<${tag}>${esc(el.content || '')}</${tag}>`;
      if (el.type === 'text' || el.type === 'button' || el.type === 'link') {
        div.contentEditable = 'true';
        div.addEventListener('blur', () => {
          pushUndo();
          el.content = div.textContent || '';
          markDirty(state.activeTab);
          saveActiveFile().catch(alertErr);
        });
      }
    }
    div.addEventListener('click', (e) => {
      e.stopPropagation();
      select({ si, ci, ei });
    });
    if (typeof window.StudioIDE?.afterRenderEl === 'function') {
      window.StudioIDE.afterRenderEl(div, el, si, ci, ei);
    }
    return div;
  }

  function reorderSection(from, to) {
    pushUndo();
    const sections = state.canvas.sections;
    const [item] = sections.splice(from, 1);
    sections.splice(to, 0, item);
    saveActiveFile().catch(alertErr);
    renderCanvas();
    renderLayers();
  }

  function addElement(type) {
    if (!state.canvas) {
      toast('Open a .studio.json page first', 'error');
      return;
    }
    pushUndo();
    if (type === 'section') {
      state.canvas.sections.push({
        id: uid('section'),
        classes: ['studio-section'],
        layout: { type: 'grid', columns: 3, gap: '1rem' },
        responsive: { default: { columns: 1 }, tablet: { columns: 2 }, desktop: { columns: 3 } },
        rows: [{ id: uid('row'), columns: [{ id: uid('col'), width: '100%', elements: [] }] }],
      });
      select({ si: state.canvas.sections.length - 1 });
    } else {
      let section = state.canvas.sections[state.selected?.si ?? 0];
      if (!section) {
        addElement('section');
        section = state.canvas.sections[0];
      }
      const ci = state.selected?.ci ?? 0;
      const col = section.rows[0].columns[ci] || section.rows[0].columns[0];
      const el = { id: uid('el'), type: 'text', tag: 'p', content: 'New text', classes: [], styles: {} };
      if (type === 'heading') { el.tag = 'h2'; el.content = 'Heading'; }
      else if (type === 'image') { el.type = 'image'; el.src = '/admin/assets/birkly-icon.svg'; el.alt = ''; }
      else if (type === 'button') { el.type = 'button'; el.content = 'Click me'; el.href = '#'; }
      else if (type === 'spacer') { el.type = 'spacer'; el.height = 32; }
      else if (type === 'divider') { el.type = 'divider'; }
      else if (type === 'html') { el.type = 'html'; el.content = '<p>Custom HTML</p>'; }
      col.elements.push(el);
      select({ si: state.selected?.si ?? 0, ci, ei: col.elements.length - 1 });
    }
    saveActiveFile().catch(alertErr);
    renderCanvas();
    renderLayers();
  }

  function addColumn() {
    const section = getSelectedSection() || state.canvas?.sections?.[0];
    if (!section || !state.canvas) {
      toast('Select a section first', 'error');
      return;
    }
    pushUndo();
    const cols = Math.min(4, (section.layout?.columns || 1) + 1);
    syncSectionColumns(section, cols);
    if (section.responsive) {
      Object.keys(section.responsive).forEach((bp) => {
        section.responsive[bp].columns = Math.min(cols, (section.responsive[bp].columns || 1) + 1);
      });
    }
    saveActiveFile().catch(alertErr);
    renderCanvas();
    renderInspector();
  }

  function duplicateSection() {
    const si = state.selected?.si;
    if (si === undefined || !state.canvas) {
      toast('Select a section first', 'error');
      return;
    }
    pushUndo();
    const copy = JSON.parse(JSON.stringify(state.canvas.sections[si]));
    copy.id = uid('section');
    (copy.rows || []).forEach((row) => {
      row.id = uid('row');
      (row.columns || []).forEach((col) => {
        col.id = uid('col');
        (col.elements || []).forEach((el) => { el.id = uid('el'); });
      });
    });
    state.canvas.sections.splice(si + 1, 0, copy);
    select({ si: si + 1 });
    saveActiveFile().catch(alertErr);
    renderCanvas();
    renderLayers();
  }

  function moveSectionUp() {
    const si = state.selected?.si;
    if (si === undefined || si <= 0 || !state.canvas) return;
    reorderSection(si, si - 1);
    select({ si: si - 1 });
  }

  function moveSectionDown() {
    const si = state.selected?.si;
    if (si === undefined || !state.canvas || si >= state.canvas.sections.length - 1) return;
    reorderSection(si, si + 1);
    select({ si: si + 1 });
  }

  function renderLayers() {
    const el = $('#studio-layers');
    if (!el) return;
    if (!state.canvas) {
      el.innerHTML = '<p class="form-hint">—</p>';
      return;
    }
    let html = '';
    (state.canvas.sections || []).forEach((sec, si) => {
      const secSel = state.selected?.si === si && state.selected.ei === undefined && state.selected.ci === undefined;
      html += `<button type="button" class="studio-layer-btn${secSel ? ' active' : ''}" data-action="select-layer" data-si="${si}">📦 Section ${si + 1}</button>`;
      (sec.rows?.[0]?.columns || []).forEach((col, ci) => {
        const colSel = state.selected?.si === si && state.selected.ci === ci && state.selected.ei === undefined;
        html += `<button type="button" class="studio-layer-btn studio-layer-col${colSel ? ' active' : ''}" data-action="select-layer" data-si="${si}" data-ci="${ci}">↳ Col ${ci + 1}</button>`;
        (col.elements || []).forEach((item, ei) => {
          const elSel = state.selected?.si === si && state.selected.ci === ci && state.selected.ei === ei;
          html += `<button type="button" class="studio-layer-btn studio-layer-el${elSel ? ' active' : ''}" data-action="select-layer" data-si="${si}" data-ci="${ci}" data-ei="${ei}">↳ ${esc(item.type)}</button>`;
        });
      });
    });
    el.innerHTML = html || '<p class="form-hint">No layers</p>';
  }

  function renderInspector() {
    const body = $('#studio-inspector-body');
    if (!body) return;

    if (state.inspectorTab === 'nav') {
      renderNavInspector(body);
      return;
    }

    if (state.inspectorTab === 'creative') {
      if (typeof window.StudioCreative?.renderCreativeInspector === 'function') {
        window.StudioCreative.renderCreativeInspector();
      } else {
        body.innerHTML = '<p class="form-hint">Creative inspector — use Timeline / Popups in the sidebar.</p>';
      }
      return;
    }

    if (state.inspectorTab === 'css') {
      const el = getSelectedElement();
      if (!el) {
        body.innerHTML = '<p class="form-hint">Select an element to edit CSS rules.</p>';
        return;
      }
      let html = '<p class="form-hint studio-css-hint">Styles write to <code>element.styles</code> and compile to inline CSS.</p>';
      CSS_STYLE_KEYS.forEach((key) => {
        const label = key.replace(/([A-Z])/g, ' $1').replace(/^./, (s) => s.toUpperCase());
        html += field(label, key, el.styles?.[key] || '');
      });
      html += `<button type="button" class="btn btn-secondary btn-sm" data-action="clear-element-styles">Clear styles</button>`;
      body.innerHTML = html;
      bindCssInspector(el);
      return;
    }

    const el = getSelectedElement();
    const column = getSelectedColumn();
    const section = getSelectedSection();

    if (el) {
      let html = `<p><strong>${esc(el.type)}</strong></p>`;
      if (el.type === 'text' || el.type === 'button' || el.type === 'link') {
        html += field('Content', 'content', el.content);
        html += field('Tag', 'tag', el.tag || 'p');
        if (el.type === 'button' || el.type === 'link') html += field('Href', 'href', el.href || '#');
      }
      if (el.type === 'image') {
        html += field('Src', 'src', el.src);
        html += field('Alt', 'alt', el.alt);
      }
      if (el.type === 'html') html += field('HTML', 'content', el.content, true);
      if (el.type === 'binding') {
        html += selectField('Binding type', 'bindingType', ['loop', 'filtered', 'single', 'site_element'], el.bindingType);
        html += selectField('Collection', 'collection', state.collections.length ? state.collections : ['blog'], el.collection);
        html += field('Item template', 'itemTemplate', el.itemTemplate, true);
        html += `<div class="studio-canonical-preview"><strong>Compiled syntax</strong><pre>${esc(bindingCanonicalPreview({ type: el.bindingType, collection: el.collection, itemTemplate: el.itemTemplate, filterField: el.filterField, filterValue: el.filterValue, orderBy: el.orderBy, limit: el.limit, slug: el.slug }))}</pre></div>`;
        if (el.bindingType === 'filtered') {
          html += field('Filter field', 'filterField', el.filterField);
          html += field('Filter value', 'filterValue', el.filterValue);
          html += selectField('Order by', 'orderBy', ['newest', 'oldest', 'last-updated'], el.orderBy || 'newest');
          html += field('Limit', 'limit', el.limit || '');
        }
        if (el.bindingType === 'single' || el.bindingType === 'site_element') {
          html += field('Entry slug', 'slug', el.slug);
        }
      }
      if (el.type === 'spacer') html += field('Height (px)', 'height', el.height);
      html += field('Classes', 'classesStr', (el.classes || []).join(' '));
      html += `<button type="button" class="btn btn-secondary btn-sm" data-action="delete-element">Delete element</button>`;
      body.innerHTML = html;
      bindInspector(el);
      return;
    }

    if (column && state.selected?.ci !== undefined && state.selected.ei === undefined) {
      let html = `<p><strong>Column ${(state.selected.ci ?? 0) + 1}</strong></p>`;
      html += field('Width %', 'width', column.width || '100%');
      html += `<p class="form-hint">${(column.elements || []).length} element(s) in this column.</p>`;
      body.innerHTML = html;
      bindColumnInspector(column);
      return;
    }

    if (section) {
      const layout = section.layout || {};
      const layoutType = layout.type || 'grid';
      const cols = layout.columns || 1;
      let html = `<p><strong>Section ${(state.selected?.si ?? 0) + 1}</strong></p>`;
      html += `<label>Layout type
        <div class="studio-layout-toggle">
          <button type="button" class="studio-layout-btn${layoutType === 'grid' ? ' active' : ''}" data-action="set-layout-type" data-layout="grid">Grid</button>
          <button type="button" class="studio-layout-btn${layoutType === 'flex' ? ' active' : ''}" data-action="set-layout-type" data-layout="flex">Flex</button>
        </div>
      </label>`;
      html += `<label>Columns <span class="studio-slider-val" id="studio-cols-val">${cols}</span>
        <input type="range" class="studio-insp-field studio-cols-slider" data-insp="columns" min="1" max="4" value="${cols}">
      </label>`;
      html += field('Gap', 'gap', layout.gap || '1rem');
      html += field('Classes', 'classesStr', (section.classes || []).join(' '));
      html += `<button type="button" class="btn btn-secondary btn-sm" data-action="delete-section">Delete section</button>`;
      body.innerHTML = html;
      bindSectionInspector(section);
      return;
    }

    body.innerHTML = '<p class="form-hint">Select a section, column, or element — or use the <strong>Nav</strong> tab for page navigation.</p>';
  }

  function renderNavInspector(body) {
    if (!state.canvas) {
      body.innerHTML = '<p class="form-hint">Open a page to edit navigation.</p>';
      return;
    }
    ensureCanvasNav();
    const items = state.canvas.nav;
    let html = '<p class="form-hint">Page header navigation (<code>canvas.nav</code>). Compiled into site header HTML.</p>';
    items.forEach((item, i) => {
      html += `<div class="studio-nav-item" data-nav-idx="${i}">
        <label>Label<input class="input studio-nav-field" data-nav="${i}" data-key="label" value="${esc(item.label || '')}"></label>
        <label>Href<input class="input studio-nav-field" data-nav="${i}" data-key="href" value="${esc(item.href || '')}"></label>
        <label class="studio-nav-active"><input type="checkbox" class="studio-nav-field" data-nav="${i}" data-key="active"${item.active ? ' checked' : ''}> Active</label>
        <button type="button" class="btn btn-secondary btn-sm" data-action="remove-nav-item" data-nav-idx="${i}">Remove</button>
      </div>`;
    });
    html += `<button type="button" class="btn btn-secondary btn-sm" data-action="add-nav-item">+ Add link</button>`;
    body.innerHTML = html;
    bindNavInspector();
  }

  function bindNavInspector() {
    $('#studio-inspector-body')?.querySelectorAll('.studio-nav-field').forEach((input) => {
      const handler = () => {
        const idx = parseInt(input.dataset.nav, 10);
        const key = input.dataset.key;
        if (!state.canvas?.nav?.[idx]) return;
        pushUndo();
        if (key === 'active') {
          state.canvas.nav.forEach((n, i) => { n.active = i === idx && input.checked; });
        } else {
          state.canvas.nav[idx][key] = input.value;
        }
        markDirty(state.activeTab);
        saveActiveFile().catch(alertErr);
      };
      input.addEventListener('change', handler);
      if (input.type === 'text') input.addEventListener('input', handler);
    });
  }

  function bindCssInspector(el) {
    $('#studio-inspector-body')?.querySelectorAll('.studio-insp-field').forEach((input) => {
      const handler = () => {
        pushUndo();
        const key = input.dataset.insp;
        el.styles = el.styles || {};
        el.styles[key] = input.value;
        markDirty(state.activeTab);
        saveActiveFile().catch(alertErr);
        renderCanvas();
      };
      input.addEventListener('change', handler);
      if (input.tagName === 'INPUT') input.addEventListener('input', handler);
    });
  }

  function bindColumnInspector(column) {
    $('#studio-inspector-body')?.querySelectorAll('.studio-insp-field').forEach((input) => {
      input.addEventListener('change', () => {
        pushUndo();
        let w = input.value.trim();
        if (w && !w.endsWith('%') && !w.endsWith('px')) w += '%';
        column.width = w || '100%';
        markDirty(state.activeTab);
        saveActiveFile().catch(alertErr);
        renderCanvas();
      });
    });
  }

  function field(label, key, value, textarea) {
    const v = esc(value ?? '');
    if (textarea) {
      return `<label>${label}<textarea class="input studio-insp-field" data-insp="${key}" rows="3">${v}</textarea></label>`;
    }
    return `<label>${label}<input class="input studio-insp-field" data-insp="${key}" value="${v}"></label>`;
  }

  function selectField(label, key, options, value) {
    const opts = options.map((o) => `<option value="${esc(o)}"${o === value ? ' selected' : ''}>${esc(o)}</option>`).join('');
    return `<label>${label}<select class="input studio-insp-field" data-insp="${key}">${opts}</select></label>`;
  }

  function bindInspector(el) {
    $('#studio-inspector-body')?.querySelectorAll('.studio-insp-field').forEach((input) => {
      const handler = () => {
        pushUndo();
        const key = input.dataset.insp;
        if (key === 'classesStr') {
          el.classes = input.value.split(/\s+/).filter(Boolean);
        } else if (key === 'height') {
          el.height = parseInt(input.value, 10) || 24;
        } else if (key === 'limit') {
          el.limit = parseInt(input.value, 10) || 0;
        } else {
          el[key] = input.value;
        }
        markDirty(state.activeTab);
        saveActiveFile().catch(alertErr);
        renderCanvas();
        if (el.type === 'binding') renderInspector();
      };
      input.addEventListener('change', handler);
      if (input.tagName === 'INPUT') input.addEventListener('input', handler);
    });
  }

  function bindSectionInspector(section) {
    $('#studio-inspector-body')?.querySelectorAll('.studio-insp-field').forEach((input) => {
      const handler = () => {
        pushUndo();
        const key = input.dataset.insp;
        if (key === 'classesStr') {
          section.classes = input.value.split(/\s+/).filter(Boolean);
        } else if (key === 'columns') {
          const cols = parseInt(input.value, 10) || 1;
          syncSectionColumns(section, cols);
          const valEl = $('#studio-cols-val');
          if (valEl) valEl.textContent = String(cols);
        } else {
          section.layout = section.layout || { type: 'grid', columns: 1, gap: '1rem' };
          section.layout[key] = input.value;
        }
        markDirty(state.activeTab);
        saveActiveFile().catch(alertErr);
        renderCanvas();
        renderLayers();
      };
      input.addEventListener('change', handler);
      if (input.type === 'range') input.addEventListener('input', handler);
    });
  }

  function openBindingWizard(type) {
    if (!state.canvas) {
      toast('Open a .studio.json page first', 'error');
      return;
    }
    state.bindingWizard = {
      step: 1,
      type: type || 'loop',
      collection: state.collections[0] || 'blog',
      itemTemplate: '<article><h2>{ title }</h2><p>{ excerpt }</p></article>',
      filterField: 'status',
      filterValue: 'published',
      orderBy: 'newest',
      limit: 3,
      slug: type === 'site_element' ? 'hero' : 'example',
    };
    $('#studio-binding-modal').hidden = false;
    renderBindingWizard();
  }

  function closeBindingWizard() {
    state.bindingWizard = null;
    const modal = $('#studio-binding-modal');
    if (modal) modal.hidden = true;
  }

  function renderBindingWizard() {
    const wiz = state.bindingWizard;
    if (!wiz) return;
    const stepsEl = $('#studio-wizard-steps');
    const bodyEl = $('#studio-wizard-body');
    const prevBtn = $('#studio-wizard-prev');
    const nextBtn = $('#studio-wizard-next');
    const maxStep = wiz.type === 'filtered' ? 4 : 3;
    const labels = ['Type', 'Collection', 'Template', wiz.type === 'filtered' ? 'Filters' : ''];
    if (stepsEl) {
      stepsEl.innerHTML = [1, 2, 3, 4].slice(0, maxStep).map((n) => {
        const cls = n === wiz.step ? 'active' : (n < wiz.step ? 'done' : '');
        return `<span class="studio-wizard-step ${cls}">${n}. ${labels[n - 1]}</span>`;
      }).join('');
    }
    if (prevBtn) prevBtn.hidden = wiz.step <= 1;
    if (nextBtn) nextBtn.textContent = wiz.step >= maxStep ? 'Add binding' : 'Next';

    let html = '';
    if (wiz.step === 1) {
      html = '<p class="form-hint">Choose binding type:</p><div class="studio-wizard-types">';
      BINDING_TYPES.forEach((bt) => {
        html += `<button type="button" class="studio-wizard-type-btn${wiz.type === bt.id ? ' active' : ''}" data-action="wizard-set-type" data-type="${bt.id}">
          <strong>${esc(bt.label)}</strong><small>${esc(bt.desc)}</small></button>`;
      });
      html += '</div>';
    } else if (wiz.step === 2) {
      const cols = state.collections.length ? state.collections : ['blog'];
      if (wiz.type === 'site_element') {
        html = '<p class="form-hint">Site elements use the <code>site_elements</code> collection.</p>';
        wiz.collection = 'site_elements';
      } else {
        html = selectField('Collection', 'wiz-collection', cols, wiz.collection);
      }
      if (wiz.type === 'single' || wiz.type === 'site_element') {
        html += field('Entry slug', 'wiz-slug', wiz.slug);
      }
    } else if (wiz.step === 3) {
      html = field('Item template', 'wiz-template', wiz.itemTemplate, true);
      html += `<div class="studio-canonical-preview"><strong>Canonical syntax preview</strong><pre>${esc(bindingCanonicalPreview(wiz))}</pre></div>`;
      html += '<p class="form-hint">Use spaced placeholders like <code>{ title }</code> — compiler normalizes these.</p>';
    } else if (wiz.step === 4) {
      html = field('Filter field', 'wiz-filterField', wiz.filterField);
      html += field('Filter value', 'wiz-filterValue', wiz.filterValue);
      html += `<label>Order by<select class="input studio-wiz-field" data-wiz="orderBy">
        <option value="newest"${wiz.orderBy === 'newest' ? ' selected' : ''}>newest</option>
        <option value="oldest"${wiz.orderBy === 'oldest' ? ' selected' : ''}>oldest</option>
        <option value="last-updated"${wiz.orderBy === 'last-updated' ? ' selected' : ''}>last-updated</option>
      </select></label>`;
      html += field('Limit', 'wiz-limit', wiz.limit);
      html += `<div class="studio-canonical-preview"><strong>Canonical syntax preview</strong><pre>${esc(bindingCanonicalPreview(wiz))}</pre></div>`;
    }
    if (bodyEl) {
      bodyEl.innerHTML = html;
      bodyEl.querySelectorAll('.studio-wiz-field, [data-wiz]').forEach((input) => {
        input.addEventListener('change', () => {
          const key = input.dataset.wiz || input.dataset.insp?.replace('wiz-', '');
          if (key === 'orderBy') wiz.orderBy = input.value;
        });
      });
      bodyEl.querySelectorAll('[data-insp^="wiz-"]').forEach((input) => {
        const key = input.dataset.insp.replace('wiz-', '');
        const sync = () => {
          if (key === 'collection') wiz.collection = input.value;
          else if (key === 'slug') wiz.slug = input.value;
          else if (key === 'template') wiz.itemTemplate = input.value;
          else if (key === 'filterField') wiz.filterField = input.value;
          else if (key === 'filterValue') wiz.filterValue = input.value;
          else if (key === 'limit') wiz.limit = parseInt(input.value, 10) || 0;
          if (wiz.step >= 3) renderBindingWizard();
        };
        input.addEventListener('change', sync);
        if (input.tagName === 'TEXTAREA' || input.type === 'text') input.addEventListener('input', sync);
      });
    }
  }

  function wizardNext() {
    const wiz = state.bindingWizard;
    if (!wiz) return;
    const maxStep = wiz.type === 'filtered' ? 4 : 3;
    if (wiz.step < maxStep) {
      wiz.step += 1;
      renderBindingWizard();
      return;
    }
    applyBindingWizard();
  }

  function applyBindingWizard() {
    const wiz = state.bindingWizard;
    if (!wiz || !state.canvas) return;
    pushUndo();
    let section = state.canvas.sections[state.selected?.si ?? 0];
    if (!section) {
      state.canvas.sections.push({
        id: uid('section'),
        classes: ['studio-section'],
        layout: { type: 'grid', columns: 1, gap: '1rem' },
        responsive: { default: { columns: 1 }, tablet: { columns: 2 }, desktop: { columns: 3 } },
        rows: [{ id: uid('row'), columns: [{ id: uid('col'), width: '100%', elements: [] }] }],
      });
      section = state.canvas.sections[0];
    }
    const ci = state.selected?.ci ?? 0;
    const col = section.rows[0].columns[ci] || section.rows[0].columns[0];
    const el = {
      id: uid('el'),
      type: 'binding',
      bindingType: wiz.type,
      collection: wiz.type === 'site_element' ? 'site_elements' : wiz.collection,
      itemTemplate: wiz.itemTemplate,
      classes: [],
      styles: {},
    };
    if (wiz.type === 'filtered') {
      el.filterField = wiz.filterField;
      el.filterValue = wiz.filterValue;
      el.orderBy = wiz.orderBy;
      el.limit = wiz.limit;
    }
    if (wiz.type === 'single' || wiz.type === 'site_element') {
      el.slug = wiz.slug;
    }
    col.elements.push(el);
    closeBindingWizard();
    select({ si: state.selected?.si ?? 0, ci, ei: col.elements.length - 1 });
    saveActiveFile().catch(alertErr);
    renderCanvas();
    renderLayers();
    toast('Binding added', 'success');
  }

  function deleteSelectedElement() {
    const el = getSelectedElement();
    if (!el || !state.canvas || state.selected?.ei === undefined) return;
    pushUndo();
    const sec = state.canvas.sections[state.selected.si];
    const ci = state.selected.ci ?? 0;
    sec.rows[0].columns[ci].elements.splice(state.selected.ei, 1);
    state.selected = { si: state.selected.si, ci };
    saveActiveFile().catch(alertErr);
    renderCanvas();
    renderLayers();
    renderInspector();
  }

  function deleteSelectedSection() {
    if (!state.canvas || state.selected?.si === undefined) return;
    pushUndo();
    state.canvas.sections.splice(state.selected.si, 1);
    state.selected = null;
    saveActiveFile().catch(alertErr);
    renderCanvas();
    renderLayers();
    renderInspector();
  }

  // ——— View modes ———
  function applyViewMode() {
    const v = state.viewMode;
    const visual = $('#studio-pane-visual');
    const code = $('#studio-pane-code');
    const panes = $('#studio-panes');
    if (!panes) return;
    panes.classList.toggle('split', v === 'split');
    if (visual) visual.hidden = v === 'code';
    if (code) code.hidden = v === 'visual';
  }

  // ——— Tokens, blocks, sections ———
  async function loadTokens() {
    const data = await api('get_tokens', { project_id: state.project.id });
    const ed = $('#studio-tokens-editor');
    if (ed) ed.value = JSON.stringify(data.tokens || {}, null, 2);
  }

  async function loadBlocks() {
    const data = await api('list_studio_blocks');
    state.blocks = data.blocks || [];
    const box = $('#studio-blocks');
    if (!box) return;
    if (!state.blocks.length) {
      box.innerHTML = '<p class="form-hint">Activate commerce/events for plugin blocks.</p>';
      return;
    }
    box.innerHTML = state.blocks.map((b) =>
      `<button type="button" class="studio-block-btn" data-action="insert-block" data-block="${esc(b.id)}">${esc(b.label || b.id)}</button>`
    ).join('');
  }

  async function loadSections() {
    const data = await api('list_sections');
    const box = $('#studio-section-lib');
    if (!box) return;
    box.innerHTML = (data.sections || []).map((s) =>
      `<button type="button" class="studio-block-btn" data-action="insert-section" data-section="${esc(s.id)}">${esc(s.label || s.id)}</button>`
    ).join('');
  }

  async function insertSection(sectionId) {
    if (!isStudioPage(state.activeTab)) {
      toast('Open a page (.studio.json) first', 'error');
      return;
    }
    await api('insert_section', {
      project_id: state.project.id,
      page_slug: pageSlugFromPath(state.activeTab),
      section_id: sectionId,
    });
    await activateTab(state.activeTab);
    toast('Section inserted', 'success');
  }

  function insertBlock(blockId) {
    const block = state.blocks.find((b) => b.id === blockId);
    if (!block || !state.canvas) {
      toast('Open a page (.studio.json) first', 'error');
      return;
    }
    pushUndo();
    let section = state.canvas.sections[0];
    if (!section) { addElement('section'); section = state.canvas.sections[0]; }
    section.rows[0].columns[0].elements.push({
      id: uid('el'),
      type: 'binding',
      bindingType: block.bindingType || 'loop',
      collection: block.collection || 'blog',
      itemTemplate: block.itemTemplate || '<div>{title}</div>',
    });
    saveActiveFile().catch(alertErr);
    renderCanvas();
    renderLayers();
    toast('Block added', 'success');
  }

  async function loadCollections() {
    const data = await api('list_collections');
    state.collections = data.collections || [];
  }

  async function createNewPage() {
    const title = prompt('Page title', 'New page');
    if (!title || !state.project) return;
    const data = await api('add_page', { project_id: state.project.id, title });
    await loadFileTree();
    const slug = data.page?.slug || title.toLowerCase().replace(/[^a-z0-9]+/g, '-');
    openTab('pages/' + slug + '.studio.json');
    toast('Page created', 'success');
  }

  // ——— Event delegation ———
  function handleClick(e) {
    const app = $('#studio-app');
    if (!app || !app.contains(e.target)) return;

    const viewBtn = e.target.closest('.studio-view-btn');
    if (viewBtn) {
      app.querySelectorAll('.studio-view-btn').forEach((b) => b.classList.remove('active'));
      viewBtn.classList.add('active');
      state.viewMode = viewBtn.dataset.view;
      applyViewMode();
      return;
    }

    const inspTab = e.target.closest('.studio-insp-tab');
    if (inspTab && !inspTab.disabled) {
      setInspectorTab(inspTab.dataset.inspTab);
      return;
    }

    const closeTabBtn = e.target.closest('[data-action="close-tab"]');
    if (closeTabBtn) {
      e.stopPropagation();
      closeTab(closeTabBtn.dataset.path);
      return;
    }

    const actionEl = e.target.closest('[data-action]');
    if (!actionEl) {
      const addBtn = e.target.closest('[data-add]');
      if (addBtn) {
        addElement(addBtn.dataset.add);
      }
      return;
    }

    const action = actionEl.dataset.action;
    switch (action) {
      case 'new-project':
        $('#studio-new-modal').hidden = false;
        break;
      case 'close-new':
        $('#studio-new-modal').hidden = true;
        break;
      case 'create-project':
        withBusy(async () => {
          const name = $('#studio-new-name')?.value?.trim();
          if (!name) throw new Error('Project name required');
          const data = await api('create_project', { name, style_kit: $('#studio-new-kit')?.value || 'minimal' });
          $('#studio-new-modal').hidden = true;
          await openProject(data.project.id);
          await loadProjects();
        });
        break;
      case 'open-project':
        withBusy(() => openProject(actionEl.dataset.id));
        break;
      case 'dup-project':
        withBusy(async () => {
          await api('duplicate_project', { id: actionEl.dataset.id, name: 'Copy' });
          await loadProjects();
          toast('Project duplicated', 'success');
        });
        break;
      case 'del-project':
        if (!confirm('Delete this project?')) return;
        withBusy(async () => {
          await api('delete_project', { id: actionEl.dataset.id });
          await loadProjects();
          toast('Project deleted', 'success');
        });
        break;
      case 'back':
        $('#studio-workspace').hidden = true;
        $('#studio-dashboard').hidden = false;
        loadProjects().catch(alertErr);
        break;
      case 'save':
        withBusy(() => saveActiveFile());
        break;
      case 'open-external':
        withBusy(async () => {
          const data = await api('open_project_url', { project_id: state.project.id });
          const url = absUrl(data.url);
          const label = data.published ? 'Live site' : 'Draft preview';
          setStatus(label + ': ' + url, 'info');
          const w = window.open(url, '_blank', 'noopener,noreferrer');
          if (!w) {
            toast('Popup blocked — copy the link from the status bar.', 'error');
            try {
              await navigator.clipboard.writeText(url);
              toast('Link copied to clipboard', 'success');
            } catch (_) { /* ignore */ }
          }
        });
        break;
      case 'publish':
        withBusy(async () => {
          await saveActiveFile();
          const data = await api('publish_project', { id: state.project.id });
          if (data.project) state.project = data.project;
          state.ssrHealth = { route_ready: data.route_ready, hints: data.route_hints || [] };
          renderStatusBar();
          const url = absUrl(data.view_url || data.public_url || '/api/studio_view.php?slug=' + encodeURIComponent(state.project.slug || ''));
          toast('Published! Open with ↗ or visit: ' + url, 'success');
        });
        break;
      case 'export':
        withBusy(() => api('export_project', { id: state.project.id }));
        break;
      case 'refresh-tree':
        withBusy(() => loadFileTree());
        break;
      case 'new-page':
        withBusy(() => createNewPage());
        break;
      case 'open-tab':
      case 'activate-tab':
        activateTab(actionEl.dataset.path).catch(alertErr);
        break;
      case 'undo': {
        const prev = state.undoStack.pop();
        if (prev && state.canvas) {
          state.redoStack.push(JSON.stringify(state.canvas));
          state.canvas = JSON.parse(prev);
          saveActiveFile().catch(alertErr);
          renderCanvas();
          renderLayers();
          renderInspector();
        }
        break;
      }
      case 'redo': {
        const next = state.redoStack.pop();
        if (next && state.canvas) {
          state.undoStack.push(JSON.stringify(state.canvas));
          state.canvas = JSON.parse(next);
          saveActiveFile().catch(alertErr);
          renderCanvas();
          renderLayers();
          renderInspector();
        }
        break;
      }
      case 'add-column':
        addColumn();
        break;
      case 'dup-section':
        duplicateSection();
        break;
      case 'move-section-up':
        moveSectionUp();
        break;
      case 'move-section-down':
        moveSectionDown();
        break;
      case 'open-binding-wizard':
        openBindingWizard(actionEl.dataset.bindingType);
        break;
      case 'close-binding-wizard':
        closeBindingWizard();
        break;
      case 'wizard-next':
        wizardNext();
        break;
      case 'wizard-prev':
        if (state.bindingWizard && state.bindingWizard.step > 1) {
          state.bindingWizard.step -= 1;
          renderBindingWizard();
        }
        break;
      case 'wizard-set-type':
        if (state.bindingWizard) {
          state.bindingWizard.type = actionEl.dataset.type;
          renderBindingWizard();
        }
        break;
      case 'set-layout-type': {
        const section = getSelectedSection();
        if (!section) break;
        pushUndo();
        section.layout = section.layout || { type: 'grid', columns: 1, gap: '1rem' };
        section.layout.type = actionEl.dataset.layout;
        markDirty(state.activeTab);
        saveActiveFile().catch(alertErr);
        renderCanvas();
        renderInspector();
        break;
      }
      case 'add-nav-item':
        ensureCanvasNav();
        pushUndo();
        state.canvas.nav.push({ id: uid('nav'), label: 'Link', href: '/', active: state.canvas.nav.length === 0 });
        markDirty(state.activeTab);
        saveActiveFile().catch(alertErr);
        renderInspector();
        break;
      case 'remove-nav-item': {
        const idx = parseInt(actionEl.dataset.navIdx, 10);
        if (!isNaN(idx) && state.canvas?.nav) {
          pushUndo();
          state.canvas.nav.splice(idx, 1);
          markDirty(state.activeTab);
          saveActiveFile().catch(alertErr);
          renderInspector();
        }
        break;
      }
      case 'clear-element-styles': {
        const el = getSelectedElement();
        if (el) {
          pushUndo();
          el.styles = {};
          markDirty(state.activeTab);
          saveActiveFile().catch(alertErr);
          renderInspector();
          renderCanvas();
        }
        break;
      }
      case 'save-tokens':
        withBusy(async () => {
          const tokens = JSON.parse($('#studio-tokens-editor').value);
          await api('save_tokens', { project_id: state.project.id, tokens });
          toast('Tokens saved', 'success');
        });
        break;
      case 'insert-section':
        withBusy(() => insertSection(actionEl.dataset.section));
        break;
      case 'insert-block':
        insertBlock(actionEl.dataset.block);
        break;
      case 'select-layer':
        select({
          si: parseInt(actionEl.dataset.si, 10),
          ci: actionEl.dataset.ci !== undefined ? parseInt(actionEl.dataset.ci, 10) : undefined,
          ei: actionEl.dataset.ei !== undefined ? parseInt(actionEl.dataset.ei, 10) : undefined,
        });
        break;
      case 'delete-element':
        deleteSelectedElement();
        break;
      case 'delete-section':
        if (confirm('Delete this section?')) deleteSelectedSection();
        break;
      default:
        break;
    }
  }

  function handleChange(e) {
    if (e.target.id === 'studio-breakpoint') {
      state.breakpoint = e.target.value;
      renderCanvas();
    }
  }

  function handleInput(e) {
    if (e.target.id === 'studio-code-editor' && state.activeTab) {
      markDirty(state.activeTab);
    }
  }

  async function handleCodeBlur() {
    if (!state.activeTab || !state.project) return;
    if (isStudioPage(state.activeTab)) {
      await withBusy(async () => {
        await api('parse_code', {
          project_id: state.project.id,
          page_slug: pageSlugFromPath(state.activeTab),
          html: $('#studio-code-editor').value,
        });
        await activateTab(state.activeTab);
      });
    } else {
      await withBusy(() => saveActiveFile());
    }
  }

  function bindEvents() {
    const app = $('#studio-app');
    if (!app || app.dataset.studioBound) return;
    app.dataset.studioBound = '1';
    app.addEventListener('click', handleClick);
    app.addEventListener('change', handleChange);
    app.addEventListener('input', handleInput);
    $('#studio-code-editor')?.addEventListener('blur', () => handleCodeBlur().catch(alertErr));
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        withBusy(() => saveActiveFile());
      }
      if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
        e.preventDefault();
        const prev = state.undoStack.pop();
        if (prev && state.canvas) {
          state.redoStack.push(JSON.stringify(state.canvas));
          state.canvas = JSON.parse(prev);
          saveActiveFile().catch(alertErr);
          renderCanvas();
          renderLayers();
          renderInspector();
        }
      }
      if ((e.ctrlKey || e.metaKey) && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) {
        e.preventDefault();
        const next = state.redoStack.pop();
        if (next && state.canvas) {
          state.undoStack.push(JSON.stringify(state.canvas));
          state.canvas = JSON.parse(next);
          saveActiveFile().catch(alertErr);
          renderCanvas();
          renderLayers();
          renderInspector();
        }
      }
    });
  }

  function boot() {
    if (typeof Birkly === 'undefined' || typeof Birkly.url !== 'function') {
      setTimeout(boot, 30);
      return;
    }
    bindEvents();
    loadProjects().catch(alertErr);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.StudioIDE = {
    state,
    api,
    apiUrl,
    csrfToken,
    toast,
    esc,
    uid,
    isStudioPage,
    pageSlugFromPath,
    pushUndo,
    markDirty,
    withBusy,
    alertErr,
    saveActiveFile,
    renderCanvas,
    renderLayers,
    renderInspector,
    updateInspectorTabs,
    setInspectorTab,
    select,
    getSelectedElement,
    getSelectedSection,
    getSelectedColumn,
    applyViewMode,
    renderElPreview,
    stylesToCss,
    bindingCanonicalPreview,
    openBindingWizard,
    renderBindingWizard,
    applyBindingWizard,
    ensureCanvasPage,
    ensureCanvasNav,
    BINDING_TYPES,
    CSS_STYLE_KEYS,
  };
})();
