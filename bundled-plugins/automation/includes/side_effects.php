<?php
/**
 * Short-lived side-effect locks so one trigger cannot send the same email twice.
 */

require_once __DIR__ . '/../cms_paths.php';

/**
 * Acquire a short-lived exclusive claim for a side effect.
 *
 * @return bool true when this caller should perform the side effect
 */
function automation_claim_side_effect(string $key, int $ttlSeconds = 45): bool
{
    $key = trim($key);
    if ($key === '') {
        return true;
    }

    $dir = automation_settings_dir() . '/automation_side_effects';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $key) ?: 'claim';
    $path = $dir . '/' . $safe . '.lock';
    $now = time();

    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return true;
    }

    try {
        flock($handle, LOCK_EX);
        $raw = stream_get_contents($handle);
        $last = is_string($raw) && is_numeric(trim($raw)) ? (int) trim($raw) : 0;
        if ($last > 0 && ($now - $last) < max(1, $ttlSeconds)) {
            return false;
        }
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) $now);
        fflush($handle);
        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * Build a stable fingerprint for an outbound email side effect.
 */
function automation_email_side_effect_key(string $to, string $subject, array $context = []): string
{
    $automationId = (string) ($context['origin_automation'] ?? $context['automation_id'] ?? '');
    $entry = is_array($context['entry'] ?? null) ? $context['entry'] : [];
    $entryId = (string) ($entry['id'] ?? $entry['slug'] ?? '');
    $collection = (string) ($context['collection'] ?? $entry['collection'] ?? '');
    $manual = !empty($context['_manual_run']) ? '1' : '0';

    return 'email_' . md5(strtolower(trim($to)) . '|' . trim($subject) . '|' . $automationId . '|' . $collection . '|' . $entryId . '|' . $manual);
}

/**
 * Fingerprint for queue enqueue dedupe (same entry trigger within a short window).
 */
function automation_enqueue_fingerprint(string $triggerType, array $context = []): string
{
    $entry = is_array($context['entry'] ?? null) ? $context['entry'] : [];
    $entryId = (string) ($entry['id'] ?? $entry['slug'] ?? '');
    $collection = (string) ($context['collection'] ?? $entry['collection'] ?? '');
    $automationId = (string) ($context['automation_id'] ?? '');
    $webhookId = (string) ($context['webhook_id'] ?? '');

    return 'enqueue_' . md5($triggerType . '|' . $collection . '|' . $entryId . '|' . $automationId . '|' . $webhookId);
}
