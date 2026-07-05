<?php
/**
 * Runtime Help manifest — merges core topics with active plugin docs (P6.5-M5).
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../core/plugins.php';

echo json_encode(birkly_build_help_manifest(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
