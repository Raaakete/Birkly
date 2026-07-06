<?php
/**
 * Commerce bundle fieldtype — purchasable bundle of catalog entries.
 */

namespace Birkly\FieldTypes\CommerceBundle;

if (!class_exists('Birkly\FieldTypes\BaseField')) {
    require_once __DIR__ . '/../../../../fieldtypes/BaseField.php';
}
if (!class_exists('CommerceBundleSchema')) {
    require_once __DIR__ . '/../../includes/CommerceBundleSchema.php';
}

use Birkly\FieldTypes\BaseField;

class CommerceBundle extends BaseField
{
    public function getType(): string
    {
        return 'commerce_bundle';
    }

    public function getCategory(): string
    {
        return 'Commerce';
    }

    public function render(): string
    {
        $value = commerce_bundle_normalize_value($this->value);
        $name = htmlspecialchars($this->name);
        $json = htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8');
        $pricingMode = htmlspecialchars($value['pricing_mode']);
        $currency = htmlspecialchars($value['currency']);
        $fulfillment = htmlspecialchars($value['fulfillment']);
        $discount = (int) $value['bundle_discount_percent'];
        $fixedPrice = $value['fixed_price_cents'] === null ? '' : number_format($value['fixed_price_cents'] / 100, 2, '.', '');
        $qtyVal = $value['quantity_available'] === null ? '' : (string) (int) $value['quantity_available'];

        $html = '<div class="commerce-bundle-fieldtype" data-field-name="' . $name . '" id="commerce_bundle_field_' . $name . '">';
        $html .= '<input type="hidden" name="' . $name . '" id="commerce_bundle_json_' . $name . '" value="' . $json . '">';

        $html .= '<div class="commerce-bundle-section">';
        $html .= '<label>Bundle components</label>';
        $html .= '<div class="commerce-bundle-items" id="commerce_bundle_items_' . $name . '"></div>';
        $html .= '<button type="button" class="btn btn-secondary commerce-bundle-add-btn" data-field="' . $name . '">Add from catalog</button>';
        $html .= '</div>';

        $html .= '<div class="commerce-fieldtype-row">';
        $html .= '<label>Pricing mode</label>';
        $html .= '<select class="form-control commerce-bundle-pricing-mode" data-field="' . $name . '">';
        foreach (['sum' => 'Sum of components', 'sum_with_discount' => 'Sum with discount', 'fixed' => 'Fixed price'] as $mode => $label) {
            $sel = $pricingMode === $mode ? ' selected' : '';
            $html .= '<option value="' . $mode . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
        }
        $html .= '</select></div>';

        $html .= '<div class="commerce-fieldtype-row commerce-bundle-discount-row" data-field="' . $name . '"'
            . ($pricingMode !== 'sum_with_discount' ? ' style="display:none"' : '') . '>';
        $html .= '<label>Bundle discount (%)</label>';
        $html .= '<input type="number" min="0" max="100" class="form-control commerce-bundle-discount" data-field="' . $name . '" value="' . $discount . '">';
        $html .= '</div>';

        $html .= '<div class="commerce-fieldtype-row commerce-bundle-fixed-row" data-field="' . $name . '"'
            . ($pricingMode !== 'fixed' ? ' style="display:none"' : '') . '>';
        $html .= '<label>Fixed price</label>';
        $html .= '<input type="number" step="0.01" min="0" class="form-control commerce-bundle-fixed-price" data-field="' . $name . '" value="' . htmlspecialchars($fixedPrice) . '">';
        $html .= '</div>';

        $html .= '<div class="commerce-fieldtype-row">';
        $html .= '<label>Currency</label>';
        $html .= '<select class="form-control commerce-bundle-currency" data-field="' . $name . '">';
        foreach (['eur', 'usd', 'gbp', 'jpy'] as $cur) {
            $sel = $value['currency'] === $cur ? ' selected' : '';
            $html .= '<option value="' . $cur . '"' . $sel . '>' . strtoupper($cur) . '</option>';
        }
        $html .= '</select></div>';

        $html .= '<div class="commerce-fieldtype-row">';
        $html .= '<label>Fulfillment</label>';
        $html .= '<select class="form-control commerce-bundle-fulfillment" data-field="' . $name . '">';
        foreach (\CommerceBundleSchema::FULFILLMENTS as $f) {
            $sel = $fulfillment === $f ? ' selected' : '';
            $label = ucfirst($f === 'none' ? 'No fulfillment' : $f);
            $html .= '<option value="' . htmlspecialchars($f) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
        }
        $html .= '</select></div>';

        $html .= '<div class="commerce-fieldtype-row">';
        $html .= '<label>Quantity available</label>';
        $html .= '<input type="number" min="0" class="form-control commerce-bundle-qty" data-field="' . $name . '" placeholder="Unlimited" value="' . htmlspecialchars($qtyVal) . '">';
        $html .= '<small class="form-text">Also limited by component stock (minimum across components)</small>';
        $html .= '</div>';

        $html .= '<input type="hidden" class="commerce-bundle-billing" data-field="' . $name . '" value="one_time">';

        $html .= '<div class="commerce-bundle-picker-modal" id="commerce_bundle_picker_' . $name . '" hidden>';
        $html .= '<div class="commerce-bundle-picker-backdrop"></div>';
        $html .= '<div class="commerce-bundle-picker-dialog">';
        $html .= '<h4>Select catalog item</h4>';
        $html .= '<input type="search" class="form-control commerce-bundle-picker-search" placeholder="Search…">';
        $html .= '<div class="commerce-bundle-picker-list"></div>';
        $html .= '<button type="button" class="btn btn-secondary commerce-bundle-picker-close">Close</button>';
        $html .= '</div></div>';

        $html .= '</div>';
        return $html;
    }

    public function validate($value): array
    {
        return \CommerceBundleSchema::validate(is_array($value) ? $value : [], $this->required);
    }

    public function getJavaScript(): string
    {
        return <<<'JS'
(function(){
  var API = (typeof Birkly !== 'undefined' && typeof Birkly.url === 'function')
    ? Birkly.url('/api/index.php?route=commerce')
    : '/api/index.php?route=commerce';

  function postCatalogList() {
    var body = { action: 'list_catalog' };
    if (typeof Birkly !== 'undefined' && Birkly.csrfToken) {
      body._csrf = Birkly.csrfToken;
    }
    return fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    }).then(function(r) { return r.json(); });
  }

  function getFieldState(fieldName) {
    var input = document.getElementById('commerce_bundle_json_' + fieldName);
    if (!input || !input.value) {
      return { items: [], pricing_mode: 'sum', bundle_discount_percent: 0, fixed_price_cents: null, currency: 'eur', billing: 'one_time', interval: null, quantity_available: null, fulfillment: 'ship' };
    }
    try { return JSON.parse(input.value); } catch (e) { return { items: [] }; }
  }

  function setFieldState(fieldName, state) {
    var input = document.getElementById('commerce_bundle_json_' + fieldName);
    if (input) input.value = JSON.stringify(state);
    renderItems(fieldName, state);
  }

  function renderItems(fieldName, state) {
    var list = document.getElementById('commerce_bundle_items_' + fieldName);
    if (!list) return;
    if (!state.items || !state.items.length) {
      list.innerHTML = '<p class="text-muted">No components selected</p>';
      return;
    }
    var html = '<table class="commerce-bundle-items-table"><thead><tr><th>Item</th><th>Qty</th><th></th></tr></thead><tbody>';
    state.items.forEach(function(item, idx) {
      html += '<tr data-index="' + idx + '"><td>' + (item.title || item.entry_id) + '<br><small>' + item.collection + ':' + item.entry_id + '</small></td>';
      html += '<td><input type="number" min="1" class="form-control commerce-bundle-item-qty" data-field="' + fieldName + '" data-index="' + idx + '" value="' + (item.quantity || 1) + '"></td>';
      html += '<td><button type="button" class="btn btn-danger commerce-bundle-remove-item" data-field="' + fieldName + '" data-index="' + idx + '">Remove</button></td></tr>';
    });
    html += '</tbody></table>';
    list.innerHTML = html;
  }

  function syncFromControls(fieldName) {
    var state = getFieldState(fieldName);
    var root = document.getElementById('commerce_bundle_field_' + fieldName);
    if (!root) return;
    var modeEl = root.querySelector('.commerce-bundle-pricing-mode');
    var discountEl = root.querySelector('.commerce-bundle-discount');
    var fixedEl = root.querySelector('.commerce-bundle-fixed-price');
    var currencyEl = root.querySelector('.commerce-bundle-currency');
    var fulfillmentEl = root.querySelector('.commerce-bundle-fulfillment');
    var qtyEl = root.querySelector('.commerce-bundle-qty');
    state.pricing_mode = modeEl ? modeEl.value : 'sum';
    state.bundle_discount_percent = discountEl ? parseInt(discountEl.value, 10) || 0 : 0;
    var fixedVal = fixedEl ? parseFloat(fixedEl.value) : NaN;
    state.fixed_price_cents = isNaN(fixedVal) ? null : Math.round(fixedVal * 100);
    state.currency = currencyEl ? currencyEl.value : 'eur';
    state.fulfillment = fulfillmentEl ? fulfillmentEl.value : 'ship';
    state.billing = 'one_time';
    state.interval = null;
    var qtyRaw = qtyEl ? qtyEl.value.trim() : '';
    state.quantity_available = qtyRaw === '' ? null : parseInt(qtyRaw, 10);
    setFieldState(fieldName, state);
  }

  function openPicker(fieldName) {
    var modal = document.getElementById('commerce_bundle_picker_' + fieldName);
    if (!modal) return;
    modal.hidden = false;
    var listEl = modal.querySelector('.commerce-bundle-picker-list');
    listEl.innerHTML = '<p>Loading catalog…</p>';
    postCatalogList().then(function(res) {
      var items = (res.data && res.data.catalog) || [];
      items = items.filter(function(i) { return !i.is_bundle; });
      listEl.innerHTML = '';
      if (!items.length) {
        listEl.innerHTML = '<p>No catalog items available. Index commerce entries first.</p>';
        return;
      }
      items.forEach(function(item) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-link commerce-bundle-pick-item';
        btn.dataset.field = fieldName;
        btn.dataset.collection = item.collection_slug;
        btn.dataset.entryId = item.entry_id;
        btn.dataset.title = item.entry_title || item.entry_slug || item.entry_id;
        var price = (item.price_cents || 0) / 100;
        btn.textContent = (item.entry_title || item.entry_slug) + ' (' + item.collection_slug + ') — ' + (item.currency || 'eur').toUpperCase() + ' ' + price.toFixed(2);
        listEl.appendChild(btn);
      });
    });
  }

  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('commerce-bundle-add-btn')) {
      openPicker(e.target.getAttribute('data-field'));
    }
    if (e.target.classList.contains('commerce-bundle-picker-close') || e.target.classList.contains('commerce-bundle-picker-backdrop')) {
      var modal = e.target.closest('.commerce-bundle-picker-modal');
      if (modal) modal.hidden = true;
    }
    if (e.target.classList.contains('commerce-bundle-pick-item')) {
      var fieldName = e.target.getAttribute('data-field');
      var state = getFieldState(fieldName);
      state.items = state.items || [];
      state.items.push({
        collection: e.target.getAttribute('data-collection'),
        entry_id: e.target.getAttribute('data-entry-id'),
        title: e.target.getAttribute('data-title'),
        quantity: 1
      });
      setFieldState(fieldName, state);
      syncFromControls(fieldName);
      var modal = document.getElementById('commerce_bundle_picker_' + fieldName);
      if (modal) modal.hidden = true;
    }
    if (e.target.classList.contains('commerce-bundle-remove-item')) {
      var fn = e.target.getAttribute('data-field');
      var idx = parseInt(e.target.getAttribute('data-index'), 10);
      var st = getFieldState(fn);
      st.items.splice(idx, 1);
      setFieldState(fn, st);
      syncFromControls(fn);
    }
  });

  document.addEventListener('change', function(e) {
    if (e.target.classList.contains('commerce-bundle-pricing-mode')) {
      var fn = e.target.getAttribute('data-field');
      var root = document.getElementById('commerce_bundle_field_' + fn);
      if (root) {
        var mode = e.target.value;
        var disc = root.querySelector('.commerce-bundle-discount-row');
        var fixed = root.querySelector('.commerce-bundle-fixed-row');
        if (disc) disc.style.display = mode === 'sum_with_discount' ? '' : 'none';
        if (fixed) fixed.style.display = mode === 'fixed' ? '' : 'none';
      }
      syncFromControls(fn);
    }
    if (e.target.classList.contains('commerce-bundle-discount') ||
        e.target.classList.contains('commerce-bundle-fixed-price') ||
        e.target.classList.contains('commerce-bundle-currency') ||
        e.target.classList.contains('commerce-bundle-fulfillment') ||
        e.target.classList.contains('commerce-bundle-qty')) {
      syncFromControls(e.target.getAttribute('data-field'));
    }
  });

  document.addEventListener('input', function(e) {
    if (e.target.classList.contains('commerce-bundle-item-qty')) {
      var fn = e.target.getAttribute('data-field');
      var idx = parseInt(e.target.getAttribute('data-index'), 10);
      var st = getFieldState(fn);
      if (st.items[idx]) {
        st.items[idx].quantity = Math.max(1, parseInt(e.target.value, 10) || 1);
        setFieldState(fn, st);
      }
    }
  });

  document.querySelectorAll('.commerce-bundle-fieldtype').forEach(function(root) {
    var fieldName = root.getAttribute('data-field-name');
    renderItems(fieldName, getFieldState(fieldName));
  });
})();
JS;
    }

    public function getCSS(): string
    {
        return '
        .commerce-bundle-fieldtype { display: flex; flex-direction: column; gap: 0.75rem; }
        .commerce-bundle-fieldtype .commerce-fieldtype-row label { display: block; font-weight: 600; margin-bottom: 0.25rem; }
        .commerce-bundle-items-table { width: 100%; border-collapse: collapse; margin-bottom: 0.5rem; }
        .commerce-bundle-items-table td, .commerce-bundle-items-table th { padding: 0.35rem 0.5rem; border-bottom: 1px solid var(--border-color, #ddd); text-align: left; }
        .commerce-bundle-picker-modal { position: fixed; inset: 0; z-index: 10000; display: flex; align-items: center; justify-content: center; }
        .commerce-bundle-picker-modal[hidden] { display: none !important; }
        .commerce-bundle-picker-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.4); }
        .commerce-bundle-picker-dialog { position: relative; background: var(--surface-color, #fff); padding: 1rem; border-radius: 6px; max-width: 520px; width: 90%; max-height: 80vh; overflow: auto; z-index: 1; }
        .commerce-bundle-picker-list { max-height: 320px; overflow: auto; margin: 0.75rem 0; display: flex; flex-direction: column; gap: 0.25rem; }
        ';
    }

    public function getAdminConfig(): array
    {
        return [];
    }

    public function getSchema(): array
    {
        return [
            'items' => 'array',
            'pricing_mode' => 'string',
            'bundle_discount_percent' => 'integer',
            'fixed_price_cents' => 'integer|null',
            'currency' => 'string',
            'billing' => 'string',
            'interval' => 'string|null',
            'quantity_available' => 'integer|null',
            'fulfillment' => 'string',
        ];
    }

    public function getDefaultValue()
    {
        return \CommerceBundleSchema::defaultValue();
    }

    public function processValue($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }
        return commerce_bundle_normalize_value($value);
    }

    public function processLoadedValue($value)
    {
        return $this->processValue($value);
    }

    public function renderListCell($value, array $context = []): string
    {
        $v = commerce_bundle_normalize_value($value);
        $count = count($v['items'] ?? []);
        $mode = $v['pricing_mode'] ?? 'sum';
        return '<span class="ft-list-text">' . htmlspecialchars($count . ' items · ' . $mode) . '</span>';
    }

    public function getConfigUI($fieldIndex, $existingData = []): string
    {
        return <<<'HTML'
            <div class="field-config-section commerce-bundle-field-config">
                <p class="form-text">Bundle field: pick catalog entries (with Commerce field) and set bundle pricing rules. Billing is one-time only in v1.</p>
            </div>
        HTML;
    }
}
