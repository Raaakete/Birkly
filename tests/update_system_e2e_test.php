<?php
/**
 * P28-M15 — Update system E2E: fixture zip download validation, apply, rollback.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../core/version.php';

test_reset_failures();
echo "Update system E2E tests\n\n";

$repoRoot = dirname(__DIR__);
$runId = 'update-e2e-' . getmypid() . '-' . uniqid();
$sandbox = sys_get_temp_dir() . '/' . $runId;
$markerRel = 'core/update-e2e-marker.txt';
$fromVersion = '1.0.0';
$toVersion = '1.0.1';

function update_e2e_mkdir(string $path): void
{
    if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
        throw new RuntimeException('Failed to create directory: ' . $path);
    }
}

function update_e2e_rmtree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($dir);
}

function update_e2e_seed_sandbox(string $root, string $version, string $markerValue): void
{
    $dirs = ['admin', 'api', 'core', 'fieldtypes', 'plugins', 'templates', 'archive/legacy-templating'];
    foreach ($dirs as $dir) {
        update_e2e_mkdir($root . '/' . $dir);
    }
    file_put_contents($root . '/index.php', "<?php\n");
    file_put_contents($root . '/admin/index.php', "<?php\n");
    file_put_contents($root . '/core/version.php', "<?php\n");
    file_put_contents($root . '/archive/legacy-templating/render_page.php', "<?php\n");
    file_put_contents($root . '/VERSION', $version);
    file_put_contents($root . '/core/update-e2e-marker.txt', $markerValue);
}

function update_e2e_build_fixture_zip(string $zipPath, string $version, string $markerValue): void
{
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create fixture zip');
    }
    $prefix = 'Birkly-' . $version . '/';
    $zip->addFromString($prefix . 'index.php', "<?php\n");
    $zip->addFromString($prefix . 'admin/index.php', "<?php\n");
    $zip->addFromString($prefix . 'core/version.php', "<?php\n// updated\n");
    $zip->addFromString($prefix . 'core/update-e2e-marker.txt', $markerValue);
    $zip->addFromString($prefix . 'padding.bin', random_bytes(12 * 1024));
    $zip->close();
}

try {
    update_e2e_seed_sandbox($sandbox, $fromVersion, 'before-update');
    $fixtureZip = sys_get_temp_dir() . '/birkly-update-' . $toVersion . '-' . $runId . '.zip';
    update_e2e_build_fixture_zip($fixtureZip, $toVersion, 'after-update');

    test_assert(
        'fixture zip validates',
        BirklyVersion::validateUpdatePackageForTest($fixtureZip, $toVersion)
    );

    $downloadTemp = sys_get_temp_dir() . '/birkly-update-' . $toVersion . '.zip';
  if (is_file($downloadTemp)) {
        @unlink($downloadTemp);
    }
    test_assert('download temp path writable', copy($fixtureZip, $downloadTemp));
    test_assert(
        'downloaded package passes integrity check',
        BirklyVersion::validateUpdatePackageForTest($downloadTemp, $toVersion)
    );

    BirklyVersion::setTestCmsRootOverride($sandbox);
    $install = BirklyVersion::installUpdate($downloadTemp, $toVersion);
    test_assert('install succeeds', ($install['success'] ?? false) === true, json_encode($install));
    test_assert('VERSION updated', trim((string) file_get_contents($sandbox . '/VERSION')) === $toVersion);
    test_assert(
        'marker file updated',
        trim((string) file_get_contents($sandbox . '/' . $markerRel)) === 'after-update'
    );
    $backupDir = (string) ($install['backup_location'] ?? '');
    test_assert('backup directory created', $backupDir !== '' && is_dir($backupDir));

    $rollback = BirklyVersion::restoreBackupForTest($backupDir, $sandbox);
    test_assert('rollback succeeds', $rollback === true);
    test_assert('VERSION restored', trim((string) file_get_contents($sandbox . '/VERSION')) === $fromVersion);
    test_assert(
        'marker file restored',
        trim((string) file_get_contents($sandbox . '/' . $markerRel)) === 'before-update'
    );
} finally {
    BirklyVersion::setTestCmsRootOverride(null);
    @unlink($downloadTemp ?? '');
    @unlink($fixtureZip ?? '');
    update_e2e_rmtree($sandbox);
}

exit(test_finish('update_system_e2e_test'));
