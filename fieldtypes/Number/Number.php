<?php
namespace HaruCMS\FieldTypes\Number;

use HaruCMS\FieldTypes\BaseField;

class Number extends BaseField {
    protected $min;
    protected $max;
    protected $step;

    public function __construct(array $config) {
        parent::__construct($config);
        $this->min = $config['min'] ?? null;
        $this->max = $config['max'] ?? null;
        $this->step = $config['step'] ?? 1;
    }

    public function getType(): string {
        return 'number';
    }

    public function render(): string {
        $required = $this->required ? 'required' : '';
        $min = $this->min !== null ? "min=\"{$this->min}\"" : '';
        $max = $this->max !== null ? "max=\"{$this->max}\"" : '';
        $step = $this->step !== null ? "step=\"{$this->step}\"" : '';
        $value = htmlspecialchars($this->value ?? '');
        return <<<HTML
        <input type="number" name="{$this->name}" id="field_{$this->name}" class="input" value="{$value}" {$required} {$min} {$max} {$step}>
        HTML;
    }

    public function validate($value): array {
        $errors = [];
        if ($this->required && ($value === null || $value === '')) {
            $errors[] = "{$this->label} is required";
        }
        if ($this->min !== null && $value < $this->min) {
            $errors[] = "{$this->label} must be at least {$this->min}";
        }
        if ($this->max !== null && $value > $this->max) {
            $errors[] = "{$this->label} must be at most {$this->max}";
        }
        return $errors;
    }

    public function getJavaScript(): string {
        return '';
    }

    public function getCSS(): string {
        return '';
    }

    public function getAdminConfig(): array {
        return [
            'type' => 'number',
            'name' => $this->name,
            'label' => $this->label,
            'required' => $this->required,
            'help' => $this->help,
            'group' => $this->group,
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step
        ];
    }

    public function getSchema(): array {
        return [
            'type' => 'number',
            'required' => $this->required,
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step
        ];
    }

    public function getDefaultValue() {
        return 0;
    }

    public function processValue($value) {
        return (float)$value;
    }

    public function processLoadedValue($value) {
        return $value;
    }
} 