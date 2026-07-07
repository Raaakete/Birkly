<?php
/**
 * Shopify Admin REST API import adapter (P12-M3).
 */

if (!class_exists('ImportHttpClient')) {
    require_once __DIR__ . '/ImportHttpClient.php';
}

class ShopifyImportAdapter
{
    public const PLATFORM_ID = 'shopify';
    public const API_VERSION = '2024-01';
    public const PAGE_LIMIT = 50;
    public const MAX_PAGES = 100;

    private ImportHttpClient $http;

    public function __construct(?ImportHttpClient $http = null)
    {
        $this->http = $http ?? new ImportHttpClient();
    }

    /** @param array<string, mixed> $credentials */
    public function testConnection(array $credentials): array
    {
        $shop = $this->normalizeShopDomain((string) ($credentials['shop_domain'] ?? ''));
        $token = trim((string) ($credentials['access_token'] ?? ''));
        if ($shop === '' || $token === '') {
            return ['ok' => false, 'error' => 'Shop domain and access token are required'];
        }

        $url = $this->apiUrl($shop, 'shop.json');
        $response = $this->http->request('GET', $url, [
            'headers' => $this->authHeaders($token),
            'timeout' => 20,
        ]);

        if (!($response['ok'] ?? false)) {
            return ['ok' => false, 'error' => $response['error'] ?? 'Connection failed'];
        }

        $shopName = is_array($response['data']['shop'] ?? null)
            ? (string) ($response['data']['shop']['name'] ?? $shop)
            : $shop;

        return ['ok' => true, 'shop_name' => $shopName];
    }

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $options
     * @return array{ok: bool, rows?: list<array<string, mixed>>, product_count?: int, pages_fetched?: int, error?: string}
     */
    public function fetchProductRows(array $credentials, array $options = []): array
    {
        $shop = $this->normalizeShopDomain((string) ($credentials['shop_domain'] ?? ''));
        $token = trim((string) ($credentials['access_token'] ?? ''));
        if ($shop === '' || $token === '') {
            return ['ok' => false, 'error' => 'Shop domain and access token are required'];
        }

        $maxPages = max(1, min(self::MAX_PAGES, (int) ($options['max_pages'] ?? self::MAX_PAGES)));
        $rows = [];
        $pageInfo = null;
        $pagesFetched = 0;

        for ($page = 0; $page < $maxPages; $page++) {
            $query = 'limit=' . self::PAGE_LIMIT;
            if ($pageInfo !== null) {
                $query = 'limit=' . self::PAGE_LIMIT . '&page_info=' . rawurlencode($pageInfo);
            }

            $url = $this->apiUrl($shop, 'products.json?' . $query);
            $response = $this->http->request('GET', $url, [
                'headers' => $this->authHeaders($token),
            ]);
            $pagesFetched++;

            if (!($response['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'error' => $response['error'] ?? 'Failed to fetch products',
                    'rows' => $rows,
                    'product_count' => count($rows),
                    'pages_fetched' => $pagesFetched,
                ];
            }

            $products = is_array($response['data']['products'] ?? null) ? $response['data']['products'] : [];
            foreach ($products as $product) {
                if (!is_array($product)) {
                    continue;
                }
                foreach ($this->mapProductToRows($product) as $row) {
                    $rows[] = $row;
                }
            }

            $pageInfo = $this->nextPageInfo($response['headers'] ?? []);
            if ($pageInfo === null || count($products) < self::PAGE_LIMIT) {
                break;
            }
        }

        return [
            'ok' => true,
            'rows' => $rows,
            'product_count' => count(array_filter($rows, fn ($r) => empty($r['parent_sku']))),
            'pages_fetched' => $pagesFetched,
        ];
    }

    /** @param array<string, mixed> $product @return list<array<string, mixed>> */
    private function mapProductToRows(array $product): array
    {
        $title = trim((string) ($product['title'] ?? 'Untitled'));
        $handle = trim((string) ($product['handle'] ?? $this->slugify($title)));
        $body = strip_tags((string) ($product['body_html'] ?? ''));
        $variants = is_array($product['variants'] ?? null) ? $product['variants'] : [];
        $images = is_array($product['images'] ?? null) ? $product['images'] : [];
        $imageUrl = is_array($images[0] ?? null) ? (string) ($images[0]['src'] ?? '') : '';

        if ($variants === []) {
            return [[
                'title' => $title,
                'slug' => $handle,
                'sku' => null,
                'body' => $body,
                'image_url' => $imageUrl !== '' ? $imageUrl : null,
                'parent_sku' => null,
                'variant_label' => null,
                'commerce' => $this->buildCommerceValue('0', null),
            ]];
        }

        $rows = [];
        $parentSku = $handle;
        $first = true;

        foreach ($variants as $variant) {
            if (!is_array($variant)) {
                continue;
            }
            $sku = trim((string) ($variant['sku'] ?? '')) ?: null;
            $price = (string) ($variant['price'] ?? '0');
            $qty = $variant['inventory_quantity'] ?? null;
            $label = trim((string) ($variant['title'] ?? ''));
            if ($label === '' || strtolower($label) === 'default title') {
                $label = trim((string) ($variant['option1'] ?? ''));
            }

            if ($first) {
                $rows[] = [
                    'title' => $title,
                    'slug' => $handle,
                    'sku' => $sku,
                    'body' => $body,
                    'image_url' => $imageUrl !== '' ? $imageUrl : null,
                    'parent_sku' => null,
                    'variant_label' => null,
                    'commerce' => $this->buildCommerceValue($price, $qty),
                ];
                $first = false;
                if (count($variants) === 1) {
                    break;
                }
                continue;
            }

            $variantLabel = $label !== '' ? $label : 'Variant';
            $rows[] = [
                'title' => $title . ' — ' . $variantLabel,
                'slug' => $handle . '-' . $this->slugify($variantLabel),
                'sku' => $sku,
                'body' => $body,
                'image_url' => null,
                'parent_sku' => $parentSku,
                'variant_label' => $variantLabel,
                'commerce' => $this->buildCommerceValue($price, $qty),
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function buildCommerceValue(string $price, $quantity): array
    {
        $price = preg_replace('/[^0-9.]/', '', $price);
        $cents = (int) round((float) $price * 100);
        $qtyAvailable = ($quantity === '' || $quantity === null) ? null : (int) $quantity;

        if (!function_exists('commerce_normalize_value')) {
            require_once dirname(__DIR__) . '/init.php';
            commerce_bootstrap_cart_services();
        }

        return commerce_normalize_value([
            'fulfillment' => 'ship',
            'purchase_options' => [[
                'id' => 'import_default',
                'label' => 'Default',
                'billing' => 'one_time',
                'price_cents' => $cents,
            ]],
            'quantity_available' => $qtyAvailable,
        ]);
    }

    /** @param array<string, string> $headers */
    private function nextPageInfo(array $headers): ?string
    {
        $link = $headers['link'] ?? '';
        if ($link === '' || !preg_match('/<[^>]+[?&]page_info=([^>&]+)[^>]*>;\s*rel="next"/i', $link, $matches)) {
            return null;
        }

        return urldecode($matches[1]);
    }

    /** @return list<string> */
    private function authHeaders(string $token): array
    {
        return [
            'X-Shopify-Access-Token: ' . $token,
            'Accept: application/json',
        ];
    }

    private function apiUrl(string $shop, string $path): string
    {
        return 'https://' . $shop . '/admin/api/' . self::API_VERSION . '/' . ltrim($path, '/');
    }

    private function normalizeShopDomain(string $domain): string
    {
        $domain = trim(strtolower($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = rtrim($domain, '/');
        if ($domain !== '' && !str_contains($domain, '.')) {
            $domain .= '.myshopify.com';
        }

        return $domain;
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-') ?: 'item';
    }
}
