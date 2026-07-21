<?php
/**
 * P12-M4 — Commerce membership wiring via PurchaseMappingService + PostPurchaseRouter.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/fixtures/commerce_scenarios.php';

test_reset_failures();
echo "Commerce membership wiring tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';
require_once $root . '/plugins/commerce/includes/EventRegistrationStore.php';
require_once $root . '/plugins/commerce/includes/DigitalDeliveryStore.php';
require_once $root . '/plugins/commerce/includes/PurchaseMappingService.php';
require_once $root . '/plugins/commerce/includes/PostPurchaseRouter.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-membership-' . getmypid();
$tierId = 'tier_membership_' . getmypid();
$baselineTierId = 'tier_baseline_' . getmypid();
$userId = 'user_membership_wiring_' . getmypid();
@mkdir($dataDir, 0755, true);

require_once $root . '/core/plugins.php';
global $birkly_plugins;
$birkly_plugins['user_management'] = ['id' => 'user_management', 'active' => true, 'path' => $root . '/plugins/user_management'];

$mappings = new PurchaseMappingService($dataDir);

$wizardResult = $mappings->createMembershipMapping([
    'collection_slug' => 'memberships',
    'entry_slug' => 'mem-1',
    'purchase_option_id' => 'community_monthly',
    'grant_tier' => $tierId,
]);
test_assert('wizard creates purchase_option mapping', ($wizardResult['ok'] ?? false) === true);
test_assert('purchase_option key stored', ($wizardResult['key'] ?? '') === 'purchase_option:memberships:mem-1:community_monthly');

$skuResult = $mappings->createMembershipMapping([
    'sku' => 'MEMBERSHIP-PRO',
    'grant_tier' => $tierId,
]);
test_assert('wizard creates sku mapping', ($skuResult['ok'] ?? false) === true);
test_assert('sku key stored', ($skuResult['key'] ?? '') === 'sku:MEMBERSHIP-PRO');

$lineKeys = $mappings->resolveLineMappingKeys([
    'collection_slug' => 'memberships',
    'entry_slug' => 'mem-1',
    'purchase_option_id' => 'community_monthly',
    'sku' => 'MEMBERSHIP-PRO',
]);
test_assert('resolveLineMappingKeys prefers purchase_option', ($lineKeys[0] ?? '') === 'purchase_option:memberships:mem-1:community_monthly');

$userTiersPath = $root . '/plugins/user_management/includes/UserTiers.php';
$testUserEmail = 'membership-wiring-' . getmypid() . '@test.local';

if (is_file($userTiersPath)) {
    require_once $root . '/plugins/user_management/includes/UserStorage.php';
    require_once $root . '/plugins/user_management/includes/UserService.php';
    require_once $userTiersPath;

    $storage = new UserStorage();
    $storage->saveTier($tierId, [
        'id' => $tierId, 'name' => 'Membership test tier', 'description' => 'Test tier',
        'permissions' => [], 'features' => [], 'custom' => [], 'created_at' => date('c'),
    ]);
    $storage->saveTier($baselineTierId, [
        'id' => $baselineTierId, 'name' => 'Membership baseline tier', 'description' => 'Test tier',
        'permissions' => [], 'features' => [], 'custom' => [], 'created_at' => date('c'),
    ]);
    $service = new UserService();
    $storage->purgeUser($userId);
    $createdUser = $service->createUser([
        'id' => $userId,
        'username' => 'membership_wiring',
        'email' => $testUserEmail,
        'password' => 'testpassword123',
        'tier' => $baselineTierId,
    ]);
    $userId = $createdUser['id'];
    $testUser = $service->getUser($userId);
    $testUserEmail = $testUser['email'] ?? $testUserEmail;
}

$router = new PostPurchaseRouter($dataDir, null, null, $mappings);

$registerLine = [
    'line_id' => 'classes:yoga-1:class_ticket',
    'entry_id' => 'yoga-1',
    'entry_slug' => 'yoga-1',
    'collection_slug' => 'classes',
    'quantity' => 1,
    'fulfillment' => 'register',
    'commerce' => commerce_scenario_yoga(),
    'purchase_option_id' => 'class_ticket',
    'billing' => 'one_time',
    'sku' => 'YOGA-SUN',
];
$mappings->createMembershipMapping([
    'collection_slug' => 'classes',
    'entry_slug' => 'yoga-1',
    'purchase_option_id' => 'class_ticket',
    'grant_tier' => $tierId,
]);

$registerOrder = [
    'id' => 'ord_membership_register',
    'status' => 'paid',
    'customer_email' => $testUserEmail,
    'customer_user_id' => $userId,
    'attendee_name' => 'Test User',
    'lines' => [$registerLine],
];

$registerResult = $router->route($registerOrder);
test_assert('register fulfillment routes ok', ($registerResult[0]['ok'] ?? false) === true);

if ($userId && is_file($userTiersPath)) {
    $tiers = new UserTiers();
    test_assert('register fulfillment grants tier via PostPurchaseRouter', $tiers->getUserTier($userId) === $tierId);
}

$noneLine = [
    'line_id' => 'memberships:mem-1:community_monthly',
    'entry_id' => 'mem-1',
    'entry_slug' => 'mem-1',
    'collection_slug' => 'memberships',
    'quantity' => 1,
    'fulfillment' => 'none',
    'commerce' => commerce_scenario_community(),
    'purchase_option_id' => 'community_monthly',
    'billing' => 'recurring',
    'interval' => 'month',
];

if ($userId) {
    $tiers = new UserTiers();
    $tiers->setUserTier($userId, $baselineTierId);
}

$noneOrder = [
    'id' => 'ord_membership_none',
    'status' => 'paid',
    'customer_email' => $testUserEmail,
    'customer_user_id' => $userId,
    'lines' => [$noneLine],
];
$noneResult = $router->route($noneOrder);
test_assert('none fulfillment routes ok', ($noneResult[0]['ok'] ?? false) === true);
test_assert('none fulfillment reports tier_granted', ($noneResult[0]['tier_granted'] ?? false) === true);

if ($userId && is_file($userTiersPath)) {
    $tiers = new UserTiers();
    test_assert('none fulfillment grants purchase_option tier', $tiers->getUserTier($userId) === $tierId);
}

@unlink($dataDir . '/purchase_mappings.json');
@unlink($dataDir . '/registrations.json');
@unlink($dataDir . '/fulfillment_state.json');
@rmdir($dataDir);
if (isset($storage)) {
    $storage->purgeUser($userId);
    $storage->deleteTier($tierId);
    $storage->deleteTier($baselineTierId);
}

exit(test_finish('Commerce membership wiring tests'));
