<?php
/**
 * Studio plugin initialization.
 */

function studio_plugin_dir(): string
{
    return __DIR__;
}

function studio_plugin_active(): bool
{
    if (!function_exists('birkly_is_plugin_active')) {
        require_once dirname(__DIR__, 2) . '/core/plugins.php';
    }
    return birkly_is_plugin_active('studio');
}

function studio_content_root(): string
{
    return dirname(__DIR__, 2) . '/content/studio-projects';
}

function studio_require_admin(): void
{
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
}

function studio_user_can_manage(): bool
{
    if (!isset($_SESSION['user'])) {
        return false;
    }
    $role = $_SESSION['user']['role'] ?? '';
    if (in_array($role, ['admin', 'superadmin'], true)) {
        return true;
    }
    $caps = $_SESSION['user']['capabilities'] ?? $_SESSION['user']['plugin_capabilities']['studio'] ?? [];
    return is_array($caps) && in_array('manage_studio', $caps, true);
}

function studio_require_manage(): void
{
    studio_require_admin();
    if (!studio_user_can_manage()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
}

function studio_slugify(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'project';
}

function studio_new_id(): string
{
    return 'stu_' . bin2hex(random_bytes(6));
}

function studio_style_kits(): array
{
    return [
        'minimal' => [
            'id' => 'minimal',
            'name' => 'Minimal',
            'description' => 'Clean base typography and spacing',
            'css' => 'kits/minimal/kit.css',
        ],
        'bootstrap' => [
            'id' => 'bootstrap',
            'name' => 'Bootstrap',
            'description' => 'Vendored Bootstrap 5.3 utilities subset',
            'css' => 'kits/bootstrap/kit.css',
        ],
        'tailwind-lite' => [
            'id' => 'tailwind-lite',
            'name' => 'Tailwind Lite',
            'description' => 'Pre-built utility classes (subset)',
            'css' => 'kits/tailwind-lite/kit.css',
        ],
    ];
}

function studio_kit_css_path(string $kitId): ?string
{
    $kits = studio_style_kits();
    if (!isset($kits[$kitId])) {
        return null;
    }
    $rel = $kits[$kitId]['css'];
    $path = studio_plugin_dir() . '/' . $rel;
    return is_file($path) ? $path : null;
}
