<?php
/**
 * Legacy media API — deprecated (M2-T2).
 * Use /api/admin_media.php instead. This endpoint returns 403 for all requests.
 */
header('Content-Type: application/json');
http_response_code(403);
echo json_encode([
    'status' => 'error',
    'message' => 'This endpoint is deprecated. Use /api/admin_media.php (authentication required).'
]);
exit;
