<?php
/**
 * Analytics enrichment and aggregation helpers (zero external dependencies).
 */

/**
 * Parse a user agent into device, browser, and OS labels.
 *
 * @return array{device_type:string,browser:string,os:string}
 */
function birkly_analytics_parse_user_agent(string $userAgent): array
{
    $ua = strtolower($userAgent);
    $deviceType = 'desktop';

    if ($ua === '') {
        return ['device_type' => 'unknown', 'browser' => 'Unknown', 'os' => 'Unknown'];
    }

    if (preg_match('/bot|crawl|spider|slurp|mediapartners|facebookexternalhit/i', $userAgent)) {
        return ['device_type' => 'bot', 'browser' => 'Bot', 'os' => 'Bot'];
    }

    if (preg_match('/ipad|tablet|kindle|playbook/i', $userAgent)) {
        $deviceType = 'tablet';
    } elseif (preg_match('/mobile|iphone|ipod|android.*mobile|windows phone|blackberry/i', $userAgent)) {
        $deviceType = 'mobile';
    }

    $browser = 'Unknown';
    if (str_contains($ua, 'edg/')) {
        $browser = 'Edge';
    } elseif (str_contains($ua, 'chrome/') && !str_contains($ua, 'chromium')) {
        $browser = 'Chrome';
    } elseif (str_contains($ua, 'firefox/')) {
        $browser = 'Firefox';
    } elseif (str_contains($ua, 'safari/') && !str_contains($ua, 'chrome/')) {
        $browser = 'Safari';
    } elseif (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) {
        $browser = 'Opera';
    }

    $os = 'Unknown';
    if (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
        $os = 'iOS';
    } elseif (str_contains($ua, 'android')) {
        $os = 'Android';
    } elseif (str_contains($ua, 'windows nt')) {
        $os = 'Windows';
    } elseif (str_contains($ua, 'mac os x') || str_contains($ua, 'macintosh')) {
        $os = 'macOS';
    } elseif (str_contains($ua, 'linux')) {
        $os = 'Linux';
    } elseif (str_contains($ua, 'cros')) {
        $os = 'Chrome OS';
    }

    return [
        'device_type' => $deviceType,
        'browser' => $browser,
        'os' => $os,
    ];
}

/**
 * Infer a coarse visitor location without external geo databases.
 *
 * @return array{country:string,region:string,source:string}
 */
function birkly_analytics_infer_location(string $ip, string $language = '', string $timezone = ''): array
{
    $country = '';
    $region = '';
    $source = 'unknown';

    $headers = [
        'HTTP_CF_IPCOUNTRY',
        'HTTP_X_APPENGINE_COUNTRY',
        'HTTP_X_COUNTRY_CODE',
        'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
    ];

    foreach ($headers as $header) {
        $value = strtoupper(trim((string) ($_SERVER[$header] ?? '')));
        if ($value !== '' && $value !== 'XX' && preg_match('/^[A-Z]{2}$/', $value)) {
            return [
                'country' => $value,
                'region' => '',
                'source' => 'proxy_header',
            ];
        }
    }

    if ($timezone !== '') {
        $mapped = birkly_analytics_country_from_timezone($timezone);
        if ($mapped !== '') {
            return [
                'country' => $mapped,
                'region' => $timezone,
                'source' => 'timezone',
            ];
        }
    }

    if ($language !== '') {
        $parts = explode('-', str_replace('_', '-', $language));
        if (count($parts) >= 2) {
            $candidate = strtoupper($parts[1]);
            if (preg_match('/^[A-Z]{2}$/', $candidate)) {
                return [
                    'country' => $candidate,
                    'region' => '',
                    'source' => 'language',
                ];
            }
        }
    }

    if ($ip !== '' && $ip !== 'unknown' && filter_var($ip, FILTER_VALIDATE_IP)) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return ['country' => 'LOCAL', 'region' => '', 'source' => 'private_ip'];
        }
    }

    return ['country' => $country, 'region' => $region, 'source' => $source];
}

/**
 * Map common IANA time zones to a representative country code.
 */
function birkly_analytics_country_from_timezone(string $timezone): string
{
    $timezone = trim($timezone);
    if ($timezone === '') {
        return '';
    }

    $map = [
        'Europe/Copenhagen' => 'DK',
        'Europe/Stockholm' => 'SE',
        'Europe/Oslo' => 'NO',
        'Europe/Berlin' => 'DE',
        'Europe/Paris' => 'FR',
        'Europe/London' => 'GB',
        'Europe/Amsterdam' => 'NL',
        'Europe/Madrid' => 'ES',
        'Europe/Rome' => 'IT',
        'Europe/Warsaw' => 'PL',
        'America/New_York' => 'US',
        'America/Chicago' => 'US',
        'America/Denver' => 'US',
        'America/Los_Angeles' => 'US',
        'America/Toronto' => 'CA',
        'Australia/Sydney' => 'AU',
        'Asia/Tokyo' => 'JP',
        'Asia/Singapore' => 'SG',
    ];

    if (isset($map[$timezone])) {
        return $map[$timezone];
    }

    if (preg_match('#^([A-Za-z]+)/#', $timezone, $matches)) {
        $area = $matches[1];
        $areaMap = [
            'Europe' => 'EU',
            'America' => 'AM',
            'Asia' => 'AS',
            'Africa' => 'AF',
            'Australia' => 'AU',
            'Pacific' => 'OC',
        ];
        return $areaMap[$area] ?? '';
    }

    return '';
}

/**
 * Build a normalized analytics event from the tracking payload.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function birkly_analytics_build_event(array $input, string $ip, string $userAgent, int $timestamp): array
{
    $trackingId = (string) ($input['tracking_id'] ?? '');
    $path = (string) ($input['path'] ?? '/');
    $referrer = (string) ($input['referrer'] ?? '');
    $language = (string) ($input['language'] ?? '');
    $timezone = (string) ($input['timezone'] ?? '');
    $eventType = strtolower(trim((string) ($input['event_type'] ?? 'page_view')));
    if ($eventType === '' || $eventType === 'pageview') {
        $eventType = 'page_view';
    }
    $allowedTypes = [
        'page_view',
        'pageview',
        'page_exit',
        'form_submit',
        'outbound_click',
        'plugin_event',
    ];
    if (!in_array($eventType, $allowedTypes, true)) {
        $eventType = 'page_view';
    }
    if ($eventType === 'pageview') {
        $eventType = 'page_view';
    }

    $ua = birkly_analytics_parse_user_agent($userAgent);
    $location = birkly_analytics_infer_location($ip, $language, $timezone);

    $referrerHost = '';
    if ($referrer !== '') {
        $referrerHost = (string) (parse_url($referrer, PHP_URL_HOST) ?? '');
    }

    $entrancePath = trim((string) ($input['entrance_path'] ?? ''));
    if ($entrancePath === '') {
        $entrancePath = $path;
    }

    $ipHash = hash('sha256', $ip . $trackingId);
    $visitorDayKey = hash('sha256', 'vd:' . $trackingId . ':' . date('Y-m-d', $timestamp) . ':' . $ip);

    return [
        'timestamp' => $timestamp,
        'event_type' => $eventType,
        'domain' => (string) ($input['domain'] ?? ''),
        'path' => $path,
        'entrance_path' => $entrancePath,
        'referrer' => $referrer,
        'referrer_host' => $referrerHost,
        'utm_source' => trim((string) ($input['utm_source'] ?? '')),
        'utm_medium' => trim((string) ($input['utm_medium'] ?? '')),
        'utm_campaign' => trim((string) ($input['utm_campaign'] ?? '')),
        'user_agent' => $userAgent,
        // Privacy-safe visitor key (never raw IP). Presets keep ip_hash when hash_ip is on.
        'ip_hash' => $ipHash,
        'ip' => $ipHash,
        'visitor_day_key' => $visitorDayKey,
        'screen_width' => (int) ($input['screen_width'] ?? 0),
        'screen_height' => (int) ($input['screen_height'] ?? 0),
        'viewport_width' => (int) ($input['viewport_width'] ?? 0),
        'viewport_height' => (int) ($input['viewport_height'] ?? 0),
        'language' => $language,
        'timezone' => $timezone,
        'device_type' => $ua['device_type'],
        'browser' => $ua['browser'],
        'os' => $ua['os'],
        'country' => $location['country'],
        'region' => $location['region'],
        'location_source' => $location['source'],
        'session_id' => (string) ($input['session_id'] ?? ''),
        'event_name' => (string) ($input['event_name'] ?? ''),
        'event_data' => is_array($input['event_data'] ?? null) ? $input['event_data'] : [],
    ];
}

function birkly_analytics_visitor_key(array $event): string
{
    $ipHash = (string) ($event['ip_hash'] ?? $event['ip'] ?? '');
    if ($ipHash !== '') {
        return $ipHash;
    }

    return (string) ($event['visitor_day_key'] ?? '');
}

/**
 * Aggregate stored analytics events into a report.
 *
 * @param list<array<string, mixed>> $events
 * @return array<string, mixed>
 */
function birkly_analytics_aggregate_events(array $events): array
{
    $pageViews = 0;
    $uniqueVisitors = [];
    $pageCounts = [];
    $referrerCounts = [];
    $dailyStats = [];
    $deviceCounts = [];
    $browserCounts = [];
    $osCounts = [];
    $countryCounts = [];
    $entranceCounts = [];
    $exitCounts = [];
    $trafficSources = [];
    $campaignCounts = [];
    $sessions = [];
    $eventCounts = [
        'page_view' => 0,
        'form_submit' => 0,
        'outbound_click' => 0,
        'plugin_event' => 0,
        'page_exit' => 0,
    ];
    $outboundTargets = [];
    $formIds = [];
    $pluginEvents = [];

    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }

        $eventType = (string) ($event['event_type'] ?? 'pageview');
        if ($eventType === 'pageview' || $eventType === '') {
            $eventType = 'page_view';
        }
        if (isset($eventCounts[$eventType])) {
            $eventCounts[$eventType]++;
        }
        if ($eventType === 'page_exit') {
            $path = (string) ($event['path'] ?? '/');
            $exitCounts[$path] = ($exitCounts[$path] ?? 0) + 1;
            continue;
        }

        $visitorKey = birkly_analytics_visitor_key($event);
        $isPageView = $eventType === 'page_view';

        if ($visitorKey !== '' && $isPageView) {
            $uniqueVisitors[$visitorKey] = true;
        }

        $path = (string) ($event['path'] ?? '/');
        if ($isPageView) {
            $pageViews++;
            $pageCounts[$path] = ($pageCounts[$path] ?? 0) + 1;
        } elseif ($eventType === 'outbound_click') {
            $href = '';
            if (is_array($event['event_data'] ?? null)) {
                $href = (string) ($event['event_data']['href'] ?? $event['event_data']['url'] ?? '');
            }
            if ($href === '') {
                $href = (string) ($event['event_name'] ?? $path);
            }
            if ($href !== '') {
                $outboundTargets[$href] = ($outboundTargets[$href] ?? 0) + 1;
            }
        } elseif ($eventType === 'form_submit') {
            $formId = '';
            if (is_array($event['event_data'] ?? null)) {
                $formId = (string) ($event['event_data']['form_id'] ?? '');
            }
            if ($formId === '') {
                $formId = (string) ($event['event_name'] ?? 'form');
            }
            $formIds[$formId] = ($formIds[$formId] ?? 0) + 1;
        } elseif ($eventType === 'plugin_event') {
            $name = (string) ($event['event_name'] ?? 'plugin_event');
            $pluginEvents[$name] = ($pluginEvents[$name] ?? 0) + 1;
        }

        if (!$isPageView) {
            continue;
        }

        $referrerHost = (string) ($event['referrer_host'] ?? '');
        if ($referrerHost === '' && !empty($event['referrer'])) {
            $referrerHost = (string) (parse_url((string) $event['referrer'], PHP_URL_HOST) ?? '');
        }
        if ($referrerHost !== '' && $referrerHost !== ($event['domain'] ?? '')) {
            $referrerCounts[$referrerHost] = ($referrerCounts[$referrerHost] ?? 0) + 1;
            $trafficSources['referral:' . $referrerHost] = ($trafficSources['referral:' . $referrerHost] ?? 0) + 1;
        }

        $utmSource = (string) ($event['utm_source'] ?? '');
        $utmCampaign = (string) ($event['utm_campaign'] ?? '');
        if ($utmSource !== '') {
            $label = 'campaign:' . $utmSource;
            if (!empty($event['utm_medium'])) {
                $label .= '/' . $event['utm_medium'];
            }
            $trafficSources[$label] = ($trafficSources[$label] ?? 0) + 1;
        } elseif ($referrerHost === '') {
            $trafficSources['direct'] = ($trafficSources['direct'] ?? 0) + 1;
        }
        if ($utmCampaign !== '') {
            $campaignLabel = $utmCampaign;
            if ($utmSource !== '') {
                $campaignLabel .= ' (' . $utmSource . ')';
            }
            $campaignCounts[$campaignLabel] = ($campaignCounts[$campaignLabel] ?? 0) + 1;
        } elseif ($utmSource !== '') {
            $campaignCounts[$utmSource] = ($campaignCounts[$utmSource] ?? 0) + 1;
        }

        $deviceType = (string) ($event['device_type'] ?? 'unknown');
        $deviceCounts[$deviceType] = ($deviceCounts[$deviceType] ?? 0) + 1;

        $browser = (string) ($event['browser'] ?? 'Unknown');
        $browserCounts[$browser] = ($browserCounts[$browser] ?? 0) + 1;

        $os = (string) ($event['os'] ?? 'Unknown');
        $osCounts[$os] = ($osCounts[$os] ?? 0) + 1;

        $country = (string) ($event['country'] ?? '');
        if ($country !== '') {
            $countryCounts[$country] = ($countryCounts[$country] ?? 0) + 1;
        }

        $sessionId = (string) ($event['session_id'] ?? '');
        if ($sessionId !== '') {
            if (!isset($sessions[$sessionId])) {
                $sessions[$sessionId] = [
                    'first_path' => $path,
                    'last_path' => $path,
                    'views' => 0,
                ];
            }
            $sessions[$sessionId]['last_path'] = $path;
            $sessions[$sessionId]['views']++;
        }

        $timestamp = (int) ($event['timestamp'] ?? time());
        $date = date('Y-m-d', $timestamp);
        if (!isset($dailyStats[$date])) {
            $dailyStats[$date] = ['views' => 0, 'visitors' => []];
        }
        $dailyStats[$date]['views']++;
        if ($visitorKey !== '') {
            $dailyStats[$date]['visitors'][$visitorKey] = true;
        }
    }

    $singlePageSessions = 0;
    foreach ($sessions as $session) {
        $first = (string) ($session['first_path'] ?? '/');
        $last = (string) ($session['last_path'] ?? '/');
        $entranceCounts[$first] = ($entranceCounts[$first] ?? 0) + 1;
        $exitCounts[$last] = ($exitCounts[$last] ?? 0) + 1;
        if ((int) ($session['views'] ?? 0) <= 1) {
            $singlePageSessions++;
        }
    }

    $sessionCount = count($sessions);
    $formattedDailyStats = [];
    foreach ($dailyStats as $date => $stats) {
        $formattedDailyStats[] = [
            'date' => $date,
            'views' => $stats['views'],
            'visitors' => count($stats['visitors']),
        ];
    }
    usort($formattedDailyStats, static function ($a, $b) {
        return strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
    });

    $uniqueCount = count($uniqueVisitors);
    $bounceRate = $sessionCount > 0 ? round(($singlePageSessions / $sessionCount) * 100, 1) : 0.0;
    $pagesPerVisitor = $uniqueCount > 0 ? round($pageViews / $uniqueCount, 2) : 0.0;

    return [
        'page_views' => $pageViews,
        'unique_visitors' => $uniqueCount,
        'sessions' => $sessionCount,
        'bounce_rate' => $bounceRate,
        'pages_per_visitor' => $pagesPerVisitor,
        'event_counts' => $eventCounts,
        'top_pages' => birkly_analytics_rank_counts($pageCounts, 'path', 'views', 15),
        'entrance_pages' => birkly_analytics_rank_counts($entranceCounts, 'path', 'entrances', 10),
        'exit_pages' => birkly_analytics_rank_counts($exitCounts, 'path', 'exits', 10),
        'referrers' => birkly_analytics_rank_counts($referrerCounts, 'domain', 'visits', 10),
        'traffic_sources' => birkly_analytics_rank_counts($trafficSources, 'source', 'visits', 10),
        'campaigns' => birkly_analytics_rank_counts($campaignCounts, 'campaign', 'visits', 10),
        'devices' => birkly_analytics_rank_counts($deviceCounts, 'device_type', 'visits', 10),
        'browsers' => birkly_analytics_rank_counts($browserCounts, 'browser', 'visits', 10),
        'operating_systems' => birkly_analytics_rank_counts($osCounts, 'os', 'visits', 10),
        'countries' => birkly_analytics_rank_counts($countryCounts, 'country', 'visits', 10),
        'outbound_targets' => birkly_analytics_rank_counts($outboundTargets, 'url', 'clicks', 10),
        'forms' => birkly_analytics_rank_counts($formIds, 'form', 'submits', 10),
        'plugin_events' => birkly_analytics_rank_counts($pluginEvents, 'event', 'count', 10),
        'daily_stats' => $formattedDailyStats,
    ];
}

/**
 * @param array<string, int> $counts
 * @return list<array<string, mixed>>
 */
function birkly_analytics_rank_counts(array $counts, string $labelKey, string $valueKey, int $limit): array
{
    arsort($counts);
    $rows = [];
    foreach (array_slice($counts, 0, $limit, true) as $label => $count) {
        $rows[] = [$labelKey => (string) $label, $valueKey => (int) $count];
    }

    return $rows;
}

/**
 * Load raw events for a tracking id and date range.
 *
 * @return list<array<string, mixed>>
 */
function birkly_analytics_load_events(string $trackingId, string $startDate, string $endDate): array
{
    $analyticsDir = dirname(__DIR__) . '/data/analytics';
    $dataDir = $analyticsDir . '/data/' . $trackingId;
    if (!is_dir($dataDir)) {
        return [];
    }

    $start = strtotime($startDate);
    $end = strtotime($endDate . ' 23:59:59');
    if ($start === false || $end === false) {
        return [];
    }

    $allEvents = [];
    $current = $start;
    while ($current <= $end) {
        $date = date('Y-m-d', $current);
        $eventFile = $dataDir . '/' . $date . '.json';
        if (is_file($eventFile)) {
            $events = json_decode((string) file_get_contents($eventFile), true);
            if (is_array($events)) {
                $allEvents = array_merge($allEvents, $events);
            }
        }
        $current = strtotime('+1 day', $current);
    }

    return $allEvents;
}

/**
 * Combined report across all enabled tracking ids (deduped visitors).
 *
 * @return array<string, mixed>
 */
function birkly_analytics_get_combined_report(string $startDate, string $endDate): array
{
    require_once __DIR__ . '/analytics_config.php';

    $trackingIds = birkly_analytics_list_enabled_tracking_ids();
    $allEvents = [];
    $domains = [];

    foreach ($trackingIds as $trackingId) {
        $events = birkly_analytics_load_events($trackingId, $startDate, $endDate);
        $domains[] = [
            'id' => $trackingId,
            'domain' => $trackingId,
            'report' => birkly_analytics_aggregate_events($events),
        ];
        $allEvents = array_merge($allEvents, $events);
    }

    $combined = birkly_analytics_aggregate_events($allEvents);
    $combined['tracking_ids'] = $trackingIds;
    $combined['domains'] = $domains;

    return $combined;
}

function birkly_analytics_effective_retention_days(): int
{
    if (!function_exists('birkly_analytics_active_preset')) {
        require_once __DIR__ . '/analytics_presets.php';
    }

    $preset = birkly_analytics_active_preset();
    $settings = birkly_analytics_load_settings();
    $days = $settings['retention_days'] ?? $preset['retention_days'] ?? 90;

    return max(0, (int) $days);
}

/**
 * Delete event files older than the configured retention window.
 */
function birkly_analytics_purge_expired(?int $retentionDays = null): int
{
    if (!function_exists('birkly_analytics_collecting')) {
        require_once __DIR__ . '/analytics_presets.php';
    }
    if (!birkly_analytics_collecting()) {
        return 0;
    }

    $retentionDays = $retentionDays ?? birkly_analytics_effective_retention_days();
    if ($retentionDays <= 0) {
        return 0;
    }

    require_once __DIR__ . '/analytics_config.php';

    $cutoffTs = strtotime('-' . $retentionDays . ' days');
    if ($cutoffTs === false) {
        return 0;
    }

    $deleted = 0;
    $dataRoot = dirname(__DIR__) . '/data/analytics/data';
    if (!is_dir($dataRoot)) {
        return 0;
    }

    foreach (scandir($dataRoot) ?: [] as $trackingId) {
        if ($trackingId === '.' || $trackingId === '..') {
            continue;
        }
        $dir = $dataRoot . '/' . $trackingId;
        if (!is_dir($dir)) {
            continue;
        }
        foreach (scandir($dir) ?: [] as $file) {
            if (!preg_match('/^(\d{4}-\d{2}-\d{2})\.json$/', $file, $matches)) {
                continue;
            }
            $fileTs = strtotime($matches[1] . ' 00:00:00');
            if ($fileTs !== false && $fileTs < $cutoffTs) {
                if (@unlink($dir . '/' . $file)) {
                    $deleted++;
                }
            }
        }
    }

    return $deleted;
}

/**
 * Collection health metadata for admin surfaces.
 *
 * @return array<string, mixed>
 */
function birkly_analytics_collection_health(): array
{
    require_once __DIR__ . '/analytics_config.php';
    if (!function_exists('birkly_analytics_active_preset')) {
        require_once __DIR__ . '/analytics_presets.php';
    }

    $trackingIds = birkly_analytics_list_enabled_tracking_ids();
    $dataRoot = dirname(__DIR__) . '/data/analytics/data';
    $lastEventAt = 0;
    $cappedDomains = [];
    $eventFiles = 0;

    foreach ($trackingIds as $trackingId) {
        $dir = $dataRoot . '/' . $trackingId;
        if (!is_dir($dir)) {
            continue;
        }
        $metaFile = $dir . '/_meta.json';
        if (is_file($metaFile)) {
            $meta = json_decode((string) file_get_contents($metaFile), true);
            if (is_array($meta)) {
                if (!empty($meta['capped_dates']) && is_array($meta['capped_dates'])) {
                    foreach (array_keys($meta['capped_dates']) as $date) {
                        $cappedDomains[] = ['domain' => $trackingId, 'date' => (string) $date];
                    }
                }
                $lastEventAt = max($lastEventAt, (int) ($meta['last_event_at'] ?? 0));
            }
        }
        foreach (scandir($dir) ?: [] as $file) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}\.json$/', $file)) {
                continue;
            }
            $eventFiles++;
            $events = json_decode((string) file_get_contents($dir . '/' . $file), true);
            if (!is_array($events)) {
                continue;
            }
            foreach ($events as $event) {
                if (!is_array($event)) {
                    continue;
                }
                $lastEventAt = max($lastEventAt, (int) ($event['timestamp'] ?? 0));
            }
        }
    }

    $preset = birkly_analytics_active_preset();

    return [
        'collecting' => birkly_analytics_collecting(),
        'preset' => (string) ($preset['id'] ?? 'off'),
        'consent_required' => birkly_analytics_consent_required(),
        'retention_days' => birkly_analytics_effective_retention_days(),
        'enabled_domains' => $trackingIds,
        'enabled_domain_count' => count($trackingIds),
        'event_files' => $eventFiles,
        'last_event_at' => $lastEventAt > 0 ? date('c', $lastEventAt) : null,
        'capped_domains' => $cappedDomains,
        'daily_event_cap' => birkly_analytics_daily_event_cap(),
    ];
}

function birkly_analytics_daily_event_cap(): int
{
    return 5000;
}

function birkly_analytics_mark_event_meta(string $trackingId, int $timestamp, bool $capped = false): void
{
    $dir = dirname(__DIR__) . '/data/analytics/data/' . $trackingId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return;
    }

    $metaFile = $dir . '/_meta.json';
    $meta = [];
    if (is_file($metaFile)) {
        $decoded = json_decode((string) file_get_contents($metaFile), true);
        if (is_array($decoded)) {
            $meta = $decoded;
        }
    }

    $meta['last_event_at'] = max((int) ($meta['last_event_at'] ?? 0), $timestamp);
    if ($capped) {
        if (!isset($meta['capped_dates']) || !is_array($meta['capped_dates'])) {
            $meta['capped_dates'] = [];
        }
        $meta['capped_dates'][date('Y-m-d', $timestamp)] = true;
    }

    file_put_contents($metaFile, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/**
 * Load and aggregate analytics for a tracking ID and date range.
 */
function birkly_analytics_get_report(string $trackingId, string $startDate, string $endDate): array
{
    $empty = [
        'page_views' => 0,
        'unique_visitors' => 0,
        'sessions' => 0,
        'bounce_rate' => 0.0,
        'pages_per_visitor' => 0.0,
        'event_counts' => [
            'page_view' => 0,
            'form_submit' => 0,
            'outbound_click' => 0,
            'plugin_event' => 0,
            'page_exit' => 0,
        ],
        'top_pages' => [],
        'entrance_pages' => [],
        'exit_pages' => [],
        'referrers' => [],
        'traffic_sources' => [],
        'campaigns' => [],
        'devices' => [],
        'browsers' => [],
        'operating_systems' => [],
        'countries' => [],
        'outbound_targets' => [],
        'forms' => [],
        'plugin_events' => [],
        'daily_stats' => [],
    ];

    if ($trackingId === '' || $trackingId === 'all' || $trackingId === '*') {
        $combined = birkly_analytics_get_combined_report($startDate, $endDate);
        unset($combined['tracking_ids'], $combined['domains']);

        return array_merge($empty, $combined);
    }

    return array_merge($empty, birkly_analytics_aggregate_events(
        birkly_analytics_load_events($trackingId, $startDate, $endDate)
    ));
}
