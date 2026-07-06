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
  var importCsvContent = '';
  var selectedImportSource = 'generic';

  function loadImportTab() {
    if (importSourcesCache.length) {
      renderImportSources();
      return;
    }
    commercePost('import_sources').then(function (res) {
      importSourcesCache = (res.data && res.data.sources) || [];
      renderImportSources();
      selectImportSource(selectedImportSource);
    });
  }

  function renderImportSources() {
    var el = document.getElementById('commerce-import-sources');
    if (!el) return;
    el.innerHTML = importSourcesCache.map(function (src) {
      return '<button type="button" class="commerce-import-source-card' + (src.id === selectedImportSource ? ' is-active' : '') + '" data-source="' + escapeHtml(src.id) + '">'
        + '<span class="commerce-import-source-name">' + escapeHtml(src.name) + '</span>'
        + '<span class="commerce-import-source-desc">' + escapeHtml(src.description) + '</span>'
        + '</button>';
    }).join('');
    el.querySelectorAll('.commerce-import-source-card').forEach(function (btn) {
      btn.addEventListener('click', function () {
        selectImportSource(btn.getAttribute('data-source'));
      });
    });
  }

  function selectImportSource(sourceId) {
    selectedImportSource = sourceId || 'generic';
    document.getElementById('commerce-import-preset').value = selectedImportSource;
    var src = importSourcesCache.find(function (s) { return s.id === selectedImportSource; }) || {};
    document.getElementById('commerce-import-source-title').textContent = src.name ? 'Import from ' + src.name : 'Import products';
    document.getElementById('commerce-import-source-steps').textContent = src.export_steps || '';
    document.querySelectorAll('.commerce-import-source-card').forEach(function (card) {
      card.classList.toggle('is-active', card.getAttribute('data-source') === selectedImportSource);
    });
  }

  function setImportCsvContent(text) {
    importCsvContent = text || '';
    var textarea = document.getElementById('commerce-import-csv');
    if (textarea && text) textarea.value = text;
    var submitBtn = document.getElementById('commerce-import-submit');
    if (submitBtn) submitBtn.disabled = !importCsvContent.trim();
    var nameEl = document.getElementById('commerce-import-file-name');
    if (nameEl && !nameEl.dataset.filename) {
      nameEl.textContent = importCsvContent ? 'CSV loaded (' + importCsvContent.split('\n').length + ' lines)' : 'No file selected';
    }
  }

  function readImportFile(file) {
    if (!file) return;
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

  function renderImportPreview(preview) {
    var wrap = document.getElementById('commerce-import-preview');
    var tableEl = document.getElementById('commerce-import-preview-table');
    if (!wrap || !tableEl) return;
    if (!preview || !preview.length) {
      wrap.hidden = true;
      return;
    }
    wrap.hidden = false;
    var html = '<table class="payments-table"><thead><tr><th>Title</th><th>SKU</th><th>Price</th><th>Type</th></tr></thead><tbody>';
    preview.slice(0, 50).forEach(function (row) {
      html += '<tr><td>' + escapeHtml(row.title) + '</td><td>' + escapeHtml(row.sku || '—') + '</td><td>'
        + ((row.price_cents || 0) / 100).toFixed(2) + '</td><td>' + (row.is_variant ? 'Variant' : 'Product') + '</td></tr>';
    });
    if (preview.length > 50) {
      html += '<tr><td colspan="4">… and ' + (preview.length - 50) + ' more</td></tr>';
    }
    html += '</tbody></table>';
    tableEl.innerHTML = html;
  }

  function runImportDryRun() {
    var csv = importCsvContent || document.getElementById('commerce-import-csv')?.value || '';
    if (!csv.trim()) return;
    commercePost('import_dry_run', {
      csv: csv,
      preset: selectedImportSource,
      collection: document.getElementById('commerce-import-collection')?.value || 'products',
      default_currency: document.getElementById('commerce-import-currency')?.value || 'eur',
    }).then(function (res) {
      var el = document.getElementById('commerce-import-result');
      if (res.status !== 'ok') {
        if (el) { el.hidden = false; el.className = 'payments-test-result error'; el.textContent = res.message || 'Preview failed'; }
        return;
      }
      renderImportPreview((res.data && res.data.preview) || []);
      if (el) {
        el.hidden = false;
        el.className = 'payments-test-result success';
        el.textContent = 'Ready to import ' + ((res.data && res.data.count) || 0) + ' products.';
      }
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
        if (name === 'import') loadImportTab();
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

    document.getElementById('commerce-import-browse')?.addEventListener('click', function () {
      document.getElementById('commerce-import-file')?.click();
    });

    document.getElementById('commerce-import-file')?.addEventListener('change', function (e) {
      var file = e.target.files && e.target.files[0];
      if (file) readImportFile(file);
    });

    var dropzone = document.getElementById('commerce-import-dropzone');
    if (dropzone) {
      dropzone.addEventListener('dragover', function (e) {
        e.preventDefault();
        dropzone.classList.add('is-dragover');
      });
      dropzone.addEventListener('dragleave', function () {
        dropzone.classList.remove('is-dragover');
      });
      dropzone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropzone.classList.remove('is-dragover');
        var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (file) readImportFile(file);
      });
    }

    document.getElementById('commerce-import-csv')?.addEventListener('input', function (e) {
      var nameEl = document.getElementById('commerce-import-file-name');
      if (nameEl) {
        delete nameEl.dataset.filename;
        nameEl.textContent = e.target.value.trim()
          ? 'CSV pasted (' + e.target.value.split('\n').length + ' lines)'
          : 'No file selected';
      }
      setImportCsvContent(e.target.value);
    });

    document.getElementById('commerce-import-dry-run')?.addEventListener('click', runImportDryRun);

    document.getElementById('commerce-import-form')?.addEventListener('submit', function (e) {
      e.preventDefault();
      var csv = importCsvContent || document.getElementById('commerce-import-csv')?.value || '';
      if (!csv.trim()) return;
      var submitBtn = document.getElementById('commerce-import-submit');
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Importing…'; }
      commercePost('import_csv', {
        csv: csv,
        preset: selectedImportSource,
        collection: document.getElementById('commerce-import-collection')?.value || 'products',
        default_currency: document.getElementById('commerce-import-currency')?.value || 'eur',
      }).then(function (res) {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Import products'; }
        var el = document.getElementById('commerce-import-result');
        if (!el) return;
        el.hidden = false;
        el.className = 'payments-test-result ' + (res.status === 'ok' ? 'success' : 'error');
        el.textContent = res.status === 'ok'
          ? 'Imported ' + ((res.data && res.data.count) || 0) + ' products.'
          : (res.message || 'Import failed');
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
