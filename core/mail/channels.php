<?php
/**
 * Notify channel dispatchers for Slack, Discord, and Twilio SMS (Phase 65 M6).
 */

declare(strict_types=1);

require_once __DIR__ . '/../birkly_paths.php';

function birkly_channel_post(string $url, string $body, array $headers = []): array
{
    if (isset($GLOBALS['__birkly_http_transport']) && is_callable($GLOBALS['__birkly_http_transport'])) {
        return call_user_func($GLOBALS['__birkly_http_transport'], $url, 'POST', $body, $headers, 30);
    }

    if (!extension_loaded('curl')) {
        return ['status' => 'failed', 'http_code' => 0, 'response' => '', 'error' => 'cURL extension is not available.'];
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['status' => 'failed', 'http_code' => 0, 'response' => '', 'error' => 'Could not initialize cURL.'];
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error !== '') {
        return ['status' => 'failed', 'http_code' => $httpCode, 'response' => is_string($response) ? $response : '', 'error' => $error];
    }
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['status' => 'accepted', 'http_code' => $httpCode, 'response' => is_string($response) ? $response : '', 'error' => null];
    }
    return ['status' => 'failed', 'http_code' => $httpCode, 'response' => is_string($response) ? $response : '', 'error' => 'HTTP ' . $httpCode];
}

function birkly_channel_dispatch(array $payload, array $connection): array
{
    $provider = (string) ($connection['provider'] ?? '');

    return match ($provider) {
        'slack' => birkly_channel_dispatch_slack($payload, $connection),
        'discord' => birkly_channel_dispatch_discord($payload, $connection),
        'twilio_sms' => birkly_channel_dispatch_twilio_sms($payload, $connection),
        default => ['status' => 'failed', 'provider' => $provider, 'error' => 'Unsupported channel provider.'],
    };
}

function birkly_channel_text(array $payload): string
{
    $text = trim((string) ($payload['text'] ?? ''));
    if ($text !== '') {
        return $text;
    }
    $subject = trim((string) ($payload['subject'] ?? ''));
    $body = trim(strip_tags((string) ($payload['html'] ?? '')));
    $parts = array_filter([$subject, $body], fn($v) => $v !== '');
    return implode("\n\n", $parts);
}

function birkly_channel_dispatch_slack(array $payload, array $connection): array
{
    $secrets = is_array($connection['secrets'] ?? null) ? $connection['secrets'] : [];
    $webhookUrl = (string) ($secrets['webhook_url'] ?? '');
    if ($webhookUrl === '' || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
        return ['status' => 'failed', 'provider' => 'slack', 'error' => 'Slack webhook URL is missing or invalid.'];
    }

    $config = is_array($connection['config'] ?? null) ? $connection['config'] : [];
    $channel = trim((string) ($config['channel'] ?? ''));

    $message = [
        'text' => birkly_channel_text($payload),
    ];
    if ($channel !== '') {
        $message['channel'] = $channel;
    }
    if (!empty($payload['slack_blocks']) && is_array($payload['slack_blocks'])) {
        $message['blocks'] = $payload['slack_blocks'];
    }

    $body = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $headers = ['Content-Type: application/json'];

    return birkly_channel_post($webhookUrl, $body, $headers);
}

function birkly_channel_dispatch_discord(array $payload, array $connection): array
{
    $secrets = is_array($connection['secrets'] ?? null) ? $connection['secrets'] : [];
    $webhookUrl = (string) ($secrets['webhook_url'] ?? '');
    if ($webhookUrl === '' || !filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
        return ['status' => 'failed', 'provider' => 'discord', 'error' => 'Discord webhook URL is missing or invalid.'];
    }

    $message = ['content' => birkly_channel_text($payload)];
    if (!empty($payload['username']) && is_string($payload['username'])) {
        $message['username'] = $payload['username'];
    }

    $body = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $headers = ['Content-Type: application/json'];

    return birkly_channel_post($webhookUrl, $body, $headers);
}

function birkly_channel_dispatch_twilio_sms(array $payload, array $connection): array
{
    $config = is_array($connection['config'] ?? null) ? $connection['config'] : [];
    $secrets = is_array($connection['secrets'] ?? null) ? $connection['secrets'] : [];

    $accountSid = (string) ($secrets['account_sid'] ?? '');
    $authToken = (string) ($secrets['auth_token'] ?? '');
    $fromNumber = (string) ($config['from_number'] ?? '');
    $toNumber = (string) ($payload['to_number'] ?? '');
    $bodyText = birkly_channel_text($payload);

    if ($accountSid === '' || $authToken === '') {
        return ['status' => 'failed', 'provider' => 'twilio_sms', 'error' => 'Twilio account SID or auth token missing.'];
    }
    if ($fromNumber === '' || $toNumber === '') {
        return ['status' => 'failed', 'provider' => 'twilio_sms', 'error' => 'From or to phone number missing.'];
    }
    if ($bodyText === '') {
        return ['status' => 'failed', 'provider' => 'twilio_sms', 'error' => 'Message body is empty.'];
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($accountSid) . '/Messages.json';
    $form = http_build_query([
        'From' => $fromNumber,
        'To' => $toNumber,
        'Body' => $bodyText,
    ], '', '&', PHP_QUERY_RFC3986);

    $headers = [
        'Authorization: Basic ' . base64_encode($accountSid . ':' . $authToken),
        'Content-Type: application/x-www-form-urlencoded',
    ];

    return birkly_channel_post($url, $form, $headers);
}
