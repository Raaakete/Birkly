<?php
/**
 * Admin media library download helpers.
 *
 * Run: php tests/admin_media_download_test.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/media_storage.php';

$failures = 0;
$assert = static function (bool $cond, string $msg) use (&$failures): void {
    if ($cond) {
        echo "ok - {$msg}\n";
        return;
    }
    echo "FAIL - {$msg}\n";
    $failures++;
};

$assert(
    birkly_media_safe_download_filename('photo "bad".jpg') === 'photo bad.jpg',
    'strips quotes from download filename'
);
$assert(
    birkly_media_safe_download_filename('../etc/passwd') === 'passwd',
    'basename-only download filename'
);
$assert(
    birkly_media_safe_download_filename('') === 'download',
    'empty name becomes download'
);
$assert(
    str_contains(
        birkly_media_content_disposition_attachment('Café photo.jpg'),
        "filename*=UTF-8''"
    ),
    'non-ascii download uses RFC 5987 filename*'
);

$mediaDir = birkly_media_dir();
$metadataPath = birkly_media_metadata_path();
$metadataBackup = is_file($metadataPath) ? file_get_contents($metadataPath) : null;

$testId = 'birklydltest' . bin2hex(random_bytes(4));
$testFile = $mediaDir . '/' . $testId . '.txt';
$testBody = 'birkly-download-test-' . time();

try {
    if (!is_dir($mediaDir)) {
        mkdir($mediaDir, 0755, true);
    }
    file_put_contents($testFile, $testBody);

    $metadata = birkly_load_media_metadata();
    $metadata[$testId] = [
        'id' => $testId,
        'name' => 'Friendly name.txt',
        'title' => 'Friendly name',
        'url' => '/media/' . $testId . '.txt',
        'type' => 'text/plain',
        'size' => strlen($testBody),
        'created_at' => date('Y-m-d H:i:s'),
        'uploaded_by' => 'test',
        'folder' => '',
        'alt_text' => '',
        'focal_point' => ['x' => 50, 'y' => 50],
        'ext' => 'txt',
    ];
    birkly_save_media_metadata($metadata);

    $resolved = birkly_media_resolve_download_file($testId);
    $assert($resolved !== null, 'resolves registered media file');
    $assert(
        $resolved !== null && $resolved['filename'] === 'Friendly name.txt',
        'uses metadata display name for download'
    );
    $assert(
        $resolved !== null && is_file($resolved['path']) && file_get_contents($resolved['path']) === $testBody,
        'resolved path reads file bytes'
    );
    $assert(birkly_media_resolve_download_file('not-valid!!') === null, 'rejects invalid media id');
    $assert(birkly_media_resolve_download_file('missingid12345') === null, 'missing id returns null');
} finally {
    @unlink($testFile);
    if ($metadataBackup !== null) {
        file_put_contents($metadataPath, $metadataBackup);
    } else {
        @unlink($metadataPath);
    }
}

require_once $root . '/api/admin_media.php';
$assert(function_exists('admin_media_serve_download'), 'admin_media_serve_download is defined');

exit($failures > 0 ? 1 : 0);
