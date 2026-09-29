<?php
/**
 * P102-M7 — new installs register with birkly_installation_id().
 * Hub release download requests a signed artifact URL and keeps the anonymous URL when that request fails.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/platform.php';
require_once $root . '/core/platform_ops.php';
require_once $root . '/core/marketplace.php';

$errors = [];

function p102_assert(bool $ok, string $message, array &$errors): void
{
    if (!$ok) {
        $errors[] = $message;
    }
}

$previousConfig = $GLOBALS['__birkly_platform_config'] ?? null;
$hadConfig = array_key_exists('__birkly_platform_config', $GLOBALS);
$previousInstall = $GLOBALS['__birkly_installation_id'] ?? null;
$hadInstall = array_key_exists('__birkly_installation_id', $GLOBALS);
$previousHost = $_SERVER['HTTP_HOST'] ?? null;

try {
    $GLOBALS['__birkly_platform_config'] = [
        'install_id' => 'config-install',
        'feedback_relay_secret' => 'already-stored',
    ];
    birkly_platform_config_reset();
    $GLOBALS['__birkly_platform_config'] = [
        'install_id' => 'config-install',
        'feedback_relay_secret' => 'already-stored',
    ];
    p102_assert(
        birkly_ops_registration_install_id() === 'config-install',
        'configured install_id should be the registration id',
        $errors
    );

    $GLOBALS['__birkly_platform_config'] = [
        'feedback_relay_secret' => 'already-stored',
    ];
    $GLOBALS['__birkly_installation_id'] = 'inst_' . str_repeat('a', 32);
    $_SERVER['HTTP_HOST'] = 'legacy.example';
    $legacy = birkly_ops_registration_install_id();
    p102_assert(
        $legacy === birkly_platform_install_id(),
        'a stored relay secret should keep the legacy installation id',
        $errors
    );
    p102_assert(
        $legacy !== 'inst_' . str_repeat('a', 32),
        'legacy registration must not switch to the entropy id',
        $errors
    );

    $GLOBALS['__birkly_platform_config'] = [];
    putenv('BIRKLY_FEEDBACK_RELAY_SECRET');
    $fresh = birkly_ops_registration_install_id();
    p102_assert(
        $fresh === 'inst_' . str_repeat('a', 32),
        'a new install should register birkly_installation_id()',
        $errors
    );

    $seen = [];
    $GLOBALS['__birkly_hub_licenses'] = [
        'birkly-automation' => ['license_key' => 'BIRKLY-STORED-KEY'],
    ];
    $GLOBALS['birkly_marketplace_mock_handler'] = static function (string $method, string $path, ?array $body = null, array $query = []) use (&$seen): array {
        $seen[] = ['path' => $path, 'query' => $query];
        if (str_contains($path, '/signed-url')) {
            return [
                'ok' => true,
                'status' => 200,
                'data' => [
                    'download_url' => 'https://marketplace.birkly.cloud/api/v1/artifacts/birkly-automation/1.1.31/download?exp=999&sig=abc',
                    'expires_at' => '2030-01-01T00:00:00Z',
                ],
            ];
        }

        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Remote marketplace unreachable'];
    };

    $signed = birkly_marketplace_release_download('birkly-automation', '1.1.31');
    p102_assert(!empty($signed['ok']), 'release lookup should still resolve from the bundled catalog', $errors);
    p102_assert(!empty($signed['signed_url']), 'Hub client should prefer a signed artifact URL', $errors);
    p102_assert(
        str_contains((string) ($signed['download_url'] ?? ''), 'sig=abc'),
        'signed URL should be the one the marketplace returned',
        $errors
    );
    $sentLicense = false;
    foreach ($seen as $call) {
        if (str_contains((string) $call['path'], '/signed-url') && ($call['query']['license_key'] ?? '') === 'BIRKLY-STORED-KEY') {
            $sentLicense = true;
        }
    }
    p102_assert($sentLicense, 'stored Hub license should be sent when requesting a signed URL', $errors);

    $seen = [];
    $explicit = birkly_marketplace_release_download('birkly-automation', '1.1.31', 'BIRKLY-EXPLICIT');
    $sentExplicit = false;
    foreach ($seen as $call) {
        if (str_contains((string) $call['path'], '/signed-url') && ($call['query']['license_key'] ?? '') === 'BIRKLY-EXPLICIT') {
            $sentExplicit = true;
        }
    }
    p102_assert(!empty($explicit['signed_url']) && $sentExplicit, 'an explicit license key should be sent instead of the stored one', $errors);

    $GLOBALS['birkly_marketplace_mock_handler'] = static function (): array {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Remote marketplace unreachable'];
    };
    $anonymous = birkly_marketplace_release_download('birkly-automation', '1.1.31');
    p102_assert(!empty($anonymous['ok']), 'release lookup should still succeed when the signed URL endpoint is unavailable', $errors);
    p102_assert(empty($anonymous['signed_url']), 'a failed signed URL request must not be marked signed', $errors);
    $anonymousUrl = (string) ($anonymous['download_url'] ?? '');
    p102_assert(
        str_contains($anonymousUrl, '/api/v1/artifacts/birkly-automation/1.1.31/download') && !str_contains($anonymousUrl, 'sig='),
        'official download should keep the anonymous URL when signing is unavailable',
        $errors
    );
} catch (Throwable $e) {
    $errors[] = $e::class . ': ' . $e->getMessage();
} finally {
    if ($hadConfig) {
        $GLOBALS['__birkly_platform_config'] = $previousConfig;
    } else {
        unset($GLOBALS['__birkly_platform_config']);
    }
    if ($hadInstall) {
        $GLOBALS['__birkly_installation_id'] = $previousInstall;
    } else {
        unset($GLOBALS['__birkly_installation_id']);
    }
    unset($GLOBALS['birkly_marketplace_mock_handler'], $GLOBALS['__birkly_hub_licenses']);
    if ($previousHost === null) {
        unset($_SERVER['HTTP_HOST']);
    } else {
        $_SERVER['HTTP_HOST'] = $previousHost;
    }
    birkly_platform_config_reset();
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "p102_hub_signed_artifact_test: OK\n";
