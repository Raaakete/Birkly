<?php
/**
 * Marketplace entitlement token + offline grace helper smoke tests.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/core/entitlement_helper.php';

$errors = [];

function test_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

function test_b64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function test_token(array $payload, string $secret): string
{
    $payloadPart = test_b64url(json_encode($payload));
    return $payloadPart . '.' . hash_hmac('sha256', $payloadPart, $secret);
}

$secret = 'entitlement-test-secret';
putenv('BIRKLY_MARKETPLACE_ENTITLEMENT_SECRET=' . $secret);

$token = test_token([
    'offer_slug' => 'paid-plugin',
    'status' => 'active',
    'pricing_model' => 'one_time',
], $secret);
$payload = birkly_entitlement_token_payload($token);
test_assert(is_array($payload) && !empty($payload['_token_verified']), 'signed token verifies with shared secret');
test_assert(birkly_entitlement_token_payload($token . 'bad') === null, 'tampered token fails verification');

$now = strtotime('2026-08-03T00:00:00Z');
$subscription = birkly_entitlement_evaluate([
    'offer_slug' => 'paid-plugin',
    'status' => 'active',
    'pricing_model' => 'subscription',
    'current_period_end' => '2026-08-01T00:00:00Z',
], ['offer_slug' => 'paid-plugin', 'now' => $now]);
test_assert(!empty($subscription['ok']) && ($subscription['status'] ?? '') === 'grace', 'subscription enters 14 day offline grace');
test_assert(($subscription['grace_days'] ?? 0) === 14, 'subscription grace is 14 days');

$oneTimeExpired = birkly_entitlement_evaluate([
    'offer_slug' => 'paid-plugin',
    'status' => 'active',
    'pricing_model' => 'one_time',
    'valid_until' => '2026-07-01T00:00:00Z',
], [
    'offer_slug' => 'paid-plugin',
    'now' => $now,
    'grace_started_at' => '2026-07-01T00:00:00Z',
]);
test_assert(empty($oneTimeExpired['ok']) && ($oneTimeExpired['code'] ?? '') === 'entitlement_expired', 'one-time grace ends after 30 days');

$trialExpired = birkly_entitlement_evaluate([
    'offer_slug' => 'trial-plugin',
    'status' => 'trialing',
    'trial_ends_at' => '2026-08-01T00:00:00Z',
], ['offer_slug' => 'trial-plugin', 'now' => $now]);
test_assert(empty($trialExpired['ok']) && ($trialExpired['code'] ?? '') === 'entitlement_expired', 'trial does not extend past end');

$held = birkly_entitlement_evaluate([
    'offer_slug' => 'paid-plugin',
    'status' => 'held',
], ['offer_slug' => 'paid-plugin', 'now' => $now]);
test_assert(empty($held['ok']) && ($held['code'] ?? '') === 'entitlement_held', 'held entitlement is blocked clearly');

$wrongOffer = birkly_entitlement_evaluate([
    'offer_slug' => 'other-plugin',
    'status' => 'active',
], ['offer_slug' => 'paid-plugin', 'now' => $now]);
test_assert(empty($wrongOffer['ok']) && ($wrongOffer['code'] ?? '') === 'wrong_offer', 'wrong offer is blocked clearly');

putenv('BIRKLY_MARKETPLACE_ENTITLEMENT_SECRET');

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "marketplace_entitlement_helper_test: PASS\n";
exit(0);
