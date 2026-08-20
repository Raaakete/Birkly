<?php
/**
 * Site delivery health probes (P66-M1).
 */

declare(strict_types=1);

require_once __DIR__ . '/hosting_urls.php';
require_once __DIR__ . '/site_domain.php';
require_once __DIR__ . '/project_site.php';

/**
 * @return array{status: string, code: string, message: string, fix?: array<string, string>}
 */
function birkly_delivery_check(string $id, string $status, string $code, string $message, array $fix = []): array
{
    $row = [
        'id' => $id,
        'status' => $status,
        'code' => $code,
        'message' => $message,
    ];
    if ($fix !== []) {
        $row['fix'] = $fix;
    }

    return $row;
}

function birkly_delivery_overall_status(array $checks): string
{
    $hasFail = false;
    $hasWarn = false;
    foreach ($checks as $check) {
        if (($check['status'] ?? '') === 'fail') {
            $hasFail = true;
        } elseif (($check['status'] ?? '') === 'warn') {
            $hasWarn = true;
        }
    }

    if ($hasFail) {
        return 'red';
    }
    if ($hasWarn) {
        return 'yellow';
    }

    return 'green';
}

function birkly_delivery_registered_hosts(): array
{
    $hosts = [];
    $file = birkly_project_root() . '/settings/domains.json';
    if (!is_readable($file)) {
        return $hosts;
    }

    $decoded = json_decode((string) file_get_contents($file), true);
    if (!is_array($decoded)) {
        return $hosts;
    }

    foreach ($decoded as $domain => $license) {
        if (!is_array($license)) {
            continue;
        }
        $domain = birkly_normalize_host((string) $domain);
        if ($domain !== '') {
            $hosts[$domain] = true;
        }
        foreach ($license['allowed_domains'] ?? [] as $allowed) {
            $allowed = birkly_normalize_host((string) $allowed);
            if ($allowed !== '') {
                $hosts[$allowed] = true;
            }
        }
    }

    return array_keys($hosts);
}

function birkly_delivery_host_registered(string $host): bool
{
    $host = birkly_normalize_host($host);
    if ($host === '' || birkly_is_local_dev_host($host)) {
        return true;
    }

    return in_array($host, birkly_delivery_registered_hosts(), true);
}

/**
 * @param array<string, mixed> $options
 * @return array{overall: string, checks: array<int, array<string, mixed>>, request_host: string, request_host_role: string, schema_version: int, env_managed: bool, migration_pending: bool}
 */
function birkly_delivery_probe(array $options = []): array
{
    $deep = !empty($options['deep']);
    $config = birkly_hosting_load();
    $checks = [];
    $fixHosting = ['tab' => 'site-delivery', 'hash' => 'hosting'];
    $fixDomains = ['tab' => 'site-delivery', 'hash' => 'domains'];

    $requestHost = birkly_cms_request_host();
    $role = birkly_request_host_role();
    $mode = birkly_hosting_normalize_mode((string) ($config['public_site_mode'] ?? 'none'));
    $envManaged = birkly_hosting_env_overrides() !== [];

    if (trim((string) ($config['cms_base_url'] ?? '')) === '') {
        $checks[] = birkly_delivery_check(
            'cms_url',
            'fail',
            'CMS_URL_MISSING',
            'CMS URL is not configured.',
            $fixHosting
        );
    } else {
        $checks[] = birkly_delivery_check(
            'cms_url',
            'pass',
            'CMS_URL_OK',
            'CMS URL: ' . (string) $config['cms_base_url']
        );
    }

    if ($mode !== 'none' && trim((string) ($config['public_site_url'] ?? '')) === '') {
        $checks[] = birkly_delivery_check(
            'public_url',
            'fail',
            'PUBLIC_URL_MISSING',
            'Public site mode is set but public URL is empty.',
            $fixHosting
        );
    } elseif ($mode === 'none') {
        $checks[] = birkly_delivery_check(
            'public_url',
            'pass',
            'PUBLIC_MODE_NONE',
            'No public site configured (CMS only).'
        );
    } else {
        $checks[] = birkly_delivery_check(
            'public_url',
            'pass',
            'PUBLIC_URL_OK',
            'Public URL: ' . (string) $config['public_site_url']
        );
    }

    if ($requestHost === '') {
        $checks[] = birkly_delivery_check(
            'host_role',
            'warn',
            'REQUEST_HOST_EMPTY',
            'Could not determine the current request host.',
            $fixHosting
        );
    } elseif ($role === 'unknown') {
        $checks[] = birkly_delivery_check(
            'host_role',
            'fail',
            'HOST_ROLE_UNKNOWN',
            'Current host "' . $requestHost . '" is not configured as CMS or public (primary or alias).',
            $fixHosting
        );
    } else {
        $checks[] = birkly_delivery_check(
            'host_role',
            'pass',
            'HOST_ROLE_OK',
            'Current host "' . $requestHost . '" resolves as ' . $role . '.'
        );
    }

    if ($requestHost !== '' && !birkly_is_local_dev_host($requestHost) && !birkly_delivery_host_registered($requestHost)) {
        $checks[] = birkly_delivery_check(
            'domain_registered',
            'warn',
            'DOMAIN_NOT_REGISTERED',
            'Current host is not in the Domains allowlist (CORS/API may fail).',
            $fixDomains
        );
    } else {
        $checks[] = birkly_delivery_check(
            'domain_registered',
            'pass',
            'DOMAIN_REGISTERED',
            'Current host is allowed for Public API access.'
        );
    }

    if ($mode === 'project') {
        require_once __DIR__ . '/project_ssr.php';
        if (birkly_project_ssr_enabled()) {
            $checks[] = birkly_delivery_check('ssr', 'pass', 'SSR_ENABLED', 'Server-side rendering is enabled.');
        } else {
            $settings = birkly_project_ssr_settings();
            $reason = 'SSR off';
            if (!in_array($settings['render_mode'], ['server', 'auto'], true)) {
                $reason = 'render_mode=' . $settings['render_mode'];
            } elseif ($mode !== 'project') {
                $reason = 'public_site_mode≠project';
            }
            $checks[] = birkly_delivery_check(
                'ssr',
                'warn',
                'SSR_DISABLED',
                'Server-side rendering is not active (' . $reason . ').',
                ['tab' => 'project', 'hash' => 'project-rendering']
            );
        }

        $index = birkly_public_site_path() . '/index.html';
        if (!is_file($index)) {
            $checks[] = birkly_delivery_check(
                'project_files',
                'warn',
                'PROJECT_INDEX_MISSING',
                'No project/index.html found.',
                ['tab' => 'project', 'hash' => 'project-files']
            );
        } elseif (is_readable($index)) {
            $html = (string) file_get_contents($index);
            if (str_contains($html, 'href="/project/') || str_contains($html, "href='/project/")) {
                $checks[] = birkly_delivery_check(
                    'asset_paths',
                    'warn',
                    'PROJECT_PATH_PREFIX',
                    'project/index.html uses /project/ asset paths — use asset_url helpers for clean public URLs.',
                    ['tab' => 'project', 'hash' => 'project-files']
                );
            } else {
                $checks[] = birkly_delivery_check(
                    'asset_paths',
                    'pass',
                    'ASSET_PATHS_OK',
                    'Homepage does not use hardcoded /project/ hrefs.'
                );
            }
        }
    } elseif ($mode === 'external') {
        $snippet = birkly_hosting_client_snippet();
        if ($snippet === '') {
            $checks[] = birkly_delivery_check(
                'snippet',
                'fail',
                'SNIPPET_UNAVAILABLE',
                'Cannot build client snippet — configure CMS URL first.',
                $fixHosting
            );
        } else {
            $checks[] = birkly_delivery_check(
                'snippet',
                'pass',
                'SNIPPET_OK',
                'Client snippet includes data-cms-url.'
            );
        }
    }

    if (!empty($config['_migration_pending'])) {
        $checks[] = birkly_delivery_check(
            'migration',
            'warn',
            'MIGRATION_PENDING',
            'Delivery config can be upgraded to schema v2 (aliases). Run Repair delivery.',
            $fixHosting
        );
    }

    if ($deep && $mode !== 'none' && trim((string) ($config['public_site_url'] ?? '')) !== '') {
        $checks[] = birkly_delivery_check(
            'public_reachable',
            'warn',
            'DEEP_PROBE_SKIPPED',
            'Deep HTTP probes require outbound network (run from admin with deep=1).'
        );
    }

    return [
        'overall' => birkly_delivery_overall_status($checks),
        'checks' => $checks,
        'request_host' => $requestHost,
        'request_host_role' => $role,
        'schema_version' => (int) ($config['schema_version'] ?? 1),
        'env_managed' => $envManaged,
        'migration_pending' => !empty($config['_migration_pending']),
        'public_site_mode' => $mode,
    ];
}
