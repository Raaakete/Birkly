<?php
/**
 * Commerce studio blocks (P23-M13).
 */
if (!function_exists('register_studio_block')) {
    return;
}
register_studio_block('commerce_product_grid', [
    'label' => 'Product grid',
    'category' => 'Commerce',
    'plugin_id' => 'commerce',
    'bindingType' => 'loop',
    'collection' => 'products',
    'itemTemplate' => '<article class="product-card"><h3>{title}</h3><p>{excerpt}</p></article>',
]);
