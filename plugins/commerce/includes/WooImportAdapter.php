<?php
/**
 * WooCommerce REST API import adapter (P12-M3).
 */

if (!class_exists('ImportHttpClient')) {
    require_once __DIR__ . '/ImportHttpClient.php';
}

class WooImportAdapter
{
    public const PLATFORM_ID = 'woocommerce';
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
        $siteUrl = $this->normalizeSiteUrl((string) ($credentials['site_url'] ?? ''));
        $key = trim((string) ($credentials['consumer_key'] ?? ''));
        $secret = trim((string) ($credentials['consumer_secret'] ?? ''));
        if ($siteUrl === '' || $key === '' || $secret === '') {
            return ['ok' => false, 'error' => 'Site URL, consumer key, and consumer secret are required'];
        }

        $url = $this->apiUrl($siteUrl, 'system_status');
        $response = $this->http->request('GET', $url, [
            'auth' => ['user' => $key, 'pass' => $secret],
            'timeout' => 20,
        ]);

        if (!($response['ok'] ?? false)) {
            $url = $this->apiUrl($siteUrl, 'products', ['per_page' => 1]);
            $response = $this->http->request('GET', $url, [
                'auth' => ['user' => $key, 'pass' => $secret],
                'timeout' => 20,
            ]);
        }

        if (!($response['ok'] ?? false)) {
            return ['ok' => false, 'error' => $response['error'] ?? 'Connection failed'];
        }

        return ['ok' => true, 'site_url' => $siteUrl];
    }

    /**
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $options
     * @return array{ok: bool, rows?: list<array<string, mixed>>, product_count?: int, pages_fetched?: int, error?: string}
     */
    public function fetchProductRows(array $credentials, array $options = []): array
    {
        $siteUrl = $this->normalizeSiteUrl((string) ($credentials['site_url'] ?? ''));
        $key = trim((string) ($credentials['consumer_key'] ?? ''));
        $secret = trim((string) ($credentials['consumer_secret'] ?? ''));
        if ($siteUrl === '' || $key === '' || $secret === '') {
            return ['ok' => false, 'error' => 'Site URL, consumer key, and consumer secret are required'];
        }

        $maxPages = max(1, min(self::MAX_PAGES, (int) ($options['max_pages'] ?? self::MAX_PAGES)));
        $rows = [];
        $pagesFetched = 0;
        $auth = ['user' => $key, 'pass' => $secret];

        for ($page = 1; $page <= $maxPages; $page++) {
            $url = $this->apiUrl($siteUrl, 'products', [
                'per_page' => self::PAGE_LIMIT,
                'page' => $page,
                'status' => 'publish',
            ]);
            $response = $this->http->request('GET', $url, ['auth' => $auth]);
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

            $products = is_array($response['data']) ? $response['data'] : [];
            if ($products === []) {
                break;
            }

            foreach ($products as $product) {
                if (!is_array($product)) {
                    continue;
                }
                $type = (string) ($product['type'] ?? 'simple');
                $mapped = $type === 'variable'
                    ? $this->mapVariableProduct($siteUrl, $product, $auth)
                    : [$this->mapSimpleProduct($product)];
                foreach ($mapped as $row) {
                    $rows[] = $row;
                }
            }

            if (count($products) < self::PAGE_LIMIT) {
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

    /** @param array<string, mixed> $product @return array<string, mixed> */
    private function mapSimpleProduct(array $product): array
    {
        $title = trim((string) ($product['name'] ?? 'Untitled'));
        $slug = trim((string) ($product['slug'] ?? $this->slugify($title)));
        $sku = trim((string) ($product['sku'] ?? '')) ?: null;
        $body = strip_tags((string) ($product['description'] ?? ''));
        $imageUrl = is_array($product['images'][0] ?? null) ? (string) ($product['images'][0]['src'] ?? '') : '';

        return [
            'title' => $title,
            'slug' => $slug,
            'sku' => $sku,
            'body' => $body,
            'image_url' => $imageUrl !== '' ? $imageUrl : null,
            'parent_sku' => null,
            'variant_label' => null,
            'commerce' => $this->buildCommerceValue(
                (string) ($product['price'] ?? $product['regular_price'] ?? '0'),
                $product['stock_quantity'] ?? $product['stock'] ?? null
            ),
        ];
    }

    /**
     * @param array<string, mixed> $product
     * @param array{user: string, pass: string} $auth
     * @return list<array<string, mixed>>
     */
    private function mapVariableProduct(string $siteUrl, array $product, array $auth): array
    {
        $title = trim((string) ($product['name'] ?? 'Untitled'));
        $slug = trim((string) ($product['slug'] ?? $this->slugify($title)));
        $body = strip_tags((string) ($product['description'] ?? ''));
        $imageUrl = is_array($product['images'][0] ?? null) ? (string) ($product['images'][0]['src'] ?? '') : '';
        $parentSku = trim((string) ($product['sku'] ?? '')) ?: $slug;
        $productId = (int) ($product['id'] ?? 0);

        $rows = [[
            'title' => $title,
            'slug' => $slug,
            'sku' => trim((string) ($product['sku'] ?? '')) ?: null,
            'body' => $body,
            'image_url' => $imageUrl !== '' ? $imageUrl : null,
            'parent_sku' => null,
            'variant_label' => null,
            'commerce' => $this->buildCommerceValue(
                (string) ($product['price'] ?? $product['regular_price'] ?? '0'),
                $product['stock_quantity'] ?? null
            ),
        ]];

        if ($productId <= 0) {
            return $rows;
        }

        $url = $this->apiUrl($siteUrl, 'products/' . $productId . '/variations', ['per_page' => 100]);
        $response = $this->http->request('GET', $url, ['auth' => $auth]);
        if (!($response['ok'] ?? false) || !is_array($response['data'])) {
            return $rows;
        }

        foreach ($response['data'] as $variation) {
            if (!is_array($variation)) {
                continue;
            }
            $label = $this->variationLabel($variation);
            $rows[] = [
                'title' => $title . ' — ' . $label,
                'slug' => $slug . '-' . $this->slugify($label),
                'sku' => trim((string) ($variation['sku'] ?? '')) ?: null,
                'body' => $body,
                'image_url' => null,
                'parent_sku' => $parentSku,
                'variant_label' => $label,
                'commerce' => $this->buildCommerceValue(
                    (string) ($variation['price'] ?? $variation['regular_price'] ?? '0'),
                    $variation['stock_quantity'] ?? null
                ),
            ];
        }

        return $rows;
    }

    /** @param array<string, mixed> $variation */
    private function variationLabel(array $variation): string
    {
        $attrs = is_array($variation['attributes'] ?? null) ? $variation['attributes'] : [];
        $parts = [];
        foreach ($attrs as $attr) {
            if (!is_array($attr)) {
                continue;
            }
            $option = trim((string) ($attr['option'] ?? ''));
            if ($option !== '') {
                $parts[] = $option;
            }
        }
        if ($parts !== []) {
            return implode(' / ', $parts);
        }

        return trim((string) ($variation['name'] ?? '')) ?: 'Variant';
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

    /** @param array<string, scalar|null> $query */
    private function apiUrl(string $siteUrl, string $path, array $query = []): string
    {
        $base = rtrim($siteUrl, '/') . '/wp-json/wc/v3/' . ltrim($path, '/');
        if ($query !== []) {
            $base .= '?' . http_build_query($query);
        }

        return $base;
    }

    private function normalizeSiteUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return rtrim($url, '/');
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-') ?: 'item';
    }
}
