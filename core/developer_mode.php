<?php
/**
 * Developer mode (P61) — gated DX tools including template/site-pack export.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/birkly_data_paths.php';
require_once __DIR__ . '/project_site.php';

function birkly_developer_mode_settings_path(): string
{
    return birkly_project_root() . '/settings/developer.json';
}

/**
 * @return array{enabled: bool}
 */
function birkly_developer_mode_defaults(): array
{
    return ['enabled' => false];
}

/**
 * @return array{enabled: bool}
 */
function birkly_developer_mode_load(): array
{
    $defaults = birkly_developer_mode_defaults();
    $path = birkly_developer_mode_settings_path();
    if (!is_readable($path)) {
        return $defaults;
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return $defaults;
    }
    return [
        'enabled' => !empty($decoded['enabled']),
    ];
}

function birkly_developer_mode_enabled(): bool
{
    return birkly_developer_mode_load()['enabled'] === true;
}

function birkly_developer_mode_save(bool $enabled): bool
{
    $dir = dirname(birkly_developer_mode_settings_path());
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }
    $payload = [
        'enabled' => $enabled,
        'updated_at' => date('c'),
    ];
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    return file_put_contents(birkly_developer_mode_settings_path(), $json, LOCK_EX) !== false;
}

/**
 * Paths/names that must never appear in a template export zip.
 *
 * @return list<string>
 */
function birkly_developer_export_blocked_basenames(): array
{
    return [
        'users',
        'config.php',
        '.env',
        '.env.local',
        'secrets',
        'secrets.json',
        'oauth_tokens.json',
        'mcp_keys.json',
        'ai_keys.json',
        'license.json',
        'licenses.json',
        'security.log',
        'php_errors.log',
    ];
}

/**
 * @return list<string>
 */
function birkly_developer_export_blocked_path_fragments(): array
{
    return [
        '/users/',
        '/settings/mcp',
        '/settings/ai_',
        '/settings/oauth',
        '/settings/cloud',
        '/logs/',
        '/.git/',
        '/node_modules/',
        '/vendor/',
    ];
}

function birkly_developer_export_path_blocked(string $relativePath): bool
{
    $norm = '/' . str_replace('\\', '/', ltrim($relativePath, '/'));
    $base = basename($norm);
    foreach (birkly_developer_export_blocked_basenames() as $blocked) {
        if (strcasecmp($base, $blocked) === 0) {
            return true;
        }
    }
    $lower = strtolower($norm);
    foreach (birkly_developer_export_blocked_path_fragments() as $frag) {
        if (str_contains($lower, strtolower($frag))) {
            return true;
        }
    }
    // Credential-ish filenames
    if (preg_match('/(secret|password|private[_-]?key|api[_-]?key|token)\\.(json|php|env|pem|key)$/i', $base)) {
        return true;
    }
    return false;
}

/**
 * Build a site/template pack zip.
 *
 * Always includes project/. Optionally includes content/ collections (entries + schemas).
 * Never includes users or secrets.
 *
 * @return array{ok: bool, path?: string, error?: string, files?: int}
 */
function birkly_developer_export_template_pack(bool $includeContent = false, ?string $destPath = null): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'ZipArchive extension is required'];
    }

    $projectRoot = birkly_public_site_path();
    if (!is_dir($projectRoot)) {
        return ['ok' => false, 'error' => 'project/ directory not found'];
    }

    $tmpDir = birkly_project_root() . '/temp';
    if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
        return ['ok' => false, 'error' => 'Cannot create temp directory'];
    }

    $destPath = $destPath ?? ($tmpDir . '/birkly-template-export-' . date('Ymd-His') . '.zip');
    $zip = new ZipArchive();
    if ($zip->open($destPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return ['ok' => false, 'error' => 'Cannot create zip'];
    }

    $files = 0;

    $addTree = static function (string $absRoot, string $zipPrefix) use ($zip, &$files): void {
        if (!is_dir($absRoot)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absRoot, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if (!$fileInfo->isFile()) {
                continue;
            }
            $abs = $fileInfo->getPathname();
            $rel = substr($abs, strlen($absRoot));
            $rel = ltrim(str_replace('\\', '/', (string) $rel), '/');
            $zipPath = rtrim($zipPrefix, '/') . '/' . $rel;
            if (birkly_developer_export_path_blocked($zipPath)) {
                continue;
            }
            // Skip huge binaries / caches
            if (preg_match('/\\.(log|map|cache)$/i', $rel)) {
                continue;
            }
            if ($zip->addFile($abs, $zipPath)) {
                $files++;
            }
        }
    };

    $addTree($projectRoot, 'project');

    if ($includeContent) {
        $contentRoot = birkly_project_root() . '/content';
        $addTree($contentRoot, 'content');
    }

    // Minimal pack manifest
    $manifest = [
        'type' => 'template_pack',
        'generated_at' => date('c'),
        'includes_project' => true,
        'includes_content' => $includeContent,
        'generator' => 'birkly-developer-mode',
    ];
    $zip->addFromString('pack.json', (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $files++;

    $zip->close();

    if ($files < 2) {
        @unlink($destPath);
        return ['ok' => false, 'error' => 'Export produced no files'];
    }

    return ['ok' => true, 'path' => $destPath, 'files' => $files];
}

/**
 * @return array<string, mixed>
 */
function birkly_developer_system_info(): array
{
    $plugins = [];
    if (function_exists('get_all_plugins')) {
        foreach (get_all_plugins() as $id => $meta) {
            $plugins[] = [
                'id' => $id,
                'active' => function_exists('birkly_is_plugin_active') ? birkly_is_plugin_active($id) : null,
            ];
        }
    }

    $opsRelay = [];
    if (is_file(__DIR__ . '/platform_ops.php')) {
        require_once __DIR__ . '/platform_ops.php';
        $opsRelay = birkly_ops_relay_status(false);
    }

    return [
        'php_version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
        'project_root' => birkly_project_root(),
        'data_root' => birkly_data_root(),
        'git_available' => trim((string) shell_exec('command -v git 2>/dev/null')) !== '',
        'zip_available' => class_exists('ZipArchive'),
        'developer_mode' => birkly_developer_mode_enabled(),
        'plugins' => $plugins,
        'os' => PHP_OS_FAMILY,
        'ops_relay' => $opsRelay,
    ];
}
