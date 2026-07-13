<?php
/**
 * J-BLOG — Blog visitor (reference-projects/blog).
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-BLOG',
    'name' => 'Blog visitor',
    'server' => 'dev',
    'run' => function (array $ctx): void {
        $base = $ctx['base_url'];
        journey_install_reference_project('blog');

        $prefix = '/reference-projects/blog/website';
        foreach (['index.html', 'blog.html', 'post.html?slug=welcome-to-birkly'] as $page) {
            $res = journey_http($base, $prefix . '/' . ltrim($page, '/'));
            journey_assert("GET $page HTTP 200", $res['code'] === 200, 'code ' . $res['code']);
            journey_assert("$page includes birkly-client.js", strpos($res['body'], 'birkly-client.js') !== false);
        }

        $bundle = journey_page_bundle($base, [
            ['type' => 'list_entries', 'into' => 'blog', 'collection' => 'blog'],
        ]);
        journey_assert('page_bundle status ok', ($bundle['status'] ?? '') === 'ok', json_encode($bundle));
        $entries = $bundle['data']['blog'] ?? [];
        journey_assert('page_bundle blog entries present', is_array($entries) && count($entries) > 0);
        $slugs = array_column($entries, 'slug');
        journey_assert('page_bundle includes welcome-to-birkly', in_array('welcome-to-birkly', $slugs, true));
    },
];
