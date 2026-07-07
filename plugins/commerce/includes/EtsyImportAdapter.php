<?php
/**
 * Etsy Open API v3 listings import adapter (P14-M4).
 */

if (!class_exists('ImportHttpClient')) {
    require_once __DIR__ . '/ImportHttpClient.php';
}

class EtsyImportAdapter
{
    public const PLATFORM_ID = 'etsy';
    public const API_BASE = 'https://openapi.etsy.com/v3/application';
    public const PAGE_LIMIT = 25;
    public const MAX_PAGES = 100;

    private ImportHttpClient $http;

    public function __construct(?ImportHttpClient $http = null)
    {
        $this->http = $http ?? new ImportHttpClient();
    }

    /** @param array<string, mixed> $credentials */
    public function testConnection(array $credentials): array
    {
        $shopId = trim((string) ($credentials['shop_id'] ?? ''));
        $token = trim((string) ($credentials['access_token'] ?? ''));
        $apiKey = trim((string) ($credentials['api_key'] ?? ''));
        if ($shopId === '' || $token === '' || $apiKey === '') {
            return ['ok' => false, 'error' => 'Shop ID, API key, and access token are required'];
        }

        $url = self::API_BASE . '/shops/' . rawurlencode($shopId);
        $response = $this->http->request('GET', $url, ['headers' => $this->authHeaders($apiKey, $token)]);
        if (!($response['ok'] ?? false)) {
            return ['ok' => false, 'error' => $response['error'] ?? 'Connection failed'];
        }

        $shopName = is_array($response['data'] ?? null)
            ? (string) ($response['data']['shop_name'] ?? $shopId)
            : $shopId;

        return ['ok' => true, 'shop_name' => $shopName];
    }

    /** @param array<string, mixed> $credentials @param array<string, mixed> $options */
    public function fetchProductRows(array $credentials, array $options = []): array
    {
        $shopId = trim((string) ($credentials['shop_id'] ?? ''));
        $token = trim((string) ($credentials['access_token'] ?? ''));
        $apiKey = trim((string) ($credentials['api_key'] ?? ''));
        if ($shopId === '' || $token === '' || $apiKey === '') {
            return ['ok' => false, 'error' => 'Shop ID, API key, and access token are required'];
        }

        $maxPages = max(1, min(self::MAX_PAGES, (int) ($options['max_pages'] ?? self::MAX_PAGES)));
        $rows = [];
        $offset = 0;
        $pagesFetched = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $query = 'limit=' . self::PAGE_LIMIT . '&offset=' . $offset;
            $url = self::API_BASE . '/shops/' . rawurlencode($shopId) . '/listings/active?' . $query;
            $response = $this->http->request('GET', $url, ['headers' => $this->authHeaders($apiKey, $token)]);
            $pagesFetched++;

            if (!($response['ok'] ?? false)) {
                return ['ok' => false, 'error' => $response['error'] ?? 'Failed to fetch listings', 'rows' => $rows, 'product_count' => count($rows), 'pages_fetched' => $pagesFetched];
            }

            $results = is_array($response['data']['results'] ?? null) ? $response['data']['results'] : [];
            foreach ($results as $listing) {
                if (is_array($listing)) {
                    $rows[] = $this->mapListingToRow($listing);
                }
            }

            $count = (int) ($response['data']['count'] ?? count($results));
            $offset += count($results);
            if ($offset >= $count || count($results) < self::PAGE_LIMIT) {
                break;
            }
        }

        return ['ok' => true, 'rows' => $rows, 'product_count' => count($rows), 'pages_fetched' => $pagesFetched];
    }

    /** @param array<string, mixed> $listing */
    private function mapListingToRow(array $listing): array
    {
        $title = trim((string) ($listing['title'] ?? 'Untitled'));
        $listingId = (string) ($listing['listing_id'] ?? '');
        $sku = trim((string) ($listing['sku'] ?? ''));
        if ($sku === '' && $listingId !== '') {
            $sku = 'ETSY-' . $listingId;
        }
        $price = (float) ($listing['price']['amount'] ?? 0) / max(1, (int) ($listing['price']['divisor'] ?? 100));

        return [
            'title' => $title,
            'slug' => $this->slugify($title),
            'sku' => $sku,
            'description' => strip_tags((string) ($listing['description'] ?? '')),
            'commerce' => [
                'fulfillment' => 'ship',
                'purchase_options' => [[
                    'id' => 'default', 'label' => 'Default', 'billing' => 'one_time',
                    'price_cents' => (int) round($price * 100), 'currency' => 'usd', 'interval' => null,
                ]],
                'quantity_available' => (int) ($listing['quantity'] ?? 0),
                'sku' => $sku,
            ],
        ];
    }

    /** @return array<string, string> */
    private function authHeaders(string $apiKey, string $token): array
    {
        return ['x-api-key' => $apiKey, 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $text) ?? '', '-'));
        return $slug !== '' ? $slug : 'listing';
    }
}
