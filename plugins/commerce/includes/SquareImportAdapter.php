<?php
/**
 * Square Catalog API import adapter (P14-M4).
 */

if (!class_exists('ImportHttpClient')) {
    require_once __DIR__ . '/ImportHttpClient.php';
}

class SquareImportAdapter
{
    public const PLATFORM_ID = 'square';
    public const API_BASE = 'https://connect.squareup.com/v2/catalog/list';
    public const PAGE_LIMIT = 100;
    public const MAX_PAGES = 100;

    private ImportHttpClient $http;

    public function __construct(?ImportHttpClient $http = null)
    {
        $this->http = $http ?? new ImportHttpClient();
    }

    /** @param array<string, mixed> $credentials */
    public function testConnection(array $credentials): array
    {
        $token = trim((string) ($credentials['access_token'] ?? ''));
        if ($token === '') {
            return ['ok' => false, 'error' => 'Access token is required'];
        }
        $response = $this->http->request('GET', self::API_BASE . '?types=ITEM', [
            'headers' => $this->authHeaders($token), 'timeout' => 20,
        ]);
        if (!($response['ok'] ?? false)) {
            return ['ok' => false, 'error' => $response['error'] ?? 'Connection failed'];
        }
        return ['ok' => true, 'shop_name' => 'Square Catalog'];
    }

    /** @param array<string, mixed> $credentials @param array<string, mixed> $options */
    public function fetchProductRows(array $credentials, array $options = []): array
    {
        $token = trim((string) ($credentials['access_token'] ?? ''));
        if ($token === '') {
            return ['ok' => false, 'error' => 'Access token is required'];
        }

        $maxPages = max(1, min(self::MAX_PAGES, (int) ($options['max_pages'] ?? self::MAX_PAGES)));
        $rows = [];
        $cursor = null;
        $pagesFetched = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $query = 'types=ITEM&limit=' . self::PAGE_LIMIT;
            if ($cursor !== null) {
                $query .= '&cursor=' . rawurlencode($cursor);
            }
            $response = $this->http->request('GET', self::API_BASE . '?' . $query, ['headers' => $this->authHeaders($token)]);
            $pagesFetched++;

            if (!($response['ok'] ?? false)) {
                return ['ok' => false, 'error' => $response['error'] ?? 'Failed to fetch catalog', 'rows' => $rows, 'product_count' => count($rows), 'pages_fetched' => $pagesFetched];
            }

            $objects = is_array($response['data']['objects'] ?? null) ? $response['data']['objects'] : [];
            foreach ($objects as $object) {
                if (!is_array($object) || ($object['type'] ?? '') !== 'ITEM') {
                    continue;
                }
                foreach ($this->mapItemToRows($object) as $row) {
                    $rows[] = $row;
                }
            }

            $cursor = $response['data']['cursor'] ?? null;
            if ($cursor === null || $cursor === '') {
                break;
            }
        }

        return ['ok' => true, 'rows' => $rows, 'product_count' => count($rows), 'pages_fetched' => $pagesFetched];
    }

    /** @param array<string, mixed> $object @return list<array<string, mixed>> */
    private function mapItemToRows(array $object): array
    {
        $itemData = is_array($object['item_data'] ?? null) ? $object['item_data'] : [];
        $title = trim((string) ($itemData['name'] ?? 'Untitled'));
        $itemId = (string) ($object['id'] ?? '');
        $variations = is_array($itemData['variations'] ?? null) ? $itemData['variations'] : [];
        $rows = [];

        if (empty($variations)) {
            return [$this->buildRow($title, $this->slugify($title), 'SQ-' . $itemId, 0)];
        }

        foreach ($variations as $variation) {
            if (!is_array($variation)) {
                continue;
            }
            $varData = is_array($variation['item_variation_data'] ?? null) ? $variation['item_variation_data'] : [];
            $sku = trim((string) ($varData['sku'] ?? ''));
            if ($sku === '') {
                $sku = 'SQ-' . ($variation['id'] ?? $itemId);
            }
            $priceMoney = is_array($varData['price_money'] ?? null) ? $varData['price_money'] : [];
            $label = trim((string) ($varData['name'] ?? $title));
            $rows[] = $this->buildRow($label, $this->slugify($label), $sku, (int) ($priceMoney['amount'] ?? 0));
        }

        return $rows;
    }

    private function buildRow(string $title, string $slug, string $sku, int $priceCents): array
    {
        return [
            'title' => $title, 'slug' => $slug, 'sku' => $sku, 'description' => '',
            'commerce' => [
                'fulfillment' => 'ship',
                'purchase_options' => [[
                    'id' => 'default', 'label' => 'Default', 'billing' => 'one_time',
                    'price_cents' => max(0, $priceCents), 'currency' => 'usd', 'interval' => null,
                ]],
                'quantity_available' => null, 'sku' => $sku,
            ],
        ];
    }

    /** @return array<string, string> */
    private function authHeaders(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token, 'Square-Version' => '2024-01-18', 'Accept' => 'application/json'];
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $text) ?? '', '-'));
        return $slug !== '' ? $slug : 'item';
    }
}
