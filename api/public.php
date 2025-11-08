<?php
// api/public.php - Public read-only API for HaruCMS
header('Content-Type: application/json');
require_once 'storage.php';
require_once '../core/cache.php';
$storage = new Storage('../content');
$input = array_merge($_GET, $_POST);
$action = $input['action'] ?? '';

function respond($data) {
    echo json_encode($data);
    exit;
}

if ($action === 'list_collections') {
    $collections = $storage->list('collections');
    $result = [];
    foreach ($collections as $dir) {
        $meta = $storage->read("collections/$dir/collection.json");
        if ($meta) {
            $result[] = [
                'id' => $meta['id'],
                'title' => $meta['title'],
                'slug' => $meta['slug']
            ];
        }
    }
    respond(['status'=>'ok','data'=>['collections'=>$result]]);
}

if ($action === 'list_entries') {
    $collection = $input['collection'] ?? '';
    $id = $input['id'] ?? '';
    $language = $input['language'] ?? $_GET['language'] ?? 'en'; // Default to 'en'
    $dir = '';
    $collections = $storage->list('collections');
    if ($id) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && $m['id'] === $id) { $dir = $d; break; }
        }
    } elseif ($collection) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && $m['slug'] === $collection) { $dir = $d; break; }
        }
    }
    if (!$dir) respond(['status'=>'error','message'=>'Collection not found']);
    
    // Get collection metadata
    $collectionMeta = $storage->read("collections/$dir/collection.json");
    $collectionSlug = $collectionMeta['slug'] ?? '';
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
        
        // Cache all entries (we'll filter published below)
        if ($collectionSlug) {
            $cache->setEntries($collectionSlug, $entryData);
        }
        $cachedEntries = $entryData;
    }
    
    // Filter only published entries for public API
    $publishedEntries = array_filter($cachedEntries, function($entry) {
        return ($entry['status'] ?? 'draft') === 'published';
    });
    
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
        }, $publishedEntries);
        
        respond(['status'=>'ok','data'=>['entries'=>array_values($transformedEntries)]]);
    } else {
        // No multi-language, return entries as-is
        respond(['status'=>'ok','data'=>['entries'=>array_values($publishedEntries)]]);
    }
}

if ($action === 'get_entry') {
    $collection = $input['collection'] ?? '';
    $id = $input['id'] ?? '';
    $entryId = $input['entry'] ?? '';
    $slug = $input['slug'] ?? $_GET['slug'] ?? '';
    $language = $input['language'] ?? $_GET['language'] ?? 'en'; // Default to 'en'
    $dir = '';
    $collections = $storage->list('collections');
    if ($id) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && $m['id'] === $id) { $dir = $d; break; }
        }
    } elseif ($collection) {
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && $m['slug'] === $collection) { $dir = $d; break; }
        }
    }
    if (!$dir) respond(['status'=>'error','message'=>'Collection not found']);
    
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
    $data = null;
    if ($slug) {
        $entries = $storage->list("collections/$dir/entries");
        foreach ($entries as $entryFile) {
            $entryData = $storage->read("collections/$dir/entries/$entryFile");
            if ($entryData && isset($entryData['slug']) && $entryData['slug'] === $slug) {
                $data = $entryData;
                break;
            }
        }
    } elseif ($entryId) {
    $data = $storage->read("collections/$dir/entries/$entryId.json");
    }
    
    if (!$data || ($data['status'] ?? 'draft') !== 'published') {
        respond(['status'=>'error','message'=>'Entry not found or not published']);
    }
    
    // Transform entry for language-specific content
    if ($language && $multilanguageEnabled) {
        // Check if entry has multi-language structure
        $hasMultilang = false;
        foreach ($multilanguageLanguages as $lang) {
            if (isset($data[$lang]) && is_array($data[$lang])) {
                $hasMultilang = true;
                break;
            }
        }
        
        if ($hasMultilang && isset($data[$language])) {
            // Merge language-specific content with entry metadata
            $transformed = array_merge($data, $data[$language]);
            // Keep metadata fields
            $transformed['id'] = $data['id'] ?? '';
            $transformed['slug'] = $data['slug'] ?? '';
            $transformed['status'] = $data['status'] ?? 'draft';
            $transformed['created_at'] = $data['created_at'] ?? '';
            $transformed['updated_at'] = $data['updated_at'] ?? '';
            $transformed['published_at'] = $data['published_at'] ?? '';
            $data = $transformed;
        }
    }
    
    respond(['status'=>'ok','data'=>['entry'=>$data]]);
}

if ($action === 'get_site_settings') {
    $settingsPath = __DIR__ . '/../settings/site.json';
    $siteSettings = [
        'title' => 'HaruCMS',
        'name' => 'HaruCMS',
        'description' => '',
        'url' => ''
    ];
    
    if (file_exists($settingsPath)) {
        $fileData = json_decode(file_get_contents($settingsPath), true);
        if ($fileData) {
            $siteSettings = array_merge($siteSettings, $fileData);
        }
    }
    
    respond(['status'=>'ok','data'=>['settings'=>$siteSettings]]);
}

if ($action === 'get_collection_info') {
    $collection = $input['collection'] ?? '';
    if (empty($collection)) {
        respond(['status'=>'error','message'=>'Collection parameter required']);
    }
    
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
        respond(['status'=>'error','message'=>'Collection not found']);
    }
    
    $collectionData = $storage->read("collections/$dir/collection.json");
    $schema = $storage->read("collections/$dir/schema.json");
    
    // Only return if Public API is enabled
    if (($collectionData['entry_method'] ?? 'admin_only') !== 'public_api') {
        respond(['status'=>'error','message'=>'Public API not enabled for this collection']);
    }
    
    respond([
        'status'=>'ok',
        'data'=>[
            'schema' => $schema,
            'api_key' => $collectionData['api_key'] ?? '',
            'collection' => $collectionData
        ]
    ]);
}

respond(['status'=>'error','message'=>'Invalid action']); 