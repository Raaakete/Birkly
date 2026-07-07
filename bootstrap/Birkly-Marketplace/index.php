<?php
/**
 * Birkly Marketplace — public landing page.
 */

require_once __DIR__ . '/core/bootstrap.php';

marketplace_html('landing.php', [
    'app_name' => marketplace_config('app_name', 'Birkly Marketplace'),
    'version' => marketplace_config('version', '0.1.0-m0'),
    'health_url' => marketplace_config('base_url', '') . '/api/v1/health',
    'catalog_url' => marketplace_config('base_url', '') . '/api/v1/catalog',
]);
