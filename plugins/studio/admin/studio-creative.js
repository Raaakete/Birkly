/**
 * Studio creative suite admin UI (P26) — timeline, popups, funnel, Figma import.
 */
(function () {
  'use strict';

  const IDE = () => window.StudioIDE;
  const $ = (s, root) => (root || document).querySelector(s);
  const $$ = (s, root) => Array.from((root || document).querySelectorAll(s));

  const creative = {
    funnel: null,
    selectedAnimId: null,
    selectedPopupId: null,
  };

  function ensureCreative(canvas) {
    if (!canvas.creative) {
      canvas.creative = { animations: [], popups: [] };
    }
    if (!Array.isArray(canvas.creative.animations)) canvas.creative.animations = [];
    if (!Array.isArray(canvas.creative.popups)) canvas.creative.popups = [];
    return canvas.creative;
  }

  function newId(prefix) {
    return prefix + '_' + Math.random().toString(36).slice(2, 9);
  }

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function selectedElementId() {
    const el = IDE()?.getSelectedElement?.();
    return el?.id || '';
  }

  async function loadFunnel() {
    const ide = IDE();
    if (!ide?.state?.project) return;
    try {
      const data = await ide.api('get_funnel', { project_id: ide.state.project.id });
      creative.funnel = data.funnel || { enabled: false, steps: [] };
    } catch (e) {
      creative.funnel = { enabled: false, steps: [] };
    }
    renderFunnelPanel();
  }

  async function saveFunnel() {
    const ide = IDE();
    if (!ide?.state?.project || !creative.funnel) return;
    await ide.api('save_funnel', { project_id: ide.state.project.id, funnel: creative.funnel });
    ide.toast?.('Funnel saved', 'ok');
  }

  function renderTimelinePanel() {
    const panel = $('#studio-timeline-body');
    if (!panel) return;
    const ide = IDE();
    const canvas = ide?.state?.canvas;
    if (!canvas) {
      panel.innerHTML = '<p class="form-hint">Open a page to edit animations.</p>';
      return;
    }
    const cr = ensureCreative(canvas);
    let html = '<p class="form-hint">Keyframes compile to <code>@keyframes</code> on publish. Triggers: scroll, click, load.</p>';
    html += '<button type="button" class="btn btn-secondary btn-sm" data-creative="add-anim">+ Animation</button>';
    html += '<ul class="studio-timeline-list">';
    cr.animations.forEach((anim) => {
      const sel = creative.selectedAnimId === anim.id ? ' active' : '';
      html += `<li class="studio-timeline-item${sel}" data-anim-id="${esc(anim.id)}">
        <strong>${esc(anim.name || anim.id)}</strong>
        <small>${esc(anim.trigger || 'scroll')} → ${esc(anim.targetId || '?')}</small>
      </li>`;
    });
    html += '</ul>';

    const anim = cr.animations.find((a) => a.id === creative.selectedAnimId);
    if (anim) {
      html += `<div class="studio-timeline-editor">
        <label>Name <input class="input" data-anim-field="name" value="${esc(anim.name || '')}"></label>
        <label>Target element ID <input class="input" data-anim-field="targetId" value="${esc(anim.targetId || '')}" placeholder="el-…"></label>
        <button type="button" class="btn btn-secondary btn-sm" data-creative="use-selected-el">Use selected element</button>
        <label>Trigger
          <select class="input" data-anim-field="trigger">
            <option value="scroll"${anim.trigger === 'scroll' ? ' selected' : ''}>Scroll into view</option>
            <option value="click"${anim.trigger === 'click' ? ' selected' : ''}>Click</option>
            <option value="load"${anim.trigger === 'load' ? ' selected' : ''}>Page load</option>
          </select>
        </label>
        <label>Click target ID <input class="input" data-anim-field="clickTargetId" value="${esc(anim.clickTargetId || '')}"></label>
        <label>Duration (ms) <input type="number" class="input" data-anim-field="duration" value="${anim.duration || 600}"></label>
        <label>Easing
          <select class="input" data-anim-field="easing">
            ${['ease-out', 'ease-in', 'ease-in-out', 'ease', 'linear'].map((e) => `<option value="${e}"${anim.easing === e ? ' selected' : ''}>${e}</option>`).join('')}
          </select>
        </label>
        <label>Keyframes JSON <textarea class="input studio-timeline-kf" data-anim-field="keyframesJson" rows="4">${esc(JSON.stringify(anim.keyframes || { '0%': { opacity: '0' }, '100%': { opacity: '1' } }, null, 2))}</textarea></label>
        <button type="button" class="btn btn-secondary btn-sm" data-creative="delete-anim">Delete animation</button>
      </div>`;
    }
    panel.innerHTML = html;
  }

  function renderPopupPanel() {
    const panel = $('#studio-popup-body');
    if (!panel) return;
    const ide = IDE();
    const canvas = ide?.state?.canvas;
    if (!canvas) {
      panel.innerHTML = '<p class="form-hint">Open a page to edit popups.</p>';
      return;
    }
    const cr = ensureCreative(canvas);
    let html = '<p class="form-hint">Popups support click, delay, and exit intent. Esc closes; focus is trapped.</p>';
    html += '<button type="button" class="btn btn-secondary btn-sm" data-creative="add-popup">+ Popup</button>';
    html += '<ul class="studio-popup-list">';
    cr.popups.forEach((pop) => {
      const sel = creative.selectedPopupId === pop.id ? ' active' : '';
      html += `<li class="studio-popup-list-item${sel}" data-popup-id="${esc(pop.id)}"><strong>${esc(pop.title || pop.id)}</strong> <small>${esc(pop.trigger || 'click')}</small></li>`;
    });
    html += '</ul>';

    const pop = cr.popups.find((p) => p.id === creative.selectedPopupId);
    if (pop) {
      html += `<div class="studio-popup-editor">
        <label>Title <input class="input" data-popup-field="title" value="${esc(pop.title || '')}"></label>
        <label>Body HTML <textarea class="input" data-popup-field="body" rows="3">${esc(pop.body || pop.content || '')}</textarea></label>
        <label>Trigger
          <select class="input" data-popup-field="trigger">
            <option value="click"${pop.trigger === 'click' ? ' selected' : ''}>Click</option>
            <option value="delay"${pop.trigger === 'delay' ? ' selected' : ''}>Delay</option>
            <option value="exit_intent"${pop.trigger === 'exit_intent' ? ' selected' : ''}>Exit intent</option>
          </select>
        </label>
        <label>Click target element ID <input class="input" data-popup-field="clickTargetId" value="${esc(pop.clickTargetId || '')}"></label>
        <button type="button" class="btn btn-secondary btn-sm" data-creative="use-selected-popup-el">Use selected element</button>
        <label>Delay (ms) <input type="number" class="input" data-popup-field="delayMs" value="${pop.delayMs || 0}"></label>
        <label>Aria label <input class="input" data-popup-field="ariaLabel" value="${esc(pop.ariaLabel || pop.title || '')}"></label>
        <button type="button" class="btn btn-secondary btn-sm" data-creative="delete-popup">Delete popup</button>
      </div>`;
    }
    panel.innerHTML = html;
  }

  function renderFunnelPanel() {
    const panel = $('#studio-funnel-body');
    if (!panel) return;
    const funnel = creative.funnel || { enabled: false, steps: [], title: 'Progress' };
    const pages = IDE()?.state?.project?.pages || [];
    let html = `<label class="studio-funnel-enable"><input type="checkbox" data-funnel="enabled"${funnel.enabled ? ' checked' : ''}> Enable funnel stepper on published pages</label>`;
    html += `<label>Title <input class="input" data-funnel="title" value="${esc(funnel.title || 'Progress')}"></label>`;
    html += '<button type="button" class="btn btn-secondary btn-sm" data-creative="add-funnel-step">+ Step</button>';
    html += '<ol class="studio-funnel-editor">';
    (funnel.steps || []).forEach((step, i) => {
      html += `<li class="studio-funnel-editor-step">
        <label>Page <select class="input" data-funnel-step="${i}" data-funnel-key="slug">
          ${pages.map((p) => `<option value="${esc(p.slug)}"${step.slug === p.slug || step.page === p.slug ? ' selected' : ''}>${esc(p.title || p.slug)}</option>`).join('')}
        </select></label>
        <label>Label <input class="input" data-funnel-step="${i}" data-funnel-key="label" value="${esc(step.label || '')}"></label>
        <button type="button" class="btn btn-secondary btn-sm" data-creative="remove-funnel-step" data-step="${i}">Remove</button>
      </li>`;
    });
    html += '</ol>';
    html += '<button type="button" class="btn btn-primary btn-sm" data-creative="save-funnel">Save funnel</button>';
    panel.innerHTML = html;
  }

  function persistCanvas() {
    const ide = IDE();
    if (!ide?.state?.activeTab || !ide.state.canvas) return;
    ide.markDirty(ide.state.activeTab);
    ide.saveActiveFile().catch(ide.alertErr);
  }

  function bindCreativeEvents() {
    const app = $('#studio-app');
    if (!app || app.dataset.creativeBound) return;
    app.dataset.creativeBound = '1';

    app.addEventListener('click', (e) => {
      const ide = IDE();
      if (!ide) return;

      if (e.target.closest('[data-creative="add-anim"]')) {
        const cr = ensureCreative(ide.state.canvas);
        const id = newId('anim');
        cr.animations.push({
          id,
          name: 'fadeUp',
          targetId: selectedElementId(),
          trigger: 'scroll',
          duration: 600,
          easing: 'ease-out',
          keyframes: { '0%': { opacity: '0', transform: 'translateY(20px)' }, '100%': { opacity: '1', transform: 'translateY(0)' } },
        });
        creative.selectedAnimId = id;
        renderTimelinePanel();
        persistCanvas();
        return;
      }

      const animItem = e.target.closest('[data-anim-id]');
      if (animItem) {
        creative.selectedAnimId = animItem.dataset.animId;
        renderTimelinePanel();
        return;
      }

      if (e.target.closest('[data-creative="delete-anim"]')) {
        const cr = ensureCreative(ide.state.canvas);
        cr.animations = cr.animations.filter((a) => a.id !== creative.selectedAnimId);
        creative.selectedAnimId = null;
        renderTimelinePanel();
        persistCanvas();
        return;
      }

      if (e.target.closest('[data-creative="use-selected-el"]')) {
        const cr = ensureCreative(ide.state.canvas);
        const anim = cr.animations.find((a) => a.id === creative.selectedAnimId);
        if (anim) {
          anim.targetId = selectedElementId();
          renderTimelinePanel();
          persistCanvas();
        }
        return;
      }

      if (e.target.closest('[data-creative="add-popup"]')) {
        const cr = ensureCreative(ide.state.canvas);
        const id = newId('popup');
        cr.popups.push({
          id,
          title: 'Dialog',
          body: '<p>Your message here</p>',
          trigger: 'click',
          clickTargetId: selectedElementId(),
          delayMs: 0,
          ariaLabel: 'Dialog',
        });
        creative.selectedPopupId = id;
        renderPopupPanel();
        persistCanvas();
        return;
      }

      const popItem = e.target.closest('[data-popup-id]');
      if (popItem) {
        creative.selectedPopupId = popItem.dataset.popupId;
        renderPopupPanel();
        return;
      }

      if (e.target.closest('[data-creative="delete-popup"]')) {
        const cr = ensureCreative(ide.state.canvas);
        cr.popups = cr.popups.filter((p) => p.id !== creative.selectedPopupId);
        creative.selectedPopupId = null;
        renderPopupPanel();
        persistCanvas();
        return;
      }

      if (e.target.closest('[data-creative="use-selected-popup-el"]')) {
        const cr = ensureCreative(ide.state.canvas);
        const pop = cr.popups.find((p) => p.id === creative.selectedPopupId);
        if (pop) {
          pop.clickTargetId = selectedElementId();
          renderPopupPanel();
          persistCanvas();
        }
        return;
      }

      if (e.target.closest('[data-creative="add-funnel-step"]')) {
        const pages = ide.state.project?.pages || [];
        const slug = pages[0]?.slug || 'index';
        if (!creative.funnel) creative.funnel = { enabled: false, steps: [] };
        creative.funnel.steps.push({ slug, label: 'Step ' + (creative.funnel.steps.length + 1) });
        renderFunnelPanel();
        return;
      }

      if (e.target.closest('[data-creative="remove-funnel-step"]')) {
        const idx = parseInt(e.target.closest('[data-creative="remove-funnel-step"]').dataset.step, 10);
        creative.funnel.steps.splice(idx, 1);
        renderFunnelPanel();
        return;
      }

      if (e.target.closest('[data-creative="save-funnel"]')) {
        saveFunnel();
        return;
      }

      if (e.target.closest('[data-action="open-figma-import"]')) {
        $('#studio-figma-modal').hidden = false;
        return;
      }

      if (e.target.closest('[data-action="close-figma-import"]')) {
        $('#studio-figma-modal').hidden = true;
        return;
      }

      if (e.target.closest('[data-action="run-figma-import"]')) {
        runFigmaImport();
        return;
      }
    });

    app.addEventListener('change', (e) => {
      const ide = IDE();
      if (!ide?.state?.canvas) return;

      const animField = e.target.closest('[data-anim-field]');
      if (animField && creative.selectedAnimId) {
        const cr = ensureCreative(ide.state.canvas);
        const anim = cr.animations.find((a) => a.id === creative.selectedAnimId);
        if (!anim) return;
        const key = animField.dataset.animField;
        if (key === 'keyframesJson') {
          try {
            anim.keyframes = JSON.parse(animField.value);
          } catch (err) {
            ide.toast?.('Invalid keyframes JSON', 'error');
          }
        } else if (key === 'duration') {
          anim[key] = parseInt(animField.value, 10) || 600;
        } else {
          anim[key] = animField.value;
        }
        persistCanvas();
        return;
      }

      const popField = e.target.closest('[data-popup-field]');
      if (popField && creative.selectedPopupId) {
        const cr = ensureCreative(ide.state.canvas);
        const pop = cr.popups.find((p) => p.id === creative.selectedPopupId);
        if (!pop) return;
        const key = popField.dataset.popupField;
        if (key === 'delayMs') {
          pop[key] = parseInt(popField.value, 10) || 0;
        } else {
          pop[key] = popField.value;
        }
        persistCanvas();
        return;
      }

      if (e.target.matches('[data-funnel="enabled"]')) {
        if (!creative.funnel) creative.funnel = { steps: [] };
        creative.funnel.enabled = e.target.checked;
        return;
      }

      const funnelTitle = e.target.closest('[data-funnel="title"]');
      if (funnelTitle) {
        if (!creative.funnel) creative.funnel = { steps: [] };
        creative.funnel.title = funnelTitle.value;
        return;
      }

      const stepField = e.target.closest('[data-funnel-step]');
      if (stepField && creative.funnel) {
        const i = parseInt(stepField.dataset.funnelStep, 10);
        const key = stepField.dataset.funnelKey;
        if (creative.funnel.steps[i]) {
          creative.funnel.steps[i][key] = stepField.value;
        }
      }
    });

    app.addEventListener('input', (e) => {
      const ide = IDE();
      if (!ide?.state?.canvas) return;
      const animField = e.target.closest('[data-anim-field]');
      if (animField && creative.selectedAnimId && animField.dataset.animField !== 'keyframesJson') {
        const cr = ensureCreative(ide.state.canvas);
        const anim = cr.animations.find((a) => a.id === creative.selectedAnimId);
        if (anim) {
          anim[animField.dataset.animField] = animField.value;
        }
      }
      const popField = e.target.closest('[data-popup-field]');
      if (popField && creative.selectedPopupId) {
        const cr = ensureCreative(ide.state.canvas);
        const pop = cr.popups.find((p) => p.id === creative.selectedPopupId);
        if (pop) {
          pop[popField.dataset.popupField] = popField.value;
        }
      }
    });
  }

  async function runFigmaImport() {
    const ide = IDE();
    const ta = $('#studio-figma-json');
    if (!ide?.state?.project || !ta) return;
    let json;
    try {
      json = JSON.parse(ta.value);
    } catch (err) {
      ide.toast?.('Invalid JSON', 'error');
      return;
    }
    try {
      const data = await ide.api('import_figma', {
        project_id: ide.state.project.id,
        page_slug: ide.pageSlugFromPath(ide.state.activeTab),
        json,
      });
      if (data.canvas) {
        ide.state.canvas = data.canvas;
        ide.renderCanvas();
        ide.renderInspector();
      }
      const warnings = (data.warnings || []).join('; ');
      ide.toast?.('Figma import: ' + (data.sections_added || 0) + ' section(s)' + (warnings ? ' — ' + warnings : ''), 'ok');
      $('#studio-figma-modal').hidden = true;
      persistCanvas();
    } catch (err) {
      ide.alertErr(err);
    }
  }

  function patchOpenProject() {
    const ide = IDE();
    if (!ide || ide._creativePatchedOpen) return;
    ide._creativePatchedOpen = true;
    const orig = ide.renderCanvas;
    ide.renderCanvas = function renderCanvasCreative(...args) {
      const out = orig.apply(ide, args);
      if (ide.state?.project) {
        loadFunnel();
        renderTimelinePanel();
        renderPopupPanel();
      }
      return out;
    };
  }

  function patchInspectorTab() {
    /* creative tab handled via studio-ide.js renderInspector + StudioCreative.renderCreativeInspector */
  }

  function renderCreativeInspector() {
    const body = $('#studio-inspector-body');
    if (!body) return;
    const el = IDE()?.getSelectedElement?.();
    const cr = ensureCreative(IDE().state.canvas);
    let html = '<p class="form-hint">Quick-add animation for selected element.</p>';
    if (el?.id) {
      html += `<p>Selected: <code>${esc(el.id)}</code></p>`;
      html += '<button type="button" class="btn btn-secondary btn-sm" data-creative="add-anim">Add scroll animation</button>';
      html += '<button type="button" class="btn btn-secondary btn-sm" data-creative="add-popup">Add click popup</button>';
    } else {
      html += '<p class="form-hint">Select an element first, or use the Timeline / Popups panels.</p>';
    }
    html += `<p class="form-hint">${cr.animations.length} animation(s), ${cr.popups.length} popup(s) on this page.</p>`;
    body.innerHTML = html;
  }

  function init() {
    if (!IDE()) {
      setTimeout(init, 50);
      return;
    }
    bindCreativeEvents();
    patchOpenProject();
    patchInspectorTab();
    renderTimelinePanel();
    renderPopupPanel();
    loadFunnel();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.StudioCreative = { renderTimelinePanel, renderPopupPanel, renderFunnelPanel, renderCreativeInspector };
})();
