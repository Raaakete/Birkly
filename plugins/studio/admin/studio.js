/**
 * Studio admin editor (P22-M4–M8).
 */
(function () {
  'use strict';

  const API = (window.birklyUrl || (p => p))('/api/index.php?route=studio');
  const CMS_URL = window.location.origin + (window.BIRKLY_BASE_PATH || '');

  const state = {
    projects: [],
    project: null,
    pageSlug: 'index',
    canvas: null,
    selected: null,
    collections: [],
  };

  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => document.querySelectorAll(sel);

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

  function uid(prefix) {
    return prefix + '-' + Math.random().toString(36).slice(2, 8);
  }

  async function loadProjects() {
    const data = await api('list_projects');
    state.projects = data.projects || [];
    renderProjectList();
  }

  function renderProjectList() {
    const el = $('#studio-project-list');
    if (!state.projects.length) {
      el.innerHTML = '<p class="form-hint">No projects yet. Create one to get started.</p>';
      return;
    }
    el.innerHTML = state.projects.map((p) => `
      <div class="studio-project-card" data-id="${p.id}">
        <div>
          <h4>${esc(p.name)}</h4>
          <div class="meta">/${esc(p.slug)} · ${p.published ? 'Published' : 'Draft'} · ${esc(p.style_kit || 'minimal')}</div>
        </div>
        <button type="button" class="btn btn-primary" data-open="${p.id}">Open</button>
        <button type="button" class="btn btn-secondary" data-dup="${p.id}">Duplicate</button>
        <button type="button" class="btn btn-secondary" data-del="${p.id}">Delete</button>
      </div>
    `).join('');
    el.querySelectorAll('[data-open]').forEach((btn) => {
      btn.addEventListener('click', () => openProject(btn.dataset.open));
    });
    el.querySelectorAll('[data-dup]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        await api('duplicate_project', { id: btn.dataset.dup, name: 'Copy' });
        loadProjects();
      });
    });
    el.querySelectorAll('[data-del]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!confirm('Delete this project?')) return;
        await api('delete_project', { id: btn.dataset.del });
        loadProjects();
      });
    });
  }

  async function openProject(id) {
    const data = await api('get_project', { id });
    state.project = data.project;
    state.pageSlug = state.project.home_page || 'index';
    $('#studio-dashboard-view').hidden = true;
    $('#studio-editor-view').hidden = false;
    $('#studio-project-title').textContent = state.project.name;
    await loadCollections();
    await loadPage();
    renderPageSelect();
    const cssData = await api('get_custom_css', { project_id: id });
    $('#studio-custom-css').value = cssData.css || '';
  }

  async function loadCollections() {
    const data = await api('list_collections');
    state.collections = data.collections || [];
  }

  function renderPageSelect() {
    const sel = $('#studio-page-select');
    const pages = state.project?.pages || [];
    sel.innerHTML = pages.map((p) => `<option value="${esc(p.slug)}"${p.slug === state.pageSlug ? ' selected' : ''}>${esc(p.title || p.slug)}</option>`).join('');
  }

  async function loadPage() {
    const data = await api('get_page', { project_id: state.project.id, page_slug: state.pageSlug });
    state.canvas = data.canvas;
    $('#studio-code-editor').value = data.html || '';
    renderCanvas();
  }

  async function savePage() {
    await api('save_page', {
      project_id: state.project.id,
      page_slug: state.pageSlug,
      canvas: state.canvas,
    });
    const compiled = await api('compile_page', { project_id: state.project.id, page_slug: state.pageSlug });
    $('#studio-code-editor').value = compiled.html || '';
  }

  function renderCanvas() {
    const root = $('#studio-canvas');
    root.innerHTML = '';
    (state.canvas?.sections || []).forEach((section, si) => {
      const sec = document.createElement('div');
      sec.className = 'studio-section-block' + (state.selected?.type === 'section' && state.selected.si === si ? ' selected' : '');
      sec.dataset.si = si;
      sec.innerHTML = `<div class="form-hint">Section ${si + 1} · ${section.layout?.type || 'grid'} · ${section.layout?.columns || 1} col</div>`;
      (section.rows || []).forEach((row, ri) => {
        (row.columns || []).forEach((col, ci) => {
          (col.elements || []).forEach((el, ei) => {
            sec.appendChild(renderElementPreview(el, si, ri, ci, ei));
          });
        });
      });
      sec.addEventListener('click', (e) => {
        e.stopPropagation();
        select({ type: 'section', si });
        renderCanvas();
        renderInspector();
      });
      root.appendChild(sec);
    });
  }

  function renderElementPreview(el, si, ri, ci, ei) {
    const div = document.createElement('div');
    const sel = state.selected?.type === 'element' && state.selected.si === si && state.selected.ei === ei;
    div.className = 'studio-el' + (sel ? ' selected' : '');
    div.dataset.si = si;
    div.dataset.ei = ei;
    if (el.type === 'binding') {
      div.className += ' studio-binding';
      div.textContent = `[${el.bindingType || 'loop'}] ${el.collection || ''}`;
    } else if (el.type === 'html') {
      div.innerHTML = el.content || '<em>HTML</em>';
    } else if (el.type === 'image') {
      div.innerHTML = `<img src="${esc(el.src || '')}" alt="${esc(el.alt || '')}" style="max-width:120px">`;
    } else {
      const tag = el.tag || 'p';
      div.innerHTML = `<${tag}>${esc(el.content || '')}</${tag}>`;
    }
    div.addEventListener('click', (e) => {
      e.stopPropagation();
      select({ type: 'element', si, ri, ci, ei });
      renderCanvas();
      renderInspector();
    });
    return div;
  }

  function select(target) {
    state.selected = target;
  }

  function getSelectedElement() {
    if (!state.selected || state.selected.type !== 'element') return null;
    const { si, ri, ci, ei } = state.selected;
    return state.canvas?.sections?.[si]?.rows?.[0]?.columns?.[0]?.elements?.[ei] ?? null;
  }

  function renderInspector() {
    const empty = $('#studio-inspector-empty');
    const fields = $('#studio-inspector-fields');
    const el = getSelectedElement();
    if (!el) {
      empty.hidden = false;
      fields.hidden = true;
      return;
    }
    empty.hidden = true;
    fields.hidden = false;
    let html = `<p><strong>${esc(el.type)}</strong></p>`;
    if (el.type === 'text' || el.type === 'button') {
      html += labelInput('Content', 'content', el.content);
      html += labelInput('Tag', 'tag', el.tag || 'p');
    }
    if (el.type === 'image') {
      html += labelInput('Src', 'src', el.src);
      html += labelInput('Alt', 'alt', el.alt);
    }
    if (el.type === 'binding') {
      html += labelSelect('Collection', 'collection', state.collections, el.collection);
      html += labelInput('Item template', 'itemTemplate', el.itemTemplate);
      html += labelSelect('Binding type', 'bindingType', ['loop', 'filtered', 'single', 'site_element'], el.bindingType);
    }
    html += labelInput('Classes (space-separated)', 'classesStr', (el.classes || []).join(' '));
    fields.innerHTML = html;
    fields.querySelectorAll('[data-field]').forEach((input) => {
      input.addEventListener('change', () => applyInspector(el, input));
      input.addEventListener('input', () => applyInspector(el, input));
    });
  }

  function labelInput(label, field, value) {
    return `<label>${label}<input class="input" data-field="${field}" value="${esc(value || '')}"></label>`;
  }

  function labelSelect(label, field, options, value) {
    const opts = options.map((o) => {
      const v = typeof o === 'string' ? o : o;
      return `<option value="${esc(v)}"${v === value ? ' selected' : ''}>${esc(v)}</option>`;
    }).join('');
    return `<label>${label}<select class="input" data-field="${field}">${opts}</select></label>`;
  }

  function applyInspector(el, input) {
    const field = input.dataset.field;
    if (field === 'classesStr') {
      el.classes = input.value.split(/\s+/).filter(Boolean);
    } else {
      el[field] = input.value;
    }
    savePage().catch(alertError);
    renderCanvas();
  }

  function addElement(type) {
    if (!state.canvas) return;
    if (type === 'section') {
      state.canvas.sections.push({
        id: uid('section'),
        classes: ['studio-section'],
        layout: { type: 'grid', columns: 1, gap: '1.5rem' },
        rows: [{ id: uid('row'), columns: [{ id: uid('col'), width: '100%', elements: [] }] }],
      });
      savePage().catch(alertError);
      renderCanvas();
      return;
    }
    const section = state.canvas.sections[0];
    if (!section) {
      addElement('section');
      return addElement(type);
    }
    const col = section.rows[0].columns[0];
    const el = { id: uid('el'), type: 'text', tag: 'p', content: 'New text', classes: [], styles: {} };
    if (type === 'text') { /* default */ }
    else if (type === 'image') { el.type = 'image'; el.src = '/admin/assets/birkly-icon.svg'; el.alt = ''; }
    else if (type === 'button') { el.type = 'button'; el.content = 'Click'; el.href = '#'; }
    else if (type === 'spacer') { el.type = 'spacer'; el.height = 24; }
    else if (type === 'divider') { el.type = 'divider'; }
    else if (type === 'html') { el.type = 'html'; el.content = '<p>Custom HTML</p>'; }
    else if (type === 'binding-loop') {
      el.type = 'binding';
      el.bindingType = 'loop';
      el.collection = state.collections[0] || 'blog';
      el.itemTemplate = '<article><h2>{title}</h2><p>{excerpt}</p></article>';
    } else if (type === 'binding-filtered') {
      el.type = 'binding';
      el.bindingType = 'filtered';
      el.collection = state.collections[0] || 'blog';
      el.filterField = 'status';
      el.filterValue = 'published';
      el.itemTemplate = '<article><h2>{title}</h2></article>';
    } else if (type === 'binding-site') {
      el.type = 'binding';
      el.bindingType = 'site_element';
      el.collection = 'site_elements';
      el.slug = 'hero';
      el.itemTemplate = '<div>{title}</div>';
    }
    col.elements.push(el);
    select({ type: 'element', si: 0, ri: 0, ci: 0, ei: col.elements.length - 1 });
    savePage().catch(alertError);
    renderCanvas();
    renderInspector();
  }

  function esc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
  }

  function alertError(e) {
    alert(e.message || String(e));
  }

  function switchTab(tab) {
    $$('.studio-tab').forEach((t) => t.classList.toggle('active', t.dataset.tab === tab));
    $('#studio-visual-panel').hidden = tab !== 'visual';
    $('#studio-code-panel').hidden = tab !== 'code';
    $('#studio-styles-panel').hidden = tab !== 'styles';
  }

  async function openPreview() {
    const data = await api('preview_url', { project_id: state.project.id, page_slug: state.pageSlug });
    const frame = $('#studio-preview-frame');
    const doc = `<!DOCTYPE html><html><head><meta charset="utf-8"><script src="${CMS_URL}/birkly-client.js" data-cms-url="${CMS_URL}"></script></head><body>${data.preview_html || ''}</body></html>`;
    frame.srcdoc = doc;
    $('#studio-preview-modal').hidden = false;
  }

  // Events
  $('#studio-new-project')?.addEventListener('click', () => { $('#studio-new-modal').hidden = false; });
  $$('[data-close-new]').forEach((el) => el.addEventListener('click', () => { $('#studio-new-modal').hidden = true; }));
  $('#studio-create-project')?.addEventListener('click', async () => {
    const name = $('#studio-new-name').value.trim();
    const kit = $('#studio-new-kit').value;
    if (!name) return alert('Name required');
    const data = await api('create_project', { name, style_kit: kit });
    $('#studio-new-modal').hidden = true;
    await openProject(data.project.id);
    loadProjects();
  });

  $('#studio-back')?.addEventListener('click', () => {
    $('#studio-editor-view').hidden = true;
    $('#studio-dashboard-view').hidden = false;
    loadProjects();
  });

  $('#studio-page-select')?.addEventListener('change', async (e) => {
    state.pageSlug = e.target.value;
    await loadPage();
  });

  $('#studio-add-page')?.addEventListener('click', async () => {
    const title = prompt('Page title', 'New page');
    if (!title) return;
    await api('add_page', { project_id: state.project.id, title });
    const proj = await api('get_project', { id: state.project.id });
    state.project = proj.project;
    state.pageSlug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-');
    renderPageSelect();
    await loadPage();
  });

  $$('.studio-tab').forEach((tab) => tab.addEventListener('click', () => switchTab(tab.dataset.tab)));
  $$('.studio-palette-btn').forEach((btn) => btn.addEventListener('click', () => addElement(btn.dataset.add)));

  $('#studio-apply-code')?.addEventListener('click', async () => {
    const html = $('#studio-code-editor').value;
    const data = await api('parse_code', { project_id: state.project.id, page_slug: state.pageSlug, html });
    state.canvas = data.canvas;
    renderCanvas();
    switchTab('visual');
  });

  $('#studio-save-css')?.addEventListener('click', async () => {
    await api('save_custom_css', { project_id: state.project.id, css: $('#studio-custom-css').value });
    alert('CSS saved');
  });

  $('#studio-preview')?.addEventListener('click', () => openPreview().catch(alertError));
  $('#studio-publish')?.addEventListener('click', async () => {
    await savePage();
    const data = await api('publish_project', { id: state.project.id });
    state.project = data.project;
    alert('Published! SSR: /site/' + state.project.slug + '/');
  });

  $('#studio-export')?.addEventListener('click', () => api('export_project', { id: state.project.id }).catch(alertError));

  $$('[data-close-preview]').forEach((el) => el.addEventListener('click', () => { $('#studio-preview-modal').hidden = true; }));

  loadProjects().catch(alertError);
})();
