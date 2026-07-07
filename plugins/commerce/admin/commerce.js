(function () {
  'use strict';

  var COMMERCE_API = (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function')
    ? Birkly.url('/api/index.php?route=commerce')
    : '/api/index.php?route=commerce';

  function commercePost(action, body) {
    body = body || {};
    body.action = action;
    var csrf = (typeof Birkly !== 'undefined' && Birkly.csrfToken)
      ? Birkly.csrfToken
      : (document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
    if (csrf) {
      body._csrf = csrf;
    }
    return fetch(COMMERCE_API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    }).then(function (r) {
      return r.json().then(function (data) {
        if (r.status === 403 && data && data.message) {
          data.status = data.status || 'error';
          data.permission_denied = true;
        }
        return data;
      });
    });
  }

  function formatCents(cents, currency) {
    return (currency || 'EUR').toUpperCase() + ' ' + (Number(cents) / 100).toFixed(2);
  }

  function loadCatalog() {
    var el = document.getElementById('commerce-catalog-list');
    if (!el) return;
    el.innerHTML = '<div class="loading-state"><p>Loading catalog…</p></div>';
    commercePost('list_catalog').then(function (res) {
      if (res.status !== 'ok') {
        el.innerHTML = '<p class="error">Failed to load catalog</p>';
        return;
      }
      var items = (res.data && res.data.catalog) || [];
      if (!items.length) {
        el.innerHTML = '<p>No purchasable entries indexed yet.</p>';
        return;
      }
      var html = '<table class="payments-table"><thead><tr><th>Title</th><th>Collection</th><th>Price</th><th>Stock</th><th>SKU</th></tr></thead><tbody>';
      items.forEach(function (item) {
        var stock = item.quantity_available === null ? '∞' : item.quantity_available;
        html += '<tr><td>' + (item.entry_title || item.entry_slug) + '</td><td>' + item.collection_slug + '</td><td>'
          + formatCents(item.price_cents, item.currency) + '</td><td>' + stock + '</td><td>' + (item.sku || '—') + '</td></tr>';
      });
      html += '</tbody></table>';
      el.innerHTML = html;
    });
  }

  function loadOrders() {
    var el = document.getElementById('commerce-orders-list');
    if (!el) return;
    el.innerHTML = '<div class="loading-state"><p>Loading orders…</p></div>';
    commercePost('list_orders').then(function (res) {
      var orders = (res.data && res.data.orders) || [];
      if (!orders.length) {
        el.innerHTML = '<p>No orders yet.</p>';
        return;
      }
      var html = '';
      orders.forEach(function (o) {
        html += '<div class="commerce-order-card"><h3>' + o.id + ' — ' + o.status + ' — ' + formatCents(o.total_cents, o.currency) + '</h3>';
        html += '<p>Email: ' + (o.customer_email || '—') + ' · Created: ' + (o.created_at || '') + '</p>';
        if (o.shipping_address) {
          html += '<p>Ship to: ' + (o.shipping_address.line1 || '') + ', ' + (o.shipping_address.city || '') + ' ' + (o.shipping_address.country || '') + '</p>';
        }
        if (o.attendee_name) {
          html += '<p>Attendee: ' + o.attendee_name + '</p>';
        }
        if (Number(o.tax_cents) > 0) {
          html += '<p>Tax (' + (Number(o.tax_rate) || 0) + '%): ' + formatCents(o.tax_cents, o.currency) + '</p>';
        }
        if (Number(o.refunded_cents) > 0) {
          html += '<p>Refunded: ' + formatCents(o.refunded_cents, o.currency) + '</p>';
        }
        var refundable = (o.status === 'paid' || o.status === 'partially_refunded');
        var remaining = (Number(o.total_cents) || 0) - (Number(o.refunded_cents) || 0);
        if (refundable && remaining > 0) {
          html += '<div class="tab-panel-actions"><button type="button" class="btn btn-secondary commerce-refund-order" data-order-id="'
            + o.id + '" data-remaining="' + remaining + '" data-currency="' + (o.currency || 'eur') + '">Refund…</button></div>';
        }
        html += '<table class="payments-table"><thead><tr><th>Line</th><th>Qty</th><th>Option</th><th>Fulfillment</th><th>State</th></tr></thead><tbody>';
        (o.lines || []).forEach(function (line, idx) {
          var fr = (o.fulfillment_results || []).find(function (r) { return r.line_id === line.line_id; }) || {};
          var state = fr.skipped ? 'done' : (fr.ok === false ? 'error' : (fr.ok ? 'fulfilled' : 'pending'));
          html += '<tr><td>' + (line.title || line.entry_id) + '</td><td>' + line.quantity + '</td><td>'
            + (line.purchase_option_id || '—') + '</td><td>' + (line.fulfillment || '—') + '</td><td>' + state + '</td></tr>';
        });
        html += '</tbody></table></div>';
      });
      el.innerHTML = html;
    });
  }

  function subStatusBadge(status) {
    var cls = 'commerce-sub-badge commerce-sub-badge-' + (status || 'unknown');
    return '<span class="' + cls + '">' + (status || 'unknown') + '</span>';
  }

  function loadSubscriptions() {
    var el = document.getElementById('commerce-subscriptions-list');
    if (!el) return;
    var detail = document.getElementById('commerce-subscription-detail');
    if (detail) { detail.hidden = true; detail.innerHTML = ''; }
    el.innerHTML = '<div class="loading-state"><p>Loading subscriptions…</p></div>';
    commercePost('list_subscriptions').then(function (res) {
      var subs = (res.data && res.data.subscriptions) || [];
      if (!subs.length) {
        el.innerHTML = '<p>No subscriptions yet. Recurring purchases create a subscription when the order is paid.</p>';
        return;
      }
      var html = '<table class="payments-table"><thead><tr><th>ID</th><th>Status</th><th>Customer</th><th>Amount</th><th>Interval</th><th>Next billing</th><th></th></tr></thead><tbody>';
      subs.forEach(function (s) {
        html += '<tr>'
          + '<td>' + s.id + '</td>'
          + '<td>' + subStatusBadge(s.status) + '</td>'
          + '<td>' + (s.customer_email || '—') + '</td>'
          + '<td>' + formatCents(s.amount_cents, s.currency) + '</td>'
          + '<td>' + (s.interval || '—') + '</td>'
          + '<td>' + (s.next_billing_at ? s.next_billing_at.slice(0, 10) : '—') + '</td>'
          + '<td><button class="btn btn-secondary commerce-sub-view" data-id="' + s.id + '">View</button></td>'
          + '</tr>';
      });
      html += '</tbody></table>';
      el.innerHTML = html;
      el.querySelectorAll('.commerce-sub-view').forEach(function (btn) {
        btn.addEventListener('click', function () {
          viewSubscription(btn.getAttribute('data-id'));
        });
      });
    });
  }

  function viewSubscription(id) {
    var detail = document.getElementById('commerce-subscription-detail');
    if (!detail) return;
    detail.hidden = false;
    detail.innerHTML = '<div class="loading-state"><p>Loading subscription…</p></div>';
    commercePost('get_subscription', { id: id }).then(function (res) {
      var s = res.data && res.data.subscription;
      if (!s) {
        detail.innerHTML = '<p class="error">Subscription not found</p>';
        return;
      }
      var canCancel = s.status !== 'canceled';
      var html = '<div class="commerce-order-card">';
      html += '<h3>' + s.id + ' ' + subStatusBadge(s.status) + '</h3>';
      html += '<p>Customer: ' + (s.customer_email || '—') + '</p>';
      html += '<p>Order: ' + (s.order_id || '—') + ' · Provider: ' + (s.provider || '—') + '</p>';
      html += '<p>Amount: ' + formatCents(s.amount_cents, s.currency) + ' · Interval: ' + (s.interval || '—') + '</p>';
      html += '<p>Started: ' + (s.created_at || '—') + '</p>';
      html += '<p>Next billing: ' + (s.next_billing_at || '—') + '</p>';
      if (s.canceled_at) html += '<p>Canceled: ' + s.canceled_at + '</p>';
      if (s.past_due_at) html += '<p>Past due since: ' + s.past_due_at + '</p>';
      html += '<table class="payments-table"><thead><tr><th>Line</th><th>Qty</th><th>Option</th></tr></thead><tbody>';
      (s.lines || []).forEach(function (line) {
        html += '<tr><td>' + (line.title || line.entry_id) + '</td><td>' + (line.quantity || 1) + '</td><td>' + (line.purchase_option_id || '—') + '</td></tr>';
      });
      html += '</tbody></table>';
      if (canCancel) {
        html += '<button class="btn btn-danger" id="commerce-sub-cancel" data-id="' + s.id + '">Cancel subscription</button>';
      }
      html += '</div>';
      detail.innerHTML = html;
      var cancelBtn = document.getElementById('commerce-sub-cancel');
      if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
          if (!confirm('Cancel this subscription? This cannot be undone.')) return;
          commercePost('cancel_subscription', { id: cancelBtn.getAttribute('data-id') }).then(function (r) {
            if (r.status === 'ok') {
              loadSubscriptions();
              viewSubscription(id);
            } else {
              alert((r.message) || 'Failed to cancel subscription');
            }
          });
        });
      }
    });
  }

  var shippingZonesCache = [];

  function loadShippingZones() {
    var el = document.getElementById('commerce-shipping-zones-list');
    if (!el) return;
    commercePost('list_shipping_zones').then(function (res) {
      shippingZonesCache = (res.data && res.data.zones) || [];
      renderShippingZones();
    });
  }

  function renderShippingZones() {
    var el = document.getElementById('commerce-shipping-zones-list');
    if (!el) return;
    if (!shippingZonesCache.length) {
      el.innerHTML = '<p>No shipping zones configured.</p>';
      return;
    }
    var html = '';
    shippingZonesCache.forEach(function (zone, idx) {
      html += '<div class="commerce-shipping-zone" data-idx="' + idx + '">';
      html += '<div class="form-group"><label>Name</label><input type="text" class="input zone-name" value="' + (zone.name || '') + '"></div>';
      html += '<div class="form-group"><label>Countries (comma-separated, * = all)</label><input type="text" class="input zone-countries" value="' + ((zone.countries || ['*']).join(', ')) + '"></div>';
      html += '<div class="form-group"><label>Flat rate (cents)</label><input type="number" class="input zone-rate" value="' + (zone.flat_rate_cents || 0) + '"></div>';
      html += '<button type="button" class="btn btn-secondary commerce-remove-zone" data-idx="' + idx + '">Remove</button></div>';
    });
    el.innerHTML = html;
    el.querySelectorAll('.commerce-remove-zone').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var i = parseInt(btn.getAttribute('data-idx'), 10);
        shippingZonesCache.splice(i, 1);
        renderShippingZones();
      });
    });
  }

  var taxRulesCache = { enabled: false, default_rate: 0, country_rates: [] };

  function loadTaxRules() {
    var el = document.getElementById('commerce-tax-country-list');
    if (!el) return;
    commercePost('list_tax_rules').then(function (res) {
      var data = res.data || {};
      taxRulesCache = {
        enabled: !!data.enabled,
        default_rate: Number(data.default_rate) || 0,
        country_rates: Array.isArray(data.country_rates) ? data.country_rates : [],
      };
      var enabledEl = document.getElementById('commerce-tax-enabled');
      var rateEl = document.getElementById('commerce-tax-default-rate');
      if (enabledEl) enabledEl.checked = taxRulesCache.enabled;
      if (rateEl) rateEl.value = taxRulesCache.default_rate;
      renderTaxCountries();
    });
  }

  function renderTaxCountries() {
    var el = document.getElementById('commerce-tax-country-list');
    if (!el) return;
    if (!taxRulesCache.country_rates.length) {
      el.innerHTML = '<p>No country overrides. The default rate applies to all destinations.</p>';
      return;
    }
    var html = '';
    taxRulesCache.country_rates.forEach(function (entry, idx) {
      html += '<div class="commerce-tax-country" data-idx="' + idx + '">';
      html += '<div class="form-group"><label>Country code (ISO, e.g. DE)</label><input type="text" class="input tax-country" maxlength="2" value="' + (entry.country || '') + '"></div>';
      html += '<div class="form-group"><label>Rate (%)</label><input type="number" step="0.01" min="0" class="input tax-rate" value="' + (Number(entry.rate) || 0) + '"></div>';
      html += '<button type="button" class="btn btn-secondary commerce-remove-tax-country" data-idx="' + idx + '">Remove</button></div>';
    });
    el.innerHTML = html;
    el.querySelectorAll('.commerce-remove-tax-country').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var i = parseInt(btn.getAttribute('data-idx'), 10);
        taxRulesCache.country_rates.splice(i, 1);
        renderTaxCountries();
      });
    });
  }

  function loadDiscounts() {
    var el = document.getElementById('commerce-discounts-list');
    if (!el) return;
    commercePost('list_discounts').then(function (res) {
      var rules = (res.data && res.data.rules) || [];
      if (!rules.length) {
        el.innerHTML = '<p>No discount rules configured.</p>';
        return;
      }
      var html = '<ul class="payments-conn-list">';
      rules.forEach(function (r) {
        html += '<li><strong>' + (r.id || '') + '</strong> — ' + (r.type || '') + ' ' + (r.target || 'global') + '</li>';
      });
      html += '</ul>';
      el.innerHTML = html;
    });
  }

  function loadMappings() {
    var el = document.getElementById('commerce-mappings-list');
    if (!el) return;
    commercePost('list_mappings').then(function (res) {
      var mappings = (res.data && res.data.mappings) || {};
      var keys = Object.keys(mappings);
      if (!keys.length) {
        el.innerHTML = '<p>No purchase mappings.</p>';
        return;
      }
      var html = '<ul class="payments-conn-list">';
      keys.forEach(function (k) {
        html += '<li><code>' + k + '</code> → tier <strong>' + (mappings[k].grant_tier || '') + '</strong></li>';
      });
      html += '</ul>';
      el.innerHTML = html;
    });
  }

  var fieldtypeProfileDraft = null;
  var fieldtypeProfilesCache = [];

  var FULFILLMENT_LABELS = { ship: 'Ship', deliver: 'Deliver', register: 'Register', none: 'None' };
  var FULFILLMENT_COLORS = { ship: 'blue', deliver: 'purple', register: 'green', none: 'gray' };

  var FT_PRESETS = {
    events: {
      name: 'Events & Classes',
      slug: 'events',
      settings: {
        default_fulfillment: 'register',
        enabled_fulfillments: ['register'],
        enable_fulfillment: true,
        enable_purchase_options: true,
        enable_quantity: true,
        enable_sku: false,
        enable_weight: false,
        enable_delivery: false,
        interval_options: [{ value: 'month', label: 'Monthly' }],
        custom_fields: [],
      },
    },
    digital: {
      name: 'Digital Products',
      slug: 'digital',
      settings: {
        default_fulfillment: 'deliver',
        enabled_fulfillments: ['deliver'],
        enable_fulfillment: true,
        enable_purchase_options: true,
        enable_quantity: false,
        enable_sku: true,
        enable_weight: false,
        enable_delivery: true,
        interval_options: [{ value: 'month', label: 'Monthly' }],
        custom_fields: [],
      },
    },
    physical: {
      name: 'Physical Products',
      slug: 'physical',
      settings: {
        default_fulfillment: 'ship',
        enabled_fulfillments: ['ship'],
        enable_fulfillment: true,
        enable_purchase_options: true,
        enable_quantity: true,
        enable_sku: true,
        enable_weight: true,
        enable_delivery: false,
        interval_options: [
          { value: 'month', label: 'Monthly' },
          { value: '2_weeks', label: 'Every 2 weeks' },
        ],
        custom_fields: [],
      },
    },
    membership: {
      name: 'Membership',
      slug: 'membership',
      settings: {
        default_fulfillment: 'none',
        enabled_fulfillments: ['none'],
        enable_fulfillment: true,
        enable_purchase_options: true,
        enable_quantity: false,
        enable_sku: false,
        enable_weight: false,
        enable_delivery: false,
        interval_options: [
          { value: 'month', label: 'Monthly' },
          { value: 'year', label: 'Yearly' },
        ],
        custom_fields: [],
      },
    },
  };

  function escapeHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function updateTypePreview() {
    var slugEl = document.getElementById('commerce-ft-slug');
    var preview = document.getElementById('commerce-ft-type-preview');
    if (!preview || !slugEl) return;
    var slug = slugEl.value.trim();
    var id = document.getElementById('commerce-ft-profile-id')?.value || '';
    if (id === 'default' || !slug || slug === 'default') {
      preview.textContent = 'commerce';
      return;
    }
    preview.textContent = 'commerce_' + slug.replace(/[^a-z0-9_]/gi, '_').toLowerCase();
  }

  function showFieldtypeModal() {
    var modal = document.getElementById('commerce-fieldtype-modal');
    if (!modal) return;
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('commerce-ft-modal-open');
  }

  function closeFieldtypeModal() {
    var modal = document.getElementById('commerce-fieldtype-modal');
    if (!modal) return;
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('commerce-ft-modal-open');
    fieldtypeProfileDraft = null;
  }

  function loadFieldtypeProfiles() {
    var el = document.getElementById('commerce-fieldtype-profiles-list');
    if (!el) return;
    el.innerHTML = '<div class="loading-state"><p>Loading fieldtype profiles…</p></div>';
    commercePost('list_fieldtype_profiles').then(function (res) {
      fieldtypeProfilesCache = (res.data && res.data.profiles) || [];
      if (!fieldtypeProfilesCache.length) {
        el.innerHTML = '<div class="commerce-ft-empty"><p>No fieldtypes yet.</p><button type="button" class="btn btn-primary" id="commerce-ft-empty-create">Create your first fieldtype</button></div>';
        document.getElementById('commerce-ft-empty-create')?.addEventListener('click', newFieldtypeProfile);
        return;
      }
      var html = '';
      fieldtypeProfilesCache.forEach(function (p) {
        var settings = p.settings || {};
        var fulfillment = settings.default_fulfillment || 'ship';
        var color = FULFILLMENT_COLORS[fulfillment] || 'gray';
        var intervalCount = (settings.interval_options || []).length;
        var cfCount = (settings.custom_fields || []).length;
        html += '<article class="commerce-ft-card' + (p.is_default ? ' is-default' : '') + '">'
          + '<div class="commerce-ft-card-top">'
          + '<div class="commerce-ft-card-icon commerce-ft-icon-' + color + '">' + (p.is_default ? '★' : '◆') + '</div>'
          + '<div class="commerce-ft-card-meta">'
          + '<h3>' + escapeHtml(p.name || p.id) + '</h3>'
          + (p.is_default ? '<span class="commerce-ft-badge">Default</span>' : '')
          + '</div></div>'
          + '<p class="commerce-ft-card-key"><code>' + escapeHtml(p.type_key || 'commerce') + '</code></p>'
          + '<div class="commerce-ft-card-stats">'
          + '<span class="status-pill commerce-ft-pill-' + color + '">' + escapeHtml(FULFILLMENT_LABELS[fulfillment] || fulfillment) + '</span>'
          + '<span class="commerce-ft-stat">' + intervalCount + ' interval' + (intervalCount === 1 ? '' : 's') + '</span>'
          + (cfCount ? '<span class="commerce-ft-stat">' + cfCount + ' custom field' + (cfCount === 1 ? '' : 's') + '</span>' : '')
          + '</div>'
          + '<div class="commerce-ft-card-actions">'
          + '<button type="button" class="btn btn-secondary commerce-edit-profile" data-id="' + escapeHtml(p.id) + '">Edit</button>'
          + (p.is_default ? '' : '<button type="button" class="btn btn-secondary commerce-delete-profile" data-id="' + escapeHtml(p.id) + '">Delete</button>')
          + '</div></article>';
      });
      el.innerHTML = html;
      el.querySelectorAll('.commerce-edit-profile').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-id');
          var profile = fieldtypeProfilesCache.find(function (p) { return p.id === id; });
          if (profile) openFieldtypeEditor(profile);
        });
      });
      el.querySelectorAll('.commerce-delete-profile').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-id');
          if (!confirm('Delete this fieldtype? Collections using it will need updating.')) return;
          commercePost('delete_fieldtype_profile', { id: id }).then(function () { loadFieldtypeProfiles(); });
        });
      });
    });
  }

  function renderIntervalRows(intervals) {
    var list = document.getElementById('commerce-ft-intervals-list');
    if (!list) return;
    if (!intervals || !intervals.length) {
      list.innerHTML = '<p class="commerce-ft-list-empty">No custom intervals — add one below.</p>';
      return;
    }
    list.innerHTML = intervals.map(function (iv, idx) {
      return '<div class="commerce-ft-list-item">'
        + '<div><strong>' + escapeHtml(iv.label) + '</strong><br><code>' + escapeHtml(iv.value) + '</code></div>'
        + '<button type="button" class="btn-icon commerce-ft-remove-interval" data-idx="' + idx + '" title="Remove">×</button>'
        + '</div>';
    }).join('');
    list.querySelectorAll('.commerce-ft-remove-interval').forEach(function (btn) {
      btn.addEventListener('click', function () {
        fieldtypeProfileDraft.settings.interval_options.splice(parseInt(btn.getAttribute('data-idx'), 10), 1);
        renderIntervalRows(fieldtypeProfileDraft.settings.interval_options);
      });
    });
  }

  function renderCustomFieldRows(fields) {
    var list = document.getElementById('commerce-ft-custom-fields-list');
    if (!list) return;
    if (!fields || !fields.length) {
      list.innerHTML = '<p class="commerce-ft-list-empty">No custom fields — optional merchant metadata.</p>';
      return;
    }
    list.innerHTML = fields.map(function (cf, idx) {
      return '<div class="commerce-ft-list-item">'
        + '<div><strong>' + escapeHtml(cf.label) + '</strong><br><code>' + escapeHtml(cf.key) + '</code></div>'
        + '<button type="button" class="btn-icon commerce-ft-remove-cf" data-idx="' + idx + '" title="Remove">×</button>'
        + '</div>';
    }).join('');
    list.querySelectorAll('.commerce-ft-remove-cf').forEach(function (btn) {
      btn.addEventListener('click', function () {
        fieldtypeProfileDraft.settings.custom_fields.splice(parseInt(btn.getAttribute('data-idx'), 10), 1);
        renderCustomFieldRows(fieldtypeProfileDraft.settings.custom_fields);
      });
    });
  }

  function renderFulfillmentChips(settings) {
    var ftEl = document.getElementById('commerce-ft-fulfillments');
    if (!ftEl) return;
    var enabled = settings.enabled_fulfillments || ['ship', 'deliver', 'register', 'none'];
    ftEl.innerHTML = ['ship', 'deliver', 'register', 'none'].map(function (f) {
      var checked = enabled.indexOf(f) >= 0 ? ' checked' : '';
      return '<label class="commerce-ft-chip"><input type="checkbox" class="commerce-ft-fulfillment-cb" value="' + f + '"' + checked + '><span>' + FULFILLMENT_LABELS[f] + '</span></label>';
    }).join('');
  }

  function renderToggleGrid(settings) {
    var toggles = [
      ['enable_fulfillment', 'Fulfillment selector', 'Let merchants pick ship / deliver / register / none'],
      ['enable_purchase_options', 'Purchase options', 'One-time and recurring prices on same entry'],
      ['enable_quantity', 'Quantity / capacity', 'Stock limits or event spots'],
      ['enable_sku', 'SKU', 'Warehouse or inventory identifier'],
      ['enable_weight', 'Weight (grams)', 'For shipping calculations'],
      ['enable_delivery', 'Delivery panel', 'Digital asset path and external URL'],
    ];
    var el = document.getElementById('commerce-ft-toggles');
    if (!el) return;
    el.innerHTML = toggles.map(function (t) {
      var checked = settings[t[0]] !== false ? ' checked' : '';
      return '<label class="commerce-ft-toggle-card">'
        + '<input type="checkbox" class="commerce-ft-toggle-cb" data-key="' + t[0] + '"' + checked + '>'
        + '<span class="commerce-ft-toggle-title">' + t[1] + '</span>'
        + '<span class="commerce-ft-toggle-desc">' + t[2] + '</span></label>';
    }).join('');
  }

  function openFieldtypeEditor(profile) {
    fieldtypeProfileDraft = JSON.parse(JSON.stringify(profile));
    if (!fieldtypeProfileDraft.settings) fieldtypeProfileDraft.settings = {};

    var isDefault = !!profile.is_default;
    var isNew = !profile.id;
    document.getElementById('commerce-fieldtype-editor-title').textContent = isNew ? 'New fieldtype' : (isDefault ? 'Edit default fieldtype' : 'Edit fieldtype');
    document.getElementById('commerce-ft-modal-subtitle').textContent = isNew
      ? 'Choose a quick start preset or configure manually.'
      : 'Changes apply to all collections using this field type.';
    document.getElementById('commerce-ft-presets-wrap').hidden = !isNew;

    document.getElementById('commerce-ft-profile-id').value = profile.id || '';
    document.getElementById('commerce-ft-name').value = profile.name || '';
    document.getElementById('commerce-ft-slug').value = profile.slug || '';
    document.getElementById('commerce-ft-slug').disabled = isDefault;
    document.getElementById('commerce-ft-default-fulfillment').value = profile.settings.default_fulfillment || 'ship';

    renderFulfillmentChips(profile.settings);
    renderToggleGrid(profile.settings);
    renderIntervalRows(profile.settings.interval_options || []);
    renderCustomFieldRows(profile.settings.custom_fields || []);
    updateTypePreview();
    showFieldtypeModal();
  }

  function newFieldtypeProfile() {
    openFieldtypeEditor({ id: '', name: '', slug: '', settings: JSON.parse(JSON.stringify(FT_PRESETS.physical.settings)) });
  }

  function applyPreset(presetKey) {
    var preset = FT_PRESETS[presetKey];
    if (!preset || !fieldtypeProfileDraft) return;
    fieldtypeProfileDraft.name = preset.name;
    fieldtypeProfileDraft.slug = preset.slug;
    fieldtypeProfileDraft.settings = JSON.parse(JSON.stringify(preset.settings));
    openFieldtypeEditor(fieldtypeProfileDraft);
  }

  function saveFieldtypeProfile() {
    if (!fieldtypeProfileDraft) return;
    var settings = fieldtypeProfileDraft.settings || {};
    settings.default_fulfillment = document.getElementById('commerce-ft-default-fulfillment').value;
    settings.enabled_fulfillments = [];
    document.querySelectorAll('.commerce-ft-fulfillment-cb:checked').forEach(function (cb) {
      settings.enabled_fulfillments.push(cb.value);
    });
    document.querySelectorAll('.commerce-ft-toggle-cb').forEach(function (cb) {
      settings[cb.getAttribute('data-key')] = cb.checked;
    });
    var profile = {
      id: document.getElementById('commerce-ft-profile-id').value || undefined,
      name: document.getElementById('commerce-ft-name').value.trim(),
      slug: document.getElementById('commerce-ft-slug').value.trim(),
      settings: settings,
    };
    if (!profile.name) {
      document.getElementById('commerce-ft-name').focus();
      return;
    }
    var saveBtn = document.getElementById('commerce-ft-save');
    if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }
    commercePost('save_fieldtype_profile', { profile: profile }).then(function (res) {
      if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save fieldtype'; }
      if (res.status !== 'ok') {
        alert(res.message || 'Save failed');
        return;
      }
      closeFieldtypeModal();
      loadFieldtypeProfiles();
    });
  }

  var importSourcesCache = [];
  var importCollectionsCache = [];
  var importHistoryCache = [];
  var importWizard = {
    step: 1,
    source: 'generic',
    csv: '',
    fileName: '',
    preview: null,
    filter: 'all',
    search: '',
  };

  var IMPORT_STEP_LABELS = ['Choose your platform', 'Upload your export', 'Review products', 'Import complete'];

  function getImportCollectionMode() {
    var checked = document.querySelector('input[name="commerce-import-collection-mode"]:checked');
    return checked ? checked.value : 'existing';
  }

  function updateImportCollectionModeUI() {
    var mode = getImportCollectionMode();
    var existing = document.getElementById('commerce-import-collection-existing');
    var createNew = document.getElementById('commerce-import-collection-new');
    if (existing) existing.hidden = mode !== 'existing';
    if (createNew) createNew.hidden = mode !== 'new';
  }

  function renderImportCollectionSelect() {
    var select = document.getElementById('commerce-import-collection-select');
    if (!select) return;
    if (!importCollectionsCache.length) {
      select.innerHTML = '<option value="">No product collections yet — create a new one below</option>';
      return;
    }
    select.innerHTML = importCollectionsCache.map(function (col) {
      return '<option value="' + escapeHtml(col.slug) + '">' + escapeHtml(col.title || col.slug) + '</option>';
    }).join('');
  }

  function loadImportCollections() {
    return commercePost('import_collections').then(function (res) {
      importCollectionsCache = (res.data && res.data.collections) || [];
      renderImportCollectionSelect();
      return importCollectionsCache;
    });
  }

  function getImportCollectionPayload() {
    var mode = getImportCollectionMode();
    if (mode === 'new') {
      var title = document.getElementById('commerce-import-collection-title')?.value.trim() || '';
      return {
        create_collection: true,
        collection_title: title,
        collection: '',
      };
    }
    return {
      create_collection: false,
      collection_title: '',
      collection: document.getElementById('commerce-import-collection-select')?.value || '',
    };
  }

  function getImportCollectionLabel() {
    var payload = getImportCollectionPayload();
    if (payload.create_collection) {
      return payload.collection_title || 'New collection';
    }
    var slug = payload.collection;
    var match = importCollectionsCache.find(function (col) { return col.slug === slug; });
    return match ? (match.title || slug) : slug;
  }

  function validateImportCollectionStep() {
    var payload = getImportCollectionPayload();
    if (payload.create_collection) {
      if (!payload.collection_title) {
        alert('Please enter a name for the new collection.');
        return false;
      }
      return true;
    }
    if (!payload.collection) {
      alert('Please select a collection or choose to create a new one.');
      return false;
    }
    return true;
  }

  function loadImportTab() {
    loadImportHistory();
    if (!importSourcesCache.length) {
      commercePost('import_sources').then(function (res) {
        importSourcesCache = (res.data && res.data.sources) || [];
      });
    }
  }

  function loadImportHistory() {
    var el = document.getElementById('commerce-import-history-list');
    if (!el) return;
    commercePost('import_history', { limit: 50 }).then(function (res) {
      importHistoryCache = (res.data && res.data.imports) || [];
      renderImportHistory();
      renderImportStats();
    });
  }

  function renderImportStats() {
    var wrap = document.getElementById('commerce-import-stats');
    if (!wrap) return;
    if (!importHistoryCache.length) {
      wrap.hidden = true;
      return;
    }
    var success = importHistoryCache.filter(function (i) { return i.status === 'success'; }).length;
    var partial = importHistoryCache.filter(function (i) { return i.status === 'partial'; }).length;
    var failed = importHistoryCache.filter(function (i) { return i.status === 'failed'; }).length;
    var totalProducts = importHistoryCache.reduce(function (sum, i) { return sum + (i.imported_count || 0); }, 0);
    wrap.hidden = false;
    wrap.innerHTML = [
      { value: importHistoryCache.length, label: 'Total imports' },
      { value: totalProducts, label: 'Products imported' },
      { value: success, label: 'Successful' },
      { value: partial + failed, label: 'With issues' },
    ].map(function (s) {
      return '<div class="commerce-import-stat-card"><div class="commerce-import-stat-value">' + s.value + '</div><div class="commerce-import-stat-label">' + escapeHtml(s.label) + '</div></div>';
    }).join('');
  }

  function formatImportDate(iso) {
    if (!iso) return '—';
    try {
      return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    } catch (e) {
      return iso;
    }
  }

  function renderImportHistory() {
    var el = document.getElementById('commerce-import-history-list');
    if (!el) return;
    if (!importHistoryCache.length) {
      el.innerHTML = '<div class="commerce-import-history-empty"><p>No imports yet.</p><p>Click <strong>Import products</strong> to bring your catalog from another system.</p></div>';
      return;
    }
    var html = '<table class="commerce-import-history-table"><thead><tr>'
      + '<th>Date</th><th>Platform</th><th>Collection</th><th>Status</th><th>Imported</th><th>Skipped</th><th>File</th><th></th>'
      + '</tr></thead><tbody>';
    importHistoryCache.forEach(function (item) {
      var status = item.status || 'success';
      var rolledBack = status === 'rolled_back' || !!item.rolled_back_at;
      var actions = rolledBack
        ? '<span class="form-hint">Removed</span>'
        : '<button type="button" class="btn btn-secondary commerce-import-view-detail" data-id="' + escapeHtml(item.id) + '">Details</button>'
          + ' <button type="button" class="btn btn-danger commerce-import-delete" data-id="' + escapeHtml(item.id) + '"'
          + ' data-collection="' + escapeHtml(item.collection || '') + '"'
          + ' data-count="' + (item.imported_count || 0) + '"'
          + (item.collection_created ? ' data-collection-created="1"' : '')
          + '>Remove</button>';
      html += '<tr>'
        + '<td>' + escapeHtml(formatImportDate(item.at)) + '</td>'
        + '<td>' + escapeHtml(item.platform || item.preset || '—') + '</td>'
        + '<td><code>' + escapeHtml(item.collection || 'products') + '</code></td>'
        + '<td><span class="commerce-import-status ' + escapeHtml(status) + '">' + escapeHtml(status) + '</span></td>'
        + '<td>' + (item.imported_count || 0) + '</td>'
        + '<td>' + (item.skipped_count || 0) + '</td>'
        + '<td>' + escapeHtml(item.file_name || '—') + '</td>'
        + '<td class="commerce-import-history-actions">' + actions + '</td>'
        + '</tr>';
    });
    html += '</tbody></table>';
    el.innerHTML = html;
    el.querySelectorAll('.commerce-import-view-detail').forEach(function (btn) {
      btn.addEventListener('click', function () {
        showImportDetail(btn.getAttribute('data-id'));
      });
    });
    el.querySelectorAll('.commerce-import-delete').forEach(function (btn) {
      btn.addEventListener('click', function () {
        rollbackImport(btn);
      });
    });
  }

  function rollbackImport(btn) {
    var importId = btn.getAttribute('data-id');
    var collection = btn.getAttribute('data-collection') || 'collection';
    var count = btn.getAttribute('data-count') || '0';
    var createdCollection = btn.hasAttribute('data-collection-created');
    var message = 'Remove ' + count + ' imported product(s) from "' + collection + '"?';
    if (createdCollection) {
      message += '\n\nThis import created the collection — the collection will be deleted as well.';
    }
    message += '\n\nThis cannot be undone.';
    if (!confirm(message)) {
      return;
    }
    btn.disabled = true;
    commercePost('import_rollback', { id: importId, delete_collection: true }).then(function (res) {
      btn.disabled = false;
      if (res.status !== 'ok') {
        alert(res.message || 'Could not remove import');
        return;
      }
      var data = res.data || {};
      var summary = 'Removed ' + (data.deleted || 0) + ' product(s)';
      if (data.variants_deleted) {
        summary += ' and ' + data.variants_deleted + ' variant(s)';
      }
      if (data.collection_deleted) {
        summary += '. Collection deleted.';
      }
      alert(summary);
      loadImportHistory();
      loadImportCollections();
      if (document.querySelector('.commerce-tabs .payments-tab.active')?.getAttribute('data-tab') === 'catalog') {
        loadCatalog();
      }
    });
  }

  function showImportDetail(importId) {
    commercePost('import_detail', { id: importId }).then(function (res) {
      if (res.status !== 'ok') {
        alert(res.message || 'Could not load import details');
        return;
      }
      var item = res.data.import || {};
      var errors = item.errors || [];
      var msg = 'Platform: ' + (item.platform || '—') + '\n'
        + 'Collection: ' + (item.collection || '—') + '\n'
        + 'Status: ' + (item.status || '—') + '\n'
        + 'Imported: ' + (item.imported_count || 0) + ' (created ' + (item.created_count || 0) + ', updated ' + (item.updated_count || 0) + ')\n'
        + 'Skipped: ' + (item.skipped_count || 0) + '\n'
        + 'Variants: ' + (item.variant_count || 0);
      if (errors.length) {
        msg += '\n\nIssues:\n' + errors.slice(0, 10).map(function (e) {
          return 'Row ' + e.row + ': ' + (e.reason || 'Unknown') + (e.title ? ' (' + e.title + ')' : '');
        }).join('\n');
      }
      alert(msg);
    });
  }

  function platformBadge(name) {
    var text = (name || '?').replace(/[^A-Za-z0-9]/g, '').slice(0, 2).toUpperCase();
    return text || '?';
  }

  function filteredImportSources() {
    return importSourcesCache.filter(function (src) {
      if (importWizard.filter !== 'all' && (src.category || 'other') !== importWizard.filter) return false;
      if (!importWizard.search) return true;
      var q = importWizard.search.toLowerCase();
      return (src.name || '').toLowerCase().indexOf(q) !== -1
        || (src.description || '').toLowerCase().indexOf(q) !== -1
        || (src.id || '').toLowerCase().indexOf(q) !== -1;
    });
  }

  function renderWizardSources() {
    var el = document.getElementById('commerce-import-wizard-sources');
    if (!el) return;
    var categories = [
      { id: 'store', label: 'Online stores' },
      { id: 'inventory', label: 'Inventory & stock' },
      { id: 'other', label: 'Other' },
    ];
    var sources = filteredImportSources();
    if (!sources.length) {
      el.innerHTML = '<p class="form-text">No platforms match your search.</p>';
      return;
    }
    var html = '';
    categories.forEach(function (cat) {
      var items = sources.filter(function (s) { return (s.category || 'other') === cat.id; });
      if (!items.length) return;
      html += '<div class="commerce-import-source-group"><h4 class="commerce-import-source-group-title">' + escapeHtml(cat.label) + '</h4><div class="commerce-import-source-grid">';
      html += items.map(function (src) {
        return '<button type="button" class="commerce-import-source-card' + (src.id === importWizard.source ? ' is-active' : '') + '" data-source="' + escapeHtml(src.id) + '">'
          + '<span class="commerce-import-source-badge">' + escapeHtml(platformBadge(src.name)) + '</span>'
          + '<span class="commerce-import-source-name">' + escapeHtml(src.name) + '</span>'
          + '<span class="commerce-import-source-desc">' + escapeHtml(src.description) + '</span>'
          + '</button>';
      }).join('');
      html += '</div></div>';
    });
    el.innerHTML = html;
    el.querySelectorAll('.commerce-import-source-card').forEach(function (btn) {
      btn.addEventListener('click', function () {
        importWizard.source = btn.getAttribute('data-source') || 'generic';
        renderWizardSources();
      });
    });
  }

  function getSelectedSource() {
    return importSourcesCache.find(function (s) { return s.id === importWizard.source; }) || { id: 'generic', name: 'Generic CSV' };
  }

  function updateWizardStepUI() {
    var step = importWizard.step;
    document.querySelectorAll('.commerce-import-wizard-pane').forEach(function (pane) {
      var paneStep = parseInt(pane.getAttribute('data-step'), 10);
      pane.hidden = paneStep !== step;
      pane.classList.toggle('is-active', paneStep === step);
    });
    document.querySelectorAll('.commerce-import-wizard-step-indicator').forEach(function (ind) {
      var indStep = parseInt(ind.getAttribute('data-step'), 10);
      ind.classList.toggle('is-active', indStep === step);
      ind.classList.toggle('is-done', indStep < step);
    });
    var subtitle = document.getElementById('commerce-import-wizard-subtitle');
    if (subtitle) subtitle.textContent = 'Step ' + step + ' of 4 — ' + (IMPORT_STEP_LABELS[step - 1] || '');

    var backBtn = document.getElementById('commerce-import-wizard-back');
    var nextBtn = document.getElementById('commerce-import-wizard-next');
    var importBtn = document.getElementById('commerce-import-wizard-import');
    var doneBtn = document.getElementById('commerce-import-wizard-done');
    var cancelBtn = document.getElementById('commerce-import-wizard-cancel');

    if (backBtn) backBtn.hidden = step <= 1 || step === 4;
    if (nextBtn) nextBtn.hidden = step !== 1 && step !== 2;
    if (importBtn) importBtn.hidden = step !== 3;
    if (doneBtn) doneBtn.hidden = step !== 4;
    if (cancelBtn) cancelBtn.hidden = step === 4;

    if (step === 2) {
      var src = getSelectedSource();
      var platformEl = document.getElementById('commerce-import-selected-platform');
      var stepsEl = document.getElementById('commerce-import-export-steps');
      if (platformEl) {
        platformEl.innerHTML = '<span class="commerce-import-source-badge">' + escapeHtml(platformBadge(src.name)) + '</span>'
          + '<div><strong>' + escapeHtml(src.name) + '</strong><div class="form-hint">' + escapeHtml(src.description || '') + '</div></div>';
      }
      if (stepsEl) stepsEl.textContent = src.export_steps || '';
    }
  }

  function openImportWizard() {
    importWizard = { step: 1, source: 'generic', csv: '', fileName: '', preview: null, filter: 'all', search: '' };
    var search = document.getElementById('commerce-import-search');
    if (search) search.value = '';
    document.querySelectorAll('#commerce-import-filters .btn').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-filter') === 'all');
    });
    var csvArea = document.getElementById('commerce-import-csv');
    if (csvArea) csvArea.value = '';
    var fileName = document.getElementById('commerce-import-file-name');
    if (fileName) { fileName.textContent = 'No file selected'; delete fileName.dataset.filename; }
    var fileInput = document.getElementById('commerce-import-file');
    if (fileInput) fileInput.value = '';

    var ensureSources = Promise.all([
      importSourcesCache.length
        ? Promise.resolve()
        : commercePost('import_sources').then(function (res) {
          importSourcesCache = (res.data && res.data.sources) || [];
        }),
      loadImportCollections(),
    ]);

    ensureSources.then(function () {
      var modeNew = document.querySelector('input[name="commerce-import-collection-mode"][value="new"]');
      if (!importCollectionsCache.length && modeNew) {
        modeNew.checked = true;
        var modeExisting = document.querySelector('input[name="commerce-import-collection-mode"][value="existing"]');
        if (modeExisting) modeExisting.checked = false;
      }
      updateImportCollectionModeUI();
      renderWizardSources();
      updateWizardStepUI();
      var modal = document.getElementById('commerce-import-wizard');
      if (modal) {
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('commerce-import-wizard-open');
      }
    });
  }

  function closeImportWizard() {
    var modal = document.getElementById('commerce-import-wizard');
    if (modal) {
      modal.hidden = true;
      modal.setAttribute('aria-hidden', 'true');
    }
    document.body.classList.remove('commerce-import-wizard-open');
  }

  function setImportCsvContent(text) {
    importWizard.csv = text || '';
    var textarea = document.getElementById('commerce-import-csv');
    if (textarea && text) textarea.value = text;
  }

  function readImportFile(file) {
    if (!file) return;
    importWizard.fileName = file.name;
    var reader = new FileReader();
    reader.onload = function (ev) {
      var nameEl = document.getElementById('commerce-import-file-name');
      if (nameEl) {
        nameEl.textContent = file.name;
        nameEl.dataset.filename = file.name;
      }
      setImportCsvContent(ev.target.result || '');
    };
    reader.readAsText(file);
  }

  function getImportCsvPayload() {
    return importWizard.csv || document.getElementById('commerce-import-csv')?.value || '';
  }

  function renderImportPreviewTable(preview) {
    var tableEl = document.getElementById('commerce-import-preview-table');
    if (!tableEl) return;
    if (!preview || !preview.length) {
      tableEl.innerHTML = '<p class="form-text">No products to preview.</p>';
      return;
    }
    var html = '<table><thead><tr><th>Title</th><th>SKU</th><th>Price</th><th>Type</th></tr></thead><tbody>';
    preview.slice(0, 50).forEach(function (row) {
      html += '<tr><td>' + escapeHtml(row.title) + '</td><td>' + escapeHtml(row.sku || '—') + '</td><td>'
        + ((row.price_cents || 0) / 100).toFixed(2) + '</td><td>' + (row.is_variant ? 'Variant' : 'Product') + '</td></tr>';
    });
    if (preview.length > 50) html += '<tr><td colspan="4">… and ' + (preview.length - 50) + ' more</td></tr>';
    html += '</tbody></table>';
    tableEl.innerHTML = html;
  }

  function runWizardDryRun() {
    var csv = getImportCsvPayload();
    var errEl = document.getElementById('commerce-import-review-error');
    if (errEl) errEl.hidden = true;
    return commercePost('import_dry_run', Object.assign({
      csv: csv,
      preset: importWizard.source,
      default_currency: document.getElementById('commerce-import-currency')?.value || 'eur',
    }, getImportCollectionPayload())).then(function (res) {
      if (res.status !== 'ok') {
        if (errEl) { errEl.hidden = false; errEl.textContent = res.message || 'Preview failed'; }
        return null;
      }
      importWizard.preview = res.data || {};
      var summary = document.getElementById('commerce-import-review-summary');
      if (summary) {
        var preview = (res.data.preview || []);
        var productCount = res.data.product_count != null
          ? res.data.product_count
          : preview.filter(function (r) { return !r.is_variant; }).length;
        var variants = res.data.variant_count != null
          ? res.data.variant_count
          : preview.filter(function (r) { return r.is_variant; }).length;
        summary.innerHTML = [
          { label: 'Products', value: productCount },
          { label: 'Variants', value: variants },
          { label: 'Rows', value: res.data.count || preview.length },
          { label: 'Collection', value: getImportCollectionLabel() },
          { label: 'Currency', value: (document.getElementById('commerce-import-currency')?.value || 'eur').toUpperCase() },
        ].map(function (s) {
          return '<div class="commerce-import-review-stat"><span class="form-hint">' + escapeHtml(s.label) + '</span><strong>' + escapeHtml(String(s.value)) + '</strong></div>';
        }).join('');
      }
      renderImportPreviewTable((res.data && res.data.preview) || []);
      return res.data;
    });
  }

  function runWizardImport() {
    var csv = getImportCsvPayload();
    var importBtn = document.getElementById('commerce-import-wizard-import');
    if (importBtn) { importBtn.disabled = true; importBtn.textContent = 'Importing…'; }
    return commercePost('import_csv', Object.assign({
      csv: csv,
      preset: importWizard.source,
      default_currency: document.getElementById('commerce-import-currency')?.value || 'eur',
      file_name: importWizard.fileName || null,
    }, getImportCollectionPayload())).then(function (res) {
      if (importBtn) { importBtn.disabled = false; importBtn.textContent = 'Import products'; }
      if (res.status !== 'ok') {
        var errEl = document.getElementById('commerce-import-review-error');
        if (errEl) {
          errEl.hidden = false;
          errEl.textContent = res.message || (res.permission_denied
            ? 'Permission denied. Your account needs Commerce admin access.'
            : 'Import failed');
        }
        return;
      }
      importWizard.step = 4;
      renderImportResult(res.data || {});
      updateWizardStepUI();
      loadImportHistory();
    });
  }

  function renderImportResult(data) {
    var card = document.getElementById('commerce-import-result-card');
    var errorsWrap = document.getElementById('commerce-import-result-errors');
    if (!card) return;
    var status = data.status || 'success';
    var src = getSelectedSource();
    var collection = data.collection || getImportCollectionLabel();
    var collectionNote = data.collection_created ? ' (new collection created)' : '';
    card.className = 'commerce-import-result-card is-' + status;
    var title = status === 'success' ? 'Import complete' : (status === 'partial' ? 'Import completed with issues' : 'Import failed');
    card.innerHTML = '<h3>' + escapeHtml(title) + '</h3>'
      + '<p><strong>' + (data.imported_count || 0) + '</strong> products imported into <code>' + escapeHtml(collection) + '</code>' + escapeHtml(collectionNote) + ' from ' + escapeHtml(src.name) + '.</p>'
      + '<p>Created: ' + (data.created_count || 0) + ' · Updated: ' + (data.updated_count || 0) + ' · Skipped: ' + (data.skipped_count || 0) + ' · Variants: ' + (data.variant_count || 0) + '</p>';
    var skipped = data.skipped || [];
    if (errorsWrap) {
      if (!skipped.length) {
        errorsWrap.hidden = true;
        errorsWrap.innerHTML = '';
      } else {
        errorsWrap.hidden = false;
        var html = '<table><thead><tr><th>Row</th><th>Product</th><th>Issue</th></tr></thead><tbody>';
        skipped.forEach(function (row) {
          html += '<tr><td>' + (row.row || '—') + '</td><td>' + escapeHtml(row.title || row.sku || '—') + '</td><td>' + escapeHtml(row.reason || '—') + '</td></tr>';
        });
        html += '</tbody></table>';
        errorsWrap.innerHTML = html;
      }
    }
  }

  function wizardNextStep() {
    if (importWizard.step === 1) {
      importWizard.step = 2;
      updateWizardStepUI();
      return;
    }
    if (importWizard.step === 2) {
      if (!validateImportCollectionStep()) {
        return;
      }
      if (!getImportCsvPayload().trim()) {
        alert('Please upload or paste a CSV file first.');
        return;
      }
      importWizard.step = 3;
      updateWizardStepUI();
      runWizardDryRun();
      return;
    }
  }

  function wizardBackStep() {
    if (importWizard.step > 1 && importWizard.step < 4) {
      importWizard.step--;
      updateWizardStepUI();
    }
  }

  function initImportWizard() {
    document.getElementById('commerce-import-start')?.addEventListener('click', openImportWizard);
    document.getElementById('commerce-import-history-refresh')?.addEventListener('click', loadImportHistory);
    document.getElementById('commerce-import-wizard-close')?.addEventListener('click', closeImportWizard);
    document.getElementById('commerce-import-wizard-overlay')?.addEventListener('click', closeImportWizard);
    document.getElementById('commerce-import-wizard-cancel')?.addEventListener('click', closeImportWizard);
    document.getElementById('commerce-import-wizard-back')?.addEventListener('click', wizardBackStep);
    document.getElementById('commerce-import-wizard-next')?.addEventListener('click', wizardNextStep);
    document.getElementById('commerce-import-wizard-import')?.addEventListener('click', runWizardImport);
    document.getElementById('commerce-import-wizard-done')?.addEventListener('click', closeImportWizard);

    document.querySelectorAll('input[name="commerce-import-collection-mode"]').forEach(function (radio) {
      radio.addEventListener('change', updateImportCollectionModeUI);
    });

    document.getElementById('commerce-import-search')?.addEventListener('input', function (e) {
      importWizard.search = e.target.value.trim();
      renderWizardSources();
    });

    document.querySelectorAll('#commerce-import-filters .btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        importWizard.filter = btn.getAttribute('data-filter') || 'all';
        document.querySelectorAll('#commerce-import-filters .btn').forEach(function (b) {
          b.classList.toggle('is-active', b === btn);
        });
        renderWizardSources();
      });
    });

    document.getElementById('commerce-import-browse')?.addEventListener('click', function () {
      document.getElementById('commerce-import-file')?.click();
    });

    document.getElementById('commerce-import-file')?.addEventListener('change', function (e) {
      var file = e.target.files && e.target.files[0];
      if (file) readImportFile(file);
    });

    var dropzone = document.getElementById('commerce-import-dropzone');
    if (dropzone) {
      dropzone.addEventListener('dragover', function (e) { e.preventDefault(); dropzone.classList.add('is-dragover'); });
      dropzone.addEventListener('dragleave', function () { dropzone.classList.remove('is-dragover'); });
      dropzone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropzone.classList.remove('is-dragover');
        var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (file) readImportFile(file);
      });
    }

    document.getElementById('commerce-import-csv')?.addEventListener('input', function (e) {
      importWizard.fileName = '';
      var nameEl = document.getElementById('commerce-import-file-name');
      if (nameEl) {
        delete nameEl.dataset.filename;
        nameEl.textContent = e.target.value.trim()
          ? 'CSV pasted (' + e.target.value.split('\n').length + ' lines)'
          : 'No file selected';
      }
      setImportCsvContent(e.target.value);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !document.getElementById('commerce-import-wizard')?.hidden) {
        closeImportWizard();
      }
    });
  }

  var migrationPreviewCache = [];

  function migrationOptions() {
    var sources = [];
    if (document.getElementById('commerce-migration-source-cart')?.checked) sources.push('shopping_cart');
    if (document.getElementById('commerce-migration-source-events')?.checked) sources.push('events');
    return {
      sources: sources,
      products_collection: document.getElementById('commerce-migration-products-collection')?.value || 'products',
      events_collection: document.getElementById('commerce-migration-events-collection')?.value || 'events-commerce',
      create_collections: !!document.getElementById('commerce-migration-create-collections')?.checked,
    };
  }

  function renderMigrationScan(data) {
    var el = document.getElementById('commerce-migration-scan');
    if (!el) return;
    var cart = data.shopping_cart || {};
    var events = data.events || {};
    var html = '<div class="commerce-migration-scan-grid">';
    html += '<div class="commerce-migration-scan-card"><h4>Shopping Cart</h4><p>'
      + (cart.detected ? 'Detected — ' + (cart.entry_count || 0) + ' legacy product entries' : 'No legacy product collections found')
      + '</p></div>';
    html += '<div class="commerce-migration-scan-card"><h4>Events</h4><p>'
      + (events.detected ? 'Detected — ' + (events.entry_count || 0) + ' legacy event entries' : 'No legacy events collections found')
      + '</p></div></div>';
    html += '<p class="form-hint">Imported source keys on record: ' + (data.imported_source_keys || 0) + '</p>';
    el.innerHTML = html;
  }

  function loadMigrationScan() {
    var el = document.getElementById('commerce-migration-scan');
    if (!el) return;
    el.innerHTML = '<div class="loading-state"><p>Scanning for legacy data…</p></div>';
    commercePost('migration_scan').then(function (res) {
      if (res.status !== 'ok') {
        el.innerHTML = '<p class="error">Scan failed</p>';
        return;
      }
      renderMigrationScan(res.data || {});
    });
  }

  function renderMigrationPreview(rows) {
    var wrap = document.getElementById('commerce-migration-preview-wrap');
    var table = document.getElementById('commerce-migration-preview-table');
    if (!wrap || !table) return;
    if (!rows.length) {
      wrap.hidden = false;
      table.innerHTML = '<p>No rows to preview for the selected sources.</p>';
      return;
    }
    var html = '<table class="payments-table"><thead><tr><th>Source</th><th>Title</th><th>Target</th><th>Fulfillment</th><th>Action</th></tr></thead><tbody>';
    rows.forEach(function (row) {
      html += '<tr><td>' + (row.source || '') + '</td><td>' + (row.title || '') + '</td><td>'
        + (row.target_collection || '') + '</td><td>' + (row.fulfillment || '—') + '</td><td>'
        + (row.action || '') + '</td></tr>';
    });
    html += '</tbody></table>';
    table.innerHTML = html;
    wrap.hidden = false;
  }

  function runMigrationPreview() {
    commercePost('migration_preview', migrationOptions()).then(function (res) {
      if (res.status !== 'ok') {
        alert('Preview failed: ' + (res.message || 'Unknown error'));
        return;
      }
      migrationPreviewCache = (res.data && res.data.preview) || [];
      renderMigrationPreview(migrationPreviewCache);
    });
  }

  function runMigrationImport() {
    if (!confirm('Import legacy entries into Commerce collections? Legacy plugins and source entries are not removed.')) {
      return;
    }
    commercePost('migration_import', migrationOptions()).then(function (res) {
      if (res.status !== 'ok') {
        alert('Import failed: ' + (res.message || 'Unknown error'));
        return;
      }
      var data = res.data || {};
      alert('Imported ' + (data.imported_count || 0) + ' entries (' + (data.skipped_count || 0) + ' skipped).');
      loadMigrationScan();
      loadMigrationHistory();
      runMigrationPreview();
    });
  }

  function loadMigrationHistory() {
    var el = document.getElementById('commerce-migration-history-list');
    if (!el) return;
    el.innerHTML = '<div class="loading-state"><p>Loading migration history…</p></div>';
    commercePost('migration_history').then(function (res) {
      var items = (res.data && res.data.migrations) || [];
      if (!items.length) {
        el.innerHTML = '<p>No migration runs yet.</p>';
        return;
      }
      var html = '';
      items.forEach(function (run) {
        html += '<div class="commerce-migration-history-card">';
        html += '<div class="commerce-migration-history-head"><strong>' + run.id + '</strong> — ' + (run.status || '') + ' · ' + (run.at || '') + '</div>';
        html += '<p>Imported: ' + (run.imported_count || 0) + ' · Skipped: ' + (run.skipped_count || 0);
        if (run.rolled_back_at) html += ' · Rolled back ' + run.rolled_back_at;
        html += '</p>';
        if (!run.rolled_back_at && (run.imported_count || 0) > 0) {
          html += '<button type="button" class="btn btn-secondary commerce-migration-rollback" data-id="' + run.id + '">Rollback</button>';
        }
        html += '</div>';
      });
      el.innerHTML = html;
      el.querySelectorAll('.commerce-migration-rollback').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-id');
          if (!id || !confirm('Delete Commerce entries created by this migration run?')) return;
          commercePost('migration_rollback', { id: id }).then(function (rb) {
            if (rb.status !== 'ok') {
              alert('Rollback failed: ' + (rb.message || 'Unknown error'));
              return;
            }
            loadMigrationHistory();
            loadMigrationScan();
            runMigrationPreview();
          });
        });
      });
    });
  }

  function loadMigrationTab() {
    loadMigrationScan();
    loadMigrationHistory();
  }

  function initMigrationTab() {
    document.getElementById('commerce-migration-refresh')?.addEventListener('click', loadMigrationScan);
    document.getElementById('commerce-migration-preview')?.addEventListener('click', runMigrationPreview);
    document.getElementById('commerce-migration-import')?.addEventListener('click', runMigrationImport);
    document.getElementById('commerce-migration-history-refresh')?.addEventListener('click', loadMigrationHistory);
  }

  function initCommerceTabs() {
    document.querySelectorAll('.commerce-tabs .payments-tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        var name = tab.getAttribute('data-tab');
        if (name === 'catalog') loadCatalog();
        if (name === 'orders') loadOrders();
        if (name === 'subscriptions') loadSubscriptions();
        if (name === 'discounts') loadDiscounts();
        if (name === 'mappings') loadMappings();
        if (name === 'shipping') loadShippingZones();
        if (name === 'tax') loadTaxRules();
        if (name === 'fieldtypes') loadFieldtypeProfiles();
        if (name === 'import') loadImportTab();
        if (name === 'migration') loadMigrationTab();
      });
    });
  }

  function initCommerceActions() {
    document.getElementById('commerce-reindex')?.addEventListener('click', function () {
      commercePost('reindex_catalog').then(function () { loadCatalog(); });
    });

    document.getElementById('commerce-subscriptions-refresh')?.addEventListener('click', loadSubscriptions);

    document.getElementById('commerce-add-discount')?.addEventListener('click', function () {
      var type = prompt('Rule type (percent_off, fixed_off, coupon, affiliate):', 'percent_off');
      if (!type) return;
      var rule = { type: type, enabled: true, percent_off: 10 };
      if (type === 'coupon') {
        rule.code = prompt('Coupon code:', 'SAVE10') || 'SAVE10';
      }
      if (type === 'affiliate') {
        rule.affiliate_id = prompt('Affiliate ID:', 'partner1') || 'partner1';
        rule.percent_off = 15;
      }
      if (type === 'fixed_off') {
        rule.fixed_off_cents = parseInt(prompt('Fixed off (cents):', '500') || '500', 10);
      }
      commercePost('save_discount', { rule: rule }).then(loadDiscounts);
    });

    initImportWizard();
    initMigrationTab();

    document.getElementById('commerce-mapping-form')?.addEventListener('submit', function (e) {
      e.preventDefault();
      var key = document.getElementById('commerce-mapping-key')?.value || '';
      var tier = document.getElementById('commerce-mapping-tier')?.value || '';
      if (!key || !tier) return;
      commercePost('save_mapping', { key: key, config: { grant_tier: tier } }).then(loadMappings);
    });

    document.getElementById('commerce-test-fulfillment')?.addEventListener('click', function () {
      var adapter = document.getElementById('commerce-fulfillment-adapter')?.value || 'manual';
      commercePost('test_fulfillment', { adapter: adapter }).then(function (res) {
        var el = document.getElementById('commerce-fulfillment-result');
        if (!el) return;
        el.hidden = false;
        el.textContent = (res.data && res.data.message) || res.message || JSON.stringify(res);
      });
    });

    document.getElementById('commerce-fulfillment-form')?.addEventListener('submit', function (e) {
      e.preventDefault();
      var adapter = document.getElementById('commerce-fulfillment-adapter')?.value || 'manual';
      commercePost('save_fulfillment', {
        connections: { active_adapter: adapter, shipstation: { mock: true } },
      }).then(function () {
        alert('Fulfillment settings saved');
      });
    });

    document.getElementById('commerce-add-shipping-zone')?.addEventListener('click', function () {
      shippingZonesCache.push({ id: 'zone_' + Date.now(), name: 'New zone', countries: ['*'], flat_rate_cents: 500 });
      renderShippingZones();
    });

    document.getElementById('commerce-save-shipping-zones')?.addEventListener('click', function () {
      var el = document.getElementById('commerce-shipping-zones-list');
      var zones = [];
      el?.querySelectorAll('.commerce-shipping-zone').forEach(function (row, idx) {
        var countriesRaw = row.querySelector('.zone-countries')?.value || '*';
        zones.push({
          id: shippingZonesCache[idx]?.id || ('zone_' + idx),
          name: row.querySelector('.zone-name')?.value || 'Zone',
          countries: countriesRaw.split(',').map(function (c) { return c.trim().toUpperCase(); }).filter(Boolean),
          flat_rate_cents: parseInt(row.querySelector('.zone-rate')?.value || '0', 10),
        });
      });
      commercePost('save_shipping_zones', { zones: zones }).then(function () {
        alert('Shipping zones saved');
        loadShippingZones();
      });
    });

    document.getElementById('commerce-add-tax-country')?.addEventListener('click', function () {
      taxRulesCache.country_rates.push({ country: '', rate: 0 });
      renderTaxCountries();
    });

    document.getElementById('commerce-save-tax-rules')?.addEventListener('click', function () {
      var el = document.getElementById('commerce-tax-country-list');
      var countryRates = [];
      el?.querySelectorAll('.commerce-tax-country').forEach(function (row) {
        var country = (row.querySelector('.tax-country')?.value || '').trim().toUpperCase();
        if (!country) return;
        countryRates.push({ country: country, rate: parseFloat(row.querySelector('.tax-rate')?.value || '0') || 0 });
      });
      commercePost('save_tax_rules', {
        enabled: !!document.getElementById('commerce-tax-enabled')?.checked,
        default_rate: parseFloat(document.getElementById('commerce-tax-default-rate')?.value || '0') || 0,
        country_rates: countryRates,
      }).then(function () {
        alert('Tax rules saved');
        loadTaxRules();
      });
    });

    document.getElementById('commerce-orders-list')?.addEventListener('click', function (e) {
      var btn = e.target.closest('.commerce-refund-order');
      if (!btn) return;
      var orderId = btn.getAttribute('data-order-id');
      var remaining = parseInt(btn.getAttribute('data-remaining'), 10) || 0;
      var currency = btn.getAttribute('data-currency') || 'EUR';
      var input = prompt(
        'Refund amount in ' + currency.toUpperCase() + ' (leave blank for full remaining ' + (remaining / 100).toFixed(2) + '):',
        ''
      );
      if (input === null) return;
      var body = { id: orderId };
      var trimmed = input.trim();
      if (trimmed !== '') {
        var amount = parseFloat(trimmed);
        if (isNaN(amount) || amount <= 0) {
          alert('Invalid refund amount');
          return;
        }
        body.amount_cents = Math.round(amount * 100);
      }
      var reason = prompt('Reason (optional):', '') || '';
      body.reason = reason;
      commercePost('refund_order', body).then(function (res) {
        if (res.status !== 'ok') {
          alert('Refund failed: ' + (res.message || 'Unknown error'));
          return;
        }
        alert('Refund processed');
        loadOrders();
      });
    });

    document.getElementById('commerce-add-fieldtype-profile')?.addEventListener('click', newFieldtypeProfile);
    document.getElementById('commerce-ft-save')?.addEventListener('click', saveFieldtypeProfile);
    document.getElementById('commerce-ft-cancel')?.addEventListener('click', closeFieldtypeModal);
    document.getElementById('commerce-ft-modal-close')?.addEventListener('click', closeFieldtypeModal);
    document.getElementById('commerce-ft-modal-overlay')?.addEventListener('click', closeFieldtypeModal);
    document.getElementById('commerce-ft-slug')?.addEventListener('input', updateTypePreview);
    document.querySelectorAll('.commerce-ft-preset').forEach(function (btn) {
      btn.addEventListener('click', function () {
        applyPreset(btn.getAttribute('data-preset'));
      });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !document.getElementById('commerce-fieldtype-modal')?.hidden) {
        closeFieldtypeModal();
      }
    });
    document.getElementById('commerce-ft-add-interval')?.addEventListener('click', function () {
      if (!fieldtypeProfileDraft) return;
      var value = document.getElementById('commerce-ft-interval-value').value.trim();
      var label = document.getElementById('commerce-ft-interval-label').value.trim();
      if (!value) return;
      if (!label) label = value.replace(/_/g, ' ');
      if (!fieldtypeProfileDraft.settings.interval_options) fieldtypeProfileDraft.settings.interval_options = [];
      fieldtypeProfileDraft.settings.interval_options.push({ value: value, label: label });
      document.getElementById('commerce-ft-interval-value').value = '';
      document.getElementById('commerce-ft-interval-label').value = '';
      renderIntervalRows(fieldtypeProfileDraft.settings.interval_options);
    });
    document.getElementById('commerce-ft-add-custom-field')?.addEventListener('click', function () {
      if (!fieldtypeProfileDraft) return;
      var key = document.getElementById('commerce-ft-cf-key').value.trim().replace(/[^a-z0-9_]/gi, '_').toLowerCase();
      var label = document.getElementById('commerce-ft-cf-label').value.trim();
      if (!key) return;
      if (!label) label = key;
      if (!fieldtypeProfileDraft.settings.custom_fields) fieldtypeProfileDraft.settings.custom_fields = [];
      fieldtypeProfileDraft.settings.custom_fields.push({ key: key, label: label, type: 'text' });
      document.getElementById('commerce-ft-cf-key').value = '';
      document.getElementById('commerce-ft-cf-label').value = '';
      renderCustomFieldRows(fieldtypeProfileDraft.settings.custom_fields);
    });
  }

  function init() {
    initCommerceTabs();
    initCommerceActions();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
