<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) ($app_name ?? 'Birkly Marketplace'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/public/style.css">
</head>
<body>
    <main class="landing">
        <h1><?= htmlspecialchars((string) ($app_name ?? 'Birkly Marketplace'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="tagline">Plugins, themes, and template packs for Birkly CMS.</p>
        <p class="version">Version <?= htmlspecialchars((string) ($version ?? '0.1.0-m0'), ENT_QUOTES, 'UTF-8') ?> (P9-M0 scaffold)</p>
        <ul class="links">
            <li><a href="/api/v1/health">Health check (JSON)</a></li>
            <li><a href="/api/v1/catalog">Catalog API stub (JSON)</a></li>
        </ul>
    </main>
</body>
</html>
