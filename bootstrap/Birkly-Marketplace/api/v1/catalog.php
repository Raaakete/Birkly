<?php
/**
 * GET /api/v1/catalog — public catalog stub (M2 will implement full listing).
 */

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    marketplace_error('method_not_allowed', 405);
}

marketplace_json(marketplace_catalog_payload());
