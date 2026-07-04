<?php
/**
 * P5.7-M2 — Global save_all persistence test (G-01).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/api/storage.php';
require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';

test_reset_failures();
echo "Settings save tests\n\n";

$root = dirname(__DIR__);
$settingsDir = $root . '/settings';
$mediaFile = $settingsDir . '/media.json';
$securityFile = $settingsDir . '/security.json';
$storageFile = StorageFactory::getConfigFilePath();

$backups = [];
foreach ([$mediaFile, $securityFile, $storageFile] as $file) {
    $backup = sys_get_temp_dir() . '/birkly-save-test-' . basename($file) . '-' . getmypid() . '.json';
    $backups[$file] = $backup;
    if (is_file($file)) {
        copy($file, $backup);
    }
}

$storage = new Storage($settingsDir);

// Simulate save_all mappings for general + security + storage
$settings = [
    'mediaMaxUploadSize' => '8',
    'mediaImageQuality' => '75',
    'mediaThumbnailWidth' => '180',
    'mediaThumbnailHeight' => '120',
    'mediaCreateThumbnails' => false,
    'securityMaxLoginAttempts' => '4',
    'securityPasswordMinLength' => '10',
    'securityLockoutTime' => '20',
    'securityRequireStrongPassword' => true,
    'securityEnableCsrf' => true,
    'storage_backend' => 'flatfile',
    'db_host' => 'localhost',
    'db_port' => '3306',
    'db_name' => 'birkly',
    'db_user' => 'admin',
    'db_password' => '',
    'db_prefix' => 'birkly_',
];

$mediaSettings = [
    'max_upload_size' => (int) $settings['mediaMaxUploadSize'] * 1024 * 1024,
    'image_quality' => (int) $settings['mediaImageQuality'],
    'thumbnail_size' => [
        (int) $settings['mediaThumbnailWidth'],
        (int) $settings['mediaThumbnailHeight'],
    ],
    'create_thumbnails' => filter_var($settings['mediaCreateThumbnails'], FILTER_VALIDATE_BOOLEAN),
];
$existingMedia = $storage->read('media.json') ?? [];
$storage->write('media.json', array_merge($existingMedia, $mediaSettings));

$securitySettings = [
    'max_login_attempts' => (int) $settings['securityMaxLoginAttempts'],
    'password_min_length' => (int) $settings['securityPasswordMinLength'],
    'lockout_time' => (int) $settings['securityLockoutTime'] * 60,
    'require_strong_password' => filter_var($settings['securityRequireStrongPassword'], FILTER_VALIDATE_BOOLEAN),
    'enable_csrf' => filter_var($settings['securityEnableCsrf'], FILTER_VALIDATE_BOOLEAN),
];
$existingSecurity = $storage->read('security.json') ?? [];
$storage->write('security.json', array_merge($existingSecurity, $securitySettings));

$storageConfig = StorageFactory::buildStorageConfigFromInput([
    'storage_backend' => $settings['storage_backend'],
    'db_host' => $settings['db_host'],
    'db_port' => $settings['db_port'],
    'db_name' => $settings['db_name'],
    'db_user' => $settings['db_user'],
    'db_password' => $settings['db_password'],
    'db_prefix' => $settings['db_prefix'],
]);
StorageFactory::saveStorageConfig($storageConfig);

$savedMedia = $storage->read('media.json');
$savedSecurity = $storage->read('security.json');
$savedStorage = json_decode(file_get_contents($storageFile), true);

test_assert('G-01 media.json updated', ($savedMedia['max_upload_size'] ?? 0) === 8388608);
test_assert('G-01 security.json updated', ($savedSecurity['max_login_attempts'] ?? 0) === 4);
test_assert('G-01 security lockout converted to seconds', ($savedSecurity['lockout_time'] ?? 0) === 1200);
test_assert('G-01 storage.json updated', ($savedStorage['driver'] ?? '') === 'flatfile');

foreach ($backups as $file => $backup) {
    if (is_file($backup)) {
        copy($backup, $file);
        @unlink($backup);
    } else {
        @unlink($file);
    }
}
StorageFactory::reset();

exit(test_finish('settings_save_test'));
