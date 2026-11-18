<?php
/**
 * Discovery pack tests (P67-M1).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/discovery.php';
require_once $root . '/core/site_seo.php';

$failures = 0;

function assert_true(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "OK  $label\n";
        return;
    }
    echo "FAIL $label\n";
    $failures++;
}

$discoveryPath = birkly_discovery_settings_path();
$seoPath = birkly_site_seo_settings_path();
$discoveryBackup = is_file($discoveryPath) ? file_get_contents($discoveryPath) : null;
$seoBackup = is_file($seoPath) ? file_get_contents($seoPath) : null;
@unlink($discoveryPath);
@unlink($seoPath);
birkly_discovery_reset_cache();
birkly_site_seo_reset_cache();

echo "==> Discovery defaults\n";
$defaults = birkly_discovery_load();
assert_true($defaults['packs']['seo']['enabled'] === false, 'seo pack default off');
assert_true($defaults['packs']['agent_guide']['enabled'] === false, 'agent_guide default off');
assert_true($defaults['packs']['webmcp']['enabled'] === false, 'webmcp default off');

echo "==> Save pack master toggle\n";
$save = birkly_discovery_save_pack('seo', ['enabled' => true]);
assert_true($save['ok'] === true, 'save_pack ok');
birkly_discovery_reset_cache();
$loaded = birkly_discovery_load();
assert_true($loaded['packs']['seo']['enabled'] === true, 'seo master on after save');

echo "==> Turn pack off retains data\n";
birkly_site_seo_save([
    'enabled' => true,
    'defaults' => ['description' => 'Kept after off'],
]);
birkly_discovery_save_pack('seo', ['enabled' => false]);
birkly_discovery_reset_cache();
birkly_site_seo_reset_cache();
$seoAfterOff = birkly_site_seo_load();
assert_true(($seoAfterOff['defaults']['description'] ?? '') === 'Kept after off', 'seo.json retained when pack off');
assert_true(birkly_discovery_load()['packs']['seo']['enabled'] === false, 'seo master off');

echo "==> SEO publish\n";
$pub = birkly_site_seo_publish();
assert_true($pub['ok'] === true, 'seo publish ok');
assert_true(birkly_site_seo_is_published() === true, 'seo is published');

echo "==> Discovery status shape\n";
$status = birkly_discovery_status();
assert_true(isset($status['packs']['seo']), 'status has seo pack');
assert_true(isset($status['packs']['agent_guide']), 'status has agent_guide pack');
assert_true(isset($status['packs']['webmcp']), 'status has webmcp pack');
assert_true(isset($status['hosting']['public_site_mode']), 'status has hosting mode');

echo "==> Discovery verify + url map\n";
require_once $root . '/core/ai_presence.php';
$draft = birkly_ai_presence_default_draft();
$draft['identity']['official_name'] = 'Verify Co';
$draft['publication_settings']['enabled'] = true;
birkly_ai_presence_save_draft($draft);
birkly_ai_presence_publish();
$shallow = birkly_discovery_verify_checks(false);
$shallowSeoDeep = 0;
foreach ($shallow as $check) {
    if (($check['pack'] ?? '') === 'seo' && str_starts_with((string) ($check['id'] ?? ''), 'seo_missing_title_')) {
        $shallowSeoDeep++;
    }
}
assert_true($shallowSeoDeep === 0, 'shallow verify skips per-page SEO health');

$verify = birkly_discovery_run_verify();
assert_true($verify['ok'] === true, 'verify runs');
assert_true(count($verify['checks']) >= 3, 'verify has checks');
$deepSeo = 0;
foreach ($verify['checks'] as $check) {
    if (($check['pack'] ?? '') === 'seo' && str_starts_with((string) ($check['id'] ?? ''), 'seo_')) {
        $deepSeo++;
    }
}
assert_true($deepSeo >= 2, 'deep verify includes SEO checks');
$cached = birkly_discovery_load();
assert_true(is_array($cached['verify_cache'] ?? null), 'verify caches SEO health rows');
$map = birkly_discovery_url_map();
assert_true(isset($map['for_agents_md']), 'url map has for_agents_md key');

echo "==> Migration does not recurse into pack gate\n";
require_once $root . '/core/ai_presence.php';
@unlink(birkly_discovery_settings_path());
birkly_discovery_reset_cache();
birkly_ai_presence_reset_for_tests();
$draft = birkly_ai_presence_default_draft();
$draft['identity']['official_name'] = 'Recursion Test';
$draft['publication_settings']['enabled'] = true;
birkly_ai_presence_save_draft($draft);
birkly_ai_presence_publish();
$enabled = birkly_discovery_pack_enabled('agent_guide');
assert_true($enabled === true, 'migrate enables agent_guide without stack overflow');
assert_true(birkly_ai_presence_is_publicly_live() === true, 'publicly live after migrate');

echo "==> Discovery admin page data\n";
$GLOBALS['__HARU_INTERNAL_CALL'] = true;
require_once $root . '/api/admin_templates.php';
$discoveryPage = getDiscoveryPageData();
assert_true(is_array($discoveryPage['discovery_status'] ?? null), 'getDiscoveryPageData includes discovery_status');
assert_true(!empty($discoveryPage['discovery_status']['packs']['seo']), 'discovery page data has seo pack');
assert_true(is_array($discoveryPage['discovery_embed_overview']['completeness'] ?? null), 'discovery embed overview completeness');
assert_true(is_array($discoveryPage['discovery_embed_webmcp']['config'] ?? null), 'discovery embed includes webmcp config');
assert_true(is_array($discoveryPage['discovery_embed_webmcp']['catalog'] ?? null), 'discovery embed includes webmcp catalog');
assert_true(count($discoveryPage['discovery_embed_webmcp']['catalog']) >= 6, 'webmcp catalog has core tools');

echo "==> Discovery template render\n";
require_once $root . '/admin/template_parser.php';
require_once $root . '/core/i18n.php';
require_once $root . '/core/icons.php';
if (!function_exists('admin_url')) {
    function admin_url(string $page, array $params = []): string
    {
        return birkly_admin_url($page, $params);
    }
}
I18n::init();
$_GET['tab'] = 'seo';
$discoveryHtml = (string) render_template_file($root . '/admin/templates/discovery.html', $discoveryPage);
assert_true($discoveryHtml !== '', 'discovery template renders');
assert_true(
    preg_match('/id="disc-page-bars"[\s\S]*id="disc-ag-bar"/', $discoveryHtml) === 1,
    'agent guide bar is inside disc-page-bars'
);
assert_true(
    preg_match('/id="disc-ap-embed"[\s\S]*id="disc-ag-bar"/', $discoveryHtml) !== 1,
    'agent guide bar is not inside disc-ap-embed'
);
assert_true(strpos($discoveryHtml, 'Template not found') === false, 'discovery embed has no missing partial');
assert_true(strpos($discoveryHtml, 'id="ap-panel-guide"') !== false, 'discovery embed includes agent guide hub');
assert_true(strpos($discoveryHtml, '<textarea id="disc-bootstrap-data"') !== false, 'discovery bootstrap textarea present');
assert_true(strpos($discoveryHtml, 'page-header-left') === false, 'discovery has no duplicate in-page breadcrumb row');
assert_true(strpos($discoveryHtml, '"packs"') !== false, 'discovery bootstrap includes pack data');
if (preg_match('/<textarea id="disc-bootstrap-data"[^>]*>(.*?)<\/textarea>/s', $discoveryHtml, $bootMatch)) {
    $bootRaw = $bootMatch[1];
    assert_true(strpos($bootRaw, '&quot;') === false, 'discovery bootstrap is raw JSON (no HTML entity quotes)');
    $bootJson = json_decode($bootRaw, true);
    assert_true(is_array($bootJson) && isset($bootJson['packs']), 'discovery bootstrap JSON parses');
}
assert_true(strpos($discoveryHtml, 'data-ssr-overview') !== false, 'discovery embed SSR overview markers');
$_GET['tab'] = 'agent-guide';
$discoveryAg = (string) render_template_file($root . '/admin/templates/discovery.html', $discoveryPage);
assert_true(strpos($discoveryAg, 'data-tab="essentials"') !== false, 'agent guide embed has side nav tabs');
assert_true(strpos($discoveryAg, 'tabs-v2-root') === false, 'guide layout is not a tabs-v2 horizontal root');
$_GET['tab'] = 'webmcp';
$discoveryWebmcp = (string) render_template_file($root . '/admin/templates/discovery.html', $discoveryPage);
assert_true(
    strpos($discoveryWebmcp, 'class="tab-panel active" id="ap-panel-tools"') !== false,
    'webmcp discovery tab SSR activates tools hub panel'
);
assert_true(
    preg_match('/id="ap-panel-guide"[^>]*hidden/', $discoveryWebmcp) === 1,
    'webmcp discovery tab SSR hides guide hub panel'
);
assert_true(
    strpos($discoveryWebmcp, 'data-discovery-initial-hub-zone="tools"') !== false,
    'webmcp embed marks initial hub zone'
);
require_once $root . '/core/webmcp.php';
$wmPath = birkly_webmcp_settings_path();
$wmBackup = is_file($wmPath) ? file_get_contents($wmPath) : null;
$wmEnabled = birkly_webmcp_load();
$wmEnabled['enabled'] = true;
file_put_contents($wmPath, json_encode($wmEnabled, JSON_THROW_ON_ERROR));
birkly_webmcp_reset_cache();
$discoveryWebmcpOn = (string) render_template_file($root . '/admin/templates/discovery.html', getDiscoveryPageData());
assert_true(
    preg_match('/id="ap-webmcp-config" class="u-stack-6"(?!\s+hidden)/', $discoveryWebmcpOn) === 1,
    'enabled webmcp SSR shows tools catalog chrome'
);
assert_true(strpos($discoveryWebmcpOn, 'data-ssr-webmcp-tool=') !== false, 'enabled webmcp SSR lists tools');
if ($wmBackup !== null) {
    file_put_contents($wmPath, $wmBackup);
} elseif (is_file($wmPath)) {
    @unlink($wmPath);
}
birkly_webmcp_reset_cache();
$discJs = (string) file_get_contents($root . '/admin/js/discovery.js');
assert_true(strpos($discJs, 'syncDiscoveryHubPanels') !== false, 'discovery syncs hub panel active+hidden');
assert_true(strpos((string) file_get_contents($root . '/admin/css/pages/discovery.css'), 'data-hub-panel][hidden]') !== false, 'discovery embed hub hidden CSS guard');

echo "==> API syntax\n";
exec('php -l ' . escapeshellarg($root . '/api/discovery.php') . ' 2>&1', $out, $code);
assert_true($code === 0, 'discovery.php syntax');

// Restore
if ($discoveryBackup !== null) {
    file_put_contents($discoveryPath, $discoveryBackup);
} elseif (is_file($discoveryPath)) {
    @unlink($discoveryPath);
}
if ($seoBackup !== null) {
    file_put_contents($seoPath, $seoBackup);
} elseif (is_file($seoPath)) {
    @unlink($seoPath);
}
birkly_discovery_reset_cache();
birkly_site_seo_reset_cache();

exit($failures > 0 ? 1 : 0);
