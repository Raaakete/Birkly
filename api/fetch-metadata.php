<?php
/**
 * Retired. The content editor loads link previews through
 * fieldtypes/ContentEditor/link-metadata.php, which checks the URL and uses
 * the shared safe fetch. This script no longer requests the supplied URL.
 */

header('Content-Type: application/json');
http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => 'This endpoint is retired. Use the content editor link metadata endpoint.',
]);
exit;
