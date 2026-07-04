<?php
/**
 * Shared payment_shelf access pattern for consumer plugins (P6.2-P6.3-M0).
 */

class ShoppingCartConsumerPaymentHelper
{
    public static function getShelf(): ?PaymentShelf
    {
        if (!function_exists('get_plugin_service')) {
            return null;
        }
        $shelf = get_plugin_service('payment_shelf', false, 'shopping_cart');
        if ($shelf instanceof PaymentShelf) {
            return $shelf;
        }
        $paymentsInit = dirname(__DIR__, 2) . '/payments/init.php';
        if (is_file($paymentsInit)) {
            require_once $paymentsInit;
            if (function_exists('payments_bootstrap')) {
                payments_bootstrap();
            }
        }
        return get_plugin_service('payment_shelf', false, 'shopping_cart');
    }

    public static function isPaymentsAvailable(): bool
    {
        $shelf = self::getShelf();
        return $shelf !== null && $shelf->isConfigured();
    }

    public static function registerPaymentEventHandlers(callable $onSucceeded, callable $onFailed): void
    {
        if (!function_exists('subscribe_to_event')) {
            return;
        }
        subscribe_to_event('payment_succeeded', function (array $data) use ($onSucceeded) {
            $onSucceeded($data);
        });
        subscribe_to_event('payment_failed', function (array $data) use ($onFailed) {
            $onFailed($data);
        });
    }
}
