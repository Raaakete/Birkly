<?php
/**
 * Offline Help manifest stub for admin JS fallback (P15-M3).
 */
header('Content-Type: application/json');
header('Cache-Control: public, max-age=3600');

require_once __DIR__ . '/../core/help_docs.php';

echo json_encode(birkly_help_offline_manifest(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
