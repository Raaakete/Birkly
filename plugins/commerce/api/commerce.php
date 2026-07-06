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
$mappings = new PurchaseMappingService();
require_once __DIR__ . '/../includes/CommerceFieldtypeProfileStore.php';
$fieldtypeProfiles = new CommerceFieldtypeProfileStore();

$publicActions = ['get', 'add', 'update', 'remove', 'checkout', 'set_coupon', 'set_affiliate'];
$adminActions = [
    'list_catalog', 'reindex_catalog', 'list_orders', 'get_order',
    'list_discounts', 'save_discount', 'delete_discount',
    'import_dry_run', 'import_csv', 'import_sources', 'import_history', 'import_detail',
    'list_mappings', 'save_mapping', 'delete_mapping',
    'list_fulfillment', 'test_fulfillment', 'save_fulfillment',
    'list_shipping_zones', 'save_shipping_zones',
    'list_fieldtype_profiles', 'save_fieldtype_profile', 'delete_fieldtype_profile',
];

if (in_array($action, $adminActions, true)) {
    commerce_require_admin_capability('manage_commerce');
}

if (in_array($action, ['save_discount', 'delete_discount', 'import_csv', 'save_mapping', 'delete_mapping', 'save_fulfillment', 'save_shipping_zones', 'save_fieldtype_profile', 'delete_fieldtype_profile'], true)
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

    case 'import_dry_run':
        $csv = $input['csv'] ?? '';
        $preset = $input['preset'] ?? 'generic';
        $collection = $input['collection'] ?? 'products';
        $defaultCurrency = $input['default_currency'] ?? 'eur';
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
        ]);
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

    case 'list_shipping_zones':
        echo json_encode(['status' => 'ok', 'data' => $catalog->loadShippingZones()]);
        break;

    case 'save_shipping_zones':
        $zones = $input['zones'] ?? [];
        $catalog->saveShippingZones(['zones' => $zones]);
        echo json_encode(['status' => 'ok']);
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

    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
        break;
}
