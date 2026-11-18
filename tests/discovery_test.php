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

echo "==> Agent Guide effective editor draft\n";
$pubDraft = birkly_ai_presence_default_draft();
$pubDraft['identity']['official_name'] = 'Published Only Co';
$pubDraft['publication_settings']['enabled'] = true;
birkly_ai_presence_save_draft($pubDraft);
birkly_ai_presence_publish();
birkly_ai_presence_save_draft(birkly_ai_presence_default_draft());
$effective = birkly_ai_presence_effective_editor_draft();
assert_true(
    ($effective['identity']['official_name'] ?? '') === 'Published Only Co',
    'effective editor draft falls back to published content'
);
$richPub = birkly_ai_presence_load_published();
if (is_array($richPub)) {
    $richPub['identity']['full_description'] = 'Published full description body';
    birkly_ai_presence_write_json(birkly_ai_presence_dir() . '/published.json', $richPub);
}
$sparse = birkly_ai_presence_default_draft();
$sparse['identity']['official_name'] = 'Sparse local edit';
birkly_ai_presence_save_draft($sparse);
$merged = birkly_ai_presence_effective_editor_draft();
assert_true(
    ($merged['identity']['official_name'] ?? '') === 'Sparse local edit',
    'effective draft keeps non-empty draft overlay fields'
);
assert_true(
    ($merged['identity']['full_description'] ?? '') === 'Published full description body',
    'effective draft keeps published fields when draft value is empty'
);
$withClaims = birkly_ai_presence_load_published();
if (is_array($withClaims)) {
    $withClaims['claims'] = [
        ['id' => 'claim_pub', 'statement' => 'Published claim', 'status' => 'supported'],
    ];
    $withClaims['evidence'] = [
        ['id' => 'ev_pub', 'title' => 'Published evidence', 'type' => 'url', 'verification_status' => 'verified'],
    ];
    birkly_ai_presence_write_json(birkly_ai_presence_dir() . '/published.json', $withClaims);
}
birkly_ai_presence_save_draft(birkly_ai_presence_default_draft());
$withMergedLists = birkly_ai_presence_effective_editor_draft();
assert_true(
    count($withMergedLists['claims'] ?? []) === 1
    && ($withMergedLists['claims'][0]['statement'] ?? '') === 'Published claim',
    'effective draft keeps published claims when draft list is empty'
);
assert_true(
    count($withMergedLists['evidence'] ?? []) === 1,
    'effective draft keeps published evidence when draft list is empty'
);

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
assert_true(strpos($discoveryHtml, 'id="disc-seo-serp-title"') !== false, 'discovery SEO SERP preview SSR');
assert_true(strpos($discoveryHtml, 'disc-seo-previews') !== false, 'discovery SEO preview chrome');
assert_true(strpos($discoveryHtml, '<textarea id="disc-bootstrap-data"') !== false, 'discovery bootstrap textarea present');
assert_true(strpos($discoveryHtml, 'page-header-left') === false, 'discovery has no duplicate in-page breadcrumb row');
assert_true(strpos($discoveryHtml, '"packs"') !== false, 'discovery bootstrap includes pack data');
if (preg_match('/<textarea id="disc-bootstrap-data"[^>]*>(.*?)<\/textarea>/s', $discoveryHtml, $bootMatch)) {
    $bootRaw = $bootMatch[1];
    assert_true(strpos($bootRaw, '&quot;') === false, 'discovery bootstrap is raw JSON (no HTML entity quotes)');
    $bootJson = json_decode($bootRaw, true);
    assert_true(is_array($bootJson) && isset($bootJson['packs']), 'discovery bootstrap JSON parses');
    assert_true(
        is_array($bootJson['agent_guide_hydrate']['draft'] ?? null),
        'discovery bootstrap embeds agent guide draft hydrate'
    );
}
assert_true(strpos($discoveryHtml, 'window.__DISC_AG_HYDRATE__=') !== false, 'discovery inline agent guide hydrate script');
preg_match_all('/<script[^>]+src="[^"]*\\/discovery\\.js/', $discoveryHtml, $discJsTags);
assert_true(count($discJsTags[0]) === 0, 'discovery template does not duplicate discovery.js (layout head emits scripts)');
assert_true(strpos((string) file_get_contents($root . '/admin/partials/layout_footer.php'), "\$page === 'discovery'") !== false, 'layout footer also loads discovery script stack');
assert_true(is_file($root . '/core/discovery_admin_assets.php'), 'discovery admin assets helper exists');
assert_true(strpos((string) file_get_contents($root . '/admin/index.php'), 'birkly_echo_discovery_admin_script_tags') !== false, 'admin index emits discovery scripts');
assert_true(strpos((string) file_get_contents($root . '/admin/partials/layout_footer.php'), 'runDiscoveryScriptLoader') !== false, 'layout footer self-heals missing discovery.js');
assert_true(strpos((string) file_get_contents($root . '/admin/partials/layout_head.php'), 'birkly_echo_discovery_admin_script_tags') !== false, 'layout head loads discovery scripts on discovery page');
assert_true(strpos($discoveryHtml, 'data-ssr-overview') !== false, 'discovery embed SSR overview markers');
$_GET['tab'] = 'agent-guide';
$discoveryAg = (string) render_template_file($root . '/admin/templates/discovery.html', $discoveryPage);
assert_true(strpos($discoveryAg, 'data-tab="essentials"') !== false, 'agent guide embed has side nav tabs');
assert_true(strpos($discoveryAg, 'data-disc-guide-link="1"') !== false, 'discovery embed guide nav uses real links');
assert_true(strpos($discoveryAg, 'guide_tab=essentials') !== false, 'discovery guide nav links include guide_tab');
$_GET['guide_tab'] = 'claims';
$discoveryAgClaims = (string) render_template_file($root . '/admin/templates/discovery.html', $discoveryPage);
assert_true(strpos($discoveryAgClaims, 'id="ap-claim-statement"') !== false, 'discovery embed ships claim modal fields');
assert_true(strpos($discoveryAgClaims, 'id="ap-evidence-title"') !== false, 'discovery embed ships evidence modal fields');
assert_true(
    preg_match('/<div id="ap-claim-modal" class="modal ap-modal"/', $discoveryAgClaims) === 1,
    'claim modal is a real element in SSR HTML'
);
assert_true(
    preg_match('/<p class="form-help[^"]*">[\s\S]*?<noscript>/i', $discoveryAgClaims) !== 1,
    'publish help must not open a literal noscript that swallows modal markup'
);
assert_true(str_contains($discoveryAgClaims, '&lt;noscript&gt;'), 'landmark help shows escaped noscript tag name');
$claimRows = count($discoveryPage['discovery_status']['agent_guide_hydrate']['draft']['claims'] ?? []);
$evidenceRows = count($discoveryPage['discovery_status']['agent_guide_hydrate']['draft']['evidence'] ?? []);
assert_true(strpos($discoveryAgClaims, 'id="ap-claims-list"') !== false, 'discovery claims tab list chrome');
assert_true(strpos($discoveryAgClaims, 'id="ap-evidence-list"') !== false, 'discovery evidence list chrome');
if ($claimRows > 0) {
    assert_true(strpos($discoveryAgClaims, 'data-ssr-claim') !== false, 'discovery claims tab SSR claim rows');
}
if ($evidenceRows > 0) {
    assert_true(strpos($discoveryAgClaims, 'data-ssr-evidence') !== false, 'discovery claims tab SSR evidence rows');
}
$_GET['guide_tab'] = 'essentials';
$discoveryAgEssentials = (string) render_template_file($root . '/admin/templates/discovery.html', $discoveryPage);
assert_true(
    preg_match('/class="tab-content active" data-tab-content="essentials"(?!\s+hidden)/', $discoveryAgEssentials) === 1,
    'guide_tab=essentials SSR activates essentials panel'
);
$expectedAgName = (string) ($discoveryPage['discovery_status']['agent_guide_hydrate']['draft']['identity']['official_name'] ?? '');
assert_true(
    $expectedAgName !== '' && strpos($discoveryAgEssentials, 'value="' . $expectedAgName . '"') !== false,
    'discovery essentials SSR shows official name from effective draft'
);
unset($_GET['guide_tab']);
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
$aiJs = (string) file_get_contents($root . '/admin/js/ai-presence.js');
assert_true(strpos($discJs, '__DISCOVERY_JS_EXECUTED__') !== false, 'discovery.js guards double execution');
assert_true(strpos($aiJs, '__AI_PRESENCE_JS_EXECUTED__') !== false, 'ai-presence.js guards double execution');
assert_true(strpos($aiJs, 'scheduleDiscoveryEmbedInit') !== false, 'ai-presence schedules full embed init after hydrate');
assert_true(strpos($aiJs, 'bindDiscoveryEmbedCaptureClicks') !== false, 'ai-presence capture-binds discovery embed clicks');
assert_true(strpos($aiJs, 'bindDiscoveryEmbedDocumentCapture') !== false, 'ai-presence document capture for embed actions');
assert_true(strpos($aiJs, 'DISCOVERY_MODAL_IDS') !== false, 'ai-presence discovery modal mount fallback ids');
assert_true(strpos($aiJs, 'var el = document.getElementById(id)') !== false, 'apFormEl prefers document getElementById');
assert_true(strpos($aiJs, 'return !!(embed && !embed.hidden)') !== false, 'embed modals gated on embed visibility only');
assert_true(strpos($discJs, "url.searchParams.set('guide_tab'") !== false, 'discovery guide nav updates guide_tab without reload');
assert_true(strpos($aiJs, 'ensureDiscoveryModalsMounted') !== false, 'ai-presence mounts discovery modals on body');
assert_true(strpos($aiJs, 'bindApModalForms') !== false, 'ai-presence binds modal forms on discovery repair');
assert_true(strpos($discJs, 'repairDiscoveryEmbedHandlers') !== false, 'discovery re-binds embed when tab shows');
assert_true(strpos($discJs, 'bindSeoPreviewFields') !== false, 'discovery binds SEO preview field listeners');
assert_true(strpos($discJs, 'bindDiscoveryGuideNavClicks') !== false, 'discovery binds agent guide sub-nav clicks');
assert_true(strpos($aiJs, 'inDiscoveryEmbed') !== false, 'ai-presence detects discovery embed context');
assert_true(strpos($discJs, 'syncDiscoveryHubPanels') !== false, 'discovery syncs hub panel active+hidden');
assert_true(strpos($aiJs, "data-hub-panel') === 'guide'") !== false, 'agent guide switchTab keeps guide hub panel visible in embed');
assert_true(strpos((string) file_get_contents($root . '/admin/css/pages/discovery.css'), 'data-hub-panel][hidden]') !== false, 'discovery embed hub hidden CSS guard');
assert_true(strpos((string) file_get_contents($root . '/admin/css/pages/discovery.css'), '#disc-ap-embed[hidden]') !== false, 'discovery embed hidden blocks pointer events');
assert_true(strpos($discJs, 'resetAgentGuideUiChrome') !== false || strpos($aiJs, 'resetAgentGuideUiChrome') !== false, 'discovery can reset agent guide overlays');
assert_true(strpos($aiJs, 'ag-wizard-modal') !== false, 'discovery reset dismisses body wizard modal');
assert_true(strpos($discJs, 'bindDiscoveryControls') !== false, 'discovery binds controls in finally block');
assert_true(strpos((string) file_get_contents($root . '/admin/js/agent-guide-wizard.js'), 'disc-ap-embed') !== false, 'wizard mounts in discovery embed when present');
assert_true(strpos((string) file_get_contents($root . '/admin/css/pages/discovery.css'), 'html[data-disc-tab]') !== false, 'wizard hidden via html disc tab attr');
assert_true(strpos((string) file_get_contents($root . '/admin/css/pages/ai-presence.css'), '.ap-ai-loading[hidden]') !== false, 'ai loading honors hidden attribute');

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
