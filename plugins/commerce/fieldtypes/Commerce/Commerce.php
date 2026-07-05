<?php
/**
 * Commerce fieldtype v2 — fulfillment + purchase_options[].
 */

namespace Birkly\FieldTypes\Commerce;

if (!class_exists('Birkly\FieldTypes\BaseField')) {
    require_once __DIR__ . '/../../../../fieldtypes/BaseField.php';
}
if (!class_exists('CommerceSchema')) {
    require_once __DIR__ . '/../../includes/CommerceSchema.php';
}

use Birkly\FieldTypes\BaseField;

class Commerce extends BaseField
{
    private array $commerceSettings;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->commerceSettings = $this->resolveCommerceSettings($config);
    }

    public function getType(): string
    {
        return 'commerce';
    }

    public function getCategory(): string
    {
        return 'Commerce';
    }

    public function render(): string
    {
        $value = \commerce_normalize_value($this->value);
        $name = htmlspecialchars($this->name);
        $settings = $this->commerceSettings;
        $fulfillments = $settings['enabled_fulfillments'] ?? \CommerceSchema::FULFILLMENTS;
        $intervals = $settings['interval_options'] ?? ['week', 'month', 'year'];
        $currencies = $settings['currency_options'] ?? ['eur', 'usd', 'gbp', 'jpy'];
        $fulfillment = htmlspecialchars($value['fulfillment']);

        $html = '<div class="commerce-fieldtype" data-field-name="' . $name . '">';

        if ($settings['enable_fulfillment'] ?? true) {
            $html .= '<div class="commerce-fieldtype-row">';
            $html .= '<label>Fulfillment</label>';
            $html .= '<select name="' . $name . '[fulfillment]" class="form-control commerce-fulfillment-select">';
            foreach ($fulfillments as $f) {
                $sel = $value['fulfillment'] === $f ? ' selected' : '';
                $label = ucfirst($f === 'none' ? 'No fulfillment' : $f);
                $html .= '<option value="' . htmlspecialchars($f) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
            }
            $html .= '</select></div>';
        }

        if ($settings['enable_purchase_options'] ?? true) {
            $html .= '<div class="commerce-fieldtype-section commerce-purchase-options">';
            $html .= '<label>Purchase options</label>';
            $html .= '<div class="commerce-options-list" data-field="' . $name . '">';
            foreach ($value['purchase_options'] as $idx => $opt) {
                $html .= $this->renderPurchaseOptionRow($name, $idx, $opt, $intervals, $currencies);
            }
            $html .= '</div>';
            $html .= '<button type="button" class="btn btn-secondary commerce-add-option" data-field="' . $name . '">Add option</button>';
            $html .= '</div>';
        }

        if ($settings['enable_quantity'] ?? true) {
            $qtyVal = $value['quantity_available'] === null ? '' : (string) (int) $value['quantity_available'];
            $html .= '<div class="commerce-fieldtype-row commerce-panel commerce-panel-register"' . ($value['fulfillment'] !== 'register' ? ' style="display:none"' : '') . '>';
            $html .= '<label>Quantity available (spots)</label>';
            $html .= '<input type="number" min="0" class="form-control" name="' . $name . '[quantity_available]" '
                . 'placeholder="Unlimited" value="' . htmlspecialchars($qtyVal) . '">';
            $html .= '<small class="form-text">Used for event registration capacity</small></div>';
        }

        if ($settings['enable_sku'] ?? true) {
            $html .= '<div class="commerce-fieldtype-row">';
            $html .= '<label>SKU (optional)</label>';
            $html .= '<input type="text" class="form-control" name="' . $name . '[sku]" value="' . htmlspecialchars($value['sku'] ?? '') . '">';
            $html .= '</div>';
        }

        $html .= '<div class="commerce-panel commerce-panel-ship"' . ($value['fulfillment'] !== 'ship' ? ' style="display:none"' : '') . '>';
        if ($settings['enable_weight'] ?? true) {
            $weight = $value['weight_grams'] === null ? '' : (string) (int) $value['weight_grams'];
            $html .= '<div class="commerce-fieldtype-row">';
            $html .= '<label>Weight (grams, optional)</label>';
            $html .= '<input type="number" min="0" class="form-control" name="' . $name . '[weight_grams]" value="' . htmlspecialchars($weight) . '">';
            $html .= '</div>';
        }
        $html .= '</div>';

        $html .= '<div class="commerce-panel commerce-panel-deliver"' . ($value['fulfillment'] !== 'deliver' ? ' style="display:none"' : '') . '>';
        $html .= '<div class="commerce-fieldtype-row">';
        $html .= '<label>Delivery mode</label>';
        $html .= '<select name="' . $name . '[delivery_mode]" class="form-control">';
        foreach (['secure_download' => 'Secure download', 'external_url' => 'External URL'] as $mode => $label) {
            $sel = ($value['delivery_mode'] ?? '') === $mode ? ' selected' : '';
            $html .= '<option value="' . $mode . '"' . $sel . '>' . $label . '</option>';
        }
        $html .= '</select></div>';
        $html .= '<div class="commerce-fieldtype-row">';
        $html .= '<label>Asset path (vault)</label>';
        $html .= '<input type="text" class="form-control" name="' . $name . '[delivery_asset]" value="' . htmlspecialchars($value['delivery_asset'] ?? '') . '">';
        $html .= '</div>';
        $html .= '<div class="commerce-fieldtype-row">';
        $html .= '<label>External URL (optional)</label>';
        $html .= '<input type="url" class="form-control" name="' . $name . '[delivery_url]" value="' . htmlspecialchars($value['delivery_url'] ?? '') . '">';
        $html .= '</div>';
        $html .= '</div>';

        foreach ($settings['custom_fields'] ?? [] as $cfIdx => $cf) {
            $cfKey = htmlspecialchars($cf['key'] ?? 'field_' . $cfIdx);
            $cfLabel = htmlspecialchars($cf['label'] ?? $cfKey);
            $cfVal = htmlspecialchars((string) ($value['custom_fields'][$cf['key'] ?? ''] ?? ''));
            $html .= '<div class="commerce-fieldtype-row">';
            $html .= '<label>' . $cfLabel . '</label>';
            $html .= '<input type="text" class="form-control" name="' . $name . '[custom_fields][' . $cfKey . ']" value="' . $cfVal . '">';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    private function renderPurchaseOptionRow(string $name, int $idx, array $opt, array $intervals, array $currencies): string
    {
        $id = htmlspecialchars($opt['id'] ?? 'opt_' . ($idx + 1));
        $label = htmlspecialchars($opt['label'] ?? '');
        $billing = htmlspecialchars($opt['billing'] ?? 'one_time');
        $priceDisplay = number_format((int) ($opt['price_cents'] ?? 0) / 100, 2, '.', '');
        $currency = htmlspecialchars(strtolower($opt['currency'] ?? 'eur'));
        $interval = htmlspecialchars($opt['interval'] ?? 'month');
        $centsId = $name . '_opt_' . $idx . '_price_cents';

        $html = '<div class="commerce-option-row" data-option-index="' . $idx . '">';
        $html .= '<input type="hidden" name="' . $name . '[purchase_options][' . $idx . '][id]" value="' . $id . '">';
        $html .= '<div class="commerce-fieldtype-row"><label>Label</label>';
        $html .= '<input type="text" class="form-control" name="' . $name . '[purchase_options][' . $idx . '][label]" value="' . $label . '"></div>';
        $html .= '<div class="commerce-fieldtype-row"><label>Price</label>';
        $html .= '<input type="number" step="0.01" min="0" class="form-control commerce-price-input" '
            . 'data-cents-target="' . htmlspecialchars($centsId) . '" value="' . htmlspecialchars($priceDisplay) . '">';
        $html .= '<input type="hidden" name="' . $name . '[purchase_options][' . $idx . '][price_cents]" id="' . htmlspecialchars($centsId) . '" '
            . 'value="' . (int) ($opt['price_cents'] ?? 0) . '"></div>';
        $html .= '<div class="commerce-fieldtype-row"><label>Currency</label><select name="' . $name . '[purchase_options][' . $idx . '][currency]" class="form-control">';
        foreach ($currencies as $cur) {
            $sel = strtolower($opt['currency'] ?? 'eur') === $cur ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($cur) . '"' . $sel . '>' . strtoupper($cur) . '</option>';
        }
        $html .= '</select></div>';
        $html .= '<div class="commerce-fieldtype-row"><label>Billing</label>';
        $html .= '<select name="' . $name . '[purchase_options][' . $idx . '][billing]" class="form-control commerce-billing-select">';
        $html .= '<option value="one_time"' . ($billing === 'one_time' ? ' selected' : '') . '>One-time</option>';
        $html .= '<option value="recurring"' . ($billing === 'recurring' ? ' selected' : '') . '>Recurring</option>';
        $html .= '</select></div>';
        $html .= '<div class="commerce-fieldtype-row commerce-interval-row"' . ($billing !== 'recurring' ? ' style="display:none"' : '') . '>';
        $html .= '<label>Interval</label><select name="' . $name . '[purchase_options][' . $idx . '][interval]" class="form-control">';
        foreach ($intervals as $iv) {
            $sel = ($opt['interval'] ?? 'month') === $iv ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($iv) . '"' . $sel . '>' . ucfirst($iv) . 'ly</option>';
        }
        $html .= '</select></div>';
        $html .= '<button type="button" class="btn btn-secondary commerce-remove-option">Remove</button>';
        $html .= '</div>';
        return $html;
    }

    public function validate($value): array
    {
        return \CommerceSchema::validate(is_array($value) ? $value : [], $this->required);
    }

    public function getJavaScript(): string
    {
        return <<<'JS'
(function(){
  document.addEventListener('input', function(e) {
    if (!e.target.classList.contains('commerce-price-input')) return;
    var targetId = e.target.getAttribute('data-cents-target');
    var hidden = document.getElementById(targetId);
    if (!hidden) return;
    var val = parseFloat(e.target.value) || 0;
    hidden.value = Math.round(val * 100);
  });
  document.addEventListener('change', function(e) {
    if (e.target.classList.contains('commerce-billing-select')) {
      var row = e.target.closest('.commerce-option-row');
      if (!row) return;
      var intervalRow = row.querySelector('.commerce-interval-row');
      if (intervalRow) {
        intervalRow.style.display = e.target.value === 'recurring' ? '' : 'none';
      }
    }
    if (e.target.classList.contains('commerce-fulfillment-select')) {
      var root = e.target.closest('.commerce-fieldtype');
      if (!root) return;
      var f = e.target.value;
      root.querySelectorAll('.commerce-panel').forEach(function(p){ p.style.display = 'none'; });
      var panel = root.querySelector('.commerce-panel-' + f);
      if (panel) panel.style.display = '';
      var regQty = root.querySelector('.commerce-panel-register');
      if (regQty && f === 'register') regQty.style.display = '';
    }
  });
  document.addEventListener('click', function(e) {
    if (e.target.classList.contains('commerce-remove-option')) {
      var row = e.target.closest('.commerce-option-row');
      if (row && row.parentNode.querySelectorAll('.commerce-option-row').length > 1) {
        row.remove();
      }
    }
  });
})();
JS;
    }

    public function getCSS(): string
    {
        return '
        .commerce-fieldtype { display: flex; flex-direction: column; gap: 0.75rem; }
        .commerce-fieldtype-row label { display: block; font-weight: 600; margin-bottom: 0.25rem; }
        .commerce-option-row { border: 1px solid var(--border-color, #ddd); padding: 0.75rem; border-radius: 4px; margin-bottom: 0.5rem; }
        .commerce-purchase-options > label { font-weight: 600; }
        ';
    }

    public function getAdminConfig(): array
    {
        return ['commerce_settings' => $this->commerceSettings];
    }

    public function getSchema(): array
    {
        return [
            'fulfillment' => 'string',
            'purchase_options' => 'array',
            'quantity_available' => 'integer|null',
            'sku' => 'string|null',
            'weight_grams' => 'integer|null',
            'delivery_asset' => 'string|null',
            'delivery_url' => 'string|null',
            'delivery_mode' => 'string',
            'custom_fields' => 'array',
            'price_cents' => 'integer',
            'currency' => 'string',
            'billing' => 'string',
            'interval' => 'string|null',
        ];
    }

    public function getDefaultValue()
    {
        return \CommerceSchema::defaultValue();
    }

    public function processValue($value)
    {
        return \commerce_normalize_value($value);
    }

    public function processLoadedValue($value)
    {
        return \commerce_normalize_value($value);
    }

    public function migrateLegacyValue(array $value): array
    {
        return \CommerceSchema::migrateLegacyValue($value);
    }

    public function renderListCell($value, array $context = []): string
    {
        $v = \commerce_normalize_value($value);
        $price = number_format($v['price_cents'] / 100, 2);
        $cur = strtoupper($v['currency']);
        $fulfillment = $v['fulfillment'] ?? '';
        return '<span class="ft-list-text">' . htmlspecialchars($cur . ' ' . $price . ' · ' . $fulfillment) . '</span>';
    }

    public function getConfigUI($fieldIndex, $existingData = []): string
    {
        $settings = $existingData['commerce_settings'] ?? [];
        $enabledFulfillments = $settings['enabled_fulfillments'] ?? ['ship', 'deliver', 'register', 'none'];
        $intervalOptions = $settings['interval_options'] ?? ['week', 'month', 'year'];
        $currencyOptions = $settings['currency_options'] ?? ['eur', 'usd', 'gbp', 'jpy'];

        $checks = function (string $key, bool $default = true) use ($settings): string {
            $on = ($settings[$key] ?? $default) ? ' checked' : '';
            return $on;
        };

        $fulfillmentChecks = '';
        foreach (\CommerceSchema::FULFILLMENTS as $f) {
            $checked = in_array($f, $enabledFulfillments, true) ? ' checked' : '';
            $fulfillmentChecks .= '<label><input type="checkbox" name="fields[' . $fieldIndex . '][commerce_settings][enabled_fulfillments][]" value="' . $f . '"' . $checked . '> ' . ucfirst($f) . '</label> ';
        }

        $intervalChecks = '';
        foreach (['week', 'month', 'year'] as $iv) {
            $checked = in_array($iv, $intervalOptions, true) ? ' checked' : '';
            $intervalChecks .= '<label><input type="checkbox" name="fields[' . $fieldIndex . '][commerce_settings][interval_options][]" value="' . $iv . '"' . $checked . '> ' . ucfirst($iv) . '</label> ';
        }

        return <<<HTML
            <div class="field-config-section commerce-field-config">
                <label>Commerce field options</label>
                <div class="commerce-config-toggles">
                    <label><input type="checkbox" name="fields[{$fieldIndex}][commerce_settings][enable_fulfillment]" value="1"{$checks('enable_fulfillment')}> Fulfillment selector</label>
                    <label><input type="checkbox" name="fields[{$fieldIndex}][commerce_settings][enable_purchase_options]" value="1"{$checks('enable_purchase_options')}> Purchase options editor</label>
                    <label><input type="checkbox" name="fields[{$fieldIndex}][commerce_settings][enable_quantity]" value="1"{$checks('enable_quantity')}> Quantity / capacity</label>
                    <label><input type="checkbox" name="fields[{$fieldIndex}][commerce_settings][enable_sku]" value="1"{$checks('enable_sku')}> SKU</label>
                    <label><input type="checkbox" name="fields[{$fieldIndex}][commerce_settings][enable_weight]" value="1"{$checks('enable_weight')}> Weight (ship)</label>
                </div>
                <div class="field-config-section">
                    <label>Enabled fulfillment presets</label>
                    <div>{$fulfillmentChecks}</div>
                </div>
                <div class="field-config-section">
                    <label>Interval options</label>
                    <div>{$intervalChecks}</div>
                </div>
                <small class="form-text">Configure which commerce sub-fields appear on entries in this collection.</small>
            </div>
        HTML;
    }

    private function resolveCommerceSettings(array $config): array
    {
        $defaults = [
            'enable_fulfillment' => true,
            'enable_purchase_options' => true,
            'enable_quantity' => true,
            'enable_sku' => true,
            'enable_weight' => true,
            'enabled_fulfillments' => \CommerceSchema::FULFILLMENTS,
            'interval_options' => ['week', 'month', 'year'],
            'currency_options' => ['eur', 'usd', 'gbp', 'jpy'],
            'custom_fields' => [],
        ];
        $fromConfig = $config['commerce_settings'] ?? [];
        if (!is_array($fromConfig)) {
            return $defaults;
        }
        return array_merge($defaults, $fromConfig);
    }
}
