<?php
/**
 * Payments plugin initialization — builds shelf, resolver, webhook router.
 */

function payments_plugin_dir(): string
{
    return __DIR__;
}

function payments_plugin_active(): bool
{
    if (!function_exists('birkly_is_plugin_active')) {
        require_once dirname(__DIR__, 2) . '/core/plugins.php';
    }
    return birkly_is_plugin_active('payments');
}

/** @return PaymentShelf|null */
function payments_bootstrap()
{
    static $shelf = null;
    if (!payments_plugin_active()) {
        return null;
    }
    if ($shelf !== null) {
        return $shelf;
    }

    $dir = payments_plugin_dir();
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

    $GLOBALS['payments_webhook_router'] = $webhookRouter;
    $GLOBALS['payments_credential_store'] = $store;
    $GLOBALS['payments_activity_log'] = $activityLog;

    return $shelf;
}

function payments_webhook_router(): ?PaymentsWebhookRouter
{
    payments_bootstrap();
    return $GLOBALS['payments_webhook_router'] ?? null;
}

function payments_require_admin_capability(string $capability): void
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

function payments_can_view_activity(): bool
{
    if (!isset($_SESSION['user'])) {
        return false;
    }
    $role = $_SESSION['user']['role'] ?? '';
    return in_array($role, ['admin', 'superadmin'], true);
}

function payments_can_manage(): bool
{
    return payments_can_view_activity();
}

function payments_can_view_stats(): bool
{
    return payments_can_view_activity();
}

function payments_init_automation_triggers(): void
{
    if (!function_exists('register_automation_block')) {
        return;
    }

    register_automation_block('trigger', 'payment_succeeded', [
        'name' => 'Payment Succeeded',
        'description' => 'Fires when a payment succeeds',
        'category' => 'Payments',
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
        'category' => 'Payments',
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
}

/**
 * Dashboard widgets for the admin home (opt-in via plugin settings).
 *
 * @return list<array<string, mixed>>
 */
function payments_dashboard_widgets(): array
{
    if (!payments_plugin_active() || !payments_can_view_stats()) {
        return [];
    }

    $enabled = function_exists('get_plugin_setting')
        ? (bool) get_plugin_setting('payments', 'enable_dashboard_widget', false)
        : false;
    if (!$enabled) {
        return [];
    }

    $roles = function_exists('get_plugin_setting')
        ? get_plugin_setting('payments', 'dashboard_widget_roles', ['admin'])
        : ['admin'];
    if (!is_array($roles)) {
        $roles = ['admin'];
    }

    $paymentsUrl = function_exists('admin_url') ? admin_url('payments') : '/admin/index.php?page=payments';

    return [[
        'id' => 'payments-stats',
        'title' => 'Payments',
        'roles' => $roles,
        'html' => '<div class="payments-dash-widget" id="payments-dash-widget">'
            . '<p class="payments-dash-loading">Loading payment stats…</p></div>'
            . '<p class="payments-dash-link"><a href="' . htmlspecialchars($paymentsUrl) . '">Manage payments →</a></p>',
    ]];
}
