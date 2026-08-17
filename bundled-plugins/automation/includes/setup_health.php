<?php
/**
 * Automation Setup / connection readiness checks.
 */

require_once __DIR__ . '/../queue.php';
require_once __DIR__ . '/mailer.php';

/**
 * Installed plugin version from plugin.json (for Setup diagnostics).
 */
function automation_plugin_version(): string
{
    static $version = null;
    if (is_string($version)) {
        return $version;
    }
    $version = 'unknown';
    $path = dirname(__DIR__) . '/plugin.json';
    if (is_readable($path)) {
        $json = json_decode((string) file_get_contents($path), true);
        if (is_array($json) && isset($json['version'])) {
            $version = (string) $json['version'];
        }
    }
    return $version;
}

/**
 * File-based lock so duplicate test-email requests cannot send twice.
 *
 * @return array{acquired: bool}
 */
function automation_acquire_test_email_lock(string $to, int $ttlSeconds = 12): array
{
    $toKey = md5(strtolower(trim($to)));
    $sessionKey = 'automation_test_email_' . $toKey;
    $now = time();
    if (!empty($_SESSION[$sessionKey]) && ($now - (int) $_SESSION[$sessionKey]) < $ttlSeconds) {
        return ['acquired' => false];
    }

    $dir = automation_settings_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $lockPath = $dir . '/automation_test_email_' . $toKey . '.lock';
    $handle = @fopen($lockPath, 'c+');
    if (!$handle) {
        $_SESSION[$sessionKey] = $now;
        return ['acquired' => true];
    }

    try {
        flock($handle, LOCK_EX);
        $raw = stream_get_contents($handle);
        $last = is_string($raw) && is_numeric(trim($raw)) ? (int) trim($raw) : 0;
        if ($last > 0 && ($now - $last) < $ttlSeconds) {
            return ['acquired' => false];
        }
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) $now);
        fflush($handle);
        $_SESSION[$sessionKey] = $now;
        return ['acquired' => true];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function automation_release_test_email_lock(string $to): void
{
    $toKey = md5(strtolower(trim($to)));
    unset($_SESSION['automation_test_email_' . $toKey]);
    $lockPath = automation_settings_dir() . '/automation_test_email_' . $toKey . '.lock';
    if (is_file($lockPath)) {
        @unlink($lockPath);
    }
}

/**
 * Load automation mail settings (From address, SMTP, transport).
 *
 * @return array<string, mixed>
 */
function automation_get_mail_settings(): array
{
    $storage = automation_storage();
    $data = $storage->read('automation_mail.json') ?? [];
    if (!is_array($data)) {
        $data = [];
    }

    $fromEmail = trim((string) ($data['from_email'] ?? ''));
    $fromName = trim((string) ($data['from_name'] ?? 'Birkly'));
    if ($fromEmail === '') {
        $fromEmail = 'noreply@' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $fromEmail = preg_replace('/:\d+$/', '', $fromEmail) ?: 'noreply@localhost';
        if (strpos($fromEmail, '@') === false) {
            $fromEmail = 'noreply@localhost';
        }
    }

    $method = strtolower(trim((string) ($data['method'] ?? 'mail')));
    if (!in_array($method, ['mail', 'smtp', 'log'], true)) {
        $method = 'mail';
    }

    $encryption = strtolower(trim((string) ($data['smtp_encryption'] ?? 'tls')));
    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        $encryption = 'tls';
    }

    return [
        'method' => $method,
        'from_email' => $fromEmail,
        'from_name' => $fromName !== '' ? $fromName : 'Birkly',
        'smtp_host' => trim((string) ($data['smtp_host'] ?? '')),
        'smtp_port' => (int) ($data['smtp_port'] ?? 587),
        'smtp_username' => (string) ($data['smtp_username'] ?? ''),
        // Never expose password to admin GET responses unless explicitly requested.
        'smtp_password' => (string) ($data['smtp_password'] ?? ''),
        'smtp_encryption' => $encryption,
        'smtp_password_set' => trim((string) ($data['smtp_password'] ?? '')) !== '',
    ];
}

/**
 * Public-safe mail settings (password redacted).
 *
 * @return array<string, mixed>
 */
function automation_get_mail_settings_public(): array
{
    $mail = automation_get_mail_settings();
    unset($mail['smtp_password']);
    return $mail;
}

/**
 * @param array $input
 * @return array<string, mixed>|false
 */
function automation_save_mail_settings(array $input)
{
    $email = trim((string) ($input['from_email'] ?? ''));
    $name = trim((string) ($input['from_name'] ?? 'Birkly'));
    $method = strtolower(trim((string) ($input['method'] ?? 'mail')));
    if (!in_array($method, ['mail', 'smtp', 'log'], true)) {
        $method = 'mail';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if ($name === '') {
        $name = 'Birkly';
    }

    $storage = automation_storage();
    $existing = $storage->read('automation_mail.json') ?? [];
    if (!is_array($existing)) {
        $existing = [];
    }

    $encryption = strtolower(trim((string) ($input['smtp_encryption'] ?? 'tls')));
    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        $encryption = 'tls';
    }

    $password = (string) ($input['smtp_password'] ?? '');
    if ($password === '' && !empty($input['keep_smtp_password'])) {
        $password = (string) ($existing['smtp_password'] ?? '');
    }

    $payload = [
        'method' => $method,
        'from_email' => $email,
        'from_name' => $name,
        'smtp_host' => trim((string) ($input['smtp_host'] ?? '')),
        'smtp_port' => (int) ($input['smtp_port'] ?? 587),
        'smtp_username' => trim((string) ($input['smtp_username'] ?? '')),
        'smtp_password' => $password,
        'smtp_encryption' => $encryption,
        'updated_at' => date('c'),
    ];

    if ($method === 'smtp' && $payload['smtp_host'] === '') {
        return false;
    }

    if (!$storage->write('automation_mail.json', $payload)) {
        return false;
    }

    return automation_get_mail_settings_public();
}

/**
 * Build readiness checklist for Setup tab and editor warnings.
 *
 * @return array<string, mixed>
 */
function automation_setup_health(): array
{
    automation_ensure_mail_pipeline();

    $queue = get_automation_queue_status();
    $storage = automation_storage();
    $runtime = $storage->read('automation_runtime.json') ?? [];
    if (!is_array($runtime)) {
        $runtime = [];
    }

    $mail = automation_get_mail_settings_public();
    $mailFn = function_exists('mail');
    $curl = extension_loaded('curl');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $fromLooksLocal = (bool) preg_match('/@(localhost|local|example\.|test)$/i', $mail['from_email'])
        || str_contains($mail['from_email'], 'birkly.local');
    $method = (string) ($mail['method'] ?? 'mail');

    $checks = [];

    $pending = (int) ($queue['pending'] ?? 0);
    $waitingRetry = (int) ($queue['waiting_retry'] ?? 0);
    $processing = (int) ($queue['processing'] ?? 0);
    $queueWarn = ($pending > 0) || ($processing > 0) || ($waitingRetry > 0);
    $checks[] = [
        'id' => 'queue_processor',
        'label' => '1. Workflow runner (queue)',
        'status' => $queueWarn ? 'warn' : 'ok',
        'detail' => !empty($runtime['last_queue_run_at'])
            ? ('Last processed ' . $runtime['last_queue_run_at']
                . '. Pending: ' . $pending
                . ($waitingRetry > 0 ? (' (waiting retry: ' . $waitingRetry . ')') : '')
                . ($processing > 0 ? (', processing: ' . $processing) : '')
                . '. If a job is stuck, click Process queue now — that forces retries immediately.')
            : ('Pending: ' . $pending . ', completed: ' . (int) ($queue['completed'] ?? 0)
                . '. Jobs process automatically after triggers; click Process queue now if logs stay empty.'),
        'required_for' => ['all'],
        'why' => 'Without the runner, automations sit in a queue and never send email or call Slack.',
    ];

    if (function_exists('birkly_send_mail')) {
        $coreHealthy = null;
        if (function_exists('birkly_connection_health')) {
            $coreHealthy = birkly_connection_health('transactional_email');
        }
        $healthy = is_array($coreHealthy) && !empty($coreHealthy['healthy']);
        $checks[] = [
            'id' => 'email_connections',
            'label' => '2. Email transport (Settings → Connections)',
            'status' => $healthy ? 'ok' : 'warn',
            'detail' => $healthy
                ? 'Transactional email connection is healthy. Automation uses the shared Connections pipeline.'
                : 'Email delivery is now managed in Settings → Connections. Configure a transactional email connection to send real mail.',
            'required_for' => ['email'],
            'why' => 'Automation uses the shared Connections mail pipeline when it is available.',
        ];
    } elseif ($method === 'smtp') {
        $smtpReady = trim((string) ($mail['smtp_host'] ?? '')) !== '' && !empty($mail['smtp_password_set']);
        $checks[] = [
            'id' => 'email_smtp',
            'label' => '2. Email transport (SMTP)',
            'status' => $smtpReady ? 'ok' : 'fail',
            'detail' => $smtpReady
                ? ('SMTP host ' . $mail['smtp_host'] . ':' . (int) $mail['smtp_port'] . ' configured. Use “Send test email” to verify delivery.')
                : 'SMTP is selected but host/password are incomplete. Fill SMTP settings below, save, then send a test email.',
            'required_for' => ['email'],
            'why' => 'Cloud/Docker hosts almost never deliver PHP mail(). SMTP (or Log mode) is required for real inboxes.',
        ];
    } elseif ($method === 'log') {
        $checks[] = [
            'id' => 'email_log',
            'label' => '2. Email transport (Log only)',
            'status' => 'warn',
            'detail' => 'Emails are written to an internal log instead of being sent. Good for testing workflows; switch to SMTP when you need real delivery.',
            'required_for' => ['email'],
            'why' => 'Log mode proves the automation ran without needing an inbox.',
        ];
    } else {
        $checks[] = [
            'id' => 'email_mail_fn',
            'label' => '2. Email transport (PHP mail)',
            'status' => $mailFn ? 'warn' : 'fail',
            'detail' => $mailFn
                ? 'PHP mail() is selected. On most cloud hosts this accepts the message but never delivers it. Prefer SMTP.'
                : 'PHP mail() is not available. Choose SMTP or Log under Email settings.',
            'required_for' => ['email'],
            'why' => 'Send Email actions use this transport. Wrong transport = “success” with no inbox message.',
        ];
    }

    $checks[] = [
        'id' => 'email_from',
        'label' => '3. From address',
        'status' => $fromLooksLocal ? 'warn' : 'ok',
        'detail' => 'From: ' . $mail['from_name'] . ' <' . $mail['from_email'] . '>'
            . ($fromLooksLocal ? ' - use a real domain address your SMTP provider allows (SPF/DKIM).' : ''),
        'required_for' => ['email'],
        'why' => 'Providers reject or spam-folder messages from localhost/placeholder From addresses.',
    ];

    $checks[] = [
        'id' => 'outbound_http',
        'label' => '4. Outbound HTTP (Slack / Teams / webhooks)',
        'status' => $curl ? 'ok' : 'fail',
        'detail' => $curl
            ? 'curl is available. Paste an Incoming Webhook URL into each Slack/Teams/Webhook action — no separate OAuth connection.'
            : 'PHP curl extension is missing. Webhook, Slack, and Teams actions cannot run.',
        'required_for' => ['webhook', 'slack', 'teams'],
        'why' => 'These actions call external HTTPS URLs. There is no Slack “login” — only a webhook URL in the action.',
    ];

    $automations = function_exists('get_automations') ? get_automations() : [];
    $actionUsage = ['email' => 0, 'webhook' => 0, 'slack' => 0, 'teams' => 0];
    foreach ($automations as $automation) {
        foreach (($automation['actions'] ?? []) as $action) {
            $type = (string) ($action['type'] ?? '');
            if (isset($actionUsage[$type])) {
                $actionUsage[$type]++;
            }
        }
        foreach (($automation['graph']['nodes'] ?? []) as $node) {
            if (($node['kind'] ?? '') !== 'action') {
                continue;
            }
            $type = (string) ($node['type'] ?? '');
            if (isset($actionUsage[$type])) {
                $actionUsage[$type]++;
            }
        }
    }

    $blocking = [];
    $warnings = [];
    foreach ($checks as $check) {
        if ($check['status'] === 'fail') {
            foreach ($check['required_for'] as $need) {
                if ($need === 'all' || ($actionUsage[$need] ?? 0) > 0) {
                    $blocking[] = $check['label'] . ': ' . $check['detail'];
                }
            }
        }
        if ($check['status'] === 'warn') {
            foreach ($check['required_for'] as $need) {
                if ($need === 'all' || ($actionUsage[$need] ?? 0) > 0) {
                    $warnings[] = $check['label'] . ': ' . $check['detail'];
                }
            }
        }
    }

    if (($actionUsage['email'] ?? 0) > 0 && $method === 'mail' && !function_exists('birkly_send_mail')) {
        $warnings[] = 'Email actions are using PHP mail(). Configure an email connection under Settings → Connections.';
    }

    $recentJobs = function_exists('automation_get_recent_queue_jobs')
        ? automation_get_recent_queue_jobs(8)
        : [];

    if ($pending > 0) {
        $warnings[] = 'There are ' . $pending . ' pending queue job(s). Click Process queue now to run them immediately (ignores retry wait).';
    }
    foreach ($recentJobs as $job) {
        if (!empty($job['error']) && in_array(($job['status'] ?? ''), ['pending', 'failed', 'processing'], true)) {
            $warnings[] = 'Queue job error (' . ($job['trigger_type'] ?? '?') . '): ' . $job['error'];
        }
        if (!empty($job['warning']) && ($job['status'] ?? '') === 'completed') {
            $warnings[] = 'Queue job warning: ' . $job['warning'];
        }
    }

    if (function_exists('birkly_send_mail')) {
        $mail['method'] = 'connections';
        $mail['core_available'] = true;
    }

    return [
        'plugin_version' => automation_plugin_version(),
        'checks' => $checks,
        'queue' => $queue,
        'runtime' => [
            'last_queue_run_at' => $runtime['last_queue_run_at'] ?? null,
            'last_queue_processed' => $runtime['last_queue_processed'] ?? null,
            'last_job_notes' => $runtime['last_job_notes'] ?? [],
        ],
        'mail' => $mail,
        'sent_mail' => automation_get_sent_mail_log(5),
        'recent_jobs' => $recentJobs,
        'capabilities' => [
            'mail' => $mailFn,
            'curl' => $curl,
            'smtp' => true,
            'core_mail' => function_exists('birkly_send_mail'),
            'host' => $host,
        ],
        'action_usage' => $actionUsage,
        'blocking' => array_values(array_unique($blocking)),
        'warnings' => array_values(array_unique($warnings)),
        'ready' => $blocking === [],
        'guide' => [
            'title' => 'What Setup is for',
            'summary' => 'Setup configures how automations actually deliver messages. Creating a workflow is not enough — email needs a connection, Slack needs a webhook URL, and the queue must run.',
            'steps' => function_exists('birkly_send_mail') ? [
                'Configure a transactional email connection in Settings → Connections and send a test email.',
                'If jobs are pending, click Process queue now.',
                'Build your workflow (trigger + Send Email / Slack / webhook).',
                'Use Test (dry run) to validate config — it never sends.',
                'Use Confirm & Run Live, or create a real entry, to execute for real.',
            ] : [
                'Choose an email transport (SMTP recommended on cloud hosts) and send a test email.',
                'If jobs are pending, click Process queue now.',
                'Build your workflow (trigger + Send Email / Slack / webhook).',
                'Use Test (dry run) to validate config — it never sends.',
                'Use Confirm & Run Live, or create a real entry, to execute for real.',
            ],
        ],
    ];
}

/**
 * Describe what a dry-run action would do (no side effects).
 */
function automation_describe_dry_run_action(string $type, array $config, array $context): string
{
    $render = static function ($value) use ($context) {
        $value = (string) $value;
        if (function_exists('render_automation_template')) {
            return render_automation_template($value, $context);
        }
        return $value;
    };

    if ($type === 'email') {
        $to = $render($config['to'] ?? '');
        $subject = $render($config['subject'] ?? '');
        return 'Would send email to ' . ($to !== '' ? $to : '(missing recipient)')
            . ($subject !== '' ? ' - subject: ' . $subject : '')
            . ' (not sent - dry run)';
    }
    if ($type === 'webhook') {
        $url = $render($config['url'] ?? '');
        $method = $config['method'] ?? 'POST';
        return 'Would ' . $method . ' ' . ($url !== '' ? $url : '(missing URL)') . ' (not sent - dry run)';
    }
    if ($type === 'slack' || $type === 'teams') {
        $url = $render($config['webhook_url'] ?? $config['url'] ?? '');
        return 'Would post to ' . strtoupper($type) . ' webhook'
            . ($url !== '' ? ' (' . $url . ')' : '')
            . ' (not sent - dry run)';
    }
    if ($type === 'log') {
        return 'Would write log: ' . $render($config['message'] ?? '');
    }
    if ($type === 'set_status') {
        return 'Would set entry status to ' . ($config['status'] ?? '(missing)') . ' (not applied - dry run)';
    }
    if ($type === 'set_field') {
        return 'Would set field ' . ($config['field'] ?? '?') . ' (not applied - dry run)';
    }

    return 'Would execute action "' . $type . '" (not executed - dry run)';
}
