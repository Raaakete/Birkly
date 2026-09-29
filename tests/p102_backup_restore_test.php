<?php
/**
 * P102-M8 — backup restore stays inside content, users, media, and settings.
 * A zip from the backup writer still round-trips.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/backup.php';

$errors = [];

function p102_assert(bool $ok, string $message, array &$errors): void
{
    if (!$ok) {
        $errors[] = $message;
    }
}

function p102_rmtree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

$tmp = sys_get_temp_dir() . '/birkly-p102-backup-' . bin2hex(random_bytes(4));
$source = $tmp . '/source';
$dest = $tmp . '/dest';
$outside = $tmp . '/outside.txt';

try {
    foreach (['content/pages', 'users', 'media/photos', 'settings'] as $dir) {
        mkdir($source . '/' . $dir, 0755, true);
        mkdir($dest . '/' . $dir, 0755, true);
    }
    file_put_contents($source . '/content/pages/home.json', '{"title":"Home"}');
    file_put_contents($source . '/users/admin.json', '{"email":"a@example.com"}');
    file_put_contents($source . '/media/photos/note.txt', 'photo');
    file_put_contents($source . '/settings/site.json', '{"name":"Site"}');
    file_put_contents($dest . '/content/pages/home.json', '{"title":"Old"}');
    file_put_contents($outside, 'untouched');

    $zipPath = $tmp . '/backup.zip';
    $created = birkly_backup_create_zip($source, $zipPath);
    p102_assert(!empty($created['ok']) && is_file($zipPath), 'backup writer should create a zip', $errors);

    $zip = new ZipArchive();
    p102_assert($zip->open($zipPath) === true, 'backup zip should open', $errors);
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (is_string($name)) {
            $names[] = $name;
        }
    }
    $zip->close();
    p102_assert(in_array('content/pages/home.json', $names, true), 'backup zip should store content under content/', $errors);
    p102_assert(in_array('settings/site.json', $names, true), 'backup zip should store settings under settings/', $errors);
    foreach ($names as $name) {
        p102_assert(!str_contains($name, '..'), 'backup zip entries should not contain ..', $errors);
    }

    $restored = birkly_backup_restore_zip($zipPath, $dest);
    p102_assert(!empty($restored['ok']) && ($restored['restored'] ?? 0) === 4, 'a backup zip should restore its files: ' . json_encode($restored), $errors);
    p102_assert((string) file_get_contents($dest . '/content/pages/home.json') === '{"title":"Home"}', 'restored content should match the backup', $errors);
    p102_assert((string) file_get_contents($dest . '/users/admin.json') === '{"email":"a@example.com"}', 'restored users should match the backup', $errors);
    p102_assert((string) file_get_contents($dest . '/media/photos/note.txt') === 'photo', 'restored media should match the backup', $errors);
    p102_assert((string) file_get_contents($dest . '/settings/site.json') === '{"name":"Site"}', 'restored settings should match the backup', $errors);

    $slip = $tmp . '/slip.zip';
    $zip = new ZipArchive();
    $zip->open($slip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('content/ok.txt', 'ok');
    $zip->addFromString('../outside.txt', 'pwned');
    $zip->close();
    $before = (string) file_get_contents($outside);
    $rejected = birkly_backup_restore_zip($slip, $dest);
    p102_assert(empty($rejected['ok']), 'a zip with .. should be rejected', $errors);
    p102_assert((string) file_get_contents($outside) === $before, 'rejected zip must not write outside the CMS root', $errors);
    p102_assert(!is_file($dest . '/content/ok.txt'), 'rejected zip must not write the safe entries either', $errors);

    $absolute = $tmp . '/absolute.zip';
    $zip = new ZipArchive();
    $zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('/tmp/p102-absolute.txt', 'nope');
    $zip->close();
    $absResult = birkly_backup_restore_zip($absolute, $dest);
    p102_assert(empty($absResult['ok']), 'an absolute path should be rejected', $errors);
    p102_assert(!is_file('/tmp/p102-absolute.txt'), 'absolute path must not be written', $errors);

    $linkZip = $tmp . '/link.zip';
    $zip = new ZipArchive();
    $zip->open($linkZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('content/link', 'target');
    $zip->setExternalAttributesName('content/link', ZipArchive::OPSYS_UNIX, 0120000 << 16);
    $zip->close();
    $linkResult = birkly_backup_restore_zip($linkZip, $dest);
    p102_assert(empty($linkResult['ok']), 'a symlink entry should be rejected', $errors);
    p102_assert(!is_link($dest . '/content/link') && !is_file($dest . '/content/link'), 'symlink entry must not be extracted', $errors);

    $endpoint = (string) file_get_contents($root . '/api/backup.php');
    p102_assert(str_contains($endpoint, "requireAuth('admin')"), 'backup endpoint should still require an admin', $errors);
    p102_assert(str_contains($endpoint, 'validate_csrf_token'), 'restore should still check CSRF', $errors);
    p102_assert(!str_contains($endpoint, 'extractTo'), 'restore should not extract the archive in one call', $errors);
} catch (Throwable $e) {
    $errors[] = $e::class . ': ' . $e->getMessage();
} finally {
    p102_rmtree($tmp);
    @unlink('/tmp/p102-absolute.txt');
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "p102_backup_restore_test: OK\n";
