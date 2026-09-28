<?php
require_once __DIR__ . '/../../core/csp.php';
birkly_set_admin_csp_headers();
$__birklyScriptNonceAttr = birkly_csp_nonce_attr();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getCurrentLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo get_csrf_token(); ?>">
    <?php
    // Expose current user's role/permissions for client-side UX gating (not security-critical).
    // Backend endpoints remain the source of truth.
    $currentRole = $_SESSION['user']['role'] ?? 'viewer';
    $currentPermissions = [];
    try {
        require_once __DIR__ . '/../../api/auth.php';
        if (function_exists('getPermissionsForRole')) {
            $currentPermissions = getPermissionsForRole($currentRole);
        }
    } catch (Throwable $e) {
        $currentPermissions = [];
    }
    ?>
    <?php
    $adminI18nLang = getCurrentLanguage();
    $pluginIdForI18n = null;
    if (!empty($page) && function_exists('get_plugin_admin_meta_for_page')) {
        $pluginMetaForI18n = get_plugin_admin_meta_for_page((string) $page);
        if (is_array($pluginMetaForI18n)) {
            $pluginIdForI18n = (string) ($pluginMetaForI18n['plugin_id'] ?? '');
            if ($pluginIdForI18n === '') {
                $pluginIdForI18n = null;
            }
        }
    }
    if (class_exists('I18n')) {
        I18n::mergePluginCatalogs();
        $adminI18nData = I18n::getJsDictionary($pluginIdForI18n);
    } else {
        $adminI18nFallbackFile = __DIR__ . '/../../lang/en.json';
        $adminI18nData = [];
        if (is_file($adminI18nFallbackFile)) {
            $decoded = json_decode((string) file_get_contents($adminI18nFallbackFile), true);
            if (is_array($decoded['admin'] ?? null)) {
                foreach ($decoded['admin'] as $k => $v) {
                    if (is_string($v)) {
                        $adminI18nData['admin.' . $k] = $v;
                    }
                }
            }
        }
    }
    ?>
    <script<?= $__birklyScriptNonceAttr ?>>
      window.Birkly = window.Birkly || {};
      window.Birkly.basePath = <?= birkly_base_path_json() ?>;
      window.Birkly.mediaUploadAccept = <?= json_encode((function () { require_once __DIR__ . '/../../core/media_storage.php'; return birkly_media_upload_accept(); })()) ?>;
      window.Birkly.currentUser = window.Birkly.currentUser || {};
      window.Birkly.currentUser.role = <?= json_encode($currentRole) ?>;
      window.Birkly.currentUser.permissions = <?= json_encode(array_values(array_unique(array_filter($currentPermissions)))) ?>;
      window.BirklyAdminI18n = <?= json_encode($adminI18nData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
    <?php require_once __DIR__ . '/../../core/build_info.php'; ?>
    <meta name="birkly-build" content="<?= htmlspecialchars((string) (birkly_release_identifier() ?? birkly_admin_asset_version('admin/js/settings-page.js'))) ?>">
    <title>Birkly Admin</title>
    <!-- Main Stylesheet - Imports all CSS modules -->
    <link rel="stylesheet" href="<?= htmlspecialchars(birkly_admin_asset_url('/admin/css/style.css')) ?>">
    <?php if (isset($page) && $page === 'entry-editor'): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(birkly_url('/fieldtypes/ContentEditor/editor.css')) ?>">
    <?php endif; ?>
    <?php
    // Load settings to get logo for favicon
    require_once __DIR__ . '/../../api/storage.php';
    $storage = new Storage(__DIR__ . '/../../settings');
    $brandingSettings = $storage->read('branding.json') ?? [];
    $logoPath = $brandingSettings['logoPath'] ?? '';
    $faviconUrl = !empty($logoPath) ? $logoPath : birkly_url('/admin/assets/birkly-icon.svg');
?><link rel="shortcut icon" href="<?= htmlspecialchars($faviconUrl) ?>">
    
    <!-- Plugin UI System -->
    <script src="<?= htmlspecialchars(birkly_url('/admin/js/birkly-paths.js')) ?>"<?= $__birklyScriptNonceAttr ?>></script>
    <script src="<?= htmlspecialchars(birkly_url('/admin/js/plugin-ui.js')) ?>" defer<?= $__birklyScriptNonceAttr ?>></script>
    
    <!-- Page uses no per-page JS here; global JS loaded in footer -->
</head>
<?php
$__birklyDeveloperModeOn = false;
if (!function_exists('birkly_developer_mode_enabled')) {
    $__devModeFile = __DIR__ . '/../../core/developer_mode.php';
    if (is_file($__devModeFile)) {
        require_once $__devModeFile;
    }
}
if (function_exists('birkly_developer_mode_enabled')) {
    $__birklyDeveloperModeOn = birkly_developer_mode_enabled();
}
$__birklyUiDensity = 'default';
if (isset($_SESSION['user'])) {
    require_once __DIR__ . '/../../core/ui_density.php';
    $__birklyUiDensity = birkly_user_ui_density($_SESSION['user']);
}
$__birklyBodyClass = trim(($__birklyDeveloperModeOn ? 'developer-mode-on' : '') . ' density-' . $__birklyUiDensity);
$__birklyAdminPage = isset($page) ? (string) $page : '';
$__birklyAdminTab = isset($_GET['tab']) ? (string) $_GET['tab'] : '';
?>
<body<?= $__birklyBodyClass !== '' ? ' class="' . htmlspecialchars($__birklyBodyClass, ENT_QUOTES, 'UTF-8') . '"' : '' ?> data-density="<?= htmlspecialchars($__birklyUiDensity, ENT_QUOTES, 'UTF-8') ?>" data-admin-page="<?= htmlspecialchars($__birklyAdminPage, ENT_QUOTES, 'UTF-8') ?>" data-admin-tab="<?= htmlspecialchars($__birklyAdminTab, ENT_QUOTES, 'UTF-8') ?>">
    <div class="admin-layout">

