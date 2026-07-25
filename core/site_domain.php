<?php
/**
 * Site domain helpers for public API access and onboarding.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';

function birkly_normalize_host(?string $host): string
{
    $host = strtolower(trim((string) $host));
    if ($host === '') {
        return '';
    }
    if (strpos($host, ':') !== false) {
        $host = explode(':', $host, 2)[0];
    }
    return $host;
}

function birkly_cms_request_host(): string
{
    return birkly_normalize_host($_SERVER['HTTP_HOST'] ?? '');
}

function birkly_client_request_host(): string
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        return birkly_normalize_host((string) parse_url($origin, PHP_URL_HOST));
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer !== '') {
        return birkly_normalize_host((string) parse_url($referer, PHP_URL_HOST));
    }

    return '';
}

function birkly_is_local_dev_host(string $host): bool
{
    if ($host === '') {
        return false;
    }

    $localHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
    if (in_array($host, $localHosts, true)) {
        return true;
    }

    foreach (['.local', '.test', '.dev'] as $suffix) {
        if (strlen($host) > strlen($suffix) && substr($host, -strlen($suffix)) === $suffix) {
            return true;
        }
    }

    return false;
}

/**
 * Public project pages served from the same CMS host may read content without a manual domain license.
 */
function birkly_request_is_same_cms_origin(): bool
{
    if (function_exists('birkly_is_local_dev_request') && birkly_is_local_dev_request()) {
        return true;
    }

    $cmsHost = birkly_cms_request_host();
    if ($cmsHost === '') {
        return false;
    }

    if (function_exists('birkly_hosting_cms_host')) {
        require_once __DIR__ . '/hosting_urls.php';
        $configuredCmsHost = birkly_hosting_cms_host();
        if ($configuredCmsHost !== '' && $cmsHost === $configuredCmsHost) {
            return true;
        }
        $publicHost = birkly_hosting_public_host();
        if ($publicHost !== '' && $cmsHost === $publicHost && birkly_hosting_same_host()) {
            return true;
        }
    }

    $clientHost = birkly_client_request_host();
    if ($clientHost !== '' && $cmsHost === $clientHost) {
        return true;
    }

    if (function_exists('birkly_hosting_cms_host')) {
        require_once __DIR__ . '/hosting_urls.php';
        $configuredCms = birkly_hosting_cms_host();
        $configuredPublic = birkly_hosting_public_host();
        if ($configuredCms !== '' && $clientHost === $configuredCms) {
            return true;
        }
        if ($configuredPublic !== '' && $clientHost === $configuredPublic) {
            return true;
        }
    }

    // fetch() from /project/* on the CMS host may omit Referer/Origin (Safari, privacy settings).
    $secFetchSite = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($secFetchSite === 'same-origin') {
        return true;
    }

    return false;
}

/**
 * Register the current CMS host for public API access (idempotent).
 */
function birkly_ensure_site_domain_registered(?string $host = null): bool
{
    require_once __DIR__ . '/../api/domain_auth.php';

    $host = birkly_normalize_host($host ?? birkly_cms_request_host());
    if ($host === '' || birkly_is_local_dev_host($host)) {
        return true;
    }

    $domainAuth = new DomainAuth();
    $validation = $domainAuth->validateDomain($host);
    if (!empty($validation['valid'])) {
        return true;
    }

    return (bool) $domainAuth->registerDomain($host, [
        'id' => 'site-' . substr(md5($host), 0, 12),
        'type' => 'site',
        'label' => 'Auto-registered during setup',
    ]);
}
