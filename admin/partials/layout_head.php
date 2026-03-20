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
    <script>
      window.Birkly = window.Birkly || {};
      window.Birkly.currentUser = window.Birkly.currentUser || {};
      window.Birkly.currentUser.role = <?= json_encode($currentRole) ?>;
      window.Birkly.currentUser.permissions = <?= json_encode(array_values(array_unique(array_filter($currentPermissions)))) ?>;
    </script>
    <title>Birkly Admin</title>
    <!-- Main Stylesheet - Imports all CSS modules -->
    <link rel="stylesheet" href="/admin/css/style.css">
    <?php
    // Load settings to get logo for favicon
    require_once __DIR__ . '/../../api/storage.php';
    $storage = new Storage(__DIR__ . '/../../settings');
    $brandingSettings = $storage->read('branding.json') ?? [];
    $logoPath = $brandingSettings['logoPath'] ?? '';
    $faviconUrl = !empty($logoPath) ? $logoPath : '/admin/assets/birkly-icon.svg';
?><link rel="shortcut icon" href="<?= htmlspecialchars($faviconUrl) ?>">
    
    <!-- Plugin UI System -->
    <script src="/admin/js/plugin-ui.js" defer></script>
    
    <!-- Page uses no per-page JS here; global JS loaded in footer -->
</head>
<body>
    <div class="admin-layout">

