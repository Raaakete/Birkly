<?php
/**
 * GET /api/v1/health — service health check (no auth).
 */

require_once dirname(__DIR__, 2) . '/core/bootstrap.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    marketplace_error('method_not_allowed', 405);
}

marketplace_json(marketplace_health_payload());
