<?php
/**
 * Timed exclusive file locks for flat-file storage writes.
 */

declare(strict_types=1);

/**
 * Acquire an exclusive flock with timeout (milliseconds). Returns false on timeout.
 */
function birkly_storage_flock_acquire_exclusive($handle, int $timeoutMs = 5000): bool
{
    if (!is_resource($handle)) {
        return false;
    }

    $timeoutMs = max(0, $timeoutMs);
    $deadline = microtime(true) + ($timeoutMs / 1000);

    while (true) {
        if (flock($handle, LOCK_EX | LOCK_NB)) {
            return true;
        }

        if ($timeoutMs === 0 || microtime(true) >= $deadline) {
            return false;
        }

        usleep(50000);
    }
}

/**
 * Remove stale flat-file write temps left when a process died mid-write.
 */
function birkly_storage_cleanup_stale_write_tmp(string $tmpPath, int $maxAgeSeconds = 300): void
{
    if (!is_file($tmpPath)) {
        return;
    }

    $age = time() - (int) @filemtime($tmpPath);
    if ($age >= $maxAgeSeconds) {
        @unlink($tmpPath);
    }
}
