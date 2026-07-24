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
require_once __DIR__ . '/../core/public_read.php';

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
    if ($collection === '' && !empty($input['id'])) {
        $collections = $storage->list('collections');
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && ($m['id'] ?? '') === $input['id']) {
                $collection = $m['slug'] ?? '';
                break;
            }
        }
    }
    if ($collection === '') {
        respond(['status' => 'error', 'message' => 'Collection not found'], 404);
    }

    $language = $input['language'] ?? $_GET['language'] ?? 'en';
    $result = birkly_public_list_entries($storage, $collection, $language);
    if (($result['status'] ?? '') !== 'ok') {
        respond(['status' => 'error', 'message' => $result['message'] ?? 'Collection not found'], 404);
    }
    respond($result);
}

if ($action === 'get_entry') {
    $collection = $input['collection'] ?? '';
    $entryId = $input['entry'] ?? '';
    $slug = $input['slug'] ?? $_GET['slug'] ?? '';
    if ($collection === '' && !empty($input['id'])) {
        $collections = $storage->list('collections');
        foreach ($collections as $d) {
            $m = $storage->read("collections/$d/collection.json");
            if ($m && ($m['id'] ?? '') === $input['id']) {
                $collection = $m['slug'] ?? '';
                break;
            }
        }
    }
    if ($collection === '') {
        respond(['status' => 'error', 'message' => 'Collection not found'], 404);
    }

    $language = $input['language'] ?? $_GET['language'] ?? 'en';
    $result = birkly_public_get_entry($storage, $collection, $slug, $entryId, $language);
    if (($result['status'] ?? '') !== 'ok') {
        respond(['status' => 'error', 'message' => $result['message'] ?? 'Entry not found or not published'], 404);
    }
    respond($result);
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