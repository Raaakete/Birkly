<?php
// Core pages collection registration for Birkly

// Register the pages collection
register_collection('pages', realpath(__DIR__ . '/../content/collections/pages'), [
    'hidden' => false,
    'type' => 'pages'
]); 