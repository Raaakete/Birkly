<?php
/**
 * J-WEBSITE — Birkly marketing site static smoke.
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-WEBSITE',
    'name' => 'Marketing website',
    'server' => 'isolated',
    'run' => function (array $ctx): void {
        $website = journey_sibling_repo('Birkly-Website');
        if ($website === null) {
            journey_fail('Birkly-Website sibling repo not found');
        }

        $port = 18110 + (getmypid() % 1000);
        $base = "http://127.0.0.1:$port";
        $pid = 0;

        try {
            $pid = journey_start_static_server($website, $port);

            foreach (['index.html', 'privacy.html', 'terms.html'] as $page) {
                $res = journey_http($base, '/' . $page);
                journey_assert("$page HTTP 200", $res['code'] === 200, 'code ' . $res['code']);
            }

            $index = journey_http($base, '/index.html');
            journey_assert(
                'waitlist CTA present',
                strpos($index['body'], 'beta_waitlist') !== false
                    || strpos($index['body'], '#getbirkly') !== false
                    || strpos($index['body'], 'waitlist') !== false
            );

            $components = @file_get_contents($website . '/js/components.js') ?: '';
            journey_assert('privacy link in site chrome', strpos($components, '/privacy.html') !== false);
            journey_assert('terms link in site chrome', strpos($components, '/terms.html') !== false);
        } finally {
            if ($pid > 0) {
                journey_stop_cms_server($pid);
            }
        }
    },
];
