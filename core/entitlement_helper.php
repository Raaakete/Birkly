<?php
/**
 * Marketplace entitlement helper for paid/free Hub plugins.
 */

function birkly_entitlement_base64url_decode(string $value): string|false
{
    $value = strtr($value, '-_', '+/');
    $pad = strlen($value) % 4;
    if ($pad > 0) {
        $value .= str_repeat('=', 4 - $pad);
    }

    return base64_decode($value, true);
}

function birkly_entitlement_secret(): string
{
    $env = trim((string) (getenv('BIRKLY_MARKETPLACE_ENTITLEMENT_SECRET') ?: ''));
    if ($env !== '') {
        return $env;
    }
    if (function_exists('birkly_marketplace_settings')) {
        return trim((string) (birkly_marketplace_settings()['entitlement_secret'] ?? ''));
    }

    return '';
}

/**
 * Decode and verify a marketplace entitlement token.
 *
 * Token format: base64url(json).hmac_sha256. When no shared secret is configured
 * the CMS trusts marketplace-issued tokens and relies on online re-checks when
 * marketplace APIs are reachable.
 *
 * @return array<string,mixed>|null
 */
function birkly_entitlement_token_payload(string $token): ?array
{
    $token = trim($token);
    if ($token === '' || !str_contains($token, '.')) {
        return null;
    }

    [$payloadPart, $signature] = explode('.', $token, 2);
    $json = birkly_entitlement_base64url_decode($payloadPart);
    if ($json === false || $json === '') {
        return null;
    }

    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        return null;
    }

    $secret = birkly_entitlement_secret();
    $payload['_token_verified'] = false;
    $payload['_token_trusted'] = $secret === '';
    if ($secret !== '') {
        $rawHmac = hash_hmac('sha256', $payloadPart, $secret, true);
        $hexHmac = hash_hmac('sha256', $payloadPart, $secret);
        $b64Hmac = rtrim(strtr(base64_encode($rawHmac), '+/', '-_'), '=');
        if (!hash_equals($hexHmac, $signature) && !hash_equals($b64Hmac, $signature)) {
            return null;
        }
        $payload['_token_verified'] = true;
        $payload['_token_trusted'] = true;
    }

    return $payload;
}

function birkly_entitlement_timestamp(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_int($value) || is_float($value) || (is_string($value) && ctype_digit($value))) {
        $ts = (int) $value;
        return $ts > 0 ? $ts : null;
    }
    if (is_string($value)) {
        $ts = strtotime($value);
        return $ts !== false ? $ts : null;
    }

    return null;
}

function birkly_entitlement_status_label(string $code): string
{
    if (function_exists('birkly_marketplace_error_message')) {
        return birkly_marketplace_error_message($code);
    }

    return match ($code) {
        'inactive', 'entitlement_inactive' => 'This entitlement is inactive.',
        'held', 'entitlement_held' => 'This entitlement is on hold.',
        'expired', 'entitlement_expired' => 'This entitlement has expired.',
        'revoked', 'entitlement_revoked' => 'This entitlement was revoked.',
        'seat_limit', 'seat_limit_reached' => 'This license has reached its activation or seat limit.',
        'wrong_offer', 'offer_mismatch' => 'This license belongs to a different Hub offer.',
        default => 'Marketplace entitlement check failed.',
    };
}

function birkly_entitlement_pricing_model(array $entitlement): string
{
    $model = strtolower(trim((string) (
        $entitlement['pricing_model']
        ?? $entitlement['model']
        ?? $entitlement['license_type']
        ?? $entitlement['type']
        ?? ''
    )));

    if (in_array($model, ['one-time', 'one_time', 'lifetime', 'free'], true)) {
        return $model === 'free' ? 'free' : 'one_time';
    }
    if (in_array($model, ['subscription', 'recurring'], true)) {
        return 'subscription';
    }
    if ($model === 'trial') {
        return 'trial';
    }

    return '';
}

function birkly_entitlement_end_timestamp(array $entitlement, string $model): ?int
{
    if ($model === 'trial' || (string) ($entitlement['status'] ?? '') === 'trialing') {
        return birkly_entitlement_timestamp($entitlement['trial_ends_at'] ?? $entitlement['ends_at'] ?? $entitlement['expires_at'] ?? null);
    }
    if ($model === 'subscription') {
        return birkly_entitlement_timestamp($entitlement['current_period_end'] ?? $entitlement['renews_at'] ?? $entitlement['expires_at'] ?? $entitlement['ends_at'] ?? null);
    }

    return birkly_entitlement_timestamp($entitlement['valid_until'] ?? $entitlement['expires_at'] ?? $entitlement['ends_at'] ?? null);
}

/**
 * @param array<string,mixed> $entitlement
 * @param array<string,mixed> $options
 * @return array<string,mixed>
 */
function birkly_entitlement_evaluate(array $entitlement, array $options = []): array
{
    $now = (int) ($options['now'] ?? time());
    $expectedOffer = trim((string) ($options['offer_slug'] ?? ''));
    $offerSlug = trim((string) ($entitlement['offer_slug'] ?? $entitlement['offer'] ?? ''));
    if ($expectedOffer !== '' && $offerSlug !== '' && $expectedOffer !== $offerSlug) {
        return [
            'ok' => false,
            'active' => false,
            'status' => 'wrong_offer',
            'code' => 'wrong_offer',
            'message' => birkly_entitlement_status_label('wrong_offer'),
        ];
    }

    $status = strtolower(trim((string) ($entitlement['status'] ?? 'active')));
    $model = birkly_entitlement_pricing_model($entitlement);
    if ($model === '' && $status === 'trialing') {
        $model = 'trial';
    }

    if (in_array($status, ['held', 'revoked', 'inactive', 'expired'], true)) {
        $code = $status === 'inactive' ? 'entitlement_inactive' : 'entitlement_' . $status;
        return [
            'ok' => false,
            'active' => false,
            'status' => $status,
            'code' => $code,
            'message' => birkly_entitlement_status_label($code),
        ];
    }

    if (!in_array($status, ['active', 'trialing', 'grace'], true)) {
        return [
            'ok' => false,
            'active' => false,
            'status' => $status !== '' ? $status : 'inactive',
            'code' => 'entitlement_inactive',
            'message' => birkly_entitlement_status_label('entitlement_inactive'),
        ];
    }

    $end = birkly_entitlement_end_timestamp($entitlement, $model);
    if (($model === 'trial' || $status === 'trialing') && $end !== null && $now > $end) {
        return [
            'ok' => false,
            'active' => false,
            'status' => 'expired',
            'code' => 'entitlement_expired',
            'message' => 'Trial entitlement has ended. Start a paid plan to continue.',
        ];
    }

    if ($end === null || $now <= $end) {
        return [
            'ok' => true,
            'active' => true,
            'status' => $status,
            'code' => $status,
            'message' => 'Entitlement active',
            'pricing_model' => $model,
            'offer_slug' => $offerSlug,
        ];
    }

    $graceDays = $model === 'subscription' ? 14 : ($model === 'one_time' || $model === 'free' ? 30 : 0);
    if ($graceDays <= 0) {
        return [
            'ok' => false,
            'active' => false,
            'status' => 'expired',
            'code' => 'entitlement_expired',
            'message' => birkly_entitlement_status_label('entitlement_expired'),
        ];
    }

    $storedGrace = birkly_entitlement_timestamp($options['grace_started_at'] ?? null);
    $graceStart = $storedGrace ?? $now;
    $graceEnds = $graceStart + ($graceDays * 86400);
    if ($now <= $graceEnds) {
        return [
            'ok' => true,
            'active' => true,
            'status' => 'grace',
            'code' => 'grace',
            'message' => 'Entitlement is in offline grace.',
            'pricing_model' => $model,
            'offer_slug' => $offerSlug,
            'grace_started_at' => gmdate('Y-m-d\TH:i:s\Z', $graceStart),
            'grace_ends_at' => gmdate('Y-m-d\TH:i:s\Z', $graceEnds),
            'grace_days' => $graceDays,
            'needs_grace_persist' => $storedGrace === null,
        ];
    }

    return [
        'ok' => false,
        'active' => false,
        'status' => 'expired',
        'code' => 'entitlement_expired',
        'message' => 'Offline grace has ended. Reconnect to Hub or renew this entitlement.',
        'pricing_model' => $model,
        'offer_slug' => $offerSlug,
    ];
}

/**
 * @return array<string,mixed>
 */
function birkly_plugin_entitlement_status(string $pluginId, ?string $capability = null): array
{
    if (!function_exists('get_all_plugins')) {
        require_once __DIR__ . '/plugins.php';
    }
    if (!function_exists('birkly_marketplace_plugin_offer_slug')) {
        require_once __DIR__ . '/marketplace.php';
    }

    global $birkly_plugins;
    $plugins = get_all_plugins();
    $plugin = $plugins[$pluginId] ?? null;
    if (!is_array($plugin)) {
        return [
            'ok' => false,
            'active' => false,
            'status' => 'missing_plugin',
            'code' => 'missing_plugin',
            'message' => 'Plugin is not installed.',
        ];
    }

    $entitlement = is_array($plugin['entitlement'] ?? null) ? $plugin['entitlement'] : [];
    $token = trim((string) ($plugin['entitlement_token'] ?? ''));
    if ($token !== '') {
        $payload = birkly_entitlement_token_payload($token);
        if ($payload === null) {
            return [
                'ok' => false,
                'active' => false,
                'status' => 'invalid_token',
                'code' => 'invalid_entitlement_token',
                'message' => 'Stored entitlement token is invalid.',
            ];
        }
        $entitlement = array_merge($entitlement, $payload);
    }

    if ($entitlement === []) {
        return [
            'ok' => false,
            'active' => false,
            'status' => 'missing_entitlement',
            'code' => 'missing_entitlement',
            'message' => 'No Hub entitlement is stored for this plugin.',
        ];
    }

    $offerSlug = birkly_marketplace_plugin_offer_slug($pluginId, $plugin);
    $result = birkly_entitlement_evaluate($entitlement, [
        'offer_slug' => $offerSlug,
        'grace_started_at' => $plugin['entitlement_grace_started_at'] ?? null,
    ]);

    if (!empty($result['ok']) && !empty($result['needs_grace_persist']) && isset($birkly_plugins[$pluginId])) {
        $birkly_plugins[$pluginId]['entitlement_grace_started_at'] = $result['grace_started_at'];
        if (function_exists('save_plugin_states')) {
            save_plugin_states();
        }
    }

    if ($capability !== null && $capability !== '' && !empty($result['ok'])) {
        $caps = is_array($entitlement['capabilities'] ?? null) ? $entitlement['capabilities'] : [];
        if ($caps !== [] && !in_array($capability, array_map('strval', $caps), true)) {
            return [
                'ok' => false,
                'active' => false,
                'status' => 'capability_missing',
                'code' => 'capability_missing',
                'message' => 'This entitlement does not include the requested capability.',
            ];
        }
    }

    return $result;
}

function birkly_plugin_has_entitlement(string $pluginId, ?string $capability = null): bool
{
    $status = birkly_plugin_entitlement_status($pluginId, $capability);
    return !empty($status['ok']);
}
