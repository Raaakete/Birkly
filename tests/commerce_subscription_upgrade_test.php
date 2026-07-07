<?php
/**
 * P11-M1 — Subscription upgrade tests (monthly → annual mock flow).
 */

require_once __DIR__ . '/bootstrap.php';

test_reset_failures();
echo "Commerce subscription upgrade tests\n\n";

$root = dirname(__DIR__);
require_once $root . '/plugins/commerce/init.php';

$dataDir = sys_get_temp_dir() . '/birkly-commerce-upgrade-' . getmypid();
$subsDir = $dataDir . '/subscriptions';
$catalogDir = $dataDir;
@mkdir($subsDir, 0755, true);

require_once $root . '/plugins/commerce/includes/ProviderInterface.php';
require_once $root . '/plugins/commerce/includes/AbstractProvider.php';
require_once $root . '/plugins/commerce/includes/CredentialStore.php';
require_once $root . '/plugins/commerce/includes/ActivityLog.php';
require_once $root . '/plugins/commerce/includes/ProviderResolver.php';
require_once $root . '/plugins/commerce/includes/PaymentShelf.php';
require_once $root . '/plugins/commerce/includes/providers/StripeProvider.php';
require_once $root . '/plugins/commerce/includes/CommerceSchema.php';
require_once $root . '/plugins/commerce/includes/CatalogIndexer.php';
require_once $root . '/plugins/commerce/includes/CommerceCatalog.php';
require_once $root . '/plugins/commerce/includes/CommerceSubscriptionStore.php';
require_once $root . '/plugins/commerce/includes/CommerceSubscriptionService.php';
require_once $root . '/plugins/commerce/includes/SubscriptionUpgradeService.php';

class TestUpgradeCredentialStore extends PaymentsCredentialStore
{
    public function __construct(string $testDir)
    {
        foreach (['/connections', '/activity', '/webhooks'] as $sub) {
            if (!is_dir($testDir . $sub)) {
                mkdir($testDir . $sub, 0755, true);
            }
        }
        if (!is_file($testDir . '/activity/log.json')) {
            file_put_contents($testDir . '/activity/log.json', '[]');
        }
        $keyFile = $testDir . '/encryption_key.txt';
        if (!is_file($keyFile)) {
            file_put_contents($keyFile, base64_encode(hash('sha256', 'test-key', true)));
        }
        $ref = new ReflectionClass(PaymentsCredentialStore::class);
        $baseProp = $ref->getProperty('baseDir');
        $baseProp->setAccessible(true);
        $baseProp->setValue($this, $testDir);
        $keyProp = $ref->getProperty('encryptionKey');
        $keyProp->setAccessible(true);
        $keyProp->setValue($this, trim(file_get_contents($keyFile)));
    }
}

$payDir = $dataDir . '/payments';
$store = new TestUpgradeCredentialStore($payDir);
$activityLog = new PaymentsActivityLog($payDir);
$resolver = new PaymentsProviderResolver($store);
$resolver->register(new StripeProvider());
$shelf = new PaymentShelf($resolver, $store, $activityLog);
$store->saveConnection([
    'provider' => 'stripe', 'label' => 'Mock', 'mode' => 'test', 'enabled' => true,
    'credentials' => ['mock' => true, 'secret_key' => 'sk_test'],
]);

$indexer = new CatalogIndexer($catalogDir);
$indexer->indexEntry([
    'id' => 'yoga-membership',
    'slug' => 'yoga-membership',
    'title' => 'Yoga Membership',
    'commerce' => commerce_normalize_value([
        'fulfillment' => 'none',
        'purchase_options' => [
            [
                'id' => 'monthly',
                'label' => 'Monthly',
                'billing' => 'recurring',
                'interval' => 'month',
                'price_cents' => 2500,
                'currency' => 'eur',
            ],
            [
                'id' => 'annual',
                'label' => 'Annual',
                'billing' => 'recurring',
                'interval' => 'year',
                'price_cents' => 25000,
                'currency' => 'eur',
            ],
        ],
    ]),
], 'products');

$subStore = new CommerceSubscriptionStore($subsDir);
$catalog = new CommerceCatalog($indexer);
$upgradeService = new SubscriptionUpgradeService($subStore, $shelf, $catalog);

$sub = $subStore->create([
    'order_id' => 'ord_yoga_1',
    'status' => 'active',
    'provider' => 'stripe',
    'provider_subscription_id' => 'sub_mock_monthly',
    'customer_email' => 'yogi@example.com',
    'purchase_option_id' => 'monthly',
    'interval' => 'month',
    'amount_cents' => 2500,
    'currency' => 'eur',
    'next_billing_at' => date('c', time() + 2592000),
    'lines' => [[
        'entry_id' => 'yoga-membership',
        'collection_slug' => 'products',
        'purchase_option_id' => 'monthly',
        'title' => 'Yoga Membership',
        'quantity' => 1,
        'interval' => 'month',
        'price_cents' => 2500,
    ]],
]);

// --- list upgrade options ---
$options = $upgradeService->listUpgradeOptions($sub['id']);
test_assert('lists one upgrade option', count($options) === 1);
test_assert('upgrade option is annual', ($options[0]['id'] ?? '') === 'annual');
test_assert('annual price correct', (int) ($options[0]['price_cents'] ?? 0) === 25000);

// --- upgrade monthly → annual ---
$result = $upgradeService->upgrade($sub['id'], 'annual');
test_assert('upgrade succeeds', ($result['ok'] ?? false) === true, $result['error'] ?? json_encode($result));

$updated = $result['subscription'] ?? [];
test_assert('interval updated to year', ($updated['interval'] ?? '') === 'year');
test_assert('amount updated to annual price', (int) ($updated['amount_cents'] ?? 0) === 25000);
test_assert('purchase_option_id updated', ($updated['purchase_option_id'] ?? '') === 'annual');
test_assert('line purchase_option updated', ($updated['lines'][0]['purchase_option_id'] ?? '') === 'annual');
test_assert('next_billing_at set', !empty($updated['next_billing_at']));
test_assert('upgraded_at timestamp set', !empty($updated['upgraded_at']));
test_assert('mock proration recorded', isset($updated['last_proration_cents']));

$nextTs = strtotime($updated['next_billing_at']);
test_assert('annual next billing ~1 year out', $nextTs > time() + (86400 * 300));

// --- reject same option ---
$dup = $upgradeService->upgrade($sub['id'], 'annual');
test_assert('reject duplicate upgrade', ($dup['ok'] ?? true) === false);

// --- reject upgrade on canceled ---
$canceled = $subStore->create([
    'order_id' => 'ord_canceled',
    'status' => 'canceled',
    'interval' => 'month',
    'amount_cents' => 900,
    'lines' => [[
        'entry_id' => 'yoga-membership',
        'collection_slug' => 'products',
        'purchase_option_id' => 'monthly',
    ]],
]);
$bad = $upgradeService->upgrade($canceled['id'], 'annual');
test_assert('reject upgrade on canceled', ($bad['ok'] ?? true) === false);

// --- Stripe mock updateSubscription ---
$stripe = new StripeProvider();
$providerResult = $stripe->updateSubscription(
    ['mock' => true],
    'sub_mock_test',
    ['interval' => 'year', 'amount_cents' => 25000]
);
test_assert('stripe mock plan change ok', ($providerResult['ok'] ?? false) === true);
test_assert('stripe mock returns next_billing_at', !empty($providerResult['next_billing_at']));

foreach (glob($subsDir . '/*.json') ?: [] as $f) { @unlink($f); }

exit(test_finish('Commerce subscription upgrade tests'));
