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

/**
 * Try loading a plugin's plugin.php (activation smoke test).
 *
 * @return array{ok:bool,error:?string}
 */
function birkly_plugin_smoke_load(string $pluginId): array
{
    global $birkly_plugins;

    if (!isset($birkly_plugins[$pluginId]) && function_exists('load_plugins')) {
        load_plugins();
    }

    $path = (string) ($birkly_plugins[$pluginId]['path'] ?? '');
    $pluginFile = $path !== '' ? $path . '/plugin.php' : '';
    if ($pluginFile === '' || !is_file($pluginFile)) {
        return ['ok' => false, 'error' => 'plugin.php not found'];
    }

    $loadedKey = '__birkly_plugin_smoke_loaded_' . $pluginId;
    if (!empty($GLOBALS[$loadedKey])) {
        return ['ok' => true, 'error' => null];
    }

    $previousActive = $birkly_plugins[$pluginId]['active'] ?? false;
    $birkly_plugins[$pluginId]['active'] = true;

    try {
        include_once $pluginFile;
        $GLOBALS[$loadedKey] = true;
        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    } finally {
        $birkly_plugins[$pluginId]['active'] = $previousActive;
    }
}
