<?php
/**
 * P66-M2a/M2b — hosting detection, proposals, and domain delivery roles.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/hosting_detect.php';
require_once dirname(__DIR__) . '/core/hosting_urls.php';
require_once dirname(__DIR__) . '/core/site_domain.php';

$failures = 0;
$hostingFile = birkly_hosting_settings_path();
$backup = is_readable($hostingFile) ? file_get_contents($hostingFile) : null;
$adminFile = dirname(__DIR__) . '/settings/admin.json';
$adminBackup = is_readable($adminFile) ? file_get_contents($adminFile) : null;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures;
    echo $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '  ' . $detail . "\n";
        }
    }
}

function detect_test_write(array $config): void
{
    birkly_hosting_reset_cache();
    birkly_hosting_persist($config, false);
    birkly_hosting_reset_cache();
}

try {
    $_SERVER['HTTP_HOST'] = 'localhost:8080';
    $_SERVER['HTTPS'] = 'off';
    unset($_SERVER['HTTP_X_FORWARDED_PROTO']);

    detect_test_write([
        'cms_base_url' => '',
        'public_site_mode' => 'none',
        'public_site_url' => '',
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ]);

    $detect = birkly_hosting_detect_environment();
    assert_true('detect returns request_host', ($detect['request_host'] ?? '') === 'localhost');
    assert_true('detect sees project dir', !empty($detect['has_project_dir']));

    $proposal = birkly_hosting_propose_config();
    assert_true('proposal includes changes when cms empty', count($proposal['changes'] ?? []) > 0);
    assert_true(
        'proposal fills cms_base_url from request',
        str_contains((string) ($proposal['proposal']['cms_base_url'] ?? ''), 'localhost:8080')
    );

    putenv('BIRKLY_CMS_BASE_URL=https://env.example.com');
    birkly_hosting_reset_cache();
    $envProposal = birkly_hosting_propose_config();
    assert_true('env locked proposal has no changes', ($envProposal['env_locked'] ?? false) === true);
    assert_true('env locked changes empty', count($envProposal['changes'] ?? []) === 0);
    putenv('BIRKLY_CMS_BASE_URL');
    birkly_hosting_reset_cache();

    detect_test_write([
        'schema_version' => 2,
        'cms_base_url' => 'https://cms.example.com',
        'cms_host_aliases' => [],
        'public_site_mode' => 'project',
        'public_site_url' => 'https://www.example.com',
        'public_host_aliases' => [],
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
        'alias_redirect_to_primary' => true,
    ]);

    assert_true(
        'cms primary role',
        birkly_hosting_domain_delivery_role('cms.example.com') === 'cms_primary'
    );
    assert_true(
        'public primary role',
        birkly_hosting_domain_delivery_role('www.example.com') === 'public_primary'
    );

    $setResult = birkly_hosting_set_domain_delivery_role('staging.example.com', 'cms');
    assert_true('set cms alias role ok', ($setResult['status'] ?? '') === 'ok');
    assert_true(
        'cms alias persisted',
        in_array('staging.example.com', birkly_hosting_cms_alias_hosts(birkly_hosting_load()), true)
    );
    assert_true(
        'role map reflects cms alias',
        birkly_hosting_domain_delivery_role('staging.example.com') === 'cms'
    );

    $setPublic = birkly_hosting_set_domain_delivery_role('extra.example.com', 'public');
    assert_true('set public alias ok', ($setPublic['status'] ?? '') === 'ok');
    assert_true(
        'public alias adds www pair',
        in_array('www.extra.example.com', birkly_hosting_public_alias_hosts(birkly_hosting_load()), true)
    );

    $suggestPublic = birkly_hosting_suggest_delivery_role('example.com');
    assert_true(
        'suggest apex for cms.* setup',
        ($suggestPublic['role'] ?? '') === 'public'
    );

    $matrix = birkly_hosting_delivery_matrix();
    assert_true('delivery matrix includes cms primary', count($matrix) >= 2);
    $matrixHosts = array_column($matrix, 'host');
    assert_true('matrix lists cms host', in_array('cms.example.com', $matrixHosts, true));
    assert_true('matrix lists public host', in_array('www.example.com', $matrixHosts, true));

    $publicHosts = birkly_hosting_public_delivery_hosts();
    assert_true('public delivery hosts includes primary', in_array('www.example.com', $publicHosts, true));

    $redirectOff = birkly_hosting_set_alias_redirect(false);
    assert_true('disable alias redirect ok', ($redirectOff['status'] ?? '') === 'ok');
    assert_true(
        'alias redirect persisted off',
        birkly_hosting_load()['alias_redirect_to_primary'] === false
    );

    $makePrimary = birkly_hosting_set_public_primary('example.com');
    assert_true('set public primary ok', ($makePrimary['status'] ?? '') === 'ok');
    $afterPrimary = birkly_hosting_load();
    assert_true(
        'example.com is public primary',
        birkly_hosting_url_host((string) ($afterPrimary['public_site_url'] ?? '')) === 'example.com'
    );
    assert_true(
        'former primary moved to aliases',
        in_array('www.example.com', birkly_hosting_public_alias_hosts($afterPrimary), true)
    );

    birkly_hosting_set_alias_redirect(true);

    $apply = birkly_hosting_apply_proposal([
        'public_site_mode' => 'project',
        'public_site_url' => 'https://www.example.com',
        'cms_base_url' => 'https://cms.example.com',
    ]);
    assert_true('apply proposal ok', ($apply['status'] ?? '') === 'ok');
    $loaded = birkly_hosting_load();
    assert_true(
        'apply proposal persisted public url',
        ($loaded['public_site_url'] ?? '') === 'https://www.example.com'
    );

    echo "\n[primary swap]\n";
    detect_test_write([
        'schema_version' => 2,
        'cms_base_url' => 'https://cms.example.com',
        'cms_host_aliases' => ['alias.example.com'],
        'public_site_mode' => 'project',
        'public_site_url' => 'https://www.example.com',
        'public_host_aliases' => [],
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
        'alias_redirect_to_primary' => false,
    ]);

    $promoteCms = birkly_hosting_set_cms_primary('alias.example.com');
    assert_true('promote cms alias to cms primary ok', ($promoteCms['status'] ?? '') === 'ok');
    $promoted = birkly_hosting_load();
    assert_true(
        'promoted host is cms_base_url',
        birkly_hosting_url_host((string) ($promoted['cms_base_url'] ?? '')) === 'alias.example.com'
    );
    assert_true(
        'previous cms host stays registered as alias',
        in_array('cms.example.com', birkly_hosting_cms_alias_hosts($promoted), true)
    );
    assert_true(
        'promoted cms host is not a public alias',
        !in_array('alias.example.com', birkly_hosting_public_alias_hosts($promoted), true)
    );
    assert_true(
        'public url unchanged when promoting a cms alias',
        birkly_hosting_url_host((string) ($promoted['public_site_url'] ?? '')) === 'www.example.com'
    );
    assert_true(
        'matrix exposes cms primary as a settable role',
        (static function (): bool {
            foreach (birkly_hosting_delivery_matrix() as $row) {
                if (($row['host'] ?? '') === 'alias.example.com') {
                    return ($row['settable_role'] ?? null) === 'cms_primary' && !empty($row['is_primary']);
                }
            }

            return false;
        })()
    );

    detect_test_write([
        'schema_version' => 2,
        'cms_base_url' => 'https://cms.example.com',
        'cms_host_aliases' => [],
        'public_site_mode' => 'project',
        'public_site_url' => 'https://www.example.com',
        'public_host_aliases' => [],
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
        'alias_redirect_to_primary' => false,
    ]);
    $moveCms = birkly_hosting_set_public_primary('cms.example.com');
    assert_true('move cms primary onto public url ok', ($moveCms['status'] ?? '') === 'ok');
    $moved = birkly_hosting_load();
    assert_true(
        'former cms host is public_site_url',
        birkly_hosting_url_host((string) ($moved['public_site_url'] ?? '')) === 'cms.example.com'
    );
    assert_true(
        'former public host becomes cms url',
        birkly_hosting_url_host((string) ($moved['cms_base_url'] ?? '')) === 'www.example.com'
    );
    assert_true(
        'moved cms host is not still a cms alias',
        !in_array('cms.example.com', birkly_hosting_cms_alias_hosts($moved), true)
    );
    assert_true(
        'new cms host is not a public alias',
        !in_array('www.example.com', birkly_hosting_public_alias_hosts($moved), true)
    );
    assert_true(
        'moved host role is public primary',
        birkly_hosting_domain_delivery_role('cms.example.com') === 'public_primary'
    );
    assert_true(
        'displaced public host role is cms primary',
        birkly_hosting_domain_delivery_role('www.example.com') === 'cms_primary'
    );
    assert_true('no alias overlap after cms to public move', birkly_hosting_overlapping_delivery_aliases($moved) === []);

    echo "\n[stuck first-visit hosts]\n";
    detect_test_write([
        'schema_version' => 2,
        'cms_base_url' => 'https://birklyandotherthings.com',
        'cms_host_aliases' => ['cms.birklyandotherthings.com'],
        'public_site_mode' => 'project',
        'public_site_url' => 'https://www.birklyandotherthings.com',
        'public_host_aliases' => ['birklyandotherthings.com'],
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
        'alias_redirect_to_primary' => true,
    ]);
    assert_true(
        'apex stays cms primary until reassigned',
        birkly_hosting_domain_delivery_role('birklyandotherthings.com') === 'cms_primary'
    );
    $stuckCms = birkly_hosting_set_domain_delivery_role('cms.birklyandotherthings.com', 'cms_primary');
    assert_true('promote cms subdomain ok', ($stuckCms['status'] ?? '') === 'ok');
    $stuckPublic = birkly_hosting_set_domain_delivery_role('birklyandotherthings.com', 'public_primary');
    assert_true('move apex to public primary ok', ($stuckPublic['status'] ?? '') === 'ok');
    $stuck = birkly_hosting_load();
    assert_true(
        'stuck repair cms url',
        birkly_hosting_url_host((string) ($stuck['cms_base_url'] ?? '')) === 'cms.birklyandotherthings.com'
    );
    assert_true(
        'stuck repair public url',
        birkly_hosting_url_host((string) ($stuck['public_site_url'] ?? '')) === 'birklyandotherthings.com'
    );
    assert_true(
        'www is a public alias',
        in_array('www.birklyandotherthings.com', birkly_hosting_public_alias_hosts($stuck), true)
    );
    assert_true(
        'apex is not also a cms alias',
        !in_array('birklyandotherthings.com', birkly_hosting_cms_alias_hosts($stuck), true)
    );
    assert_true(
        'www is not also a cms alias',
        !in_array('www.birklyandotherthings.com', birkly_hosting_cms_alias_hosts($stuck), true)
    );
    assert_true('stuck repair has no alias overlap', birkly_hosting_overlapping_delivery_aliases($stuck) === []);

    $swapToApex = birkly_hosting_set_domain_delivery_role('birklyandotherthings.com', 'cms_primary');
    assert_true('swap apex back to cms primary ok', ($swapToApex['status'] ?? '') === 'ok');
    $swapped = birkly_hosting_load();
    assert_true(
        'swap puts apex on cms url',
        birkly_hosting_url_host((string) ($swapped['cms_base_url'] ?? '')) === 'birklyandotherthings.com'
    );
    assert_true(
        'swap puts previous cms host on public url',
        birkly_hosting_url_host((string) ($swapped['public_site_url'] ?? '')) === 'cms.birklyandotherthings.com'
    );
    assert_true(
        'www remains a public alias after swap',
        in_array('www.birklyandotherthings.com', birkly_hosting_public_alias_hosts($swapped), true)
            && birkly_hosting_domain_delivery_role('www.birklyandotherthings.com') === 'public'
    );

    $swapBack = birkly_hosting_set_domain_delivery_role('cms.birklyandotherthings.com', 'cms_primary');
    assert_true('swap cms subdomain back ok', ($swapBack['status'] ?? '') === 'ok');
    $restored = birkly_hosting_load();
    assert_true(
        'restored cms url',
        birkly_hosting_url_host((string) ($restored['cms_base_url'] ?? '')) === 'cms.birklyandotherthings.com'
    );
    assert_true(
        'restored public url',
        birkly_hosting_url_host((string) ($restored['public_site_url'] ?? '')) === 'birklyandotherthings.com'
    );
    assert_true(
        'www still public alias after swap back',
        birkly_hosting_domain_delivery_role('www.birklyandotherthings.com') === 'public'
    );

    $_SERVER['HTTP_HOST'] = 'birklyandotherthings.com';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
    assert_true('apex request role is public', birkly_request_host_role() === 'public');
    assert_true('strict admin redirects apex /admin', birkly_hosting_should_redirect_admin() === true);
    assert_true(
        'apex can serve project root',
        birkly_hosting_role_can_serve_public_project('public', '/') === true
    );
    $_SERVER['HTTP_HOST'] = 'cms.birklyandotherthings.com';
    assert_true('cms host request role is cms', birkly_request_host_role() === 'cms');
    $cmsRoot = birkly_hosting_root_redirect_url();
    assert_true(
        'cms host root opens admin',
        is_string($cmsRoot) && str_contains($cmsRoot, 'cms.birklyandotherthings.com') && str_contains($cmsRoot, '/admin/')
    );
    assert_true(
        'cms host root does not serve project',
        birkly_hosting_role_can_serve_public_project(birkly_request_host_role(), '/') === false
    );

    echo "\n[same-host first open]\n";
    detect_test_write([
        'schema_version' => 2,
        'cms_base_url' => 'https://example.com',
        'cms_host_aliases' => ['cms.example.com'],
        'public_site_mode' => 'project',
        'public_site_url' => 'https://example.com',
        'public_host_aliases' => [],
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
        'alias_redirect_to_primary' => true,
    ]);
    $split = birkly_hosting_set_cms_primary('cms.example.com');
    assert_true('split same-host install ok', ($split['status'] ?? '') === 'ok');
    $splitConfig = birkly_hosting_load();
    assert_true(
        'same-host correction cms url',
        birkly_hosting_url_host((string) ($splitConfig['cms_base_url'] ?? '')) === 'cms.example.com'
    );
    assert_true(
        'same-host correction keeps apex as public url',
        birkly_hosting_url_host((string) ($splitConfig['public_site_url'] ?? '')) === 'example.com'
    );
    assert_true(
        'apex is public primary after split',
        birkly_hosting_domain_delivery_role('example.com') === 'public_primary'
    );
    assert_true(
        'apex is not listed as a cms alias',
        !in_array('example.com', birkly_hosting_cms_alias_hosts($splitConfig), true)
    );

    assert_true(
        'oauth discovery path is not an alias redirect',
        birkly_hosting_is_platform_endpoint_path('/.well-known/oauth-authorization-server')
            && birkly_hosting_is_platform_endpoint_path('/mcp/agents/ai_agent_personal')
            && birkly_hosting_is_platform_endpoint_path('/') === false
    );

    require_once dirname(__DIR__) . '/core/mcp_oauth.php';
    $_SERVER['HTTP_HOST'] = 'www.example.com';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
    detect_test_write([
        'schema_version' => 2,
        'cms_base_url' => 'https://cms.example.com',
        'cms_host_aliases' => [],
        'public_site_mode' => 'project',
        'public_site_url' => 'https://www.example.com',
        'public_host_aliases' => ['example.com'],
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ]);
    $_SERVER['HTTP_HOST'] = 'www.example.com';
    $authUrl = birkly_mcp_oauth_absolute_url('/oauth/authorize');
    assert_true(
        'public host oauth metadata uses cms origin',
        str_contains($authUrl, 'https://cms.example.com/oauth/authorize'),
        $authUrl
    );
    $_SERVER['HTTP_HOST'] = 'cms.example.com';
    $cmsAuthUrl = birkly_mcp_oauth_absolute_url('/oauth/authorize');
    assert_true(
        'cms host oauth metadata keeps request origin',
        str_contains($cmsAuthUrl, 'https://cms.example.com/oauth/authorize'),
        $cmsAuthUrl
    );

    foreach (['en', 'de', 'es', 'fr', 'ja'] as $lang) {
        $catalog = json_decode((string) file_get_contents(dirname(__DIR__) . '/lang/' . $lang . '.json'), true);
        $admin = is_array($catalog['admin'] ?? null) ? $catalog['admin'] : [];
        foreach (['settings_delivery_alias_redirect_help', 'settings_domain_delivery_role_help', 'hosting_wizard_alias_redirect_help'] as $key) {
            $text = (string) ($admin[$key] ?? '');
            assert_true(
                $lang . ' ' . $key . ' has no install hostname',
                $text !== '' && !str_contains($text, 'birkly.io') && !str_contains($text, 'birkly.cloud'),
                $text
            );
        }
    }
    $wizardJs = (string) file_get_contents(dirname(__DIR__) . '/admin/js/hosting-wizard.js');
    assert_true(
        'wizard fallback has no install hostname',
        !str_contains($wizardJs, 'birkly.io') && !str_contains($wizardJs, 'birkly.cloud')
    );
} finally {
    birkly_hosting_reset_cache();
    if ($backup !== null) {
        file_put_contents($hostingFile, $backup);
    } elseif (is_file($hostingFile)) {
        unlink($hostingFile);
    }
    if ($adminBackup !== null) {
        file_put_contents($adminFile, $adminBackup);
    } elseif (is_file($adminFile)) {
        unlink($adminFile);
    }
}

echo "\nhosting_detect_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
