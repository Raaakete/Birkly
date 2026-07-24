<?php
if (!function_exists('register_studio_block')) {
    return;
}
register_studio_block('events_upcoming', [
    'label' => 'Upcoming events',
    'category' => 'Events',
    'plugin_id' => 'events',
    'bindingType' => 'loop',
    'collection' => 'events',
    'itemTemplate' => '<article><h3>{title}</h3><p>{date}</p></article>',
]);
