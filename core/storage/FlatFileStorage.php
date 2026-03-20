<?php
/**
 * Flat File Storage Driver for Birkly
 * 
 * Implements StorageInterface using JSON files in directories.
 * This is the default storage driver and maintains backward compatibility.
 */

require_once __DIR__ . '/StorageInterface.php';

class FlatFileStorage implements StorageInterface {
    public $basePath;
    
    public function __construct($basePath = '../content') {
        $this->basePath = rtrim($basePath, '/');
        if (!file_exists($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }
    
    public function read($path) {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        if (!file_exists($fullPath)) {
            return null;
        }
        // Avoid warnings/notices when attempting to read a directory
        if (is_dir($fullPath)) {
            return null;
        }
        $content = file_get_contents($fullPath);
        return json_decode($content, true);
    }
    
    public function write($path, $data) {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        $dir = dirname($fullPath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        $tmpPath = $fullPath . '.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT);
        $fp = fopen($tmpPath, 'c');
        if (!$fp) return false;
        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, $json);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            rename($tmpPath, $fullPath);
            return true;
        } else {
            fclose($fp);
            return false;
        }
    }
    
    public function delete($path) {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        return false;
    }
    
    public function list($path) {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        if (!file_exists($fullPath)) {
            return [];
        }
        $files = scandir($fullPath);
        return array_filter($files, function($file) {
            return $file !== '.' && $file !== '..';
        });
    }
    
    public function exists($path) {
        $fullPath = $this->basePath . '/' . ltrim($path, '/');
        return file_exists($fullPath);
    }
    
    public function query($collection, $filters = [], $options = []) {
        // For flat-file storage, we need to scan the collection directory
        $collectionPath = $this->basePath . '/collections/' . $collection . '/entries';
        if (!file_exists($collectionPath)) {
            return [];
        }
        
        $entries = [];
        $files = $this->list('collections/' . $collection . '/entries');
        
        foreach ($files as $file) {
            if (substr($file, -5) !== '.json') {
                continue;
            }
            
            $entryPath = 'collections/' . $collection . '/entries/' . $file;
            $entry = $this->read($entryPath);
            
            if ($entry === null) {
                continue;
            }
            
            // Apply filters
            $matches = true;
            foreach ($filters as $field => $filter) {
                $operator = '=';
                $value = $filter;
                
                // Support advanced filter format: ['operator' => '=', 'value' => value]
                if (is_array($filter) && isset($filter['operator']) && isset($filter['value'])) {
                    $operator = $filter['operator'];
                    $value = $filter['value'];
                }
                
                $fieldValue = $entry[$field] ?? null;
                
                switch ($operator) {
                    case '=':
                        $matches = ($fieldValue === $value);
                        break;
                    case '!=':
                        $matches = ($fieldValue !== $value);
                        break;
                    case '>':
                        $matches = ($fieldValue > $value);
                        break;
                    case '>=':
                        $matches = ($fieldValue >= $value);
                        break;
                    case '<':
                        $matches = ($fieldValue < $value);
                        break;
                    case '<=':
                        $matches = ($fieldValue <= $value);
                        break;
                    case 'contains':
                        $matches = (is_string($fieldValue) && strpos($fieldValue, $value) !== false);
                        break;
                    case 'in':
                        $matches = (is_array($value) && in_array($fieldValue, $value));
                        break;
                    default:
                        $matches = ($fieldValue === $value);
                }
                
                if (!$matches) {
                    break;
                }
            }
            
            if ($matches) {
                $entries[] = $entry;
            }
        }
        
        // Apply sorting
        if (isset($options['sort'])) {
            $sortField = $options['sort'];
            $sortOrder = $options['order'] ?? 'asc';
            usort($entries, function($a, $b) use ($sortField, $sortOrder) {
                $aVal = $a[$sortField] ?? '';
                $bVal = $b[$sortField] ?? '';
                $result = $aVal <=> $bVal;
                return $sortOrder === 'desc' ? -$result : $result;
            });
        }
        
        // Apply pagination
        if (isset($options['offset'])) {
            $entries = array_slice($entries, $options['offset']);
        }
        if (isset($options['limit'])) {
            $entries = array_slice($entries, 0, $options['limit']);
        }
        
        return $entries;
    }
    
    /**
     * Transactions are not supported in flat-file storage
     * These methods are no-ops for backward compatibility
     */
    public function beginTransaction() {
        return true; // No-op for flat-file storage
    }
    
    public function commit() {
        return true; // No-op for flat-file storage
    }
    
    public function rollback() {
        return true; // No-op for flat-file storage
    }
    
    public function getDriverName() {
        return 'flatfile';
    }
    
    public function getMetadata() {
        return [
            'driver' => 'flatfile',
            'version' => '1.0.0',
            'capabilities' => [
                'transactions' => false,
                'queries' => true,
                'indexing' => false
            ],
            'base_path' => $this->basePath
        ];
    }
}

// Backward compatibility: Create alias for old Storage class name
if (!class_exists('Storage')) {
    class Storage extends FlatFileStorage {
        // This maintains backward compatibility with existing code
    }
}
