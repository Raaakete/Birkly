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
} finally {
    birkly_hosting_reset_cache();
    if ($backup !== null) {
        file_put_contents($hostingFile, $backup);
    } elseif (is_file($hostingFile)) {
        unlink($hostingFile);
    }
}

echo "\nhosting_detect_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures)\n");
exit($failures === 0 ? 0 : 1);
