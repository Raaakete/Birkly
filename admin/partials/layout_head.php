<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getCurrentLanguage()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo get_csrf_token(); ?>">
    <title>HaruCMS Admin</title>
    <link rel="stylesheet" href="/admin/css/style.css">
    <?php if (isset($page) && $page === 'help'): ?>
    <link rel="stylesheet" href="/admin/css/help.css">
    <?php endif; ?>
    <?php
    // Load settings to get logo for favicon
    require_once __DIR__ . '/../../api/storage.php';
    $storage = new Storage(__DIR__ . '/../../settings');
    $brandingSettings = $storage->read('branding.json') ?? [];
    $logoPath = $brandingSettings['logoPath'] ?? '';
    $faviconUrl = !empty($logoPath) ? $logoPath : '/admin/assets/haru-icon.svg';
    ?>
    <link rel="shortcut icon" href="<?= htmlspecialchars($faviconUrl) ?>">
    
    <!-- Page uses no per-page JS here; global JS loaded in footer -->
</head>
<body>
    <div class="admin-layout">

