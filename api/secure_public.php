<?php
// api/secure_public.php - Secure public read-only API for Birkly with domain authentication
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    require_once 'domain_auth.php';
    setDynamicCORS();
    http_response_code(200);
    exit;
}

require_once 'storage.php';
require_once 'domain_auth.php';
require_once 'rate_limit.php';
require_once 'log.php';

// Apply domain authentication with read permission
requireDomainAuth('read');

// Apply rate limiting based on domain's read limits
applyDomainRateLimit('read');

$storage = new Storage('../content');
$input = array_merge($_GET, $_POST);
$action = $input['action'] ?? '';

function respond($data, $code = 200) {
    http_response_code($code);
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
    $entries = $storage->list("collections/$dir/entries");
    $entryData = [];
    foreach ($entries as $entry) {
        $data = $storage->read("collections/$dir/entries/$entry");
        if ($data && $data['status'] === 'published') {
            $entryData[] = $data;
        }
    }
    respond(['status'=>'ok','data'=>['entries'=>$entryData]]);
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

// License info endpoint (for client verification)
if ($action === 'license_info') {
    $license = $GLOBALS['current_license'] ?? [];
    respond([
        'status' => 'ok',
        'data' => [
            'domain' => $license['domain'] ?? '',
            'license_id' => $license['id'] ?? '',
            'expires_at' => $license['expires_at'] ?? null,
            'features' => $license['features'] ?? []
        ]
    ]);
}

respond(['status'=>'error','message'=>'Invalid action']); 