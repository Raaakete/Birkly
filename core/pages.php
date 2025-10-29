<?php
// Core pages collection registration for HaruCMS

// Register the pages collection
register_collection('pages', realpath(__DIR__ . '/../content/collections/pages'), [
    'hidden' => false,
    'type' => 'pages'
]); 