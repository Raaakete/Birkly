<?php
/**
 * J-HEALTH — Operations smoke subset (health.php, production-smoke, admin login).
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-HEALTH',
    'name' => 'Operations health smoke',
    'server' => 'dev',
    'run' => function (array $ctx): void {
        $base = $ctx['base_url'];
        $root = $ctx['repo_root'];

        putenv('BIRKLY_SMOKE_BASE_URL=' . $base);
        putenv('BIRKLY_SMOKE_SKIP_MARKETPLACE=1');
        $smoke = journey_run_script('scripts/production-smoke.sh', [$base]);
        journey_assert('production-smoke.sh passes', $smoke['ok'], $smoke['output']);

        $health = journey_http($base, '/api/health.php');
        journey_assert('health.php HTTP 200', in_array($health['code'], [200, 503], true), 'code ' . $health['code']);
        $healthData = birkly_test_json_decode($health['body']);
        journey_assert('health writable settings', !empty($healthData['writable']['settings']['writable']));
        journey_assert('health writable content', !empty($healthData['writable']['content']['writable']));
        journey_assert('health writable cache', !empty($healthData['writable']['cache']['writable']));
        journey_assert('health writable logs', !empty($healthData['writable']['logs']['writable']));

        $login = journey_http($base, '/admin/login.php');
        journey_assert('admin login page HTTP 200', in_array($login['code'], [200, 302], true), 'code ' . $login['code']);
    },
];
