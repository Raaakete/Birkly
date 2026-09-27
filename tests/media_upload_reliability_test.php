<?php
/**
 * Media upload size errors and Windows octet-stream MIME fallback.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/api/admin_media.php';

$failures = 0;
$assert = static function (bool $cond, string $msg) use (&$failures): void {
    if ($cond) {
        echo "ok - {$msg}\n";
        return;
    }
    echo "FAIL - {$msg}\n";
    $failures++;
};

$assert(admin_media_ini_to_bytes('8M') === 8 * 1024 * 1024, '8M parses to bytes');
$assert(admin_media_ini_to_bytes('64M') === 64 * 1024 * 1024, '64M parses to bytes');
$assert(admin_media_content_length_exceeds_post_max(9 * 1024 * 1024, '8M') === true, '9MB exceeds an 8M post limit');
$assert(admin_media_content_length_exceeds_post_max(1024, '8M') === false, '1KB stays under an 8M post limit');

$_SERVER['CONTENT_LENGTH'] = (string) (9 * 1024 * 1024);
$_POST = [];
$_FILES = [];
$over = uploadMedia();
$assert(($over['status'] ?? '') === 'error', 'oversized body returns an error');
$assert(stripos((string) ($over['message'] ?? ''), 'CSRF') === false, 'oversized body is not reported as an invalid CSRF token');
$assert(stripos((string) ($over['message'] ?? ''), 'too large') !== false, 'oversized body explains the size limit');
unset($_SERVER['CONTENT_LENGTH']);

$tmp = tempnam(sys_get_temp_dir(), 'birkly-mp4-');
if ($tmp === false) {
    fwrite(STDERR, "FAIL - could not create temp file\n");
    exit(1);
}
file_put_contents($tmp, random_bytes(64));
$mp4 = detectUploadMimeType($tmp, 'clip.mp4');
$assert($mp4 === 'video/mp4', 'octet-stream .mp4 resolves to video/mp4');
$assert(in_array($mp4, admin_media_allowed_mime_types(), true), 'resolved mp4 mime is allowed');
$assert(getMediaCategory($mp4) === 'video', 'resolved mp4 is categorized as video');

$exe = detectUploadMimeType($tmp, 'payload.exe');
$assert(!in_array($exe, admin_media_allowed_mime_types(), true), '.exe is not accepted via the extension map');

$mov = detectUploadMimeType($tmp, 'clip.mov');
$assert($mov === 'video/quicktime', '.mov resolves to video/quicktime when finfo is generic');
$assert(in_array('video/quicktime', admin_media_allowed_mime_types(), true), 'quicktime is on the allow list');

unlink($tmp);

$source = (string) file_get_contents($root . '/api/admin_media.php');
$sizePos = strpos($source, 'admin_media_request_exceeds_post_limit');
$csrfPos = strpos($source, 'function uploadMedia()');
$csrfCall = strpos($source, 'admin_media_csrf_valid()', $csrfPos === false ? 0 : $csrfPos);
$assert($sizePos !== false && $csrfCall !== false && $sizePos < $csrfCall, 'uploadMedia checks body size before CSRF');

$filePhp = (string) file_get_contents($root . '/fieldtypes/File/File.php');
$assert(strpos($filePhp, "'mp4'") !== false && strpos($filePhp, "'mov'") !== false, 'File field resolves video extensions');

if ($failures > 0) {
    fwrite(STDERR, "media_upload_reliability_test: {$failures} failure(s)\n");
    exit(1);
}

echo "media_upload_reliability_test passed\n";
