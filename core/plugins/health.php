<?php
/**
 * P18-M3 — Plugin health checks (activation smoke, deps, capability warnings).
 */

declare(strict_types=1);

require_once __DIR__ . '/dependencies.php';
require_once __DIR__ . '/capabilities.php';

/**
 * @return array{status:string,warnings:array,errors:array,capabilities:array,active:bool}
 */
function birkly_plugin_health_check(string $pluginId): array
{
    global $birkly_plugins;

    if (!isset($birkly_plugins[$pluginId])) {
        return [
            'status' => 'unknown',
            'warnings' => [],
            'errors' => ["Plugin '{$pluginId}' not found"],
            'capabilities' => [],
            'active' => false,
        ];
    }

    $plugin = $birkly_plugins[$pluginId];
    $path = (string) ($plugin['path'] ?? '');
    $status = 'healthy';
    $warnings = [];
    $errors = [];

    if ($path === '' || !is_dir($path)) {
        $status = 'error';
        $errors[] = 'Plugin directory missing';
    } else {
        $validationErrors = validate_plugin($path);
        if ($validationErrors !== []) {
            $status = 'error';
            $errors = array_merge($errors, $validationErrors);
        }
    }

    $depErrors = check_plugin_dependencies($pluginId);
    if ($depErrors !== []) {
        $status = 'error';
        $errors = array_merge($errors, $depErrors);
    }

    $declared = get_plugin_declared_capabilities($pluginId);
    $granted = get_plugin_granted_capabilities($pluginId);
    $undeclared = [];
    foreach ($granted as $scope => $caps) {
        if (!is_array($caps)) {
            continue;
        }
        foreach ($caps as $cap) {
            if (!in_array($cap, $declared, true)) {
                $undeclared[] = (string) $cap;
            }
        }
    }
    if ($undeclared !== []) {
        if ($status === 'healthy') {
            $status = 'warning';
        }
        $warnings[] = 'Granted capabilities not declared in plugin.json: ' . implode(', ', array_unique($undeclared));
    }

    if (!empty($plugin['active']) && function_exists('validate_plugin_activation')) {
        $activation = validate_plugin_activation($pluginId);
        foreach ($activation['errors'] ?? [] as $err) {
            if ($status === 'healthy') {
                $status = 'warning';
            }
            $warnings[] = (string) $err;
        }
    }

    return [
        'status' => $status,
        'warnings' => $warnings,
        'errors' => $errors,
        'capabilities' => $declared,
        'active' => !empty($plugin['active']),
    ];
}

/**
 * @return array<string, array{status:string,warnings:array,errors:array,capabilities:array,active:bool}>
 */
function birkly_plugin_health_all(): array
{
    $out = [];
    foreach (array_keys(get_all_plugins()) as $pluginId) {
        $out[$pluginId] = birkly_plugin_health_check((string) $pluginId);
    }
    return $out;
}
