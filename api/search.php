<?php
/**
 * Global Search API for Birkly
 * 
 * Provides unified search across collections, entries, sub-entries, media, users, and plugin content.
 */

// Start output buffering to catch any errors
ob_start();

// Suppress error display but log them
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Register error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Fatal PHP error',
            'error' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
        exit;
    }
});

header('Content-Type: application/json');

// SECURITY FIX: Replace wildcard CORS with origin whitelist
require_once __DIR__ . '/../core/cors.php';
require_once __DIR__ . '/../core/birkly_paths.php';
set_cors_headers('admin'); // Search API is admin-only

try {
    require_once __DIR__ . '/session.php';
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/../core/storage/StorageFactory.php';
    require_once __DIR__ . '/../core/sub_entries.php';
    
    // Only require plugins.php if it hasn't been loaded and if the function exists
    if (!function_exists('birkly_trigger_event')) {
        require_once __DIR__ . '/../core/plugins.php';
    }
    
    haru_secure_session_start();
} catch (Exception $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log("Search API: Error loading dependencies: " . $e->getMessage());
    error_log("Search API: Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to load dependencies',
        'error' => $e->getMessage()
    ]);
    exit;
} catch (Error $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log("Search API: Fatal error loading dependencies: " . $e->getMessage());
    error_log("Search API: Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Fatal error loading dependencies',
        'error' => $e->getMessage()
    ]);
    exit;
}

// Check authentication
if (!isset($_SESSION['user'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$query = trim($_GET['q'] ?? '');
$scope = $_GET['scope'] ?? 'all'; // all, collections, entries, sub_entries, media, users, plugins
$limit = intval($_GET['limit'] ?? 20);

if (empty($query)) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode([
        'status' => 'ok',
        'query' => '',
        'results' => [],
        'total' => 0,
        'scopes' => []
    ]);
    exit;
}

// Get storage instance
try {
    $storage = StorageFactory::getStorage(__DIR__ . '/../content');
    if (!$storage) {
        throw new Exception('Storage instance is null');
    }
} catch (Exception $e) {
    error_log("Search API: Failed to get storage: " . $e->getMessage());
    error_log("Search API: Stack trace: " . $e->getTraceAsString());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Storage initialization failed',
        'error' => $e->getMessage()
    ]);
    exit;
} catch (Error $e) {
    error_log("Search API: Fatal error getting storage: " . $e->getMessage());
    error_log("Search API: Stack trace: " . $e->getTraceAsString());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Fatal error getting storage',
        'error' => $e->getMessage()
    ]);
    exit;
}

$results = [];
$scopes = [
    'collections' => 0,
    'entries' => 0,
    'sub_entries' => 0,
    'media' => 0,
    'users' => 0,
    'plugins' => 0
];

// Debug logging
error_log("Search API: Query='{$query}', Scope='{$scope}', Limit={$limit}");

// Search Collections
try {
    if ($scope === 'all' || $scope === 'collections') {
        $collectionResults = searchCollections($storage, $query, $limit);
        $results = array_merge($results, $collectionResults);
        $scopes['collections'] = count($collectionResults);
    }
} catch (Exception $e) {
    error_log("Search API: Error searching collections: " . $e->getMessage());
}

// Search Entries
try {
    if ($scope === 'all' || $scope === 'entries') {
        $entryResults = searchEntries($storage, $query, $limit);
        $results = array_merge($results, $entryResults);
        $scopes['entries'] = count($entryResults);
    }
} catch (Exception $e) {
    error_log("Search API: Error searching entries: " . $e->getMessage());
}

// Search Sub-Entries
try {
    if ($scope === 'all' || $scope === 'sub_entries') {
        $subEntryResults = searchSubEntries($storage, $query, $limit);
        $results = array_merge($results, $subEntryResults);
        $scopes['sub_entries'] = count($subEntryResults);
    }
} catch (Exception $e) {
    error_log("Search API: Error searching sub-entries: " . $e->getMessage());
}

// Search Media
try {
    if ($scope === 'all' || $scope === 'media') {
        $mediaResults = searchMedia($query, $limit);
        $results = array_merge($results, $mediaResults);
        $scopes['media'] = count($mediaResults);
    }
} catch (Exception $e) {
    error_log("Search API: Error searching media: " . $e->getMessage());
}

// Search Users (admin only)
try {
    if (($scope === 'all' || $scope === 'users')) {
        // Check if user has permission to view users
        $canViewUsers = false;
        if (isset($_SESSION['user']) && function_exists('checkPermission')) {
            $canViewUsers = checkPermission($_SESSION['user'], 'manage_users') || 
                           checkPermission($_SESSION['user'], 'view_users') ||
                           ($_SESSION['user']['role'] ?? '') === 'admin';
        }
        
        if ($canViewUsers) {
            $userResults = searchUsers($query, $limit);
            $results = array_merge($results, $userResults);
            $scopes['users'] = count($userResults);
        }
    }
} catch (Exception $e) {
    error_log("Search API: Error searching users: " . $e->getMessage());
}

// Search Plugin Content
try {
    if ($scope === 'all' || $scope === 'plugins') {
        $pluginResults = searchPluginContent($query, $limit);
        $results = array_merge($results, $pluginResults);
        $scopes['plugins'] = count($pluginResults);
    }
} catch (Exception $e) {
    error_log("Search API: Error searching plugin content: " . $e->getMessage());
}

// Sort results by relevance (match_score)
usort($results, function($a, $b) {
    return ($b['match_score'] ?? 0) <=> ($a['match_score'] ?? 0);
});

// Limit total results
$results = array_slice($results, 0, $limit);

// Debug logging
error_log("Search API: Returning " . count($results) . " results");
error_log("Search API: Scopes: " . json_encode($scopes));

// Clean any output before JSON
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Start fresh output buffer for JSON
ob_start();

try {
    $response = [
        'status' => 'ok',
        'query' => $query,
        'results' => $results,
        'total' => count($results),
        'scopes' => $scopes
    ];
    
    $json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if ($json === false) {
        throw new Exception('JSON encoding failed: ' . json_last_error_msg());
    }
    
    echo $json;
    ob_end_flush();
    exit;
} catch (Exception $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log("Search API: Error encoding response: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to encode response',
        'error' => $e->getMessage()
    ]);
    exit;
}

/**
 * Search collections
 */
function searchCollections($storage, $query, $limit) {
    $results = [];
    $queryLower = strtolower($query);
    
    try {
        $collections = $storage->list('collections');
        error_log("Search Collections: Found " . count($collections) . " collections");
        
        if (empty($collections)) {
            error_log("Search Collections: No collections found in storage");
            return $results;
        }
        
        foreach ($collections as $collectionSlug) {
            $collectionPath = "collections/{$collectionSlug}/collection.json";
            $collection = $storage->read($collectionPath);
            
            if (!$collection) continue;
            
            $name = $collection['name'] ?? $collection['title'] ?? $collectionSlug;
            $slug = $collection['slug'] ?? $collectionSlug;
            $description = $collection['description'] ?? '';
            
            $score = 0;
            $matched = false;
            
            // Exact match in name
            if (strtolower($name) === $queryLower) {
                $score = 1.0;
                $matched = true;
            }
            // Name starts with query
            elseif (stripos($name, $query) === 0) {
                $score = 0.9;
                $matched = true;
            }
            // Name contains query
            elseif (stripos($name, $query) !== false) {
                $score = 0.8;
                $matched = true;
            }
            // Slug matches
            elseif (stripos($slug, $query) !== false) {
                $score = 0.7;
                $matched = true;
            }
            // Description contains query
            elseif (stripos($description, $query) !== false) {
                $score = 0.6;
                $matched = true;
            }
            
            if ($matched) {
                $results[] = [
                    'type' => 'collection',
                    'id' => $slug,
                    'title' => $name,
                    'subtitle' => 'Collection',
                    'url' => birkly_admin_url('entries', ['collection' => $slug]),
                    'icon' => '📁',
                    'match_score' => $score
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Search collections error: " . $e->getMessage());
    }
    
    return array_slice($results, 0, $limit);
}

/**
 * Search entries across all collections
 */
function searchEntries($storage, $query, $limit) {
    $results = [];
    $queryLower = strtolower($query);
    
    try {
        $collections = $storage->list('collections');
        error_log("Search Entries: Found " . count($collections) . " collections to search");
        
        foreach ($collections as $collectionSlug) {
            $entriesPath = "collections/{$collectionSlug}/entries";
            
            try {
                $entryFiles = $storage->list($entriesPath);
            } catch (Exception $e) {
                error_log("Search Entries: Failed to list entries for {$collectionSlug}: " . $e->getMessage());
                continue;
            }
            
            if (empty($entryFiles)) {
                continue;
            }
            
            foreach ($entryFiles as $entryFile) {
                if (pathinfo($entryFile, PATHINFO_EXTENSION) !== 'json') continue;
                
                $entryPath = "{$entriesPath}/{$entryFile}";
                $entry = $storage->read($entryPath);
                
                if (!$entry) continue;
                
                $entryId = basename($entryFile, '.json');
                $title = $entry['title'] ?? $entry['__cells']['__title'] ?? $entryId;
                $slug = $entry['slug'] ?? '';
                $status = $entry['status'] ?? 'draft';
                
                // Search in title
                $score = 0;
                $matched = false;
                
                if (strtolower($title) === $queryLower) {
                    $score = 1.0;
                    $matched = true;
                } elseif (stripos($title, $query) === 0) {
                    $score = 0.9;
                    $matched = true;
                } elseif (stripos($title, $query) !== false) {
                    $score = 0.8;
                    $matched = true;
                } elseif (stripos($slug, $query) !== false) {
                    $score = 0.7;
                    $matched = true;
                } else {
                    // Search in content fields
                    if (isset($entry['__cells'])) {
                        foreach ($entry['__cells'] as $fieldName => $fieldValue) {
                            if (is_string($fieldValue) && stripos($fieldValue, $query) !== false) {
                                $score = 0.6;
                                $matched = true;
                                break;
                            }
                        }
                    }
                }
                
                if ($matched) {
                    // Check permission if user management plugin is active
                    $canView = true;
                    if (function_exists('get_plugin_state') && get_plugin_state('user_management')) {
                        require_once __DIR__ . '/../plugins/user_management/includes/EntryPermissions.php';
                        $entryPermissions = new EntryPermissions();
                        $currentUserId = $entryPermissions->getCurrentUserId();
                        $canView = $entryPermissions->canViewEntry($currentUserId, $entry);
                    }
                    
                    if ($canView) {
                        $collectionName = getCollectionName($storage, $collectionSlug);
                        $statusLabel = ucfirst($status);
                        
                        $results[] = [
                            'type' => 'entry',
                            'id' => $entryId,
                            'title' => $title,
                            'subtitle' => "{$collectionName} • {$statusLabel}",
                            'url' => birkly_admin_url('entry-detail', ['collection' => $collectionSlug, 'entry' => $entryId]),
                            'icon' => '📝',
                            'match_score' => $score
                        ];
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Search entries error: " . $e->getMessage());
    }
    
    return array_slice($results, 0, $limit);
}

/**
 * Search sub-entries (including nested sub-entries)
 */
function searchSubEntries($storage, $query, $limit) {
    $results = [];
    $queryLower = strtolower($query);
    
    try {
        $collections = $storage->list('collections');
        foreach ($collections as $collectionSlug) {
            $entriesPath = "collections/{$collectionSlug}/entries";
            $entryFiles = $storage->list($entriesPath);
            
            foreach ($entryFiles as $entryFile) {
                if (pathinfo($entryFile, PATHINFO_EXTENSION) !== 'json') continue;
                
                $entryId = basename($entryFile, '.json');
                $subEntriesPath = "{$entriesPath}/{$entryId}/sub_entries";
                
                // Check if sub_entries directory exists
                try {
                    $subCollections = $storage->list($subEntriesPath);
                } catch (Exception $e) {
                    continue; // No sub-entries for this entry
                }
                
                foreach ($subCollections as $subCollectionSlug) {
                    $subCollectionPath = "{$subEntriesPath}/{$subCollectionSlug}";
                    
                    // Search direct sub-entries
                    searchSubEntriesRecursive($storage, $query, $queryLower, $collectionSlug, $entryId, $subCollectionSlug, $subCollectionPath, null, null, $results);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Search sub-entries error: " . $e->getMessage());
    }
    
    return array_slice($results, 0, $limit);
}

/**
 * Recursively search sub-entries (handles nested sub-entries)
 */
function searchSubEntriesRecursive($storage, $query, $queryLower, $parentCollection, $parentEntryId, $subCollectionSlug, $subCollectionPath, $parentSubCollection, $parentSubEntryId, &$results) {
    try {
        $subEntryFiles = $storage->list($subCollectionPath);
        
        foreach ($subEntryFiles as $subEntryFile) {
            if (pathinfo($subEntryFile, PATHINFO_EXTENSION) !== 'json') {
                // Check if it's a nested sub-entry directory
                $nestedPath = "{$subCollectionPath}/{$subEntryFile}/sub_entries";
                try {
                    $nestedSubCollections = $storage->list($nestedPath);
                    foreach ($nestedSubCollections as $nestedSubCollectionSlug) {
                        $nestedSubCollectionPath = "{$nestedPath}/{$nestedSubCollectionSlug}";
                        searchSubEntriesRecursive($storage, $query, $queryLower, $parentCollection, $parentEntryId, $nestedSubCollectionSlug, $nestedSubCollectionPath, $subCollectionSlug, $subEntryFile, $results);
                    }
                } catch (Exception $e) {
                    // Not a nested sub-entry directory, continue
                }
                continue;
            }
            
            $subEntryPath = "{$subCollectionPath}/{$subEntryFile}";
            $subEntry = $storage->read($subEntryPath);
            
            if (!$subEntry) continue;
            
            $subEntryId = basename($subEntryFile, '.json');
            $title = $subEntry['title'] ?? $subEntry['__cells']['__title'] ?? $subEntryId;
            
            $score = 0;
            $matched = false;
            
            if (strtolower($title) === $queryLower) {
                $score = 1.0;
                $matched = true;
            } elseif (stripos($title, $query) === 0) {
                $score = 0.9;
                $matched = true;
            } elseif (stripos($title, $query) !== false) {
                $score = 0.8;
                $matched = true;
            } else {
                // Search in content fields
                if (isset($subEntry['__cells'])) {
                    foreach ($subEntry['__cells'] as $fieldName => $fieldValue) {
                        if (is_string($fieldValue) && stripos($fieldValue, $query) !== false) {
                            $score = 0.6;
                            $matched = true;
                            break;
                        }
                    }
                }
            }
            
            if ($matched) {
                $collectionName = getCollectionName($storage, $parentCollection);
                $parentTitle = getEntryTitle($storage, $parentCollection, $parentEntryId);
                
                $urlParams = [
                    'collection' => $parentCollection,
                    'entry' => $parentEntryId,
                    'sub_collection' => $subCollectionSlug,
                    'sub_entry' => $subEntryId,
                ];
                if ($parentSubCollection && $parentSubEntryId) {
                    $urlParams['parent_sub_collection'] = $parentSubCollection;
                    $urlParams['parent_sub_entry'] = $parentSubEntryId;
                }
                $url = birkly_admin_url('entry-detail', $urlParams);
                
                $subtitle = "{$subCollectionSlug} • {$collectionName}";
                if ($parentSubCollection) {
                    $subtitle = "{$subCollectionSlug} (nested) • {$collectionName}";
                }
                
                $results[] = [
                    'type' => 'sub_entry',
                    'id' => $subEntryId,
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'url' => $url,
                    'icon' => '💬',
                    'match_score' => $score
                ];
            }
        }
    } catch (Exception $e) {
        // Silently continue if directory doesn't exist
    }
}

/**
 * Search media files
 */
function searchMedia($query, $limit) {
    $results = [];
    $queryLower = strtolower($query);
    $mediaDir = __DIR__ . '/../media';
    
    if (!is_dir($mediaDir)) {
        return $results;
    }
    
    try {
        $metadataPath = $mediaDir . '/.metadata.json';
        $metadata = [];
        
        if (file_exists($metadataPath)) {
            $metadata = json_decode(file_get_contents($metadataPath), true) ?: [];
        }
        
        foreach ($metadata as $id => $item) {
            $filename = $item['filename'] ?? $id;
            $path = $item['path'] ?? '';
            $type = $item['type'] ?? 'file';
            $size = $item['size'] ?? 0;
            
            $score = 0;
            $matched = false;
            
            if (stripos($filename, $query) !== false) {
                $score = stripos(strtolower($filename), $queryLower) === 0 ? 0.9 : 0.8;
                $matched = true;
            } elseif (stripos($path, $query) !== false) {
                $score = 0.7;
                $matched = true;
            }
            
            if ($matched) {
                $sizeFormatted = formatFileSize($size);
                $typeLabel = ucfirst($type);
                
                $results[] = [
                    'type' => 'media',
                    'id' => $id,
                    'title' => $filename,
                    'subtitle' => "{$typeLabel} • {$sizeFormatted}",
                    'url' => birkly_admin_url('media', ['file' => $id]),
                    'icon' => getMediaIcon($type),
                    'match_score' => $score
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Search media error: " . $e->getMessage());
    }
    
    return array_slice($results, 0, $limit);
}

/**
 * Search users (admin only)
 */
function searchUsers($query, $limit) {
    $results = [];
    $queryLower = strtolower($query);
    $usersDir = __DIR__ . '/../users';
    
    if (!is_dir($usersDir)) {
        return $results;
    }
    
    try {
        $userFiles = scandir($usersDir);
        foreach ($userFiles as $userFile) {
            if (pathinfo($userFile, PATHINFO_EXTENSION) !== 'json') continue;
            
            $userPath = $usersDir . '/' . $userFile;
            $user = json_decode(file_get_contents($userPath), true);
            
            if (!$user) continue;
            
            $email = $user['email'] ?? '';
            $name = $user['name'] ?? '';
            $role = $user['role'] ?? 'viewer';
            
            $score = 0;
            $matched = false;
            
            if (stripos($email, $query) !== false) {
                $score = stripos(strtolower($email), $queryLower) === 0 ? 0.9 : 0.8;
                $matched = true;
            } elseif ($name && stripos($name, $query) !== false) {
                $score = 0.7;
                $matched = true;
            }
            
            if ($matched) {
                $results[] = [
                    'type' => 'user',
                    'id' => $email,
                    'title' => $name ?: $email,
                    'subtitle' => "{$email} • " . ucfirst($role),
                    'url' => birkly_admin_url('settings', ['tab' => 'users']),
                    'icon' => '👤',
                    'match_score' => $score
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Search users error: " . $e->getMessage());
    }
    
    return array_slice($results, 0, $limit);
}

/**
 * Search plugin-provided content
 */
function searchPluginContent($query, $limit) {
    $results = [];
    
    // Allow plugins to register searchable content via hook
    if (function_exists('birkly_trigger_event')) {
        $pluginResults = birkly_trigger_event('search_content', [
            'query' => $query,
            'limit' => $limit
        ]);
        
        if (is_array($pluginResults)) {
            foreach ($pluginResults as $result) {
                if (isset($result['type']) && isset($result['title']) && isset($result['url'])) {
                    $results[] = array_merge([
                        'type' => 'plugin',
                        'icon' => '🔌',
                        'match_score' => $result['match_score'] ?? 0.5
                    ], $result);
                }
            }
        }
    }
    
    return array_slice($results, 0, $limit);
}

// Helper functions

function getCollectionName($storage, $collectionSlug) {
    try {
        $collectionPath = "collections/{$collectionSlug}/collection.json";
        $collection = $storage->read($collectionPath);
        return $collection['name'] ?? $collection['title'] ?? $collectionSlug;
    } catch (Exception $e) {
        return $collectionSlug;
    }
}

function getEntryTitle($storage, $collectionSlug, $entryId) {
    try {
        $entryPath = "collections/{$collectionSlug}/entries/{$entryId}.json";
        $entry = $storage->read($entryPath);
        return $entry['title'] ?? $entry['__cells']['__title'] ?? $entryId;
    } catch (Exception $e) {
        return $entryId;
    }
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function getMediaIcon($type) {
    $icons = [
        'image' => '🖼️',
        'video' => '🎥',
        'audio' => '🎵',
        'document' => '📄',
        'pdf' => '📕',
        'archive' => '📦'
    ];
    return $icons[$type] ?? '📎';
}

