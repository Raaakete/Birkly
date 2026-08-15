<?php
/**
 * Project SSR pipeline tests (P51-M2) — V-SSR-01..10,
 * plus P52-M2 cutover coverage — V-CUT-01..04/07/08,
 * plus P52-M3 defaults coverage — V-DEF-01/02/04/05/06.
 *
 * P52-M2: render is in-process PHP (core/birkly_template_engine.php).
 * The suite never shells out to Node — the legacy Node-collect parity block
 * was deleted with the Node SSR surface in P52-M4.
 *
 * P52-M3: render_mode defaults to server for Birkly hosting. A saved
 * "client" in settings/project.json must survive load (no migration).
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
    echo "\n[V-SSR settings, V-DEF-01/02] defaults + env override\n";

    // V-DEF-01: missing project.json → pure defaults apply (server since P52-M3).
    if (is_file($projectSettingsFile)) {
        unlink($projectSettingsFile);
    }
    putenv('BIRKLY_SSR_RENDER_MODE');
    $settings = birkly_project_ssr_settings();
    assert_true('default render_mode is server (V-DEF-01)', $settings['render_mode'] === 'server');
    assert_true('default hosting_type is birkly', $settings['hosting_type'] === 'birkly');
    assert_true('default ssr_cache_ttl is 300 (P58-M1)', $settings['ssr_cache_ttl'] === 300);

    // V-DEF-02: an existing saved "client" must never be overwritten by the
    // new default (migration-style assertion: only missing keys pick up server).
    ssr_test_write_project_settings(['render_mode' => 'client', 'ssr_cache_ttl' => 30]);
    $settings = birkly_project_ssr_settings();
    assert_true('saved render_mode=client preserved on load (V-DEF-02)', $settings['render_mode'] === 'client');
    assert_true('saved client not rewritten in project.json (V-DEF-02)', strpos((string) file_get_contents($projectSettingsFile), '"client"') !== false);

    // Saving an unrelated key must not persist a render_mode over the saved client.
    birkly_save_project_settings(['show_visit_button' => true]);
    $settings = birkly_project_ssr_settings();
    assert_true('unrelated save keeps saved client (V-DEF-02)', $settings['render_mode'] === 'client');

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
    assert_true('invalid render_mode falls back to server', $settings['render_mode'] === 'server');

    echo "\n[V-SSR-10, V-DEF-05/06] gating (hosting + mode + external)\n";

    // enabled requires hosting project mode + render_mode server/auto + hosting_type birkly
    ssr_test_set_hosting_project_mode();

    // V-DEF-05: with no project.json at all, Birkly hosting + project mode
    // now gets SSR by default (render_mode defaults to server).
    if (is_file($projectSettingsFile)) {
        unlink($projectSettingsFile);
    }
    assert_true('defaults + project hosting → SSR enabled (V-DEF-05)', birkly_project_ssr_enabled() === true);

    ssr_test_write_project_settings(['render_mode' => 'client', 'hosting_type' => 'birkly']);
    assert_true('client mode → SSR disabled', birkly_project_ssr_enabled() === false);

    ssr_test_write_project_settings(['render_mode' => 'server', 'hosting_type' => 'birkly']);
    assert_true('server mode + project hosting → SSR enabled', birkly_project_ssr_enabled() === true);

    ssr_test_write_project_settings(['render_mode' => 'auto', 'hosting_type' => 'birkly']);
    assert_true('auto mode + project hosting → SSR enabled', birkly_project_ssr_enabled() === true);

    ssr_test_write_project_settings(['render_mode' => 'server', 'hosting_type' => 'external']);
    assert_true('external hosting_type → SSR disabled (V-DEF-06)', birkly_project_ssr_enabled() === false);

    // V-DEF-06: external hosting cannot SSR even on pure defaults.
    ssr_test_write_project_settings(['hosting_type' => 'external']);
    assert_true('external + default render_mode → SSR disabled (V-DEF-06)', birkly_project_ssr_enabled() === false);

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

    echo "\n[V-SSR-06/07] cache put/get/ttl/invalidate\n";
    birkly_project_ssr_invalidate_all();
    $key = birkly_project_ssr_cache_key('/index.html', ['slug' => 'demo'], 'en', 'rev123');
    assert_true('cache_get miss returns null', birkly_project_ssr_cache_get($key, 60) === null);
    assert_true('cache_put succeeds', birkly_project_ssr_cache_put($key, '<html>cached</html>', 60, '/index.html', 'rev123') === true);
    assert_true('cache_get hit returns html', birkly_project_ssr_cache_get($key, 60) === '<html>cached</html>');

    // Source mtime/size mismatch busts stale cache even when TTL + revision key match.
    $fp = ['mtime' => 100, 'size' => 20, 'path' => '/tmp/stale.html'];
    assert_true('cache_put stores source fingerprint', birkly_project_ssr_cache_put($key, '<html>cached</html>', 60, '/index.html', 'rev123', $fp) === true);
    $liveFp = ['mtime' => 101, 'size' => 20, 'path' => '/tmp/stale.html'];
    assert_true('cache_get rejects stale source mtime', birkly_project_ssr_cache_get($key, 60, $liveFp) === null);

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

    echo "\n[V-SSR-02/04, V-CUT-01] render smoke via in-process PHP engine (required — no Node)\n";
    $rendered = birkly_project_ssr_render('<p>{title}</p>', ['title' => 'Hi']);
    assert_true('render returns rendered HTML', $rendered === '<p>Hi</p>', 'got: ' . var_export($rendered, true));
    $escaped = birkly_project_ssr_render("{for each entry in 'items'}{title}{endfor}", ['items' => [['title' => '<script>x</script>']]]);
    assert_true('render escapes untrusted field', is_string($escaped) && strpos($escaped, '<script>') === false && strpos($escaped, '&lt;script&gt;') !== false);
    $unescaped = birkly_project_ssr_render('<p>{title}</p>', ['title' => '<b>x</b>'], ['escapeHtml' => false]);
    assert_true('render passes escapeHtml=false through', $unescaped === '<p><b>x</b></p>', 'got: ' . var_export($unescaped, true));

    echo "\n[V-CUT-02] runtime status ready without Node\n";
    $status = birkly_project_ssr_runtime_status();
    assert_true('runtime status ready=true', ($status['ready'] ?? false) === true, 'status=' . json_encode($status));
    assert_true('runtime status engine=php', ($status['engine'] ?? null) === 'php');
    assert_true('runtime status reason is null (never node_missing)', array_key_exists('reason', $status) && $status['reason'] === null);

    echo "\n[V-CUT-02/08] render + runtime status in Node-absent subprocess (PATH without node)\n";
    $probeCode = 'require $argv[1] . "/core/project_ssr.php";'
        . '$out = birkly_project_ssr_render("<p>{title}</p>", ["title" => "Hi"]);'
        . 'echo (($out === "<p>Hi</p>") ? "RENDER_OK" : "RENDER_FAIL");'
        . '$st = birkly_project_ssr_runtime_status();'
        . 'echo "|" . ((($st["ready"] ?? false) === true) ? "READY" : "NOTREADY");'
        . 'echo "|reason=" . (($st["reason"] ?? null) === null ? "null" : (string) $st["reason"]);';
    $probeCmd = 'env PATH=/usr/bin:/bin '
        . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($probeCode) . ' ' . escapeshellarg($root);
    $probeOut = (string) shell_exec($probeCmd);
    assert_true('Node-absent PATH: render works', strpos($probeOut, 'RENDER_OK') !== false, 'out=' . $probeOut);
    assert_true('Node-absent PATH: runtime status ready', strpos($probeOut, '|READY|') !== false, 'out=' . $probeOut);
    assert_true('Node-absent PATH: reason=null', strpos($probeOut, 'reason=null') !== false, 'out=' . $probeOut);

    echo "\n[V-CUT-03] engine exception falls back cleanly (stubbed engine throws)\n";
    $throwCode = 'function birkly_template_engine_render($h, $d, $o = []) { throw new RuntimeException("boom"); }'
        . 'require $argv[1] . "/core/project_ssr.php";'
        . '$out = birkly_project_ssr_render("<p>{title}</p>", ["title" => "Hi"]);'
        . 'echo (($out === null) ? "NULL" : "NOTNULL") . "|" . birkly_project_ssr_last_error();';
    $throwCmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($throwCode) . ' ' . escapeshellarg($root) . ' 2>/dev/null';
    $throwOut = (string) shell_exec($throwCmd);
    assert_true('engine throw → render returns null', strpos($throwOut, 'NULL|') === 0, 'out=' . $throwOut);
    assert_true('engine throw → last_error=engine_error', strpos($throwOut, '|engine_error') !== false, 'out=' . $throwOut);

    echo "\n[V-SSR-06b] cheap revision + content bump busts cache key\n";
    require_once dirname(__DIR__) . '/core/page_bundle_helpers.php';
    require_once dirname(__DIR__) . '/core/storage/StorageFactory.php';
    birkly_project_ssr_invalidate_all();
    $storage = get_storage(birkly_project_root() . '/content');
    $rev1 = birkly_page_bundle_compute_revision($storage, [], 'en');
    birkly_public_content_revision_bump();
    $rev2 = birkly_page_bundle_compute_revision($storage, [], 'en');
    assert_true('content revision bump changes cache revision', $rev1 !== $rev2);

    echo "\n[V-SSR-06c] draft entries excluded from SSR output\n";
    $draftTpl = "{for each entry in 'blog'}{title}{endfor}";
    $draftData = birkly_project_ssr_build_data($draftTpl, [], 'en', $storage);
    $blogItems = $draftData['blog'] ?? [];
    $hasDraft = false;
    if (is_array($blogItems)) {
        foreach ($blogItems as $item) {
            if (is_array($item) && ($item['status'] ?? '') !== 'published') {
                $hasDraft = true;
                break;
            }
        }
    }
    assert_true('build_data blog list has no draft entries', $hasDraft === false);

    echo "\n[V-SSR-06d] cache write diagnostic on MISS serve\n";
    birkly_project_ssr_invalidate_all();
    ssr_test_set_hosting_project_mode();
    ssr_test_write_project_settings(['render_mode' => 'server', 'hosting_type' => 'birkly', 'ssr_cache_ttl' => 300]);
    $writeSmoke = $root . '/project/__p58-ssr-write-smoke.html';
    file_put_contents($writeSmoke, "<!DOCTYPE html><html><head><title>t</title></head><body><p>{current_year}</p></body></html>");
    try {
        ob_start();
        $servedWrite = birkly_project_ssr_serve($writeSmoke, []);
        $writeOut = (string) ob_get_clean();
        assert_true('write smoke serve succeeds', $servedWrite === true);
        // Header is set via birkly_project_ssr_send — verify cache files exist (write ok).
        $rel = birkly_project_ssr_relative_path($writeSmoke);
        $lang = birkly_project_ssr_language([]);
        $reqs = birkly_project_ssr_collect_requests((string) file_get_contents($writeSmoke));
        $rev = birkly_page_bundle_compute_revision($storage, $reqs, $lang, [$writeSmoke]);
        $wkey = birkly_project_ssr_cache_key($rel, [], $lang, $rev);
        $htmlCache = birkly_project_ssr_cache_dir() . '/' . $wkey . '.html';
        assert_true('cache write created html file', is_file($htmlCache), 'path=' . $htmlCache);
    } finally {
        @unlink($writeSmoke);
        birkly_project_ssr_invalidate_all();
    }

    echo "\n[V-CUT-01/02/04] serve path end-to-end (server mode, no Node, cache MISS→HIT)\n";
    ssr_test_set_hosting_project_mode();
    ssr_test_write_project_settings(['render_mode' => 'server', 'hosting_type' => 'birkly', 'ssr_cache_ttl' => 60]);
    birkly_project_ssr_invalidate_all();

    $smokeFile = $root . '/project/__p52-ssr-smoke.html';
    file_put_contents(
        $smokeFile,
        "<!DOCTYPE html>\n<html><head><title>SSR smoke</title></head>\n<body><p>Year: {current_year}</p></body></html>"
    );
    try {
        assert_true('smoke file gated for SSR', birkly_project_ssr_should_render($smokeFile) === true);

        ob_start();
        $served = birkly_project_ssr_serve($smokeFile, []);
        $firstOut = (string) ob_get_clean();
        assert_true('serve renders server-side (MISS)', $served === true);
        assert_true('served HTML has rendered {current_year}', strpos($firstOut, 'Year: ' . date('Y')) !== false, 'out=' . substr($firstOut, 0, 200));
        assert_true('served HTML has SSR marker', strpos($firstOut, 'name="birkly-ssr"') !== false);

        ob_start();
        $servedAgain = birkly_project_ssr_serve($smokeFile, []);
        $secondOut = (string) ob_get_clean();
        assert_true('second serve succeeds (HIT)', $servedAgain === true);
        assert_true('cache HIT returns identical HTML', $secondOut === $firstOut);

        // V-DEF-04: auto mode prefers the server path when the PHP engine is
        // available (renders server-side instead of falling back to client).
        ssr_test_write_project_settings(['render_mode' => 'auto', 'hosting_type' => 'birkly', 'ssr_cache_ttl' => 60]);
        birkly_project_ssr_invalidate_all();
        ob_start();
        $servedAuto = birkly_project_ssr_serve($smokeFile, []);
        $autoOut = (string) ob_get_clean();
        assert_true('auto mode renders server-side (V-DEF-04)', $servedAuto === true);
        assert_true('auto mode output is rendered', strpos($autoOut, 'Year: ' . date('Y')) !== false, 'out=' . substr($autoOut, 0, 200));
    } finally {
        @unlink($smokeFile);
        birkly_project_ssr_invalidate_all();
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
