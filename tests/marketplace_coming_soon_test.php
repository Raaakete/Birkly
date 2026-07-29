<?php
/**
 * CMS marketplace client — coming soon offer handling.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';

$errors = [];

$published = [
    'slug' => 'published-plugin',
    'title' => 'Published',
    'status' => 'published',
];
if (!birkly_marketplace_offer_is_installable($published)) {
    $errors[] = 'Published offer should be installable';
}

$comingSoon = [
    'slug' => 'birkly-commerce',
    'title' => 'Commerce',
    'status' => 'coming_soon',
];
if (birkly_marketplace_offer_is_installable($comingSoon)) {
    $errors[] = 'Coming soon offer should not be installable';
}

$legacy = [
    'slug' => 'legacy-plugin',
    'title' => 'Legacy',
];
if (!birkly_marketplace_offer_is_installable($legacy)) {
    $errors[] = 'Legacy offer without status should remain installable';
}

$enriched = birkly_marketplace_enrich_offer_install_state($comingSoon);
if (($enriched['installable'] ?? true) !== false) {
    $errors[] = 'Enriched coming soon offer should set installable=false';
}
if (($enriched['status'] ?? '') !== 'coming_soon') {
    $errors[] = 'Enrich should preserve offer status';
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "marketplace_coming_soon_test: PASS\n";
exit(0);
