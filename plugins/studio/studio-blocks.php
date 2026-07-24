<?php
/**
 * Studio core blocks v2 (P25-M15) — reference-quality markup.
 */
if (!function_exists('register_studio_block')) {
    return;
}

register_studio_block('commerce_product_card', [
    'label' => 'Product card grid',
    'description' => 'Commerce product cards matching reference-projects/coffee',
    'category' => 'Commerce',
    'plugin_id' => 'studio',
    'bindingType' => 'filtered',
    'collection' => 'products',
    'filterField' => 'status',
    'filterValue' => 'published',
    'orderBy' => 'newest',
    'itemTemplate' => '<article class="product-card"><h3><a href="#">{ title }</a></h3><p class="product-meta">{if entry has \'origin\'}{ origin }{endif}{if entry has \'roast\'} · { roast }{endif}</p>{if entry has \'description\'}<p>{ description }</p>{endif}<p class="price-from"><a href="#">View options →</a></p></article>',
    'canvas' => [
        'type' => 'binding',
        'classes' => ['product-grid'],
    ],
]);

register_studio_block('events_list', [
    'label' => 'Events list',
    'description' => 'Upcoming events list matching reference-projects/yoga',
    'category' => 'Events',
    'plugin_id' => 'studio',
    'bindingType' => 'template_function',
    'functionName' => 'upcoming_events',
    'functionArgs' => '6',
    'itemTemplate' => '<li><h3><a href="#">{ title }</a></h3><p class="event-meta">{ start } · { venue }</p><a class="btn studio-btn" href="#">Details &amp; register</a></li>',
    'canvas' => [
        'type' => 'html',
        'content' => '<ul class="event-list">{for each event in upcoming}<li><h3><a href="#">{ title }</a></h3><p class="event-meta">{ start } · { venue }</p><a class="btn studio-btn" href="#">Details &amp; register</a></li>{endfor}</ul>',
    ],
]);
