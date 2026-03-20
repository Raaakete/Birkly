<?php
/**
 * Storage Interface for Birkly
 * 
 * Defines the contract that all storage drivers must implement.
 * This abstraction allows switching between flat-file, SQL, and other storage backends.
 */

interface StorageInterface {
    /**
     * Read data from storage
     * 
     * @param string $path Path to the resource (relative to storage root)
     * @return array|null Decoded JSON data, or null if not found
     */
    public function read($path);
    
    /**
     * Write data to storage
     * 
     * @param string $path Path to the resource (relative to storage root)
     * @param array $data Data to write (will be JSON encoded)
     * @return bool True on success, false on failure
     */
    public function write($path, $data);
    
    /**
     * Delete data from storage
     * 
     * @param string $path Path to the resource (relative to storage root)
     * @return bool True on success, false on failure
     */
    public function delete($path);
    
    /**
     * List items in a directory
     * 
     * @param string $path Path to the directory (relative to storage root)
     * @return array List of item names (files/directories)
     */
    public function list($path);
    
    /**
     * Check if a path exists
     * 
     * @param string $path Path to check (relative to storage root)
     * @return bool True if exists, false otherwise
     */
    public function exists($path);
    
    /**
     * Query entries with filters
     * 
     * @param string $collection Collection name/slug
     * @param array $filters Query filters (field => value, or field => ['operator' => '=', 'value' => value])
     * @param array $options Query options (limit, offset, sort, order)
     * @return array List of matching entries
     */
    public function query($collection, $filters = [], $options = []);
    
    /**
     * Begin a transaction (optional - can be no-op for drivers that don't support it)
     * 
     * @return bool True on success, false on failure or if not supported
     */
    public function beginTransaction();
    
    /**
     * Commit a transaction (optional - can be no-op for drivers that don't support it)
     * 
     * @return bool True on success, false on failure or if not supported
     */
    public function commit();
    
    /**
     * Rollback a transaction (optional - can be no-op for drivers that don't support it)
     * 
     * @return bool True on success, false on failure or if not supported
     */
    public function rollback();
    
    /**
     * Get storage driver name
     * 
     * @return string Driver name (e.g., 'flatfile', 'postgres', 'mysql')
     */
    public function getDriverName();
    
    /**
     * Get storage metadata/info
     * 
     * @return array Storage information (driver, version, capabilities, etc.)
     */
    public function getMetadata();
}
