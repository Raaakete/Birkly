<?php
/**
 * Runtime Help manifest — merges core topics with active plugin docs (P6.5-M5).
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../core/plugins.php';

try {
    $manifest = birkly_build_help_manifest();
    if (!is_array($manifest)) {
        $manifest = ['version' => 2, 'topics' => []];
    }
    $json = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo $json !== false ? $json : '{"version":2,"topics":[]}';
} catch (Throwable $e) {
    echo '{"version":2,"topics":[],"error":"manifest_failed"}';
}
