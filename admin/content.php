<?php
/**
 * Direct Content page entry — bypasses index.php routing if needed.
 * Usage: /admin/content.php (same auth as admin panel)
 */
$_GET['page'] = 'content';
require __DIR__ . '/index.php';
