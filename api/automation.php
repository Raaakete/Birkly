<?php
/**
 * Legacy plugin API URL alias — dispatches to api/index.php by filename (P82-M7).
 */

$_GET['route'] = $_GET['route'] ?? basename(__FILE__, '.php');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && empty($_GET['route'])) {
    $_GET['route'] = basename(__FILE__, '.php');
}
require __DIR__ . '/index.php';
