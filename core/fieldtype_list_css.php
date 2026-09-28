<?php
/**
 * Fieldtype list-cell CSS: each fieldtype's getListCSS() for the fields a list shows.
 * Printed once per class per request, so several lists on one page share it.
 */

declare(strict_types=1);

/**
 * @param array<int, array<string, mixed>> $fields schema fields shown as list columns
 */
function birkly_fieldtype_list_css(array $fields): string
{
    static $printed = [];

    if (!function_exists('getFieldtypeClass')) {
        $GLOBALS['__HARU_INTERNAL_CALL'] = true;
        require_once __DIR__ . '/../api/admin_fieldtypes.php';
    }

    $css = [];
    foreach ($fields as $field) {
        $type = (string) ($field['type'] ?? '');
        $class = $type !== '' ? getFieldtypeClass($type) : null;
        if (!$class || isset($printed[$class]) || !class_exists($class)) {
            continue;
        }
        $printed[$class] = true;
        try {
            $fieldtype = new $class(['name' => 'list', 'label' => 'List']);
            $listCss = method_exists($fieldtype, 'getListCSS') ? trim($fieldtype->getListCSS()) : '';
            if ($listCss !== '') {
                $css[] = $listCss;
            }
        } catch (\Throwable $e) {
            continue;
        }
    }

    return implode("\n", $css);
}
