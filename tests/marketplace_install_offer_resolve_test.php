<?php
/**
 * Install gating must follow live marketplace status when the API is reachable,
 * not stale bundled fallback (e.g. coming_soon → published).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';

$errors = [];
$slug = 'birkly-events';

$GLOBALS['birkly_marketplace_mock_handler'] = static function (string $method, string $path) use ($slug): array {
    if ($method === 'GET' && str_contains($path, '/api/v1/offers/' . $slug)) {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'offer' => [
                    'slug' => $slug,
                    'title' => 'Events',
                    'status' => 'published',
                    'releases' => [['version' => '1.2.1']],
                ],
                'releases' => [['version' => '1.2.1']],
            ],
            'error' => null,
        ];
    }

    return [
        'ok' => false,
        'status' => 0,
        'data' => null,
        'error' => 'unreachable',
    ];
};

$resolved = birkly_marketplace_resolve_install_offer($slug);
if ($resolved === null) {
    $errors[] = 'resolve_install_offer should return an offer';
} elseif (($resolved['status'] ?? '') !== 'published') {
    $errors[] = 'resolve_install_offer should use live published status';
} elseif (!birkly_marketplace_offer_is_installable($resolved)) {
    $errors[] = 'resolved live published offer should be installable';
}

$fallback = birkly_marketplace_find_fallback_offer($slug);
if (is_array($fallback) && ($fallback['status'] ?? '') === 'coming_soon' && birkly_marketplace_offer_is_installable($resolved ?? [])) {
    // Explicit regression: live published wins over fallback coming_soon.
} elseif (is_array($fallback) && ($fallback['status'] ?? '') === 'coming_soon') {
    $errors[] = 'expected live API to override fallback coming_soon for install resolve';
}

unset($GLOBALS['birkly_marketplace_mock_handler']);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "marketplace_install_offer_resolve_test: PASS\n";
exit(0);