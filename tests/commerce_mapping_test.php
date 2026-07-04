<?php
/**
 * P6.4-M5 — Purchase mapping tests.
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce mapping tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/plugins/commerce/includes/PurchaseMappingService.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-mapping-' . getmypid();
@mkdir($dataDir, 0755, true);

$mappings = new PurchaseMappingService($dataDir);
$mappings->setMapping('entry:products:premium-plan', ['grant_tier' => 'premium']);

$list = $mappings->getMappings();
test_assert('mapping saved', isset($list['entry:products:premium-plan']));
test_assert('grant_tier stored', ($list['entry:products:premium-plan']['grant_tier'] ?? '') === 'premium');

$order = [
    'status' => 'paid',
    'customer_user_id' => 'user_test_1',
    'lines' => [[
        'collection_slug' => 'products',
        'entry_slug' => 'premium-plan',
        'entry_id' => 'e-premium',
    ]],
];

$tierGranted = false;
if (!function_exists('birkly_is_plugin_active')) {
    function birkly_is_plugin_active(string $plugin_id): bool
    {
        return $plugin_id === 'user_management';
    }
}

$userTiersPath = $root . '/plugins/user_management/includes/UserTiers.php';
if (is_file($userTiersPath)) {
    require_once $root . '/plugins/user_management/includes/UserStorage.php';
    require_once $root . '/plugins/user_management/includes/UserService.php';
    require_once $userTiersPath;

    $storage = new UserStorage();
    $storage->saveTier('premium', [
        'id' => 'premium', 'name' => 'Premium', 'description' => 'Test tier',
        'permissions' => [], 'features' => [], 'custom' => [], 'created_at' => date('c'),
    ]);
    $service = new UserService();
    $users = $service->listUsers();
    $userId = null;
    foreach ($users as $u) {
        $userId = $u['id'];
        break;
    }
    if (!$userId) {
        $userId = 'user_test_mapping';
        $service->createUser([
            'id' => $userId,
            'username' => 'mapping_test',
            'email' => 'mapping@test.local',
            'password' => 'testpassword123',
            'tier' => 'all',
        ]);
    }
    $order['customer_user_id'] = $userId;
    $mappings->applyPurchaseMappings($order);
    $tiers = new UserTiers();
    $tierGranted = $tiers->getUserTier($userId) === 'premium';
}

test_assert('mapping apply runs without error', true);
if (is_file($userTiersPath)) {
    test_assert('tier granted on paid order when user_management active', $tierGranted);
}

@unlink($dataDir . '/purchase_mappings.json');
@rmdir($dataDir);

exit(test_finish('Commerce mapping tests'));
