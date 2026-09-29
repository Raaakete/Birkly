/**
 * Settings → Connections UI (Phase 65).
 * Guided email setup wizard + HTTP credential management.
 */
(function () {
    'use strict';

function connT(key, fallback, params) {
    let text = (typeof Birkly !== 'undefined' && Birkly.i18n && typeof Birkly.i18n.t === 'function')
        ? Birkly.i18n.t('admin.' + key, fallback)
        : (fallback != null ? fallback : key);
    if (params && typeof params === 'object') {
        Object.keys(params).forEach(function (name) {
            text = text.split('{' + name + '}').join(String(params[name]));
        });
    }
    return text;
}

    var EMAIL_PROVIDER_KEYS = ['log', 'smtp', 'resend', 'postmark', 'ses', 'mailgun', 'mail'];

    function getEmailProviderCard(key) {
        return {
            title: connT('connections_provider_' + key + '_title', key),
            subtitle: connT('connections_provider_' + key + '_subtitle', ''),
            badge: connT('connections_provider_' + key + '_badge', ''),
            help: connT('connections_provider_' + key + '_help', ''),
        };
    }

    var HTTP_PROVIDERS = ['basic_auth', 'bearer', 'custom_header'];

    var root = document.getElementById('sys-conn-settings-root');
    if (!root) return;

    var providers = {};
    var connections = [];
    var wizardStep = 1;
    var isEditMode = false;
    var testPassed = false;
    var pendingTestAlias = '';

    var emailListEl = document.getElementById('sys-conn-email-list');
    var httpListEl = document.getElementById('sys-conn-http-list');
    var emailStatusEl = document.getElementById('sys-conn-email-status');
    var emailModal = document.getElementById('sys-conn-email-modal');
    var emailForm = document.getElementById('sys-conn-email-form');
    var httpModal = document.getElementById('sys-conn-http-modal');
    var httpForm = document.getElementById('sys-conn-http-form');
    var providerGrid = document.getElementById('sys-conn-provider-grid');
    var providerFields = document.getElementById('sys-conn-provider-fields');
    var httpProviderFields = document.getElementById('sys-conn-http-provider-fields');
    var wizardError = document.getElementById('sys-conn-wizard-error');
    var httpError = document.getElementById('sys-conn-http-error');

    function apiUrl(path) {
        return (window.Birkly && typeof Birkly.url === 'function') ? Birkly.url(path) : path;
    }

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : (window.csrfToken || '');
    }

    function escapeHtml(text) {
        if (text == null) return '';
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function showToast(message, type) {
        if (window.BirklyFeedback && typeof BirklyFeedback.toast === 'function') {
            BirklyFeedback.toast(message, type === 'success' ? 'success' : 'error');
        }
    }

    function slugify(text) {
        return String(text || '')
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 48) || 'email';
    }

    async function api(action, method, body) {
        var url = apiUrl('/api/connections.php?action=' + encodeURIComponent(action));
        var opts = {
            method: method,
            headers: { 'Content-Type': 'application/json' },
        };
        if (body) {
            opts.body = JSON.stringify(Object.assign({}, body, { _csrf: getCsrfToken() }));
        }
        var res = await fetch(url, opts);
        return await res.json();
    }

    async function loadProviders() {
        var result = await api('providers', 'GET');
        if (result.status === 'ok') {
            providers = result.providers || {};
            populateHttpProviderSelect();
        }
    }

    function populateHttpProviderSelect() {
        var select = document.getElementById('sys-conn-http-provider');
        if (!select) return;
        select.innerHTML = HTTP_PROVIDERS.map(function (key) {
            var p = providers[key] || {};
            return '<option value="' + escapeHtml(key) + '">' + escapeHtml(p.label || key) + '</option>';
        }).join('');
    }

    function renderProviderGrid() {
        if (!providerGrid) return;
        var keys = EMAIL_PROVIDER_KEYS;
        providerGrid.innerHTML = keys.map(function (key) {
            var card = getEmailProviderCard(key);
            var schema = providers[key] || {};
            return '<button type="button" class="conn-provider-card" data-provider="' + escapeHtml(key) + '" role="option">' +
                '<span class="conn-provider-card-badge">' + escapeHtml(card.badge) + '</span>' +
                '<strong class="conn-provider-card-title">' + escapeHtml(card.title) + '</strong>' +
                '<span class="conn-provider-card-subtitle">' + escapeHtml(card.subtitle) + '</span>' +
                (schema.description ? '<span class="conn-provider-card-meta">' + escapeHtml(schema.description) + '</span>' : '') +
                '</button>';
        }).join('');

        providerGrid.querySelectorAll('.conn-provider-card').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectProvider(btn.getAttribute('data-provider'));
            });
        });
    }

    function selectProvider(providerKey) {
        document.getElementById('sys-conn-email-provider').value = providerKey;
        providerGrid.querySelectorAll('.conn-provider-card').forEach(function (btn) {
            btn.classList.toggle('is-selected', btn.getAttribute('data-provider') === providerKey);
            btn.setAttribute('aria-selected', btn.getAttribute('data-provider') === providerKey ? 'true' : 'false');
        });
        updateProviderSummary(providerKey);
        hideWizardError();
    }

    function updateProviderSummary(providerKey) {
        var summary = document.getElementById('sys-conn-provider-summary');
        var titleEl = document.getElementById('sys-conn-provider-summary-title');
        var helpEl = document.getElementById('sys-conn-provider-summary-help');
        var card = getEmailProviderCard(providerKey);
        if (!summary || !titleEl || !helpEl) return;
        if (!providerKey) {
            summary.hidden = true;
            return;
        }
        titleEl.textContent = card.title || providerKey;
        helpEl.textContent = card.help || (providers[providerKey] || {}).description || '';
        summary.hidden = false;
    }

    async function loadConnections() {
        var result = await api('list', 'GET');
        if (result.status !== 'ok') {
            if (emailListEl) emailListEl.innerHTML = '<p class="message message-error">' + escapeHtml(connT('connections_js_err_load_email_connections', 'Could not load email connections. Please refresh the page.')) + '</p>';
            if (httpListEl) httpListEl.innerHTML = '<p class="message message-error">' + escapeHtml(connT('connections_js_err_load_credentials', 'Could not load credentials.')) + '</p>';
            return;
        }
        connections = result.connections || [];
        renderEmailConnections();
        renderHttpCredentials();
        renderEmailStatusBanner();
    }

    function renderEmailStatusBanner() {
        if (!emailStatusEl) return;
        var emailConnections = connections.filter(function (c) { return (c.type || 'email') === 'email' && c.enabled !== false; });
        if (emailConnections.length === 0) {
            emailStatusEl.hidden = false;
            emailStatusEl.className = 'sys-conn-status-banner callout callout-warning';
            emailStatusEl.innerHTML = '<div class="callout-body"><p class="callout-title">' + escapeHtml(connT('connections_js_no_email_connection_title', 'No email connection yet')) + '</p>' +
                '<p>' + escapeHtml(connT('connections_js_no_email_connection_body', 'Form submissions and plugin emails will not reach a real inbox until you set up email below.')) + '</p></div>';
            return;
        }
        var def = emailConnections.find(function (c) { return c.is_default; }) || emailConnections[0];
        var providerKey = def.provider || '';
        var card = getEmailProviderCard(providerKey);
        var providerLabel = card.title || (providers[providerKey] || {}).label || providerKey;
        if (providerKey === 'log') {
            emailStatusEl.hidden = false;
            emailStatusEl.className = 'sys-conn-status-banner callout callout-warning';
            emailStatusEl.innerHTML = '<div class="callout-body"><p class="callout-title">' + escapeHtml(connT('connections_js_emails_logged_title', 'Emails are logged, not sent')) + '</p>' +
                '<p>' + escapeHtml(connT('connections_js_emails_logged_body', 'Your default connection ({name}) uses Log only. Switch to SMTP or an email service before going live.', { name: def.name })) + '</p></div>';
            return;
        }
        emailStatusEl.hidden = false;
        emailStatusEl.className = 'sys-conn-status-banner callout callout-info';
        emailStatusEl.innerHTML = '<div class="callout-body"><p class="callout-title">' + escapeHtml(connT('connections_js_default_email_title', 'Default email: {name}', { name: def.name })) + '</p>' +
            '<p>' + escapeHtml(connT('connections_js_default_email_body', 'Sending via {provider} from {from_email}. Form notifications and plugins use this connection.', {
                provider: providerLabel,
                from_email: def.from_email || '',
            })) + '</p></div>';
    }

    function signingSecretMarkup(connection) {
        var secret = connection.inbound_signing_secret || '';
        if (!secret) return '';
        return '<p class="form-help u-m-0">' + escapeHtml(connT('connections_js_inbound_secret_label', 'Inbound webhook signing secret')) + '</p>' +
            '<code class="sys-conn-signing-secret">' + escapeHtml(secret) + '</code>' +
            '<p class="form-help u-m-0">' + escapeHtml(connT('connections_js_inbound_secret_help', 'Copy this secret to the sender. Posts without a valid signature are rejected.')) + '</p>';
    }

    function renderEmailConnections() {
        if (!emailListEl) return;
        var emailConnections = connections.filter(function (c) { return (c.type || 'email') === 'email'; });
        if (emailConnections.length === 0) {
            emailListEl.innerHTML = '<div class="empty-state empty-state-friendly">' +
                '<p><strong>' + escapeHtml(connT('connections_js_no_email_setup_title', 'No email set up yet')) + '</strong></p>' +
                '<p>' + escapeHtml(connT('connections_js_no_email_setup_hint', 'Click Set up email above and follow the steps. It only takes a minute.')) + '</p></div>';
            return;
        }

        var rows = emailConnections.map(function (c) {
            var card = getEmailProviderCard(c.provider || '');
            var providerLabel = card.title || (providers[c.provider] || {}).label || escapeHtml(c.provider);
            var defaultBadge = c.is_default ? '<span class="badge badge-accent">Default</span>' : '';
            var statusBadge = (c.enabled === false)
                ? '<span class="badge badge-neutral">Disabled</span>'
                : '<span class="badge badge-success">Active</span>';
            return '<div class="card card--sunken sys-conn-card" data-id="' + escapeHtml(c.id) + '" data-type="email">' +
                '<div class="card-header">' +
                '<div class="u-stack-1">' +
                '<h3 class="card-title">' + escapeHtml(c.name) + ' ' + defaultBadge + '</h3>' +
                '<p class="card-description">' + escapeHtml(providerLabel) + ' · ' + escapeHtml(c.from_email || '') + '</p>' +
                signingSecretMarkup(c) +
                '</div>' +
                '<div class="u-cluster">' + statusBadge +
                '<button type="button" class="btn btn-secondary btn-sm" data-action="test" data-alias="' + escapeHtml(c.alias) + '">Send test</button>' +
                '<button type="button" class="btn btn-secondary btn-sm" data-action="edit">Edit</button>' +
                (c.is_default ? '' : '<button type="button" class="btn btn-secondary btn-sm" data-action="set-default">Make default</button>') +
                '<button type="button" class="btn btn-danger btn-sm" data-action="delete">Delete</button>' +
                '</div></div></div>';
        }).join('');

        emailListEl.innerHTML = '<div class="u-stack-3">' + rows + '</div>';
        emailListEl.querySelectorAll('[data-action]').forEach(function (btn) {
            btn.addEventListener('click', handleListAction);
        });
    }

    function renderHttpCredentials() {
        if (!httpListEl) return;
        var httpConnections = connections.filter(function (c) { return c.type === 'http_credential'; });
        if (httpConnections.length === 0) {
            httpListEl.innerHTML = '<div class="empty-state"><p>No HTTP credentials yet.</p></div>';
            return;
        }

        var rows = httpConnections.map(function (c) {
            var providerLabel = (providers[c.provider] || {}).label || escapeHtml(c.provider);
            var statusBadge = (c.enabled === false)
                ? '<span class="badge badge-neutral">Disabled</span>'
                : '<span class="badge badge-success">Active</span>';
            return '<div class="card card--sunken sys-conn-card" data-id="' + escapeHtml(c.id) + '" data-type="http_credential">' +
                '<div class="card-header">' +
                '<div class="u-stack-1">' +
                '<h3 class="card-title">' + escapeHtml(c.name) + '</h3>' +
                '<p class="card-description">' + escapeHtml(providerLabel) + ' · ' + escapeHtml((c.config || {}).base_url || '') + '</p>' +
                signingSecretMarkup(c) +
                '</div>' +
                '<div class="u-cluster">' + statusBadge +
                '<button type="button" class="btn btn-secondary btn-sm" data-action="test" data-alias="' + escapeHtml(c.alias) + '">Test</button>' +
                '<button type="button" class="btn btn-secondary btn-sm" data-action="edit">Edit</button>' +
                '<button type="button" class="btn btn-danger btn-sm" data-action="delete">Delete</button>' +
                '</div></div></div>';
        }).join('');

        httpListEl.innerHTML = '<div class="u-stack-3">' + rows + '</div>';
        httpListEl.querySelectorAll('[data-action]').forEach(function (btn) {
            btn.addEventListener('click', handleListAction);
        });
    }

    async function handleListAction(event) {
        var btn = event.currentTarget;
        var card = btn.closest('.sys-conn-card');
        var id = card.dataset.id;
        var action = btn.dataset.action;
        var connection = connections.find(function (c) { return c.id === id; });
        if (!connection) return;

        if (action === 'edit') {
            if ((connection.type || 'email') === 'email') {
                openEmailEditModal(connection);
            } else {
                openHttpEditModal(connection);
            }
        } else if (action === 'delete') {
            var label = connection.name || 'this connection';
            var ok = true;
            if (window.BirklyFeedback && typeof BirklyFeedback.confirm === 'function') {
                ok = await BirklyFeedback.confirm(connT('connections_js_confirm_delete', 'Delete "{label}"? This cannot be undone.', { label: label }));
            } else {
                ok = confirm(connT('connections_js_confirm_delete', 'Delete "{label}"? This cannot be undone.', { label: label }));
            }
            if (!ok) return;
            var delResult = await api('delete', 'POST', { id: id });
            if (delResult.status === 'ok') {
                showToast('Connection deleted.', 'success');
                await loadConnections();
            } else {
                showToast(delResult.message || 'Delete failed.', 'error');
            }
        } else if (action === 'set-default') {
            var defResult = await api('set_default', 'POST', { id: id });
            if (defResult.status === 'ok') {
                showToast('Default email connection updated.', 'success');
                await loadConnections();
            } else {
                showToast(defResult.message || 'Update failed.', 'error');
            }
        } else if (action === 'test') {
            if ((connection.type || 'email') === 'email') {
                openTestPrompt(connection);
            } else {
                var testResult = await api('test', 'POST', { alias: connection.alias });
                if (testResult.status === 'ok' && testResult.send_result && testResult.send_result.status !== 'failed') {
                    showToast(connT('connections_js_connection_test_success', 'Connection test succeeded.'), 'success');
                } else {
                    showToast(connT('connections_js_test_failed_unknown', 'Test failed: {message}', {
                        message: (testResult.send_result && testResult.send_result.error) || connT('error_unknown', 'Unknown error'),
                    }), 'error');
                }
            }
        }
    }

    async function openTestPrompt(connection) {
        var testEmail = '';
        if (window.BirklyFeedback && typeof BirklyFeedback.prompt === 'function') {
            testEmail = await BirklyFeedback.prompt(connT('connections_js_test_email_prompt', 'Send a test email to:'), '');
        } else {
            testEmail = prompt(connT('connections_js_test_email_prompt', 'Send a test email to:'), '') || '';
        }
        if (!testEmail) return;
        var testResult = await api('test', 'POST', { alias: connection.alias, test_email: testEmail });
        if (testResult.status === 'ok' && testResult.send_result && testResult.send_result.status !== 'failed') {
            showToast(connT('connections_js_test_email_sent_inbox', 'Test email sent. Check your inbox (and spam folder).'), 'success');
        } else {
            showToast(connT('connections_js_test_failed_unknown', 'Test failed: {message}', {
                message: (testResult.send_result && testResult.send_result.error) || connT('error_unknown', 'Unknown error'),
            }), 'error');
        }
    }

    function renderSchemaFields(container, fields, values) {
        if (!container) return;
        container.innerHTML = '';
        (fields || []).forEach(function (field) {
            var value = values && values[field.name] !== undefined ? values[field.name] : (field.value || '');
            var name = field.name;
            var id = 'sys-conn-field-' + name.replace(/[\[\]]/g, '-');
            var wrapper = document.createElement('div');
            wrapper.className = 'form-group';

            var label = document.createElement('label');
            label.htmlFor = id;
            label.textContent = field.label + (field.required ? ' *' : '');
            wrapper.appendChild(label);

            var input;
            if (field.type === 'select') {
                input = document.createElement('select');
                input.className = 'input';
                Object.entries(field.options || {}).forEach(function (entry) {
                    var opt = document.createElement('option');
                    opt.value = entry[0];
                    opt.textContent = entry[1];
                    if (String(value) === entry[0]) opt.selected = true;
                    input.appendChild(opt);
                });
            } else {
                input = document.createElement('input');
                input.type = field.type === 'password' ? 'password' : (field.type === 'number' ? 'number' : 'text');
                input.className = 'input';
                input.placeholder = field.placeholder || '';
                input.value = value;
            }

            input.id = id;
            input.name = name;
            if (field.required) input.required = true;
            wrapper.appendChild(input);

            if (field.help) {
                var help = document.createElement('small');
                help.className = 'form-help';
                help.textContent = field.help;
                wrapper.appendChild(help);
            }

            container.appendChild(wrapper);
        });
    }

    function renderProviderFields(providerKey, values) {
        var schema = providers[providerKey];
        if (schema && schema.fields && schema.fields.length) {
            renderSchemaFields(providerFields, schema.fields, values);
            return;
        }
            providerFields.innerHTML = '<p class="form-help">' + escapeHtml(connT('connections_js_no_extra_provider_settings', 'No extra settings needed for this method.')) + '</p>';
    }

    function showWizardStep(step) {
        wizardStep = step;
        emailForm.querySelectorAll('.conn-wizard-panel').forEach(function (panel) {
            var panelStep = parseInt(panel.getAttribute('data-wizard-step'), 10);
            panel.hidden = panelStep !== step;
        });
        emailForm.querySelectorAll('.conn-wizard-step-indicator').forEach(function (ind) {
            var indStep = parseInt(ind.getAttribute('data-step'), 10);
            ind.classList.toggle('active', indStep === step);
            ind.classList.toggle('completed', indStep < step);
        });

        var backBtn = document.getElementById('sys-conn-wizard-back');
        var nextBtn = document.getElementById('sys-conn-wizard-next');
        var saveBtn = document.getElementById('sys-conn-wizard-save');
        if (backBtn) backBtn.hidden = step === 1 || (isEditMode && step === 2);
        if (nextBtn) nextBtn.hidden = step === 3;
        if (saveBtn) saveBtn.hidden = step !== 3;

        var title = document.getElementById('sys-conn-email-modal-title');
        if (title) {
            if (isEditMode) {
                title.textContent = step === 3 ? 'Test & save changes' : 'Edit email connection';
            } else {
                title.textContent = step === 1 ? 'Set up email' : (step === 2 ? 'Enter your details' : 'Test & save');
            }
        }
        hideWizardError();
    }

    function hideWizardError() {
        if (wizardError) {
            wizardError.textContent = '';
            wizardError.hidden = true;
        }
    }

    function showWizardError(message) {
        if (wizardError) {
            wizardError.textContent = message;
            wizardError.hidden = false;
        }
    }

    function resetEmailWizard() {
        emailForm.reset();
        document.getElementById('sys-conn-email-id').value = '';
        document.getElementById('sys-conn-email-type').value = 'email';
        document.getElementById('sys-conn-email-provider').value = '';
        document.getElementById('sys-conn-email-enabled').checked = true;
        document.getElementById('sys-conn-set-default').checked = connections.filter(function (c) {
            return (c.type || 'email') === 'email';
        }).length === 0;
        if (providerFields) providerFields.innerHTML = '';
        if (providerGrid) {
            providerGrid.querySelectorAll('.conn-provider-card').forEach(function (btn) {
                btn.classList.remove('is-selected');
                btn.setAttribute('aria-selected', 'false');
            });
        }
        var testStatus = document.getElementById('sys-conn-test-status');
        if (testStatus) {
            testStatus.textContent = '';
            testStatus.className = 'form-help sys-conn-test-status';
        }
        testPassed = false;
        pendingTestAlias = '';
        hideWizardError();
    }

    function openEmailWizard() {
        isEditMode = false;
        resetEmailWizard();
        renderProviderGrid();
        showWizardStep(1);
        emailModal.classList.add('active');
        emailModal.style.display = 'flex';
    }

    function openEmailEditModal(connection) {
        isEditMode = true;
        resetEmailWizard();
        document.getElementById('sys-conn-email-id').value = connection.id;
        document.getElementById('sys-conn-email-provider').value = connection.provider || '';
        document.getElementById('sys-conn-email-name').value = connection.name || '';
        document.getElementById('sys-conn-email-alias').value = connection.alias || '';
        document.getElementById('sys-conn-email-from').value = connection.from_email || '';
        document.getElementById('sys-conn-email-from-name').value = connection.from_name || '';
        document.getElementById('sys-conn-email-enabled').checked = connection.enabled !== false;
        document.getElementById('sys-conn-set-default').checked = !!connection.is_default;
        pendingTestAlias = connection.alias || '';
        renderProviderFields(connection.provider || '', {
            'config[host]': (connection.config || {}).host,
            'config[port]': (connection.config || {}).port,
            'config[encryption]': (connection.config || {}).encryption,
            'config[auth]': (connection.config || {}).auth,
            'config[region]': (connection.config || {}).region,
            'config[domain]': (connection.config || {}).domain,
            'secrets[username]': '',
            'secrets[password]': '',
            'secrets[api_key]': '',
            'secrets[access_key]': '',
            'secrets[secret_key]': '',
        });
        updateProviderSummary(connection.provider || '');
        showWizardStep(2);
        emailModal.classList.add('active');
        emailModal.style.display = 'flex';
    }

    function closeEmailModal() {
        emailModal.classList.remove('active');
        emailModal.style.display = 'none';
        resetEmailWizard();
    }

    function validateStep2() {
        var provider = document.getElementById('sys-conn-email-provider').value;
        if (!provider) {
            showWizardError('Please go back and choose how you want to send email.');
            return false;
        }
        var name = document.getElementById('sys-conn-email-name').value.trim();
        if (!name) {
            showWizardError('Please enter a name for this connection.');
            return false;
        }
        var fromEmail = document.getElementById('sys-conn-email-from').value.trim();
        if (provider !== 'log' && !fromEmail) {
            showWizardError('Please enter the sender email address recipients will see.');
            return false;
        }
        if (emailForm.reportValidity && !emailForm.reportValidity()) {
            return false;
        }
        return true;
    }

    function formDataToObject(form) {
        var data = { type: 'email' };
        var formData = new FormData(form);
        formData.forEach(function (value, key) {
            if (key === 'enabled' || key === 'set_default') {
                data[key] = true;
                return;
            }
            if (key === 'set_default') return;
            if (key.indexOf('[') > 0) {
                var match = key.match(/^([^\[]+)\[(.*)\]$/);
                if (match) {
                    var group = match[1];
                    var sub = match[2];
                    if (!data[group]) data[group] = {};
                    data[group][sub] = value;
                }
            } else if (key !== 'id' && key !== 'type' && key !== 'provider') {
                data[key] = value;
            }
        });

        data.provider = document.getElementById('sys-conn-email-provider').value;
        var idVal = document.getElementById('sys-conn-email-id').value;
        if (idVal) data.id = idVal;

        if (!data.alias || !String(data.alias).trim()) {
            var base = slugify(data.name || data.provider || 'email');
            data.alias = base;
            var n = 1;
            while (connections.some(function (c) {
                return c.alias === data.alias && c.id !== data.id;
            })) {
                data.alias = base + '-' + n;
                n++;
            }
        }

        if (!('enabled' in data)) data.enabled = false;
        return data;
    }

    async function wizardNext() {
        hideWizardError();
        if (wizardStep === 1) {
            var provider = document.getElementById('sys-conn-email-provider').value;
            if (!provider) {
                showWizardError('Please choose how you want to send email.');
                return;
            }
            renderProviderFields(provider, {});
            var nameInput = document.getElementById('sys-conn-email-name');
            if (nameInput && !nameInput.value) {
                var card = getEmailProviderCard(provider);
                nameInput.value = card.title || provider;
            }
            if (provider === 'log') {
                var fromInput = document.getElementById('sys-conn-email-from');
                if (fromInput && !fromInput.value) fromInput.value = 'dev@localhost';
                fromInput.required = false;
            } else {
                document.getElementById('sys-conn-email-from').required = true;
            }
            showWizardStep(2);
            return;
        }
        if (wizardStep === 2) {
            if (!validateStep2()) return;
            var testEmailInput = document.getElementById('sys-conn-test-email');
            if (testEmailInput && !testEmailInput.value) {
                var sessionEmail = (document.querySelector('meta[name="user-email"]') || {}).content || '';
                testEmailInput.value = sessionEmail;
            }
            showWizardStep(3);
        }
    }

    function wizardBack() {
        if (wizardStep > 1) {
            showWizardStep(wizardStep - 1);
        }
    }

    async function sendWizardTest() {
        var testEmail = (document.getElementById('sys-conn-test-email').value || '').trim();
        var statusEl = document.getElementById('sys-conn-test-status');
        if (!testEmail) {
            if (statusEl) {
                statusEl.textContent = connT('connections_js_enter_email_first', 'Enter your email address first.');
                statusEl.className = 'form-help sys-conn-test-status is-error';
            }
            return;
        }
        if (!validateStep2()) return;

        var data = formDataToObject(emailForm);
        var isEdit = !!data.id;
        var saveFirst = !isEdit || data.alias !== pendingTestAlias;

        if (statusEl) {
            statusEl.textContent = connT('connections_js_sending_test', 'Sending test…');
            statusEl.className = 'form-help sys-conn-test-status';
        }

        if (saveFirst) {
            var saveResult = await api(isEdit ? 'update' : 'create', 'POST', data);
            if (saveResult.status !== 'ok') {
                var msg = saveResult.errors ? Object.values(saveResult.errors).join(' ') : (saveResult.message || 'Could not save connection.');
                if (statusEl) {
                    statusEl.textContent = msg;
                    statusEl.className = 'form-help sys-conn-test-status is-error';
                }
                return;
            }
            document.getElementById('sys-conn-email-id').value = saveResult.connection.id;
            pendingTestAlias = saveResult.connection.alias;
            isEditMode = true;
            await loadConnections();
        }

        var testResult = await api('test', 'POST', { alias: pendingTestAlias || data.alias, test_email: testEmail });
        if (testResult.status === 'ok' && testResult.send_result && testResult.send_result.status !== 'failed') {
            testPassed = true;
            var st = testResult.send_result.status;
            if (statusEl) {
                if (st === 'logged') {
                    statusEl.textContent = connT('connections_js_test_logged', 'Test logged successfully (Log only mode — check logs/mail.jsonl).');
                } else {
                    statusEl.textContent = connT('connections_js_test_sent', 'Test sent! Check your inbox and spam folder.');
                }
                statusEl.className = 'form-help sys-conn-test-status is-success';
            }
        } else {
            testPassed = false;
            if (statusEl) {
                statusEl.textContent = connT('connections_js_test_failed', 'Test failed:') + ' ' + ((testResult.send_result && testResult.send_result.error) || testResult.message || connT('ai_conn_js_unknown_error', 'unknown error'));
                statusEl.className = 'form-help sys-conn-test-status is-error';
            }
        }
    }

    emailForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        hideWizardError();
        if (!validateStep2()) {
            showWizardStep(2);
            return;
        }

        var data = formDataToObject(emailForm);
        var isEdit = !!data.id;
        var setDefault = !!document.getElementById('sys-conn-set-default').checked;
        delete data.set_default;

        var result = await api(isEdit ? 'update' : 'create', 'POST', data);
        if (result.status !== 'ok') {
            var messages = result.errors
                ? Object.values(result.errors).join(' ')
                : (result.message || 'Save failed.');
            showWizardError(messages);
            return;
        }

        if (setDefault) {
            await api('set_default', 'POST', { id: result.connection.id });
        }

        closeEmailModal();
        showToast(isEdit ? 'Email connection updated.' : 'Email is set up! Form notifications and plugins can now send mail.', 'success');
        await loadConnections();
    });

    document.getElementById('sys-conn-wizard-next').addEventListener('click', wizardNext);
    document.getElementById('sys-conn-wizard-back').addEventListener('click', wizardBack);
    document.getElementById('sys-conn-send-test-btn').addEventListener('click', sendWizardTest);

    function openHttpWizard() {
        httpForm.reset();
        document.getElementById('sys-conn-http-id').value = '';
        document.getElementById('sys-conn-http-type').value = 'http_credential';
        document.getElementById('sys-conn-http-enabled').checked = true;
        httpProviderFields.innerHTML = '';
        httpError.hidden = true;
        document.getElementById('sys-conn-http-modal-title').textContent = connT('connections_js_add_http_credential', 'Add HTTP credential');
        document.getElementById('sys-conn-http-submit').textContent = connT('connections_js_save_credential', 'Save credential');
        var provider = document.getElementById('sys-conn-http-provider').value;
        renderHttpProviderFields(provider, {});
        httpModal.classList.add('active');
        httpModal.style.display = 'flex';
    }

    function openHttpEditModal(connection) {
        httpForm.reset();
        document.getElementById('sys-conn-http-id').value = connection.id;
        document.getElementById('sys-conn-http-provider').value = connection.provider || 'bearer';
        document.getElementById('sys-conn-http-name').value = connection.name || '';
        document.getElementById('sys-conn-http-alias').value = connection.alias || '';
        document.getElementById('sys-conn-http-enabled').checked = connection.enabled !== false;
        httpError.hidden = true;
        document.getElementById('sys-conn-http-modal-title').textContent = connT('connections_js_edit_http_credential', 'Edit HTTP credential');
        document.getElementById('sys-conn-http-submit').textContent = connT('connections_js_update_credential', 'Update credential');
        renderHttpProviderFields(connection.provider || 'bearer', {
            'config[base_url]': (connection.config || {}).base_url,
            'config[timeout]': (connection.config || {}).timeout,
            'config[header_name]': (connection.config || {}).header_name,
        });
        httpModal.classList.add('active');
        httpModal.style.display = 'flex';
    }

    function closeHttpModal() {
        httpModal.classList.remove('active');
        httpModal.style.display = 'none';
    }

    function renderHttpProviderFields(providerKey, values) {
        var schema = providers[providerKey];
        renderSchemaFields(httpProviderFields, schema ? schema.fields : [], values);
    }

    function httpFormToObject() {
        var data = { type: 'http_credential' };
        var formData = new FormData(httpForm);
        formData.forEach(function (value, key) {
            if (key === 'enabled') {
                data.enabled = true;
                return;
            }
            if (key.indexOf('[') > 0) {
                var match = key.match(/^([^\[]+)\[(.*)\]$/);
                if (match) {
                    if (!data[match[1]]) data[match[1]] = {};
                    data[match[1]][match[2]] = value;
                }
            } else {
                data[key] = value;
            }
        });
        data.provider = document.getElementById('sys-conn-http-provider').value;
        if (!('enabled' in data)) data.enabled = false;
        return data;
    }

    httpForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        httpError.hidden = true;
        var data = httpFormToObject();
        var isEdit = !!data.id;
        var result = await api(isEdit ? 'update' : 'create', 'POST', data);
        if (result.status === 'ok') {
            closeHttpModal();
            showToast(isEdit ? 'Credential updated.' : 'Credential saved.', 'success');
            await loadConnections();
        } else {
            var messages = result.errors ? Object.values(result.errors).join(' ') : (result.message || 'Save failed.');
            httpError.textContent = messages;
            httpError.hidden = false;
        }
    });

    document.getElementById('sys-conn-http-provider').addEventListener('change', function () {
        renderHttpProviderFields(this.value, {});
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-sys-conn-action]');
        if (!btn) return;
        var action = btn.getAttribute('data-sys-conn-action');
        if (action === 'open-email-wizard') openEmailWizard();
        else if (action === 'open-http-wizard') openHttpWizard();
        else if (action === 'close-email-modal') closeEmailModal();
        else if (action === 'close-http-modal') closeHttpModal();
    });

    [emailModal, httpModal].forEach(function (modal) {
        if (!modal) return;
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                if (modal === emailModal) closeEmailModal();
                else closeHttpModal();
            }
        });
    });

    function scrollToEmailSection() {
        var params = new URLSearchParams(window.location.search);
        if (params.get('section') !== 'email') return;
        var section = document.getElementById('sys-conn-email-section');
        if (section) {
            setTimeout(function () {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 200);
        }
    }

    async function init() {
        await loadProviders();
        await loadConnections();
        scrollToEmailSection();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
