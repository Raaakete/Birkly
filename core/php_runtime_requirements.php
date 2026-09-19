<?php
/**
 * PHP extensions and settings expected for a full local Birkly install (Hub, starters, media).
 */
declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

/**
 * @return list<string>
 */
function birkly_php_required_extensions(): array
{
    return ['json', 'mbstring', 'curl', 'openssl', 'zip', 'fileinfo', 'gd'];
}

/**
 * @return array<string, bool>
 */
function birkly_php_extension_status(): array
{
    $status = [];
    foreach (birkly_php_required_extensions() as $ext) {
        $status[$ext] = extension_loaded($ext);
    }

    return $status;
}

/**
 * @return list<string> Missing extension names.
 */
function birkly_php_missing_extensions(): array
{
    $missing = [];
    foreach (birkly_php_extension_status() as $ext => $loaded) {
        if (!$loaded) {
            $missing[] = $ext;
        }
    }

    return $missing;
}

/**
 * Portable Windows PHP may ship without a CA bundle; Hub HTTPS needs one.
 */
function birkly_php_ca_bundle_path(): ?string
{
    $root = birkly_project_root();
    $candidates = [
        $root . '/runtime/php/extras/ssl/cacert.pem',
        $root . '/runtime/php/extras/cacert.pem',
    ];
    foreach ($candidates as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }

    return null;
}
