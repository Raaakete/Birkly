<?php
/**
 * Cron / queue processor authentication for automation.
 * Queue processing is allowed via CLI or a shared secret token only.
 */

require_once __DIR__ . '/cms_paths.php';
require_once automation_cms_root() . '/core/storage/StorageFactory.php';
require_once __DIR__ . '/automation.php';

/**
 * Read or create the automation cron token (stored in settings, not committed).
 */
function automation_get_cron_token(): string
{
    $storage = automation_storage();
    $config = $storage->read('automation_cron.json') ?? [];

    if (!empty($config['token']) && is_string($config['token'])) {
        return $config['token'];
    }

    $token = bin2hex(random_bytes(32));
    $storage->write('automation_cron.json', [
        'token' => $token,
        'created_at' => date('c'),
    ]);

    return $token;
}

/**
 * Check whether a provided token matches the stored cron secret.
 */
function automation_cron_token_matches(string $provided): bool
{
    if ($provided === '') {
        return false;
    }
    $expected = automation_get_cron_token();
    return hash_equals($expected, $provided);
}

/**
 * Verify cron request: CLI invocation or matching token header/query param.
 */
function automation_verify_cron_request(): bool
{
    if (php_sapi_name() === 'cli') {
        return true;
    }

    $provided = $_GET['token']
        ?? $_SERVER['HTTP_X_AUTOMATION_CRON_TOKEN']
        ?? $_SERVER['HTTP_X_CRON_TOKEN']
        ?? '';

    if (!is_string($provided)) {
        return false;
    }

    return automation_cron_token_matches($provided);
}
