/**
 * Studio IDE (P23) — filesystem, tabs, visual/code/split, canvas v2.
 */
(function () {
  'use strict';

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
    busy: false,
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
    restoreTabs(id);
    $('#studio-dashboard').hidden = true;
    $('#studio-workspace').hidden = false;
    $('#studio-project-name').textContent = state.project.name;
    await Promise.all([loadFileTree(), loadBlocks(), loadSections(), loadTokens(), loadCollections()]);
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
      $('#studio-code-editor').value = data.html || '';
      renderCanvas();
      renderLayers();
      renderInspector();
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
  function getSelectedElement() {
    if (!state.selected || state.selected.ei === undefined || !state.canvas) return null;
    const sec = state.canvas.sections[state.selected.si];
    return sec?.rows?.[0]?.columns?.[0]?.elements?.[state.selected.ei] ?? null;
  }

  function getSelectedSection() {
    if (!state.selected || state.selected.si === undefined || !state.canvas) return null;
    return state.canvas.sections[state.selected.si] ?? null;
  }

  function select(sel) {
    state.selected = sel;
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
      const selSec = state.selected?.si === si && state.selected.ei === undefined;
      sec.className = 'studio-section-block' + (selSec ? ' selected' : '');
      sec.draggable = true;
      sec.dataset.si = si;
      const layout = section.responsive?.[bp] || section.layout || {};
      const cols = layout.columns || section.layout?.columns || 1;
      sec.innerHTML = `<div class="studio-section-label">Section ${si + 1} · ${cols} col · ${bp}</div>`;
      const rowWrap = document.createElement('div');
      rowWrap.className = 'studio-row-preview';
      rowWrap.style.display = 'grid';
      rowWrap.style.gridTemplateColumns = `repeat(${cols}, minmax(0, 1fr))`;
      rowWrap.style.gap = layout.gap || section.layout?.gap || '1rem';
      (section.rows || []).forEach((row) => {
        (row.columns || []).forEach((col) => {
          const colEl = document.createElement('div');
          colEl.className = 'studio-col-preview';
          (col.elements || []).forEach((el, ei) => {
            colEl.appendChild(renderElPreview(el, si, ei));
          });
          rowWrap.appendChild(colEl);
        });
      });
      sec.appendChild(rowWrap);
      sec.addEventListener('click', (e) => {
        if (e.target.closest('.studio-el')) return;
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

  function renderElPreview(el, si, ei) {
    const div = document.createElement('div');
    const sel = state.selected?.si === si && state.selected?.ei === ei;
    div.className = 'studio-el' + (sel ? ' selected' : '');
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
      select({ si, ei });
    });
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
      const col = section.rows[0].columns[0];
      const el = { id: uid('el'), type: 'text', tag: 'p', content: 'New text', classes: [], styles: {} };
      if (type === 'heading') { el.tag = 'h2'; el.content = 'Heading'; }
      else if (type === 'image') { el.type = 'image'; el.src = '/admin/assets/birkly-icon.svg'; el.alt = ''; }
      else if (type === 'button') { el.type = 'button'; el.content = 'Click me'; el.href = '#'; }
      else if (type === 'spacer') { el.type = 'spacer'; el.height = 32; }
      else if (type === 'divider') { el.type = 'divider'; }
      else if (type === 'html') { el.type = 'html'; el.content = '<p>Custom HTML</p>'; }
      else if (type === 'binding-loop') {
        Object.assign(el, { type: 'binding', bindingType: 'loop', collection: state.collections[0] || 'blog', itemTemplate: '<article><h2>{title}</h2><p>{excerpt}</p></article>' });
      } else if (type === 'binding-filtered') {
        Object.assign(el, { type: 'binding', bindingType: 'filtered', collection: state.collections[0] || 'blog', filterField: 'status', filterValue: 'published', itemTemplate: '<article><h2>{title}</h2></article>' });
      } else if (type === 'binding-single') {
        Object.assign(el, { type: 'binding', bindingType: 'single', collection: state.collections[0] || 'blog', slug: 'example', itemTemplate: '<div><h2>{title}</h2></div>' });
      } else if (type === 'binding-site') {
        Object.assign(el, { type: 'binding', bindingType: 'site_element', collection: 'site_elements', slug: 'hero', itemTemplate: '<div>{title}</div>' });
      }
      col.elements.push(el);
      select({ si: state.selected?.si ?? 0, ei: col.elements.length - 1 });
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
    const cols = (section.layout?.columns || 1) + 1;
    section.layout = section.layout || { type: 'grid', columns: 1, gap: '1rem' };
    section.layout.columns = cols;
    if (section.responsive) {
      Object.keys(section.responsive).forEach((bp) => {
        section.responsive[bp].columns = Math.min(cols, (section.responsive[bp].columns || 1) + 1);
      });
    }
    section.rows[0].columns.push({ id: uid('col'), width: Math.floor(100 / cols) + '%', elements: [] });
    saveActiveFile().catch(alertErr);
    renderCanvas();
    renderInspector();
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
      const secSel = state.selected?.si === si && state.selected.ei === undefined;
      html += `<button type="button" class="studio-layer-btn${secSel ? ' active' : ''}" data-action="select-layer" data-si="${si}">📦 Section ${si + 1}</button>`;
      (sec.rows?.[0]?.columns?.[0]?.elements || []).forEach((item, ei) => {
        const elSel = state.selected?.si === si && state.selected.ei === ei;
        html += `<button type="button" class="studio-layer-btn studio-layer-el${elSel ? ' active' : ''}" data-action="select-layer" data-si="${si}" data-ei="${ei}">↳ ${esc(item.type)}</button>`;
      });
    });
    el.innerHTML = html || '<p class="form-hint">No layers</p>';
  }

  function renderInspector() {
    const body = $('#studio-inspector-body');
    if (!body) return;
    const el = getSelectedElement();
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
        if (el.bindingType === 'filtered') {
          html += field('Filter field', 'filterField', el.filterField);
          html += field('Filter value', 'filterValue', el.filterValue);
        }
        if (el.bindingType === 'single' || el.bindingType === 'site_element') {
          html += field('Entry slug', 'slug', el.slug);
        }
      }
      if (el.type === 'spacer') html += field('Height (px)', 'height', el.height);
      html += field('Classes', 'classesStr', (el.classes || []).join(' '));
      html += field('Text align', 'textAlign', el.styles?.textAlign || '');
      html += field('Color', 'color', el.styles?.color || '');
      html += field('Font size', 'fontSize', el.styles?.fontSize || '');
      html += field('Padding', 'padding', el.styles?.padding || '');
      html += `<button type="button" class="btn btn-secondary btn-sm" data-action="delete-element">Delete element</button>`;
      body.innerHTML = html;
      bindInspector(el);
      return;
    }

    if (section) {
      const layout = section.layout || {};
      let html = `<p><strong>Section ${(state.selected?.si ?? 0) + 1}</strong></p>`;
      html += field('Columns', 'columns', layout.columns || 1);
      html += field('Gap', 'gap', layout.gap || '1rem');
      html += field('Classes', 'classesStr', (section.classes || []).join(' '));
      html += `<button type="button" class="btn btn-secondary btn-sm" data-action="delete-section">Delete section</button>`;
      body.innerHTML = html;
      bindSectionInspector(section);
      return;
    }

    body.innerHTML = '<p class="form-hint">Select a section or element on the canvas or in Layers.</p>';
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
        } else if (['textAlign', 'color', 'fontSize', 'padding'].includes(key)) {
          el.styles = el.styles || {};
          el.styles[key] = input.value;
        } else if (key === 'height') {
          el.height = parseInt(input.value, 10) || 24;
        } else {
          el[key] = input.value;
        }
        markDirty(state.activeTab);
        saveActiveFile().catch(alertErr);
        renderCanvas();
      };
      input.addEventListener('change', handler);
      if (input.tagName === 'INPUT') input.addEventListener('input', handler);
    });
  }

  function bindSectionInspector(section) {
    $('#studio-inspector-body')?.querySelectorAll('.studio-insp-field').forEach((input) => {
      input.addEventListener('change', () => {
        pushUndo();
        const key = input.dataset.insp;
        if (key === 'classesStr') {
          section.classes = input.value.split(/\s+/).filter(Boolean);
        } else {
          section.layout = section.layout || { type: 'grid', columns: 1, gap: '1rem' };
          section.layout[key] = key === 'columns' ? parseInt(input.value, 10) || 1 : input.value;
        }
        markDirty(state.activeTab);
        saveActiveFile().catch(alertErr);
        renderCanvas();
      });
    });
  }

  function deleteSelectedElement() {
    const el = getSelectedElement();
    if (!el || !state.canvas || state.selected?.ei === undefined) return;
    pushUndo();
    const sec = state.canvas.sections[state.selected.si];
    sec.rows[0].columns[0].elements.splice(state.selected.ei, 1);
    state.selected = { si: state.selected.si };
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
          const label = data.published ? 'Live site' : 'Draft preview (admin only)';
          setStatus(label + ': ' + url, 'info');
          if (data.route_ready === false) {
            const hints = Array.isArray(data.route_hints) ? data.route_hints.join(' ') : '';
            toast('Server routing not ready — /site/ may 404. ' + hints, 'error');
          }
          const w = window.open(url, '_blank', 'noopener,noreferrer');
          if (!w) {
            toast('Popup blocked. URL: ' + url, 'error');
            try {
              await navigator.clipboard.writeText(url);
              toast('URL copied to clipboard', 'success');
            } catch (_) { /* ignore */ }
          }
        });
        break;
      case 'publish':
        withBusy(async () => {
          await saveActiveFile();
          const data = await api('publish_project', { id: state.project.id });
          if (data.project) state.project = data.project;
          const url = absUrl(data.public_url || '/site/' + (state.project.slug || '') + '/');
          if (data.route_ready === false) {
            const hints = Array.isArray(data.route_hints) ? data.route_hints.join(' ') : '';
            toast('Published (files saved) but /site/ may 404 until server routing is deployed. ' + hints, 'error');
          } else {
            toast('Published! ' + url, 'success');
          }
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
        if (prev) {
          state.canvas = JSON.parse(prev);
          saveActiveFile().catch(alertErr);
          renderCanvas();
          renderLayers();
        }
        break;
      }
      case 'add-column':
        addColumn();
        break;
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
})();
