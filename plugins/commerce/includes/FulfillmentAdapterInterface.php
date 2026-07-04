<?php
/**
 * Fulfillment adapter interface.
 */

interface FulfillmentAdapterInterface
{
    public function getId(): string;

    public function getName(): string;

    public function isConfigured(): bool;

    public function testConnection(): array;

    /** @param array<string, mixed> $order */
    public function pushOrder(array $order): array;
}
