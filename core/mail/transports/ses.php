<?php
/**
 * Thin HTTP adapter for Amazon SES using the HTTPS API (AWS Signature V4).
 *
 * Uses SES v2 SendEmail endpoint: POST /v2/email/outbound-emails
 */

declare(strict_types=1);

function birkly_mail_transport_ses(array $payload, array $connection): array
{
    $secrets = is_array($connection['secrets'] ?? null) ? $connection['secrets'] : [];
    $config = is_array($connection['config'] ?? null) ? $connection['config'] : [];
    $accessKey = (string) ($secrets['access_key'] ?? '');
    $secretKey = (string) ($secrets['secret_key'] ?? '');
    $region = (string) ($config['region'] ?? 'us-east-1');
    $connectionId = $connection['id'] ?? null;
    $alias = $connection['alias'] ?? null;

    if ($accessKey === '' || $secretKey === '') {
        return birkly_mail_result('failed', 'ses', $connectionId, $alias, 'AWS access key and secret key are required.');
    }

    $to = $payload['to'] ?? [];
    $from = (string) ($payload['from'] ?? '');
    $fromName = trim((string) ($payload['from_name'] ?? ''));

    $content = [];
    if (!empty($payload['html'])) {
        $content['Simple'] = [
            'Subject' => ['Data' => $payload['subject'] ?? '', 'Charset' => 'UTF-8'],
            'Body' => ['Html' => ['Data' => $payload['html'], 'Charset' => 'UTF-8']],
        ];
        if (!empty($payload['text'])) {
            $content['Simple']['Body']['Text'] = ['Data' => $payload['text'], 'Charset' => 'UTF-8'];
        }
    } elseif (!empty($payload['text'])) {
        $content['Simple'] = [
            'Subject' => ['Data' => $payload['subject'] ?? '', 'Charset' => 'UTF-8'],
            'Body' => ['Text' => ['Data' => $payload['text'], 'Charset' => 'UTF-8']],
        ];
    }

    $requestBody = [
        'FromEmailAddress' => $fromName !== '' ? birkly_mail_encode_address($fromName, $from) : $from,
        'Destination' => ['ToAddresses' => $to],
        'Content' => $content,
    ];
    if (!empty($payload['reply_to'])) {
        $requestBody['ReplyToAddresses'] = [$payload['reply_to']];
    }

    $host = 'email.' . $region . '.amazonaws.com';
    $uri = '/v2/email/outbound-emails';
    $service = 'ses';

    $result = birkly_aws_signed_post($host, $uri, $service, $region, $requestBody, $accessKey, $secretKey);
    return birkly_mail_map_http_status($result, 'ses', $connectionId, $alias);
}

function birkly_aws_signed_post(
    string $host,
    string $uri,
    string $service,
    string $region,
    array $payload,
    string $accessKey,
    string $secretKey
): array {
    $algorithm = 'AWS4-HMAC-SHA256';
    $now = gmdate('Ymd\THis\Z');
    $dateStamp = substr($now, 0, 8);
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $payloadHash = hash('sha256', $payloadJson);

    $headers = [
        'host' => $host,
        'x-amz-date' => $now,
        'content-type' => 'application/json',
    ];
    $signedHeaders = implode(';', array_keys($headers));
    $canonicalHeaders = '';
    foreach ($headers as $k => $v) {
        $canonicalHeaders .= strtolower($k) . ':' . trim($v) . "\n";
    }

    $canonicalRequest = "POST\n" . $uri . "\n\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
    $credentialScope = $dateStamp . '/' . $region . '/' . $service . '/aws4_request';
    $stringToSign = $algorithm . "\n" . $now . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);

    $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    $authHeader = $algorithm . ' Credential=' . $accessKey . '/' . $credentialScope . ', SignedHeaders=' . $signedHeaders . ', Signature=' . $signature;

    $headerList = [
        'Authorization: ' . $authHeader,
        'Host: ' . $host,
        'X-Amz-Date: ' . $now,
        'Content-Type: application/json',
    ];

    return birkly_mail_http_post('https://' . $host . $uri, $payloadJson, $headerList, false, true);
}
