<?php
/**
 * Automation Queue System for Birkly
 *
 * Handles async execution of automations to avoid blocking requests.
 * SMTP/network work runs OUTSIDE the queue lock so jobs cannot get stuck
 * mid-send while holding the lock.
 */

require_once __DIR__ . '/cms_paths.php';
require_once __DIR__ . '/executor.php';

function automation_with_queue_lock(callable $callback)
{
    $settings_dir = automation_settings_dir();
    if (!is_dir($settings_dir)) {
        @mkdir($settings_dir, 0775, true);
    }

    $lock_path = $settings_dir . '/automation_queue.lock';
    $handle = @fopen($lock_path, 'c');
    if (!$handle) {
        return $callback();
    }

    try {
        flock($handle, LOCK_EX);
        return $callback();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * Enqueue an automation for execution
 *
 * @param string $trigger_type Trigger type
 * @param array $context Execution context
 * @return bool True on success
 */
function enqueue_automation($trigger_type, $context = [])
{
    if (!function_exists('automation_claim_side_effect')) {
        require_once __DIR__ . '/includes/side_effects.php';
    }

    // Collapse duplicate enqueue storms (double form submit / double API call).
    $fingerprint = automation_enqueue_fingerprint((string) $trigger_type, is_array($context) ? $context : []);
    if (!automation_claim_side_effect($fingerprint, 15)) {
        // Still drain the queue so the first job runs.
        automation_inline_process_queue(5);
        return true;
    }

    $written = automation_with_queue_lock(function () use ($trigger_type, $context, $fingerprint) {
        $storage = automation_storage();
        $queue = $storage->read('automation_queue.json') ?? [];
        if (!is_array($queue)) {
            $queue = [];
        }

        // Skip if an identical pending/processing job is already queued.
        foreach ($queue as $existing) {
            if (!is_array($existing)) {
                continue;
            }
            $status = (string) ($existing['status'] ?? 'pending');
            if (!in_array($status, ['pending', 'processing'], true)) {
                continue;
            }
            if (($existing['fingerprint'] ?? '') === $fingerprint) {
                return true;
            }
        }

        $job = [
            'id' => uniqid('job_', true),
            'trigger_type' => $trigger_type,
            'context' => $context,
            'fingerprint' => $fingerprint,
            'created_at' => date('c'),
            'attempts' => 0,
            'status' => 'pending',
        ];

        $queue[] = $job;

        if (count($queue) > 10000) {
            $queue = array_slice($queue, -10000);
        }

        return $storage->write('automation_queue.json', $queue);
    });

    // Process outside the lock so first-run workflows work without cron.
    if ($written) {
        automation_inline_process_queue(5);
    }

    return $written;
}

/**
 * Process a small batch after enqueue (non-recursive).
 */
function automation_inline_process_queue(int $limit = 5): int
{
    static $busy = false;
    if ($busy) {
        return 0;
    }
    $busy = true;
    try {
        return (int) process_automation_queue($limit, ['force' => false]);
    } catch (Throwable $e) {
        error_log('[Automation] inline queue process failed: ' . $e->getMessage());
        return 0;
    } finally {
        $busy = false;
    }
}

/**
 * Process automation queue.
 *
 * @param int $limit Maximum number of jobs to process
 * @param array{force?:bool} $options force=true ignores retry_after and reclaims stale processing jobs
 * @return int Number of jobs processed
 */
function process_automation_queue($limit = 10, array $options = [])
{
    $force = !empty($options['force']);
    $limit = max(1, (int) $limit);

    // Phase 1: claim jobs under lock (no network I/O).
    $claimed = automation_with_queue_lock(function () use ($limit, $force) {
        $storage = automation_storage();
        $queue = $storage->read('automation_queue.json') ?? [];
        if (!is_array($queue)) {
            $queue = [];
        }

        $claimedJobs = [];
        $staleSeconds = $force ? 30 : 120;

        foreach ($queue as $index => $job) {
            if (!is_array($job)) {
                continue;
            }
            if (count($claimedJobs) >= $limit) {
                break;
            }

            $status = $job['status'] ?? 'pending';
            $attempts = (int) ($job['attempts'] ?? 0);

            if ($status === 'processing' && !empty($job['started_at'])) {
                $started = strtotime((string) $job['started_at']);
                if ($started !== false && $started < time() - $staleSeconds) {
                    $status = 'pending';
                    $job['status'] = 'pending';
                    $job['recovered_at'] = date('c');
                    unset($job['retry_after']);
                }
            }

            if ($status !== 'pending') {
                $queue[$index] = $job;
                continue;
            }

            if (!$force && isset($job['retry_after'])) {
                $retryTime = strtotime((string) $job['retry_after']);
                if ($retryTime !== false && $retryTime > time()) {
                    $queue[$index] = $job;
                    continue;
                }
            }

            $job['status'] = 'processing';
            $job['attempts'] = $attempts + 1;
            $job['started_at'] = date('c');
            unset($job['retry_after']);
            $queue[$index] = $job;
            $claimedJobs[] = [
                'index' => $index,
                'job' => $job,
                'prior_attempts' => $attempts,
            ];
        }

        $storage->write('automation_queue.json', array_values($queue));
        return $claimedJobs;
    });

    if ($claimed === [] || !is_array($claimed)) {
        automation_record_queue_heartbeat(0, ['forced' => $force]);
        return 0;
    }

    // Phase 2: execute without holding the lock (SMTP/webhooks can take seconds).
    $resultsById = [];
    foreach ($claimed as $item) {
        $job = $item['job'];
        $priorAttempts = (int) ($item['prior_attempts'] ?? 0);
        $jobId = (string) ($job['id'] ?? '');
        try {
            $executed = execute_automations(
                (string) ($job['trigger_type'] ?? ''),
                is_array($job['context'] ?? null) ? $job['context'] : []
            );
            $job['status'] = 'completed';
            $job['completed_at'] = date('c');
            $job['executed_count'] = $executed;
            if ((int) $executed === 0) {
                $job['warning'] = 'No matching enabled automation ran for this trigger (check collection filters, enabled flag, and trigger type).';
            }
            unset($job['error']);
        } catch (Throwable $e) {
            $job['status'] = 'failed';
            $job['error'] = $e->getMessage();
            $job['failed_at'] = date('c');
            // Retry up to 3 attempts total (priorAttempts is attempts before this claim).
            if ($priorAttempts < 2) {
                $job['status'] = 'pending';
                $backoff = $force ? 5 : (int) (pow(2, $priorAttempts) * 60);
                $job['retry_after'] = date('c', time() + $backoff);
                $job['retry_count'] = (int) ($job['retry_count'] ?? 0) + 1;
            }
        }
        if ($jobId !== '') {
            $resultsById[$jobId] = $job;
        }
    }

    // Phase 3: persist outcomes under lock.
    automation_with_queue_lock(function () use ($resultsById) {
        $storage = automation_storage();
        $queue = $storage->read('automation_queue.json') ?? [];
        if (!is_array($queue)) {
            $queue = [];
        }
        foreach ($queue as $index => $job) {
            if (!is_array($job)) {
                continue;
            }
            $id = (string) ($job['id'] ?? '');
            if ($id !== '' && isset($resultsById[$id])) {
                $queue[$index] = $resultsById[$id];
            }
        }
        $storage->write('automation_queue.json', array_values($queue));
    });

    $processed = count($resultsById);
    $errors = [];
    foreach ($resultsById as $job) {
        if (!empty($job['error'])) {
            $errors[] = (string) $job['error'];
        } elseif (!empty($job['warning'])) {
            $errors[] = (string) $job['warning'];
        }
    }
    automation_record_queue_heartbeat($processed, [
        'forced' => $force,
        'last_job_notes' => array_slice($errors, 0, 5),
    ]);

    return $processed;
}

/**
 * Persist last queue drain metadata for Setup health.
 *
 * @param array<string, mixed> $extra
 */
function automation_record_queue_heartbeat(int $processed, array $extra = []): void
{
    try {
        $storage = automation_storage();
        $runtime = $storage->read('automation_runtime.json') ?? [];
        if (!is_array($runtime)) {
            $runtime = [];
        }
        $runtime['last_queue_run_at'] = date('c');
        $runtime['last_queue_processed'] = $processed;
        if (array_key_exists('forced', $extra)) {
            $runtime['last_queue_forced'] = !empty($extra['forced']);
        }
        if (isset($extra['last_job_notes'])) {
            $runtime['last_job_notes'] = $extra['last_job_notes'];
        }
        $storage->write('automation_runtime.json', $runtime);
    } catch (Throwable $e) {
        error_log('[Automation] failed to write queue heartbeat: ' . $e->getMessage());
    }
}

/**
 * Recent queue jobs for Setup diagnostics.
 *
 * @return list<array<string, mixed>>
 */
function automation_get_recent_queue_jobs(int $limit = 8): array
{
    $storage = automation_storage();
    $queue = $storage->read('automation_queue.json') ?? [];
    if (!is_array($queue)) {
        return [];
    }

    $jobs = array_values(array_filter($queue, static fn ($j) => is_array($j)));
    usort($jobs, static function ($a, $b) {
        $ta = strtotime((string) ($a['created_at'] ?? $a['started_at'] ?? '1970-01-01')) ?: 0;
        $tb = strtotime((string) ($b['created_at'] ?? $b['started_at'] ?? '1970-01-01')) ?: 0;
        return $tb <=> $ta;
    });

    $out = [];
    foreach (array_slice($jobs, 0, max(1, $limit)) as $job) {
        $out[] = [
            'id' => (string) ($job['id'] ?? ''),
            'trigger_type' => (string) ($job['trigger_type'] ?? ''),
            'status' => (string) ($job['status'] ?? 'pending'),
            'attempts' => (int) ($job['attempts'] ?? 0),
            'executed_count' => $job['executed_count'] ?? null,
            'error' => $job['error'] ?? null,
            'warning' => $job['warning'] ?? null,
            'retry_after' => $job['retry_after'] ?? null,
            'created_at' => $job['created_at'] ?? null,
            'completed_at' => $job['completed_at'] ?? null,
            'collection' => $job['context']['collection'] ?? null,
        ];
    }
    return $out;
}

/**
 * Get queue status
 *
 * @return array Queue statistics
 */
function get_automation_queue_status()
{
    $storage = automation_storage();
    $queue = $storage->read('automation_queue.json') ?? [];
    if (!is_array($queue)) {
        $queue = [];
    }

    $stats = [
        'total' => count($queue),
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0,
        'waiting_retry' => 0,
    ];

    foreach ($queue as $job) {
        if (!is_array($job)) {
            continue;
        }
        $status = $job['status'] ?? 'pending';
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
        if ($status === 'pending' && !empty($job['retry_after'])) {
            $retry = strtotime((string) $job['retry_after']);
            if ($retry !== false && $retry > time()) {
                $stats['waiting_retry']++;
            }
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
function clear_automation_queue($older_than_days = 7)
{
    return automation_with_queue_lock(function () use ($older_than_days) {
        $storage = automation_storage();
        $queue = $storage->read('automation_queue.json') ?? [];
        if (!is_array($queue)) {
            $queue = [];
        }

        $cutoff = time() - ($older_than_days * 24 * 60 * 60);
        $removed = 0;
        $filtered = [];

        foreach ($queue as $job) {
            if (!is_array($job)) {
                continue;
            }
            $status = $job['status'] ?? 'pending';
            $completed_at = $job['completed_at'] ?? $job['failed_at'] ?? null;

            if ($status === 'pending' || $status === 'processing') {
                $filtered[] = $job;
                continue;
            }

            if ($completed_at) {
                $timestamp = strtotime((string) $completed_at);
                if ($timestamp !== false && $timestamp < $cutoff) {
                    $removed++;
                    continue;
                }
            }

            $filtered[] = $job;
        }

        $storage->write('automation_queue.json', $filtered);

        return $removed;
    });
}
