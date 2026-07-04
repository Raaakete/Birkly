<?php
/**
 * P5.7-M2 — Storage factory and config path tests (STO-03–07).
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
require_once dirname(__DIR__) . '/core/storage/FlatFileStorage.php';

test_reset_failures();
echo "Settings storage tests\n\n";

$root = dirname(__DIR__);
$configFile = StorageFactory::getConfigFilePath();
test_assert('STO-07 config path is settings/storage.json', $configFile === $root . '/settings/storage.json');

$backup = sys_get_temp_dir() . '/birkly-storage-test-' . getmypid() . '.json';
$hadFile = is_file($configFile);
if ($hadFile) {
    copy($configFile, $backup);
}

StorageFactory::reset();
$default = StorageFactory::loadStorageConfig(true);
test_assert('STO-02 flatfile default driver', ($default['driver'] ?? '') === 'flatfile');

$input = [
    'storage_backend' => 'mysql',
    'db_host' => 'db.example.test',
    'db_port' => '3307',
    'db_name' => 'birkly_test',
    'db_user' => 'user',
    'db_password' => 'secret',
    'db_prefix' => 'test_',
];
$built = StorageFactory::buildStorageConfigFromInput($input);
test_assert('STO-06 backend maps to driver', ($built['driver'] ?? '') === 'mysql');
test_assert('STO-06 host normalized', ($built['host'] ?? '') === 'db.example.test');
test_assert('STO-06 database normalized', ($built['database'] ?? '') === 'birkly_test');

StorageFactory::saveStorageConfig($built);
StorageFactory::reset();
$loaded = StorageFactory::loadStorageConfig(true);
test_assert('STO-03 storage config saved', ($loaded['driver'] ?? '') === 'mysql');
test_assert('STO-03 storage config host persisted', ($loaded['host'] ?? '') === 'db.example.test');

$unsupported = StorageFactory::testDatabaseConnection(['driver' => 'sqlite']);
test_assert('STO-04 unsupported driver rejected', ($unsupported['ok'] ?? true) === false);

$flatfile = StorageFactory::createDriver('flatfile', $loaded, $root . '/content');
test_assert('flatfile driver instantiates', $flatfile instanceof FlatFileStorage);
test_assert('flatfile metadata driver name', $flatfile->getDriverName() === 'flatfile');

$roundtripPath = 'collections/test-collection/entries/sample-' . getmypid() . '.json';
$payload = ['id' => 'sample', 'title' => 'Storage test'];
test_assert('flatfile write succeeds', $flatfile->write($roundtripPath, $payload) === true);
$readBack = $flatfile->read($roundtripPath);
test_assert('flatfile read roundtrip', is_array($readBack) && ($readBack['title'] ?? '') === 'Storage test');
$flatfile->delete($roundtripPath);

test_assert('normalize postgres alias', StorageFactory::normalizeDriver('postgres') === 'postgresql');

if ($hadFile) {
    copy($backup, $configFile);
} else {
    @unlink($configFile);
}
@unlink($backup);
StorageFactory::reset();

exit(test_finish('settings_storage_test'));
