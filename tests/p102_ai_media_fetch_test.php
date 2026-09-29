<?php
/**
 * P102-M9 — AI media upload uses the shared safe fetch and cannot leave media/.
 * Base64 uploads still land in the requested folder. A test double proves a
 * public URL can be saved. The double cannot bypass a blocked URL.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$project = sys_get_temp_dir() . '/birkly-p102-media-' . bin2hex(random_bytes(4));
mkdir($project, 0755, true);

$liveMetadata = $root . '/media/.metadata.json';
$liveBefore = is_file($liveMetadata) ? hash_file('sha256', $liveMetadata) : null;

function p102_media_fail(string $message, array &$errors): void
{
    $errors[] = $message;
    fwrite(STDERR, "FAIL $message\n");
}

function p102_media_cleanup(string $project): void
{
    if (!is_dir($project)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($project, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($project);
}

$GLOBALS['__birkly_project_root'] = $project;
$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once $root . '/core/media_storage.php';

if (birkly_media_dir() !== $project . '/media') {
    p102_media_fail('media dir did not follow the test project root', $errors);
    p102_media_cleanup($project);
    exit(1);
}

require_once $root . '/api/ai_approvals.php';

$bytes = 'p102-image-bytes';
$base64 = base64_encode($bytes);
$saved = executeUploadMedia([
    'base64' => $base64,
    'name' => 'Hero.PNG',
    'folder' => 'campaigns/spring',
]);
$savedPath = '';
if (empty($saved['success']) || empty($saved['data']['media_id'])) {
    p102_media_fail('base64 upload failed: ' . json_encode($saved), $errors);
} else {
    $savedPath = $project . '/media/campaigns/spring/' . $saved['data']['media_id'] . '.png';
    $meta = birkly_load_media_metadata();
    if (!is_file($savedPath) || file_get_contents($savedPath) !== $bytes) {
        p102_media_fail('base64 file was not written inside the folder', $errors);
    }
    if (($saved['data']['url'] ?? '') !== '/media/campaigns/spring/' . $saved['data']['media_id'] . '.png') {
        p102_media_fail('base64 public URL was not the media path', $errors);
    }
    if (($meta[$saved['data']['media_id']]['folder'] ?? '') !== 'campaigns/spring' || ($meta[$saved['data']['media_id']]['ext'] ?? '') !== 'png') {
        p102_media_fail('base64 metadata did not record the folder and extension', $errors);
    }
}

foreach (['../outside', 'campaigns/../../secret', '/tmp/pwn', 'campaigns//hidden'] as $badFolder) {
    $before = is_file($project . '/media/.metadata.json') ? file_get_contents($project . '/media/.metadata.json') : '';
    $rejected = executeUploadMedia([
        'base64' => $base64,
        'name' => 'nope.png',
        'folder' => $badFolder,
    ]);
    $after = is_file($project . '/media/.metadata.json') ? file_get_contents($project . '/media/.metadata.json') : '';
    if (!empty($rejected['success']) || $after !== $before) {
        p102_media_fail('folder escape was stored: ' . $badFolder . ' ' . json_encode($rejected), $errors);
    }
}
if (is_dir($project . '/outside') || is_dir($project . '/secret') || is_dir($project . '/tmp')) {
    p102_media_fail('folder escape created a directory outside media/', $errors);
}

$GLOBALS['__birkly_safe_http_fetch_override'] = [
    'ok' => true,
    'body' => 'public-image',
    'http_code' => 200,
];
$blocked = executeUploadMedia([
    'url' => 'http://127.0.0.1/secret.png',
    'name' => 'secret.png',
    'folder' => 'campaigns/spring',
]);
$metadataAfterBlock = birkly_load_media_metadata();
if (!empty($blocked['success']) || ($blocked['message'] ?? '') !== 'Failed to download file from URL') {
    p102_media_fail('loopback URL was not rejected: ' . json_encode($blocked), $errors);
}
if (isset($saved['data']['media_id']) && count($metadataAfterBlock) !== 1) {
    p102_media_fail('blocked URL wrote media metadata', $errors);
}

$linkLocal = executeUploadMedia([
    'url' => 'http://169.254.169.254/latest/meta-data',
    'name' => 'meta.txt',
]);
if (!empty($linkLocal['success'])) {
    p102_media_fail('link-local URL was downloaded', $errors);
}

$remote = executeUploadMedia([
    'url' => 'https://example.com/assets/photo.png',
    'name' => 'From URL',
    'folder' => 'campaigns/spring',
]);
unset($GLOBALS['__birkly_safe_http_fetch_override']);
if (empty($remote['success']) || empty($remote['data']['media_id'])) {
    p102_media_fail('public URL upload failed: ' . json_encode($remote), $errors);
} else {
    $remotePath = $project . '/media/campaigns/spring/' . $remote['data']['media_id'] . '.png';
    if (!is_file($remotePath) || file_get_contents($remotePath) !== 'public-image') {
        p102_media_fail('public URL body was not saved inside media/', $errors);
    }
    $remoteReal = realpath($remotePath);
    $mediaReal = realpath($project . '/media');
    if ($remoteReal === false || $mediaReal === false || !str_starts_with($remoteReal, $mediaReal . DIRECTORY_SEPARATOR)) {
        p102_media_fail('public URL file escaped the media directory', $errors);
    }
}

$liveAfter = is_file($liveMetadata) ? hash_file('sha256', $liveMetadata) : null;
if ($liveAfter !== $liveBefore) {
    p102_media_fail('live media metadata changed', $errors);
}

p102_media_cleanup($project);

if ($errors !== []) {
    fwrite(STDERR, count($errors) . " failure(s)\n");
    exit(1);
}

echo "p102_ai_media_fetch_test ok\n";
