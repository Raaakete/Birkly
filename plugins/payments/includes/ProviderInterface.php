<?php
/**
 * Payment provider adapter interface.
 */

interface PaymentProviderInterface
{
    public function getId(): string;

    public function getName(): string;

    /** @return array Capability matrix: regions, currencies, methods, features */
    public function getCapabilities(): array;

    public function testConnection(array $credentials): array;

    public function createPayment(array $credentials, array $params): array;

    public function capturePayment(array $credentials, string $paymentId, array $params = []): array;

    public function refundPayment(array $credentials, string $paymentId, ?int $amountCents = null, array $params = []): array;

    public function getPaymentStatus(array $credentials, string $paymentId): array;

    public function createSubscription(array $credentials, array $params): array;

    public function cancelSubscription(array $credentials, string $subscriptionId): array;

    public function verifyWebhookSignature(array $credentials, string $payload, array $headers): bool;

    public function processWebhook(array $credentials, array $payload, array $headers): array;
}
