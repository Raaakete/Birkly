<?php
/**
 * Event fieldtype — schedule, location, capacity, recurrence (v1).
 */

namespace Birkly\FieldTypes\Event;

if (!class_exists('Birkly\FieldTypes\BaseField')) {
    require_once __DIR__ . '/../../../../fieldtypes/BaseField.php';
}
if (!class_exists('EventSchema')) {
    require_once __DIR__ . '/../../includes/EventSchema.php';
}

use Birkly\FieldTypes\BaseField;

class Event extends BaseField
{
    public function getType(): string
    {
        return 'event';
    }

    public function getCategory(): string
    {
        return 'Events';
    }

    public function render(): string
    {
        $value = event_normalize_value($this->value);
        $name = htmlspecialchars($this->name);
        $rec = $value['recurrence'];
        $qtyVal = $value['quantity_available'] === null ? '' : (string) (int) $value['quantity_available'];

        $html = '<div class="event-fieldtype" data-field-name="' . $name . '">';

        $html .= '<div class="event-fieldtype-section"><strong>Schedule</strong>';
        $html .= '<div class="event-fieldtype-row"><label>Starts</label>';
        $html .= '<input type="datetime-local" class="form-control" name="' . $name . '[starts_at]" value="' . htmlspecialchars($this->toDatetimeLocal($value['starts_at'])) . '"></div>';
        $html .= '<div class="event-fieldtype-row"><label>Ends</label>';
        $html .= '<input type="datetime-local" class="form-control" name="' . $name . '[ends_at]" value="' . htmlspecialchars($this->toDatetimeLocal($value['ends_at'])) . '"></div>';
        $html .= '<div class="event-fieldtype-row"><label>Timezone</label>';
        $html .= '<input type="text" class="form-control" name="' . $name . '[timezone]" placeholder="UTC" value="' . htmlspecialchars($value['timezone']) . '"></div>';
        $html .= '</div>';

        $html .= '<div class="event-fieldtype-section"><strong>Recurrence</strong>';
        $html .= '<div class="event-fieldtype-row"><label><input type="checkbox" name="' . $name . '[recurrence][enabled]" value="1"' . ($rec['enabled'] ? ' checked' : '') . '> Recurring series</label></div>';
        $html .= '<div class="event-fieldtype-row"><label>Frequency</label><select name="' . $name . '[recurrence][frequency]" class="form-control">';
        foreach (['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $freq => $label) {
            $sel = $rec['frequency'] === $freq ? ' selected' : '';
            $html .= '<option value="' . $freq . '"' . $sel . '>' . $label . '</option>';
        }
        $html .= '</select></div>';
        $html .= '<div class="event-fieldtype-row"><label>Interval</label>';
        $html .= '<input type="number" min="1" class="form-control" name="' . $name . '[recurrence][interval]" value="' . (int) $rec['interval'] . '"></div>';
        $html .= '<div class="event-fieldtype-row"><label>Until (date)</label>';
        $html .= '<input type="date" class="form-control" name="' . $name . '[recurrence][until]" value="' . htmlspecialchars((string) ($rec['until'] ?? '')) . '"></div>';
        $html .= '<div class="event-fieldtype-row"><label>Count (max occurrences)</label>';
        $html .= '<input type="number" min="1" class="form-control" name="' . $name . '[recurrence][count]" placeholder="Optional" value="' . htmlspecialchars($rec['count'] !== null ? (string) $rec['count'] : '') . '"></div>';
        $skipDates = implode("\n", $rec['skip_dates']);
        $html .= '<div class="event-fieldtype-row"><label>Skip dates (one per line, YYYY-MM-DD)</label>';
        $html .= '<textarea class="form-control" name="' . $name . '[recurrence][skip_dates_text]" rows="2">' . htmlspecialchars($skipDates) . '</textarea></div>';
        $html .= '</div>';

        $html .= '<div class="event-fieldtype-section"><strong>Location</strong>';
        $html .= '<div class="event-fieldtype-row"><label>Venue</label>';
        $html .= '<input type="text" class="form-control" name="' . $name . '[venue]" value="' . htmlspecialchars($value['venue']) . '"></div>';
        $html .= '<div class="event-fieldtype-row"><label>Virtual URL</label>';
        $html .= '<input type="url" class="form-control" name="' . $name . '[virtual_url]" value="' . htmlspecialchars($value['virtual_url']) . '"></div>';
        $html .= '<div class="event-fieldtype-row"><label><input type="checkbox" name="' . $name . '[hybrid]" value="1"' . ($value['hybrid'] ? ' checked' : '') . '> Hybrid (in-person + virtual)</label></div>';
        $html .= '</div>';

        $html .= '<div class="event-fieldtype-section"><strong>Capacity</strong>';
        $html .= '<div class="event-fieldtype-row"><label>Quantity available (free RSVP)</label>';
        $html .= '<input type="number" min="0" class="form-control" name="' . $name . '[quantity_available]" placeholder="Unlimited" value="' . htmlspecialchars($qtyVal) . '"></div>';
        $html .= '<div class="event-fieldtype-row"><label>Commerce field (paid tickets)</label>';
        $html .= '<input type="text" class="form-control" name="' . $name . '[commerce_field]" placeholder="e.g. tickets" value="' . htmlspecialchars($value['commerce_field']) . '">';
        $html .= '<small class="form-text">Optional — field name with Commerce fulfillment: register on this entry</small></div>';
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }

    public function validate($value): array
    {
        return EventSchema::validate(is_array($value) ? $value : [], $this->required);
    }

    public function getJavaScript(): string
    {
        return '';
    }

    public function getCSS(): string
    {
        return '
        .event-fieldtype { display: flex; flex-direction: column; gap: 1rem; }
        .event-fieldtype-section { border: 1px solid var(--border-color, #ddd); padding: 0.75rem; border-radius: 4px; }
        .event-fieldtype-row { margin-bottom: 0.5rem; }
        .event-fieldtype-row label { display: block; font-weight: 600; margin-bottom: 0.25rem; }
        ';
    }

    public function getAdminConfig(): array
    {
        return [];
    }

    public function getSchema(): array
    {
        return [
            'starts_at' => 'string',
            'ends_at' => 'string',
            'timezone' => 'string',
            'recurrence' => 'array',
            'venue' => 'string',
            'virtual_url' => 'string',
            'hybrid' => 'boolean',
            'quantity_available' => 'integer|null',
            'commerce_field' => 'string',
        ];
    }

    public function getDefaultValue()
    {
        return EventSchema::defaultValue();
    }

    public function processValue($value)
    {
        if (!is_array($value)) {
            return EventSchema::defaultValue();
        }
        if (isset($value['recurrence']['skip_dates_text'])) {
            $lines = preg_split('/\r\n|\r|\n/', (string) $value['recurrence']['skip_dates_text']);
            $value['recurrence']['skip_dates'] = array_values(array_filter(array_map('trim', $lines ?: [])));
            unset($value['recurrence']['skip_dates_text']);
        }
        return event_normalize_value($value);
    }

    public function processLoadedValue($value)
    {
        return event_normalize_value($value);
    }

    public function renderListCell($value, array $context = []): string
    {
        $v = event_normalize_value($value);
        $start = $v['starts_at'] !== '' ? $v['starts_at'] : '—';
        $venue = $v['venue'] !== '' ? $v['venue'] : ($v['virtual_url'] !== '' ? 'Online' : '');
        $rec = !empty($v['recurrence']['enabled']) ? ' · recurring' : '';
        return '<span class="ft-list-text">' . htmlspecialchars($start . ($venue ? ' @ ' . $venue : '') . $rec) . '</span>';
    }

    public function getConfigUI($fieldIndex, $existingData = []): string
    {
        return '<div class="field-config-section"><p class="form-text">Event field — schedule, recurrence, location, and optional Commerce link for paid registration.</p></div>';
    }

    private function toDatetimeLocal(string $iso): string
    {
        if ($iso === '') {
            return '';
        }
        try {
            $dt = new \DateTimeImmutable($iso);
            return $dt->format('Y-m-d\TH:i');
        } catch (\Exception $e) {
            return '';
        }
    }
}
