<?php
/**
 * J-SOCIAL — Community member (user_management + social reference project).
 */

require_once __DIR__ . '/_helpers.php';

return [
    'id' => 'J-SOCIAL',
    'name' => 'Social community member',
    'server' => 'dev',
    'run' => function (array $ctx): void {
        $base = $ctx['base_url'];
        journey_install_dev_plugins();
        journey_activate_plugins('commerce', 'user_management');
        journey_install_reference_project('social');

        $collectionPath = journey_repo_root() . '/content/collections/social_posts/collection.json';
        $backup = journey_enable_collection_public_api('social_posts', true);
        journey_grant_collection_create('social_posts');

        $cookieFile = journey_temp_cookie_file('social');
        $email = 'journey-social-' . getmypid() . '@demo.social';
        $password = 'SocialPass123!';
        $postContent = 'Journey social post ' . getmypid();

        try {
            $register = journey_http($base, '/api/index.php?route=user_registration&action=register', [
                'method' => 'POST',
                'headers' => ['Content-Type: application/x-www-form-urlencoded'],
                'body' => http_build_query([
                    'email' => $email,
                    'password' => $password,
                    'name' => 'Journey Social User',
                ]),
                'cookie_file' => $cookieFile,
            ]);
            journey_assert('register HTTP 200/201', in_array($register['code'], [200, 201], true), $register['body']);

            $login = journey_http($base, '/api/index.php?route=user_auth&action=login', [
                'method' => 'POST',
                'headers' => ['Content-Type: application/x-www-form-urlencoded'],
                'body' => http_build_query([
                    'email' => $email,
                    'password' => $password,
                ]),
                'cookie_file' => $cookieFile,
            ]);
            $loginData = birkly_test_json_decode($login['body']);
            journey_assert('login ok', ($loginData['success'] ?? false) === true || isset($loginData['user']), $login['body']);

            $submit = journey_http($base, '/api/public_submit.php', [
                'method' => 'POST',
                'headers' => ['Content-Type: application/json'],
                'body' => json_encode([
                    'collection' => 'social_posts',
                    'fields' => [
                        'content' => $postContent,
                        'visibility' => 'public',
                    ],
                ]),
                'cookie_file' => $cookieFile,
            ]);
            $submitData = birkly_test_json_decode($submit['body']);
            journey_assert('create post HTTP 200', $submit['code'] === 200, $submit['body']);
            journey_assert('create post status ok', ($submitData['status'] ?? '') === 'ok', $submit['body']);

            $bundle = journey_page_bundle($base, [
                ['type' => 'list_entries', 'into' => 'social_posts', 'collection' => 'social_posts'],
            ]);
            journey_assert('feed bundle ok', ($bundle['status'] ?? '') === 'ok');
            $feedJson = json_encode($bundle['data']['social_posts'] ?? []);
            journey_assert('feed contains new post', strpos($feedJson, $postContent) !== false, $feedJson);

            $index = journey_http($base, '/reference-projects/social/website/index.html');
            journey_assert('social index HTTP 200', $index['code'] === 200);
            journey_assert('social index has birkly-client.js', strpos($index['body'], 'birkly-client.js') !== false);
        } finally {
            journey_restore_file($collectionPath, $backup);
            @unlink($cookieFile);
        }
    },
];
