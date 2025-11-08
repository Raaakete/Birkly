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
    
    respond(['status'=>'ok','data'=>['entries'=>array_values($publishedEntries)]]);
}

if ($action === 'get_entry') {
    $collection = $input['collection'] ?? '';
    $id = $input['id'] ?? '';
    $entryId = $input['entry'] ?? '';
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
    if (!$entryId) respond(['status'=>'error','message'=>'Entry ID required']);
    $data = $storage->read("collections/$dir/entries/$entryId.json");
    if (!$data || $data['status'] !== 'published') {
        respond(['status'=>'error','message'=>'Entry not found or not published']);
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