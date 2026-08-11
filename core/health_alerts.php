<?php
/**
 * Health alert dispatch (P13-M4) — email + optional webhook when status is error/degraded.
 */

declare(strict_types=1);

require_once __DIR__ . '/birkly_paths.php';
require_once __DIR__ . '/mail/notify.php';

/**
 * @return array{alert_email?:string,alert_webhook_url?:string}
 */
function birkly_health_monitoring_settings(): array
{
    if (isset($GLOBALS['birkly_health_monitoring_override']) && is_array($GLOBALS['birkly_health_monitoring_override'])) {
        return $GLOBALS['birkly_health_monitoring_override'];
    }

    $path = birkly_project_root() . '/settings/monitoring.json';
    if (!is_file($path)) {
        return [];
    }

    $data = json_decode((string) file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function birkly_health_should_alert(string $status): bool
{
    return in_array($status, ['error', 'degraded'], true);
}

/**
 * @return array{dispatched:bool,reason?:string,email?:bool,webhook?:bool}
 */
function birkly_health_dispatch_alerts(array $report): array
{
    $status = (string) ($report['status'] ?? '');
    if (!birkly_health_should_alert($status)) {
        return ['dispatched' => false, 'reason' => 'status_ok'];
    }

    if (isset($GLOBALS['birkly_health_alert_mock']) && is_callable($GLOBALS['birkly_health_alert_mock'])) {
        return (array) ($GLOBALS['birkly_health_alert_mock'])($report);
    }

    $settings = birkly_health_monitoring_settings();
    $email = trim((string) ($settings['alert_email'] ?? ''));
    $webhook = trim((string) ($settings['alert_webhook_url'] ?? ''));

    if ($email === '' && $webhook === '') {
        return ['dispatched' => false, 'reason' => 'no_targets'];
    }

    $subject = 'Birkly health alert: ' . $status;
    $body = birkly_health_alert_message($report);
    $results = ['dispatched' => true, 'email' => false, 'webhook' => false];

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $sendResult = birkly_notify('health_alert', [
            'to' => $email,
            'subject' => $subject,
            'message' => $body,
        ]);
        $results['email'] = birkly_notify_results_any_success($sendResult);
    }

    if ($webhook !== '') {
        $results['webhook'] = birkly_health_post_webhook($webhook, $report);
    }

    return $results;
}

function birkly_health_alert_message(array $report): string
{
    $status = (string) ($report['status'] ?? 'unknown');
    $service = (string) ($report['service'] ?? 'birkly-cms');
    $version = (string) ($report['version'] ?? '');
    $time = (string) ($report['time'] ?? gmdate('c'));

    $lines = [
        "Birkly health alert",
        '',
        "Status: {$status}",
        "Service: {$service}",
        "Version: {$version}",
        "Time: {$time}",
        '',
    ];

    $checks = $report['checks'] ?? [];
    if (is_array($checks) && $checks !== []) {
        $lines[] = 'Checks:';
        foreach ($checks as $name => $check) {
            if (!is_array($check)) {
                continue;
            }
            $checkStatus = (string) ($check['status'] ?? 'unknown');
            $lines[] = "  - {$name}: {$checkStatus}";
        }
        $lines[] = '';
    }

    $writable = $report['writable'] ?? [];
    if (is_array($writable)) {
        foreach ($writable as $dir => $info) {
            if (!is_array($info) || !empty($info['writable'])) {
                continue;
            }
            $lines[] = "Not writable: {$dir}";
        }
    }

    if (!empty($report['disk']['low_space'])) {
        $lines[] = 'Disk space is low.';
    }

    $marketplace = $report['marketplace'] ?? [];
    if (is_array($marketplace) && ($marketplace['status'] ?? '') === 'unreachable') {
        $lines[] = 'Marketplace unreachable: ' . (string) ($marketplace['url'] ?? '');
    }

    return implode("\n", $lines);
}

function birkly_health_post_webhook(string $url, array $report): bool
{
    if (!function_exists('curl_init')) {
        return false;
    }

    $payload = json_encode([
        'event' => 'health.alert',
        'status' => $report['status'] ?? 'unknown',
        'service' => $report['service'] ?? 'birkly-cms',
        'time' => $report['time'] ?? gmdate('c'),
        'report' => $report,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $code >= 200 && $code < 300;
}
