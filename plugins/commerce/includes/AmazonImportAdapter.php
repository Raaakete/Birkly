<?php
/**
 * Amazon Selling Partner API listings import adapter (P17-M3).
 */

if (!class_exists('ImportHttpClient')) {
    require_once __DIR__ . '/ImportHttpClient.php';
}

class AmazonImportAdapter
{
    public const PLATFORM_ID = 'amazon';
    public const API_BASE = 'https://sellingpartnerapi-na.amazon.com';
    public const PAGE_LIMIT = 20;
    public const MAX_PAGES = 50;

    private ImportHttpClient $http;

    public function __construct(?ImportHttpClient $http = null)
    {
        $this->http = $http ?? new ImportHttpClient();
    }

    /** @param array<string, mixed> $credentials */
    public function testConnection(array $credentials): array
    {
        $sellerId = trim((string) ($credentials['seller_id'] ?? ''));
        $marketplaceId = trim((string) ($credentials['marketplace_id'] ?? ''));
        $token = trim((string) ($credentials['refresh_token'] ?? ''));
        if ($sellerId === '' || $marketplaceId === '' || $token === '') {
            return ['ok' => false, 'error' => 'Seller ID, marketplace ID, and refresh token are required'];
        }

        $url = self::API_BASE . '/catalog/2022-04-01/items?marketplaceIds=' . rawurlencode($marketplaceId) . '&pageSize=1';
        $response = $this->http->request('GET', $url, ['headers' => $this->authHeaders($token)]);
        if (!($response['ok'] ?? false)) {
            return ['ok' => false, 'error' => $response['error'] ?? 'Connection failed'];
        }

        return ['ok' => true, 'shop_name' => 'Amazon Seller ' . $sellerId];
    }

    /** @param array<string, mixed> $credentials @param array<string, mixed> $options */
    public function fetchProductRows(array $credentials, array $options = []): array
    {
        $sellerId = trim((string) ($credentials['seller_id'] ?? ''));
        $marketplaceId = trim((string) ($credentials['marketplace_id'] ?? ''));
        $token = trim((string) ($credentials['refresh_token'] ?? ''));
        if ($sellerId === '' || $marketplaceId === '' || $token === '') {
            return ['ok' => false, 'error' => 'Seller ID, marketplace ID, and refresh token are required'];
        }

        $maxPages = max(1, min(self::MAX_PAGES, (int) ($options['max_pages'] ?? self::MAX_PAGES)));
        $rows = [];
        $nextToken = null;
        $pagesFetched = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $query = 'marketplaceIds=' . rawurlencode($marketplaceId) . '&pageSize=' . self::PAGE_LIMIT;
            if ($nextToken !== null && $nextToken !== '') {
                $query .= '&pageToken=' . rawurlencode($nextToken);
            }
            $url = self::API_BASE . '/catalog/2022-04-01/items?' . $query;
            $response = $this->http->request('GET', $url, ['headers' => $this->authHeaders($token)]);
            $pagesFetched++;

            if (!($response['ok'] ?? false)) {
                return ['ok' => false, 'error' => $response['error'] ?? 'Failed to fetch listings', 'rows' => $rows, 'product_count' => count($rows), 'pages_fetched' => $pagesFetched];
            }

            $items = is_array($response['data']['items'] ?? null) ? $response['data']['items'] : [];
            foreach ($items as $item) {
                if (is_array($item)) {
                    $rows[] = $this->mapItemToRow($item, $sellerId);
                }
            }

            $nextToken = $response['data']['pagination']['nextToken'] ?? null;
            if ($nextToken === null || $nextToken === '' || count($items) < self::PAGE_LIMIT) {
                break;
            }
        }

        return ['ok' => true, 'rows' => $rows, 'product_count' => count($rows), 'pages_fetched' => $pagesFetched];
    }

    /** @param array<string, mixed> $item */
    private function mapItemToRow(array $item, string $sellerId): array
    {
        $asin = (string) ($item['asin'] ?? '');
        $summaries = is_array($item['summaries'][0] ?? null) ? $item['summaries'][0] : [];
        $title = trim((string) ($summaries['itemName'] ?? ($item['title'] ?? 'Untitled')));
        $sku = trim((string) ($item['sku'] ?? ''));
        if ($sku === '' && $asin !== '') {
            $sku = 'AMZ-' . $asin;
        }
        $price = (float) ($item['offers'][0]['price']['amount'] ?? ($summaries['listPrice']['amount'] ?? 0));

        return [
            'title' => $title,
            'slug' => $this->slugify($title !== '' ? $title : $asin),
            'sku' => $sku,
            'description' => strip_tags((string) ($summaries['brand'] ?? '')),
            'commerce' => [
                'fulfillment' => 'ship',
                'purchase_options' => [[
                    'id' => 'default', 'label' => 'Default', 'billing' => 'one_time',
                    'price_cents' => (int) round($price * 100), 'currency' => 'usd', 'interval' => null,
                ]],
                'quantity_available' => (int) ($item['inventory']['quantity'] ?? 0),
                'sku' => $sku,
            ],
        ];
    }

    /** @return array<string, string> */
    private function authHeaders(string $token): array
    {
        return ['x-amz-access-token' => $token, 'Accept' => 'application/json'];
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $text) ?? '', '-'));

        return $slug !== '' ? $slug : 'listing';
    }
}
