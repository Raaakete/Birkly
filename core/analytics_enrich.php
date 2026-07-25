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
    $eventType = (string) ($input['event_type'] ?? 'pageview');
    if (!in_array($eventType, ['pageview', 'page_exit'], true)) {
        $eventType = 'pageview';
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
        'ip' => hash('sha256', $ip . $trackingId),
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
    ];
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
    $sessions = [];

    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }

        $eventType = (string) ($event['event_type'] ?? 'pageview');
        if ($eventType === 'page_exit') {
            $path = (string) ($event['path'] ?? '/');
            $exitCounts[$path] = ($exitCounts[$path] ?? 0) + 1;
            continue;
        }

        $pageViews++;
        $ipHash = (string) ($event['ip'] ?? '');
        if ($ipHash !== '') {
            $uniqueVisitors[$ipHash] = true;
        }

        $path = (string) ($event['path'] ?? '/');
        $pageCounts[$path] = ($pageCounts[$path] ?? 0) + 1;

        $referrerHost = (string) ($event['referrer_host'] ?? '');
        if ($referrerHost === '' && !empty($event['referrer'])) {
            $referrerHost = (string) (parse_url((string) $event['referrer'], PHP_URL_HOST) ?? '');
        }
        if ($referrerHost !== '' && $referrerHost !== ($event['domain'] ?? '')) {
            $referrerCounts[$referrerHost] = ($referrerCounts[$referrerHost] ?? 0) + 1;
            $trafficSources['referral:' . $referrerHost] = ($trafficSources['referral:' . $referrerHost] ?? 0) + 1;
        }

        $utmSource = (string) ($event['utm_source'] ?? '');
        if ($utmSource !== '') {
            $label = 'campaign:' . $utmSource;
            if (!empty($event['utm_medium'])) {
                $label .= '/' . $event['utm_medium'];
            }
            $trafficSources[$label] = ($trafficSources[$label] ?? 0) + 1;
        } elseif ($referrerHost === '') {
            $trafficSources['direct'] = ($trafficSources['direct'] ?? 0) + 1;
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
        if ($ipHash !== '') {
            $dailyStats[$date]['visitors'][$ipHash] = true;
        }
    }

    foreach ($sessions as $session) {
        $first = (string) ($session['first_path'] ?? '/');
        $last = (string) ($session['last_path'] ?? '/');
        $entranceCounts[$first] = ($entranceCounts[$first] ?? 0) + 1;
        $exitCounts[$last] = ($exitCounts[$last] ?? 0) + 1;
    }

    $formattedDailyStats = [];
    foreach ($dailyStats as $date => $stats) {
        $formattedDailyStats[] = [
            'date' => $date,
            'views' => $stats['views'],
            'visitors' => count($stats['visitors']),
        ];
    }

    return [
        'page_views' => $pageViews,
        'unique_visitors' => count($uniqueVisitors),
        'top_pages' => birkly_analytics_rank_counts($pageCounts, 'path', 'views', 10),
        'entrance_pages' => birkly_analytics_rank_counts($entranceCounts, 'path', 'entrances', 10),
        'exit_pages' => birkly_analytics_rank_counts($exitCounts, 'path', 'exits', 10),
        'referrers' => birkly_analytics_rank_counts($referrerCounts, 'domain', 'visits', 10),
        'traffic_sources' => birkly_analytics_rank_counts($trafficSources, 'source', 'visits', 10),
        'devices' => birkly_analytics_rank_counts($deviceCounts, 'device_type', 'visits', 10),
        'browsers' => birkly_analytics_rank_counts($browserCounts, 'browser', 'visits', 10),
        'operating_systems' => birkly_analytics_rank_counts($osCounts, 'os', 'visits', 10),
        'countries' => birkly_analytics_rank_counts($countryCounts, 'country', 'visits', 10),
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
 * Load and aggregate analytics for a tracking ID and date range.
 */
function birkly_analytics_get_report(string $trackingId, string $startDate, string $endDate): array
{
    $analyticsDir = dirname(__DIR__) . '/data/analytics';
    $dataDir = $analyticsDir . '/data/' . $trackingId;

    $empty = [
        'page_views' => 0,
        'unique_visitors' => 0,
        'top_pages' => [],
        'entrance_pages' => [],
        'exit_pages' => [],
        'referrers' => [],
        'traffic_sources' => [],
        'devices' => [],
        'browsers' => [],
        'operating_systems' => [],
        'countries' => [],
        'daily_stats' => [],
    ];

    if (!is_dir($dataDir)) {
        return $empty;
    }

    $start = strtotime($startDate);
    $end = strtotime($endDate . ' 23:59:59');
    if ($start === false || $end === false) {
        return $empty;
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

    return birkly_analytics_aggregate_events($allEvents);
}
