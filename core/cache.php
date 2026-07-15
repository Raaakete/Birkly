<?php
/**
 * Cache Management for Birkly
 * Provides file-based caching for entries, collections, and search indexes
 */

class Cache {
    private $cacheDir;
    private $enabled;
    
    public function __construct($cacheDir = null) {
        if ($cacheDir === null || $cacheDir === '../cache') {
            $this->cacheDir = dirname(__DIR__) . '/cache';
        } else {
            $this->cacheDir = $cacheDir;
        }
        $this->enabled = true; // Can be configured via settings
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Get cache file path for a collection
     */
    private function getCachePath($collectionSlug, $type = 'entries') {
        $collectionCacheDir = $this->cacheDir . '/collections/' . $collectionSlug;
        if (!is_dir($collectionCacheDir)) {
            @mkdir($collectionCacheDir, 0755, true);
        }
        return $collectionCacheDir . '/' . $type . '.json';
    }
    
    /**
     * Get cached entries list
     */
    public function getEntries($collectionSlug) {
        if (!$this->enabled) {
            return null;
        }
        
        $cacheFile = $this->getCachePath($collectionSlug, 'entries');
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        
        if (!$cacheData || !isset($cacheData['entries'])) {
            return null;
        }
        
        // Check if cache is still valid (compare with last modification time)
        $entriesDir = __DIR__ . '/../content/collections/' . $collectionSlug . '/entries';
        if (!is_dir($entriesDir)) {
            return null;
        }
        
        // Get most recent entry modification time
        $mostRecentEntryTime = $this->getMostRecentEntryTime($entriesDir);
        
        // If cache is older than most recent entry, invalidate
        if (isset($cacheData['cache_time']) && $mostRecentEntryTime > $cacheData['cache_time']) {
            return null;
        }

        // Invalidate when cached entry ids no longer match disk (e.g. demo project switch).
        $diskIds = $this->getEntryIdsOnDisk($entriesDir);
        $cachedIds = [];
        foreach ($cacheData['entries'] as $entry) {
            if (!empty($entry['id'])) {
                $cachedIds[] = (string) $entry['id'];
            }
        }
        sort($diskIds);
        sort($cachedIds);
        if ($diskIds !== $cachedIds) {
            return null;
        }
        
        return $cacheData['entries'];
    }
    
    /**
     * Cache entries list
     */
    public function setEntries($collectionSlug, $entries) {
        if (!$this->enabled) {
            return false;
        }
        
        $cacheFile = $this->getCachePath($collectionSlug, 'entries');
        
        $cacheData = [
            'entries' => $entries,
            'cache_time' => time(),
            'count' => count($entries)
        ];
        
        return file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Get search index for a collection
     */
    public function getSearchIndex($collectionSlug) {
        if (!$this->enabled) {
            return null;
        }
        
        $cacheFile = $this->getCachePath($collectionSlug, 'search_index');
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $indexData = json_decode(file_get_contents($cacheFile), true);
        
        if (!$indexData || !isset($indexData['index'])) {
            return null;
        }
        
        // Check if index is still valid
        $entriesDir = __DIR__ . '/../content/collections/' . $collectionSlug . '/entries';
        if (!is_dir($entriesDir)) {
            return null;
        }
        
        $mostRecentEntryTime = $this->getMostRecentEntryTime($entriesDir);
        
        if (isset($indexData['index_time']) && $mostRecentEntryTime > $indexData['index_time']) {
            return null;
        }
        
        return $indexData['index'];
    }
    
    /**
     * Set search index for a collection
     */
    public function setSearchIndex($collectionSlug, $index) {
        if (!$this->enabled) {
            return false;
        }
        
        $cacheFile = $this->getCachePath($collectionSlug, 'search_index');
        
        $indexData = [
            'index' => $index,
            'index_time' => time()
        ];
        
        return file_put_contents($cacheFile, json_encode($indexData, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Invalidate cache for a collection
     */
    public function invalidateCollection($collectionSlug) {
        $collectionCacheDir = $this->cacheDir . '/collections/' . $collectionSlug;
        
        if (is_dir($collectionCacheDir)) {
            $files = glob($collectionCacheDir . '/*.json');
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        
        return true;
    }
    
    /**
     * Invalidate all caches
     */
    public function invalidateAll() {
        if (is_dir($this->cacheDir)) {
            $this->deleteDirectory($this->cacheDir);
            mkdir($this->cacheDir, 0755, true);
        }
        return true;
    }
    
    /**
     * Entry ids present on disk (published JSON files only).
     */
    private function getEntryIdsOnDisk($entriesDir) {
        $ids = [];
        if (!is_dir($entriesDir)) {
            return $ids;
        }
        foreach (scandir($entriesDir) as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filePath = $entriesDir . '/' . $file;
            if (is_dir($filePath) || pathinfo($file, PATHINFO_EXTENSION) !== 'json') {
                continue;
            }
            $ids[] = pathinfo($file, PATHINFO_FILENAME);
        }
        return $ids;
    }

    /**
     * Get most recent entry modification time
     */
    private function getMostRecentEntryTime($entriesDir) {
        $mostRecent = 0;
        
        if (!is_dir($entriesDir)) {
            return 0;
        }
        
        $files = scandir($entriesDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filePath = $entriesDir . '/' . $file;
            
            // Skip directories (like version folders)
            if (is_dir($filePath)) {
                continue;
            }
            
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $mtime = filemtime($filePath);
                if ($mtime > $mostRecent) {
                    $mostRecent = $mtime;
                }
            }
        }
        
        return $mostRecent;
    }
    
    /**
     * Recursively delete directory
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        return @rmdir($dir);
    }
    
    /**
     * Build search index from entries
     */
    public function buildSearchIndex($collectionSlug, $entries, $schema = null) {
        $index = [];
        
        foreach ($entries as $entry) {
            $entryId = $entry['id'] ?? null;
            if (!$entryId) {
                continue;
            }
            
            $indexEntry = [
                'id' => $entryId,
                'title' => $entry['title'] ?? '',
                'searchable_text' => []
            ];
            
            // Index all text fields
            foreach ($entry as $key => $value) {
                // Skip non-text fields
                if (in_array($key, ['id', 'created_at', 'updated_at', 'status', 'version', 'slug'])) {
                    continue;
                }
                
                // If it's a string, add to searchable text
                if (is_string($value) && !empty($value)) {
                    // Strip HTML tags for better searching
                    $text = strip_tags($value);
                    if (!empty($text)) {
                        $indexEntry['searchable_text'][] = strtolower($text);
                    }
                }
                
                // If it's an array (like tags), add each item
                if (is_array($value)) {
                    foreach ($value as $item) {
                        if (is_string($item) && !empty($item)) {
                            $indexEntry['searchable_text'][] = strtolower($item);
                        }
                    }
                }
            }
            
            // Combine all searchable text
            $indexEntry['searchable_text'] = implode(' ', $indexEntry['searchable_text']);
            
            $index[] = $indexEntry;
        }
        
        return $index;
    }
    
    /**
     * Search in index
     */
    public function searchInIndex($index, $query) {
        if (empty($query) || empty($index)) {
            return [];
        }
        
        $query = strtolower(trim($query));
        $queryWords = explode(' ', $query);
        $results = [];
        
        foreach ($index as $entry) {
            $searchableText = $entry['searchable_text'] ?? '';
            $title = strtolower($entry['title'] ?? '');
            
            // Check if all query words are found
            $matches = true;
            foreach ($queryWords as $word) {
                if (empty($word)) {
                    continue;
                }
                
                if (strpos($searchableText, $word) === false && strpos($title, $word) === false) {
                    $matches = false;
                    break;
                }
            }
            
            if ($matches) {
                $results[] = $entry['id'];
            }
        }
        
        return $results;
    }
    
    /**
     * Generic get method for key-value caching
     * 
     * @param string $key Cache key
     * @return mixed Cached value or null if not found/expired
     */
    public function get($key) {
        if (!$this->enabled) {
            return null;
        }
        
        $cacheFile = $this->cacheDir . '/kv/' . md5($key) . '.json';
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        
        if (!$cacheData || !isset($cacheData['value'])) {
            return null;
        }
        
        // Check if cache is expired
        if (isset($cacheData['expires_at']) && $cacheData['expires_at'] < time()) {
            @unlink($cacheFile);
            return null;
        }
        
        return $cacheData['value'];
    }
    
    /**
     * Generic set method for key-value caching
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds (default: 3600 = 1 hour)
     * @return bool True on success
     */
    public function set($key, $value, $ttl = 3600) {
        if (!$this->enabled) {
            return false;
        }
        
        $kvDir = $this->cacheDir . '/kv';
        if (!is_dir($kvDir)) {
            mkdir($kvDir, 0755, true);
        }
        
        $cacheFile = $kvDir . '/' . md5($key) . '.json';
        
        $cacheData = [
            'value' => $value,
            'created_at' => time(),
            'expires_at' => time() + $ttl
        ];
        
        return file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Generic delete method for key-value caching
     * 
     * @param string $key Cache key
     * @return bool True on success
     */
    public function delete($key) {
        $cacheFile = $this->cacheDir . '/kv/' . md5($key) . '.json';
        
        if (file_exists($cacheFile)) {
            return @unlink($cacheFile);
        }
        
        return true;
    }
}

