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
$verify = birkly_discovery_run_verify();
assert_true($verify['ok'] === true, 'verify runs');
assert_true(count($verify['checks']) >= 3, 'verify has checks');
$map = birkly_discovery_url_map();
assert_true(isset($map['for_agents_md']), 'url map has for_agents_md key');

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
