<?php
/**
 * Serve user avatar images stored under data/avatars/.
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/avatar_upload.php';

$filename = basename((string) ($_GET['f'] ?? ''));
if ($filename === '' || !birkly_avatar_filename_is_safe($filename)) {
    http_response_code(404);
    exit;
}

$filepath = birkly_avatar_file_path($filename);
if ($filepath === null) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($filepath);
exit;
