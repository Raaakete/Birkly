<?php
/**
 * J-PORTFOLIO — Portfolio visitor (reference-projects/portfolio).
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-PORTFOLIO',
    'name' => 'Portfolio visitor',
    'server' => 'dev',
    'run' => function (array $ctx): void {
        $base = $ctx['base_url'];
        journey_install_reference_project('portfolio');

        $prefix = '/reference-projects/portfolio/website';
        $index = journey_http($base, $prefix . '/index.html');
        journey_assert('portfolio index HTTP 200', $index['code'] === 200);
        journey_assert('portfolio index has birkly-client.js', strpos($index['body'], 'birkly-client.js') !== false);

        $bundle = journey_page_bundle($base, [
            ['type' => 'list_entries', 'into' => 'projects', 'collection' => 'projects'],
        ]);
        journey_assert('page_bundle projects ok', ($bundle['status'] ?? '') === 'ok');
        $projects = $bundle['data']['projects'] ?? [];
        journey_assert('featured projects in bundle', is_array($projects) && count($projects) > 0);

        $detail = journey_http($base, $prefix . '/project.html?slug=brand-identity-acme');
        journey_assert('project detail HTTP 200', $detail['code'] === 200);
        journey_assert('project detail has birkly-client.js', strpos($detail['body'], 'birkly-client.js') !== false);
    },
];
