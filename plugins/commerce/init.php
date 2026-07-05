<?php
/**
 * Commerce plugin initialization — shelf, resolver, webhook router, migration.
 */

function commerce_plugin_dir(): string
{
    return __DIR__;
}

function commerce_data_dir(): string
{
    $root = dirname(__DIR__, 2);
    return $root . '/data/plugins/commerce';
}

function commerce_legacy_payments_data_dir(): string
{
    $root = dirname(__DIR__, 2);
    return $root . '/data/plugins/payments';
}

function commerce_plugin_active(): bool
{
    if (!function_exists('birkly_is_plugin_active')) {
        require_once dirname(__DIR__, 2) . '/core/plugins.php';
    }
    return birkly_is_plugin_active('commerce') || birkly_is_plugin_active('payments');
}

function commerce_active_plugin_id(): string
{
    if (function_exists('birkly_is_plugin_active') && birkly_is_plugin_active('commerce')) {
        return 'commerce';
    }
    return 'payments';
}

function commerce_migrate_legacy_data(): void
{
    $commerceDir = commerce_data_dir();
    $legacyDir = commerce_legacy_payments_data_dir();
    $flagFile = $commerceDir . '/.migrated_from_payments';

    if (is_file($flagFile)) {
        return;
    }

    if (!is_dir($commerceDir)) {
        mkdir($commerceDir, 0755, true);
    }

    if (is_dir($legacyDir) && $legacyDir !== $commerceDir) {
        commerce_copy_dir($legacyDir, $commerceDir);
    }

    commerce_migrate_plugin_settings();

    file_put_contents($flagFile, date('c'));
}

function commerce_copy_dir(string $src, string $dest): void
{
    if (!is_dir($dest)) {
        mkdir($dest, 0755, true);
    }
    foreach (scandir($src) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $from = $src . '/' . $item;
        $to = $dest . '/' . $item;
        if (is_dir($from)) {
            commerce_copy_dir($from, $to);
        } elseif (!is_file($to)) {
            copy($from, $to);
        }
    }
}

function commerce_migrate_plugin_settings(): void
{
    $root = dirname(__DIR__, 2);
    $legacyFile = $root . '/settings/plugins/payments.json';
    $commerceFile = $root . '/settings/plugins/commerce.json';

    if (!is_file($legacyFile) || is_file($commerceFile)) {
        return;
    }

    $dir = dirname($commerceFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    copy($legacyFile, $commerceFile);
}

/** @return PaymentShelf|null */
function commerce_bootstrap()
{
    static $shelf = null;
    if (!commerce_plugin_active()) {
        return null;
    }
    if ($shelf !== null) {
        return $shelf;
    }

    commerce_migrate_legacy_data();

    $dir = commerce_plugin_dir();
    require_once $dir . '/includes/ProviderInterface.php';
    require_once $dir . '/includes/AbstractProvider.php';
    require_once $dir . '/includes/CredentialStore.php';
    require_once $dir . '/includes/ActivityLog.php';
    require_once $dir . '/includes/ProviderResolver.php';
    require_once $dir . '/includes/WebhookRouter.php';
    require_once $dir . '/includes/PaymentShelf.php';

    foreach ([
        'StripeProvider', 'MollieProvider', 'PayPalProvider', 'AdyenProvider',
        'SquareProvider', 'RazorpayProvider', 'KomojuProvider', 'PayssionProvider',
    ] as $providerFile) {
        require_once $dir . '/includes/providers/' . $providerFile . '.php';
    }

    $store = new PaymentsCredentialStore();
    $activityLog = new PaymentsActivityLog($store->getDataDir());
    $resolver = new PaymentsProviderResolver($store);

    $resolver->register(new StripeProvider());
    $resolver->register(new MollieProvider());
    $resolver->register(new PayPalProvider());
    $resolver->register(new AdyenProvider());
    $resolver->register(new SquareProvider());
    $resolver->register(new RazorpayProvider());
    $resolver->register(new KomojuProvider());
    $resolver->register(new PayssionProvider());

    $shelf = new PaymentShelf($resolver, $store, $activityLog);
    $webhookRouter = new PaymentsWebhookRouter($resolver, $store, $activityLog);
    $shelf->setWebhookRouter($webhookRouter);

    $GLOBALS['commerce_webhook_router'] = $webhookRouter;
    $GLOBALS['commerce_credential_store'] = $store;
    $GLOBALS['commerce_activity_log'] = $activityLog;
    $GLOBALS['payments_webhook_router'] = $webhookRouter;
    $GLOBALS['payments_credential_store'] = $store;
    $GLOBALS['payments_activity_log'] = $activityLog;

    return $shelf;
}

function commerce_webhook_router(): ?PaymentsWebhookRouter
{
    commerce_bootstrap();
    return $GLOBALS['commerce_webhook_router'] ?? null;
}

function commerce_require_admin_capability(string $capability): void
{
    if (!isset($_SESSION['user'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    $role = $_SESSION['user']['role'] ?? '';
    if ($role !== 'admin' && $role !== 'superadmin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
        exit;
    }
}

function commerce_can_view_activity(): bool
{
    if (!isset($_SESSION['user'])) {
        return false;
    }
    $role = $_SESSION['user']['role'] ?? '';
    return in_array($role, ['admin', 'superadmin'], true);
}

function commerce_can_manage(): bool
{
    return commerce_can_view_activity();
}

function commerce_can_view_stats(): bool
{
    return commerce_can_view_activity();
}

function commerce_can_view_orders(): bool
{
    return commerce_can_view_activity();
}

function commerce_get_setting(string $key, $default = null)
{
    $pluginId = commerce_active_plugin_id();
    if (function_exists('get_plugin_setting')) {
        $val = get_plugin_setting($pluginId, $key, null);
        if ($val !== null) {
            return $val;
        }
        if ($pluginId === 'commerce') {
            return get_plugin_setting('payments', $key, $default);
        }
        return get_plugin_setting('commerce', $key, $default);
    }
    return $default;
}

function commerce_init_automation_triggers(): void
{
    if (!function_exists('register_automation_block')) {
        return;
    }

    register_automation_block('trigger', 'payment_succeeded', [
        'name' => 'Payment Succeeded',
        'description' => 'Fires when a payment succeeds',
        'category' => 'Commerce',
        'schema' => [
            'provider' => [
                'type' => 'string',
                'label' => 'Provider (optional)',
                'description' => 'Filter by provider ID, or leave empty for all',
            ],
        ],
        'handler' => function ($config, $context) {
            if (!empty($config['provider'])) {
                return ($context['provider'] ?? '') === $config['provider'];
            }
            return true;
        },
    ]);

    register_automation_block('trigger', 'payment_failed', [
        'name' => 'Payment Failed',
        'description' => 'Fires when a payment fails',
        'category' => 'Commerce',
        'schema' => [
            'provider' => [
                'type' => 'string',
                'label' => 'Provider (optional)',
                'description' => 'Filter by provider ID, or leave empty for all',
            ],
        ],
        'handler' => function ($config, $context) {
            if (!empty($config['provider'])) {
                return ($context['provider'] ?? '') === $config['provider'];
            }
            return true;
        },
    ]);

    register_automation_block('trigger', 'commerce_order_paid', [
        'name' => 'Commerce Order Paid',
        'description' => 'Fires when a commerce order is paid',
        'category' => 'Commerce',
        'schema' => [],
        'handler' => function ($config, $context) {
            return true;
        },
    ]);

    register_automation_block('trigger', 'commerce_order_created', [
        'name' => 'Commerce Order Created',
        'description' => 'Fires when a commerce order is created',
        'category' => 'Commerce',
        'schema' => [],
        'handler' => function ($config, $context) {
            return true;
        },
    ]);

    register_automation_block('trigger', 'commerce_physical_fulfilled', [
        'name' => 'Physical Order Fulfilled',
        'description' => 'Fires when a ship fulfillment line is pushed to the fulfillment adapter',
        'category' => 'Commerce',
        'schema' => [],
        'handler' => function ($config, $context) {
            return true;
        },
    ]);

    register_automation_block('trigger', 'commerce_digital_delivered', [
        'name' => 'Digital Delivery Created',
        'description' => 'Fires when a deliver fulfillment line receives a download token',
        'category' => 'Commerce',
        'schema' => [],
        'handler' => function ($config, $context) {
            return true;
        },
    ]);

    register_automation_block('trigger', 'commerce_event_registered', [
        'name' => 'Event Registration',
        'description' => 'Fires when a register fulfillment line creates an attendee record',
        'category' => 'Commerce',
        'schema' => [],
        'handler' => function ($config, $context) {
            return true;
        },
    ]);

    register_automation_block('trigger', 'commerce_membership_started', [
        'name' => 'Membership Started',
        'description' => 'Fires when a recurring none-fulfillment purchase begins',
        'category' => 'Commerce',
        'schema' => [],
        'handler' => function ($config, $context) {
            return true;
        },
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function commerce_dashboard_widgets(): array
{
    if (!commerce_plugin_active() || !commerce_can_view_stats()) {
        return [];
    }

    $enabled = (bool) commerce_get_setting('enable_dashboard_widget', false);
    if (!$enabled) {
        return [];
    }

    $roles = commerce_get_setting('dashboard_widget_roles', ['admin']);
    if (!is_array($roles)) {
        $roles = ['admin'];
    }

    $commerceUrl = function_exists('admin_url') ? admin_url('commerce') : '/admin/index.php?page=commerce';

    return [[
        'id' => 'commerce-stats',
        'title' => 'Commerce',
        'roles' => $roles,
        'html' => '<div class="payments-dash-widget" id="commerce-dash-widget">'
            . '<p class="payments-dash-loading">Loading commerce stats…</p></div>'
            . '<p class="payments-dash-link"><a href="' . htmlspecialchars($commerceUrl) . '">Manage commerce →</a></p>',
    ]];
}

function commerce_bootstrap_cart_services(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $dir = commerce_plugin_dir();
    require_once $dir . '/includes/CommerceCatalog.php';
    require_once $dir . '/includes/CommerceCartSession.php';
    require_once $dir . '/includes/CommerceOrderStore.php';
    require_once $dir . '/includes/DiscountEngine.php';
    require_once $dir . '/includes/CommerceCheckoutService.php';
    require_once $dir . '/includes/CatalogIndexer.php';
    require_once $dir . '/includes/PurchaseMappingService.php';
    require_once $dir . '/includes/FulfillmentAdapterInterface.php';
    require_once $dir . '/includes/ManualFulfillmentAdapter.php';
    require_once $dir . '/includes/ShipStationStubAdapter.php';
    require_once $dir . '/includes/ImportService.php';
    require_once $dir . '/includes/CommerceSchema.php';
    require_once $dir . '/includes/EventRegistrationStore.php';
    require_once $dir . '/includes/DigitalDeliveryStore.php';
    require_once $dir . '/includes/PostPurchaseRouter.php';
    $loaded = true;
}

function commerce_on_payment_succeeded(array $data): void
{
    if (!commerce_plugin_active()) {
        return;
    }
    commerce_bootstrap_cart_services();
    $paymentId = $data['payment_id'] ?? '';
    if ($paymentId === '') {
        return;
    }
    $checkout = new CommerceCheckoutService();
    $checkout->markPaidByPaymentId($paymentId);
}

function commerce_register_payment_handlers(): void
{
    if (!function_exists('subscribe_to_event')) {
        return;
    }
    static $registered = false;
    if ($registered) {
        return;
    }
    subscribe_to_event('payment_succeeded', function (array $data) {
        commerce_on_payment_succeeded($data);
    });
    $registered = true;
}

function commerce_buy_url(string $entryId = '', string $collectionSlug = '', int $qty = 1, string $purchaseOptionId = ''): string
{
    $base = function_exists('birkly_url')
        ? birkly_url('/api/index.php?route=commerce&action=add')
        : '/api/index.php?route=commerce&action=add';
    $params = [];
    if ($entryId !== '') {
        $params['entry_id'] = $entryId;
    }
    if ($collectionSlug !== '') {
        $params['collection'] = $collectionSlug;
    }
    if ($qty > 1) {
        $params['quantity'] = $qty;
    }
    if ($purchaseOptionId !== '') {
        $params['purchase_option_id'] = $purchaseOptionId;
    }
    if (empty($params)) {
        return $base;
    }
    return $base . '&' . http_build_query($params);
}

function commerce_cart_url(): string
{
    return function_exists('birkly_url')
        ? birkly_url('/api/index.php?route=commerce&action=get')
        : '/api/index.php?route=commerce&action=get';
}

// --- Legacy payments_* aliases ---

function payments_plugin_dir(): string { return commerce_plugin_dir(); }
function payments_plugin_active(): bool { return commerce_plugin_active(); }
function payments_bootstrap() { return commerce_bootstrap(); }
function payments_webhook_router(): ?PaymentsWebhookRouter { return commerce_webhook_router(); }
function payments_require_admin_capability(string $capability): void { commerce_require_admin_capability($capability); }
function payments_can_view_activity(): bool { return commerce_can_view_activity(); }
function payments_can_manage(): bool { return commerce_can_manage(); }
function payments_can_view_stats(): bool { return commerce_can_view_stats(); }
function payments_init_automation_triggers(): void { commerce_init_automation_triggers(); }
function payments_dashboard_widgets(): array { return commerce_dashboard_widgets(); }
