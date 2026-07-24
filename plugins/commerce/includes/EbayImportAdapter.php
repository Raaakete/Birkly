<?php
/**
 * eBay Inventory API import adapter (P17-M3).
 */

if (!class_exists('ImportHttpClient')) {
    require_once __DIR__ . '/ImportHttpClient.php';
}

class EbayImportAdapter
{
    public const PLATFORM_ID = 'ebay';
    public const API_BASE = 'https://api.ebay.com/sell/inventory/v1';
    public const PAGE_LIMIT = 25;
    public const MAX_PAGES = 50;

    private ImportHttpClient $http;

    public function __construct(?ImportHttpClient $http = null)
    {
        $this->http = $http ?? new ImportHttpClient();
    }

    /** @param array<string, mixed> $credentials */
    public function testConnection(array $credentials): array
    {
        $token = trim((string) ($credentials['access_token'] ?? ''));
        $marketplaceId = trim((string) ($credentials['marketplace_id'] ?? 'EBAY_US'));
        if ($token === '') {
            return ['ok' => false, 'error' => 'OAuth access token is required'];
        }

        $url = self::API_BASE . '/inventory_item?limit=1';
        $response = $this->http->request('GET', $url, ['headers' => $this->authHeaders($token, $marketplaceId)]);
        if (!($response['ok'] ?? false)) {
            return ['ok' => false, 'error' => $response['error'] ?? 'Connection failed'];
        }

        return ['ok' => true, 'shop_name' => 'eBay ' . $marketplaceId];
    }

    /** @param array<string, mixed> $credentials @param array<string, mixed> $options */
    public function fetchProductRows(array $credentials, array $options = []): array
    {
        $token = trim((string) ($credentials['access_token'] ?? ''));
        $marketplaceId = trim((string) ($credentials['marketplace_id'] ?? 'EBAY_US'));
        if ($token === '') {
            return ['ok' => false, 'error' => 'OAuth access token is required'];
        }

        $maxPages = max(1, min(self::MAX_PAGES, (int) ($options['max_pages'] ?? self::MAX_PAGES)));
        $rows = [];
        $offset = 0;
        $pagesFetched = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $url = self::API_BASE . '/inventory_item?limit=' . self::PAGE_LIMIT . '&offset=' . $offset;
            $response = $this->http->request('GET', $url, ['headers' => $this->authHeaders($token, $marketplaceId)]);
            $pagesFetched++;

            if (!($response['ok'] ?? false)) {
                return ['ok' => false, 'error' => $response['error'] ?? 'Failed to fetch inventory', 'rows' => $rows, 'product_count' => count($rows), 'pages_fetched' => $pagesFetched];
            }

            $items = is_array($response['data']['inventoryItems'] ?? null) ? $response['data']['inventoryItems'] : [];
            foreach ($items as $item) {
                if (is_array($item)) {
                    $rows[] = $this->mapItemToRow($item);
                }
            }

            $total = (int) ($response['data']['total'] ?? count($items));
            $offset += count($items);
            if ($offset >= $total || count($items) < self::PAGE_LIMIT) {
                break;
            }
        }

        return ['ok' => true, 'rows' => $rows, 'product_count' => count($rows), 'pages_fetched' => $pagesFetched];
    }

    /** @param array<string, mixed> $item */
    private function mapItemToRow(array $item): array
    {
        $sku = trim((string) ($item['sku'] ?? ''));
        $product = is_array($item['product'] ?? null) ? $item['product'] : [];
        $title = trim((string) ($product['title'] ?? ($sku !== '' ? $sku : 'Untitled')));
        if ($sku === '') {
            $sku = 'EBAY-' . substr(md5($title), 0, 8);
        }
        $price = (float) ($item['availability']['shipToLocationAvailability']['allocationByFormat']['fixedPrice']['value'] ?? 0);
        if ($price <= 0) {
            $price = (float) ($product['aspects']['Price'][0] ?? 0);
        }

        return [
            'title' => $title,
            'slug' => $this->slugify($title),
            'sku' => $sku,
            'description' => strip_tags((string) ($product['description'] ?? '')),
            'commerce' => [
                'fulfillment' => 'ship',
                'purchase_options' => [[
                    'id' => 'default', 'label' => 'Default', 'billing' => 'one_time',
                    'price_cents' => (int) round($price * 100), 'currency' => 'usd', 'interval' => null,
                ]],
                'quantity_available' => (int) ($item['availability']['shipToLocationAvailability']['quantity'] ?? 0),
                'sku' => $sku,
            ],
        ];
    }

    /** @return array<string, string> */
    private function authHeaders(string $token, string $marketplaceId): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Language' => 'en-US',
            'X-EBAY-C-MARKETPLACE-ID' => $marketplaceId,
        ];
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $text) ?? '', '-'));

        return $slug !== '' ? $slug : 'listing';
    }
}
