<?php
/**
 * Storage API - Backward Compatibility Layer
 * 
 * This file maintains backward compatibility with existing code.
 * New code should use StorageFactory::getStorage() or get_storage() instead.
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Load the new storage system
require_once __DIR__ . '/../core/storage/StorageFactory.php';
require_once __DIR__ . '/../core/storage/FlatFileStorage.php';

// The Storage class is now defined in FlatFileStorage.php for backward compatibility
// This file is kept for any code that includes it directly 