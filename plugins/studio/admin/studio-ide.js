/**
 * Studio IDE (P23) — filesystem, tabs, visual/code/split, canvas v2.
 */
(function () {
  'use strict';

  const API = (window.birklyUrl || (p => p))(window.Birkly?.url ? window.Birkly.url('/api/index.php?route=studio') : '/api/index.php?route=studio');
  const SESSION_KEY = 'studio-ide-tabs';

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
    collections: [],
    blocks: [],
    undoStack: [],
    dirtyPaths: new Set(),
  };

  const $ = (s) => document.querySelector(s);
  const $$ = (s) => document.querySelectorAll(s);

  async function api(action, body = {}) {
    const res = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ action, ...body }),
    });
    if (action === 'export_project') {
      if (!res.ok) throw new Error('Export failed');
      const blob = await res.blob();
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = (state.project?.slug || 'studio') + '-export.zip';
      a.click();
      return {};
    }
    const json = await res.json();
    if (json.status !== 'ok') throw new Error(json.message || 'API error');
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
    const base = path.split('/').pop() || '';
    return base.replace(/\.studio\.json$/, '');
  }

  function pushUndo() {
    if (!state.canvas) return;
    state.undoStack.push(JSON.stringify(state.canvas));
    if (state.undoStack.length > 30) state.undoStack.shift();
  }

  function alertErr(e) {
    (window.showMessage || alert)(e.message || String(e), 'error');
  }

  // ——— Dashboard ———
  async function loadProjects() {
    const data = await api('list_projects');
    state.projects = data.projects || [];
    renderProjects();
  }

  function renderProjects() {
    const el = $('#studio-project-list');
    if (!state.projects.length) {
      el.innerHTML = '<p class="form-hint">No projects yet.</p>';
      return;
    }
    el.innerHTML = state.projects.map((p) => `
      <div class="studio-project-card">
        <div><h4>${esc(p.name)}</h4><div class="meta">/${esc(p.slug)} · ${p.published ? 'Published' : 'Draft'}</div></div>
        <button type="button" class="btn btn-primary" data-open="${esc(p.id)}">Open IDE</button>
      </div>`).join('');
    el.querySelectorAll('[data-open]').forEach((b) => b.addEventListener('click', () => openProject(b.dataset.open)));
  }

  async function openProject(id) {
    const data = await api('get_project', { id });
    state.project = data.project;
    state.undoStack = [];
    restoreTabs(id);
    $('#studio-dashboard').hidden = true;
    $('#studio-workspace').hidden = false;
    $('#studio-project-name').textContent = state.project.name;
    await Promise.all([loadFileTree(), loadBlocks(), loadSections(), loadTokens(), loadCollections()]);
    if (!state.tabs.length) {
      openTab('pages/index.studio.json');
    } else {
      activateTab(state.activeTab || state.tabs[0].path);
    }
    persistTabs();
  }

  // ——— File tree ———
  async function loadFileTree() {
    const data = await api('list_files', { project_id: state.project.id });
    state.fileTree = data.tree || [];
    renderFileTree();
  }

  function renderFileTree(nodes, container) {
    const root = container || $('#studio-file-tree');
    if (!container) root.innerHTML = '';
    const list = nodes || state.fileTree;
    list.forEach((node) => {
      const row = document.createElement('div');
      row.className = 'studio-tree-node studio-tree-' + node.type;
      row.style.paddingLeft = (node.depth || 0) * 12 + 'px';
      if (node.type === 'dir') {
        row.innerHTML = `<span class="studio-tree-icon">📁</span> ${esc(node.name)}`;
        root.appendChild(row);
        if (node.children) {
          node.children.forEach((c) => { c.depth = (node.depth || 0) + 1; });
          renderFileTree(node.children, root);
        }
      } else {
        row.innerHTML = `<button type="button" class="studio-tree-file" data-path="${esc(node.path)}">${esc(node.name)}</button>`;
        root.appendChild(row);
        row.querySelector('button').addEventListener('click', () => openTab(node.path));
      }
    });
  }

  // ——— Tabs ———
  function openTab(path) {
    if (!state.tabs.find((t) => t.path === path)) {
      state.tabs.push({ path, dirty: false });
    }
    activateTab(path);
    persistTabs();
  }

  async function activateTab(path) {
    state.activeTab = path;
    renderTabBar();
    const tab = state.tabs.find((t) => t.path === path);
    if (isStudioPage(path)) {
      const slug = pageSlugFromPath(path);
      const data = await api('get_page', { project_id: state.project.id, page_slug: slug });
      state.canvas = data.canvas;
      $('#studio-code-editor').value = data.html || '';
      renderCanvas();
    } else {
      state.canvas = null;
      const file = await api('read_file', { project_id: state.project.id, path });
      $('#studio-code-editor').value = file.file.content;
    }
    applyViewMode();
  }

  function renderTabBar() {
    const bar = $('#studio-tabbar');
    bar.innerHTML = state.tabs.map((t) => `
      <button type="button" class="studio-tab${t.path === state.activeTab ? ' active' : ''}${t.dirty ? ' dirty' : ''}" data-path="${esc(t.path)}">
        ${esc(t.path.split('/').pop())}${t.dirty ? ' •' : ''}
        <span class="studio-tab-close" data-close="${esc(t.path)}">×</span>
      </button>`).join('');
    bar.querySelectorAll('.studio-tab').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        if (e.target.classList.contains('studio-tab-close')) return;
        activateTab(btn.dataset.path);
      });
    });
    bar.querySelectorAll('[data-close]').forEach((x) => {
      x.addEventListener('click', (e) => {
        e.stopPropagation();
        closeTab(x.dataset.close);
      });
    });
  }

  function closeTab(path) {
    state.tabs = state.tabs.filter((t) => t.path !== path);
    if (state.activeTab === path) {
      state.activeTab = state.tabs[0]?.path || null;
      if (state.activeTab) activateTab(state.activeTab);
    }
    renderTabBar();
    persistTabs();
  }

  function markDirty(path) {
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
      const raw = sessionStorage.getItem(SESSION_KEY);
      const saved = raw ? JSON.parse(raw) : null;
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
    if (!path) return;
    if (isStudioPage(path)) {
      pushUndo();
      const slug = pageSlugFromPath(path);
      await api('save_page', { project_id: state.project.id, page_slug: slug, canvas: state.canvas });
      const compiled = await api('compile_page', { project_id: state.project.id, page_slug: slug });
      $('#studio-code-editor').value = compiled.html || '';
      const htmlPath = 'pages/' + slug + '.html';
      if (state.tabs.find((t) => t.path === htmlPath)) {
        await api('write_file', { project_id: state.project.id, path: htmlPath, content: compiled.html });
      }
    } else {
      await api('write_file', { project_id: state.project.id, path, content: $('#studio-code-editor').value });
    }
    const t = state.tabs.find((x) => x.path === path);
    if (t) t.dirty = false;
    state.dirtyPaths.delete(path);
    renderTabBar();
  }

  // ——— Visual canvas ———
  function renderCanvas() {
    const root = $('#studio-canvas');
    root.innerHTML = '';
    if (!state.canvas) {
      root.innerHTML = '<p class="form-hint">Not a studio page</p>';
      return;
    }
    const bp = state.breakpoint;
    (state.canvas.sections || []).forEach((section, si) => {
      const sec = document.createElement('div');
      sec.className = 'studio-section-block' + (state.selected?.si === si ? ' selected' : '');
      sec.draggable = true;
      sec.dataset.si = si;
      const layout = section.responsive?.[bp] || section.layout || {};
      const cols = layout.columns || section.layout?.columns || 1;
      sec.innerHTML = `<div class="studio-section-label">Section ${si + 1} · ${cols} col · ${bp}</div>`;
      (section.rows || []).forEach((row) => {
        (row.columns || []).forEach((col) => {
          (col.elements || []).forEach((el, ei) => {
            sec.appendChild(renderElPreview(el, si, ei));
          });
        });
      });
      sec.addEventListener('click', (e) => { e.stopPropagation(); select({ si }); renderCanvas(); renderInspector(); });
      sec.addEventListener('dragstart', (e) => { e.dataTransfer.setData('text/plain', String(si)); });
      sec.addEventListener('dragover', (e) => e.preventDefault());
      sec.addEventListener('drop', (e) => {
        e.preventDefault();
        const from = parseInt(e.dataTransfer.getData('text/plain'), 10);
        const to = si;
        if (!isNaN(from) && from !== to) reorderSection(from, to);
      });
      root.appendChild(sec);
    });
  }

  function renderElPreview(el, si, ei) {
    const div = document.createElement('div');
    div.className = 'studio-el' + (state.selected?.si === si && state.selected?.ei === ei ? ' selected' : '');
    if (el.type === 'binding') {
      div.className += ' studio-binding';
      div.textContent = `[${el.bindingType}] ${el.collection}`;
    } else if (el.type === 'html') {
      div.innerHTML = el.content || '';
    } else {
      div.textContent = el.content || el.type;
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
      select({ si, ei });
      renderCanvas();
      renderInspector();
    });
    return div;
  }

  function select(sel) { state.selected = sel; }

  function reorderSection(from, to) {
    pushUndo();
    const sections = state.canvas.sections;
    const [item] = sections.splice(from, 1);
    sections.splice(to, 0, item);
    saveActiveFile().catch(alertErr);
    renderCanvas();
  }

  function addElement(type) {
    if (!state.canvas) return;
    pushUndo();
    if (type === 'section') {
      state.canvas.sections.push({
        id: uid('section'),
        classes: ['studio-section'],
        layout: { type: 'grid', columns: 3, gap: '1rem' },
        responsive: { default: { columns: 1 }, tablet: { columns: 2 }, desktop: { columns: 3 } },
        rows: [{ id: uid('row'), columns: [{ id: uid('col'), width: '100%', elements: [] }] }],
      });
    } else {
      let section = state.canvas.sections[0];
      if (!section) { addElement('section'); section = state.canvas.sections[0]; }
      const col = section.rows[0].columns[0];
      const el = { id: uid('el'), type: 'text', tag: 'p', content: 'New text', classes: [], styles: {} };
      if (type === 'binding-loop') {
        el.type = 'binding';
        el.bindingType = 'loop';
        el.collection = state.collections[0] || 'blog';
        el.itemTemplate = '<article><h2>{title}</h2></article>';
      }
      col.elements.push(el);
    }
    saveActiveFile().catch(alertErr);
    renderCanvas();
  }

  function renderInspector() {
    const body = $('#studio-inspector-body');
    if (!state.selected || state.selected.ei === undefined) {
      body.innerHTML = '<p class="form-hint">Select an element</p>';
      return;
    }
    const section = state.canvas.sections[state.selected.si];
    const el = section?.rows?.[0]?.columns?.[0]?.elements?.[state.selected.ei];
    if (!el) return;
    body.innerHTML = `<p><strong>${esc(el.type)}</strong></p>
      <label>Content <input class="input" id="insp-content" value="${esc(el.content || '')}"></label>`;
    $('#insp-content')?.addEventListener('change', (e) => {
      pushUndo();
      el.content = e.target.value;
      saveActiveFile().catch(alertErr);
      renderCanvas();
    });
  }

  // ——— View modes ———
  function applyViewMode() {
    const v = state.viewMode;
    const visual = $('#studio-pane-visual');
    const code = $('#studio-pane-code');
    const panes = $('#studio-panes');
    panes.classList.toggle('split', v === 'split');
    visual.hidden = v === 'code';
    code.hidden = v === 'visual';
    if (v !== 'visual') code.hidden = false;
  }

  // ——— Tokens, blocks, sections ———
  async function loadTokens() {
    const data = await api('get_tokens', { project_id: state.project.id });
    $('#studio-tokens-editor').value = JSON.stringify(data.tokens || {}, null, 2);
  }

  async function loadBlocks() {
    const data = await api('list_studio_blocks');
    state.blocks = data.blocks || [];
    $('#studio-blocks').innerHTML = state.blocks.map((b) =>
      `<button type="button" class="studio-block-btn" data-block="${esc(b.id)}">${esc(b.label || b.id)}</button>`
    ).join('');
    $('#studio-blocks').querySelectorAll('[data-block]').forEach((btn) => {
      btn.addEventListener('click', () => insertBlock(btn.dataset.block));
    });
  }

  async function loadSections() {
    const data = await api('list_sections');
    $('#studio-section-lib').innerHTML = (data.sections || []).map((s) =>
      `<button type="button" class="studio-block-btn" data-section="${esc(s.id)}">${esc(s.label || s.id)}</button>`
    ).join('');
    $('#studio-section-lib').querySelectorAll('[data-section]').forEach((btn) => {
      btn.addEventListener('click', () => insertSection(btn.dataset.section));
    });
  }

  async function insertSection(sectionId) {
    if (!isStudioPage(state.activeTab)) return;
    await api('insert_section', {
      project_id: state.project.id,
      page_slug: pageSlugFromPath(state.activeTab),
      section_id: sectionId,
    });
    await activateTab(state.activeTab);
  }

  function insertBlock(blockId) {
    const block = state.blocks.find((b) => b.id === blockId);
    if (!block || !state.canvas) return;
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
  }

  async function loadCollections() {
    const data = await api('list_collections');
    state.collections = data.collections || [];
  }

  // ——— Init events ———
  function bindEvents() {
    $('#studio-new-project')?.addEventListener('click', () => { $('#studio-new-modal').hidden = false; });
    $$('[data-close-new]').forEach((el) => el.addEventListener('click', () => { $('#studio-new-modal').hidden = true; }));
    $('#studio-create-project')?.addEventListener('click', async () => {
      const name = $('#studio-new-name').value.trim();
      if (!name) return;
      const data = await api('create_project', { name, style_kit: $('#studio-new-kit').value });
      $('#studio-new-modal').hidden = true;
      await openProject(data.project.id);
      loadProjects();
    });
    $('#studio-back')?.addEventListener('click', () => {
      $('#studio-workspace').hidden = true;
      $('#studio-dashboard').hidden = false;
      loadProjects();
    });
    $('#studio-open-external')?.addEventListener('click', async () => {
      const data = await api('open_project_url', { project_id: state.project.id });
      window.open(data.url, '_blank', 'noopener,noreferrer');
    });
    $('#studio-publish')?.addEventListener('click', async () => {
      await saveActiveFile();
      await api('publish_project', { id: state.project.id });
      (window.showMessage || alert)('Published', 'success');
    });
    $('#studio-export')?.addEventListener('click', () => api('export_project', { id: state.project.id }).catch(alertErr));
    $('#studio-refresh-tree')?.addEventListener('click', () => loadFileTree().catch(alertErr));
    $$('.studio-view-btn').forEach((btn) => btn.addEventListener('click', () => {
      $$('.studio-view-btn').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      state.viewMode = btn.dataset.view;
      applyViewMode();
    }));
    $('#studio-breakpoint')?.addEventListener('change', (e) => {
      state.breakpoint = e.target.value;
      renderCanvas();
    });
    $('#studio-code-editor')?.addEventListener('input', () => {
      if (state.activeTab) markDirty(state.activeTab);
    });
    $('#studio-code-editor')?.addEventListener('blur', async () => {
      if (!state.activeTab) return;
      if (isStudioPage(state.activeTab)) {
        const html = $('#studio-code-editor').value;
        await api('parse_code', {
          project_id: state.project.id,
          page_slug: pageSlugFromPath(state.activeTab),
          html,
        });
        await activateTab(state.activeTab);
      } else {
        await saveActiveFile();
      }
    });
    $$('[data-add]').forEach((btn) => btn.addEventListener('click', () => addElement(btn.dataset.add)));
    $('#studio-undo')?.addEventListener('click', () => {
      const prev = state.undoStack.pop();
      if (prev) {
        state.canvas = JSON.parse(prev);
        saveActiveFile().catch(alertErr);
        renderCanvas();
      }
    });
    $('#studio-save-tokens')?.addEventListener('click', async () => {
      try {
        const tokens = JSON.parse($('#studio-tokens-editor').value);
        await api('save_tokens', { project_id: state.project.id, tokens });
        (window.showMessage || alert)('Tokens saved', 'success');
      } catch (e) { alertErr(e); }
    });
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        saveActiveFile().catch(alertErr);
      }
    });
  }

  async function init() {
    bindEvents();
    await loadProjects().catch(alertErr);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
