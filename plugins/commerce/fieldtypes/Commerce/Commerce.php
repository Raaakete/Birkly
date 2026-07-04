<?php
/**
 * Commerce fieldtype — price, billing, quantity, SKU on any collection entry.
 */

namespace Birkly\FieldTypes\Commerce;

if (!class_exists('Birkly\FieldTypes\BaseField')) {
    require_once __DIR__ . '/../../../../fieldtypes/BaseField.php';
}

use Birkly\FieldTypes\BaseField;

class Commerce extends BaseField
{
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
        $value = $this->normalizeValue($this->value);
        $name = htmlspecialchars($this->name);
        $priceDisplay = number_format($value['price_cents'] / 100, 2, '.', '');
        $currency = htmlspecialchars(strtoupper($value['currency']));
        $billing = htmlspecialchars($value['billing']);
        $interval = htmlspecialchars($value['interval'] ?? 'month');
        $qty = $value['quantity_available'];
        $qtyVal = $qty === null ? '' : (string) (int) $qty;
        $sku = htmlspecialchars($value['sku'] ?? '');

        $html = '<div class="commerce-fieldtype" data-field-name="' . $name . '">';
        $html .= '<div class="commerce-fieldtype-row">';
        $html .= '<label>Price</label>';
        $html .= '<input type="number" step="0.01" min="0" class="form-control commerce-price-input" '
            . 'data-cents-target="' . $name . '_price_cents" value="' . htmlspecialchars($priceDisplay) . '">';
        $html .= '<input type="hidden" name="' . $name . '[price_cents]" id="' . $name . '_price_cents" '
            . 'value="' . (int) $value['price_cents'] . '">';
        $html .= '</div>';

        $html .= '<div class="commerce-fieldtype-row">';
        $html .= '<label>Currency</label>';
        $html .= '<select name="' . $name . '[currency]" class="form-control">';
        foreach (['eur', 'usd', 'gbp', 'jpy'] as $cur) {
            $sel = $value['currency'] === $cur ? ' selected' : '';
            $html .= '<option value="' . $cur . '"' . $sel . '>' . strtoupper($cur) . '</option>';
        }
        $html .= '</select></div>';

        $html .= '<div class="commerce-fieldtype-row">';
        $html .= '<label>Billing</label>';
        $html .= '<select name="' . $name . '[billing]" class="form-control commerce-billing-select">';
        $html .= '<option value="one_time"' . ($billing === 'one_time' ? ' selected' : '') . '>One-time</option>';
        $html .= '<option value="recurring"' . ($billing === 'recurring' ? ' selected' : '') . '>Recurring</option>';
        $html .= '</select></div>';

        $html .= '<div class="commerce-fieldtype-row commerce-interval-row"' . ($billing !== 'recurring' ? ' style="display:none"' : '') . '>';
        $html .= '<label>Interval</label>';
        $html .= '<select name="' . $name . '[interval]" class="form-control">';
        $html .= '<option value="month"' . ($interval === 'month' ? ' selected' : '') . '>Monthly</option>';
        $html .= '<option value="year"' . ($interval === 'year' ? ' selected' : '') . '>Yearly</option>';
        $html .= '</select></div>';

        $html .= '<div class="commerce-fieldtype-row">';
        $html .= '<label>Quantity available</label>';
        $html .= '<input type="number" min="0" class="form-control" name="' . $name . '[quantity_available]" '
            . 'placeholder="Unlimited" value="' . htmlspecialchars($qtyVal) . '">';
        $html .= '<small class="form-text">Leave empty for unlimited</small>';
        $html .= '</div>';

        $html .= '<div class="commerce-fieldtype-row">';
        $html .= '<label>SKU (optional)</label>';
        $html .= '<input type="text" class="form-control" name="' . $name . '[sku]" value="' . $sku . '">';
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }

    public function validate($value): array
    {
        $errors = [];
        $normalized = $this->normalizeValue($value);

        if ($this->required && (int) $normalized['price_cents'] < 0) {
            $errors[] = 'Price is required';
        }

        if (!in_array($normalized['billing'], ['one_time', 'recurring'], true)) {
            $errors[] = 'Billing must be one_time or recurring';
        }

        if ($normalized['billing'] === 'recurring' && !in_array($normalized['interval'], ['month', 'year'], true)) {
            $errors[] = 'Interval required for recurring billing';
        }

        return $errors;
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
    if (!e.target.classList.contains('commerce-billing-select')) return;
    var row = e.target.closest('.commerce-fieldtype');
    if (!row) return;
    var intervalRow = row.querySelector('.commerce-interval-row');
    if (intervalRow) {
      intervalRow.style.display = e.target.value === 'recurring' ? '' : 'none';
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
        ';
    }

    public function getAdminConfig(): array
    {
        return [];
    }

    public function getSchema(): array
    {
        return [
            'price_cents' => 'integer',
            'currency' => 'string',
            'billing' => 'string',
            'interval' => 'string|null',
            'quantity_available' => 'integer|null',
            'sku' => 'string|null',
        ];
    }

    public function getDefaultValue()
    {
        return [
            'price_cents' => 0,
            'currency' => 'eur',
            'billing' => 'one_time',
            'interval' => null,
            'quantity_available' => null,
            'sku' => null,
        ];
    }

    public function processValue($value)
    {
        return $this->normalizeValue($value);
    }

    public function processLoadedValue($value)
    {
        return $this->normalizeValue($value);
    }

    public function renderListCell($value, array $context = []): string
    {
        $v = $this->normalizeValue($value);
        $price = number_format($v['price_cents'] / 100, 2);
        $cur = strtoupper($v['currency']);
        return '<span class="ft-list-text">' . htmlspecialchars($cur . ' ' . $price) . '</span>';
    }

    private function normalizeValue($value): array
    {
        $defaults = $this->getDefaultValue();
        if (!is_array($value)) {
            return $defaults;
        }

        $priceCents = isset($value['price_cents']) ? (int) $value['price_cents'] : $defaults['price_cents'];
        if ($priceCents === 0 && isset($value['price']) && is_numeric($value['price'])) {
            $priceCents = (int) round((float) $value['price'] * 100);
        }

        $qty = $value['quantity_available'] ?? null;
        if ($qty === '' || $qty === false) {
            $qty = null;
        } elseif ($qty !== null) {
            $qty = (int) $qty;
        }

        $billing = $value['billing'] ?? 'one_time';
        $interval = $value['interval'] ?? null;
        if ($billing === 'recurring' && !in_array($interval, ['month', 'year'], true)) {
            $interval = 'month';
        }
        if ($billing !== 'recurring') {
            $interval = null;
        }

        $sku = isset($value['sku']) && $value['sku'] !== '' ? trim((string) $value['sku']) : null;

        return [
            'price_cents' => max(0, $priceCents),
            'currency' => strtolower((string) ($value['currency'] ?? $defaults['currency'])),
            'billing' => in_array($billing, ['one_time', 'recurring'], true) ? $billing : 'one_time',
            'interval' => $interval,
            'quantity_available' => $qty,
            'sku' => $sku,
        ];
    }
}
