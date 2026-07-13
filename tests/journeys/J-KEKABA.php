<?php
/**
 * J-KEKABA — Production reference site (live path, no birkly-baked).
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-KEKABA',
    'name' => 'Kekaba live path',
    'server' => 'isolated',
    'run' => function (array $ctx): void {
        $kekabaSrc = journey_sibling_repo('kekaba');
        if ($kekabaSrc === null) {
            journey_fail('kekaba sibling repo not found');
        }

        $workDir = sys_get_temp_dir() . '/birkly-journey-kekaba-' . journey_unique_suffix();
        $port = journey_allocate_port(100);
        $base = "http://127.0.0.1:$port";
        $pid = 0;

        try {
            exec('cp -a ' . escapeshellarg($kekabaSrc) . ' ' . escapeshellarg($workDir), $out, $code);
            if ($code !== 0 || !is_dir($workDir)) {
                journey_fail('failed to copy kekaba to temp workspace');
            }

            $migrate = journey_run_script(
                $ctx['repo_root'] . '/scripts/migrate-kekaba-live-path.sh',
                [$workDir]
            );
            if (!$migrate['ok']) {
                journey_fail('migrate-kekaba-live-path.sh failed: ' . $migrate['output']);
            }

            $pid = journey_start_static_server($workDir, $port);

            $index = journey_http($base, '/index.html');
            journey_assert('kekaba index HTTP 200', $index['code'] === 200);
            journey_assert('no birkly-baked meta tag', stripos($index['body'], '<meta name="birkly-baked"') === false);
            journey_assert('birkly-client.js present', strpos($index['body'], 'birkly-client.js') !== false);

            foreach (['blog.html', 'service.html'] as $page) {
                $res = journey_http($base, '/' . $page);
                journey_assert("$page HTTP 200", $res['code'] === 200);
            }

            $health = journey_http($base, '/Birkly-0.1/api/health.php');
            journey_assert('CMS embed health HTTP 200', $health['code'] === 200, 'code ' . $health['code']);
        } finally {
            if ($pid > 0) {
                journey_stop_cms_server($pid);
            }
            journey_remove_tree($workDir);
        }
    },
];
