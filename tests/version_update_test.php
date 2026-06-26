<?php
/**
 * Update package validation tests (GitHub archive layout).
 */

require_once __DIR__ . '/../core/version.php';

echo "Update package tests\n\n";

function test_assert(string $label, bool $ok, string $detail = ''): void
{
    echo $label . ': ' . ($ok ? 'PASS' : 'FAIL');
    if ($detail !== '') {
        echo " ($detail)";
    }
    echo "\n";
    if (!$ok) {
        exit(1);
    }
}

$zipPath = sys_get_temp_dir() . '/birkly-update-test-' . uniqid() . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Could not create test zip\n");
    exit(1);
}

// GitHub tag archive layout
$zip->addFromString('Birkly-1.0.3/index.php', '<?php');
$zip->addFromString('Birkly-1.0.3/admin/index.php', '<?php');
$zip->addFromString('Birkly-1.0.3/core/version.php', '<?php');
// Pad so size > 50KB minimum
$zip->addFromString('Birkly-1.0.3/padding.bin', random_bytes(12 * 1024));
$zip->close();

test_assert(
    'github archive zip validates',
    BirklyVersion::validateUpdatePackageForTest($zipPath, '1.0.3')
);

@unlink($zipPath);

// Flat zip at root should also validate
$zipPath2 = sys_get_temp_dir() . '/birkly-update-test-flat-' . uniqid() . '.zip';
$zip2 = new ZipArchive();
$zip2->open($zipPath2, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip2->addFromString('index.php', '<?php');
$zip2->addFromString('admin/index.php', '<?php');
$zip2->addFromString('core/version.php', '<?php');
$zip2->addFromString('padding.bin', random_bytes(12 * 1024));
$zip2->close();

test_assert(
    'flat zip validates',
    BirklyVersion::validateUpdatePackageForTest($zipPath2, '1.0.0')
);

@unlink($zipPath2);

echo "\nversion_update_test: PASS\n";
