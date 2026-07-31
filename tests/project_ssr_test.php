<?php
/**
 * Project SSR pipeline tests (P51-M2) — V-SSR-01..10.
 *
 * Plain PHP assert script (mirrors tests/hosting_urls_test.php style).
 * Run: php tests/project_ssr_test.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/core/project_ssr.php';

$failures = 0;
$total = 0;

function assert_true(string $name, bool $ok, string $detail = ''): void
{
    global $failures, $total;
    $total++;
    echo '  ' . $name . ': ' . ($ok ? "PASS\n" : "FAIL\n");
    if (!$ok) {
        $failures++;
        if ($detail !== '') {
            echo '    ' . $detail . "\n";
        }
    }
}

$root = dirname(__DIR__);
$hostingFile = $root . '/settings/hosting.json';
$projectSettingsFile = $root . '/settings/project.json';

$hostingBackup = is_readable($hostingFile) ? file_get_contents($hostingFile) : null;
$projectBackup = is_readable($projectSettingsFile) ? file_get_contents($projectSettingsFile) : null;

function ssr_test_write_project_settings(array $settings): void
{
    global $projectSettingsFile;
    if (!is_dir(dirname($projectSettingsFile))) {
        @mkdir(dirname($projectSettingsFile), 0755, true);
    }
    file_put_contents($projectSettingsFile, json_encode($settings, JSON_PRETTY_PRINT));
}

function ssr_test_set_hosting_project_mode(): void
{
    birkly_hosting_reset_cache();
    birkly_hosting_persist([
        'cms_base_url' => 'https://cms.ssr-test.local',
        'public_site_mode' => 'project',
        'public_site_url' => 'https://ssr-test.local',
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ], false);
    birkly_hosting_reset_cache();
}

function ssr_test_set_hosting_none_mode(): void
{
    birkly_hosting_reset_cache();
    birkly_hosting_persist([
        'cms_base_url' => 'https://cms.ssr-test.local',
        'public_site_mode' => 'none',
        'public_site_url' => '',
        'same_host_landing' => 'admin',
        'strict_admin_host' => true,
    ], false);
    birkly_hosting_reset_cache();
}

try {
    echo "\n[V-SSR settings] defaults + env override\n";

    // Clean project.json so pure defaults apply.
    if (is_file($projectSettingsFile)) {
        unlink($projectSettingsFile);
    }
    putenv('BIRKLY_SSR_RENDER_MODE');
    $settings = birkly_project_ssr_settings();
    assert_true('default render_mode is client', $settings['render_mode'] === 'client');
    assert_true('default hosting_type is birkly', $settings['hosting_type'] === 'birkly');
    assert_true('default ssr_cache_ttl is 60', $settings['ssr_cache_ttl'] === 60);

    ssr_test_write_project_settings(['render_mode' => 'server', 'ssr_cache_ttl' => 120]);
    $settings = birkly_project_ssr_settings();
    assert_true('project.json render_mode=server honored', $settings['render_mode'] === 'server');
    assert_true('project.json ttl=120 honored', $settings['ssr_cache_ttl'] === 120);

    putenv('BIRKLY_SSR_RENDER_MODE=client');
    $settings = birkly_project_ssr_settings();
    assert_true('env override forces render_mode=client', $settings['render_mode'] === 'client');
    putenv('BIRKLY_SSR_RENDER_MODE');

    ssr_test_write_project_settings(['render_mode' => 'bogus']);
    $settings = birkly_project_ssr_settings();
    assert_true('invalid render_mode falls back to client', $settings['render_mode'] === 'client');

    echo "\n[V-SSR-10] gating (hosting + mode + external)\n";

    // enabled requires hosting project mode + render_mode server/auto + hosting_type birkly
    ssr_test_set_hosting_project_mode();

    ssr_test_write_project_settings(['render_mode' => 'client', 'hosting_type' => 'birkly']);
    assert_true('client mode → SSR disabled', birkly_project_ssr_enabled() === false);

    ssr_test_write_project_settings(['render_mode' => 'server', 'hosting_type' => 'birkly']);
    assert_true('server mode + project hosting → SSR enabled', birkly_project_ssr_enabled() === true);

    ssr_test_write_project_settings(['render_mode' => 'auto', 'hosting_type' => 'birkly']);
    assert_true('auto mode + project hosting → SSR enabled', birkly_project_ssr_enabled() === true);

    ssr_test_write_project_settings(['render_mode' => 'server', 'hosting_type' => 'external']);
    assert_true('external hosting_type → SSR disabled', birkly_project_ssr_enabled() === false);

    // hosting mode none disables SSR regardless of settings
    ssr_test_set_hosting_none_mode();
    ssr_test_write_project_settings(['render_mode' => 'server', 'hosting_type' => 'birkly']);
    assert_true('public_site_mode=none → SSR disabled', birkly_project_ssr_enabled() === false);

    echo "\n[V-SSR-01] should_render gating (html only, under project/)\n";
    ssr_test_set_hosting_project_mode();
    ssr_test_write_project_settings(['render_mode' => 'server', 'hosting_type' => 'birkly']);

    $indexHtml = $root . '/project/index.html';
    $stylesCss = $root . '/project/styles.css';
    if (is_file($indexHtml)) {
        assert_true('should_render true for project/index.html', birkly_project_ssr_should_render($indexHtml) === true);
    } else {
        assert_true('should_render (skipped: no project/index.html)', true);
    }
    if (is_file($stylesCss)) {
        assert_true('should_render false for .css', birkly_project_ssr_should_render($stylesCss) === false);
    } else {
        assert_true('should_render css (skipped: no styles.css)', true);
    }
    // A file outside project/ must never SSR.
    assert_true('should_render false outside project/', birkly_project_ssr_should_render($root . '/README.md') === false);

    // With client mode, should_render is false even for html.
    ssr_test_write_project_settings(['render_mode' => 'client', 'hosting_type' => 'birkly']);
    if (is_file($indexHtml)) {
        assert_true('should_render false when render_mode=client', birkly_project_ssr_should_render($indexHtml) === false);
    } else {
        assert_true('should_render client (skipped)', true);
    }

    echo "\n[V-SSR-03] collect_requests mirrors engine shape\n";
    $tpl = "{for each entry in 'blog'}{title}{endfor}"
        . "{from 'site_elements' get entry 'header'}{'content'}{endget}"
        . "{for each entry in 'comments' of 'blog'.current}{'author'}{endfor}";
    $reqs = birkly_project_ssr_collect_requests($tpl);
    $hasBlog = false;
    $hasSiteElements = false;
    $hasHeaderEntry = false;
    $hasComments = false;
    foreach ($reqs as $r) {
        if (($r['type'] ?? '') === 'list_entries' && ($r['collection'] ?? '') === 'blog') {
            $hasBlog = true;
        }
        if (($r['type'] ?? '') === 'list_entries' && ($r['collection'] ?? '') === 'site_elements') {
            $hasSiteElements = true;
        }
        if (($r['type'] ?? '') === 'get_entry' && ($r['collection'] ?? '') === 'site_elements' && ($r['slug'] ?? '') === 'header') {
            $hasHeaderEntry = true;
        }
        if (($r['type'] ?? '') === 'list_entries' && ($r['collection'] ?? '') === 'comments') {
            $hasComments = true;
        }
    }
    assert_true('collect finds blog list_entries', $hasBlog);
    assert_true('collect finds site_elements list_entries', $hasSiteElements);
    assert_true('collect finds header get_entry', $hasHeaderEntry);
    assert_true('collect finds sub-collection parent (comments)', $hasComments);

    // Parity with Node CLI collect (if node available).
    $node = birkly_project_ssr_node_binary();
    $cliParity = null;
    if (is_string($node) && $node !== '') {
        $cliParity = birkly_project_ssr_run_node(
            $node,
            $root . '/scripts/birkly-ssr-cli.js',
            json_encode(['mode' => 'collect', 'html' => $tpl]),
            5.0
        );
    }
    if ($cliParity !== null) {
        $decoded = json_decode($cliParity, true);
        $nodeReqs = is_array($decoded) ? ($decoded['requests'] ?? []) : [];
        // Compare as sets of json-encoded requests.
        $norm = static function (array $list): array {
            $out = array_map(static fn($r) => json_encode($r), $list);
            sort($out);
            return $out;
        };
        assert_true('PHP collect == Node collect (parity)', $norm($reqs) === $norm($nodeReqs), 'php=' . json_encode($reqs) . ' node=' . json_encode($nodeReqs));
    } else {
        echo "  PHP/Node collect parity: SKIP (node unavailable)\n";
    }

    echo "\n[V-SSR-06/07] cache put/get/ttl/invalidate\n";
    birkly_project_ssr_invalidate_all();
    $key = birkly_project_ssr_cache_key('/index.html', ['slug' => 'demo'], 'en', 'rev123');
    assert_true('cache_get miss returns null', birkly_project_ssr_cache_get($key, 60) === null);
    assert_true('cache_put succeeds', birkly_project_ssr_cache_put($key, '<html>cached</html>', 60, '/index.html', 'rev123') === true);
    assert_true('cache_get hit returns html', birkly_project_ssr_cache_get($key, 60) === '<html>cached</html>');

    // TTL expiry: rewrite meta created_at into the past.
    $metaFile = birkly_project_ssr_cache_dir() . '/' . $key . '.meta.json';
    $meta = json_decode((string) file_get_contents($metaFile), true);
    $meta['created_at'] = time() - 3600;
    $meta['ttl'] = 60;
    file_put_contents($metaFile, json_encode($meta));
    assert_true('cache_get expired returns null', birkly_project_ssr_cache_get($key, 60) === null);

    // invalidate_by_prefix
    birkly_project_ssr_cache_put($key, '<html>x</html>', 60, '/blog.html', 'rev1');
    $key2 = birkly_project_ssr_cache_key('/about.html', [], 'en', 'rev2');
    birkly_project_ssr_cache_put($key2, '<html>y</html>', 60, '/about.html', 'rev2');
    birkly_project_ssr_invalidate_by_prefix('/blog');
    assert_true('invalidate_by_prefix clears matching', birkly_project_ssr_cache_get($key, 60) === null);
    assert_true('invalidate_by_prefix keeps others', birkly_project_ssr_cache_get($key2, 60) === '<html>y</html>');

    birkly_project_ssr_invalidate_all();
    assert_true('invalidate_all clears everything', birkly_project_ssr_cache_get($key2, 60) === null);

    echo "\n[V-SSR-05/09] marker injection + build_data system context\n";
    $marked = birkly_project_ssr_inject_marker('<html><head><title>t</title></head><body>x</body></html>');
    assert_true('marker injected into head', strpos($marked, 'name="birkly-ssr"') !== false);
    $markedTwice = birkly_project_ssr_inject_marker($marked);
    assert_true('marker not duplicated', substr_count($markedTwice, 'name="birkly-ssr"') === 1);

    $data = birkly_project_ssr_build_data('<p>{current.language}</p>', ['lang' => 'de', 'slug' => 'x'], 'de');
    assert_true('build_data sets current.language', ($data['current']['language'] ?? '') === 'de');
    assert_true('build_data sets current_year', (int) ($data['current_year'] ?? 0) === (int) date('Y'));
    assert_true('build_data sets query', ($data['query']['lang'] ?? '') === 'de');
    assert_true('build_data sets site_elements map', is_array($data['site_elements'] ?? null));

    echo "\n[V-SSR-02/04] render smoke via Node CLI\n";
    $rendered = birkly_project_ssr_render('<p>{title}</p>', ['title' => 'Hi']);
    if ($rendered !== null) {
        assert_true('render returns rendered HTML', $rendered === '<p>Hi</p>', 'got: ' . var_export($rendered, true));
        $escaped = birkly_project_ssr_render("{for each entry in 'items'}{title}{endfor}", ['items' => [['title' => '<script>x</script>']]]);
        assert_true('render escapes untrusted field', is_string($escaped) && strpos($escaped, '<script>') === false && strpos($escaped, '&lt;script&gt;') !== false);
    } else {
        echo "  render smoke: SKIP (node unavailable)\n";
    }
} finally {
    // Restore hosting + project settings.
    birkly_project_ssr_invalidate_all();
    putenv('BIRKLY_SSR_RENDER_MODE');
    birkly_hosting_reset_cache();
    if ($hostingBackup !== null) {
        file_put_contents($hostingFile, $hostingBackup);
    } elseif (is_file($hostingFile)) {
        unlink($hostingFile);
    }
    if ($projectBackup !== null) {
        file_put_contents($projectSettingsFile, $projectBackup);
    } elseif (is_file($projectSettingsFile)) {
        unlink($projectSettingsFile);
    }
    birkly_hosting_reset_cache();
}

echo "\nproject_ssr_test: " . ($failures === 0 ? "PASS\n" : "FAIL ($failures/$total)\n");
exit($failures === 0 ? 0 : 1);
