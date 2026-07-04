<?php
/**
 * Webhook router — per-provider endpoint, signature verify, idempotency log.
 */

class PaymentsWebhookRouter
{
    private PaymentsProviderResolver $resolver;
    private PaymentsCredentialStore $store;
    private PaymentsActivityLog $activityLog;
    private string $idempotencyFile;

    public function __construct(
        PaymentsProviderResolver $resolver,
        PaymentsCredentialStore $store,
        PaymentsActivityLog $activityLog
    ) {
        $this->resolver = $resolver;
        $this->store = $store;
        $this->activityLog = $activityLog;
        $this->idempotencyFile = $store->getDataDir() . '/webhooks/idempotency.json';
        if (!is_dir(dirname($this->idempotencyFile))) {
            mkdir(dirname($this->idempotencyFile), 0755, true);
        }
        if (!is_file($this->idempotencyFile)) {
            file_put_contents($this->idempotencyFile, json_encode([]));
        }
    }

    public function handle(string $providerId, string $payload, array $headers): array
    {
        $provider = $this->resolver->get($providerId);
        if (!$provider) {
            return ['ok' => false, 'status' => 404, 'error' => 'Unknown provider'];
        }

        $connection = $this->store->getActiveConnectionForProvider($providerId);
        if (!$connection) {
            return ['ok' => false, 'status' => 404, 'error' => 'No active connection for provider'];
        }

        $credentials = $connection['credentials'] ?? [];
        if (!$provider->verifyWebhookSignature($credentials, $payload, $headers)) {
            $this->activityLog->append([
                'type' => 'webhook',
                'provider' => $providerId,
                'status' => 'rejected',
                'message' => 'Webhook signature verification failed',
            ]);
            return ['ok' => false, 'status' => 401, 'error' => 'Invalid signature'];
        }

        $parsed = json_decode($payload, true);
        if (!is_array($parsed)) {
            $parsed = ['raw' => $payload];
        }

        $eventId = $this->extractEventId($providerId, $parsed, $headers);
        if ($eventId && $this->isDuplicate($eventId)) {
            return ['ok' => true, 'status' => 200, 'duplicate' => true, 'event_id' => $eventId];
        }

        $result = $provider->processWebhook($credentials, $parsed, $headers);

        if ($eventId) {
            $this->recordIdempotency($eventId);
        }

        $status = $result['status'] ?? 'received';
        $this->activityLog->append([
            'type' => 'webhook',
            'provider' => $providerId,
            'status' => $status,
            'payment_id' => $result['payment_id'] ?? null,
            'source_plugin' => 'payments',
            'source_ref' => $eventId,
            'message' => $result['message'] ?? 'Webhook processed',
            'metadata' => ['event_type' => $result['event_type'] ?? null],
        ]);

        $this->broadcastPaymentEvent($result);

        return [
            'ok' => true,
            'status' => 200,
            'result' => $result,
            'event_id' => $eventId,
        ];
    }

    private function extractEventId(string $providerId, array $payload, array $headers): ?string
    {
        switch ($providerId) {
            case 'stripe':
                return $payload['id'] ?? null;
            case 'mollie':
                return $payload['id'] ?? null;
            case 'paypal':
                return $payload['id'] ?? ($payload['event_version'] ?? null);
            default:
                return $payload['id'] ?? $payload['event_id'] ?? null;
        }
    }

    private function isDuplicate(string $eventId): bool
    {
        $log = $this->readIdempotencyLog();
        return isset($log[$eventId]);
    }

    private function recordIdempotency(string $eventId): void
    {
        $log = $this->readIdempotencyLog();
        $log[$eventId] = date('c');
        // Keep last 5000 entries
        if (count($log) > 5000) {
            $log = array_slice($log, -5000, null, true);
        }
        file_put_contents($this->idempotencyFile, json_encode($log, JSON_PRETTY_PRINT));
    }

    private function readIdempotencyLog(): array
    {
        if (!is_file($this->idempotencyFile)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($this->idempotencyFile), true);
        return is_array($data) ? $data : [];
    }

    private function broadcastPaymentEvent(array $result): void
    {
        $eventType = $result['event_type'] ?? '';
        $status = $result['status'] ?? '';

        $automationEvents = [
            'payment_intent.succeeded' => 'payment_succeeded',
            'payment.succeeded' => 'payment_succeeded',
            'paid' => 'payment_succeeded',
            'succeeded' => 'payment_succeeded',
            'payment_intent.payment_failed' => 'payment_failed',
            'failed' => 'payment_failed',
        ];

        $eventName = $automationEvents[$eventType] ?? $automationEvents[$status] ?? null;
        if (!$eventName) {
            return;
        }

        $context = [
            'payment_id' => $result['payment_id'] ?? null,
            'provider' => $result['provider'] ?? null,
            'status' => $status,
            'amount_cents' => $result['amount_cents'] ?? null,
            'currency' => $result['currency'] ?? null,
        ];

        if (function_exists('broadcast_event')) {
            broadcast_event($eventName, $context, 'payments');
        }
        if (function_exists('broadcast_plugin_event')) {
            broadcast_plugin_event($eventName, $context);
        }
        if (function_exists('enqueue_automation')) {
            enqueue_automation($eventName, $context);
        }
    }

    /** For tests */
    public function clearIdempotencyLog(): void
    {
        file_put_contents($this->idempotencyFile, json_encode([]));
    }

    public function hasProcessed(string $eventId): bool
    {
        return $this->isDuplicate($eventId);
    }
}
