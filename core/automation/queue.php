<?php
/**
 * Automation Queue System for Birkly
 * 
 * Handles async execution of automations to avoid blocking requests.
 */

require_once __DIR__ . '/../storage/StorageFactory.php';
require_once __DIR__ . '/executor.php';

/**
 * Enqueue an automation for execution
 * 
 * @param string $trigger_type Trigger type
 * @param array $context Execution context
 * @return bool True on success
 */
function enqueue_automation($trigger_type, $context = []) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $queue = $storage->read('automation_queue.json') ?? [];
    
    $job = [
        'id' => uniqid('job_', true),
        'trigger_type' => $trigger_type,
        'context' => $context,
        'created_at' => date('c'),
        'attempts' => 0,
        'status' => 'pending'
    ];
    
    $queue[] = $job;
    
    // Keep queue size manageable (max 10000 jobs)
    if (count($queue) > 10000) {
        $queue = array_slice($queue, -10000);
    }
    
    return $storage->write('automation_queue.json', $queue);
}

/**
 * Process automation queue
 * 
 * @param int $limit Maximum number of jobs to process
 * @return int Number of jobs processed
 */
function process_automation_queue($limit = 10) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $queue = $storage->read('automation_queue.json') ?? [];
    
    $processed = 0;
    $updated_queue = [];
    
    foreach ($queue as $job) {
        if ($processed >= $limit) {
            $updated_queue[] = $job;
            continue;
        }
        
        $status = $job['status'] ?? 'pending';
        $attempts = $job['attempts'] ?? 0;
        
        // Only process pending jobs
        if ($status !== 'pending') {
            $updated_queue[] = $job;
            continue;
        }
        
        // Mark as processing
        $job['status'] = 'processing';
        $job['attempts'] = $attempts + 1;
        $job['started_at'] = date('c');
        
        try {
            // Check retry delay
            if (isset($job['retry_after'])) {
                $retry_time = strtotime($job['retry_after']);
                if ($retry_time > time()) {
                    // Not ready to retry yet
                    $updated_queue[] = $job;
                    continue;
                }
            }
            
            // Execute automations for this trigger
            $executed = execute_automations($job['trigger_type'], $job['context']);
            
            // Mark as completed
            $job['status'] = 'completed';
            $job['completed_at'] = date('c');
            $job['executed_count'] = $executed;
            
            $processed++;
        } catch (Exception $e) {
            // Mark as failed
            $job['status'] = 'failed';
            $job['error'] = $e->getMessage();
            $job['failed_at'] = date('c');
            
            // Retry logic: retry up to 3 times with exponential backoff
            if ($attempts < 3) {
                $job['status'] = 'pending'; // Retry
                $backoff_seconds = pow(2, $attempts) * 60; // 1min, 2min, 4min
                $job['retry_after'] = date('c', time() + $backoff_seconds);
                $job['retry_count'] = ($job['retry_count'] ?? 0) + 1;
            }
            
            $processed++;
        }
        
        $updated_queue[] = $job;
    }
    
    // Save updated queue
    $storage->write('automation_queue.json', $updated_queue);
    
    return $processed;
}

/**
 * Get queue status
 * 
 * @return array Queue statistics
 */
function get_automation_queue_status() {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $queue = $storage->read('automation_queue.json') ?? [];
    
    $stats = [
        'total' => count($queue),
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0
    ];
    
    foreach ($queue as $job) {
        $status = $job['status'] ?? 'pending';
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
    }
    
    return $stats;
}

/**
 * Clear completed jobs from queue
 * 
 * @param int $older_than_days Remove jobs older than this many days
 * @return int Number of jobs removed
 */
function clear_automation_queue($older_than_days = 7) {
    $storage = StorageFactory::createStorage(__DIR__ . '/../../settings');
    $queue = $storage->read('automation_queue.json') ?? [];
    
    $cutoff = time() - ($older_than_days * 24 * 60 * 60);
    $removed = 0;
    $filtered = [];
    
    foreach ($queue as $job) {
        $status = $job['status'] ?? 'pending';
        $completed_at = $job['completed_at'] ?? $job['failed_at'] ?? null;
        
        // Keep pending/processing jobs
        if ($status === 'pending' || $status === 'processing') {
            $filtered[] = $job;
            continue;
        }
        
        // Remove old completed/failed jobs
        if ($completed_at) {
            $timestamp = strtotime($completed_at);
            if ($timestamp < $cutoff) {
                $removed++;
                continue; // Skip this job
            }
        }
        
        $filtered[] = $job;
    }
    
    $storage->write('automation_queue.json', $filtered);
    
    return $removed;
}
