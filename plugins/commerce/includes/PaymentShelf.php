<?php
/**
 * Payment shelf — public API for consumer plugins via get_plugin_service('payment_shelf').
 */

class PaymentShelf
{
    private PaymentsProviderResolver $resolver;
    private PaymentsCredentialStore $store;
    private PaymentsActivityLog $activityLog;
    private ?PaymentsWebhookRouter $webhookRouter = null;

    public function __construct(
        PaymentsProviderResolver $resolver,
        PaymentsCredentialStore $store,
        PaymentsActivityLog $activityLog
    ) {
        $this->resolver = $resolver;
        $this->store = $store;
        $this->activityLog = $activityLog;
    }

    public function setWebhookRouter(PaymentsWebhookRouter $router): void
    {
        $this->webhookRouter = $router;
    }

    public function isConfigured(): bool
    {
        return count($this->store->listConfiguredProviders()) > 0;
    }

    public function listProviders(): array
    {
        return $this->resolver->listAvailableProviders([]);
    }

    public function listAvailableProviders(array $filters = []): array
    {
        return $this->resolver->listAvailableProviders($filters);
    }

    public function resolveProvider(array $criteria): ?string
    {
        return $this->resolver->resolveProvider($criteria);
    }

    public function getProviderCapabilities(string $provider): array
    {
        $impl = $this->resolver->get($provider);
        return $impl ? $impl->getCapabilities() : [];
    }

    public function createPayment(string $provider, array $params, array $context = []): array
    {
        if ($provider === '' || $provider === 'auto') {
            $provider = $this->resolveProvider(array_merge($params, $context)) ?? '';
        }
        if ($provider === '') {
            return $this->error('No payment provider available');
        }

        $impl = $this->resolver->get($provider);
        $connection = $this->store->getActiveConnectionForProvider($provider);
        if (!$impl || !$connection) {
            return $this->error('Provider not configured: ' . $provider);
        }

        $credentials = $connection['credentials'] ?? [];
        $result = $impl->createPayment($credentials, $params);

        $this->activityLog->append([
            'type' => 'payment',
            'provider' => $provider,
            'status' => $result['status'] ?? ($result['ok'] ? 'pending' : 'failed'),
            'source_plugin' => $context['source_plugin'] ?? null,
            'source_ref' => $context['source_ref'] ?? null,
            'payment_id' => $result['payment_id'] ?? null,
            'amount_cents' => $params['amount_cents'] ?? null,
            'currency' => $params['currency'] ?? null,
            'message' => $result['ok'] ? 'Payment created' : ($result['error'] ?? 'Payment failed'),
        ]);

        return array_merge(['provider' => $provider], $result);
    }

    public function capturePayment(string $provider, string $paymentId, array $params = [], array $context = []): array
    {
        $impl = $this->resolver->get($provider);
        $connection = $this->store->getActiveConnectionForProvider($provider);
        if (!$impl || !$connection) {
            return $this->error('Provider not configured');
        }

        $result = $impl->capturePayment($connection['credentials'] ?? [], $paymentId, $params);

        $this->activityLog->append([
            'type' => 'capture',
            'provider' => $provider,
            'status' => $result['status'] ?? ($result['ok'] ? 'captured' : 'failed'),
            'source_plugin' => $context['source_plugin'] ?? null,
            'source_ref' => $context['source_ref'] ?? null,
            'payment_id' => $paymentId,
            'message' => $result['ok'] ? 'Payment captured' : ($result['error'] ?? 'Capture failed'),
        ]);

        return array_merge(['provider' => $provider, 'payment_id' => $paymentId], $result);
    }

    public function refundPayment(string $provider, string $paymentId, ?int $amountCents = null, array $context = []): array
    {
        $impl = $this->resolver->get($provider);
        $connection = $this->store->getActiveConnectionForProvider($provider);
        if (!$impl || !$connection) {
            return $this->error('Provider not configured');
        }

        $caps = $impl->getCapabilities();
        if (empty($caps['features']['refunds'])) {
            return $this->error('Provider does not support refunds');
        }

        $result = $impl->refundPayment($connection['credentials'] ?? [], $paymentId, $amountCents);

        $this->activityLog->append([
            'type' => 'refund',
            'provider' => $provider,
            'status' => $result['status'] ?? ($result['ok'] ? 'refunded' : 'failed'),
            'source_plugin' => $context['source_plugin'] ?? null,
            'source_ref' => $context['source_ref'] ?? null,
            'payment_id' => $paymentId,
            'amount_cents' => $amountCents,
            'message' => $result['ok'] ? 'Refund processed' : ($result['error'] ?? 'Refund failed'),
        ]);

        return array_merge(['provider' => $provider, 'payment_id' => $paymentId], $result);
    }

    public function getPaymentStatus(string $provider, string $paymentId, array $context = []): array
    {
        $impl = $this->resolver->get($provider);
        $connection = $this->store->getActiveConnectionForProvider($provider);
        if (!$impl || !$connection) {
            return $this->error('Provider not configured');
        }

        return array_merge(
            ['provider' => $provider, 'payment_id' => $paymentId],
            $impl->getPaymentStatus($connection['credentials'] ?? [], $paymentId)
        );
    }

    public function createSubscription(string $provider, array $params, array $context = []): array
    {
        $impl = $this->resolver->get($provider);
        $connection = $this->store->getActiveConnectionForProvider($provider);
        if (!$impl || !$connection) {
            return $this->error('Provider not configured');
        }

        $caps = $impl->getCapabilities();
        if (empty($caps['features']['subscriptions'])) {
            return $this->error('Provider does not support subscriptions');
        }

        $result = $impl->createSubscription($connection['credentials'] ?? [], $params);

        $this->activityLog->append([
            'type' => 'subscription',
            'provider' => $provider,
            'status' => $result['status'] ?? ($result['ok'] ? 'active' : 'failed'),
            'source_plugin' => $context['source_plugin'] ?? null,
            'source_ref' => $context['source_ref'] ?? null,
            'payment_id' => $result['subscription_id'] ?? null,
            'message' => $result['ok'] ? 'Subscription created' : ($result['error'] ?? 'Subscription failed'),
        ]);

        return array_merge(['provider' => $provider], $result);
    }

    public function cancelSubscription(string $provider, string $subscriptionId, array $context = []): array
    {
        $impl = $this->resolver->get($provider);
        $connection = $this->store->getActiveConnectionForProvider($provider);
        if (!$impl || !$connection) {
            return $this->error('Provider not configured');
        }

        $result = $impl->cancelSubscription($connection['credentials'] ?? [], $subscriptionId);

        $this->activityLog->append([
            'type' => 'subscription',
            'provider' => $provider,
            'status' => $result['status'] ?? ($result['ok'] ? 'cancelled' : 'failed'),
            'source_plugin' => $context['source_plugin'] ?? null,
            'source_ref' => $context['source_ref'] ?? null,
            'payment_id' => $subscriptionId,
            'message' => $result['ok'] ? 'Subscription cancelled' : ($result['error'] ?? 'Cancel failed'),
        ]);

        return array_merge(['provider' => $provider, 'subscription_id' => $subscriptionId], $result);
    }

    public function updateSubscription(string $provider, string $subscriptionId, array $params, array $context = []): array
    {
        $impl = $this->resolver->get($provider);
        $connection = $this->store->getActiveConnectionForProvider($provider);
        if (!$impl || !$connection) {
            return $this->error('Provider not configured');
        }

        $caps = $impl->getCapabilities();
        if (empty($caps['features']['subscriptions'])) {
            return $this->error('Provider does not support subscriptions');
        }

        $result = $impl->updateSubscription($connection['credentials'] ?? [], $subscriptionId, $params);

        $this->activityLog->append([
            'type' => 'subscription',
            'provider' => $provider,
            'status' => $result['status'] ?? ($result['ok'] ? 'updated' : 'failed'),
            'source_plugin' => $context['source_plugin'] ?? null,
            'source_ref' => $context['source_ref'] ?? null,
            'payment_id' => $subscriptionId,
            'message' => $result['ok'] ? 'Subscription plan updated' : ($result['error'] ?? 'Plan change failed'),
        ]);

        return array_merge(['provider' => $provider, 'subscription_id' => $subscriptionId], $result);
    }

    public function retrySubscriptionPayment(string $provider, string $subscriptionId, array $params = [], array $context = []): array
    {
        $impl = $this->resolver->get($provider);
        $connection = $this->store->getActiveConnectionForProvider($provider);
        if (!$impl || !$connection) {
            return $this->error('Provider not configured');
        }

        $result = $impl->retrySubscriptionPayment($connection['credentials'] ?? [], $subscriptionId, $params);

        $this->activityLog->append([
            'type' => 'subscription',
            'provider' => $provider,
            'status' => $result['status'] ?? ($result['ok'] ? 'retry' : 'failed'),
            'source_plugin' => $context['source_plugin'] ?? null,
            'source_ref' => $context['source_ref'] ?? null,
            'payment_id' => $subscriptionId,
            'message' => $result['ok'] ? 'Subscription payment retry' : ($result['error'] ?? 'Retry failed'),
        ]);

        return array_merge(['provider' => $provider, 'subscription_id' => $subscriptionId], $result);
    }

    public function testConnection(string $provider, array $credentials): array
    {
        $impl = $this->resolver->get($provider);
        if (!$impl) {
            return $this->error('Unknown provider');
        }
        return $impl->testConnection($credentials);
    }

    public function getWebhookUrl(string $provider): string
    {
        $base = function_exists('birkly_base_url') ? birkly_base_url() : '';
        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
            $base = $scheme . '://' . $host;
        }
        return rtrim($base, '/') . '/api/index.php?route=payments_webhook&provider=' . urlencode($provider);
    }

    private function error(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
