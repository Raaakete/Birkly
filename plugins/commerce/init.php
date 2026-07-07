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
    return birkly_is_plugin_active('commerce');
}

function commerce_active_plugin_id(): string
{
    return 'commerce';
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
    if (!commerce_user_has_capability($capability)) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Forbidden']); exit; }
}
function commerce_user_has_capability(string $capability): bool {
    if (!isset($_SESSION['user'])) return false;
    if (in_array($_SESSION['user']['role']??'', ['admin','superadmin'], true)) return true;
    $caps=$_SESSION['user']['capabilities']??$_SESSION['user']['plugin_capabilities']['commerce']??[];
    return is_array($caps) && (in_array($capability,$caps,true) || (in_array($capability,['view_commerce_stats','view_payment_stats'],true)&&(in_array('view_commerce_stats',$caps,true)||in_array('view_payment_stats',$caps,true))) || (in_array($capability,['manage_commerce','manage_payments'],true)&&(in_array('manage_commerce',$caps,true)||in_array('manage_payments',$caps,true))));
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
    return commerce_user_has_capability('view_commerce_stats')||commerce_user_has_capability('manage_commerce');
}

function commerce_can_view_stats(): bool
{
    return commerce_can_view_activity();
}

function commerce_require_stats_capability(): void { if (!commerce_can_view_stats()) { http_response_code(403); echo json_encode(['status'=>'error','message'=>'Forbidden']); exit; } }
function commerce_can_view_orders(): bool { return commerce_user_has_capability('view_commerce_orders')||commerce_user_has_capability('manage_commerce'); }

function commerce_get_setting(string $key, $default = null)
{
    if (function_exists('get_plugin_setting')) {
        $val = get_plugin_setting('commerce', $key, null);
        if ($val !== null) {
            return $val;
        }
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

    register_automation_block('trigger', 'commerce_order_receipt', [
        'name' => 'Order Receipt (Customer)',
        'description' => 'Fires when an order is paid — build a customer receipt email. Payload includes lines, totals, tax, and customer_email.',
        'category' => 'Commerce',
        'schema' => [],
        'handler' => function ($config, $context) {
            return true;
        },
    ]);

    register_automation_block('trigger', 'commerce_order_admin_notify', [
        'name' => 'Order Notification (Admin)',
        'description' => 'Fires when an order is paid — notify the site admin of a new order. Same order payload as the receipt trigger.',
        'category' => 'Commerce',
        'schema' => [],
        'handler' => function ($config, $context) {
            return true;
        },
    ]);

    register_automation_block('trigger', 'commerce_order_refunded', [
        'name' => 'Order Refunded',
        'description' => 'Fires when an order receives a full or partial refund',
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

    register_automation_block('trigger', 'commerce_subscription_past_due', [
        'name' => 'Subscription Past Due',
        'description' => 'Fires when a subscription is marked past_due from a provider event',
        'category' => 'Commerce',
        'schema' => [],
        'handler' => function ($config, $context) {
            return true;
        },
    ]);

    register_automation_block('trigger', 'commerce_subscription_canceled', [
        'name' => 'Subscription Canceled',
        'description' => 'Fires when a subscription is canceled (admin action or provider event)',
        'category' => 'Commerce',
        'schema' => [],
        'handler' => function ($config, $context) {
            return true;
        },
    ]);

    register_automation_block('trigger', 'commerce_subscription_renewed', [
        'name' => 'Subscription Renewed',
        'description' => 'Fires when a subscription renewal payment succeeds',
        'category' => 'Commerce',
        'schema' => [],
        'handler' => function ($config, $context) {
            return true;
        },
    ]);

    register_automation_block('trigger', 'commerce_subscription_payment_failed', [
        'name' => 'Subscription Payment Failed',
        'description' => 'Fires when a subscription payment fails (dunning)',
        'category' => 'Commerce',
        'schema' => [],
        'handler' => function ($config, $context) {
            return true;
        },
    ]);

    register_automation_block('trigger', 'commerce_subscription_recovered', [
        'name' => 'Subscription Recovered',
        'description' => 'Fires when a past_due subscription payment succeeds',
        'category' => 'Commerce',
        'schema' => [],
        'handler' => function ($config, $context) {
            return true;
        },
    ]);

    register_automation_block('trigger', 'commerce_subscription_upgraded', [
        'name' => 'Subscription Upgraded',
        'description' => 'Fires when a subscription plan is changed to another purchase option',
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
    require_once $dir . '/includes/CommerceSchema.php';
    require_once $dir . '/includes/CommerceFieldtypeProfileStore.php';
    require_once $dir . '/includes/CommerceCatalog.php';
    require_once $dir . '/includes/CommerceCartSession.php';
    require_once $dir . '/includes/CommerceOrderStore.php';
    require_once $dir . '/includes/CommerceSubscriptionStore.php';
    require_once $dir . '/includes/CommerceSubscriptionService.php';
    require_once $dir . '/includes/SubscriptionUpgradeService.php';
    require_once $dir . '/includes/CommerceDunningNotifier.php';
    require_once $dir . '/includes/DiscountEngine.php';
    require_once $dir . '/includes/GiftCardStore.php';
    require_once $dir . '/includes/CommerceQuoteStore.php';
    require_once $dir . '/includes/CommerceAnalyticsService.php';
    require_once $dir . '/includes/CommerceCheckoutService.php';
    require_once $dir . '/includes/CatalogIndexer.php';
    require_once $dir . '/includes/PurchaseMappingService.php';
    require_once $dir . '/includes/FulfillmentAdapterInterface.php';
    require_once $dir . '/includes/ManualFulfillmentAdapter.php';
    require_once $dir . '/includes/ShipStationStubAdapter.php';
    require_once $dir . '/includes/ImportService.php';
    require_once $dir . '/includes/ImportHttpClient.php';
    require_once $dir . '/includes/CommerceImportCredentialStore.php';
    require_once $dir . '/includes/ShopifyImportAdapter.php';
    require_once $dir . '/includes/WooImportAdapter.php';
    require_once $dir . '/includes/CommerceLiveImportService.php';
    require_once $dir . '/includes/CommerceSchema.php';
    require_once $dir . '/includes/CommerceBundleSchema.php';
    require_once $dir . '/includes/EventRegistrationStore.php';
    require_once $dir . '/includes/DigitalDeliveryStore.php';
    require_once $dir . '/includes/PostPurchaseRouter.php';
    $loaded = true;
}

function commerce_profile_settings_for_type(string $typeKey): array
{
    commerce_bootstrap_cart_services();
    $store = new CommerceFieldtypeProfileStore();
    return $store->getSettingsForTypeKey($typeKey);
}

function commerce_register_fieldtype_profiles(): void
{
    if (!function_exists('register_plugin_fieldtype') || !commerce_plugin_active()) {
        return;
    }

    if (!class_exists('Birkly\FieldTypes\BaseField')) {
        require_once dirname(__DIR__, 2) . '/fieldtypes/BaseField.php';
    }
    require_once commerce_plugin_dir() . '/includes/CommerceSchema.php';
    require_once commerce_plugin_dir() . '/includes/CommerceFieldtypeProfileStore.php';
    require_once commerce_plugin_dir() . '/fieldtypes/Commerce/Commerce.php';

    $pluginId = commerce_active_plugin_id();
    $store = new CommerceFieldtypeProfileStore();

    global $haru_plugin_fieldtypes;
    if (isset($haru_plugin_fieldtypes) && function_exists('unregister_plugin_fieldtype')) {
        $profileTypeKeys = array_map(fn ($p) => $p['type_key'] ?? 'commerce', $store->listProfiles());
        foreach (array_keys($haru_plugin_fieldtypes) as $type) {
            if (($type === 'commerce' || in_array($type, $profileTypeKeys, true))
                && (($haru_plugin_fieldtypes[$type]['plugin_id'] ?? '') === $pluginId)) {
                unregister_plugin_fieldtype($type, $pluginId);
            }
        }
    }

    foreach ($store->listProfiles() as $profile) {
        $typeKey = $profile['type_key'] ?? 'commerce';
        register_plugin_fieldtype(
            $typeKey,
            'Birkly\\FieldTypes\\Commerce\\Commerce',
            $pluginId,
            [
                'label' => $profile['name'] ?? ucwords(str_replace('_', ' ', $typeKey)),
                'description' => 'Commerce field profile',
                'profile_id' => $profile['id'] ?? null,
            ]
        );
    }

    require_once commerce_plugin_dir() . '/fieldtypes/CommerceBundle/CommerceBundle.php';
    register_plugin_fieldtype(
        'commerce_bundle',
        'Birkly\\FieldTypes\\CommerceBundle\\CommerceBundle',
        $pluginId,
        [
            'label' => 'Commerce Bundle',
            'description' => 'Bundle of catalog entries with bundle pricing rules',
        ]
    );
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
