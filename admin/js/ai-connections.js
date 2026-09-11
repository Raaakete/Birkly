/**
 * AI Connections Management
 * Handles outbound BYOAI connection management and approval queue
 */

(function() {
    'use strict';

    let csrfToken = '';
    let initialized = false;
    let isEditMode = false;
    let currentEditingConnection = null;
    let wizardStep = 1;
    const WIZARD_MAX_STEP = 3;
    let providersCache = null;
    let providerModelsCache = [];
    let modelPickerLoading = false;
    let connectionTestPassed = false;

    function birklyApiUrl(path) {
        if (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function') {
            return Birkly.url(path);
        }
        return path;
    }

    function aiT(key, fallback) {
        if (typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function') {
            return Birkly.i18n.t('admin.' + key, fallback);
        }
        return fallback != null ? fallback : key;
    }

    function isAiIntegrationsContext() {
        const pane = document.getElementById('ai-integrations');
        if (!pane) return false;
        const hash = window.location.hash.replace('#', '');
        const aiHashes = [
            'ai-integrations',
            'ai-connections',
            'mcp-connections',
            'outbound-connections',
            'inbound-mcp'
        ];
        if (aiHashes.indexOf(hash) >= 0) {
            return true;
        }
        const aiTab = document.getElementById('ai');
        return !!(aiTab && aiTab.classList.contains('active'));
    }

    function initAiConnections() {
        if (initialized) return;
        initialized = true;

        csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        loadConnections();
        setupEventListeners();
    }

    function maybeInitAiConnections() {
        if (isAiIntegrationsContext()) {
            setTimeout(initAiConnections, 100);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        maybeInitAiConnections();
        window.addEventListener('hashchange', maybeInitAiConnections);
        window.addEventListener('birkly:ai-integrations-tab', maybeInitAiConnections);
    });

    function setupEventListeners() {
        document.getElementById('add-connection-btn')?.addEventListener('click', showAddConnectionModal);

        document.querySelectorAll('#add-connection-modal .modal-close').forEach(btn => {
            btn.addEventListener('click', hideAddConnectionModal);
        });

        document.getElementById('add-connection-form')?.addEventListener('submit', handleConnectionSubmit);
        document.getElementById('test-connection-btn')?.addEventListener('click', testConnection);

        document.getElementById('connection-provider')?.addEventListener('change', function() {
            updateProviderModels();
            updateProviderFields();
            connectionTestPassed = false;
            clearTestStatus();
        });

        document.getElementById('connection-model-refresh')?.addEventListener('click', function() {
            fetchProviderModels({ force: true }).catch(function(err) {
                console.error('Error loading models:', err);
            });
        });

        document.getElementById('connection-model-search')?.addEventListener('input', function() {
            renderModelPickerList(providerModelsCache, this.value);
        });

        document.getElementById('connection-model-search')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const value = this.value.trim();
                if (value) setModelPickerValue(value);
            }
        });

        document.getElementById('connection-model-list')?.addEventListener('click', function(e) {
            const btn = e.target.closest('.ai-model-picker-option[data-model-id]');
            if (!btn) return;
            setModelPickerValue(btn.getAttribute('data-model-id') || '');
        });

        document.getElementById('connection-model-search')?.addEventListener('blur', function() {
            const value = this.value.trim();
            if (value) setModelPickerValue(value);
        });

        document.getElementById('connection-api-key')?.addEventListener('input', function() {
            connectionTestPassed = false;
            clearTestStatus();
        });

        document.getElementById('connection-api-url')?.addEventListener('input', function() {
            connectionTestPassed = false;
            clearTestStatus();
        });

        const connectionsList = document.getElementById('connections-list');
        if (connectionsList && !connectionsList.dataset.actionsBound) {
            connectionsList.dataset.actionsBound = '1';
            connectionsList.addEventListener('click', async function (e) {
                const deleteBtn = e.target.closest('[data-delete-connection]');
                if (deleteBtn) {
                    e.preventDefault();
                    deleteConnection(deleteBtn.getAttribute('data-delete-connection'));
                    return;
                }
                const editBtn = e.target.closest('[data-edit-connection]');
                if (editBtn) {
                    e.preventDefault();
                    editConnection(editBtn.getAttribute('data-edit-connection'));
                }
            });
        }
    }

    async function loadConnections() {
        try {
            const response = await fetch(birklyApiUrl('/api/ai_connections.php?action=list'));
            const result = await response.json();

            if (result && result.status === 'ok' && result.data) {
                renderConnections(result.data.connections || []);
            } else {
                renderConnections([]);
            }
        } catch (error) {
            console.error('Error loading connections:', error);
            renderConnections([]);
        }
    }

    function renderConnections(connections) {
        const list = document.getElementById('connections-list');
        if (!list) return;

        const outbound = connections.filter(function (conn) {
            if (conn.provider === 'mcp') return false;
            return true;
        });
        const seen = new Set();
        const unique = outbound.filter(function (conn) {
            if (!conn.id || seen.has(conn.id)) return false;
            seen.add(conn.id);
            return true;
        });

        if (unique.length === 0) {
            const emptyTitle = escapeHtml(aiT('ai_conn_empty_assistant_title', 'No assistant connected'));
            const emptyBody = escapeHtml(aiT('ai_conn_empty_assistant_body', 'Click Quick connect to add your AI provider and API key for in-admin chat.'));
            list.innerHTML = '<div class="empty-state empty-state-friendly"><p><strong>' + emptyTitle + '</strong></p><p>' + emptyBody + '</p></div>';
            return;
        }

        const activeLabel = escapeHtml(aiT('webhook_active', 'Active'));
        const inactiveLabel = escapeHtml(aiT('webhook_inactive', 'Inactive'));
        const editLabel = escapeHtml(aiT('edit', 'Edit'));
        const deleteLabel = escapeHtml(aiT('delete', 'Delete'));

        list.innerHTML = unique.map(conn => `
            <div class="ai-connection-entry" data-connection-id="${escapeHtml(conn.id)}">
                <div class="ai-connection-entry-header">
                    <strong>${escapeHtml(conn.name || conn.provider)}</strong>
                    <span class="mcp-endpoint-status ${conn.status === 'active' ? 'active' : 'inactive'}">${conn.status === 'active' ? activeLabel : inactiveLabel}</span>
                </div>
                <div class="ai-connection-entry-meta">
                    <span class="connection-provider">${escapeHtml(conn.provider)}</span>
                    <span class="connection-role">${escapeHtml(conn.role || 'viewer')}</span>
                    ${conn.model ? `<span class="connection-model">${escapeHtml(conn.model)}</span>` : ''}
                </div>
                <div class="ai-connection-entry-actions">
                    <button type="button" class="btn btn-sm btn-secondary" data-edit-connection="${escapeHtml(conn.id)}">${editLabel}</button>
                    <button type="button" class="btn btn-sm btn-danger" data-delete-connection="${escapeHtml(conn.id)}">${deleteLabel}</button>
                </div>
            </div>
        `).join('');
    }

    function showAddConnectionModal() {
        const modal = document.getElementById('add-connection-modal');
        if (!modal) return;

        isEditMode = false;
        currentEditingConnection = null;
        connectionTestPassed = false;
        resetConnectionForm();

        const title = document.getElementById('connection-modal-title');
        const intro = document.getElementById('assistant-modal-intro');
        if (title) title.textContent = aiT('ai_conn_modal_quick_connect', 'Quick connect assistant');
        if (intro) intro.style.display = 'block';

        modal.style.display = 'flex';
        loadProviders().then(() => {
            updateProviderFields();
            updateProviderModels();
        });
        loadRoles();
        updateConnectionModalTitleAndButton();
    }

    function hideAddConnectionModal() {
        const modal = document.getElementById('add-connection-modal');
        if (modal) {
            modal.style.display = 'none';
            resetConnectionForm();
            hideWizardError();
        }
    }

    function resetConnectionForm() {
        const form = document.getElementById('add-connection-form');
        if (!form) return;

        form.reset();

        const idInput = document.getElementById('connection-id');
        if (idInput) idInput.value = '';

        const providerSelect = document.getElementById('connection-provider');
        if (providerSelect) providerSelect.disabled = false;

        const apiUrlGroup = document.getElementById('api-url-group');
        const apiKeyGroup = document.getElementById('api-key-group');
        const modelCustom = document.getElementById('connection-model-custom');
        if (apiUrlGroup) apiUrlGroup.style.display = 'none';
        if (apiKeyGroup) apiKeyGroup.style.display = 'block';
        if (modelCustom) modelCustom.style.display = 'none';
        resetModelPicker();

        const requireApprovalCheckbox = document.getElementById('connection-require-approval');
        if (requireApprovalCheckbox) requireApprovalCheckbox.checked = true;

        clearTestStatus();
        connectionTestPassed = false;
        updateConnectionModalTitleAndButton();
    }

    function setWizardMode() {
        // One-screen form — wizard UI removed
    }

    function showWizardStep(step) {
        wizardStep = step;
        document.querySelectorAll('.wizard-panel').forEach(panel => {
            const panelStep = parseInt(panel.getAttribute('data-wizard-step'), 10);
            panel.style.display = panelStep === step ? 'block' : 'none';
        });
        document.querySelectorAll('.wizard-step-indicator').forEach(ind => {
            const indStep = parseInt(ind.getAttribute('data-step'), 10);
            ind.classList.toggle('active', indStep === step);
            ind.classList.toggle('completed', indStep < step);
        });
        setWizardMode(true);
        hideWizardError();
    }

    function wizardNext() {
        hideWizardError();

        if (wizardStep === 1) {
            const provider = document.getElementById('connection-provider')?.value;
            if (!provider) {
                showWizardError(aiT('ai_conn_js_select_provider', 'Please select a provider.'));
                return;
            }
            updateProviderFields();
            updateProviderModels();
            showWizardStep(2);
            return;
        }

        if (wizardStep === 2) {
            const provider = document.getElementById('connection-provider')?.value;
            const apiKey = document.getElementById('connection-api-key')?.value || '';
            const apiUrl = document.getElementById('connection-api-url')?.value || '';

            if (provider === 'custom' && !apiUrl) {
                showWizardError('API URL is required for custom providers.');
                return;
            }
            if (provider !== 'ollama' && !apiKey) {
                showWizardError('API key is required. Consumer subscriptions (ChatGPT Plus, Claude Pro) do not include API access — get a key from your provider\'s developer console.');
                return;
            }
            if (provider !== 'ollama' && !connectionTestPassed) {
                showWizardError(aiT('ai_conn_js_test_before_continue', 'Please test the connection before continuing, or fix any errors shown.'));
                return;
            }
            if (provider === 'ollama' && !connectionTestPassed) {
                showWizardError(aiT('ai_conn_js_ollama_test_required', 'Please test that Birkly can reach your Ollama server before continuing.'));
                return;
            }

            loadRoles();
            updateProviderModels();
            showWizardStep(3);
        }
    }

    function wizardBack() {
        if (wizardStep > 1) {
            showWizardStep(wizardStep - 1);
        }
    }

    function showWizardError(message) {
        const el = document.getElementById('wizard-error');
        if (el) {
            el.textContent = message;
            el.style.display = 'block';
        }
    }

    function hideWizardError() {
        const el = document.getElementById('wizard-error');
        if (el) {
            el.textContent = '';
            el.style.display = 'none';
        }
    }

    function clearTestStatus() {
        const status = document.getElementById('test-connection-status');
        if (status) {
            status.textContent = '';
            status.className = 'test-connection-status';
        }
    }

    function getSelectedModel() {
        const modelCustom = document.getElementById('connection-model-custom');
        if (modelCustom && modelCustom.style.display !== 'none') {
            return (modelCustom.value || '').trim();
        }
        const hidden = document.getElementById('connection-model');
        const search = document.getElementById('connection-model-search');
        return ((hidden && hidden.value) || (search && search.value) || '').trim();
    }

    function setModelPickerStatus(message, tone) {
        const status = document.getElementById('connection-model-status');
        if (!status) return;
        status.textContent = message || '';
        status.className = 'form-help ai-model-picker-status' + (tone ? ' is-' + tone : '');
    }

    function resetModelPicker() {
        providerModelsCache = [];
        const hidden = document.getElementById('connection-model');
        const search = document.getElementById('connection-model-search');
        const list = document.getElementById('connection-model-list');
        if (hidden) hidden.value = '';
        if (search) search.value = '';
        if (list) {
            list.innerHTML = '';
            list.classList.add('is-empty');
            list.textContent = aiT('ai_conn_js_load_models_provider', 'Load models after selecting a provider.');
        }
        setModelPickerStatus('');
    }

    function setModelPickerValue(modelId) {
        const hidden = document.getElementById('connection-model');
        const search = document.getElementById('connection-model-search');
        const custom = document.getElementById('connection-model-custom');
        const value = (modelId || '').trim();
        if (hidden) hidden.value = value;
        if (search) search.value = value;
        if (custom && custom.style.display !== 'none') custom.value = value;
        renderModelPickerList(providerModelsCache, search ? search.value : '');
    }

    function updateModelPickerVisibility(provider) {
        const picker = document.getElementById('connection-model-picker');
        const custom = document.getElementById('connection-model-custom');
        const info = providersCache && provider ? providersCache[provider] : null;
        const manualOnly = provider === 'custom' || (info && !info.dynamic_models && info.custom_models);

        if (picker) picker.hidden = !!manualOnly;
        if (custom) {
            custom.style.display = manualOnly ? 'block' : 'none';
            custom.required = provider === 'custom';
        }
    }

    function renderModelPickerList(models, filter) {
        const list = document.getElementById('connection-model-list');
        const selected = getSelectedModel();
        if (!list) return;

        const query = String(filter || '').trim().toLowerCase();
        const filtered = (models || []).filter(function(model) {
            if (!query) return true;
            const hay = [
                model.id || '',
                model.name || '',
                model.description || ''
            ].join(' ').toLowerCase();
            return hay.indexOf(query) >= 0;
        });

        if (!filtered.length) {
            list.classList.add('is-empty');
            list.innerHTML = '';
            list.textContent = models && models.length
                ? aiT('ai_conn_js_no_models_search', 'No models match your search.')
                : aiT('ai_conn_js_no_models_yet', 'No models loaded yet.');
            return;
        }

        list.classList.remove('is-empty');
        list.innerHTML = filtered.map(function(model) {
            const id = escapeHtml(model.id || '');
            const name = escapeHtml(model.name || model.id || '');
            const description = escapeHtml(model.description || '');
            const context = model.context_length
                ? '<span class="ai-model-picker-option-meta">' + Number(model.context_length).toLocaleString() + ' tokens</span>'
                : '';
            const meta = description
                ? '<span class="ai-model-picker-option-meta">' + description + '</span>'
                : context;
            const selectedClass = selected && selected === model.id ? ' is-selected' : '';
            return '<button type="button" class="ai-model-picker-option' + selectedClass + '" data-model-id="' + id + '">' +
                '<span class="ai-model-picker-option-id">' + name + '</span>' +
                (name !== id ? '<span class="ai-model-picker-option-meta">' + id + '</span>' : '') +
                meta +
                '</button>';
        }).join('');
    }

    function canFetchModelsWithoutKey(provider) {
        return provider === 'openrouter';
    }

    function canFetchModelsForProvider(provider) {
        if (!provider || provider === 'custom') return false;
        if (provider === 'ollama') return true;
        if (canFetchModelsWithoutKey(provider)) return true;
        const apiKey = document.getElementById('connection-api-key')?.value || '';
        return apiKey.trim() !== '';
    }

    async function fetchProviderModels(options) {
        options = options || {};
        const providerSelect = document.getElementById('connection-provider');
        const provider = providerSelect ? providerSelect.value : '';
        if (!provider) {
            resetModelPicker();
            return null;
        }

        updateModelPickerVisibility(provider);
        if (provider === 'custom') {
            setModelPickerStatus('Enter the model ID required by your endpoint.');
            return null;
        }

        if (!canFetchModelsForProvider(provider)) {
            setModelPickerStatus('Enter your API key to load models from ' + (providersCache?.[provider]?.name || provider) + '.');
            renderModelPickerList([], '');
            return null;
        }

        if (modelPickerLoading && !options.force) return null;
        modelPickerLoading = true;
        setModelPickerStatus(aiT('ai_conn_js_loading_models', 'Loading models…'), 'loading');

        const apiKey = document.getElementById('connection-api-key')?.value || '';
        const apiUrl = document.getElementById('connection-api-url')?.value || '';

        try {
            const response = await fetch(birklyApiUrl('/api/ai_connections.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'list_models',
                    provider: provider,
                    api_key: apiKey,
                    api_url: apiUrl
                })
            });
            const result = await response.json();
            if (result.status !== 'ok') {
                throw new Error(result.message || 'Failed to load models');
            }

            providerModelsCache = result.data?.models || [];
            const defaultModel = result.data?.default_model || '';
            const search = document.getElementById('connection-model-search');
            renderModelPickerList(providerModelsCache, search ? search.value : '');
            setModelPickerStatus(result.message || (providerModelsCache.length + ' models loaded'));

            if (options.autoSelectDefault && defaultModel && !getSelectedModel()) {
                const hasDefault = providerModelsCache.some(function(m) { return m.id === defaultModel; });
                setModelPickerValue(hasDefault ? defaultModel : (providerModelsCache[0]?.id || defaultModel));
            }

            return providerModelsCache;
        } catch (error) {
            providerModelsCache = [];
            renderModelPickerList([], '');
            setModelPickerStatus(error.message || 'Could not load models', 'error');
            return null;
        } finally {
            modelPickerLoading = false;
        }
    }

    async function testConnection() {
        const provider = document.getElementById('connection-provider')?.value;
        const apiKey = document.getElementById('connection-api-key')?.value || '';
        const apiUrl = document.getElementById('connection-api-url')?.value || '';
        const model = getSelectedModel();
        const statusEl = document.getElementById('test-connection-status');
        const testBtn = document.getElementById('test-connection-btn');

        if (!provider) {
            showWizardError(aiT('ai_conn_js_select_provider_first', 'Select a provider first.'));
            return;
        }

        if (provider === 'ollama') {
            if (testBtn) testBtn.disabled = true;
            if (statusEl) {
                statusEl.textContent = 'Testing…';
                statusEl.className = 'test-connection-status pending';
            }

            try {
                const response = await fetch(birklyApiUrl('/api/ai_connections.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'test',
                        provider: 'ollama',
                        api_url: apiUrl,
                        _csrf: csrfToken
                    })
                });
                const result = await response.json();

                if (result.status === 'ok' && result.data?.valid) {
                    connectionTestPassed = true;
                    if (statusEl) {
                        statusEl.textContent = result.data.message || aiT('ai_conn_js_ollama_reachable', 'Ollama reachable');
                        statusEl.className = 'test-connection-status success';
                    }
                    fetchProviderModels({ force: true, autoSelectDefault: !getSelectedModel() }).catch(function(err) {
                        console.error('Error loading Ollama models after test:', err);
                    });
                } else {
                    connectionTestPassed = false;
                    const providerErr = result.data?.provider_error || result.message || 'Cannot reach Ollama';
                    const msg = formatSaveError(providerErr);
                    if (statusEl) {
                        statusEl.textContent = msg;
                        statusEl.className = 'test-connection-status error';
                    }
                }
            } catch (error) {
                connectionTestPassed = false;
                if (statusEl) {
                    statusEl.textContent = aiT('ai_conn_js_network_error', 'Network error — could not reach Birkly server.');
                    statusEl.className = 'test-connection-status error';
                }
            } finally {
                if (testBtn) testBtn.disabled = false;
            }
            return;
        }

        if (!apiKey) {
            showWizardError(aiT('ai_conn_js_enter_api_key_test', 'Enter an API key before testing.'));
            return;
        }

        if (testBtn) testBtn.disabled = true;
        if (statusEl) {
            statusEl.textContent = 'Testing…';
            statusEl.className = 'test-connection-status pending';
        }

        try {
            const response = await fetch(birklyApiUrl('/api/ai_connections.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'test',
                    provider,
                    api_key: apiKey,
                    api_url: apiUrl,
                    model: model,
                    _csrf: csrfToken
                })
            });
            const result = await response.json();

            if (result.status === 'ok' && result.data?.valid) {
                connectionTestPassed = true;
                if (statusEl) {
                    statusEl.textContent = result.data.message || aiT('ai_conn_js_connection_success', 'Connection successful');
                    statusEl.className = 'test-connection-status success';
                }
                fetchProviderModels({ force: true, autoSelectDefault: !getSelectedModel() }).catch(function(err) {
                    console.error('Error loading models after test:', err);
                });
            } else {
                    connectionTestPassed = false;
                    const providerErr = result.message || result.data?.message || result.data?.provider_error || 'Connection test failed';
                    const msg = formatSaveError(providerErr);
                if (statusEl) {
                    statusEl.textContent = msg;
                    statusEl.className = 'test-connection-status error';
                }
            }
        } catch (error) {
            connectionTestPassed = false;
            if (statusEl) {
                statusEl.textContent = aiT('ai_conn_js_network_error', 'Network error — could not reach Birkly server.');
                statusEl.className = 'test-connection-status error';
            }
        } finally {
            if (testBtn) testBtn.disabled = false;
        }
    }

    function updateConnectionModalTitleAndButton() {
        const title = document.getElementById('connection-modal-title');
        const submitBtn = document.getElementById('connection-submit-btn');
        if (!title || !submitBtn) return;

        if (isEditMode) {
            title.textContent = aiT('ai_conn_modal_edit_title', 'Edit assistant connection');
            submitBtn.textContent = aiT('save_changes', 'Save changes');
        } else {
            title.textContent = aiT('ai_conn_modal_quick_connect', 'Quick connect assistant');
            submitBtn.textContent = aiT('connect', 'Connect');
        }
    }

    async function loadProviders() {
        try {
            const response = await fetch(birklyApiUrl('/api/ai_connections.php?action=providers'));
            const result = await response.json();

            if (result.status === 'ok') {
                providersCache = result.data.providers;
                const select = document.getElementById('connection-provider');
                if (select) {
                    const outbound = Object.entries(providersCache)
                        .filter(([key]) => key !== 'mcp');
                    select.innerHTML = '<option value="">Select a provider...</option>' +
                        outbound.map(([key, provider]) =>
                            `<option value="${key}">${escapeHtml(provider.name)}</option>`
                        ).join('');
                }
                return providersCache;
            }
        } catch (error) {
            console.error('Error loading providers:', error);
        }
        return null;
    }

    async function loadRoles(selectedRole = null) {
        const select = document.getElementById('connection-role');
        if (!select) return;

        const legacyRoles = [
            { id: 'viewer', name: 'Viewer', description: 'Read-only' },
            { id: 'author', name: 'Author', description: 'Create content' },
            { id: 'editor', name: 'Editor', description: 'Create & Edit' },
            { id: 'admin', name: 'Admin', description: 'Full access' }
        ];

        try {
            const response = await fetch(birklyApiUrl('/api/roles.php?action=list_roles'));
            const result = await response.json();
            const roles = (result.status === 'ok' && Array.isArray(result.roles) && result.roles.length > 0)
                ? result.roles
                : legacyRoles;

            select.innerHTML = roles.map(role => {
                const roleId = role.id || role.name || '';
                const label = role.name || roleId;
                const desc = role.description ? ` (${role.description})` : '';
                return `<option value="${escapeHtml(roleId)}">${escapeHtml(label)}${escapeHtml(desc)}</option>`;
            }).join('');

            if (selectedRole) {
                select.value = selectedRole;
            } else if (!select.value && roles.length > 0) {
                const authorRole = roles.find(r => (r.id || r.name) === 'author');
                select.value = authorRole ? 'author' : (roles[0].id || roles[0].name || '');
            }
        } catch (error) {
            console.error('Error loading roles:', error);
            select.innerHTML = legacyRoles.map(role =>
                `<option value="${role.id}">${escapeHtml(role.name)} (${escapeHtml(role.description)})</option>`
            ).join('');
            if (selectedRole) select.value = selectedRole;
        }
    }

    function updateProviderModels() {
        const providerSelect = document.getElementById('connection-provider');
        const provider = providerSelect ? providerSelect.value : '';
        if (!provider) {
            resetModelPicker();
            return;
        }

        updateModelPickerVisibility(provider);
        if (canFetchModelsForProvider(provider)) {
            fetchProviderModels({ autoSelectDefault: true }).catch(function(err) {
                console.error('Error loading models:', err);
            });
        } else {
            resetModelPicker();
            if (provider !== 'custom') {
                setModelPickerStatus(
                    canFetchModelsWithoutKey(provider)
                        ? 'Loading models…'
                        : 'Enter your API key, then test the connection to load models.'
                );
            }
        }
    }

    async function loadUsers() {
        try {
            const response = await fetch(birklyApiUrl('/api/ai_connections.php?action=get_users'));
            const result = await response.json();

            if (result.status === 'ok') {
                const usersList = document.getElementById('allowed-users-list');
                if (usersList) {
                    if (result.data.users && result.data.users.length > 0) {
                        usersList.innerHTML = result.data.users.map(user => `
                            <label style="display: block; padding: 0.5rem; cursor: pointer;">
                                <input type="checkbox" name="allowed_users[]" value="${escapeHtml(user.id)}" class="user-checkbox">
                                <span>${escapeHtml(user.name || user.email)} (${escapeHtml(user.role)})</span>
                            </label>
                        `).join('');
                    } else {
                        usersList.innerHTML = '<div class="empty-state"><p>No users found</p></div>';
                    }
                }
            }
        } catch (error) {
            console.error('Error loading users:', error);
            const usersList = document.getElementById('allowed-users-list');
            if (usersList) usersList.innerHTML = '<div class="error">Error loading users</div>';
        }
    }

    function updateSharedTypeFields() {
        const sharedType = document.getElementById('connection-shared-type');
        const allowedUsersGroup = document.getElementById('allowed-users-group');

        if (!sharedType || !allowedUsersGroup) return;

        if (sharedType.value === 'specific') {
            allowedUsersGroup.style.display = 'block';
        } else {
            allowedUsersGroup.style.display = 'none';
            document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = false);
        }
    }

    function updateProviderFields() {
        const providerSelect = document.getElementById('connection-provider');
        const apiKeyGroup = document.getElementById('api-key-group');
        const apiUrlGroup = document.getElementById('api-url-group');
        const apiKeyInput = document.getElementById('connection-api-key');
        const ollamaHint = document.getElementById('ollama-hint');
        const testGroup = document.getElementById('test-connection-group');
        const apiUrlHint = document.getElementById('api-url-hint');

        if (!providerSelect) return;

        const provider = providerSelect.value;

        if (apiUrlGroup) {
            if (provider === 'custom' || provider === 'ollama') {
                apiUrlGroup.style.display = 'block';
                const apiUrlInput = document.getElementById('connection-api-url');
                if (apiUrlInput) {
                    apiUrlInput.required = provider === 'custom';
                    if (provider === 'ollama' && !apiUrlInput.value) {
                        apiUrlInput.placeholder = 'http://localhost:11434/api/chat';
                    }
                }
                if (apiUrlHint && provider === 'ollama') {
                    apiUrlHint.textContent = 'Local Ollama URL (default: http://localhost:11434/api/chat)';
                }
            } else {
                apiUrlGroup.style.display = 'none';
                const apiUrlInput = document.getElementById('connection-api-url');
                if (apiUrlInput) apiUrlInput.required = false;
            }
        }

        if (apiKeyGroup && apiKeyInput) {
            if (provider === 'ollama') {
                apiKeyGroup.style.display = 'none';
                apiKeyInput.required = false;
            } else {
                apiKeyGroup.style.display = 'block';
                apiKeyInput.required = true;
            }
        }

        if (ollamaHint) {
            ollamaHint.style.display = provider === 'ollama' ? 'block' : 'none';
        }

        if (testGroup) {
            testGroup.style.display = provider ? 'block' : 'none';
        }
    }

    async function handleConnectionSubmit(e) {
        e.preventDefault();

        const formData = new FormData(e.target);
        const model = getSelectedModel();
        const idInput = document.getElementById('connection-id');
        const connectionId = idInput ? (idInput.value || '') : '';

        if (!connectionId) {
            const provider = formData.get('provider');
            const apiKey = formData.get('api_key') || '';
            const apiUrl = formData.get('api_url') || '';

            if (!provider) {
                showWizardError(aiT('ai_conn_js_select_provider', 'Please select a provider.'));
                return;
            }
            if (provider === 'custom' && !apiUrl) {
                showWizardError('API URL is required for custom providers.');
                return;
            }
            if (provider !== 'ollama' && !apiKey) {
                showWizardError('API key is required.');
                return;
            }
            if (!connectionTestPassed) {
                showWizardError(aiT('ai_conn_js_test_before_save', 'Please test the connection before saving.'));
                return;
            }
        }

        const allowedUsers = [];

        let payload;
        if (connectionId) {
            const requireApprovalCheckbox = document.getElementById('connection-require-approval');
            const requireApproval = requireApprovalCheckbox ? Boolean(requireApprovalCheckbox.checked) : false;

            const updates = {
                name: formData.get('name') || '',
                api_url: formData.get('api_url') || '',
                model: model || '',
                role: formData.get('role') || 'author',
                shared_type: formData.get('shared_type') || 'none',
                allowed_users: allowedUsers,
                require_approval: requireApproval
            };

            Object.keys(updates).forEach(key => {
                if (updates[key] === '' && key !== 'api_url' && key !== 'model') {
                    delete updates[key];
                }
            });

            const apiKey = formData.get('api_key') || '';
            if (apiKey) updates.api_key = apiKey;

            payload = {
                action: 'update',
                id: connectionId,
                updates: updates,
                _csrf: csrfToken
            };
        } else {
            payload = {
                action: 'create',
                provider: formData.get('provider'),
                name: formData.get('name'),
                api_key: formData.get('api_key') || '',
                api_url: formData.get('api_url') || '',
                model: model,
                role: formData.get('role'),
                shared_type: formData.get('shared_type') || 'none',
                allowed_users: allowedUsers,
                require_approval: formData.get('require_approval') === '1',
                _csrf: csrfToken
            };
        }

        try {
            const response = await fetch(birklyApiUrl('/api/ai_connections.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result && result.status === 'ok') {
                const newId = result.data?.connection?.id || connectionId;
                hideAddConnectionModal();
                await loadConnections();
                showSuccess(connectionId ? aiT('ai_conn_js_updated_success', 'AI connection updated successfully') : aiT('ai_conn_js_created_success', 'AI connection created successfully'));
                window.dispatchEvent(new CustomEvent('birkly:ai-connection-changed', {
                    detail: {
                        action: connectionId ? 'update' : 'create',
                        id: newId,
                        connection: result.data?.connection || null
                    }
                }));
            } else {
                const providerErr = result?.data?.provider_error;
                showError(formatSaveError(providerErr || result?.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error saving connection:', error);
            showError('Could not reach the server. Check your network connection and try again.');
        }
    }

    function formatSaveError(message) {
        const m = String(message);
        if (m.includes('Invalid API key') || m.includes('HTTP 401') || m.includes('HTTP 403')) {
            return 'Invalid API key. Make sure you copied the key from your provider\'s developer console — ChatGPT Plus and Claude Pro subscriptions do not include API access.';
        }
        if (m.includes('rate limit') || m.includes('HTTP 429')) {
            return 'Rate limited by the provider. Wait a moment and try again, or check your API plan limits.';
        }
        if (m.includes('HTTP 404') || m.includes('not found')) {
            return 'Provider rejected the request — the model may be retired. Try another model (e.g. gemini-2.5-flash for Gemini). Details: ' + m;
        }
        if (m.includes('402') || m.toLowerCase().includes('insufficient') || m.toLowerCase().includes('credit')) {
            return 'API key looks valid, but the provider account may need credits before chat works.';
        }
        if (m.includes('Unsupported provider')) {
            return 'This provider is not supported for outbound connections.';
        }
        if (m.includes('already exists')) {
            return 'You already have a connection for this provider. Edit the existing connection or delete it first.';
        }
        if (m.includes('API key is required')) {
            return 'API key is required. Get one from your provider\'s developer portal (not from a consumer subscription).';
        }
        if (m.includes('API URL is required')) {
            return 'API URL is required for custom providers.';
        }
        if (m.includes('Cannot reach Ollama') || m.includes('Ollama returned')) {
            return 'Cannot reach Ollama. Ensure it is running and the URL is correct from the Birkly server (not just your browser).';
        }
        if (m.includes('validation failed')) {
            return m.replace('API key validation failed: ', 'Connection test failed: ');
        }
        return m;
    }

    async function editConnection(connectionId) {
        try {
            const response = await fetch(birklyApiUrl(`/api/ai_connections.php?action=get&id=${encodeURIComponent(connectionId)}`));
            const result = await response.json();

            if (result.status !== 'ok' || !result.data?.connection) {
                showError(aiT('ai_conn_js_load_connection_failed', 'Failed to load connection details'));
                return;
            }

            const conn = result.data.connection;
            isEditMode = true;
            currentEditingConnection = conn;

            const modal = document.getElementById('add-connection-modal');
            if (!modal) return;

            resetConnectionForm();
            setWizardMode(false);

            const title = document.getElementById('connection-modal-title');
            const intro = document.getElementById('assistant-modal-intro');
            if (title) title.textContent = aiT('ai_conn_modal_edit_title', 'Edit assistant connection');
            if (intro) intro.style.display = 'none';

            await loadProviders();
            await loadRoles(conn.role);

            const idInput = document.getElementById('connection-id');
            const providerSelect = document.getElementById('connection-provider');
            const nameInput = document.getElementById('connection-name');
            const apiUrlInput = document.getElementById('connection-api-url');
            const modelCustom = document.getElementById('connection-model-custom');
            const roleSelect = document.getElementById('connection-role');
            const requireApprovalCheckbox = document.getElementById('connection-require-approval');

            if (idInput) idInput.value = conn.id || '';

            if (providerSelect) {
                providerSelect.value = conn.provider || '';
                providerSelect.disabled = true;
                updateProviderFields();
                await fetchProviderModels({ force: true });
            }

            if (nameInput) nameInput.value = conn.name || '';
            if (apiUrlInput) apiUrlInput.value = conn.api_url || '';

            const modelValue = conn.model || '';
            if (modelValue) {
                if (modelCustom && modelCustom.style.display !== 'none') {
                    modelCustom.value = modelValue;
                } else {
                    setModelPickerValue(modelValue);
                }
            }

            if (roleSelect && conn.role) roleSelect.value = conn.role;

            if (requireApprovalCheckbox) {
                const requireApproval = conn.require_approval;
                if (typeof requireApproval === 'string') {
                    requireApprovalCheckbox.checked = (requireApproval === '1' || requireApproval === 'true' || requireApproval === 'yes');
                } else {
                    requireApprovalCheckbox.checked = !!requireApproval;
                }
                requireApprovalCheckbox.value = '1';
            }

            updateConnectionModalTitleAndButton();
            modal.style.display = 'flex';
        } catch (error) {
            console.error('Error loading connection for edit:', error);
            showError('Error loading connection details');
        }
    }

    async function deleteConnection(connectionId) {
        if (!connectionId) return;
        if (!(await BirklyFeedback.confirm(aiT('ai_conn_js_delete_confirm', 'Delete this assistant connection? This cannot be undone.')))) return;

        try {
            csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || csrfToken;
            const response = await fetch(birklyApiUrl('/api/ai_connections.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: connectionId, _csrf: csrfToken })
            });

            let result;
            try {
                result = await response.json();
            } catch (parseErr) {
                showError('Server returned an invalid response. Check that you are logged in and try again.');
                return;
            }

            if (result.status === 'ok') {
                showSuccess('Connection deleted');
                loadConnections();
                window.dispatchEvent(new CustomEvent('birkly:ai-connection-changed', {
                    detail: { action: 'delete', id: connectionId }
                }));
            } else {
                showError('Failed to delete: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error deleting connection:', error);
            showError('Could not reach the server. Check your connection and try again.');
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatTime(isoString) {
        return new Date(isoString).toLocaleString();
    }

    function showError(message) {
        BirklyFeedback.toast('Error: ' + message, 'error')
    }

    function showSuccess(message) {
        BirklyFeedback.toast('Success: ' + message, 'success')
    }

    window.aiConnections = {
        editConnection,
        deleteConnection
    };

    // Explicit mount entry point (P60-M4). `initAiConnections` guards against
    // double-init, so the Settings → AI zone can safely call this to
    // render the same built-in-assistant controls on Settings → AI.
    window.AiConnections = {
        init: initAiConnections,
        editConnection: editConnection,
        deleteConnection: deleteConnection
    };
})();
