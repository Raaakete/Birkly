<?php
/**
 * Platform install flags (P5.4-M2) — feedback model, alpha program, relay.
 */

/**
 * @return array<string, mixed>
 */
function birkly_platform_config(): array
{
    if (array_key_exists('__birkly_platform_config', $GLOBALS)) {
        return $GLOBALS['__birkly_platform_config'];
    }

    $path = __DIR__ . '/../settings/platform.json';
    if (!is_file($path)) {
        $GLOBALS['__birkly_platform_config'] = [];
        return $GLOBALS['__birkly_platform_config'];
    }

    $data = json_decode((string) file_get_contents($path), true);
    $GLOBALS['__birkly_platform_config'] = is_array($data) ? $data : [];
    return $GLOBALS['__birkly_platform_config'];
}

function birkly_platform_config_reset(): void
{
    unset($GLOBALS['__birkly_platform_config']);
}

function birkly_is_platform_owner_install(): bool
{
    $config = birkly_platform_config();
    return !empty($config['owner_mode']);
}

/**
 * Whether this install should show the local feedback triage inbox (sidebar + API).
 * Only platform-owner installs triage locally; customer installs submit via the floating widget
 * and relay to the platform inbox when feedback_relay_url is configured.
 */
function birkly_feedback_triage_enabled(): bool
{
    return birkly_is_platform_owner_install();
}

function birkly_alpha_program_enabled(): bool
{
    $config = birkly_platform_config();
    if (array_key_exists('alpha_program', $config)) {
        return (bool) $config['alpha_program'];
    }
    // Closed alpha default: floating submit on customer installs unless owner triage install.
    return !birkly_is_platform_owner_install();
}

function birkly_platform_install_id(): string
{
    $config = birkly_platform_config();
    $id = trim((string) ($config['install_id'] ?? ''));
    if ($id !== '') {
        return $id;
    }

    require_once __DIR__ . '/../api/storage.php';
    $storage = new Storage(__DIR__ . '/../settings');
    $admin = $storage->read('admin.json') ?? [];
    $project = trim((string) ($admin['project_name'] ?? 'birkly'));
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'local'));
    return preg_replace('/[^a-z0-9\-]/', '-', strtolower($project . '-' . $host));
}

function birkly_feedback_relay_url(): string
{
    $config = birkly_platform_config();
    return trim((string) ($config['feedback_relay_url'] ?? ''));
}

function birkly_feedback_relay_secret(): string
{
    $config = birkly_platform_config();
    return trim((string) ($config['feedback_relay_secret'] ?? ''));
}

function birkly_legal_terms_url(): string
{
    $config = birkly_platform_config();
    $url = trim((string) ($config['legal_terms_url'] ?? ''));
    return $url !== '' ? $url : 'https://birkly.com/terms.html';
}

function birkly_legal_privacy_url(): string
{
    $config = birkly_platform_config();
    $url = trim((string) ($config['legal_privacy_url'] ?? ''));
    return $url !== '' ? $url : 'https://birkly.com/privacy.html';
}
