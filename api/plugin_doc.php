<?php
/**
 * Serve plugin markdown documentation to the Admin Help panel (P6.5-M5).
 *
 * GET ?plugin=commerce&doc=overview|ai|{help_topic_id}
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../core/plugins.php';

$plugin_id = preg_replace('/[^a-z0-9_]/', '', strtolower(trim((string) ($_GET['plugin'] ?? ''))));
$doc_key = trim((string) ($_GET['doc'] ?? 'overview'));

if ($plugin_id === '') {
    http_response_code(400);
    echo 'Missing or invalid plugin parameter';
    exit;
}

$text = read_plugin_doc_markdown($plugin_id, $doc_key);
if ($text === null) {
    http_response_code(404);
    echo 'Documentation not found or plugin inactive';
    exit;
}

echo $text;
