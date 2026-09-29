<?php
/**
 * Scheduled publish/unpublish.
 * CLI runs without a token. Every HTTP request must present the settings token.
 * Loopback is not accepted as proof the caller is local.
 */

declare(strict_types=1);

function birkly_scheduled_publish_settings_dir(): string
{
    if (isset($GLOBALS['__birkly_scheduled_publish_settings_dir']) && is_string($GLOBALS['__birkly_scheduled_publish_settings_dir'])) {
        return $GLOBALS['__birkly_scheduled_publish_settings_dir'];
    }

    return dirname(__DIR__) . '/settings';
}

function birkly_scheduled_publish_settings_path(): string
{
    return rtrim(birkly_scheduled_publish_settings_dir(), '/\\') . '/scheduled_publish.json';
}

function birkly_scheduled_publish_is_http_request(): bool
{
    if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
        return true;
    }

    return isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['HTTP_HOST']);
}

function birkly_scheduled_publish_read_token(): string
{
    $path = birkly_scheduled_publish_settings_path();
    if (!is_file($path)) {
        return '';
    }
    $data = json_decode((string) file_get_contents($path), true);

    return is_array($data) ? trim((string) ($data['token'] ?? '')) : '';
}

function birkly_scheduled_publish_ensure_token(): string
{
    $existing = birkly_scheduled_publish_read_token();
    if ($existing !== '') {
        return $existing;
    }

    $token = bin2hex(random_bytes(32));
    $dir = birkly_scheduled_publish_settings_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return '';
    }
    $encoded = json_encode(['token' => $token], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false || file_put_contents(birkly_scheduled_publish_settings_path(), $encoded . "\n") === false) {
        return '';
    }

    return $token;
}

function birkly_scheduled_publish_presented_token(): string
{
    $fromQuery = trim((string) ($_GET['token'] ?? ''));
    if ($fromQuery !== '') {
        return $fromQuery;
    }

    return trim((string) ($_SERVER['HTTP_X_BIRKLY_CRON_TOKEN'] ?? ''));
}

function birkly_scheduled_publish_http_authorized(): bool
{
    $expected = birkly_scheduled_publish_ensure_token();
    $presented = birkly_scheduled_publish_presented_token();
    if ($expected === '' || $presented === '') {
        return false;
    }

    return hash_equals($expected, $presented);
}

function birkly_scheduled_publish_admin_url(): string
{
    $token = birkly_scheduled_publish_ensure_token();
    $path = '/api/process_scheduled.php?token=' . rawurlencode($token);
    if (!function_exists('birkly_url')) {
        $paths = dirname(__DIR__) . '/core/birkly_paths.php';
        if (is_file($paths)) {
            require_once $paths;
        }
    }

    return function_exists('birkly_url') ? birkly_url($path) : $path;
}

/**
 * @return array{status:string,processed:int,published:int,unpublished:int,errors:list<string>,message?:string}
 */
function birkly_process_scheduled_entries(string $collectionsDir): array
{
    $now = time();
    $processed = 0;
    $published = 0;
    $unpublished = 0;
    $errors = [];

    if (!is_dir($collectionsDir)) {
        return [
            'status' => 'error',
            'processed' => 0,
            'published' => 0,
            'unpublished' => 0,
            'errors' => [],
            'message' => 'Collections directory not found',
        ];
    }

    $collections = glob($collectionsDir . '/*', GLOB_ONLYDIR) ?: [];
    foreach ($collections as $collectionDir) {
        $entriesDir = $collectionDir . '/entries';
        if (!is_dir($entriesDir)) {
            continue;
        }
        $entryFiles = glob($entriesDir . '/*.json') ?: [];
        foreach ($entryFiles as $entryFile) {
            if (strpos($entryFile, '/versions/') !== false) {
                continue;
            }
            $entryData = json_decode((string) file_get_contents($entryFile), true);
            if (!is_array($entryData)) {
                continue;
            }

            $processed++;
            $collectionSlug = basename($collectionDir);
            $entryId = basename($entryFile, '.json');

            if (($entryData['status'] ?? '') === 'scheduled' && isset($entryData['published_at'])) {
                $publishTime = strtotime((string) $entryData['published_at']);
                if ($publishTime !== false && $publishTime <= $now) {
                    $entryData['status'] = 'published';
                    $entryData['published_at'] = date('Y-m-d H:i:s', $publishTime);
                    $entryData['updated_at'] = date('Y-m-d H:i:s');
                    if (file_put_contents($entryFile, json_encode($entryData, JSON_PRETTY_PRINT)) !== false) {
                        $published++;
                        if (function_exists('log_security_event')) {
                            log_security_event('scheduled_publish', [
                                'collection' => $collectionSlug,
                                'entry_id' => $entryId,
                                'published_at' => $entryData['published_at'],
                            ]);
                        }
                        if (!function_exists('triggerWebhook') && is_file(dirname(__DIR__) . '/api/webhooks.php')) {
                            $previousInternal = $GLOBALS['__HARU_INTERNAL_CALL'] ?? null;
                            $GLOBALS['__HARU_INTERNAL_CALL'] = true;
                            require_once dirname(__DIR__) . '/api/webhooks.php';
                            if ($previousInternal === null) {
                                unset($GLOBALS['__HARU_INTERNAL_CALL']);
                            } else {
                                $GLOBALS['__HARU_INTERNAL_CALL'] = $previousInternal;
                            }
                        }
                        if (function_exists('triggerWebhook')) {
                            triggerWebhook('entry.published', [
                                'collection' => $collectionSlug,
                                'entry_id' => $entryId,
                                'entry' => $entryData,
                                'scheduled' => true,
                            ]);
                        }
                    } else {
                        $errors[] = "Failed to publish entry $entryId in collection $collectionSlug";
                    }
                }
            }

            if (($entryData['status'] ?? '') === 'published' && !empty($entryData['expires_at'])) {
                $expireTime = strtotime((string) $entryData['expires_at']);
                if ($expireTime !== false && $expireTime <= $now) {
                    $entryData['status'] = 'unpublished';
                    $entryData['updated_at'] = date('Y-m-d H:i:s');
                    if (file_put_contents($entryFile, json_encode($entryData, JSON_PRETTY_PRINT)) !== false) {
                        $unpublished++;
                        if (function_exists('log_security_event')) {
                            log_security_event('scheduled_unpublish', [
                                'collection' => $collectionSlug,
                                'entry_id' => $entryId,
                                'expires_at' => $entryData['expires_at'],
                            ]);
                        }
                        if (!function_exists('triggerWebhook') && is_file(dirname(__DIR__) . '/api/webhooks.php')) {
                            $previousInternal = $GLOBALS['__HARU_INTERNAL_CALL'] ?? null;
                            $GLOBALS['__HARU_INTERNAL_CALL'] = true;
                            require_once dirname(__DIR__) . '/api/webhooks.php';
                            if ($previousInternal === null) {
                                unset($GLOBALS['__HARU_INTERNAL_CALL']);
                            } else {
                                $GLOBALS['__HARU_INTERNAL_CALL'] = $previousInternal;
                            }
                        }
                        if (function_exists('triggerWebhook')) {
                            triggerWebhook('entry.unpublished', [
                                'collection' => $collectionSlug,
                                'entry_id' => $entryId,
                                'entry' => $entryData,
                                'scheduled' => true,
                            ]);
                        }
                    } else {
                        $errors[] = "Failed to unpublish entry $entryId in collection $collectionSlug";
                    }
                }
            }
        }
    }

    return [
        'status' => 'ok',
        'processed' => $processed,
        'published' => $published,
        'unpublished' => $unpublished,
        'errors' => $errors,
    ];
}
