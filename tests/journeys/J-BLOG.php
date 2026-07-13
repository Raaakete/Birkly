<?php
/**
 * J-BLOG — Blog visitor: list → open post → read article content.
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-BLOG',
    'name' => 'Blog visitor',
    'server' => 'dev',
    'run' => function (array $ctx): void {
        $base = $ctx['base_url'];
        journey_prepare_reference_demo('blog');

        $prefix = '/reference-projects/blog/website';
        $blogList = journey_http($base, $prefix . '/blog.html');
        journey_assert('blog list HTTP 200', $blogList['code'] === 200);
        journey_assert('blog list has birkly-client.js', strpos($blogList['body'], 'birkly-client.js') !== false);

        $post = journey_http($base, $prefix . '/post.html?slug=welcome-to-birkly');
        journey_assert('post page HTTP 200', $post['code'] === 200);
        journey_assert('post page has birkly-client.js', strpos($post['body'], 'birkly-client.js') !== false);
        journey_assert('post page has birkly-collection blog', stripos($post['body'], 'birkly-collection') !== false);

        $entry = journey_page_bundle($base, [
            ['type' => 'get_entry', 'into' => 'post', 'collection' => 'blog', 'slug' => 'welcome-to-birkly'],
        ]);
        journey_assert('get_entry status ok', ($entry['status'] ?? '') === 'ok', json_encode($entry));
        $postData = $entry['data']['post'] ?? [];
        journey_assert('post title present', ($postData['title'] ?? '') !== '', json_encode($postData));
        journey_assert(
            'post body contains Welcome to Birkly',
            stripos(json_encode($postData), 'Welcome to Birkly') !== false,
            json_encode($postData)
        );

        $list = journey_page_bundle($base, [
            ['type' => 'list_entries', 'into' => 'blog', 'collection' => 'blog'],
        ]);
        journey_assert('blog list bundle ok', ($list['status'] ?? '') === 'ok');
        $slugs = array_column($list['data']['blog'] ?? [], 'slug');
        journey_assert('blog list includes welcome-to-birkly', in_array('welcome-to-birkly', $slugs, true));
    },
];
