/**
 * Dedicated Automation editor page — full-canvas graph builder.
 * Route: ?page=automation-editor&id=... | &new=1
 */
(function () {
  'use strict';

  const apiUrl = (path) => (window.Birkly && typeof Birkly.url === 'function') ? Birkly.url(path) : path;

  function adminPageUrl(page, params) {
    const base = apiUrl('/admin/index.php');
    const qs = new URLSearchParams(Object.assign({ page: page }, params || {}));
    return base + '?' + qs.toString();
  }

  let nodeEditor = null;
  let availableBlocks = { triggers: {}, conditions: {}, actions: {} };
  let availableCollections = [];
  let currentId = null;
  let dirty = false;

  function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function showMessage(message, type) {
    if (window.Birkly && typeof Birkly.notify === 'function') {
      Birkly.notify(message, type);
      return;
    }
    const status = document.getElementById('automation-editor-status');
    if (status) {
      status.textContent = message;
      status.className = 'automation-editor-subhint is-' + (type || 'info');
    }
  }

  function setStatus(text, kind) {
    const status = document.getElementById('automation-editor-status');
    if (!status) return;
    status.textContent = text;
    status.className = 'automation-editor-subhint' + (kind ? ' is-' + kind : '');
  }

  function syncValidation() {
    const box = document.getElementById('canvas-validation-errors');
    if (!box || !nodeEditor) return;
    const errors = nodeEditor.validate();
    if (!errors.length) {
      box.style.display = 'none';
      box.innerHTML = '';
      return;
    }
    box.style.display = 'block';
    box.innerHTML = '<strong>Validation:</strong><ul>' +
      errors.map((e) => '<li>' + escapeHtml(e) + '</li>').join('') + '</ul>';
  }

  function markDirty() {
    dirty = true;
    syncValidation();
    setStatus('Unsaved changes', 'warn');
  }

  function updateBreadcrumb(name) {
    const label = String(name || '').trim() || 'New automation';
    const selectors = [
      '#admin-topbar-breadcrumbs .current',
      '#admin-topbar-breadcrumbs .breadcrumb-segment--current',
      '.plugin-page-header .breadcrumbs .current',
      '.page-header .breadcrumbs .current',
      '.breadcrumbs .current'
    ];
    const seen = new Set();
    selectors.forEach((sel) => {
      document.querySelectorAll(sel).forEach((el) => {
        if (seen.has(el)) return;
        seen.add(el);
        el.textContent = label;
      });
    });
  }

  function initEditor() {
    if (typeof AutomationNodeEditor === 'undefined') {
      setStatus('Node editor failed to load.', 'error');
      return;
    }
    const canvas = document.getElementById('node-canvas');
    const palette = document.getElementById('node-palette');
    if (!canvas || !palette) return;

    nodeEditor = new AutomationNodeEditor({
      container: canvas,
      palette,
      inspector: document.getElementById('node-inspector'),
      blocks: availableBlocks,
      collections: availableCollections,
      onChange: markDirty
    });
  }

  async function loadBlocks() {
    try {
      const response = await fetch(apiUrl('/api/automation.php?action=get_blocks'));
      const result = await response.json();
      if (result.status === 'ok') {
        availableBlocks = result.data.blocks || availableBlocks;
        if (nodeEditor) nodeEditor.setBlocks(availableBlocks);
      }
    } catch (e) {
      console.warn('Failed to load automation blocks', e);
    }
  }

  async function loadCollections() {
    try {
      const response = await fetch(apiUrl('/api/automation.php?action=get_collections'));
      const result = await response.json();
      if (result.status === 'ok') {
        availableCollections = result.data.collections || [];
        if (nodeEditor) nodeEditor.setCollections(availableCollections);
      }
    } catch (e) {
      console.warn('Failed to load collections', e);
    }
  }

  async function loadAutomation(id) {
    const response = await fetch(apiUrl('/api/automation.php?action=get&automation_id=' + encodeURIComponent(id)));
    const result = await response.json();
    if (result.status !== 'ok') {
      throw new Error(result.message || 'Automation not found');
    }
    return result.data.automation;
  }

  function applyAutomationToUi(automation) {
    currentId = automation.id || null;
    const nameEl = document.getElementById('automation-editor-name');
    const enabledEl = document.getElementById('automation-editor-enabled');
    const stopEl = document.getElementById('automation-editor-stop-on-failure');
    const testBtn = document.getElementById('automation-editor-test');

    if (nameEl) nameEl.value = automation.name || '';
    if (enabledEl) enabledEl.checked = automation.enabled !== false;
    if (stopEl) stopEl.checked = !!automation.stop_on_failure;
    if (testBtn) testBtn.hidden = !currentId;

    updateBreadcrumb(automation.name || 'New automation');
    if (!nodeEditor) initEditor();
    nodeEditor.loadFromWorkflow(automation);
    dirty = false;
    syncValidation();
    setStatus(currentId ? 'Editing “' + (automation.name || currentId) + '”' : 'New automation', 'ok');
  }

  const EDITOR_TEMPLATES = {
    notify_entry_published: {
      name: 'Notify on Entry Published',
      enabled: true,
      stop_on_failure: false,
      trigger: { type: 'status_changed', config: { to: 'published' } },
      conditions: [],
      actions: [{
        type: 'email',
        config: {
          to: '',
          subject: '{{automation.name}}',
          body: '{{trigger_summary}}\n\nTitle: {{entry.title}}\nCollection: {{collection}}\nPublished at: {{now}}\n\nOpen in Birkly:\n{{entry_admin_url}}'
        }
      }]
    },
    webhook_on_created: {
      name: 'Webhook on Entry Created',
      enabled: true,
      stop_on_failure: false,
      trigger: { type: 'entry_created', config: {} },
      conditions: [],
      actions: [{
        type: 'webhook',
        config: {
          url: 'https://example.com/webhook',
          method: 'POST',
          payload: '{"event":"entry_created","entry":"{{entry.id}}","collection":"{{collection}}"}'
        }
      }]
    },
    sub_entry_notify: {
      name: 'Notify on Sub-Entry Created',
      enabled: true,
      stop_on_failure: false,
      trigger: { type: 'sub_entry_created', config: {} },
      conditions: [],
      actions: [{
        type: 'email',
        config: {
          to: '',
          subject: '{{automation.name}}',
          body: '{{trigger_summary}}\n\nParent: {{parent_collection}} / {{parent_entry_id}}\nSub-collection: {{sub_collection}}\n\nOpen in Birkly:\n{{entry_admin_url}}'
        }
      }]
    }
  };

  function seedNewAutomation(templateId) {
    let seed = {
      id: '',
      name: 'Untitled automation',
      enabled: true,
      stop_on_failure: false,
      trigger: { type: 'entry_created', config: {} },
      conditions: [],
      actions: [{ type: 'log', config: { message: 'Automation ran for {{entry.title}}' } }],
      graph: null
    };

    const fromQuery = templateId || new URLSearchParams(window.location.search).get('template') || '';
    let stored = '';
    try {
      stored = sessionStorage.getItem('automation-editor-template') || '';
      sessionStorage.removeItem('automation-editor-template');
    } catch (_) {}

    const tpl = EDITOR_TEMPLATES[fromQuery] || EDITOR_TEMPLATES[stored];
    if (tpl) {
      seed = Object.assign({ id: '', graph: null }, tpl);
    }

    applyAutomationToUi(seed);
    dirty = true;
    setStatus('New automation — connect nodes, then save', 'warn');
  }

  async function saveAutomation() {
    if (!nodeEditor) {
      showMessage('Editor not ready.', 'error');
      return;
    }

    const errors = nodeEditor.validate();
    if (errors.length) {
      showMessage(errors[0], 'error');
      syncValidation();
      return;
    }

    const workflow = nodeEditor.toWorkflow();
    const name = (document.getElementById('automation-editor-name')?.value || '').trim();
    if (!name) {
      showMessage('Name is required.', 'error');
      return;
    }
    if (!workflow.trigger?.type) {
      showMessage('Add a trigger node.', 'error');
      return;
    }
    if (!workflow.actions?.length) {
      showMessage('Add at least one action node.', 'error');
      return;
    }

    const payload = {
      name,
      enabled: !!document.getElementById('automation-editor-enabled')?.checked,
      stop_on_failure: !!document.getElementById('automation-editor-stop-on-failure')?.checked,
      trigger: workflow.trigger,
      conditions: workflow.conditions || [],
      actions: workflow.actions || [],
      graph: workflow.graph || nodeEditor.getCanvas(),
      _csrf: getCsrfToken()
    };

    const endpoint = currentId ? 'update' : 'create';
    if (currentId) payload.automation_id = currentId;

    setStatus('Saving…');
    try {
      const response = await fetch(apiUrl('/api/automation.php?action=' + endpoint), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      if (result.status !== 'ok') {
        throw new Error(result.message || 'Save failed');
      }

      const saved = result.data.automation;
      currentId = saved.id;
      dirty = false;
      updateBreadcrumb(saved.name || 'Automation');
      const testBtn = document.getElementById('automation-editor-test');
      if (testBtn) testBtn.hidden = false;

      // Keep URL in sync after first create.
      if (endpoint === 'create') {
        const url = adminPageUrl('automation-editor', { id: currentId });
        window.history.replaceState({}, '', url);
      }

      showMessage('Automation saved.', 'success');
      setStatus('Saved “' + (saved.name || currentId) + '”', 'ok');
    } catch (e) {
      showMessage(e.message || 'Save failed', 'error');
      setStatus(e.message || 'Save failed', 'error');
    }
  }

  async function runDryTest() {
    if (!currentId) {
      showMessage('Save the automation before testing.', 'error');
      return;
    }
    if (dirty) {
      showMessage('Save changes before testing so the dry-run uses the latest graph.', 'error');
      return;
    }
    const modal = document.getElementById('automation-logs-modal');
    const title = document.getElementById('automation-logs-title');
    const content = document.getElementById('automation-logs-content');
    if (title) title.textContent = 'Dry run (nothing sent)';
    if (content) content.innerHTML = '<div class="loading-state">Running dry-run…</div>';
    showModal(modal);

    try {
      const response = await fetch(apiUrl('/api/automation.php?action=dry_run'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ automation_id: currentId, context: {}, _csrf: getCsrfToken() })
      });
      const result = await response.json();
      if (result.status !== 'ok') {
        content.innerHTML = '<div class="error-state">' + escapeHtml(result.message || 'Dry-run failed') + '</div>';
        return;
      }
      const data = result.data || {};
      const steps = data.steps || data.results || [];
      const actionCount = data.action_count || 0;
      const setup = data.setup || {};
      const setupNotes = []
        .concat(setup.blocking || [])
        .concat(setup.warnings || [])
        .slice(0, 3)
        .map((note) => '<li>' + escapeHtml(note) + '</li>')
        .join('');

      const stepsHtml = steps.length
        ? steps.map((step) => `
            <div class="automation-dry-run-step ${escapeHtml(step.status || 'info')}">
              <div class="automation-dry-run-step-label">${escapeHtml(step.label || step.action_type || 'Step')}</div>
              <div class="automation-dry-run-step-detail">${escapeHtml(step.detail || step.status || JSON.stringify(step.result || {}))}</div>
            </div>
          `).join('')
        : '<p class="field-help">Dry-run completed with no step details.</p>';

      content.innerHTML = `
        <div class="automation-dry-run-panel" style="background:none;border:none;padding:0">
          <div style="margin-bottom:var(--space-3)">
            <span class="automation-dry-run-badge">Dry Run — nothing sent</span>
            <p style="font-size:0.8125rem;color:var(--text-secondary);margin:var(--space-2) 0 0 0">
              Simulation only. Emails, Slack, Teams, and webhooks were <strong>not</strong> sent.
              Configure email under Settings → Connections (Send using on each email action).
            </p>
            ${setupNotes ? `<ul style="margin:var(--space-2) 0 0;padding-left:1.1rem;font-size:0.8125rem;color:var(--warning-700,#b45309)">${setupNotes}</ul>` : ''}
          </div>
          <div class="automation-dry-run-steps">${stepsHtml}</div>
          <div class="automation-dry-run-confirm-bar">
            <p><strong>Run for real?</strong> Executes ${actionCount} action${actionCount === 1 ? '' : 's'} with live side effects.</p>
            <button type="button" class="btn btn-sm btn-warning" id="automation-editor-run-live">Confirm &amp; Run Live</button>
          </div>
        </div>
      `;
      document.getElementById('automation-editor-run-live')?.addEventListener('click', () => runLiveTest());
    } catch (e) {
      content.innerHTML = '<div class="error-state">' + escapeHtml(e.message) + '</div>';
    }
  }

  async function runLiveTest() {
    if (!currentId) return;
    if (!confirm('Run this automation live now? Real actions (emails, webhooks, etc.) will execute.')) return;
    const content = document.getElementById('automation-logs-content');
    const title = document.getElementById('automation-logs-title');
    if (title) title.textContent = 'Live run';
    if (content) content.innerHTML = '<div class="loading-state">Running live…</div>';
    try {
      const response = await fetch(apiUrl('/api/automation.php?action=run_live'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          automation_id: currentId,
          skip_conditions: true,
          context: {},
          _csrf: getCsrfToken()
        })
      });
      const result = await response.json();
      if (result.status !== 'ok') {
        content.innerHTML = '<div class="error-state">' + escapeHtml(result.message || 'Live run failed') + '</div>';
        return;
      }
      const ok = result.data?.success !== false;
      const delivery = result.data?.delivery_note
        ? '<br>' + escapeHtml(result.data.delivery_note)
        : '<br>If nothing arrived, check Settings → Connections and the Send using connection on your email action.';
      content.innerHTML = `
        <div style="padding:var(--space-4)">
          <p style="color:${ok ? 'var(--success-600)' : 'var(--warning-600)'};font-weight:600;margin:0 0 var(--space-2) 0">
            ${ok ? '✓ Live run completed' : 'Live run finished with warnings'}
          </p>
          <p style="font-size:0.875rem;color:var(--text-secondary);margin:0">
            Runtime: ${result.data?.runtime_ms ? Number(result.data.runtime_ms).toFixed(1) + 'ms' : 'n/a'}<br>
            Actions executed: ${result.data?.actions_executed ?? 'n/a'}
            ${delivery}
          </p>
        </div>
      `;
    } catch (e) {
      content.innerHTML = '<div class="error-state">' + escapeHtml(e.message) + '</div>';
    }
  }

  function showModal(modal) {
    if (!modal) return;
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.style.display = 'flex';
    modal.classList.add('active');
  }

  function hideModal(modal) {
    if (!modal) return;
    modal.classList.remove('active');
    modal.style.display = 'none';
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
  }

  function escapeHtml(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
  }

  function bindUi() {
    document.getElementById('automation-editor-save')?.addEventListener('click', saveAutomation);
    document.getElementById('automation-editor-test')?.addEventListener('click', runDryTest);
    document.getElementById('automation-editor-export')?.addEventListener('click', () => {
      if (!nodeEditor) return;
      const json = nodeEditor.exportJson();
      if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(json).then(() => showMessage('Canvas JSON copied.', 'success'));
      } else {
        prompt('Canvas JSON:', json);
      }
    });
    document.getElementById('automation-editor-import')?.addEventListener('click', () => {
      const raw = prompt('Paste canvas JSON:');
      if (!raw || !nodeEditor) return;
      try {
        nodeEditor.importJson(raw);
        markDirty();
        showMessage('Canvas imported.', 'success');
      } catch (e) {
        showMessage('Invalid canvas JSON: ' + e.message, 'error');
      }
    });

    ['automation-editor-name', 'automation-editor-enabled', 'automation-editor-stop-on-failure'].forEach((id) => {
      document.getElementById(id)?.addEventListener('change', markDirty);
      document.getElementById(id)?.addEventListener('input', () => {
        if (id === 'automation-editor-name') {
          updateBreadcrumb(document.getElementById(id).value || 'Automation');
        }
        markDirty();
      });
    });

    document.getElementById('automation-editor-close-logs')?.addEventListener('click', () => {
      hideModal(document.getElementById('automation-logs-modal'));
    });
    document.getElementById('automation-editor-close-logs-footer')?.addEventListener('click', () => {
      hideModal(document.getElementById('automation-logs-modal'));
    });

    window.addEventListener('beforeunload', (e) => {
      if (!dirty) return;
      e.preventDefault();
      e.returnValue = '';
    });
  }

  async function boot() {
    bindUi();
    initEditor();
    await Promise.all([loadBlocks(), loadCollections()]);

    const params = new URLSearchParams(window.location.search);
    const id = params.get('id') || '';
    const isNew = params.get('new') === '1' || !id;

    // Breadcrumbs move into the topbar after DOMContentLoaded — sync name again.
    updateBreadcrumb(document.getElementById('automation-editor-name')?.value || 'New automation');
    setTimeout(() => {
      updateBreadcrumb(document.getElementById('automation-editor-name')?.value || 'New automation');
    }, 0);

    try {
      if (isNew && !id) {
        seedNewAutomation(params.get('template') || '');
      } else {
        const automation = await loadAutomation(id);
        applyAutomationToUi(automation);
      }
    } catch (e) {
      setStatus(e.message || 'Failed to load automation', 'error');
      showMessage(e.message || 'Failed to load automation', 'error');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
