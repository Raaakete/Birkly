<?php
/**
 * Payments Plugin — deprecated shim forwarding to Commerce.
 */

if (!defined('BIRKLY_COMMERCE_BOOTSTRAP')) {
    require_once dirname(__DIR__) . '/commerce/plugin.php';
}
