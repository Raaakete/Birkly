<?php
/**
 * CMS marketplace installation review client tests.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/core/marketplace.php';

test_reset_failures();

$calls = [];
$GLOBALS['birkly_marketplace_mock_handler'] = static function (string $method, string $path, ?array $body, array $query) use (&$calls): array {
    $calls[] = compact('method', 'path', 'body', 'query');
    if ($method === 'POST' && str_ends_with($path, '/install')) {
        return ['ok' => true, 'status' => 200, 'data' => ['ok' => true, 'attestation' => ['offer_slug' => 'test-offer']]];
    }
    if ($method === 'GET' && str_ends_with($path, '/review')) {
        return [
            'ok' => true,
            'status' => 200,
            'data' => [
                'status' => [
                    'can_review' => true,
                    'has_review' => false,
                    'installed' => true,
                    'review' => null,
                ],
            ],
        ];
    }
    if ($method === 'POST' && str_ends_with($path, '/review')) {
        return [
            'ok' => true,
            'status' => 201,
            'data' => ['ok' => true, 'review' => ['id' => 'rev_test', 'rating' => 5, 'body' => 'Great']],
        ];
    }
    return ['ok' => false, 'status' => 404, 'data' => ['error' => 'not found', 'message' => 'not found']];
};

$GLOBALS['__birkly_installation_id'] = 'inst_cms_review_test';

test_assert('attest installation offer', birkly_marketplace_attest_installation_offer('test-offer', 'ABCD')['ok'] === true);
$status = birkly_marketplace_review_status('test-offer', 'ABCD');
test_assert('review status ok', $status['ok'] === true && !empty($status['can_review']));
$submit = birkly_marketplace_submit_installation_review('test-offer', 5, 'Excellent plugin', 'ABCD');
test_assert('submit review ok', $submit['ok'] === true && ($submit['review']['id'] ?? '') === 'rev_test');
test_assert('marketplace calls made', count($calls) >= 3);

unset($GLOBALS['birkly_marketplace_mock_handler']);
birkly_installation_id_reset();

exit(test_finish('marketplace_review_test'));
