(function () {
  'use strict';

  var API = (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function')
    ? Birkly.url('/api/index.php?route=payments')
    : '/api/index.php?route=payments';
  var providers = [];
  var connections = [];

  var CREDENTIAL_SCHEMAS = {
    stripe: [
      { key: 'secret_key', label: 'Secret Key', type: 'password', required: true },
    ],
    mollie: [
      { key: 'api_key', label: 'API Key', type: 'password', required: true },
    ],
    paypal: [
      { key: 'client_id', label: 'Client ID', type: 'text', required: true },
      { key: 'client_secret', label: 'Client Secret', type: 'password', required: true },
    ],
    adyen: [
      { key: 'api_key', label: 'API Key', type: 'password', required: true },
      { key: 'merchant_account', label: 'Merchant Account', type: 'text', required: true },
      { key: 'hmac_key', label: 'HMAC Key (webhooks)', type: 'password' },
    ],
    square: [
      { key: 'access_token', label: 'Access Token', type: 'password', required: true },
      { key: 'location_id', label: 'Location ID', type: 'text', required: true },
    ],
    razorpay: [
      { key: 'key_id', label: 'Key ID', type: 'text', required: true },
      { key: 'key_secret', label: 'Key Secret', type: 'password', required: true },
    ],
    komoju: [
      { key: 'secret_key', label: 'Secret Key', type: 'password', required: true },
    ],
    payssion: [
      { key: 'api_key', label: 'API Key', type: 'text', required: true },
      { key: 'secret_key', label: 'Secret Key', type: 'password', required: true },
    ],
  };

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function apiCall(action, data, method) {
    method = method || 'POST';
    var url = API + '&action=' + encodeURIComponent(action);
    var opts = {
      method: method,
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
    };
    if (method === 'POST') {
      opts.body = JSON.stringify(Object.assign({ action: action, _csrf: csrfToken() }, data || {}));
    }
    return fetch(url, opts).then(function (r) { return r.json(); });
  }

  function initTabs() {
    document.querySelectorAll('.payments-tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        var name = tab.getAttribute('data-tab');
        document.querySelectorAll('.payments-tab').forEach(function (t) {
          t.classList.toggle('active', t === tab);
          t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
        });
        document.querySelectorAll('.payments-tab-panel').forEach(function (panel) {
          var isActive = panel.id === 'payments-tab-' + name;
          panel.classList.toggle('active', isActive);
          panel.hidden = !isActive;
        });
        if (name === 'activity') loadActivity();
        if (name === 'documentation' && window.BirklyPluginDocs) {
          window.BirklyPluginDocs.activate('commerce', 'payments-tab-documentation');
        }
      });
    });
  }

  function renderProviderMatrix() {
    var el = document.getElementById('payments-providers-matrix');
    if (!el) return;
    if (!providers.length) {
      el.innerHTML = '<div class="payments-empty">No providers registered</div>';
      return;
    }
    el.innerHTML = providers.map(function (p) {
      var caps = p.capabilities || {};
      var features = caps.features || {};
      var featList = [];
      if (features.refunds) featList.push('Refunds');
      if (features.subscriptions) featList.push('Subscriptions');
      if (features.capture) featList.push('Capture');
      return (
        '<div class="payments-provider-card">' +
        '<h3>' + escapeHtml(p.name) + '</h3>' +
        '<span class="badge' + (p.configured ? ' configured' : '') + '">' +
        (p.configured ? 'Connected' : 'Not connected') + '</span>' +
        '<ul><li>Currencies: ' + escapeHtml((caps.currencies || []).slice(0, 4).join(', ')) + '</li>' +
        '<li>Methods: ' + escapeHtml((caps.methods || []).slice(0, 4).join(', ')) + '</li>' +
        (featList.length ? '<li>' + escapeHtml(featList.join(', ')) + '</li>' : '') +
        '</ul></div>'
      );
    }).join('');
  }

  function renderConnections() {
    var el = document.getElementById('payments-connections-list');
    if (!el) return;
    if (!connections.length) {
      el.innerHTML = '<div class="payments-empty">No connections yet. Add one to get started.</div>';
      return;
    }
    el.innerHTML =
      '<table class="payments-table"><thead><tr>' +
      '<th>Label</th><th>Provider</th><th>Mode</th><th>Status</th><th>Webhook URL</th><th></th>' +
      '</tr></thead><tbody>' +
      connections.map(function (c) {
        return (
          '<tr>' +
          '<td>' + escapeHtml(c.label || '') + '</td>' +
          '<td>' + escapeHtml(c.provider || '') + '</td>' +
          '<td>' + escapeHtml(c.mode || 'test') + '</td>' +
          '<td>' + (c.enabled ? 'Enabled' : 'Disabled') + '</td>' +
          '<td><code style="font-size:0.75rem">' + escapeHtml(c.webhook_url || '') + '</code></td>' +
          '<td class="payments-actions">' +
          '<button class="btn btn-secondary btn-sm" data-edit="' + escapeHtml(c.id) + '">Edit</button>' +
          '<button class="btn btn-secondary btn-sm" data-delete="' + escapeHtml(c.id) + '">Delete</button>' +
          '</td></tr>'
        );
      }).join('') +
      '</tbody></table>';

    el.querySelectorAll('[data-edit]').forEach(function (btn) {
      btn.addEventListener('click', function () { openConnectionModal(btn.getAttribute('data-edit')); });
    });
    el.querySelectorAll('[data-delete]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (confirm('Delete this connection?')) deleteConnection(btn.getAttribute('data-delete'));
      });
    });
  }

  function loadProviders() {
    return apiCall('list_providers', {}, 'POST').then(function (res) {
      if (res.status === 'ok') {
        providers = res.data.providers || [];
        renderProviderMatrix();
        populateProviderSelects();
      }
    });
  }

  function loadConnections() {
    return apiCall('list_connections', {}, 'POST').then(function (res) {
      if (res.status === 'ok') {
        connections = res.data.connections || [];
        renderConnections();
        renderProviderMatrix();
      }
    });
  }

  function populateProviderSelects() {
    var selects = [
      document.getElementById('payments-conn-provider'),
      document.getElementById('payments-default-provider'),
      document.getElementById('payments-filter-provider'),
    ];
    selects.forEach(function (sel, idx) {
      if (!sel) return;
      var current = sel.value;
      var opts = idx === 2
        ? '<option value="">All providers</option>'
        : '';
      providers.forEach(function (p) {
        opts += '<option value="' + escapeHtml(p.id) + '">' + escapeHtml(p.name) + '</option>';
      });
      sel.innerHTML = opts;
      if (current) sel.value = current;
    });
  }

  function renderCredentialFields(providerId) {
    var el = document.getElementById('payments-credential-fields');
    if (!el) return;
    var schema = CREDENTIAL_SCHEMAS[providerId] || [{ key: 'api_key', label: 'API Key', type: 'password', required: true }];
    el.innerHTML = schema.map(function (field) {
      return (
        '<div class="form-group">' +
        '<label for="cred-' + escapeHtml(field.key) + '">' + escapeHtml(field.label) +
        (field.required ? ' <span class="required">*</span>' : '') + '</label>' +
        '<input type="' + (field.type || 'text') + '" id="cred-' + escapeHtml(field.key) + '" ' +
        'class="input" data-cred-key="' + escapeHtml(field.key) + '" autocomplete="off"' +
        (field.required ? ' required' : '') + '>' +
        '</div>'
      );
    }).join('');

    var webhookDisplay = document.getElementById('payments-webhook-url-display');
    if (webhookDisplay && providerId) {
      apiCall('get_webhook_url', { provider: providerId }, 'POST').then(function (res) {
        if (res.status === 'ok') {
          webhookDisplay.hidden = false;
          webhookDisplay.innerHTML = '<strong>Webhook URL:</strong><br>' + escapeHtml(res.data.webhook_url);
        }
      });
    }
  }

  function showConnectionModal() {
    var modal = document.getElementById('payments-connection-modal');
    if (!modal) return;
    modal.hidden = false;
    modal.style.display = 'flex';
    modal.classList.add('active');
  }

  function hideConnectionModal() {
    var modal = document.getElementById('payments-connection-modal');
    if (!modal) return;
    modal.classList.remove('active');
    modal.style.display = 'none';
    modal.hidden = true;
  }

  function openConnectionModal(id) {
    var modal = document.getElementById('payments-connection-modal');
    var title = document.getElementById('payments-modal-title');
    if (!modal || !title) return;

    document.getElementById('payments-connection-form').reset();
    var testResult = document.getElementById('payments-test-result');
    if (testResult) testResult.hidden = true;

    if (id) {
      title.textContent = 'Edit Connection';
      var conn = connections.find(function (c) { return c.id === id; });
      if (conn) {
        document.getElementById('payments-conn-id').value = conn.id;
        document.getElementById('payments-conn-provider').value = conn.provider;
        document.getElementById('payments-conn-label').value = conn.label || '';
        document.getElementById('payments-conn-mode').value = conn.mode || 'test';
        renderCredentialFields(conn.provider);
      }
    } else {
      title.textContent = 'Add Connection';
      document.getElementById('payments-conn-id').value = '';
      var firstProvider = (providers[0] && providers[0].id) ? providers[0].id : 'stripe';
      document.getElementById('payments-conn-provider').value = firstProvider;
      renderCredentialFields(firstProvider);
    }

    showConnectionModal();
  }

  function closeConnectionModal() {
    hideConnectionModal();
  }

  function collectCredentials() {
    var creds = {};
    document.querySelectorAll('[data-cred-key]').forEach(function (input) {
      if (input.value) creds[input.getAttribute('data-cred-key')] = input.value;
    });
    return creds;
  }

  function saveConnection(e) {
    e.preventDefault();
    var connection = {
      id: document.getElementById('payments-conn-id').value || undefined,
      provider: document.getElementById('payments-conn-provider').value,
      label: document.getElementById('payments-conn-label').value,
      mode: document.getElementById('payments-conn-mode').value,
      enabled: true,
      credentials: collectCredentials(),
    };
    var webhookSecret = document.getElementById('payments-webhook-secret').value;
    if (webhookSecret) connection.webhook_secret = webhookSecret;

    apiCall('save_connection', { connection: connection }).then(function (res) {
      if (res.status === 'ok') {
        closeConnectionModal();
        loadConnections();
      } else {
        alert(res.message || 'Save failed');
      }
    });
  }

  function testConnection() {
    var provider = document.getElementById('payments-conn-provider').value;
    var creds = collectCredentials();
    if (Object.keys(creds).length === 0) {
      creds.mock = true;
    }
    var resultEl = document.getElementById('payments-test-result');
    apiCall('test_connection', { provider: provider, credentials: creds }).then(function (res) {
      resultEl.hidden = false;
      var ok = res.status === 'ok' && res.data && res.data.ok;
      resultEl.className = 'payments-test-result ' + (ok ? 'success' : 'error');
      resultEl.textContent = ok ? (res.data.message || 'Connection successful') : (res.data?.error || res.message || 'Test failed');
    });
  }

  function deleteConnection(id) {
    apiCall('delete_connection', { id: id }).then(function () { loadConnections(); });
  }

  function loadActivity() {
    var provider = document.getElementById('payments-filter-provider')?.value || '';
    var status = document.getElementById('payments-filter-status')?.value || '';
    var filters = {};
    if (provider) filters.provider = provider;
    if (status) filters.status = status;

    apiCall('list_activity', { filters: filters, limit: 100 }).then(function (res) {
      var el = document.getElementById('payments-activity-list');
      if (!el) return;
      var items = (res.data && res.data.activity) || [];
      if (!items.length) {
        el.innerHTML = '<div class="payments-empty">No activity yet</div>';
        return;
      }
      el.innerHTML =
        '<table class="payments-table"><thead><tr>' +
        '<th>Time</th><th>Type</th><th>Provider</th><th>Status</th><th>Source</th><th>Message</th>' +
        '</tr></thead><tbody>' +
        items.map(function (a) {
          var statusClass = (a.status || '').toLowerCase();
          return (
            '<tr>' +
            '<td>' + escapeHtml(formatTime(a.timestamp)) + '</td>' +
            '<td>' + escapeHtml(a.type || '') + '</td>' +
            '<td>' + escapeHtml(a.provider || '—') + '</td>' +
            '<td><span class="status-pill ' + escapeHtml(statusClass) + '">' + escapeHtml(a.status || '—') + '</span></td>' +
            '<td>' + escapeHtml((a.source_plugin || '—') + (a.source_ref ? ':' + a.source_ref : '')) + '</td>' +
            '<td>' + escapeHtml(a.message || '') + '</td>' +
            '</tr>'
          );
        }).join('') +
        '</tbody></table>';
    });
  }

  function saveSettings(e) {
    e.preventDefault();
    var roleSelect = document.getElementById('payments-widget-roles');
    var selectedRoles = roleSelect
      ? Array.from(roleSelect.selectedOptions).map(function (o) { return o.value; })
      : ['admin'];
    if (!selectedRoles.length) {
      selectedRoles = ['admin'];
    }
    var settings = {
      selection_mode: document.getElementById('payments-selection-mode').value,
      default_provider: document.getElementById('payments-default-provider').value,
      enable_dashboard_widget: document.getElementById('payments-enable-widget').checked,
      dashboard_widget_roles: selectedRoles,
      dunning_email_enabled: document.getElementById('payments-dunning-email-enabled')?.checked || false,
      dunning_email_subject: document.getElementById('payments-dunning-email-subject')?.value || '',
      dunning_email_body: document.getElementById('payments-dunning-email-body')?.value || '',
    };
    apiCall('save_settings', { settings: settings }).then(function (res) {
      if (res.status === 'ok') {
        alert('Settings saved');
      }
    });
  }

  function loadSettings() {
    apiCall('get_settings', {}, 'GET').then(function (res) {
      if (res.status !== 'ok' || !res.data || !res.data.settings) return;
      var s = res.data.settings;
      var modeEl = document.getElementById('payments-selection-mode');
      if (modeEl && s.selection_mode) modeEl.value = s.selection_mode;
      var defEl = document.getElementById('payments-default-provider');
      if (defEl && s.default_provider) defEl.value = s.default_provider;
      var widgetEl = document.getElementById('payments-enable-widget');
      if (widgetEl) widgetEl.checked = !!s.enable_dashboard_widget;
      var rolesEl = document.getElementById('payments-widget-roles');
      if (rolesEl && Array.isArray(s.dashboard_widget_roles)) {
        Array.from(rolesEl.options).forEach(function (opt) {
          opt.selected = s.dashboard_widget_roles.indexOf(opt.value) !== -1;
        });
      }
      var dunningEnabled = document.getElementById('payments-dunning-email-enabled');
      if (dunningEnabled) dunningEnabled.checked = !!s.dunning_email_enabled;
      var dunningSubject = document.getElementById('payments-dunning-email-subject');
      if (dunningSubject && s.dunning_email_subject) dunningSubject.value = s.dunning_email_subject;
      var dunningBody = document.getElementById('payments-dunning-email-body');
      if (dunningBody && s.dunning_email_body) dunningBody.value = s.dunning_email_body;
    });
  }

  function formatTime(ts) {
    if (!ts) return '—';
    try { return new Date(ts).toLocaleString(); } catch (e) { return ts; }
  }

  function escapeHtml(str) {
    if (str == null) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function init() {
    initTabs();
    loadProviders().then(function () {
      loadConnections();
      loadSettings();
    });

    document.getElementById('payments-add-connection')?.addEventListener('click', function () { openConnectionModal(null); });
    document.getElementById('payments-modal-close')?.addEventListener('click', closeConnectionModal);
    document.getElementById('payments-connection-form')?.addEventListener('submit', saveConnection);
    document.getElementById('payments-test-conn')?.addEventListener('click', testConnection);
    document.getElementById('payments-conn-provider')?.addEventListener('change', function (e) {
      renderCredentialFields(e.target.value);
    });
    document.getElementById('payments-refresh-activity')?.addEventListener('click', loadActivity);
    document.getElementById('payments-settings-form')?.addEventListener('submit', saveSettings);

    document.getElementById('payments-connection-modal')?.addEventListener('click', function (e) {
      if (e.target === e.currentTarget) closeConnectionModal();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
