/**
 * Automation admin page — workflow builder UI
 * P5.6-M3: collection pickers, reorder, duplicate, dry-run (D4), empty state, health hint
 */

const apiUrl = (path) => (window.Birkly && typeof Birkly.url === 'function') ? Birkly.url(path) : path;

function adminPageUrl(page, params) {
  const base = apiUrl('/admin/index.php');
  const qs = new URLSearchParams(Object.assign({ page: page }, params || {}));
  return base + '?' + qs.toString();
}

function openAutomationEditor(automationId, extras) {
  const params = Object.assign({}, extras || {});
  if (automationId) params.id = automationId;
  else params.new = '1';
  window.location.href = adminPageUrl('automation-editor', params);
}

let automations = [];
let availableBlocks = { triggers: [], conditions: [], actions: [] };
let availableCollections = [];
let currentAutomation = null;
let automationEditorMode = 'canvas';
let nodeEditor = null;
let cronUrlsVisible = false;

// ============================================================
// Node canvas editor (P7-M6)
// ============================================================

function initNodeEditor() {
  if (nodeEditor || typeof AutomationNodeEditor === 'undefined') return;
  const canvas = document.getElementById('node-canvas');
  const palette = document.getElementById('node-palette');
  if (!canvas || !palette) return;

  nodeEditor = new AutomationNodeEditor({
    container: canvas,
    palette,
    inspector: document.getElementById('node-inspector'),
    blocks: availableBlocks,
    onChange: syncCanvasValidation
  });
}

function setAutomationEditorMode(mode) {
  automationEditorMode = mode === 'canvas' ? 'canvas' : 'form';
  initNodeEditor();

  const formPanel = document.getElementById('automation-form-editor');
  const canvasPanel = document.getElementById('automation-canvas-editor');
  const canvasActions = document.getElementById('canvas-toolbar-actions');

  document.querySelectorAll('.editor-mode-btn').forEach((btn) => {
    const active = btn.dataset.mode === automationEditorMode;
    btn.classList.toggle('active', active);
    btn.setAttribute('aria-selected', active ? 'true' : 'false');
  });

  if (formPanel) formPanel.style.display = automationEditorMode === 'form' ? '' : 'none';
  if (canvasPanel) canvasPanel.style.display = automationEditorMode === 'canvas' ? '' : 'none';
  if (canvasActions) canvasActions.style.display = automationEditorMode === 'canvas' ? '' : 'none';

  if (automationEditorMode === 'canvas') {
    if (nodeEditor) {
      nodeEditor.setBlocks(availableBlocks);
      nodeEditor.loadFromWorkflow(collectWorkflowFromForm());
    }
    syncCanvasValidation();
  }
}

function collectWorkflowFromForm() {
  const form = document.getElementById('automation-form');
  if (!form) return { trigger: { type: '', config: {} }, conditions: [], actions: [] };

  const formData = new FormData(form);
  const workflow = {
    trigger: { type: formData.get('trigger[type]') || '', config: {} },
    conditions: [],
    actions: []
  };

  formData.forEach((value, key) => {
    const m = key.match(/^trigger\[config\]\[([^\]]+)\]$/);
    if (m) workflow.trigger.config[m[1]] = value;
  });

  document.querySelectorAll('.condition-item').forEach((item, index) => {
    const typeSelect = item.querySelector('.condition-type-select');
    if (typeSelect && typeSelect.value) {
      const condition = { type: typeSelect.value, config: {} };
      formData.forEach((value, key) => {
        const cm = key.match(new RegExp(`^conditions\\[${index}\\]\\[config\\]\\[([^\\]]+)\\]$`));
        if (cm) condition.config[cm[1]] = value;
      });
      workflow.conditions.push(condition);
    }
  });

  document.querySelectorAll('.action-item').forEach((item, index) => {
    const typeSelect = item.querySelector('.action-type-select');
    if (typeSelect && typeSelect.value) {
      const action = { type: typeSelect.value, config: {} };
      formData.forEach((value, key) => {
        const am = key.match(new RegExp(`^actions\\[${index}\\]\\[config\\]\\[([^\\]]+)\\]$`));
        if (am) action.config[am[1]] = value;
      });
      workflow.actions.push(action);
    }
  });

  return workflow;
}

function applyWorkflowToForm(workflow) {
  const automation = {
    id: document.getElementById('automation-id')?.value || '',
    name: document.getElementById('automation-name')?.value || '',
    enabled: document.getElementById('automation-enabled')?.checked !== false,
    stop_on_failure: document.getElementById('stop-on-failure')?.checked || false,
    trigger: workflow.trigger || { type: '', config: {} },
    conditions: workflow.conditions || [],
    actions: workflow.actions || []
  };
  populateAutomationForm(automation);
}

function syncCanvasValidation() {
  const box = document.getElementById('canvas-validation-errors');
  if (!box || !nodeEditor) return;
  const errors = nodeEditor.validate();
  if (!errors.length) {
    box.style.display = 'none';
    box.innerHTML = '';
    return;
  }
  box.style.display = 'block';
  box.innerHTML = '<strong>Canvas validation:</strong><ul>' +
    errors.map((e) => '<li>' + escapeHtml(e) + '</li>').join('') + '</ul>';
}

function exportCanvasJson() {
  if (!nodeEditor) return;
  const json = nodeEditor.exportJson();
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(json).then(() => showMessage('Canvas JSON copied to clipboard.', 'success'));
  } else {
    prompt('Canvas JSON:', json);
  }
}

function importCanvasJson() {
  const raw = prompt('Paste canvas JSON:');
  if (!raw) return;
  try {
    initNodeEditor();
    nodeEditor.importJson(raw);
    showMessage('Canvas imported.', 'success');
  } catch (e) {
    showMessage('Invalid canvas JSON: ' + e.message, 'error');
  }
}

// ============================================================
// Init
// ============================================================

document.addEventListener('DOMContentLoaded', function () {
  initAutomationActionDelegation();
  initAutomationViewTabs();
  loadBlocks();
  loadAutomations();
  loadCollections();
  checkQueueHealth();
});

// ============================================================
// CSP-safe actions (admin CSP blocks inline onclick)
// ============================================================

let automationActionsBound = false;
function initAutomationActionDelegation() {
  if (automationActionsBound) return;
  automationActionsBound = true;
  document.addEventListener('click', function (event) {
    const el = event.target.closest('[data-automation-action]');
    if (!el) return;
    // Page shell + modals (modals sit outside .automation-page)
    if (!el.closest('.automation-page, #automation-modal, #automation-logs-modal')) return;

    // Prevent duplicate handlers (script loaded twice) from firing the same action.
    if (event.__automationActionHandled) return;
    event.__automationActionHandled = true;
    event.preventDefault();

    const action = el.getAttribute('data-automation-action');
    const id = el.getAttribute('data-automation-id') || '';
    const index = parseInt(el.getAttribute('data-automation-index') || '', 10);

    switch (action) {
      case 'create':
        openAutomationEditor(null);
        break;
      case 'close-modal':
        closeAutomationModal();
        break;
      case 'close-logs':
        closeLogsModal();
        break;
      case 'save':
        saveAutomation();
        break;
      case 'editor-mode':
        setAutomationEditorMode(el.getAttribute('data-automation-mode') || 'canvas');
        break;
      case 'export-canvas':
        exportCanvasJson();
        break;
      case 'import-canvas':
        importCanvasJson();
        break;
      case 'add-condition':
        addCondition();
        break;
      case 'add-action':
        addAction();
        break;
      case 'remove-condition':
        if (!Number.isNaN(index)) removeCondition(index);
        break;
      case 'remove-action':
        if (!Number.isNaN(index)) removeAction(index);
        break;
      case 'move-action': {
        const delta = parseInt(el.getAttribute('data-automation-delta') || '0', 10);
        if (!Number.isNaN(index) && delta) moveAction(index, delta);
        break;
      }
      case 'edit':
        if (id) openAutomationEditor(id);
        break;
      case 'logs':
        if (id) viewLogs(id);
        break;
      case 'delete':
        if (id) deleteAutomation(id);
        break;
      case 'duplicate':
        if (id) duplicateAutomation(id);
        break;
      case 'dry-run':
        if (id) dryRunAutomation(id);
        break;
      case 'toggle':
        if (id) {
          const enabled = el.getAttribute('data-automation-enabled') === '1';
          toggleAutomation(id, enabled);
        }
        break;
      case 'apply-template': {
        const templateId = el.getAttribute('data-automation-template') || '';
        if (templateId) {
          try {
            sessionStorage.setItem('automation-editor-template', templateId);
          } catch (_) {}
          openAutomationEditor(null, { template: templateId });
        }
        break;
      }
      case 'confirm-live-run':
        if (id) confirmLiveRun(id);
        break;
      case 'dismiss-health':
        dismissHealthHint();
        break;
      case 'toggle-cron-urls':
        toggleCronUrlVisibility();
        break;
      case 'show-setup':
        switchAutomationView('setup');
        break;
      case 'process-queue-now':
        processQueueNow();
        break;
      case 'refresh-setup':
        loadSetupHealth();
        break;
      case 'send-test-email':
        sendTestEmail();
        break;
      default:
        break;
    }
  });

  const mailForm = document.getElementById('automation-mail-settings-form');
  if (mailForm) {
    mailForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      await saveMailSettings();
    });
  }
  const methodSelect = document.getElementById('automation-mail-method');
  if (methodSelect) {
    methodSelect.addEventListener('change', syncSmtpFieldsVisibility);
    syncSmtpFieldsVisibility();
  }

  document.addEventListener('keydown', function (event) {
    const el = event.target.closest('[data-automation-action="apply-template"]');
    if (!el || (event.key !== 'Enter' && event.key !== ' ')) return;
    event.preventDefault();
    const templateId = el.getAttribute('data-automation-template') || '';
    if (templateId) applyTemplate(templateId);
  });

  document.addEventListener('change', function (event) {
    const el = event.target.closest('[data-automation-change]');
    if (!el || !el.closest('#automation-modal')) return;
    const kind = el.getAttribute('data-automation-change');
    const index = parseInt(el.getAttribute('data-automation-index') || '', 10);
    if (Number.isNaN(index)) return;
    if (kind === 'condition-type') loadConditionConfig(index, el.value);
    if (kind === 'action-type') loadActionConfig(index, el.value);
  });
}

function showAutomationModal(modal) {
  if (!modal) return;
  modal.hidden = false;
  modal.setAttribute('aria-hidden', 'false');
  modal.style.display = 'flex';
  modal.classList.add('active');
}

function hideAutomationModal(modal) {
  if (!modal) return;
  modal.classList.remove('active');
  modal.style.display = 'none';
  modal.hidden = true;
  modal.setAttribute('aria-hidden', 'true');
}

function switchAutomationView(view) {
  const target = view || 'list';
  document.querySelectorAll('.automation-view-tabs .payments-tab').forEach((btn) => {
    const active = (btn.getAttribute('data-automation-view') || '') === target;
    btn.classList.toggle('active', active);
  });
  const listPanel = document.getElementById('automation-view-list');
  const setupPanel = document.getElementById('automation-view-setup');
  const docsPanel = document.getElementById('automation-view-documentation');
  if (listPanel) listPanel.hidden = target !== 'list';
  if (setupPanel) setupPanel.hidden = target !== 'setup';
  if (docsPanel) docsPanel.hidden = target !== 'documentation';
  if (target === 'setup') {
    loadCronInfo();
    refreshSetupStatus();
  }
  if (target === 'documentation' && window.BirklyPluginDocs) {
    window.BirklyPluginDocs.activate('automation', 'automation-view-documentation');
  }
}

// ============================================================
// Data loading
// ============================================================

async function loadAutomations() {
  try {
    const response = await fetch(apiUrl('/api/automation.php?action=list'));
    const result = await response.json();
    if (result.status === 'ok') {
      automations = result.data.automations || [];
      renderAutomations();
      // Re-evaluate health tip after we know whether automations exist.
      checkQueueHealth();
    } else {
      showMessage('Error loading automations: ' + result.message, 'error');
    }
  } catch (error) {
    showMessage('Error loading automations: ' + error.message, 'error');
  }
}

async function loadBlocks() {
  try {
    const response = await fetch(apiUrl('/api/automation.php?action=get_blocks'));
    const result = await response.json();
    if (result.status === 'ok') {
      availableBlocks = result.data.blocks || { triggers: [], conditions: [], actions: [] };
      populateTriggerSelect();
      if (nodeEditor) nodeEditor.setBlocks(availableBlocks);
    }
  } catch (error) {
    console.error('Error loading blocks:', error);
  }
}

async function loadCollections() {
  try {
    const response = await fetch(apiUrl('/api/automation.php?action=get_collections'));
    const result = await response.json();
    if (result.status === 'ok') {
      availableCollections = result.data.collections || [];
    }
  } catch (_) {
    // Non-fatal — pickers fall back to text inputs
  }
}

// ============================================================
// Queue / cron health hint (M3-T6)
// ============================================================

async function checkQueueHealth() {
  try {
    const response = await fetch(apiUrl('/api/automation.php?action=queue_status'));
    const result = await response.json();
    if (result.status !== 'ok') return;
    const q = result.data.queue || result.data || {};
    const pending = Number(q.pending || 0);
    const completed = Number(q.completed || 0);
    const processing = Number(q.processing || 0);
    const failed = Number(q.failed || 0);
    window.__automationQueueStats = { pending, completed, processing, failed };

    // Always preload Setup panel info (not a scary list banner).
    loadCronInfo();
    loadSetupHealth();

    const hint = document.getElementById('automation-health-hint');
    const countEl = document.getElementById('automation-health-pending-count');
    if (countEl) countEl.textContent = String(pending);

    // Compact list tip only when jobs are waiting AND at least one automation exists.
    const hasAutomations = Array.isArray(automations) && automations.length > 0;
    const jobsWaiting = pending > 0;
    let dismissed = false;
    try {
      dismissed = sessionStorage.getItem('automation-health-dismissed') === '1';
    } catch (_) {}
    if (hint) {
      hint.style.display = (hasAutomations && jobsWaiting && completed === 0 && !dismissed) ? 'flex' : 'none';
    }
  } catch (_) {
    // Non-fatal
  }
}

function syncSmtpFieldsVisibility() {
  const method = document.getElementById('automation-mail-method')?.value || 'mail';
  const smtp = document.getElementById('automation-smtp-fields');
  if (smtp) smtp.hidden = method !== 'smtp';
}

async function loadSetupHealth() {
  const el = document.getElementById('automation-setup-status');
  const list = document.getElementById('automation-setup-checklist');
  const badge = document.getElementById('automation-setup-badge');
  const sentList = document.getElementById('automation-sent-mail-log');
  try {
    const response = await fetch(apiUrl('/api/automation.php?action=setup_health'));
    const result = await response.json();
    if (result.status !== 'ok') {
      if (el) {
        el.className = 'automation-setup-status is-warn';
        el.textContent = 'Could not load setup status.';
      }
      return;
    }
    const data = result.data || {};
    window.__automationSetupHealth = data;
    const versionLine = document.getElementById('automation-plugin-version-line');
    if (versionLine) {
      versionLine.textContent = 'Plugin version: ' + (data.plugin_version || 'unknown')
        + ' — if this is below 1.1.5, redeploy/sync the Automation plugin before debugging further.';
    }
    if (data.queue) {
      window.__automationQueueStats = {
        pending: Number(data.queue.pending || 0),
        completed: Number(data.queue.completed || 0),
        processing: Number(data.queue.processing || 0),
        failed: Number(data.queue.failed || 0),
      };
    }

    const mail = data.mail || {};
    const methodInput = document.getElementById('automation-mail-method');
    const nameInput = document.getElementById('automation-mail-from-name');
    const emailInput = document.getElementById('automation-mail-from-email');
    const hostInput = document.getElementById('automation-smtp-host');
    const portInput = document.getElementById('automation-smtp-port');
    const userInput = document.getElementById('automation-smtp-username');
    const encInput = document.getElementById('automation-smtp-encryption');
    if (methodInput && mail.method) methodInput.value = mail.method;
    if (nameInput && mail.from_name) nameInput.value = mail.from_name;
    if (emailInput && mail.from_email) emailInput.value = mail.from_email;
    if (hostInput) hostInput.value = mail.smtp_host || '';
    if (portInput) portInput.value = String(mail.smtp_port || 587);
    if (userInput) userInput.value = mail.smtp_username || '';
    if (encInput && mail.smtp_encryption) encInput.value = mail.smtp_encryption;
    syncSmtpFieldsVisibility();

    const checks = Array.isArray(data.checks) ? data.checks : [];
    if (list) {
      list.innerHTML = checks.map((check) => {
        const status = check.status || 'ok';
        const why = check.why ? `<p class="automation-setup-check-why"><strong>Why:</strong> ${escapeHtml(check.why)}</p>` : '';
        return `<li class="automation-setup-check is-${escapeAttr(status)}">
          <span class="automation-setup-check-status">${escapeHtml(status)}</span>
          <span class="automation-setup-check-label">${escapeHtml(check.label || '')}</span>
          <p class="automation-setup-check-detail">${escapeHtml(check.detail || '')}</p>
          ${why}
        </li>`;
      }).join('') || '<li class="form-help">No checks available.</li>';
    }

    if (sentList) {
      const sent = Array.isArray(data.sent_mail) ? data.sent_mail : [];
      sentList.innerHTML = sent.length
        ? sent.map((row) => `<li class="automation-setup-check is-ok">
            <span class="automation-setup-check-status">${escapeHtml(row.method || 'mail')}</span>
            <span class="automation-setup-check-label">${escapeHtml(row.to || '')}</span>
            <p class="automation-setup-check-detail">${escapeHtml(row.subject || '')} · ${escapeHtml(row.sent_at || '')}</p>
          </li>`).join('')
        : '<li class="form-help">No sends recorded yet. Use Send test email after saving settings.</li>';
    }

    const jobsList = document.getElementById('automation-recent-jobs');
    if (jobsList) {
      const jobs = Array.isArray(data.recent_jobs) ? data.recent_jobs : [];
      jobsList.innerHTML = jobs.length
        ? jobs.map((job) => {
          const status = job.status || 'pending';
          const cls = status === 'completed' ? 'ok' : (status === 'failed' ? 'fail' : 'warn');
          const note = job.error || job.warning || (job.executed_count != null ? (`matched ${job.executed_count} automation(s)`) : 'waiting');
          return `<li class="automation-setup-check is-${cls}">
            <span class="automation-setup-check-status">${escapeHtml(status)}</span>
            <span class="automation-setup-check-label">${escapeHtml(job.trigger_type || 'job')}${job.collection ? ' · ' + escapeHtml(String(job.collection)) : ''}</span>
            <p class="automation-setup-check-detail">${escapeHtml(note)}${job.retry_after ? ' · retry after ' + escapeHtml(String(job.retry_after)) : ''}</p>
          </li>`;
        }).join('')
        : '<li class="form-help">No queue jobs yet. Create an entry in the watched collection to enqueue one.</li>';
    }

    const blocking = data.blocking || [];
    const warnings = data.warnings || [];
    if (el) {
      if (blocking.length) {
        el.className = 'automation-setup-status is-warn';
        el.textContent = 'Setup incomplete: ' + blocking[0];
      } else if (warnings.length) {
        el.className = 'automation-setup-status is-warn';
        el.textContent = warnings[0];
      } else {
        el.className = 'automation-setup-status is-ok';
        el.textContent = 'Setup looks ready. Remember: Test is dry-run only — use Confirm & Run Live or create an entry for real delivery.';
      }
    }
    if (badge) {
      badge.hidden = !(blocking.length || warnings.length || Number(data.queue?.pending || 0) > 0);
    }
  } catch (_) {
    if (el) {
      el.className = 'automation-setup-status is-warn';
      el.textContent = 'Could not load setup status.';
    }
  }
}

function refreshSetupStatus() {
  loadSetupHealth();
}

async function processQueueNow() {
  const el = document.getElementById('automation-setup-status');
  if (el) {
    el.className = 'automation-setup-status';
    el.textContent = 'Force-processing queue (ignores retry wait)…';
  }
  try {
    const response = await fetch(apiUrl('/api/automation.php?action=process_queue_now'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ limit: 25, force: true, _csrf: getCsrfToken() })
    });
    const result = await response.json();
    if (result.status !== 'ok') {
      showMessage(result.message || 'Failed to process queue', 'error');
      await loadSetupHealth();
      return;
    }
    const processed = Number(result.data?.processed || 0);
    const notes = result.data?.health?.runtime?.last_job_notes || [];
    const noteText = notes.length ? (' ' + notes[0]) : '';
    showMessage(`Processed ${processed} queue job${processed === 1 ? '' : 's'}.${noteText}`, processed > 0 ? 'success' : 'info');
    await loadSetupHealth();
    await loadAutomations();
  } catch (error) {
    showMessage(error.message || 'Failed to process queue', 'error');
  }
}

async function saveMailSettings() {
  const msg = document.getElementById('automation-mail-settings-msg');
  const method = document.getElementById('automation-mail-method')?.value || 'mail';
  const fromName = document.getElementById('automation-mail-from-name')?.value || '';
  const fromEmail = document.getElementById('automation-mail-from-email')?.value || '';
  const smtpPassword = document.getElementById('automation-smtp-password')?.value || '';
  if (msg) msg.textContent = 'Saving…';
  try {
    const response = await fetch(apiUrl('/api/automation.php?action=save_mail_settings'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        method,
        from_name: fromName,
        from_email: fromEmail,
        smtp_host: document.getElementById('automation-smtp-host')?.value || '',
        smtp_port: Number(document.getElementById('automation-smtp-port')?.value || 587),
        smtp_username: document.getElementById('automation-smtp-username')?.value || '',
        smtp_password: smtpPassword,
        keep_smtp_password: smtpPassword === '',
        smtp_encryption: document.getElementById('automation-smtp-encryption')?.value || 'tls',
        _csrf: getCsrfToken()
      })
    });
    const result = await response.json();
    if (result.status !== 'ok') {
      if (msg) msg.textContent = result.message || 'Could not save email settings';
      showMessage(result.message || 'Could not save email settings', 'error');
      return;
    }
    if (msg) {
      msg.textContent = method === 'smtp'
        ? 'Saved. Click Send test email to verify SMTP delivery.'
        : (method === 'log'
          ? 'Saved. Emails will be logged only — use Send test email, then check Recent sends.'
          : 'Saved. PHP mail() rarely delivers on cloud hosts — prefer SMTP.');
    }
    const pwd = document.getElementById('automation-smtp-password');
    if (pwd) pwd.value = '';
    showMessage('Email settings saved.', 'success');
    if (result.data?.health) {
      window.__automationSetupHealth = result.data.health;
    }
    await loadSetupHealth();
  } catch (error) {
    if (msg) msg.textContent = error.message || 'Could not save email settings';
    showMessage(error.message || 'Could not save email settings', 'error');
  }
}

let sendingTestEmail = false;
let lastTestEmailStartedAt = 0;
async function sendTestEmail() {
  const now = Date.now();
  // Client-side hard gate: one send per 8s even if multiple listeners fire.
  if (sendingTestEmail || (now - lastTestEmailStartedAt) < 8000) return;
  sendingTestEmail = true;
  lastTestEmailStartedAt = now;
  const msg = document.getElementById('automation-test-email-msg');
  const to = document.getElementById('automation-test-email-to')?.value || '';
  const btn = document.querySelector('[data-automation-action="send-test-email"]');
  if (btn) btn.disabled = true;
  if (msg) msg.textContent = 'Sending test email…';
  try {
    const response = await fetch(apiUrl('/api/automation.php?action=send_test_email'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ to, _csrf: getCsrfToken() })
    });
    const result = await response.json();
    if (result.status !== 'ok') {
      if (msg) msg.textContent = result.message || 'Test email failed';
      showMessage(result.message || 'Test email failed', 'error');
      await loadSetupHealth();
      return;
    }
    if (msg) msg.textContent = result.message || 'Test email sent.';
    showMessage(result.message || 'Test email sent.', 'success');
    await loadSetupHealth();
  } catch (error) {
    if (msg) msg.textContent = error.message || 'Test email failed';
    showMessage(error.message || 'Test email failed', 'error');
  } finally {
    sendingTestEmail = false;
    if (btn) btn.disabled = false;
  }
}

async function loadCronInfo() {
  try {
    const response = await fetch(apiUrl('/api/automation.php?action=cron_info'));
    const result = await response.json();
    if (result.status !== 'ok') return;
    const data = result.data || {};
    const cli = document.getElementById('automation-cron-cli');
    const queueUrl = document.getElementById('automation-cron-queue-url');
    const scheduledUrl = document.getElementById('automation-cron-scheduled-url');
    if (cli && data.queue_cli) cli.textContent = data.queue_cli;
    setCronUrl(queueUrl, data.queue_url || '');
    setCronUrl(scheduledUrl, data.scheduled_url || '');
  } catch (_) {
    // Non-fatal
  }
}

function setCronUrl(element, url) {
  if (!element || !url) return;
  element.dataset.fullUrl = url;
  element.textContent = cronUrlsVisible ? url : maskCronUrl(url);
}

function maskCronUrl(url) {
  return String(url).replace(/([?&]token=)[^&]+/, '$1••••••');
}

function toggleCronUrlVisibility() {
  cronUrlsVisible = !cronUrlsVisible;
  document.querySelectorAll('#automation-cron-queue-url, #automation-cron-scheduled-url').forEach((el) => {
    const fullUrl = el.dataset.fullUrl || el.textContent || '';
    el.textContent = cronUrlsVisible ? fullUrl : maskCronUrl(fullUrl);
  });
  const toggle = document.getElementById('automation-cron-toggle');
  if (toggle) {
    toggle.textContent = cronUrlsVisible ? 'Hide cron URLs' : 'Show cron URLs';
    toggle.setAttribute('aria-pressed', cronUrlsVisible ? 'true' : 'false');
  }
}

function dismissHealthHint() {
  const hint = document.getElementById('automation-health-hint');
  if (hint) hint.style.display = 'none';
  try {
    sessionStorage.setItem('automation-health-dismissed', '1');
  } catch (_) {}
}

// ============================================================
// Trigger select
// ============================================================

function populateTriggerSelect() {
  const select = document.getElementById('trigger-type');
  if (!select) return;

  // Group triggers by category
  const categories = {};
  Object.keys(availableBlocks.triggers || {}).forEach(triggerId => {
    const trigger = availableBlocks.triggers[triggerId];
    const cat = trigger.category || 'Other';
    if (!categories[cat]) categories[cat] = [];
    categories[cat].push({ id: triggerId, ...trigger });
  });

  select.innerHTML = '<option value="">Select trigger…</option>';
  Object.keys(categories).sort().forEach(cat => {
    const group = document.createElement('optgroup');
    group.label = cat;
    categories[cat].forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.id;
      opt.textContent = t.name || t.id;
      group.appendChild(opt);
    });
    select.appendChild(group);
  });
}

// ============================================================
// Automations list rendering
// ============================================================

function renderAutomations() {
  const container = document.getElementById('automation-list');
  if (!container) return;

  if (automations.length === 0) {
    renderEmptyState(container);
    return;
  }

  // Hide health hint when there are automations (already rendered if needed)
  container.innerHTML = automations.map(automation => {
    const triggerType = automation.trigger?.type || 'unknown';
    const actionCount = automation.actions?.length || 0;
    return `
      <div class="automation-card" data-id="${escapeAttr(automation.id)}">
        <div class="automation-card-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
          </svg>
        </div>
        <div class="automation-card-body">
          <button type="button" class="automation-card-title-btn" data-automation-action="edit" data-automation-id="${escapeAttr(automation.id)}">
            ${escapeHtml(automation.name || 'Untitled')}
          </button>
          <div class="automation-card-meta">
            <span class="status-badge ${automation.enabled ? 'active' : 'inactive'}">
              ${automation.enabled ? 'Enabled' : 'Disabled'}
            </span>
            <span class="automation-card-meta-sep">·</span>
            <span>Trigger: <strong>${escapeHtml(triggerType)}</strong></span>
            <span class="automation-card-meta-sep">·</span>
            <span>${actionCount} action${actionCount !== 1 ? 's' : ''}</span>
          </div>
        </div>
        <div class="automation-card-actions">
          <button type="button" class="btn btn-sm btn-ghost" data-automation-action="dry-run" data-automation-id="${escapeAttr(automation.id)}" title="Test run (dry run)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polygon points="5,3 19,12 5,21 5,3"/>
            </svg>
            Test
          </button>
          <button type="button" class="btn btn-sm btn-ghost" data-automation-action="duplicate" data-automation-id="${escapeAttr(automation.id)}" title="Duplicate">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
              <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
            </svg>
          </button>
          <button type="button" class="btn btn-sm btn-primary" data-automation-action="edit" data-automation-id="${escapeAttr(automation.id)}">Open</button>
          <button type="button" class="btn btn-sm btn-secondary" data-automation-action="logs" data-automation-id="${escapeAttr(automation.id)}">Logs</button>
          <button type="button" class="btn btn-sm ${automation.enabled ? 'btn-warning' : 'btn-primary'}"
                  data-automation-action="toggle" data-automation-id="${escapeAttr(automation.id)}" data-automation-enabled="${automation.enabled ? '1' : '0'}">
            ${automation.enabled ? 'Disable' : 'Enable'}
          </button>
          <button type="button" class="btn btn-sm btn-danger" data-automation-action="delete" data-automation-id="${escapeAttr(automation.id)}">Delete</button>
        </div>
      </div>
    `;
  }).join('');
}

// ============================================================
// Empty state + templates (M3-T5)
// ============================================================

const AUTOMATION_TEMPLATES = [
  {
    id: 'notify_entry_published',
    label: 'Notify on Entry Published',
    description: 'Send an email when an entry status changes to published.',
    automation: {
      name: 'Notify on Entry Published',
      enabled: true,
      stop_on_failure: false,
      trigger: { type: 'status_changed', config: { to: 'published' } },
      conditions: [],
      actions: [
        {
          type: 'email',
          config: {
            to: '',
            subject: '{{automation.name}}',
            body: '{{trigger_summary}}\n\nTitle: {{entry.title}}\nCollection: {{collection}}\nPublished at: {{now}}\n\nOpen in Birkly:\n{{entry_admin_url}}'
          }
        }
      ]
    }
  },
  {
    id: 'webhook_on_created',
    label: 'Webhook on Entry Created',
    description: 'POST to a webhook URL when any entry is created.',
    automation: {
      name: 'Webhook on Entry Created',
      enabled: true,
      stop_on_failure: false,
      trigger: { type: 'entry_created', config: {} },
      conditions: [],
      actions: [
        {
          type: 'webhook',
          config: {
            url: 'https://example.com/webhook',
            method: 'POST',
            payload: '{"event":"entry_created","entry":"{{entry.id}}","collection":"{{collection}}"}'
          }
        }
      ]
    }
  },
  {
    id: 'sub_entry_notify',
    label: 'Notify on Sub-Entry Created',
    description: 'Email when a sub-entry (e.g. comment) is added.',
    automation: {
      name: 'Notify on Sub-Entry Created',
      enabled: true,
      stop_on_failure: false,
      trigger: { type: 'sub_entry_created', config: {} },
      conditions: [],
      actions: [
        {
          type: 'email',
          config: {
            to: '',
            subject: '{{automation.name}}',
            body: '{{trigger_summary}}\n\nParent: {{parent_collection}} / {{parent_entry_id}}\nSub-collection: {{sub_collection}}\n\nOpen in Birkly:\n{{entry_admin_url}}'
          }
        }
      ]
    }
  }
];

function renderEmptyState(container) {
  const templatesHtml = AUTOMATION_TEMPLATES.map(t => `
    <div class="automation-template-card" data-automation-action="apply-template" data-automation-template="${escapeAttr(t.id)}" role="button" tabindex="0">
      <div class="automation-template-card-label">${escapeHtml(t.label)}</div>
      <div class="automation-template-card-desc">${escapeHtml(t.description)}</div>
    </div>
  `).join('');

  container.innerHTML = `
    <div class="automation-empty-state">
      <div class="automation-empty-icon">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
        </svg>
      </div>
      <h3>No automations yet</h3>
      <p>Automations run workflows automatically when content changes, entries are published, or sub-entries are created.</p>
      <div class="automation-empty-actions">
        <button type="button" class="btn btn-primary" data-automation-action="create">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          Create Automation
        </button>
      </div>
      <div class="automation-templates-section">
        <h4>Start from a template</h4>
        <div class="automation-templates-grid">${templatesHtml}</div>
      </div>
    </div>
  `;
}

function applyTemplate(templateId) {
  const tpl = AUTOMATION_TEMPLATES.find(t => t.id === templateId);
  if (!tpl) return;
  currentAutomation = null;
  const title = document.getElementById('automation-modal-title');
  if (title) title.textContent = 'Create Automation';
  const form = document.getElementById('automation-form');
  if (form) form.reset();
  const idField = document.getElementById('automation-id');
  if (idField) idField.value = '';
  const conditionsList = document.getElementById('conditions-list');
  if (conditionsList) conditionsList.innerHTML = '';
  const actionsList = document.getElementById('actions-list');
  if (actionsList) actionsList.innerHTML = '';
  const triggerConfig = document.getElementById('trigger-config');
  if (triggerConfig) triggerConfig.style.display = 'none';
  showAutomationModal(document.getElementById('automation-modal'));
  try {
    setAutomationEditorMode('canvas');
  } catch (e) {
    console.warn('Automation editor init failed:', e);
  }
  setTimeout(() => populateAutomationForm(tpl.automation), 50);
}

// ============================================================
// Modal open/close
// ============================================================

function openCreateAutomationModal() {
  currentAutomation = null;
  const title = document.getElementById('automation-modal-title');
  if (title) title.textContent = 'Create Automation';
  const form = document.getElementById('automation-form');
  if (form) form.reset();
  const idField = document.getElementById('automation-id');
  if (idField) idField.value = '';
  const conditionsList = document.getElementById('conditions-list');
  if (conditionsList) conditionsList.innerHTML = '';
  const actionsList = document.getElementById('actions-list');
  if (actionsList) actionsList.innerHTML = '';
  const triggerConfig = document.getElementById('trigger-config');
  if (triggerConfig) triggerConfig.style.display = 'none';

  // Open modal first so CSP/UI failures in the editor never look like a dead button.
  showAutomationModal(document.getElementById('automation-modal'));

  try {
    setAutomationEditorMode('canvas');
    initNodeEditor();
    if (nodeEditor) {
      nodeEditor.loadFromWorkflow({
        trigger: { type: 'entry_created', config: {} },
        conditions: [],
        actions: [{ type: 'log', config: { message: 'Automation ran for {{entry.title}}' } }],
      });
    }
  } catch (e) {
    console.warn('Automation editor init failed:', e);
  }

  const drPanel = document.getElementById('dry-run-panel');
  if (drPanel) drPanel.style.display = 'none';
}

function closeAutomationModal() {
  hideAutomationModal(document.getElementById('automation-modal'));
}

// ============================================================
// Edit automation
// ============================================================

async function editAutomation(automationId) {
  try {
    const response = await fetch(apiUrl(`/api/automation.php?action=get&automation_id=${encodeURIComponent(automationId)}`));
    const result = await response.json();
    if (result.status === 'ok') {
      currentAutomation = result.data.automation;
      document.getElementById('automation-modal-title').textContent = 'Edit Automation';
      const drPanel = document.getElementById('dry-run-panel');
      if (drPanel) drPanel.style.display = 'none';
      showAutomationModal(document.getElementById('automation-modal'));
      try {
        setAutomationEditorMode('canvas');
      } catch (e) {
        console.warn('Automation editor init failed:', e);
      }
      setTimeout(() => populateAutomationForm(currentAutomation), 50);
    } else {
      showMessage('Error loading automation: ' + result.message, 'error');
    }
  } catch (error) {
    showMessage('Error: ' + error.message, 'error');
  }
}

function populateAutomationForm(automation) {
  document.getElementById('automation-id').value = automation.id || '';
  document.getElementById('automation-name').value = automation.name || '';
  document.getElementById('automation-enabled').checked = automation.enabled !== false;
  document.getElementById('stop-on-failure').checked = automation.stop_on_failure || false;

  if (automation.trigger) {
    const triggerSelect = document.getElementById('trigger-type');
    triggerSelect.value = automation.trigger.type || '';
    loadTriggerConfig(automation.trigger.type, automation.trigger.config || {});
  }

  const conditionsList = document.getElementById('conditions-list');
  conditionsList.innerHTML = '';
  (automation.conditions || []).forEach((condition, index) => {
    addConditionToForm(condition, index);
  });

  const actionsList = document.getElementById('actions-list');
  actionsList.innerHTML = '';
  (automation.actions || []).forEach((action, index) => {
    addActionToForm(action, index);
  });

  if (automationEditorMode === 'canvas') {
    initNodeEditor();
    if (nodeEditor) nodeEditor.loadFromWorkflow(automation);
  }
}

function initAutomationViewTabs() {
  document.querySelectorAll('.automation-view-tabs .payments-tab').forEach((btn) => {
    btn.addEventListener('click', () => {
      switchAutomationView(btn.getAttribute('data-automation-view') || 'list');
    });
  });
}

// ============================================================
// Trigger config (M3-T3: collection/sub-collection pickers)
// ============================================================

document.getElementById('trigger-type')?.addEventListener('change', function () {
  loadTriggerConfig(this.value);
});

function loadTriggerConfig(triggerType, config = {}) {
  const container = document.getElementById('trigger-config');
  if (!triggerType) {
    container.style.display = 'none';
    return;
  }

  const trigger = availableBlocks.triggers[triggerType];
  if (!trigger || !Object.keys(trigger.schema || {}).length) {
    container.style.display = 'none';
    return;
  }

  container.style.display = 'block';
  container.innerHTML = '';

  const schema = trigger.schema || {};
  Object.keys(schema).forEach(field => {
    const fieldSchema = schema[field];
    const div = document.createElement('div');
    div.className = 'form-group';

    const label = document.createElement('label');
    label.textContent = fieldSchema.label || field;
    if (fieldSchema.required) {
      label.innerHTML += ' <span class="required">*</span>';
    }
    div.appendChild(label);

    const input = buildFieldInput(fieldSchema, field, config[field], `trigger[config][${field}]`, config);
    div.appendChild(input);

    if (fieldSchema.description) {
      const help = document.createElement('small');
      help.className = 'form-help';
      help.textContent = fieldSchema.description;
      div.appendChild(help);
    }

    container.appendChild(div);
  });
}

/**
 * Build a form input element. Handles collection_picker and sub_collection_picker
 * in addition to the standard text/select/textarea types.
 */
function buildFieldInput(fieldSchema, fieldName, currentValue, nameAttr, siblingConfig = {}) {
  const type = fieldSchema.type || 'text';

  if (type === 'collection_picker') {
    return buildCollectionPickerInput(fieldName, nameAttr, currentValue, siblingConfig);
  }

  if (type === 'sub_collection_picker') {
    return buildSubCollectionPickerInput(fieldName, nameAttr, currentValue, siblingConfig);
  }

  if (type === 'select') {
    const input = document.createElement('select');
    input.className = 'input';
    input.name = nameAttr;
    const empty = document.createElement('option');
    empty.value = '';
    empty.textContent = '— Any —';
    input.appendChild(empty);
    (fieldSchema.options || []).forEach(opt => {
      const option = document.createElement('option');
      option.value = opt.value;
      option.textContent = opt.label;
      if (currentValue === opt.value) option.selected = true;
      input.appendChild(option);
    });
    if (fieldSchema.required) input.required = true;
    return input;
  }

  if (type === 'textarea') {
    const input = document.createElement('textarea');
    input.className = 'input';
    input.name = nameAttr;
    input.rows = fieldSchema.rows || 4;
    input.value = currentValue || '';
    if (fieldSchema.required) input.required = true;
    return input;
  }

  const input = document.createElement('input');
  input.type = type === 'collection_picker' || type === 'sub_collection_picker' ? 'text' : (type || 'text');
  input.className = 'input';
  input.name = nameAttr;
  input.value = currentValue || '';
  if (fieldSchema.placeholder) input.placeholder = fieldSchema.placeholder;
  if (fieldSchema.required) input.required = true;
  return input;
}

function buildCollectionPickerInput(fieldName, nameAttr, currentValue, siblingConfig) {
  const wrapper = document.createElement('div');
  wrapper.className = 'collection-picker-wrapper';

  if (availableCollections.length === 0) {
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'input';
    input.name = nameAttr;
    input.value = currentValue || '';
    input.placeholder = 'Collection slug (optional)';
    wrapper.appendChild(input);
    return wrapper;
  }

  const select = document.createElement('select');
  select.className = 'input';
  select.name = nameAttr;
  select.dataset.role = 'collection-picker';

  const empty = document.createElement('option');
  empty.value = '';
  empty.textContent = '— Any collection —';
  select.appendChild(empty);

  availableCollections.forEach(col => {
    const opt = document.createElement('option');
    opt.value = col.slug;
    opt.textContent = col.title || col.slug;
    opt.dataset.subCollections = JSON.stringify(col.sub_collections || []);
    if (currentValue === col.slug) opt.selected = true;
    select.appendChild(opt);
  });

  // When collection changes, refresh the sub_collection picker if present
  select.addEventListener('change', function () {
    const form = this.closest('form') || document.getElementById('automation-form');
    const subPicker = form ? form.querySelector('[data-role="sub-collection-picker"]') : null;
    if (subPicker) {
      const chosen = this.options[this.selectedIndex];
      const subCols = JSON.parse(chosen.dataset.subCollections || '[]');
      refreshSubCollectionPicker(subPicker, subCols, subPicker.value);
    }
  });

  wrapper.appendChild(select);
  return wrapper;
}

function buildSubCollectionPickerInput(fieldName, nameAttr, currentValue, siblingConfig) {
  const wrapper = document.createElement('div');
  wrapper.className = 'sub-collection-picker-wrapper';

  const select = document.createElement('select');
  select.className = 'input';
  select.name = nameAttr;
  select.dataset.role = 'sub-collection-picker';

  const empty = document.createElement('option');
  empty.value = '';
  empty.textContent = '— Any sub-collection —';
  select.appendChild(empty);

  // Populate from currently selected collection in sibling config
  const selectedCollection = siblingConfig['collection'] || '';
  const collectionMeta = availableCollections.find(c => c.slug === selectedCollection);
  const subCols = collectionMeta ? (collectionMeta.sub_collections || []) : [];
  refreshSubCollectionPicker(select, subCols, currentValue);

  wrapper.appendChild(select);
  return wrapper;
}

function refreshSubCollectionPicker(selectEl, subCols, currentValue) {
  // Preserve first "any" option
  while (selectEl.options.length > 1) selectEl.remove(1);

  subCols.forEach(sc => {
    const opt = document.createElement('option');
    opt.value = sc.slug || sc;
    opt.textContent = sc.title || sc.slug || sc;
    if (currentValue === opt.value) opt.selected = true;
    selectEl.appendChild(opt);
  });

  if (subCols.length === 0) {
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = '— Select a collection first —';
    placeholder.disabled = true;
    // Don't add if already empty
  }
}

// ============================================================
// Conditions
// ============================================================

function addCondition() {
  const list = document.getElementById('conditions-list');
  addConditionToForm({ type: '', config: {} }, list.children.length);
}

function addConditionToForm(condition, index) {
  const list = document.getElementById('conditions-list');
  const div = document.createElement('div');
  div.className = 'block-item condition-item';
  div.dataset.index = index;

  const conditionBlocks = availableBlocks.conditions || {};
  const options = Object.keys(conditionBlocks).map(id => {
    const block = conditionBlocks[id];
    return `<option value="${id}" ${condition.type === id ? 'selected' : ''}>${escapeHtml(block.name || id)}</option>`;
  }).join('');

  div.innerHTML = `
    <div class="block-header">
      <h4 class="block-header-title">Condition ${index + 1}</h4>
      <button type="button" class="block-remove-btn" data-automation-action="remove-condition" data-automation-index="${index}" title="Remove">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="3,6 5,6 21,6"/>
          <path d="M19,6v14a2,2 0 0,1 -2,2H7a2,2 0 0,1 -2,-2V6m3,0V4a2,2 0 0,1 2,-2h4a2,2 0 0,1 2,2v2"/>
        </svg>
      </button>
    </div>
    <div class="block-body">
      <div class="form-group">
        <label>Condition Type</label>
        <select name="conditions[${index}][type]" class="input condition-type-select"
                data-automation-change="condition-type" data-automation-index="${index}">
          <option value="">Select condition…</option>
          ${options}
        </select>
      </div>
      <div class="block-config condition-config-${index}"></div>
    </div>
  `;

  list.appendChild(div);
  if (condition.type) loadConditionConfig(index, condition.type, condition.config || {});
}

function removeCondition(index) {
  const item = document.querySelector(`.condition-item[data-index="${index}"]`);
  if (item) item.remove();
  renumberBlocks('condition-item', 'Condition');
}

// ============================================================
// Actions (M3-T4: reorder up/down)
// ============================================================

function addAction() {
  const list = document.getElementById('actions-list');
  addActionToForm({ type: '', config: {} }, list.children.length);
}

function addActionToForm(action, index) {
  const list = document.getElementById('actions-list');
  const div = document.createElement('div');
  div.className = 'block-item action-item';
  div.dataset.index = index;

  const actionBlocks = availableBlocks.actions || {};
  const options = Object.keys(actionBlocks).map(id => {
    const block = actionBlocks[id];
    return `<option value="${id}" ${action.type === id ? 'selected' : ''}>${escapeHtml(block.name || id)}</option>`;
  }).join('');

  div.innerHTML = `
    <div class="block-header">
      <h4 class="block-header-title">Action ${index + 1}</h4>
      <div class="block-header-order">
        <button type="button" class="block-reorder-btn" data-automation-action="move-action" data-automation-index="${index}" data-automation-delta="-1" title="Move up" aria-label="Move up">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="18,15 12,9 6,15"/>
          </svg>
        </button>
        <button type="button" class="block-reorder-btn" data-automation-action="move-action" data-automation-index="${index}" data-automation-delta="1" title="Move down" aria-label="Move down">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <polyline points="6,9 12,15 18,9"/>
          </svg>
        </button>
      </div>
      <button type="button" class="block-remove-btn" data-automation-action="remove-action" data-automation-index="${index}" title="Remove">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="3,6 5,6 21,6"/>
          <path d="M19,6v14a2,2 0 0,1 -2,2H7a2,2 0 0,1 -2,-2V6m3,0V4a2,2 0 0,1 2,-2h4a2,2 0 0,1 2,2v2"/>
        </svg>
      </button>
    </div>
    <div class="block-body">
      <div class="form-group">
        <label>Action Type <span class="required">*</span></label>
        <select name="actions[${index}][type]" class="input action-type-select" required data-automation-change="action-type" data-automation-index="${index}">
          <option value="">Select action…</option>
          ${options}
        </select>
      </div>
      <div class="block-config action-config-${index}"></div>
    </div>
  `;

  list.appendChild(div);
  if (action.type) loadActionConfig(index, action.type, action.config || {});
  updateReorderButtons();
}

function moveAction(index, direction) {
  const list = document.getElementById('actions-list');
  const items = Array.from(list.querySelectorAll('.action-item'));
  const fromIdx = items.findIndex(el => parseInt(el.dataset.index) === index);
  const toIdx = fromIdx + direction;

  if (toIdx < 0 || toIdx >= items.length) return;

  const moving = items[fromIdx];
  const target = items[toIdx];

  if (direction === -1) {
    list.insertBefore(moving, target);
  } else {
    list.insertBefore(target, moving);
  }

  renumberBlocks('action-item', 'Action');
  updateReorderButtons();
}

function updateReorderButtons() {
  const list = document.getElementById('actions-list');
  if (!list) return;
  const items = Array.from(list.querySelectorAll('.action-item'));
  items.forEach((item, i) => {
    const btns = item.querySelectorAll('.block-reorder-btn');
    if (btns[0]) btns[0].disabled = (i === 0);
    if (btns[1]) btns[1].disabled = (i === items.length - 1);
  });
}

function removeAction(index) {
  const item = document.querySelector(`.action-item[data-index="${index}"]`);
  if (item) item.remove();
  renumberBlocks('action-item', 'Action');
  updateReorderButtons();
}

function renumberBlocks(className, label) {
  document.querySelectorAll('.' + className).forEach((el, i) => {
    el.dataset.index = i;
    const title = el.querySelector('.block-header-title');
    if (title) title.textContent = label + ' ' + (i + 1);
    // Update select name indices
    el.querySelectorAll('[name]').forEach(input => {
      input.name = input.name.replace(/\[\d+\]/, `[${i}]`);
    });
    // Update onclick indices
    const removeBtn = el.querySelector('.block-remove-btn');
    if (removeBtn) {
      const lowerLabel = label.toLowerCase();
      removeBtn.setAttribute('onclick', `remove${label}(${i})`);
    }
    const orderBtns = el.querySelectorAll('.block-reorder-btn');
    if (orderBtns.length === 2) {
      orderBtns[0].setAttribute('onclick', `moveAction(${i}, -1)`);
      orderBtns[1].setAttribute('onclick', `moveAction(${i}, 1)`);
    }
  });
}

// ============================================================
// Block config renderers
// ============================================================

function loadConditionConfig(index, conditionType, config = {}) {
  const container = document.querySelector(`.condition-config-${index}`);
  if (!container || !conditionType) return;
  const condition = availableBlocks.conditions[conditionType];
  if (!condition) { container.innerHTML = ''; return; }
  renderBlockConfig(container, condition.schema || {}, config, `conditions[${index}][config]`);
}

function loadActionConfig(index, actionType, config = {}) {
  const container = document.querySelector(`.action-config-${index}`);
  if (!container || !actionType) return;
  const action = availableBlocks.actions[actionType];
  if (!action) { container.innerHTML = ''; return; }
  renderBlockConfig(container, action.schema || {}, config, `actions[${index}][config]`);
}

function renderBlockConfig(container, schema, config, namePrefix) {
  container.innerHTML = '';

  Object.keys(schema).forEach(field => {
    const fieldSchema = schema[field];
    const div = document.createElement('div');
    div.className = 'form-group';

    const label = document.createElement('label');
    label.textContent = fieldSchema.label || field;
    if (fieldSchema.required) label.innerHTML += ' <span class="required">*</span>';
    div.appendChild(label);

    if (fieldSchema.description) {
      const help = document.createElement('small');
      help.className = 'form-help';
      help.textContent = fieldSchema.description;
      div.appendChild(help);
    }

    const input = buildFieldInput(fieldSchema, field, config[field], `${namePrefix}[${field}]`, config);
    if (fieldSchema.required && input.tagName !== 'DIV') input.required = true;
    div.appendChild(input);

    container.appendChild(div);
  });
}

// ============================================================
// Duplicate workflow (M3-T4)
// ============================================================

async function duplicateAutomation(automationId) {
  const source = automations.find(a => a.id === automationId);
  if (!source) return;

  const copy = JSON.parse(JSON.stringify(source));
  delete copy.id;
  copy.name = (copy.name || 'Automation') + ' (Copy)';
  copy.enabled = false;
  copy._csrf = getCsrfToken();

  try {
    const response = await fetch(apiUrl('/api/automation.php?action=create'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(copy)
    });
    const result = await response.json();
    if (result.status === 'ok') {
      showMessage('Automation duplicated.', 'success');
      loadAutomations();
    } else {
      showMessage('Error: ' + result.message, 'error');
    }
  } catch (error) {
    showMessage('Error: ' + error.message, 'error');
  }
}

// ============================================================
// Dry-run test (M3-T4, D4)
// ============================================================

async function dryRunAutomation(automationId) {
  const modal = document.getElementById('automation-logs-modal');
  const title = document.getElementById('automation-logs-title');
  const content = document.getElementById('automation-logs-content');

  const automation = automations.find(a => a.id === automationId);
  title.textContent = `Test Run: ${automation ? automation.name : automationId}`;
  content.innerHTML = '<div class="loading-state">Running dry-run…</div>';
  showAutomationModal(modal);

  try {
    const response = await fetch(apiUrl('/api/automation.php?action=dry_run'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ automation_id: automationId, context: {}, _csrf: getCsrfToken() })
    });
    const result = await response.json();

    if (result.status === 'ok') {
      renderDryRunResult(result.data, automationId);
    } else {
      content.innerHTML = `<div class="error-state">Error: ${escapeHtml(result.message)}</div>`;
    }
  } catch (error) {
    content.innerHTML = `<div class="error-state">Error: ${escapeHtml(error.message)}</div>`;
  }
}

function renderDryRunResult(data, automationId) {
  const content = document.getElementById('automation-logs-content');
  const steps = data.steps || [];
  const actionCount = data.action_count || 0;

  const iconFor = status => {
    if (status === 'pass') return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20,6 9,17 4,12"/></svg>';
    if (status === 'skip') return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>';
    if (status === 'warn') return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
    return '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
  };

  const stepsHtml = steps.map(step => `
    <div class="dry-run-step ${step.status || 'info'}">
      <div class="dry-run-step-icon">${iconFor(step.status)}</div>
      <div>
        <div class="dry-run-step-label">${escapeHtml(step.label || '')}</div>
        <div class="dry-run-step-detail">${escapeHtml(step.detail || '')}</div>
      </div>
    </div>
  `).join('');

  const setup = data.setup || {};
  const setupNotes = []
    .concat(setup.blocking || [])
    .concat(setup.warnings || [])
    .slice(0, 3)
    .map((note) => `<li>${escapeHtml(note)}</li>`)
    .join('');

  // D4: dry-run only; show live-run confirm section
  content.innerHTML = `
    <div class="dry-run-panel" style="background:none;border:none;padding:0">
      <div style="margin-bottom:var(--space-3)">
        <span class="dry-run-badge">Dry Run — nothing sent</span>
        <p style="font-size:0.8125rem;color:var(--text-secondary);margin:var(--space-2) 0 0 0">
          Simulation only. Emails, Slack, Teams, and webhooks were <strong>not</strong> sent.
          “Succeeded” here means the workflow config looks valid.
        </p>
        ${setupNotes ? `<ul style="margin:var(--space-2) 0 0;padding-left:1.1rem;font-size:0.8125rem;color:var(--warning-700,#b45309)">${setupNotes}</ul>` : ''}
      </div>
      <div class="dry-run-steps">${stepsHtml}</div>
      <div class="dry-run-confirm-bar">
        <p>
          <strong>Run for real?</strong>
          This will execute ${actionCount} action${actionCount !== 1 ? 's' : ''} live (real email/webhook side effects).
        </p>
        <button type="button" class="btn btn-sm btn-warning" data-automation-action="confirm-live-run" data-automation-id="${escapeAttr(automationId)}">
          Confirm &amp; Run Live
        </button>
      </div>
    </div>
  `;
}

let liveRunInFlight = false;
let lastLiveRunStartedAt = 0;
async function confirmLiveRun(automationId) {
  const now = Date.now();
  if (liveRunInFlight || (now - lastLiveRunStartedAt) < 8000) return;
  if (!confirm('Run this automation live now? Real actions (emails, webhooks, etc.) will execute.')) return;

  liveRunInFlight = true;
  lastLiveRunStartedAt = Date.now();
  const content = document.getElementById('automation-logs-content');
  content.innerHTML = '<div class="loading-state">Running live…</div>';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  try {
    const response = await fetch(apiUrl('/api/automation.php?action=run_live'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        automation_id: automationId,
        skip_conditions: true,
        context: {},
        _csrf: csrf
      })
    });
    const result = await response.json();

    if (result.status === 'ok') {
      const ok = result.data.success !== false;
      const errors = Array.isArray(result.data.errors) ? result.data.errors : [];
      const errorHtml = errors.length
        ? `<br><strong>Error:</strong> ${escapeHtml(errors.join(' | '))}`
        : '';
      const delivery = result.data.delivery_note
        ? `<br>${escapeHtml(result.data.delivery_note)}`
        : '<br>Check Automation → Setup if delivery channels are not configured.';
      content.innerHTML = `
        <div style="padding:var(--space-4)">
          <p style="color:${ok ? 'var(--success-600)' : 'var(--warning-600)'};font-weight:600;margin:0 0 var(--space-2) 0">
            ${ok ? '✓ Live run completed' : 'Live run finished with warnings'}
          </p>
          <p style="font-size:0.875rem;color:var(--text-secondary);margin:0">
            Runtime: ${result.data.runtime_ms ? result.data.runtime_ms.toFixed(1) + 'ms' : 'n/a'}<br>
            Actions executed: ${result.data.actions_executed ?? 'n/a'}
            ${errorHtml}
            ${delivery}
          </p>
        </div>
      `;
    } else {
      content.innerHTML = `<div class="error-state">Error: ${escapeHtml(result.message)}</div>`;
    }
  } catch (error) {
    content.innerHTML = `<div class="error-state">Error: ${escapeHtml(error.message)}</div>`;
  } finally {
    liveRunInFlight = false;
  }
}

// ============================================================
// Save automation
// ============================================================

async function saveAutomation() {
  if (automationEditorMode === 'canvas') {
    initNodeEditor();
    if (!nodeEditor) {
      showMessage('Node editor not available.', 'error');
      return;
    }
    const errors = nodeEditor.validate();
    if (errors.length) {
      showMessage(errors[0], 'error');
      syncCanvasValidation();
      return;
    }
    applyWorkflowToForm(nodeEditor.toWorkflow());
  }

  const form = document.getElementById('automation-form');
  const formData = new FormData(form);

  const automationData = {
    name: formData.get('name'),
    enabled: formData.get('enabled') === 'on',
    stop_on_failure: formData.get('stop_on_failure') === 'on',
    trigger: {
      type: formData.get('trigger[type]'),
      config: {}
    },
    conditions: [],
    actions: []
  };

  formData.forEach((value, key) => {
    const m = key.match(/^trigger\[config\]\[([^\]]+)\]$/);
    if (m) automationData.trigger.config[m[1]] = value;
  });

  const conditionItems = document.querySelectorAll('.condition-item');
  conditionItems.forEach((item, index) => {
    const typeSelect = item.querySelector('.condition-type-select');
    if (typeSelect && typeSelect.value) {
      const condition = { type: typeSelect.value, config: {} };
      formData.forEach((value, key) => {
        const m = key.match(new RegExp(`^conditions\\[${index}\\]\\[config\\]\\[([^\\]]+)\\]$`));
        if (m) condition.config[m[1]] = value;
      });
      automationData.conditions.push(condition);
    }
  });

  const actionItems = document.querySelectorAll('.action-item');
  actionItems.forEach((item, index) => {
    const typeSelect = item.querySelector('.action-type-select');
    if (typeSelect && typeSelect.value) {
      const action = { type: typeSelect.value, config: {} };
      formData.forEach((value, key) => {
        const m = key.match(new RegExp(`^actions\\[${index}\\]\\[config\\]\\[([^\\]]+)\\]$`));
        if (m) action.config[m[1]] = value;
      });
      automationData.actions.push(action);
    }
  });

  if (!automationData.name || !automationData.trigger.type || automationData.actions.length === 0) {
    showMessage('Name, trigger, and at least one action are required.', 'error');
    return;
  }

  try {
    const automationId = formData.get('automation_id');
    const endpoint = automationId ? 'update' : 'create';
    if (automationId) automationData.automation_id = automationId;
    automationData._csrf = getCsrfToken();

    const response = await fetch(apiUrl('/api/automation.php?action=' + endpoint), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(automationData)
    });
    const result = await response.json();

    if (result.status === 'ok') {
      showMessage('Automation saved.', 'success');
      closeAutomationModal();
      loadAutomations();
    } else {
      showMessage('Error: ' + result.message, 'error');
    }
  } catch (error) {
    showMessage('Error: ' + error.message, 'error');
  }
}

// ============================================================
// Toggle / Delete / Logs
// ============================================================

async function toggleAutomation(automationId, currentState) {
  const automation = automations.find(a => a.id === automationId);
  if (!automation) return;

  try {
    const response = await fetch(apiUrl('/api/automation.php?action=update'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ automation_id: automationId, enabled: !currentState, _csrf: getCsrfToken() })
    });
    const result = await response.json();
    if (result.status === 'ok') {
      loadAutomations();
    } else {
      showMessage('Error: ' + result.message, 'error');
    }
  } catch (error) {
    showMessage('Error: ' + error.message, 'error');
  }
}

async function deleteAutomation(automationId) {
  if (!confirm('Delete this automation? This cannot be undone.')) return;

  try {
    const response = await fetch(apiUrl('/api/automation.php?action=delete'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ automation_id: automationId, _csrf: getCsrfToken() })
    });
    const result = await response.json();
    if (result.status === 'ok') {
      showMessage('Automation deleted.', 'success');
      loadAutomations();
    } else {
      showMessage('Error: ' + result.message, 'error');
    }
  } catch (error) {
    showMessage('Error: ' + error.message, 'error');
  }
}

async function viewLogs(automationId) {
  const automation = automations.find(a => a.id === automationId);
  document.getElementById('automation-logs-title').textContent = `Logs: ${automation ? automation.name : automationId}`;
  showAutomationModal(document.getElementById('automation-logs-modal'));

  const content = document.getElementById('automation-logs-content');
  content.innerHTML = '<div class="loading-state">Loading logs…</div>';

  try {
    const response = await fetch(apiUrl(`/api/automation.php?action=get_logs&automation_id=${encodeURIComponent(automationId)}&limit=50`));
    const result = await response.json();
    if (result.status === 'ok') {
      renderLogs(result.data.logs || []);
    } else {
      content.innerHTML = `<div class="error-state">Error: ${escapeHtml(result.message)}</div>`;
    }
  } catch (error) {
    content.innerHTML = `<div class="error-state">Error: ${escapeHtml(error.message)}</div>`;
  }
}

function renderLogs(logs) {
  const content = document.getElementById('automation-logs-content');

  if (logs.length === 0) {
    content.innerHTML = '<p class="form-help" style="text-align:center;padding:2rem 0">No execution logs yet.</p>';
    return;
  }

  content.innerHTML = '<div class="logs-list">' + logs.map(log => {
    const status = log.status || 'unknown';
    const executedAt = log.executed_at ? new Date(log.executed_at).toLocaleString() : '';
    const runtime = log.runtime_ms ? log.runtime_ms.toFixed(1) + 'ms' : '';

    return `
      <div class="log-item ${status}">
        <div class="log-header">
          <span class="log-status ${status}">${status}</span>
          <span class="log-time">${executedAt}</span>
          ${runtime ? `<span class="log-runtime">${runtime}</span>` : ''}
        </div>
        ${log.errors?.length ? `
          <div class="log-body">
            <div class="log-errors">
              <strong>Errors:</strong>
              <ul>${log.errors.map(e => `<li>${escapeHtml(e)}</li>`).join('')}</ul>
            </div>
          </div>
        ` : ''}
        ${log.results?.length ? `
          <div class="log-body">
            <div class="log-results">
              <strong>Results:</strong>
              <ul>${log.results.map(r => `<li>${escapeHtml(r.action_type || 'action')}: ${r.success ? 'Success' : 'Failed'}</li>`).join('')}</ul>
            </div>
          </div>
        ` : ''}
      </div>
    `;
  }).join('') + '</div>';
}

function closeLogsModal() {
  hideAutomationModal(document.getElementById('automation-logs-modal'));
}

// ============================================================
// Utilities
// ============================================================

function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') : '';
}

function escapeHtml(str) {
  if (str == null) return '';
  const div = document.createElement('div');
  div.textContent = String(str);
  return div.innerHTML;
}

function escapeAttr(str) {
  if (str == null) return '';
  return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function showMessage(message, type) {
  // Use admin notification system if available
  if (window.Birkly && typeof Birkly.notify === 'function') {
    Birkly.notify(message, type);
    return;
  }
  // Fallback: inline banner at top of page
  const existing = document.getElementById('automation-inline-message');
  if (existing) existing.remove();
  const banner = document.createElement('div');
  banner.id = 'automation-inline-message';
  banner.className = type === 'error' ? 'alert alert-danger' : 'alert alert-success';
  banner.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:9999;padding:.75rem 1.25rem;border-radius:.5rem;max-width:380px;box-shadow:0 4px 12px rgba(0,0,0,.15);font-size:.875rem';
  banner.style.background = type === 'error' ? 'var(--error-100,#fee2e2)' : 'var(--success-100,#dcfce7)';
  banner.style.color = type === 'error' ? 'var(--error-700,#b91c1c)' : 'var(--success-700,#15803d)';
  banner.style.border = '1px solid ' + (type === 'error' ? 'var(--error-200,#fecaca)' : 'var(--success-200,#bbf7d0)');
  banner.textContent = message;
  document.body.appendChild(banner);
  setTimeout(() => banner.remove(), 4000);
}
