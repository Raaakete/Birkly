<?php
/**
 * Shared payment_shelf access pattern for Events plugin (P6.2-P6.3-M0).
 */

class EventsConsumerPaymentHelper
{
    public static function getShelf(): ?PaymentShelf
    {
        if (!function_exists('get_plugin_service')) {
            return null;
        }
        $shelf = get_plugin_service('payment_shelf', false, 'events');
        if ($shelf instanceof PaymentShelf) {
            return $shelf;
        }
        if (function_exists('birkly_is_plugin_active') && birkly_is_plugin_active('commerce')) {
            if (function_exists('birkly_plugin_require')) {
                birkly_plugin_require('commerce', 'init.php');
            }
            if (function_exists('commerce_bootstrap')) {
                commerce_bootstrap();
            }
        }
        return get_plugin_service('payment_shelf', false, 'events');
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
