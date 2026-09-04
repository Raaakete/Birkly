<?php
/**
 * P76 virtual fieldtype contract tests.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/fieldtype_virtual.php';
require_once __DIR__ . '/../fieldtypes/BaseField.php';

use Birkly\FieldTypes\BaseField;

class VirtualFieldtypeTestField extends BaseField
{
    public function getType(): string
    {
        return 'virtual_test';
    }

    public function render(): string
    {
        return '<div>virtual</div>';
    }

    public function validate($value): array
    {
        return [];
    }

    public function getJavaScript(): string
    {
        return '';
    }

    public function getCSS(): string
    {
        return '';
    }

    public function getAdminConfig(): array
    {
        return [];
    }

    public function getSchema(): array
    {
        return ['type' => 'null'];
    }

    public function getDefaultValue()
    {
        return null;
    }

    public function processValue($value)
    {
        return $value;
    }

    public function processLoadedValue($value)
    {
        return $value;
    }

    public function persistsInEntry(): bool
    {
        return false;
    }

    public function resolveValueForEntry(array $entry, array $fieldConfig, array $context = [])
    {
        return ['resolved' => true, 'owner' => $entry['owner'] ?? ''];
    }
}

if (!function_exists('getFieldtypeClass')) {
    function getFieldtypeClass(string $type): string
    {
        return $type === 'virtual_test' ? VirtualFieldtypeTestField::class : '';
    }
}

function vf_assert(string $label, bool $ok): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
    echo "PASS: {$label}\n";
}

$ft = new VirtualFieldtypeTestField(['name' => 'test']);
vf_assert('BaseField persistsInEntry default true', (new class(['name' => 'x']) extends BaseField {
    public function getType(): string { return 'x'; }
    public function render(): string { return ''; }
    public function validate($value): array { return []; }
    public function getJavaScript(): string { return ''; }
    public function getCSS(): string { return ''; }
    public function getAdminConfig(): array { return []; }
    public function getSchema(): array { return []; }
    public function getDefaultValue() { return null; }
    public function processValue($value) { return $value; }
    public function processLoadedValue($value) { return $value; }
})->persistsInEntry() === true);

vf_assert('virtual test field persistsInEntry false', $ft->persistsInEntry() === false);

$field = ['name' => 'relationships', 'type' => 'virtual_test'];
vf_assert('birkly_fieldtype_persists_in_entry false', birkly_fieldtype_persists_in_entry($field) === false);

$entry = ['owner' => 'usr_1', 'title' => 'Profile'];
$resolved = birkly_fieldtype_resolve_value_for_entry($entry, $field);
vf_assert('resolveValueForEntry returns plugin data', is_array($resolved) && ($resolved['owner'] ?? '') === 'usr_1');

$dirty = ['owner' => 'usr_1', 'relationships' => ['hack' => true], 'title' => 'Profile'];
$clean = birkly_strip_non_persisting_entry_fields($dirty, [$field]);
vf_assert('strip removes virtual field', !array_key_exists('relationships', $clean) && ($clean['owner'] ?? '') === 'usr_1');

echo "virtual_fieldtype_test: all passed\n";
