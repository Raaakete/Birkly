/**
 * Automation admin page — builder UI, test-run, template preview, inbound webhooks.
 */
(function () {
    'use strict';

    let automations = [];
    let availableBlocks = { triggers: {}, conditions: {}, actions: {} };
    let inboundWebhooks = [];
    let currentAutomation = null;

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function showMessage(message, type) {
        if (window.Birkly && window.Birkly.Utils && typeof window.Birkly.Utils.showToast === 'function') {
            window.Birkly.Utils.showToast(message, type || 'info');
        } else {
            BirklyFeedback.toast(message, 'error')
        }
    }

    async function loadAutomations() {
        const container = document.getElementById('automation-list');
        try {
            const response = await fetch('/api/automation.php?action=list');
            const result = await response.json();
            if (result.status === 'ok') {
                automations = result.data.automations || [];
                renderAutomations();
            } else {
                showMessage('Error loading automations: ' + (result.message || 'Unknown'), 'error');
            }
        } catch (error) {
            if (container) {
                container.innerHTML = '<div class="error-state">Failed to load automations.</div>';
            }
            showMessage('Error loading automations: ' + error.message, 'error');
        }
    }

    async function loadBlocks() {
        try {
            const response = await fetch('/api/automation.php?action=get_blocks');
            const result = await response.json();
            if (result.status === 'ok') {
                availableBlocks = result.data.blocks || { triggers: {}, conditions: {}, actions: {} };
                populateTriggerSelect();
            }
        } catch (error) {
            console.error('Error loading blocks:', error);
        }
    }

    async function loadWebhooks() {
        const container = document.getElementById('inbound-webhooks-list');
        if (!container) return;
        container.innerHTML = '<div class="loading-state">Loading webhooks…</div>';
        try {
            const response = await fetch('/api/automation.php?action=list_webhooks');
            const result = await response.json();
            if (result.status === 'ok') {
                inboundWebhooks = result.data.webhooks || [];
                renderWebhooks();
            } else {
                container.innerHTML = '<p class="form-text">Could not load webhooks.</p>';
            }
        } catch (error) {
            container.innerHTML = '<p class="form-text">Could not load webhooks.</p>';
        }
    }

    function renderWebhooks() {
        const container = document.getElementById('inbound-webhooks-list');
        if (!container) return;
        if (inboundWebhooks.length === 0) {
            container.innerHTML = '<p class="form-text">No inbound webhooks yet. Create one to receive external triggers.</p>';
            return;
        }
        container.innerHTML = inboundWebhooks.map(function (wh) {
            return (
                '<div class="webhook-card">' +
                '<div><strong>' + escapeHtml(wh.name || wh.id) + '</strong>' +
                '<div class="form-help">ID: ' + escapeHtml(wh.id) + '</div>' +
                '<div class="form-help">POST ' + escapeHtml(wh.url || '') + '</div></div>' +
                '<button type="button" class="btn btn-sm btn-danger" data-webhook-id="' + escapeHtml(wh.id) + '">Delete</button>' +
                '</div>'
            );
        }).join('');
        container.querySelectorAll('[data-webhook-id]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                deleteWebhook(btn.getAttribute('data-webhook-id'));
            });
        });
    }

    async function createWebhook() {
        const nameInput = document.getElementById('new-webhook-name');
        const name = nameInput ? nameInput.value.trim() : '';
        try {
            const response = await fetch('/api/automation.php?action=create_webhook', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: name || 'Inbound Webhook', _csrf: getCsrfToken() })
            });
            const result = await response.json();
            if (result.status === 'ok') {
                const wh = result.data.webhook;
                showMessage('Webhook created. Copy the full URL with token now — it will not be shown again in the list.', 'success');
                if (wh && wh.url) {
                    prompt('Inbound webhook URL (save this):', window.location.origin + wh.url);
                }
                if (nameInput) nameInput.value = '';
                loadWebhooks();
            } else {
                showMessage(result.message || 'Failed to create webhook', 'error');
            }
        } catch (error) {
            showMessage(error.message, 'error');
        }
    }

    async function deleteWebhook(webhookId) {
        if (!(await BirklyFeedback.confirm('Delete this inbound webhook?'))) return;
        try {
            const response = await fetch('/api/automation.php?action=delete_webhook', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ webhook_id: webhookId, _csrf: getCsrfToken() })
            });
            const result = await response.json();
            if (result.status === 'ok') {
                loadWebhooks();
            } else {
                showMessage(result.message || 'Delete failed', 'error');
            }
        } catch (error) {
            showMessage(error.message, 'error');
        }
    }

    function populateTriggerSelect() {
        const select = document.getElementById('trigger-type');
        if (!select) return;
        select.innerHTML = '<option value="">Select trigger…</option>';
        Object.keys(availableBlocks.triggers || {}).forEach(function (triggerId) {
            const trigger = availableBlocks.triggers[triggerId];
            const option = document.createElement('option');
            option.value = triggerId;
            option.textContent = trigger.name || triggerId;
            select.appendChild(option);
        });
    }

    function renderAutomations() {
        const container = document.getElementById('automation-list');
        if (!container) return;
        if (automations.length === 0) {
            container.innerHTML =
                '<div class="empty-state"><p>No automations yet.</p>' +
                '<button type="button" class="btn btn-primary" id="create-first-automation">Create First Automation</button></div>';
            const btn = document.getElementById('create-first-automation');
            if (btn) btn.addEventListener('click', openCreateAutomationModal);
            return;
        }
        container.innerHTML = automations.map(function (automation) {
            const id = escapeHtml(automation.id);
            return (
                '<div class="automation-card">' +
                '<div class="automation-card-header">' +
                '<h3>' + escapeHtml(automation.name || 'Untitled') + '</h3>' +
                '<div class="automation-card-meta">' +
                '<span class="status-badge ' + (automation.enabled ? 'active' : 'inactive') + '">' +
                (automation.enabled ? 'Enabled' : 'Disabled') + '</span>' +
                '<span>Trigger: ' + escapeHtml((automation.trigger && automation.trigger.type) || 'unknown') + '</span>' +
                '<span>' + ((automation.actions && automation.actions.length) || 0) + ' action(s)</span>' +
                '</div></div>' +
                '<div class="automation-card-actions">' +
                '<button type="button" class="btn btn-sm btn-secondary" data-edit="' + id + '">Edit</button>' +
                '<button type="button" class="btn btn-sm btn-secondary" data-logs="' + id + '">Logs</button>' +
                '<button type="button" class="btn btn-sm btn-secondary" data-test="' + id + '">Test</button>' +
                '<button type="button" class="btn btn-sm ' + (automation.enabled ? 'btn-warning' : 'btn-primary') + '" data-toggle="' + id + '" data-enabled="' + (automation.enabled ? '1' : '0') + '">' +
                (automation.enabled ? 'Disable' : 'Enable') + '</button>' +
                '<button type="button" class="btn btn-sm btn-danger" data-delete="' + id + '">Delete</button>' +
                '</div></div>'
            );
        }).join('');

        container.querySelectorAll('[data-edit]').forEach(function (btn) {
            btn.addEventListener('click', async function () { editAutomation(btn.getAttribute('data-edit')); });
        });
        container.querySelectorAll('[data-logs]').forEach(function (btn) {
            btn.addEventListener('click', async function () { viewLogs(btn.getAttribute('data-logs')); });
        });
        container.querySelectorAll('[data-test]').forEach(function (btn) {
            btn.addEventListener('click', async function () { testAutomation(btn.getAttribute('data-test')); });
        });
        container.querySelectorAll('[data-toggle]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                toggleAutomation(btn.getAttribute('data-toggle'), btn.getAttribute('data-enabled') === '1');
            });
        });
        container.querySelectorAll('[data-delete]').forEach(function (btn) {
            btn.addEventListener('click', async function () { deleteAutomation(btn.getAttribute('data-delete')); });
        });
    }

    function openCreateAutomationModal() {
        currentAutomation = null;
        document.getElementById('automation-modal-title').textContent = 'Create Automation';
        document.getElementById('automation-form').reset();
        document.getElementById('automation-id').value = '';
        document.getElementById('conditions-list').innerHTML = '';
        document.getElementById('actions-list').innerHTML = '';
        document.getElementById('trigger-config').style.display = 'none';
        clearPreviewPanel();
        document.getElementById('automation-modal').classList.add('active');
    }

    function closeAutomationModal() {
        document.getElementById('automation-modal').classList.remove('active');
    }

    async function editAutomation(automationId) {
        try {
            const response = await fetch('/api/automation.php?action=get&automation_id=' + encodeURIComponent(automationId));
            const result = await response.json();
            if (result.status === 'ok') {
                currentAutomation = result.data.automation;
                populateAutomationForm(currentAutomation);
                document.getElementById('automation-modal-title').textContent = 'Edit Automation';
                document.getElementById('automation-modal').classList.add('active');
            } else {
                showMessage('Error loading automation: ' + result.message, 'error');
            }
        } catch (error) {
            showMessage(error.message, 'error');
        }
    }

    function populateAutomationForm(automation) {
        document.getElementById('automation-id').value = automation.id || '';
        document.getElementById('automation-name').value = automation.name || '';
        document.getElementById('automation-enabled').checked = automation.enabled !== false;
        document.getElementById('stop-on-failure').checked = !!automation.stop_on_failure;
        if (automation.trigger) {
            document.getElementById('trigger-type').value = automation.trigger.type || '';
            loadTriggerConfig(automation.trigger.type, automation.trigger.config || {});
        }
        const conditionsList = document.getElementById('conditions-list');
        conditionsList.innerHTML = '';
        (automation.conditions || []).forEach(function (condition, index) {
            addConditionToForm(condition, index);
        });
        const actionsList = document.getElementById('actions-list');
        actionsList.innerHTML = '';
        (automation.actions || []).forEach(function (action, index) {
            addActionToForm(action, index);
        });
    }

    function loadTriggerConfig(triggerType, config) {
        config = config || {};
        const container = document.getElementById('trigger-config');
        if (!triggerType || !container) {
            if (container) container.style.display = 'none';
            return;
        }
        const trigger = availableBlocks.triggers[triggerType];
        if (!trigger) {
            container.style.display = 'none';
            return;
        }
        container.style.display = 'block';
        renderBlockConfig(container, trigger.schema || {}, config, 'trigger[config]');
    }

    function bindTriggerTypeChange() {
        const select = document.getElementById('trigger-type');
        if (!select || select.dataset.bound) return;
        select.dataset.bound = '1';
        select.addEventListener('change', function () {
            loadTriggerConfig(this.value);
        });
    }

    function addCondition() {
        const conditionsList = document.getElementById('conditions-list');
        addConditionToForm({ type: '', config: {} }, conditionsList.children.length);
    }

    function addConditionToForm(condition, index) {
        const conditionsList = document.getElementById('conditions-list');
        const div = document.createElement('div');
        div.className = 'condition-item';
        div.dataset.index = String(index);
        const conditionBlocks = availableBlocks.conditions || {};
        const options = Object.keys(conditionBlocks).map(function (id) {
            const block = conditionBlocks[id];
            return '<option value="' + escapeHtml(id) + '"' + (condition.type === id ? ' selected' : '') + '>' + escapeHtml(block.name || id) + '</option>';
        }).join('');
        div.innerHTML =
            '<div class="block-header"><h4>Condition ' + (index + 1) + '</h4>' +
            '<button type="button" class="btn-icon" data-remove-condition="' + index + '">×</button></div>' +
            '<div class="form-group"><label>Condition Type</label>' +
            '<select name="conditions[' + index + '][type]" class="input condition-type-select" data-condition-index="' + index + '">' +
            '<option value="">Select condition…</option>' + options + '</select></div>' +
            '<div class="block-config condition-config-' + index + '"></div>';
        conditionsList.appendChild(div);
        div.querySelector('[data-remove-condition]').addEventListener('click', async function () { div.remove(); });
        const typeSelect = div.querySelector('.condition-type-select');
        typeSelect.addEventListener('change', function () {
            loadConditionConfig(index, this.value, {});
        });
        if (condition.type) {
            typeSelect.value = condition.type;
            loadConditionConfig(index, condition.type, condition.config || {});
        }
    }

    function addAction() {
        const actionsList = document.getElementById('actions-list');
        addActionToForm({ type: '', config: {} }, actionsList.children.length);
    }

    function addActionToForm(action, index) {
        const actionsList = document.getElementById('actions-list');
        const div = document.createElement('div');
        div.className = 'action-item';
        div.dataset.index = String(index);
        const actionBlocks = availableBlocks.actions || {};
        const options = Object.keys(actionBlocks).map(function (id) {
            const block = actionBlocks[id];
            return '<option value="' + escapeHtml(id) + '"' + (action.type === id ? ' selected' : '') + '>' + escapeHtml(block.name || id) + '</option>';
        }).join('');
        div.innerHTML =
            '<div class="block-header"><h4>Action ' + (index + 1) + '</h4>' +
            '<button type="button" class="btn-icon" data-remove-action="' + index + '">×</button></div>' +
            '<div class="form-group"><label>Action Type <span class="required">*</span></label>' +
            '<select name="actions[' + index + '][type]" class="input action-type-select" required data-action-index="' + index + '">' +
            '<option value="">Select action…</option>' + options + '</select></div>' +
            '<div class="block-config action-config-' + index + '"></div>';
        actionsList.appendChild(div);
        div.querySelector('[data-remove-action]').addEventListener('click', async function () { div.remove(); });
        const typeSelect = div.querySelector('.action-type-select');
        typeSelect.addEventListener('change', function () {
            loadActionConfig(index, this.value, {});
        });
        if (action.type) {
            typeSelect.value = action.type;
            loadActionConfig(index, action.type, action.config || {});
        }
    }

    function loadConditionConfig(index, conditionType, config) {
        const container = document.querySelector('.condition-config-' + index);
        if (!container || !conditionType) return;
        const condition = availableBlocks.conditions[conditionType];
        if (!condition) {
            container.innerHTML = '';
            return;
        }
        renderBlockConfig(container, condition.schema || {}, config || {}, 'conditions[' + index + '][config]');
    }

    function loadActionConfig(index, actionType, config) {
        const container = document.querySelector('.action-config-' + index);
        if (!container || !actionType) return;
        const action = availableBlocks.actions[actionType];
        if (!action) {
            container.innerHTML = '';
            return;
        }
        renderBlockConfig(container, action.schema || {}, config || {}, 'actions[' + index + '][config]');
    }

    function renderBlockConfig(container, schema, config, namePrefix) {
        container.innerHTML = '';
        Object.keys(schema).forEach(function (field) {
            const fieldSchema = schema[field];
            const div = document.createElement('div');
            div.className = 'form-group';
            const label = document.createElement('label');
            label.textContent = fieldSchema.label || field;
            if (fieldSchema.required) {
                label.innerHTML += ' <span class="required">*</span>';
            }
            div.appendChild(label);
            if (fieldSchema.description) {
                const help = document.createElement('small');
                help.className = 'form-help';
                help.textContent = fieldSchema.description;
                div.appendChild(help);
            }
            let input;
            if (fieldSchema.type === 'select') {
                input = document.createElement('select');
                input.className = 'input';
                input.name = namePrefix + '[' + field + ']';
                (fieldSchema.options || []).forEach(function (opt) {
                    const option = document.createElement('option');
                    option.value = opt.value;
                    option.textContent = opt.label;
                    if (config[field] === opt.value) option.selected = true;
                    input.appendChild(option);
                });
            } else if (fieldSchema.type === 'textarea') {
                input = document.createElement('textarea');
                input.className = 'input template-field';
                input.name = namePrefix + '[' + field + ']';
                input.rows = fieldSchema.rows || 4;
                input.value = config[field] || '';
                input.dataset.previewField = '1';
            } else {
                input = document.createElement('input');
                input.type = fieldSchema.type || 'text';
                input.className = 'input' + ((fieldSchema.type === 'string' || !fieldSchema.type) ? ' template-field' : '');
                input.name = namePrefix + '[' + field + ']';
                input.value = config[field] || '';
                if (fieldSchema.placeholder) input.placeholder = fieldSchema.placeholder;
                if (!fieldSchema.type || fieldSchema.type === 'string') {
                    input.dataset.previewField = '1';
                }
            }
            if (fieldSchema.required) input.required = true;
            div.appendChild(input);
            container.appendChild(div);
        });
    }

    function collectAutomationData(form) {
        const formData = new FormData(form);
        const automationData = {
            name: formData.get('name'),
            enabled: formData.get('enabled') === 'on',
            stop_on_failure: formData.get('stop_on_failure') === 'on',
            trigger: { type: formData.get('trigger[type]'), config: {} },
            conditions: [],
            actions: []
        };
        formData.forEach(function (value, key) {
            if (key.indexOf('trigger[config]') === 0) {
                const field = key.replace('trigger[config][', '').replace(']', '');
                automationData.trigger.config[field] = value;
            }
        });
        document.querySelectorAll('.condition-item').forEach(function (item, index) {
            const typeSelect = item.querySelector('.condition-type-select');
            if (typeSelect && typeSelect.value) {
                const condition = { type: typeSelect.value, config: {} };
                formData.forEach(function (value, key) {
                    if (key.indexOf('conditions[' + index + '][config]') === 0) {
                        const field = key.replace('conditions[' + index + '][config][', '').replace(']', '');
                        condition.config[field] = value;
                    }
                });
                automationData.conditions.push(condition);
            }
        });
        document.querySelectorAll('.action-item').forEach(function (item, index) {
            const typeSelect = item.querySelector('.action-type-select');
            if (typeSelect && typeSelect.value) {
                const action = { type: typeSelect.value, config: {} };
                formData.forEach(function (value, key) {
                    if (key.indexOf('actions[' + index + '][config]') === 0) {
                        const field = key.replace('actions[' + index + '][config][', '').replace(']', '');
                        action.config[field] = value;
                    }
                });
                automationData.actions.push(action);
            }
        });
        return automationData;
    }

    async function saveAutomation() {
        const form = document.getElementById('automation-form');
        const automationData = collectAutomationData(form);
        if (!automationData.name || !automationData.trigger.type || automationData.actions.length === 0) {
            showMessage('Please fill in all required fields', 'error');
            return;
        }
        try {
            const automationId = form.querySelector('[name="automation_id"]').value;
            const action = automationId ? 'update' : 'create';
            if (automationId) automationData.automation_id = automationId;
            automationData._csrf = getCsrfToken();
            const response = await fetch('/api/automation.php?action=' + action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(automationData)
            });
            const result = await response.json();
            if (result.status === 'ok') {
                showMessage('Automation saved successfully!', 'success');
                closeAutomationModal();
                loadAutomations();
            } else {
                showMessage('Error: ' + result.message, 'error');
            }
        } catch (error) {
            showMessage(error.message, 'error');
        }
    }

    async function testAutomation(automationId) {
        if (!automationId) {
            const form = document.getElementById('automation-form');
            automationId = form ? form.querySelector('[name="automation_id"]').value : '';
        }
        if (!automationId) {
            showMessage('Save the automation before testing', 'error');
            return;
        }
        const panel = document.getElementById('automation-test-result');
        if (panel) panel.textContent = 'Running test…';
        try {
            const response = await fetch('/api/automation.php?action=test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    automation_id: automationId,
                    context: { _test_mode: true },
                    _csrf: getCsrfToken()
                })
            });
            const result = await response.json();
            const text = result.status === 'ok'
                ? 'Test OK — runtime: ' + (result.data.runtime_ms || 0).toFixed(2) + 'ms, actions: ' + (result.data.actions_executed || 0)
                : 'Test failed: ' + (result.message || 'Unknown');
            if (panel) panel.textContent = text;
            showMessage(text, result.status === 'ok' ? 'success' : 'error');
        } catch (error) {
            if (panel) panel.textContent = error.message;
            showMessage(error.message, 'error');
        }
    }

    async function previewSelectedTemplate() {
        const field = document.querySelector('#automation-form .template-field:focus') ||
            document.querySelector('#automation-form .template-field');
        const template = field ? field.value : '';
        const triggerType = document.getElementById('trigger-type').value;
        const panel = document.getElementById('template-preview-output');
        if (!template) {
            showMessage('Enter template text in an action field first', 'error');
            return;
        }
        if (panel) panel.textContent = 'Rendering…';
        try {
            const response = await fetch('/api/automation.php?action=preview_template', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    template: template,
                    trigger_type: triggerType || null,
                    _csrf: getCsrfToken()
                })
            });
            const result = await response.json();
            if (result.status === 'ok' && panel) {
                panel.textContent = result.data.rendered || '';
            } else {
                if (panel) panel.textContent = result.message || 'Preview failed';
            }
        } catch (error) {
            if (panel) panel.textContent = error.message;
        }
    }

    function clearPreviewPanel() {
        const testPanel = document.getElementById('automation-test-result');
        const previewPanel = document.getElementById('template-preview-output');
        if (testPanel) testPanel.textContent = '';
        if (previewPanel) previewPanel.textContent = '';
    }

    async function toggleAutomation(automationId, currentState) {
        try {
            const response = await fetch('/api/automation.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    automation_id: automationId,
                    enabled: !currentState,
                    _csrf: getCsrfToken()
                })
            });
            const result = await response.json();
            if (result.status === 'ok') {
                loadAutomations();
            } else {
                showMessage(result.message, 'error');
            }
        } catch (error) {
            showMessage(error.message, 'error');
        }
    }

    async function deleteAutomation(automationId) {
        if (!(await BirklyFeedback.confirm('Delete this automation?'))) return;
        try {
            const response = await fetch('/api/automation.php?action=delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ automation_id: automationId, _csrf: getCsrfToken() })
            });
            const result = await response.json();
            if (result.status === 'ok') {
                showMessage('Automation deleted', 'success');
                loadAutomations();
            } else {
                showMessage(result.message, 'error');
            }
        } catch (error) {
            showMessage(error.message, 'error');
        }
    }

    async function viewLogs(automationId) {
        const automation = automations.find(function (a) { return a.id === automationId; });
        document.getElementById('automation-logs-title').textContent = 'Logs: ' + (automation ? automation.name : automationId);
        document.getElementById('automation-logs-modal').classList.add('active');
        const content = document.getElementById('automation-logs-content');
        content.innerHTML = '<div class="loading-state">Loading logs…</div>';
        try {
            const response = await fetch('/api/automation.php?action=get_logs&automation_id=' + encodeURIComponent(automationId) + '&limit=50');
            const result = await response.json();
            if (result.status === 'ok') {
                renderLogs(result.data.logs || []);
            } else {
                content.innerHTML = '<div class="error-state">' + escapeHtml(result.message) + '</div>';
            }
        } catch (error) {
            content.innerHTML = '<div class="error-state">' + escapeHtml(error.message) + '</div>';
        }
    }

    function renderLogs(logs) {
        const content = document.getElementById('automation-logs-content');
        if (!logs.length) {
            content.innerHTML = '<p class="form-text">No execution logs yet.</p>';
            return;
        }
        content.innerHTML = '<div class="logs-list">' + logs.map(function (log) {
            const status = log.status || 'unknown';
            const executedAt = log.executed_at ? new Date(log.executed_at).toLocaleString() : '';
            const runtime = log.runtime_ms ? log.runtime_ms.toFixed(2) + 'ms' : '';
            return (
                '<div class="log-item ' + status + '">' +
                '<div class="log-header"><span class="log-status">' + escapeHtml(status) + '</span>' +
                '<span class="log-time">' + escapeHtml(executedAt) + '</span>' +
                (runtime ? '<span class="log-runtime">' + runtime + '</span>' : '') + '</div>' +
                (log.errors && log.errors.length ? '<div class="log-errors"><ul>' +
                    log.errors.map(function (e) { return '<li>' + escapeHtml(e) + '</li>'; }).join('') + '</ul></div>' : '') +
                '</div>'
            );
        }).join('') + '</div>';
    }

    function closeLogsModal() {
        document.getElementById('automation-logs-modal').classList.remove('active');
    }

    function init() {
        bindTriggerTypeChange();
        const createBtn = document.getElementById('create-workflow-btn') || document.querySelector('.main-actions-bar .btn-primary');
        if (createBtn && !createBtn.dataset.bound) {
            createBtn.dataset.bound = '1';
            createBtn.addEventListener('click', openCreateAutomationModal);
        }
        const createWebhookBtn = document.getElementById('create-webhook-btn');
        if (createWebhookBtn) createWebhookBtn.addEventListener('click', createWebhook);
        const testBtn = document.getElementById('automation-test-btn');
        if (testBtn) testBtn.addEventListener('click', async function () {
            const id = document.getElementById('automation-id').value;
            testAutomation(id);
        });
        const previewBtn = document.getElementById('template-preview-btn');
        if (previewBtn) previewBtn.addEventListener('click', previewSelectedTemplate);
        loadBlocks().then(loadAutomations);
        loadWebhooks();
    }

    // Legacy onclick hooks from template
    window.openCreateAutomationModal = openCreateAutomationModal;
    window.closeAutomationModal = closeAutomationModal;
    window.addCondition = addCondition;
    window.addAction = addAction;
    window.saveAutomation = saveAutomation;
    window.closeLogsModal = closeLogsModal;
    window.loadConditionConfig = loadConditionConfig;
    window.loadActionConfig = loadActionConfig;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
