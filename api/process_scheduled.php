<?php
/**
 * Process Scheduled Publishing/Unpublishing
 * 
 * This script should be run periodically (via cron) to:
 * - Publish entries with status="scheduled" where published_at <= now
 * - Unpublish entries where expires_at <= now
 * 
 * Usage:
 * - Via cron: */5 * * * * php /path/to/api/process_scheduled.php
 * - Via web: GET /api/process_scheduled.php
 */

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/log.php';

$collectionsDir = __DIR__ . '/../content/collections';
$now = time();
$processed = 0;
$published = 0;
$unpublished = 0;
$errors = [];

if (!is_dir($collectionsDir)) {
    echo json_encode(['status' => 'error', 'message' => 'Collections directory not found']);
    exit;
}

// Get all collections
$collections = glob($collectionsDir . '/*', GLOB_ONLYDIR);

foreach ($collections as $collectionDir) {
    $entriesDir = $collectionDir . '/entries';
    if (!is_dir($entriesDir)) {
        continue;
    }
    
    // Get all entry files
    $entryFiles = glob($entriesDir . '/*.json');
    
    foreach ($entryFiles as $entryFile) {
        // Skip version files
        if (strpos($entryFile, '/versions/') !== false) {
            continue;
        }
        
        $entryData = json_decode(file_get_contents($entryFile), true);
        if (!$entryData) {
            continue;
        }
        
        $processed++;
        $collectionSlug = basename($collectionDir);
        $entryId = basename($entryFile, '.json');
        
        // Check for scheduled publishing
        if (isset($entryData['status']) && $entryData['status'] === 'scheduled') {
            if (isset($entryData['published_at'])) {
                $publishTime = strtotime($entryData['published_at']);
                if ($publishTime <= $now) {
                    // Time to publish!
                    $entryData['status'] = 'published';
                    $entryData['published_at'] = date('Y-m-d H:i:s', $publishTime);
                    $entryData['updated_at'] = date('Y-m-d H:i:s');
                    
                    if (file_put_contents($entryFile, json_encode($entryData, JSON_PRETTY_PRINT))) {
                        $published++;
                        log_security_event('scheduled_publish', [
                            'collection' => $collectionSlug,
                            'entry_id' => $entryId,
                            'published_at' => $entryData['published_at']
                        ]);
                        
                        // Trigger webhook
                        require_once __DIR__ . '/webhooks.php';
                        triggerWebhook('entry.published', [
                            'collection' => $collectionSlug,
                            'entry_id' => $entryId,
                            'entry' => $entryData,
                            'scheduled' => true
                        ]);
                    } else {
                        $errors[] = "Failed to publish entry $entryId in collection $collectionSlug";
                    }
                }
            }
        }
        
        // Check for scheduled unpublishing
        if (isset($entryData['status']) && $entryData['status'] === 'published') {
            if (isset($entryData['expires_at']) && !empty($entryData['expires_at'])) {
                $expireTime = strtotime($entryData['expires_at']);
                if ($expireTime <= $now) {
                    // Time to unpublish!
                    $entryData['status'] = 'unpublished';
                    $entryData['updated_at'] = date('Y-m-d H:i:s');
                    
                    if (file_put_contents($entryFile, json_encode($entryData, JSON_PRETTY_PRINT))) {
                        $unpublished++;
                        log_security_event('scheduled_unpublish', [
                            'collection' => $collectionSlug,
                            'entry_id' => $entryId,
                            'expires_at' => $entryData['expires_at']
                        ]);
                        
                        // Trigger webhook
                        require_once __DIR__ . '/webhooks.php';
                        triggerWebhook('entry.unpublished', [
                            'collection' => $collectionSlug,
                            'entry_id' => $entryId,
                            'entry' => $entryData,
                            'scheduled' => true
                        ]);
                    } else {
                        $errors[] = "Failed to unpublish entry $entryId in collection $collectionSlug";
                    }
                }
            }
        }
    }
}

// Output results
$result = [
    'status' => 'ok',
    'processed' => $processed,
    'published' => $published,
    'unpublished' => $unpublished,
    'errors' => $errors,
    'timestamp' => date('Y-m-d H:i:s')
];

if (!empty($_SERVER['HTTP_HOST'])) {
    // Web request - output JSON
    header('Content-Type: application/json');
    echo json_encode($result);
} else {
    // CLI request - output readable format
    echo "Scheduled Publishing Processor\n";
    echo "==============================\n";
    echo "Processed entries: $processed\n";
    echo "Published: $published\n";
    echo "Unpublished: $unpublished\n";
    if (!empty($errors)) {
        echo "Errors:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
}

