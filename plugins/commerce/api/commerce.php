<?php
/**
 * Commerce API — cart, checkout, catalog, orders, discounts, import, mappings, fulfillment.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../../api/session.php';
haru_secure_session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../../api/auth.php';
require_once __DIR__ . '/../../../core/csrf.php';
require_once __DIR__ . '/../init.php';

if (!commerce_plugin_active()) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Commerce plugin is not active']);
    exit;
}

commerce_bootstrap();
commerce_bootstrap_cart_services();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

$cart = new CommerceCartSession();
$catalog = new CommerceCatalog();
$orders = new CommerceOrderStore();
$checkout = new CommerceCheckoutService($orders, $catalog, $cart);
$indexer = new CatalogIndexer();
$discounts = new DiscountEngine();
$import = new ImportService($indexer);
require_once __DIR__ . '/../includes/LegacyMigrationService.php';
$migration = new LegacyMigrationService($indexer);
$mappings = new PurchaseMappingService();
require_once __DIR__ . '/../includes/CommerceFieldtypeProfileStore.php';
$fieldtypeProfiles = new CommerceFieldtypeProfileStore();
require_once __DIR__ . '/../includes/CommerceSubscriptionStore.php';
require_once __DIR__ . '/../includes/CommerceSubscriptionService.php';
require_once __DIR__ . '/../includes/SubscriptionUpgradeService.php';
$subscriptionStore = new CommerceSubscriptionStore();
$subscriptions = new CommerceSubscriptionService($subscriptionStore, commerce_bootstrap());
$subscriptionUpgrades = new SubscriptionUpgradeService($subscriptionStore, commerce_bootstrap(), $catalog);
$giftCards = new GiftCardStore();
$quoteStore = new CommerceQuoteStore();
$analytics = new CommerceAnalyticsService($orders);

$publicActions=['get','add','update','remove','checkout','set_coupon','set_affiliate','set_gift_card','get_quote','accept_quote','quote_checkout'];
$adminActions = [
    'list_catalog', 'reindex_catalog', 'list_orders', 'get_order', 'refund_order',
    'list_subscriptions', 'get_subscription', 'cancel_subscription', 'upgrade_subscription',
    'list_subscription_upgrade_options', 'retry_subscription_payment',
    'list_discounts', 'save_discount', 'delete_discount',
    'import_dry_run', 'import_csv', 'import_sources', 'import_history', 'import_detail', 'import_collections', 'import_rollback',
    'list_mappings', 'save_mapping', 'delete_mapping',
    'list_fulfillment', 'test_fulfillment', 'save_fulfillment',
    'list_shipping_zones', 'save_shipping_zones',
    'list_tax_rules', 'save_tax_rules',
    'list_fieldtype_profiles', 'save_fieldtype_profile', 'delete_fieldtype_profile',
    'migration_scan', 'migration_preview', 'migration_import', 'migration_history', 'migration_rollback',
    'list_gift_cards', 'issue_gift_card', 'void_gift_card', 'create_quote', 'list_quotes', 'cancel_quote',
];
$statsActions=['analytics_summary','analytics_export_csv'];

if (in_array($action, $adminActions, true)) {
    commerce_require_admin_capability('manage_commerce');
}
if (in_array($action, $statsActions, true)) {
    commerce_require_stats_capability();
}

if (in_array($action, ['save_discount', 'delete_discount', 'import_csv', 'import_rollback', 'migration_import', 'migration_rollback', 'save_mapping', 'delete_mapping', 'save_fulfillment', 'save_shipping_zones', 'save_tax_rules', 'refund_order', 'save_fieldtype_profile', 'delete_fieldtype_profile', 'cancel_subscription', 'upgrade_subscription', 'retry_subscription_payment', 'issue_gift_card', 'void_gift_card', 'create_quote', 'cancel_quote'], true)
    && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $input['_csrf'] ?? $_POST['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
}

switch ($action) {
    case 'set_affiliate':
        $ref = $input['ref'] ?? $_GET['ref'] ?? '';
        $cart->setAffiliateId($ref !== '' ? $ref : null);
        echo json_encode(['status' => 'ok', 'affiliate_id' => $cart->getAffiliateId()]);
        break;

    case 'set_coupon':
        $code = $input['code'] ?? $_GET['code'] ?? '';
        $cart->setCouponCode($code !== '' ? $code : null);
        echo json_encode(['status' => 'ok', 'coupon' => $cart->getCouponCode()]);
        break;

    case 'set_gift_card':
        $gc = $input['code'] ?? $_GET['code'] ?? '';
        $cart->setGiftCardCode($gc !== '' ? $gc : null);
        echo json_encode(['status' => 'ok', 'gift_card' => $cart->getGiftCardCode()]);
        break;

    case 'get':
        $built = $catalog->buildCartLines(
            $cart->getItems(),
            $discounts,
            $cart->getCouponCode(),
            $cart->getAffiliateId()
        );
        echo json_encode([
            'status' => 'ok',
            'data' => [
                'items' => $built['lines'],
                'count' => $cart->count(),
                'total_cents' => $built['total_cents'],
                'discount_cents' => $built['discount_cents'] ?? 0,
                'currency' => $built['currency'],
                'coupon' => $cart->getCouponCode(),
                'affiliate_id' => $cart->getAffiliateId(),
                'gift_card' => $cart->getGiftCardCode(),
            ],
        ]);
        break;

    case 'add':
        $entryId = $input['entry_id'] ?? $_GET['entry_id'] ?? '';
        $collection = $input['collection'] ?? $_GET['collection'] ?? '';
        $qty = (int) ($input['quantity'] ?? $_GET['quantity'] ?? 1);
        $purchaseOptionId = $input['purchase_option_id'] ?? $_GET['purchase_option_id'] ?? null;
        $parentId = $input['parent_id'] ?? $_GET['parent_id'] ?? null;
        if ($entryId === '' || $collection === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'entry_id and collection required']);
            break;
        }
        if (!$catalog->getItem($collection, $entryId)) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Item not in catalog']);
            break;
        }
        $cart->addItem($collection, $entryId, $qty, $purchaseOptionId, $parentId);
        $built = $catalog->buildCartLines($cart->getItems(), $discounts, $cart->getCouponCode(), $cart->getAffiliateId());
        echo json_encode(['status' => 'ok', 'data' => ['count' => $cart->count(), 'total_cents' => $built['total_cents'] ?? 0]]);
        break;

    case 'update':
        $entryId = $input['entry_id'] ?? '';
        $collection = $input['collection'] ?? '';
        $qty = (int) ($input['quantity'] ?? 0);
        if ($entryId === '' || $collection === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'entry_id and collection required']);
            break;
        }
        $cart->updateItem($collection, $entryId, $qty);
        echo json_encode(['status' => 'ok', 'data' => ['count' => $cart->count()]]);
        break;

    case 'remove':
        $entryId = $input['entry_id'] ?? $_GET['entry_id'] ?? '';
        $collection = $input['collection'] ?? $_GET['collection'] ?? '';
        $cart->removeItem($collection, $entryId);
        echo json_encode(['status' => 'ok', 'data' => ['count' => $cart->count()]]);
        break;

    case 'checkout':
        $email = $input['email'] ?? $_GET['email'] ?? '';
        $returnUrl = $input['return_url'] ?? '';
        $cancelUrl = $input['cancel_url'] ?? '';
        $checkoutData = [
            'shipping_address' => $input['shipping_address'] ?? null,
            'attendee_name' => $input['attendee_name'] ?? null,
        ];
        $result = $checkout->checkout($email, $returnUrl, $cancelUrl, $checkoutData);
        echo json_encode(['status' => ($result['ok'] ?? false) ? 'ok' : 'error', 'data' => $result]);
        break;

    case 'list_catalog':
        $filters = $input['filters'] ?? [];
        echo json_encode(['status' => 'ok', 'data' => ['catalog' => $catalog->listItems($filters)]]);
        break;

    case 'reindex_catalog':
        $count = $indexer->reindexAll();
        echo json_encode(['status' => 'ok', 'data' => ['indexed' => $count]]);
        break;

    case 'list_orders':
        echo json_encode(['status' => 'ok', 'data' => ['orders' => $orders->listAll()]]);
        break;

    case 'get_order':
        $id = $input['id'] ?? $_GET['id'] ?? '';
        $order = $orders->get($id);
        if (!$order) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Order not found']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => ['order' => $order]]);
        break;

    case 'list_subscriptions':
        echo json_encode(['status' => 'ok', 'data' => ['subscriptions' => $subscriptionStore->listAll()]]);
        break;

    case 'get_subscription':
        $id = $input['id'] ?? $_GET['id'] ?? '';
        $subscription = $subscriptionStore->get($id);
        if (!$subscription) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Subscription not found']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => ['subscription' => $subscription]]);
        break;

    case 'cancel_subscription':
        $id = $input['id'] ?? '';
        if ($id === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'id required']);
            break;
        }
        $canceled = $subscriptions->cancel($id);
        if (!$canceled) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Subscription not found']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => ['subscription' => $canceled]]);
        break;

    case 'list_subscription_upgrade_options':
        $id = $input['id'] ?? $_GET['id'] ?? '';
        if ($id === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'id required']);
            break;
        }
        $sub = $subscriptionStore->get($id);
        if (!$sub) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Subscription not found']);
            break;
        }
        echo json_encode([
            'status' => 'ok',
            'data' => ['options' => $subscriptionUpgrades->listUpgradeOptions($id)],
        ]);
        break;

    case 'upgrade_subscription':
        $id = $input['id'] ?? '';
        $purchaseOptionId = $input['purchase_option_id'] ?? '';
        if ($id === '' || $purchaseOptionId === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'id and purchase_option_id required']);
            break;
        }
        $result = $subscriptionUpgrades->upgrade($id, $purchaseOptionId);
        if (!($result['ok'] ?? false)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Upgrade failed']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => $result]);
        break;

    case 'retry_subscription_payment':
        $id = $input['id'] ?? '';
        if ($id === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'id required']);
            break;
        }
        $result = $subscriptions->retryPayment($id);
        if (!($result['ok'] ?? false)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Retry failed']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => $result]);
        break;

    case 'list_discounts':
        echo json_encode(['status' => 'ok', 'data' => ['rules' => array_values($discounts->listRules())]]);
        break;

    case 'save_discount':
        $rule = $input['rule'] ?? [];
        $saved = $discounts->saveRule($rule);
        echo json_encode(['status' => 'ok', 'data' => ['rule' => $saved]]);
        break;

    case 'delete_discount':
        $id = $input['id'] ?? '';
        $ok = $discounts->deleteRule($id);
        echo json_encode(['status' => $ok ? 'ok' : 'error']);
        break;

    case 'import_sources':
        echo json_encode(['status' => 'ok', 'data' => ['sources' => ImportService::getImportSources()]]);
        break;

    case 'import_collections':
        echo json_encode(['status' => 'ok', 'data' => ['collections' => $import->listImportCollections()]]);
        break;

    case 'import_history':
        require_once __DIR__ . '/../includes/ImportHistoryStore.php';
        $history = new ImportHistoryStore();
        $limit = max(1, min(100, (int) ($input['limit'] ?? 50)));
        echo json_encode(['status' => 'ok', 'data' => ['imports' => $history->list($limit)]]);
        break;

    case 'import_detail':
        require_once __DIR__ . '/../includes/ImportHistoryStore.php';
        $importId = $input['id'] ?? '';
        if ($importId === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'id required']);
            break;
        }
        $history = new ImportHistoryStore();
        $record = $history->get($importId);
        if ($record === null) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Import not found']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => ['import' => $record]]);
        break;

    case 'import_rollback':
        $importId = trim((string) ($input['id'] ?? ''));
        if ($importId === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'id required']);
            break;
        }
        $deleteCollection = !array_key_exists('delete_collection', $input) || !empty($input['delete_collection']);
        $result = $import->rollbackImport($importId, $deleteCollection);
        if (!($result['ok'] ?? false)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Rollback failed']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => $result]);
        break;

    case 'import_dry_run':
        $csv = $input['csv'] ?? '';
        $preset = $input['preset'] ?? 'generic';
        $collection = $input['collection'] ?? 'products';
        $defaultCurrency = $input['default_currency'] ?? 'eur';
        $createCollection = !empty($input['create_collection']);
        $collectionTitle = trim((string) ($input['collection_title'] ?? ''));
        if ($createCollection && $collectionTitle !== '') {
            require_once __DIR__ . '/../includes/ImportCollectionHelper.php';
            $collection = (new ImportCollectionHelper())->previewSlugForTitle($collectionTitle);
        }
        $parsed = $import->parseCsv($csv, $preset);
        if (!($parsed['ok'] ?? false)) {
            echo json_encode(['status' => 'error', 'message' => $parsed['error'] ?? 'Parse failed']);
            break;
        }
        $rows = $import->applyDefaultCurrency($parsed['rows'], $defaultCurrency);
        $dry = $import->dryRun($rows, $collection);
        echo json_encode(['status' => 'ok', 'data' => $dry]);
        break;

    case 'import_csv':
        $csv = $input['csv'] ?? '';
        $preset = $input['preset'] ?? 'generic';
        $collection = $input['collection'] ?? 'products';
        $defaultCurrency = $input['default_currency'] ?? 'eur';
        $dryRun = !empty($input['dry_run']);
        $createCollection = !empty($input['create_collection']);
        $collectionTitle = trim((string) ($input['collection_title'] ?? ''));
        if ($createCollection && $collectionTitle !== '') {
            require_once __DIR__ . '/../includes/ImportCollectionHelper.php';
            $collection = (new ImportCollectionHelper())->previewSlugForTitle($collectionTitle);
        }
        $parsed = $import->parseCsv($csv, $preset);
        if (!($parsed['ok'] ?? false)) {
            echo json_encode(['status' => 'error', 'message' => $parsed['error'] ?? 'Parse failed']);
            break;
        }
        $rows = $import->applyDefaultCurrency($parsed['rows'], $defaultCurrency);
        $result = $import->importRows($rows, $collection, $dryRun, [
            'preset' => $preset,
            'platform' => ImportService::platformName($preset),
            'currency' => $defaultCurrency,
            'file_name' => trim((string) ($input['file_name'] ?? '')) ?: null,
            'create_collection' => $createCollection,
            'collection_title' => $collectionTitle !== '' ? $collectionTitle : null,
        ]);
        if (!($result['ok'] ?? false)) {
            echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Import failed']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => $result]);
        break;

    case 'list_mappings':
        echo json_encode(['status' => 'ok', 'data' => ['mappings' => $mappings->getMappings()]]);
        break;

    case 'save_mapping':
        $key = $input['key'] ?? '';
        $config = $input['config'] ?? [];
        if ($key === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'key required']);
            break;
        }
        $mappings->setMapping($key, $config);
        echo json_encode(['status' => 'ok']);
        break;

    case 'delete_mapping':
        $key = $input['key'] ?? '';
        $ok = $mappings->deleteMapping($key);
        echo json_encode(['status' => $ok ? 'ok' : 'error']);
        break;

    case 'list_fulfillment':
        $file = commerce_data_dir() . '/fulfillment_connections.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        echo json_encode(['status' => 'ok', 'data' => ['connections' => $data ?: ['active_adapter' => 'manual']]]);
        break;

    case 'test_fulfillment':
        $adapterId = $input['adapter'] ?? 'manual';
        $adapter = $adapterId === 'shipstation' ? new ShipStationStubAdapter() : new ManualFulfillmentAdapter();
        echo json_encode(['status' => 'ok', 'data' => $adapter->testConnection()]);
        break;

    case 'save_fulfillment':
        $file = commerce_data_dir() . '/fulfillment_connections.json';
        $data = $input['connections'] ?? [];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
        echo json_encode(['status' => 'ok']);
        break;

    case 'refund_order':
        $id = $input['id'] ?? '';
        if ($id === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'id required']);
            break;
        }
        $amountRaw = $input['amount_cents'] ?? null;
        $amountCents = ($amountRaw === null || $amountRaw === '') ? null : (int) $amountRaw;
        $reason = (string) ($input['reason'] ?? '');
        $result = $checkout->refundOrder($id, $amountCents, $reason);
        if (!($result['ok'] ?? false)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Refund failed']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => $result]);
        break;

    case 'list_shipping_zones':
        echo json_encode(['status' => 'ok', 'data' => $catalog->loadShippingZones()]);
        break;

    case 'save_shipping_zones':
        $zones = $input['zones'] ?? [];
        $catalog->saveShippingZones(['zones' => $zones]);
        echo json_encode(['status' => 'ok']);
        break;

    case 'list_tax_rules':
        echo json_encode(['status' => 'ok', 'data' => $catalog->loadTaxRules()]);
        break;

    case 'save_tax_rules':
        $catalog->saveTaxRules([
            'enabled' => !empty($input['enabled']),
            'default_rate' => (float) ($input['default_rate'] ?? 0),
            'country_rates' => $input['country_rates'] ?? [],
        ]);
        echo json_encode(['status' => 'ok', 'data' => $catalog->loadTaxRules()]);
        break;

    case 'list_fieldtype_profiles':
        echo json_encode(['status' => 'ok', 'data' => ['profiles' => $fieldtypeProfiles->listProfiles()]]);
        break;

    case 'save_fieldtype_profile':
        $profile = $input['profile'] ?? [];
        if (empty($profile['name'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Profile name required']);
            break;
        }
        $saved = $fieldtypeProfiles->saveProfile($profile);
        commerce_register_fieldtype_profiles();
        echo json_encode(['status' => 'ok', 'data' => ['profile' => $saved]]);
        break;

    case 'delete_fieldtype_profile':
        $id = $input['id'] ?? '';
        if ($id === '' || $id === 'default') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Cannot delete default profile']);
            break;
        }
        $ok = $fieldtypeProfiles->deleteProfile($id);
        if ($ok) {
            commerce_register_fieldtype_profiles();
        }
        echo json_encode(['status' => $ok ? 'ok' : 'error']);
        break;

    case 'migration_scan':
        echo json_encode(['status' => 'ok', 'data' => $migration->scan()]);
        break;

    case 'migration_preview':
        echo json_encode(['status' => 'ok', 'data' => $migration->preview($input)]);
        break;

    case 'migration_import':
        $result = $migration->import($input);
        if (!($result['ok'] ?? false)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Import failed']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => $result]);
        break;

    case 'migration_history':
        $limit = max(1, min(100, (int) ($input['limit'] ?? 50)));
        echo json_encode(['status' => 'ok', 'data' => ['migrations' => $migration->listHistory($limit)]]);
        break;

    case 'migration_rollback':
        $importId = trim((string) ($input['id'] ?? ''));
        if ($importId === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'id required']);
            break;
        }
        $result = $migration->rollback($importId);
        if (!($result['ok'] ?? false)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $result['error'] ?? 'Rollback failed']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => $result]);
        break;

    case 'list_gift_cards':
        echo json_encode(['status' => 'ok', 'data' => ['gift_cards' => $giftCards->listAll()]]);
        break;

    case 'issue_gift_card':
        try {
            $c = $giftCards->issue([
                'code' => $input['code'] ?? null,
                'balance_cents' => (int) ($input['balance_cents'] ?? 0),
                'currency' => $input['currency'] ?? 'eur',
                'expires_at' => $input['expires_at'] ?? null,
            ]);
            echo json_encode(['status' => 'ok', 'data' => ['gift_card' => $c]]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        break;

    case 'void_gift_card':
        echo json_encode(['status' => $giftCards->voidCard($input['code'] ?? '') ? 'ok' : 'error']);
        break;

    case 'create_quote':
        $b = $catalog->buildCartLines($cart->getItems(), $discounts, $cart->getCouponCode(), $cart->getAffiliateId());
        if (empty($b['lines'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Cart is empty']);
            break;
        }
        $q = $quoteStore->createFromBuiltCart($b, $input['customer_email'] ?? '');
        echo json_encode(['status' => 'ok', 'data' => ['quote' => $q, 'accept_url' => $quoteStore->acceptUrl($q['token'])]]);
        break;

    case 'list_quotes':
        echo json_encode(['status' => 'ok', 'data' => ['quotes' => $quoteStore->listAll()]]);
        break;

    case 'cancel_quote':
        $quoteId = trim((string) ($input['id'] ?? ''));
        if ($quoteId === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'id required']);
            break;
        }
        echo json_encode(['status' => $quoteStore->cancel($quoteId) ? 'ok' : 'error']);
        break;

    case 'get_quote':
        $q = $quoteStore->findByToken($input['token'] ?? $_GET['token'] ?? '');
        if (!$q) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quote not found']);
            break;
        }
        echo json_encode(['status' => 'ok', 'data' => ['quote' => $q]]);
        break;

    case 'accept_quote':
        $r = $quoteStore->accept($input['token'] ?? '');
        echo json_encode(['status' => ($r['ok'] ?? false) ? 'ok' : 'error', 'data' => $r]);
        break;

    case 'quote_checkout':
        $r = $checkout->checkoutQuote($input['token'] ?? '', $input['email'] ?? '');
        echo json_encode(['status' => ($r['ok'] ?? false) ? 'ok' : 'error', 'data' => $r]);
        break;

    case 'analytics_summary':
        echo json_encode(['status' => 'ok', 'data' => ['summary' => $analytics->getSummary($input['from'] ?? null, $input['to'] ?? null)]]);
        break;

    case 'analytics_export_csv':
        header('Content-Type: text/csv');
        echo $analytics->exportOrdersCsv($input['from'] ?? null, $input['to'] ?? null);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
        break;
}
