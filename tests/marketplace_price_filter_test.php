<?php
/**
 * CMS marketplace client — price range filter helpers.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/marketplace.php';

$errors = [];

$offers = [
    ['slug' => 'free-plugin', 'pricing' => ['model' => 'free', 'amount_cents' => 0]],
    ['slug' => 'paid-plugin', 'pricing' => ['model' => 'one_time', 'amount_cents' => 2500]],
];

$selection = birkly_marketplace_price_selection('10_50');
if (($selection['price_range'] ?? '') !== '10_50') {
    $errors[] = 'Price range selection failed';
}

$filtered = birkly_marketplace_filter_offers($offers, '', '', '', '10_50');
if (count($filtered) !== 1 || ($filtered[0]['slug'] ?? '') !== 'paid-plugin') {
    $errors[] = 'Price range filter failed';
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "marketplace_price_filter_test: PASS\n";
exit(0);
