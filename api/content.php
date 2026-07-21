<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
header('Content-Type: application/json');

require_once '../core/storage/StorageFactory.php';
require_once '../api/session.php';
require_once 'auth.php';
require_once '../core/plugins.php';
require_once '../core/cache.php';
require_once 'validate.php';
require_once '../core/csrf.php';
require_once '../core/collection_schema.php';
require_once '../core/collection_groups.php';
require_once 'log.php';

// Use admin session for content API (must be before auth check)
// haru_secure_session_start() is called inside requireAuth(), but we call it here too to ensure session is ready
haru_secure_session_start();

// M2-T1: Admin content API requires authentication. Public read uses api/public.php
// (published entries only). Unauthenticated GET previously exposed drafts.
requireAuth('manage_content');

$storage = get_storage('../content');
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

// Handle collections
if ($action === 'list_collections') {
    global $birkly_collections;
    // Populate $birkly_collections with core collections
    $collections = $storage->list('collections');
    foreach ($collections as $dir) {
        $meta = $storage->read("collections/$dir/collection.json");
        if (!$meta) {
            $meta = [
                'id' => 'col-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $dir)),
                'title' => $dir,
                'slug' => strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $dir)),
            ];
            $storage->write("collections/$dir/collection.json", $meta);
        }
        $birkly_collections[$meta['slug']] = [
            'path' => realpath("../content/collections/$dir"),
            'options' => []
        ];
    }
    // Now build result from all registered collections (core + plugins)
    $result = [];
    foreach ($birkly_collections as $slug => $info) {
        if (!empty($info['options']['hidden'])) continue; // skip hidden collections
        $path = $info['path'];
        $metaFile = rtrim($path, '/').'/collection.json';
        if (file_exists($metaFile)) {
            $meta = json_decode(file_get_contents($metaFile), true);
            if ($meta) {
                // Add entries_count including sub-entries
                $entriesDir = rtrim($path, '/') . '/entries';
                $entriesCount = 0;
                if (file_exists($entriesDir) && is_dir($entriesDir)) {
                    $entriesCount = count(array_filter(scandir($entriesDir), function($f) use ($entriesDir) {
                        return $f !== '.' && $f !== '..' && is_file($entriesDir . '/' . $f) && pathinfo($f, PATHINFO_EXTENSION) === 'json';
                    }));
                }
                
                // Count sub-entries if collection has sub-collections
                $subEntriesCount = 0;
                if (!empty($meta['sub_collections']) && is_array($meta['sub_collections'])) {
                    require_once __DIR__ . '/../core/sub_entries.php';
                    require_once __DIR__ . '/../core/storage/StorageFactory.php';
                    
                    $collectionSlug = $meta['slug'] ?? basename($path);
                    $entryFiles = glob($entriesDir . '/*.json');
                    
                    if ($entryFiles) {
                        $storage = get_storage();
                        foreach ($entryFiles as $entryFile) {
                            $entryId = basename($entryFile, '.json');
                            foreach ($meta['sub_collections'] as $subCollection) {
                                $subCollectionSlug = $subCollection['slug'] ?? '';
                                if ($subCollectionSlug) {
                                    try {
                                        $subCount = countSubEntries($collectionSlug, $entryId, $subCollectionSlug);
                                        $subEntriesCount += $subCount;
                                        
                                        // Count nested sub-entries recursively
                                        if (!empty($subCollection['sub_collections'])) {
                                            $subEntryPath = "collections/{$collectionSlug}/entries/{$entryId}/sub_entries/{$subCollectionSlug}";
                                            try {
                                                $subEntryFiles = $storage->list($subEntryPath);
                                                foreach ($subEntryFiles as $subEntryFile) {
                                                    if (pathinfo($subEntryFile, PATHINFO_EXTENSION) === 'json') {
                                                        $subEntryId = basename($subEntryFile, '.json');
                                                        foreach ($subCollection['sub_collections'] as $nestedSubCollection) {
                                                            $nestedSlug = $nestedSubCollection['slug'] ?? '';
                                                            if ($nestedSlug) {
                                                                $nestedPath = "collections/{$collectionSlug}/entries/{$entryId}/sub_entries/{$subCollectionSlug}/{$subEntryId}/sub_entries/{$nestedSlug}";
                                                                try {
                                                                    $nestedFiles = $storage->list($nestedPath);
                                                                    $subEntriesCount += count(array_filter($nestedFiles, function($f) {
                                                                        return pathinfo($f, PATHINFO_EXTENSION) === 'json';
                                                                    }));
                                                                } catch (Exception $e) {
                                                                    // Path doesn't exist, continue
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                            } catch (Exception $e) {
                                                // Path doesn't exist, continue
                                            }
                                        }
                                    } catch (Exception $e) {
                                        // Continue with next sub-collection
                                    }
                                }
                            }
                        }
                    }
                }
                
                $meta['entries_count'] = $entriesCount;
                $meta['total_entries'] = $entriesCount + $subEntriesCount;
                $result[] = $meta;
            }
        }
    }
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'collections' => $result
        ]
    ]);
    exit;
}

if ($action === 'create_collection') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'create_collection', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    requireAuth('manage_content');
    $title = trim($input['title'] ?? '');
    if (empty($title)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection title required']);
        exit;
    }
    // Generate slug
    function slugify($str) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $str)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }
    $slug = slugify($title);
    // Ensure slug and title uniqueness
    $collections = $storage->list('collections');
    $existingTitles = [];
    $existingSlugs = [];
    foreach ($collections as $dir) {
        $meta = $storage->read("collections/$dir/collection.json");
        if ($meta) {
            $existingTitles[] = strtolower($meta['title']);
            $existingSlugs[] = $meta['slug'];
        } else {
            $existingTitles[] = strtolower($dir);
            $existingSlugs[] = slugify($dir);
        }
    }
    $baseSlug = $slug;
    $i = 2;
    while (in_array($slug, $existingSlugs)) {
        $slug = $baseSlug . '-' . $i;
        $i++;
    }
    if (in_array(strtolower($title), $existingTitles)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection title must be unique']);
        exit;
    }
    $id = 'col-' . uniqid();
    $dir = $slug;
    // Create collection directory
    if (!file_exists("../content/collections/$dir")) {
        mkdir("../content/collections/$dir/entries", 0755, true);
    }
    $meta = [ 'id' => $id, 'title' => $title, 'slug' => $slug ];
    $storage->write("collections/$dir/collection.json", $meta);
    $schema = birkly_normalize_collection_schema($input);
    $storage->write("collections/$dir/schema.json", $schema);
    log_security_event('create_collection', ['id' => $id, 'title' => $title, 'slug' => $slug]);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Collection created',
        'data' => $meta
    ]);
    exit;
}

if ($action === 'update_collection') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'update_collection', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    requireAuth('manage_content');
    $id = $input['id'] ?? '';
    $title = trim($input['title'] ?? '');
    $slug = trim($input['slug'] ?? '');
    $schema = $input['schema'] ?? [];
    if (empty($id) || empty($title) || empty($slug) || empty($schema)) {
        echo json_encode(['status' => 'error', 'message' => 'ID, title, slug, and schema required']);
        exit;
    }
    // Find collection dir by id
    $collections = $storage->list('collections');
    $dir = null;
    foreach ($collections as $d) {
        $meta = $storage->read("collections/$d/collection.json");
        if ($meta && $meta['id'] === $id) {
            $dir = $d;
            break;
        }
    }
    if (!$dir) {
        echo json_encode(['status' => 'error', 'message' => 'Collection not found']);
        exit;
    }
    // Ensure title and slug uniqueness (ignore current collection)
    $existingTitles = [];
    $existingSlugs = [];
    foreach ($collections as $d) {
        if ($d === $dir) continue;
        $meta = $storage->read("collections/$d/collection.json");
        if ($meta) {
            $existingTitles[] = strtolower($meta['title']);
            $existingSlugs[] = $meta['slug'];
        }
    }
    if (in_array(strtolower($title), $existingTitles)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection title must be unique']);
        exit;
    }
    if (in_array($slug, $existingSlugs)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection slug must be unique']);
        exit;
    }
    // Update metadata and schema
    $meta = [ 'id' => $id, 'title' => $title, 'slug' => $slug ];
    $storage->write("collections/$dir/collection.json", $meta);
    $storage->write("collections/$dir/schema.json", $schema);
    log_security_event('update_collection', ['id' => $id, 'title' => $title, 'slug' => $slug]);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Collection updated',
        'data' => $meta
    ]);
    exit;
}

if ($action === 'delete_collection') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'delete_collection', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    requireAuth('manage_content');
    $id = $input['id'] ?? '';
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection id required']);
        exit;
    }
    // Find collection dir by id
    $collections = $storage->list('collections');
    $dir = null;
    foreach ($collections as $d) {
        $meta = $storage->read("collections/$d/collection.json");
        if ($meta) {
            // Check both id field and generated id from slug
            $metaId = $meta['id'] ?? 'col-' . $d;
            if ($metaId === $id || $d === $id || (strpos($id, 'col-') === 0 && $d === substr($id, 4))) {
                $dir = $d;
                break;
            }
        }
    }
    if (!$dir) {
        echo json_encode(['status' => 'error', 'message' => 'Collection not found']);
        exit;
    }
    // Delete all entries and schema/meta
    $entries = $storage->list("collections/$dir/entries");
    foreach ($entries as $entry) {
        $storage->delete("collections/$dir/entries/$entry");
    }
    $storage->delete("collections/$dir/schema.json");
    $storage->delete("collections/$dir/collection.json");
    // Remove the collection directory if empty
    @rmdir("../content/collections/$dir/entries");
    @rmdir("../content/collections/$dir");
    log_security_event('delete_collection', ['id' => $id]);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Collection deleted'
    ]);
    exit;
}

if ($action === 'duplicate_collection') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'duplicate_collection', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    requireAuth('manage_content');
    $id = $input['id'] ?? '';
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection id required']);
        exit;
    }
    
    // Find source collection dir by id
    $collections = $storage->list('collections');
    $sourceDir = null;
    $sourceMeta = null;
    foreach ($collections as $d) {
        $meta = $storage->read("collections/$d/collection.json");
        if ($meta) {
            // Check both id field and generated id from slug
            $metaId = $meta['id'] ?? 'col-' . $d;
            if ($metaId === $id || $d === $id || (strpos($id, 'col-') === 0 && $d === substr($id, 4))) {
                $sourceDir = $d;
                $sourceMeta = $meta;
                break;
            }
        }
    }
    if (!$sourceDir || !$sourceMeta) {
        echo json_encode(['status' => 'error', 'message' => 'Collection not found']);
        exit;
    }
    
    // Generate slug for duplicate
    function slugify($str) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $str)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }
    
    $baseSlug = slugify($sourceMeta['title'] ?? $sourceMeta['name'] ?? 'collection') . '-copy';
    $newSlug = $baseSlug;
    $i = 2;
    while (in_array($newSlug, array_map(function($d) use ($storage) {
        $m = $storage->read("collections/$d/collection.json");
        return $m['slug'] ?? $d;
    }, $collections))) {
        $newSlug = $baseSlug . '-' . $i;
        $i++;
    }
    
    // Create new collection directory
    $newDir = $newSlug;
    $newDirPath = "../content/collections/$newDir";
    if (!file_exists($newDirPath)) {
        mkdir($newDirPath . '/entries', 0755, true);
    }
    
    // Copy collection metadata
    $newMeta = $sourceMeta;
    $newMeta['id'] = 'col-' . uniqid();
    $newMeta['slug'] = $newSlug;
    $newMeta['title'] = ($sourceMeta['title'] ?? $sourceMeta['name'] ?? 'Collection') . ' (Copy)';
    if (isset($newMeta['name'])) {
        $newMeta['name'] = $newMeta['title'];
    }
    $newMeta['created_at'] = date('Y-m-d H:i:s');
    $newMeta['updated_at'] = date('Y-m-d H:i:s');
    $storage->write("collections/$newDir/collection.json", $newMeta);
    
    // Copy schema
    $sourceSchema = $storage->read("collections/$sourceDir/schema.json");
    if ($sourceSchema) {
        $storage->write("collections/$newDir/schema.json", $sourceSchema);
    }
    
    // Note: We don't copy entries, just the collection structure
    log_security_event('duplicate_collection', ['id' => $id, 'new_id' => $newMeta['id']]);
    echo json_encode([
        'status' => 'ok',
        'message' => 'Collection duplicated',
        'data' => $newMeta
    ]);
    exit;
}

// Handle entries
if ($action === 'list_entries') {
    $collection = $input['collection'] ?? $_GET['collection'] ?? '';
    $id = $input['id'] ?? $_GET['id'] ?? '';
    $dir = '';
    $collections = $storage->list('collections');
    if ($id) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && $m['id'] === $id) {
                $dir = $d;
                break;
            }
        }
    } elseif ($collection) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && $m['slug'] === $collection) {
                $dir = $d;
                break;
            }
        }
    }
    if (!$dir) {
        echo json_encode(['status' => 'error', 'message' => 'Collection not found']);
        exit;
    }
    
    // Get collection slug for caching
    $collectionSlug = '';
    $collectionMeta = $storage->read("collections/$dir/collection.json");
    if ($collectionMeta && isset($collectionMeta['slug'])) {
        $collectionSlug = $collectionMeta['slug'];
    }
    
    // Try to get cached entries
    $cache = new Cache();
    $cachedEntries = null;
    if ($collectionSlug) {
        $cachedEntries = $cache->getEntries($collectionSlug);
    }
    
    if ($cachedEntries === null) {
        // Cache miss - load entries from files
        $entries = $storage->list("collections/$dir/entries");
        $entryData = [];
        foreach ($entries as $entry) {
            $data = $storage->read("collections/$dir/entries/$entry");
            if ($data) {
                $entryData[] = $data;
            }
        }
        
        // Cache entries if we have a slug
        if ($collectionSlug) {
            $cache->setEntries($collectionSlug, $entryData);
        }
        
        $cachedEntries = $entryData;
    }
    
    // Apply order if order.json exists
    $orderFile = "collections/$dir/order.json";
    if (file_exists("../content/$orderFile")) {
        $order = json_decode(file_get_contents("../content/$orderFile"), true);
        if (is_array($order)) {
            $entryDataById = [];
            foreach ($cachedEntries as $e) {
                if (isset($e['id'])) $entryDataById[$e['id']] = $e;
            }
            $ordered = [];
            foreach ($order as $id) {
                if (isset($entryDataById[$id])) {
                    $ordered[] = $entryDataById[$id];
                    unset($entryDataById[$id]);
                }
            }
            // Append any new entries not in order.json
            foreach ($entryDataById as $e) {
                $ordered[] = $e;
            }
            $cachedEntries = $ordered;
        }
    }
    
    // Apply permission filtering if user management plugin is installed and active
    if (birkly_plugin_is_installed_and_active('user_management')
        && birkly_require_plugin_file('user_management', 'includes/EntryPermissions.php')) {
        $entryPermissions = new EntryPermissions();
        $currentUserId = $entryPermissions->getCurrentUserId();

        // Filter entries by view permission
        $cachedEntries = $entryPermissions->filterEntriesByViewPermission($currentUserId, $cachedEntries);
    }
    
    // Get language parameter
    $language = $input['language'] ?? $_GET['language'] ?? 'en';
    
    // Get collection metadata
    $collectionMeta = $storage->read("collections/$dir/collection.json");
    $multilanguageEnabled = !empty($collectionMeta['multilanguage']['enabled']);
    $multilanguageLanguages = $collectionMeta['multilanguage']['languages'] ?? [];
    
    // Validate language
    if ($multilanguageEnabled && !empty($multilanguageLanguages)) {
        if (!in_array($language, $multilanguageLanguages)) {
            $language = $multilanguageLanguages[0] ?? 'en'; // Fallback to first available language
        }
    } else {
        $language = null; // No multi-language, use original structure
    }
    
    // Transform entries for language-specific content
    if ($language && $multilanguageEnabled) {
        $transformedEntries = array_map(function($entry) use ($language, $multilanguageLanguages) {
            // Check if entry has multi-language structure
            $hasMultilang = false;
            foreach ($multilanguageLanguages as $lang) {
                if (isset($entry[$lang]) && is_array($entry[$lang])) {
                    $hasMultilang = true;
                    break;
                }
            }
            
            if ($hasMultilang && isset($entry[$language])) {
                // Merge language-specific content with entry metadata
                $transformed = array_merge($entry, $entry[$language]);
                // Keep metadata fields
                $transformed['id'] = $entry['id'] ?? '';
                $transformed['slug'] = $entry['slug'] ?? '';
                $transformed['status'] = $entry['status'] ?? 'draft';
                $transformed['created_at'] = $entry['created_at'] ?? '';
                $transformed['updated_at'] = $entry['updated_at'] ?? '';
                $transformed['published_at'] = $entry['published_at'] ?? '';
                return $transformed;
            }
            
            // No multi-language structure, return as-is
            return $entry;
        }, $cachedEntries);
        
        echo json_encode([
            'status' => 'ok',
            'data' => [ 'entries' => $transformedEntries ]
        ]);
    } else {
        // No multi-language, return entries as-is
        echo json_encode([
            'status' => 'ok',
            'data' => [ 'entries' => $cachedEntries ]
        ]);
    }
    exit;
}

if ($action === 'create_entry') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'create_entry', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    requireAuth('manage_content');
    $collection = $input['collection'] ?? '';
    $data = $input['data'] ?? [];
    $dir = '';
    $collections = $storage->list('collections');
    foreach ($collections as $d) {
        $m = $storage->read("collections/$d/collection.json");
        if ($m && $m['id'] === $collection) {
            $dir = $d;
            break;
        }
    }
    if (!$dir) {
        echo json_encode(['status' => 'error', 'message' => 'Collection not found']);
        exit;
    }
    if (empty($dir) || empty($data)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection and data required']);
        exit;
    }
    // Generate entry ID first
    $id = uniqid();
    // Auto-fill title with entry ID if not provided
    $title = trim($data['title'] ?? '');
    if ($title === '') {
        $title = $id;
    }
    // Generate slug
    function slugify($str) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $str)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }
    $slug = slugify($title);
    // Ensure slug uniqueness within collection
    $entries = $storage->list("collections/$dir/entries");
    $existingSlugs = [];
    foreach ($entries as $entryFile) {
        $entryData = $storage->read("collections/$dir/entries/$entryFile");
        if ($entryData && isset($entryData['slug'])) {
            $existingSlugs[] = $entryData['slug'];
        }
    }
    $baseSlug = $slug;
    $i = 2;
    while (in_array($slug, $existingSlugs)) {
        $slug = $baseSlug . '-' . $i;
        $i++;
    }
    // --- WYSIWYG HTML fix: build $cleanData ---
    $schema = $storage->read("collections/$dir/schema.json");
    $cleanData = [];
    foreach (
        $schema['fields'] as $field
    ) {
        $name = $field['name'];
        if ($field['type'] === 'wysiwyg' || $field['type'] === 'editor' || $field['type'] === 'media') {
            $cleanData[$name] = $data[$name] ?? '';
        } else if ($field['type'] === 'dynamicitems') {
            $cleanData[$name] = $data[$name] ?? [];
        } else {
            $cleanData[$name] = sanitize_string($data[$name] ?? '');
        }
    }
    // Add system fields
    $cleanData['id'] = $id;
    $cleanData['slug'] = $slug;
    $cleanData['created_at'] = date('c');
    $cleanData['updated_at'] = date('c');
    $cleanData['title'] = $title;
    
    // Add status and metadata
    if (isset($data['status'])) {
        $cleanData['status'] = $data['status'];
    } else {
        $cleanData['status'] = 'unpublished'; // Default status for new entries
    }
    if (isset($data['published_at'])) {
        $cleanData['published_at'] = $data['published_at'];
    }
    if (isset($data['expires_at'])) {
        $cleanData['expires_at'] = $data['expires_at'];
    }
    if (isset($data['meta'])) {
        $cleanData['meta'] = $data['meta'];
    }
    $result = $storage->write("collections/$dir/entries/$id.json", $cleanData);
    if ($result) do_action('after_content_save', $dir, $cleanData);
    log_security_event('create_entry', ['collection' => $dir, 'id' => $id]);
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'Entry created' : 'Failed to create entry',
        'data' => ['id' => $id, 'slug' => $slug, 'title' => $title]
    ]);
    exit;
}

if ($action === 'update_entry') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'update_entry', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    requireAuth('manage_content');
    $collection = $input['collection'] ?? '';
    $id = $input['id'] ?? '';
    $data = $input['data'] ?? [];
    $dir = '';
    $collections = $storage->list('collections');
    foreach ($collections as $d) {
        $m = $storage->read("collections/$d/collection.json");
        if ($m && $m['id'] === $collection) {
            $dir = $d;
            break;
        }
    }
    if (!$dir) {
        echo json_encode(['status' => 'error', 'message' => 'Collection not found']);
        exit;
    }
    if (empty($dir) || empty($id) || empty($data)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection, ID and data required']);
        exit;
    }
    $existing = $storage->read("collections/$dir/entries/$id.json");
    if (!$existing) {
        echo json_encode(['status' => 'error', 'message' => 'Entry not found']);
        exit;
    }
    // Auto-fill title with entry ID if not provided
    $title = trim($data['title'] ?? '');
    if ($title === '') {
        $title = $id;
    }
    // Generate slug
    function slugify($str) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $str)));
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug;
    }
    $slug = slugify($title);
    // Ensure slug uniqueness within collection (ignore current entry)
    $entries = $storage->list("collections/$dir/entries");
    $existingSlugs = [];
    foreach ($entries as $entryFile) {
        if ($entryFile === "$id.json") continue;
        $entryData = $storage->read("collections/$dir/entries/$entryFile");
        if ($entryData && isset($entryData['slug'])) {
            $existingSlugs[] = $entryData['slug'];
        }
    }
    $baseSlug = $slug;
    $i = 2;
    while (in_array($slug, $existingSlugs)) {
        $slug = $baseSlug . '-' . $i;
        $i++;
    }
    // --- WYSIWYG HTML fix: build $cleanData ---
    $schema = $storage->read("collections/$dir/schema.json");
    $cleanData = [];
    foreach (
        $schema['fields'] as $field
    ) {
        $name = $field['name'];
        if ($field['type'] === 'wysiwyg' || $field['type'] === 'editor' || $field['type'] === 'media') {
            $cleanData[$name] = $data[$name] ?? '';
        } else if ($field['type'] === 'dynamicitems') {
            $cleanData[$name] = $data[$name] ?? [];
        } else {
            $cleanData[$name] = sanitize_string($data[$name] ?? '');
        }
    }
    // Add system fields
    $cleanData['id'] = $id;
    $cleanData['slug'] = $slug;
    $cleanData['created_at'] = $existing['created_at'];
    $cleanData['updated_at'] = date('c');
    $cleanData['title'] = $title;
    
    // Add status and metadata
    if (isset($data['status'])) {
        $cleanData['status'] = $data['status'];
    }
    if (isset($data['published_at'])) {
        $cleanData['published_at'] = $data['published_at'];
    }
    if (isset($data['expires_at'])) {
        $cleanData['expires_at'] = $data['expires_at'];
    }
    if (isset($data['meta'])) {
        $cleanData['meta'] = $data['meta'];
    }
    $result = $storage->write("collections/$dir/entries/$id.json", $cleanData);
    if ($result) do_action('after_content_save', $dir, $cleanData);
    log_security_event('update_entry', ['collection' => $dir, 'id' => $id]);
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'Entry updated' : 'Failed to update entry',
        'data' => ['id' => $id, 'slug' => $slug, 'title' => $title]
    ]);
    exit;
}

if ($action === 'delete_entry') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'delete_entry', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    requireAuth('manage_content');
    $collection = $input['collection'] ?? '';
    $id = $input['id'] ?? '';
    $dir = '';
    $collections = $storage->list('collections');
    
    // Try to find collection by ID first, then by slug (like other endpoints)
    foreach ($collections as $d) {
        $m = $storage->read("collections/$d/collection.json");
        if ($m) {
            // Check by ID
            if (isset($m['id']) && $m['id'] === $collection) {
                $dir = $d;
                break;
            }
            // Check by slug
            if (isset($m['slug']) && $m['slug'] === $collection) {
                $dir = $d;
                break;
            }
        }
    }
    
    if (!$dir) {
        echo json_encode(['status' => 'error', 'message' => 'Collection not found']);
        exit;
    }
    if (empty($dir) || empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Collection and ID required']);
        exit;
    }
    $result = $storage->delete("collections/$dir/entries/$id.json");
    log_security_event('delete_entry', ['collection' => $dir, 'id' => $id]);
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'Entry deleted' : 'Failed to delete entry'
    ]);
    exit;
}

if ($action === 'set_entry_order') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'set_entry_order', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    requireAuth('manage_content');
    $collection = $input['collection'] ?? '';
    $order = $input['order'] ?? [];
    $dir = '';
    $collections = $storage->list('collections');
    foreach ($collections as $d) {
        $m = $storage->read("collections/$d/collection.json");
        if ($m && ($m['id'] === $collection || $m['slug'] === $collection)) {
            $dir = $d;
            break;
        }
    }
    if (!$dir) {
        echo json_encode(['status' => 'error', 'message' => 'Collection not found']);
        exit;
    }
    if (!is_array($order)) {
        echo json_encode(['status' => 'error', 'message' => 'Order must be an array']);
        exit;
    }
    $result = $storage->write("collections/$dir/order.json", $order);
    log_security_event('set_entry_order', ['collection' => $dir, 'order_count' => count($order)]);
    
    if ($result) {
        echo json_encode(['status' => 'ok', 'message' => 'Entry order saved successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save entry order']);
    }
    exit;
}

if ($action === 'get_schema') {
    $collection = $input['collection'] ?? '';
    $id = $input['id'] ?? '';
    $dir = '';
    $meta = null;
    $collections = $storage->list('collections');
    if ($id) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && $m['id'] === $id) {
                $dir = $d;
                $meta = $m;
                break;
            }
        }
    } elseif ($collection) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && $m['slug'] === $collection) {
                $dir = $d;
                $meta = $m;
                break;
            }
        }
    }
    if (!$dir) {
        echo json_encode(['status' => 'error', 'message' => 'Collection not found']);
        exit;
    }
    $schema = $storage->read("collections/$dir/schema.json") ?? [];
    echo json_encode([
        'status' => 'ok',
        'data' => [ 'schema' => $schema, 'collection' => $meta ]
    ]);
    exit;
}

if ($action === 'get_entry_with_children') {
    $id = $input['id'] ?? $_GET['id'] ?? '';
    $collection = $input['collection'] ?? $_GET['collection'] ?? '';
    $include_children = isset($input['include_children']) ? (bool)$input['include_children'] : (isset($_GET['include_children']) ? (bool)$_GET['include_children'] : true);
    $max_depth = isset($input['max_depth']) ? (int)$input['max_depth'] : (isset($_GET['max_depth']) ? (int)$_GET['max_depth'] : 3);
    $sub_collections = $input['sub_collections'] ?? $_GET['sub_collections'] ?? null;
    
    if (empty($id) || empty($collection)) {
        respond(['status' => 'error', 'message' => 'ID and collection required']);
    }
    
    // Find collection directory
    $dir = '';
    $collections = $storage->list('collections');
    foreach ($collections as $d) {
        $m = $storage->read("collections/$d/collection.json");
        if ($m && ($m['slug'] === $collection || $m['id'] === $collection)) {
            $dir = $d;
            break;
        }
    }
    
    if (!$dir) {
        respond(['status' => 'error', 'message' => 'Collection not found']);
    }
    
    $entry = $storage->read("collections/{$dir}/entries/{$id}.json");
    if (!$entry) {
        respond(['status' => 'error', 'message' => 'Entry not found']);
    }
    
    $result = ['entry' => $entry];
    
    if ($include_children) {
        require_once __DIR__ . '/../core/sub_entries.php';
        
        $children_data = [];
        $collection_config = $storage->read("collections/{$dir}/collection.json");
        $sub_cols = $collection_config['sub_collections'] ?? [];
        
        // Filter sub-collections if specified
        if ($sub_collections && is_string($sub_collections)) {
            $sub_collections = explode(',', $sub_collections);
        }
        
        foreach ($sub_cols as $sub_col) {
            $sub_col_slug = $sub_col['slug'] ?? '';
            if (!$sub_col_slug) continue;
            
            // Filter if specific sub-collections requested
            if ($sub_collections && !in_array($sub_col_slug, $sub_collections)) {
                continue;
            }
            
            $children = getSubEntries($collection, $id, $sub_col_slug, [], []);
            
            // Recursively get nested children if depth allows
            if ($max_depth > 1) {
                foreach ($children as &$child) {
                    $nested = getDescendants($collection, $child['id'], $sub_col_slug, $max_depth - 1);
                    if (!empty($nested)) {
                        $child['children'] = $nested;
                    }
                }
            }
            
            $children_data[$sub_col_slug] = $children;
        }
        
        $result['children'] = $children_data;
    }
    
    respond(['status' => 'ok', 'data' => $result]);
    exit;
}

if ($action === 'get_entry') {
    $collection = $input['collection'] ?? $_GET['collection'] ?? '';
    $entryId = $input['id'] ?? $_GET['id'] ?? '';
    $slug = $input['slug'] ?? $_GET['slug'] ?? '';
    $language = $input['language'] ?? $_GET['language'] ?? 'en'; // Default to 'en'
    $dir = '';
    $collections = $storage->list('collections');
    
    // Find collection by ID
    if ($collection) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && $m['id'] === $collection) {
                $dir = $d;
                break;
            }
        }
    }
    
    if (!$dir) {
        echo json_encode(['status' => 'error', 'message' => 'Collection not found']);
        exit;
    }
    
    // Get collection metadata
    $collectionMeta = $storage->read("collections/$dir/collection.json");
    $multilanguageEnabled = !empty($collectionMeta['multilanguage']['enabled']);
    $multilanguageLanguages = $collectionMeta['multilanguage']['languages'] ?? [];
    
    // Validate language
    if ($multilanguageEnabled && !empty($multilanguageLanguages)) {
        if (!in_array($language, $multilanguageLanguages)) {
            $language = $multilanguageLanguages[0] ?? 'en'; // Fallback to first available language
        }
    } else {
        $language = null; // No multi-language, use original structure
    }
    
    // Find entry by slug or ID
    $entry = null;
    if ($slug) {
        // Search by slug
        $entries = $storage->list("collections/$dir/entries");
        foreach ($entries as $entryFile) {
            $entryData = $storage->read("collections/$dir/entries/$entryFile");
            if ($entryData && isset($entryData['slug']) && $entryData['slug'] === $slug) {
                $entry = $entryData;
                break;
            }
        }
    } elseif ($entryId) {
        // Search by entry ID
        $entry = $storage->read("collections/$dir/entries/$entryId.json");
    }
    
    if (!$entry) {
        echo json_encode(['status' => 'error', 'message' => 'Entry not found']);
        exit;
    }
    
    // Transform entry for language-specific content
    if ($language && $multilanguageEnabled) {
        // Check if entry has multi-language structure
        $hasMultilang = false;
        foreach ($multilanguageLanguages as $lang) {
            if (isset($entry[$lang]) && is_array($entry[$lang])) {
                $hasMultilang = true;
                break;
            }
        }
        
        if ($hasMultilang && isset($entry[$language])) {
            // Merge language-specific content with entry metadata
            $transformed = array_merge($entry, $entry[$language]);
            // Keep metadata fields
            $transformed['id'] = $entry['id'] ?? '';
            $transformed['slug'] = $entry['slug'] ?? '';
            $transformed['status'] = $entry['status'] ?? 'draft';
            $transformed['created_at'] = $entry['created_at'] ?? '';
            $transformed['updated_at'] = $entry['updated_at'] ?? '';
            $transformed['published_at'] = $entry['published_at'] ?? '';
            $entry = $transformed;
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'data' => [ 'entry' => $entry ]
    ]);
    exit;
}

if ($action === 'get_entry_titles') {
    $ids = $input['ids'] ?? $_GET['ids'] ?? [];
    if (is_string($ids)) {
        // Handle JSON string from GET parameter
        $decoded = json_decode($ids, true);
        $ids = is_array($decoded) ? $decoded : [$ids];
    }
    if (!is_array($ids)) $ids = [$ids];
    $result = [];
    $collections = $storage->list('collections');
    foreach ($ids as $id) {
        foreach ($collections as $d) {
            $entry = $storage->read("collections/$d/entries/$id.json");
            if ($entry) {
                $result[] = [ 'id' => $id, 'title' => $entry['title'] ?? $id ];
                break;
            }
        }
    }
    echo json_encode(['status' => 'ok', 'data' => [ 'entries' => $result ]]);
    exit;
}

if ($action === 'count') {
    $collections = $storage->list('collections');
    $collectionCount = count($collections);
    $totalEntries = 0;
    foreach ($collections as $collection) {
        $entries = $storage->list("collections/$collection/entries");
        $totalEntries += count($entries);
    }
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'collections' => $collectionCount,
            'entries' => $totalEntries,
            'total' => $collectionCount + $totalEntries
        ]
    ]);
    exit;
}

if ($action === 'list_groups') {
    requireAuth('manage_content');
    $groups = birkly_load_collection_groups();
    echo json_encode([
        'status' => 'ok',
        'data' => [
            'groups' => $groups,
        ],
    ]);
    exit;
}

if ($action === 'save_groups') {
    $csrf = $input['_csrf'] ?? '';
    if (!validate_csrf_token($csrf)) {
        log_security_event('permission_denied', ['action' => 'save_groups', 'reason' => 'invalid CSRF']);
        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
        exit;
    }
    requireAuth('manage_content');
    $groups = $input['groups'] ?? [];
    if (!is_array($groups)) {
        echo json_encode(['status' => 'error', 'message' => 'Groups must be an array']);
        exit;
    }
    $result = birkly_save_collection_groups($groups);
    echo json_encode([
        'status' => $result ? 'ok' : 'error',
        'message' => $result ? 'Groups saved' : 'Failed to save groups'
    ]);
    exit;
}

// Unknown action — avoid misleading default success payload (P2-R08)
if ($action !== '') {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unknown action: ' . $action,
    ]);
    exit;
}

// Default response (no action specified)
$collections = $storage->list('collections');
$totalEntries = 0;
foreach ($collections as $collection) {
    $entries = $storage->list("collections/$collection/entries");
    $totalEntries += count($entries);
}
echo json_encode([
    'status' => 'ok',
    'message' => 'Content API is working',
    'data' => [
        'count' => $totalEntries
    ]
]); 