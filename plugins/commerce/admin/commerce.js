(function () {
  'use strict';

  var COMMERCE_API = (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function')
    ? Birkly.url('/api/index.php?route=commerce')
    : '/api/index.php?route=commerce';

  function commercePost(action, body) {
    body = body || {};
    body.action = action;
    if (typeof Birkly !== 'undefined' && Birkly.csrfToken) {
      body._csrf = Birkly.csrfToken;
    }
    return fetch(COMMERCE_API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    }).then(function (r) { return r.json(); });
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

  function loadFieldtypeProfiles() {
    var el = document.getElementById('commerce-fieldtype-profiles-list');
    if (!el) return;
    el.innerHTML = '<div class="loading-state"><p>Loading fieldtype profiles…</p></div>';
    commercePost('list_fieldtype_profiles').then(function (res) {
      var profiles = (res.data && res.data.profiles) || [];
      if (!profiles.length) {
        el.innerHTML = '<p>No profiles configured.</p>';
        return;
      }
      var html = '<table class="payments-table"><thead><tr><th>Name</th><th>Type key</th><th>Default fulfillment</th><th></th></tr></thead><tbody>';
      profiles.forEach(function (p) {
        html += '<tr><td>' + (p.name || p.id) + (p.is_default ? ' (default)' : '') + '</td><td><code>' + (p.type_key || '') + '</code></td><td>'
          + ((p.settings && p.settings.default_fulfillment) || '—') + '</td><td>'
          + '<button type="button" class="btn btn-secondary commerce-edit-profile" data-id="' + p.id + '">Edit</button> '
          + (p.is_default ? '' : '<button type="button" class="btn btn-secondary commerce-delete-profile" data-id="' + p.id + '">Delete</button>')
          + '</td></tr>';
      });
      html += '</tbody></table>';
      el.innerHTML = html;
      el.querySelectorAll('.commerce-edit-profile').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-id');
          var profile = profiles.find(function (p) { return p.id === id; });
          if (profile) openFieldtypeEditor(profile);
        });
      });
      el.querySelectorAll('.commerce-delete-profile').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var id = btn.getAttribute('data-id');
          if (!confirm('Delete this fieldtype profile? Collections using it will need updating.')) return;
          commercePost('delete_fieldtype_profile', { id: id }).then(function () { loadFieldtypeProfiles(); });
        });
      });
    });
  }

  function renderIntervalRows(intervals) {
    var list = document.getElementById('commerce-ft-intervals-list');
    if (!list) return;
    list.innerHTML = '';
    (intervals || []).forEach(function (iv, idx) {
      var row = document.createElement('div');
      row.className = 'commerce-ft-row';
      row.innerHTML = '<code>' + iv.value + '</code> — ' + iv.label
        + ' <button type="button" class="btn btn-secondary commerce-ft-remove-interval" data-idx="' + idx + '">Remove</button>';
      list.appendChild(row);
    });
    list.querySelectorAll('.commerce-ft-remove-interval').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var i = parseInt(btn.getAttribute('data-idx'), 10);
        fieldtypeProfileDraft.settings.interval_options.splice(i, 1);
        renderIntervalRows(fieldtypeProfileDraft.settings.interval_options);
      });
    });
  }

  function renderCustomFieldRows(fields) {
    var list = document.getElementById('commerce-ft-custom-fields-list');
    if (!list) return;
    list.innerHTML = '';
    (fields || []).forEach(function (cf, idx) {
      var row = document.createElement('div');
      row.className = 'commerce-ft-row';
      row.innerHTML = '<code>' + cf.key + '</code> — ' + cf.label
        + ' <button type="button" class="btn btn-secondary commerce-ft-remove-cf" data-idx="' + idx + '">Remove</button>';
      list.appendChild(row);
    });
    list.querySelectorAll('.commerce-ft-remove-cf').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var i = parseInt(btn.getAttribute('data-idx'), 10);
        fieldtypeProfileDraft.settings.custom_fields.splice(i, 1);
        renderCustomFieldRows(fieldtypeProfileDraft.settings.custom_fields);
      });
    });
  }

  function openFieldtypeEditor(profile) {
    fieldtypeProfileDraft = JSON.parse(JSON.stringify(profile));
    if (!fieldtypeProfileDraft.settings) fieldtypeProfileDraft.settings = {};
    document.getElementById('commerce-fieldtype-editor').hidden = false;
    document.getElementById('commerce-fieldtype-editor-title').textContent = profile.is_default ? 'Edit default profile' : 'Edit profile';
    document.getElementById('commerce-ft-profile-id').value = profile.id || '';
    document.getElementById('commerce-ft-name').value = profile.name || '';
    document.getElementById('commerce-ft-slug').value = profile.slug || '';
    document.getElementById('commerce-ft-slug').disabled = !!profile.is_default;
    document.getElementById('commerce-ft-default-fulfillment').value = profile.settings.default_fulfillment || 'ship';

    var fulfillments = ['ship', 'deliver', 'register', 'none'];
    var ftEl = document.getElementById('commerce-ft-fulfillments');
    ftEl.innerHTML = fulfillments.map(function (f) {
      var checked = (profile.settings.enabled_fulfillments || fulfillments).indexOf(f) >= 0 ? ' checked' : '';
      return '<label><input type="checkbox" class="commerce-ft-fulfillment-cb" value="' + f + '"' + checked + '> ' + f + '</label>';
    }).join(' ');

    var toggles = [
      ['enable_fulfillment', 'Fulfillment selector'],
      ['enable_purchase_options', 'Purchase options'],
      ['enable_quantity', 'Quantity / capacity'],
      ['enable_sku', 'SKU'],
      ['enable_weight', 'Weight (ship)'],
      ['enable_delivery', 'Delivery panel'],
    ];
    document.getElementById('commerce-ft-toggles').innerHTML = toggles.map(function (t) {
      var checked = profile.settings[t[0]] !== false ? ' checked' : '';
      return '<label><input type="checkbox" class="commerce-ft-toggle-cb" data-key="' + t[0] + '"' + checked + '> ' + t[1] + '</label>';
    }).join(' ');

    renderIntervalRows(profile.settings.interval_options || []);
    renderCustomFieldRows(profile.settings.custom_fields || []);
  }

  function newFieldtypeProfile() {
    openFieldtypeEditor({
      id: '',
      name: '',
      slug: '',
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
    });
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
      alert('Profile name is required');
      return;
    }
    commercePost('save_fieldtype_profile', { profile: profile }).then(function (res) {
      if (res.status !== 'ok') {
        alert(res.message || 'Save failed');
        return;
      }
      document.getElementById('commerce-fieldtype-editor').hidden = true;
      fieldtypeProfileDraft = null;
      loadFieldtypeProfiles();
    });
  }

  function initCommerceTabs() {
    document.querySelectorAll('.commerce-tabs .payments-tab').forEach(function (tab) {
      tab.addEventListener('click', function () {
        var name = tab.getAttribute('data-tab');
        if (name === 'catalog') loadCatalog();
        if (name === 'orders') loadOrders();
        if (name === 'discounts') loadDiscounts();
        if (name === 'mappings') loadMappings();
        if (name === 'shipping') loadShippingZones();
        if (name === 'fieldtypes') loadFieldtypeProfiles();
      });
    });
  }

  function initCommerceActions() {
    document.getElementById('commerce-reindex')?.addEventListener('click', function () {
      commercePost('reindex_catalog').then(function () { loadCatalog(); });
    });

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

    document.getElementById('commerce-import-dry-run')?.addEventListener('click', function () {
      var csv = document.getElementById('commerce-import-csv')?.value || '';
      var preset = document.getElementById('commerce-import-preset')?.value || 'generic';
      var collection = document.getElementById('commerce-import-collection')?.value || 'products';
      commercePost('import_dry_run', { csv: csv, preset: preset, collection: collection }).then(function (res) {
        var el = document.getElementById('commerce-import-result');
        if (!el) return;
        el.hidden = false;
        el.textContent = res.status === 'ok'
          ? 'Dry run: ' + ((res.data && res.data.count) || 0) + ' entries'
          : (res.message || 'Failed');
      });
    });

    document.getElementById('commerce-import-form')?.addEventListener('submit', function (e) {
      e.preventDefault();
      var csv = document.getElementById('commerce-import-csv')?.value || '';
      var preset = document.getElementById('commerce-import-preset')?.value || 'generic';
      var collection = document.getElementById('commerce-import-collection')?.value || 'products';
      commercePost('import_csv', { csv: csv, preset: preset, collection: collection }).then(function (res) {
        var el = document.getElementById('commerce-import-result');
        if (!el) return;
        el.hidden = false;
        el.textContent = res.status === 'ok'
          ? 'Imported ' + ((res.data && res.data.count) || 0) + ' entries'
          : (res.message || 'Failed');
      });
    });

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

    document.getElementById('commerce-add-fieldtype-profile')?.addEventListener('click', newFieldtypeProfile);
    document.getElementById('commerce-ft-save')?.addEventListener('click', saveFieldtypeProfile);
    document.getElementById('commerce-ft-cancel')?.addEventListener('click', function () {
      document.getElementById('commerce-fieldtype-editor').hidden = true;
      fieldtypeProfileDraft = null;
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
