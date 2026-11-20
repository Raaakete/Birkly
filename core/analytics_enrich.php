<?php
/**
 * Analytics enrichment and aggregation helpers (zero external dependencies).
 */

require_once __DIR__ . '/analytics_ai.php';
require_once __DIR__ . '/analytics_attribution.php';

/**
 * Detect automated clients from a user-agent string.
 */
function birkly_analytics_is_bot_user_agent(string $userAgent): bool
{
    $ua = strtolower(trim($userAgent));
    if ($ua === '') {
        return false;
    }

    $patterns = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'facebookexternalhit',
        'googlebot', 'google-inspectiontool', 'bingbot', 'yandexbot', 'baiduspider',
        'duckduckbot', 'applebot', 'petalbot', 'bytespider', 'semrush', 'ahrefs',
        'mj12bot', 'dotbot', 'rogerbot', 'screaming frog', 'linkedinbot',
        'twitterbot', 'slackbot', 'discordbot', 'telegrambot', 'whatsapp',
        'headlesschrome', 'phantomjs', 'puppeteer', 'playwright', 'lighthouse',
        'wget/', 'curl/', 'python-requests', 'go-http-client', 'libwww-perl',
        'gptbot', 'chatgpt-user', 'oai-searchbot', 'claudebot', 'claude-web',
        'anthropic-ai', 'perplexitybot', 'perplexity-user', 'google-extended',
        'ccbot', 'petal', 'cohere-ai', 'meta-externalagent', 'applebot-extended',
        'scrapy', 'httpclient', 'java/', 'okhttp', 'node-fetch',
    ];

    foreach ($patterns as $needle) {
        if (str_contains($ua, $needle)) {
            return true;
        }
    }

    return (bool) preg_match('/\b(bot|spider|crawler)\b/i', $userAgent);
}

/**
 * Classify an event as human, confirmed bot, or suspected automation.
 *
 * @param array<string, mixed> $event
 */
function birkly_analytics_traffic_class(array $event): string
{
    if (($event['device_type'] ?? '') === 'bot') {
        return 'bot';
    }

    $userAgent = (string) ($event['user_agent'] ?? '');
    if ($userAgent !== '' && birkly_analytics_is_bot_user_agent($userAgent)) {
        return 'bot';
    }

    $consent = strtolower(trim((string) ($event['consent_state'] ?? '')));
    if ($consent === 'anonymous') {
        return 'limited';
    }

    $browser = (string) ($event['browser'] ?? '');
    $os = (string) ($event['os'] ?? '');
    $deviceType = (string) ($event['device_type'] ?? '');
    $sessionId = trim((string) ($event['session_id'] ?? ''));

    if ($userAgent === '' || strlen(trim($userAgent)) < 16) {
        if ($sessionId !== '') {
            return 'limited';
        }

        return 'suspected';
    }

    if ($browser === 'Unknown' && $os === 'Unknown' && ($deviceType === 'unknown' || $deviceType === 'desktop')) {
        $screenW = (int) ($event['screen_width'] ?? 0);
        $screenH = (int) ($event['screen_height'] ?? 0);
        if ($screenW <= 0 && $screenH <= 0) {
            if ($sessionId !== '') {
                return 'limited';
            }

            return 'suspected';
        }
    }

    return 'human';
}

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

    if (birkly_analytics_is_bot_user_agent($userAgent)) {
        $label = 'Bot';
        if (preg_match('/googlebot|google-inspectiontool/i', $userAgent)) {
            $label = 'Googlebot';
        } elseif (preg_match('/bingbot/i', $userAgent)) {
            $label = 'Bingbot';
        } elseif (preg_match('/gptbot|chatgpt-user|oai-searchbot/i', $userAgent)) {
            $label = 'OpenAI bot';
        } elseif (preg_match('/claudebot|claude-web|anthropic/i', $userAgent)) {
            $label = 'Anthropic bot';
        } elseif (preg_match('/perplexitybot|perplexity-user/i', $userAgent)) {
            $label = 'Perplexity bot';
        }

        return ['device_type' => 'bot', 'browser' => $label, 'os' => 'Bot'];
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
    $input = birkly_analytics_enrich_input_attribution($input);

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
        'not_found',
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

    $consentState = trim((string) ($input['consent_state'] ?? ''));
    if ($consentState === '') {
        $rawConsent = strtolower(trim((string) ($input['consent'] ?? '')));
        if ($rawConsent === 'granted' || $rawConsent === 'accepted' || $rawConsent === 'true' || $rawConsent === '1') {
            $consentState = 'granted';
        } elseif ($rawConsent === 'anonymous') {
            $consentState = 'anonymous';
        } else {
            $consentState = 'not_required';
        }
    }

    $visitorTokenHash = '';
    $rawVisitorToken = trim((string) ($input['visitor_token'] ?? ''));
    if ($rawVisitorToken !== '' && $trackingId !== '') {
        $visitorTokenHash = hash('sha256', $trackingId . ':' . $rawVisitorToken);
    }

    $eventData = is_array($input['event_data'] ?? null) ? $input['event_data'] : [];
    if ($eventType === 'outbound_click' && ($eventData['href'] ?? '') === '' && $referrer !== '') {
        $eventData['href'] = $referrer;
    }

    $event = [
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
        'event_data' => $eventData,
        'schema_version' => 2,
        'consent_state' => $consentState,
        'visitor_token_hash' => $visitorTokenHash,
    ];

    $ai = birkly_analytics_detect_ai_attribution(
        $referrer,
        $referrerHost,
        trim((string) ($input['utm_source'] ?? '')),
        trim((string) ($input['utm_medium'] ?? '')),
        $userAgent
    );
    if (!$ai['is_ai']) {
        $ai = birkly_analytics_detect_ai_from_path($path);
    }
    if ($ai['is_ai']) {
        $event['ai_source'] = $ai['ai_source'];
        $event['ai_channel'] = $ai['ai_channel'];
        $event['is_ai'] = true;
    }

    $event['quality'] = birkly_analytics_event_quality($event);
    $event['traffic_class'] = birkly_analytics_traffic_class($event);

    return $event;
}

/**
 * @param array<string, mixed> $event
 * @return array{has_ua:bool,has_session:bool,has_visitor_token:bool,has_viewport:bool}
 */
function birkly_analytics_event_quality(array $event): array
{
    $ua = trim((string) ($event['user_agent'] ?? ''));

    return [
        'has_ua' => $ua !== '' && strlen($ua) >= 16,
        'has_session' => trim((string) ($event['session_id'] ?? '')) !== '',
        'has_visitor_token' => trim((string) ($event['visitor_token_hash'] ?? '')) !== '',
        'has_viewport' => ((int) ($event['viewport_width'] ?? 0)) > 0 || ((int) ($event['viewport_height'] ?? 0)) > 0,
    ];
}

/**
 * Stored traffic_class when present; otherwise classify at read time.
 *
 * @param array<string, mixed> $event
 */
function birkly_analytics_event_traffic_class(array $event): string
{
    $stored = strtolower(trim((string) ($event['traffic_class'] ?? '')));
    if (in_array($stored, ['human', 'bot', 'suspected', 'limited', 'unknown'], true)) {
        return $stored;
    }

    return birkly_analytics_traffic_class($event);
}

/**
 * Backfill missing schema v2 fields on stored JSON events.
 *
 * @param array<string, mixed> $event
 * @return array<string, mixed>
 */
function birkly_analytics_normalize_stored_event(array $event): array
{
    if (!isset($event['schema_version'])) {
        $event['schema_version'] = 1;
    }
    if (!isset($event['consent_state']) || !is_string($event['consent_state'])) {
        $event['consent_state'] = 'not_required';
    }
    if (!isset($event['traffic_class'])) {
        $event['traffic_class'] = birkly_analytics_traffic_class($event);
    }
    if (!isset($event['quality']) || !is_array($event['quality'])) {
        $event['quality'] = birkly_analytics_event_quality($event);
    }

    return $event;
}

/**
 * @param list<array<string, mixed>> $rawEvents
 * @param array<string, mixed> $report
 * @return array<string, mixed>
 */
function birkly_analytics_build_report_quality(array $rawEvents, array $report): array
{
    $total = count($rawEvents);
    $withUa = 0;
    $withSession = 0;
    $withToken = 0;
    $consentGranted = 0;
    $consentAnonymous = 0;
    $schemaV2 = 0;

    foreach ($rawEvents as $event) {
        if (!is_array($event)) {
            continue;
        }
        $event = birkly_analytics_normalize_stored_event($event);
        $quality = $event['quality'];
        if (!empty($quality['has_ua'])) {
            $withUa++;
        }
        if (!empty($quality['has_session'])) {
            $withSession++;
        }
        if (!empty($quality['has_visitor_token'])) {
            $withToken++;
        }
        $consent = (string) ($event['consent_state'] ?? '');
        if ($consent === 'granted') {
            $consentGranted++;
        } elseif ($consent === 'anonymous') {
            $consentAnonymous++;
        }
        if ((int) ($event['schema_version'] ?? 0) >= 2) {
            $schemaV2++;
        }
    }

    $den = max(1, $total);
    $sessionPct = $withSession / $den;
    $uaPct = $withUa / $den;

    $confidence = 'low';
    if ($total >= 10 && $sessionPct >= 0.35) {
        $confidence = 'medium';
    }
    if ($total >= 25 && $sessionPct >= 0.5 && $uaPct >= 0.4) {
        $confidence = 'high';
    }

    $visitors = (int) ($report['unique_visitors'] ?? 0);
    $sessions = (int) ($report['sessions'] ?? 0);
    $suppressPagesPerVisitor = $confidence === 'low' || $visitors < 2 || ($sessions > 0 && $visitors === 1 && $sessions >= 5);

    return [
        'confidence' => $confidence,
        'event_count' => $total,
        'indexed_event_count' => 0,
        'coverage' => [
            'has_ua_pct' => round(100 * $withUa / $den, 1),
            'has_session_pct' => round(100 * $withSession / $den, 1),
            'has_visitor_token_pct' => round(100 * $withToken / $den, 1),
            'consent_granted_pct' => round(100 * $consentGranted / $den, 1),
            'consent_anonymous_pct' => round(100 * $consentAnonymous / $den, 1),
            'schema_v2_pct' => round(100 * $schemaV2 / $den, 1),
        ],
        'suppress_pages_per_visitor' => $suppressPagesPerVisitor,
        'definitions' => [
            'session' => 'One continuous visit in a browser tab until the visitor closes it or clears site data.',
            'visitor' => 'One person we could count: a returning token after they accept analytics, otherwise at most once per session per day.',
            'page_view' => 'A page load or 404 hit your preset allows.',
            'traffic_tags' => 'Confirmed bots are dropped from headline totals. Privacy-limited hits are real visitors without full browser detail.',
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $rawEvents
 * @param array<string, mixed> $report
 * @return array<string, mixed>
 */
function birkly_analytics_finalize_report(array $rawEvents, array $report): array
{
    $quality = birkly_analytics_build_report_quality($rawEvents, $report);
    $report['quality'] = $quality;
    if (!empty($quality['suppress_pages_per_visitor'])) {
        $report['pages_per_visitor'] = null;
        $report['pages_per_visitor_suppressed'] = true;
    } else {
        $report['pages_per_visitor_suppressed'] = false;
    }

    return $report;
}

function birkly_analytics_visitor_key(array $event): string
{
    $visitorToken = trim((string) ($event['visitor_token_hash'] ?? ''));
    if ($visitorToken !== '') {
        return 'vt:' . $visitorToken;
    }

    $sessionId = trim((string) ($event['session_id'] ?? ''));
    if ($sessionId !== '') {
        $day = date('Y-m-d', (int) ($event['timestamp'] ?? time()));

        return 'sess:' . hash('sha256', $day . ':' . $sessionId);
    }

    $dayKey = (string) ($event['visitor_day_key'] ?? '');
    if ($dayKey !== '') {
        return 'vd:' . $dayKey;
    }

    $ipHash = (string) ($event['ip_hash'] ?? $event['ip'] ?? '');
    if ($ipHash !== '') {
        return 'ip:' . $ipHash;
    }

    return '';
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
        'not_found' => 0,
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
        $isPageView = $eventType === 'page_view' || $eventType === 'not_found';

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
                $href = (string) ($event['referrer'] ?? '');
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

        $timestamp = (int) ($event['timestamp'] ?? time());
        $date = date('Y-m-d', $timestamp);
        if (!isset($dailyStats[$date])) {
            $dailyStats[$date] = ['views' => 0, 'visitors' => [], 'interactions' => 0];
        }
        if (in_array($eventType, ['form_submit', 'outbound_click', 'plugin_event'], true)) {
            $dailyStats[$date]['interactions']++;
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
        $aiSource = trim((string) ($event['ai_source'] ?? ''));
        if ($aiSource !== '' && $utmCampaign === '' && $utmSource === '') {
            $aiCampaignLabel = 'AI · ' . $aiSource;
            $campaignCounts[$aiCampaignLabel] = ($campaignCounts[$aiCampaignLabel] ?? 0) + 1;
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

        if (!isset($dailyStats[$date]['interactions'])) {
            $dailyStats[$date]['interactions'] = 0;
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
            'interactions' => (int) ($stats['interactions'] ?? 0),
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
        'ai_sources' => birkly_analytics_summarize_ai_sources($events),
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
 * Pseudonymous label for admin session/visitor lists (never raw IP).
 */
function birkly_analytics_visitor_label(array $event): string
{
    $key = birkly_analytics_visitor_key($event);
    if ($key === '') {
        return 'visitor_unknown';
    }

    return 'vis_' . substr(hash('sha256', 'vl:' . $key), 0, 10);
}

/**
 * Derive acquisition source from the first event in a session.
 *
 * @param array<string, mixed> $event
 */
function birkly_analytics_session_source_label(array $event): string
{
    $aiSource = trim((string) ($event['ai_source'] ?? ''));
    if ($aiSource !== '') {
        $channel = trim((string) ($event['ai_channel'] ?? 'referral'));
        return 'ai:' . $aiSource . ($channel !== '' ? ('/' . $channel) : '');
    }

    $utmSource = trim((string) ($event['utm_source'] ?? ''));
    if ($utmSource !== '') {
        $medium = trim((string) ($event['utm_medium'] ?? ''));
        return $medium !== '' ? ('campaign:' . $utmSource . '/' . $medium) : ('campaign:' . $utmSource);
    }

    $referrerHost = (string) ($event['referrer_host'] ?? '');
    if ($referrerHost === '' && !empty($event['referrer'])) {
        $referrerHost = (string) (parse_url((string) $event['referrer'], PHP_URL_HOST) ?? '');
    }
    $domain = (string) ($event['domain'] ?? '');
    if ($referrerHost !== '' && $referrerHost !== $domain) {
        return 'referral:' . $referrerHost;
    }

    return 'direct';
}

/**
 * Human-readable trail step label.
 *
 * @param array<string, mixed> $event
 */
function birkly_analytics_trail_label(array $event): string
{
    $type = strtolower((string) ($event['event_type'] ?? 'page_view'));
    if ($type === 'pageview' || $type === '') {
        $type = 'page_view';
    }

    if ($type === 'outbound_click') {
        $href = '';
        if (is_array($event['event_data'] ?? null)) {
            $href = (string) ($event['event_data']['href'] ?? $event['event_data']['url'] ?? '');
        }
        if ($href === '') {
            $href = (string) ($event['referrer'] ?? '');
        }

        return $href !== '' ? ('Outbound → ' . $href) : 'Outbound click';
    }
    if ($type === 'form_submit') {
        $formId = '';
        if (is_array($event['event_data'] ?? null)) {
            $formId = (string) ($event['event_data']['form_id'] ?? '');
        }

        return $formId !== '' ? ('Form · ' . $formId) : 'Form submit';
    }
    if ($type === 'plugin_event') {
        $name = trim((string) ($event['event_name'] ?? ''));

        return $name !== '' ? ('Plugin · ' . $name) : 'Plugin event';
    }
    if ($type === 'not_found') {
        return '404 · ' . (string) ($event['path'] ?? '/');
    }
    if ($type === 'page_exit') {
        return 'Exit · ' . (string) ($event['path'] ?? '/');
    }

    return (string) ($event['path'] ?? '/');
}

/**
 * @param list<array<string, mixed>> $events
 * @return array{bot_page_views: int, human_page_views: int, bot_sessions: int}
 */
function birkly_analytics_summarize_bot_traffic(array $events): array
{
    $botPageViews = 0;
    $limitedPageViews = 0;
    $suspectedPageViews = 0;
    $humanPageViews = 0;
    $unknownDeviceViews = 0;
    $botSessions = [];
    $botAgents = [];
    $aiPageViews = 0;

    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }
        $type = strtolower((string) ($event['event_type'] ?? 'page_view'));
        if ($type === 'pageview' || $type === '') {
            $type = 'page_view';
        }
        if ($type !== 'page_view' && $type !== 'not_found') {
            continue;
        }

        if (($event['device_type'] ?? '') === 'unknown') {
            $unknownDeviceViews++;
        }

        if (!empty($event['is_ai']) || trim((string) ($event['ai_source'] ?? '')) !== '') {
            $aiPageViews++;
        }

        $class = birkly_analytics_event_traffic_class($event);
        if ($class === 'bot') {
            $botPageViews++;
            $sid = (string) ($event['session_id'] ?? '');
            if ($sid !== '') {
                $botSessions[$sid] = true;
            }
            $agent = (string) ($event['browser'] ?? 'Bot');
            if ($agent !== '') {
                $botAgents[$agent] = ($botAgents[$agent] ?? 0) + 1;
            }
        } elseif ($class === 'limited') {
            $limitedPageViews++;
        } elseif ($class === 'suspected') {
            $suspectedPageViews++;
        } else {
            $humanPageViews++;
        }
    }

    arsort($botAgents);

    return [
        'bot_page_views' => $botPageViews,
        'limited_page_views' => $limitedPageViews,
        'suspected_bot_page_views' => $suspectedPageViews,
        'human_page_views' => $humanPageViews,
        'unknown_device_views' => $unknownDeviceViews,
        'bot_sessions' => count($botSessions),
        'top_bot_agents' => birkly_analytics_rank_counts($botAgents, 'agent', 'views', 8),
        'ai_page_views' => $aiPageViews,
    ];
}

/**
 * Build session footprints, visitor rollups, and top paths for Advanced reporting.
 *
 * @param list<array<string, mixed>> $events
 * @return array<string, mixed>
 */
function birkly_analytics_build_journey_index(array $events, int $sessionLimit = 60, int $visitorLimit = 40, int $pathLimit = 12): array
{
    usort($events, static function ($a, $b): int {
        return ((int) ($a['timestamp'] ?? 0)) <=> ((int) ($b['timestamp'] ?? 0));
    });

    $sessions = [];
    $pathCounts = [];

    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }
        $sessionId = trim((string) ($event['session_id'] ?? ''));
        if ($sessionId === '') {
            continue;
        }

        $timestamp = (int) ($event['timestamp'] ?? 0);
        if (!isset($sessions[$sessionId])) {
            $sessions[$sessionId] = [
                'session_id' => $sessionId,
                'visitor_id' => birkly_analytics_visitor_label($event),
                'started_at' => $timestamp,
                'ended_at' => $timestamp,
                'landing_path' => '',
                'exit_path' => '',
                'device_type' => (string) ($event['device_type'] ?? 'unknown'),
                'browser' => (string) ($event['browser'] ?? ''),
                'os' => (string) ($event['os'] ?? ''),
                'country' => (string) ($event['country'] ?? ''),
                'location_source' => (string) ($event['location_source'] ?? ''),
                'is_bot' => birkly_analytics_event_traffic_class($event) === 'bot',
                'traffic_class' => birkly_analytics_traffic_class($event),
                'source' => birkly_analytics_session_source_label($event),
                'page_views' => 0,
                'trail' => [],
                'page_path_sequence' => [],
            ];
        }

        $session = &$sessions[$sessionId];
        $session['ended_at'] = max($session['ended_at'], $timestamp);
        if ($timestamp > 0 && ($session['started_at'] === 0 || $timestamp < $session['started_at'])) {
            $session['started_at'] = $timestamp;
        }

        $eventType = strtolower((string) ($event['event_type'] ?? 'page_view'));
        if ($eventType === 'pageview' || $eventType === '') {
            $eventType = 'page_view';
        }

        $trailStep = [
            'timestamp' => $timestamp,
            'type' => $eventType,
            'path' => (string) ($event['path'] ?? ''),
            'label' => birkly_analytics_trail_label($event),
        ];
        $session['trail'][] = $trailStep;

        if ($eventType === 'page_view' || $eventType === 'not_found') {
            $path = (string) ($event['path'] ?? '/');
            $session['page_views']++;
            if ($session['landing_path'] === '') {
                $session['landing_path'] = $path;
            }
            $session['exit_path'] = $path;
            $session['page_path_sequence'][] = $path;
        } elseif ($eventType === 'page_exit') {
            $session['exit_path'] = (string) ($event['path'] ?? $session['exit_path']);
        }
        unset($session);
    }

    foreach ($sessions as $session) {
        $seq = $session['page_path_sequence'] ?? [];
        $count = count($seq);
        for ($len = 2; $len <= 3; $len++) {
            if ($count < $len) {
                continue;
            }
            for ($i = 0; $i <= $count - $len; $i++) {
                $slice = array_slice($seq, $i, $len);
                $key = implode(' → ', $slice);
                $pathCounts[$key] = ($pathCounts[$key] ?? 0) + 1;
            }
        }
    }

    $sessionList = array_values($sessions);
    usort($sessionList, static function ($a, $b): int {
        return ((int) ($b['ended_at'] ?? 0)) <=> ((int) ($a['ended_at'] ?? 0));
    });
    $sessionList = array_slice($sessionList, 0, max(1, $sessionLimit));

    foreach ($sessionList as &$row) {
        unset($row['page_path_sequence']);
        if (isset($row['started_at']) && is_int($row['started_at']) && $row['started_at'] > 0) {
            $row['started_at'] = date('c', $row['started_at']);
        }
        if (isset($row['ended_at']) && is_int($row['ended_at']) && $row['ended_at'] > 0) {
            $row['ended_at'] = date('c', $row['ended_at']);
        }
        foreach ($row['trail'] as &$step) {
            if (isset($step['timestamp']) && is_int($step['timestamp']) && $step['timestamp'] > 0) {
                $step['timestamp'] = date('c', $step['timestamp']);
            }
        }
        unset($step);
    }
    unset($row);

    $visitors = [];
    foreach ($sessionList as $sessionRow) {
        $vid = (string) ($sessionRow['visitor_id'] ?? '');
        if ($vid === '') {
            continue;
        }
        if (!isset($visitors[$vid])) {
            $visitors[$vid] = [
                'visitor_id' => $vid,
                'sessions' => 0,
                'page_views' => 0,
                'last_seen' => '',
                'country' => (string) ($sessionRow['country'] ?? ''),
                'device_type' => (string) ($sessionRow['device_type'] ?? ''),
                'source' => (string) ($sessionRow['source'] ?? ''),
                'is_bot' => !empty($sessionRow['is_bot']),
            ];
        }
        $visitors[$vid]['sessions']++;
        $visitors[$vid]['page_views'] += (int) ($sessionRow['page_views'] ?? 0);
        $visitors[$vid]['last_seen'] = (string) ($sessionRow['ended_at'] ?? $visitors[$vid]['last_seen']);
        if (($visitors[$vid]['country'] ?? '') === '' && ($sessionRow['country'] ?? '') !== '') {
            $visitors[$vid]['country'] = (string) $sessionRow['country'];
        }
    }
    $visitorList = array_values($visitors);
    usort($visitorList, static function ($a, $b): int {
        return strcmp((string) ($b['last_seen'] ?? ''), (string) ($a['last_seen'] ?? ''));
    });
    $visitorList = array_slice($visitorList, 0, max(1, $visitorLimit));

    return [
        'sessions' => $sessionList,
        'visitors' => $visitorList,
        'paths' => birkly_analytics_rank_counts($pathCounts, 'path', 'sessions', $pathLimit),
    ];
}

/**
 * Load raw events for one or all enabled tracking ids in a date range.
 *
 * @return list<array<string, mixed>>
 */
function birkly_analytics_load_events_for_scope(string $trackingId, string $startDate, string $endDate): array
{
    require_once __DIR__ . '/analytics_config.php';

    $trackingId = trim($trackingId);
    if ($trackingId === '' || $trackingId === 'all' || $trackingId === '*') {
        $allEvents = [];
        foreach (birkly_analytics_list_enabled_tracking_ids() as $enabledId) {
            $allEvents = array_merge($allEvents, birkly_analytics_load_events($enabledId, $startDate, $endDate));
        }
        usort($allEvents, static function ($a, $b): int {
            return ((int) ($a['timestamp'] ?? 0)) <=> ((int) ($b['timestamp'] ?? 0));
        });

        return $allEvents;
    }

    return birkly_analytics_load_events($trackingId, $startDate, $endDate);
}

/**
 * @param list<array<string, mixed>> $events
 * @return list<array<string, mixed>>
 */
function birkly_analytics_exclude_bot_events(array $events, bool $includeBots = false): array
{
    if ($includeBots) {
        return array_values(array_filter($events, static function ($event): bool {
            return is_array($event);
        }));
    }

    return array_values(array_filter($events, static function ($event): bool {
        return is_array($event) && birkly_analytics_event_traffic_class($event) !== 'bot';
    }));
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
 * @param array{include_bots?: bool, include_journey?: bool} $options
 * @return array<string, mixed>
 */
function birkly_analytics_get_combined_report(string $startDate, string $endDate, array $options = []): array
{
    require_once __DIR__ . '/analytics_config.php';

    if (!function_exists('birkly_analytics_db_ensure_scope_index')) {
        $dbPath = __DIR__ . '/analytics_db.php';
        if (is_file($dbPath)) {
            require_once $dbPath;
        }
    }
    if (function_exists('birkly_analytics_db_ensure_scope_index')) {
        birkly_analytics_db_ensure_scope_index('all', $startDate, $endDate);
    }

    $trackingIds = birkly_analytics_list_enabled_tracking_ids();
    $rawEvents = birkly_analytics_load_events_for_scope('all', $startDate, $endDate);
    $events = !empty($options['include_bots']) ? $rawEvents : birkly_analytics_exclude_bot_events($rawEvents);

    $domains = [];
    foreach ($trackingIds as $trackingId) {
        $domainEvents = birkly_analytics_load_events($trackingId, $startDate, $endDate);
        if (empty($options['include_bots'])) {
            $domainEvents = birkly_analytics_exclude_bot_events($domainEvents);
        }
        $domains[] = [
            'id' => $trackingId,
            'domain' => $trackingId,
            'report' => birkly_analytics_aggregate_events($domainEvents),
        ];
    }

    $combined = birkly_analytics_aggregate_events($events);
    $combined['tracking_ids'] = $trackingIds;
    $combined['domains'] = $domains;
    $combined['bot_traffic'] = birkly_analytics_summarize_bot_traffic($rawEvents);

    if (!empty($options['include_journey'])) {
        $combined['journey'] = birkly_analytics_build_journey_index($events);
        $combined['top_paths'] = $combined['journey']['paths'] ?? [];
    }

    $combined = birkly_analytics_finalize_report($rawEvents, $combined);
    if (function_exists('birkly_analytics_db_count_events') && isset($combined['quality']) && is_array($combined['quality'])) {
        $combined['quality']['indexed_event_count'] = birkly_analytics_db_count_events('all', $startDate, $endDate);
    }

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

    if ($deleted > 0 && function_exists('birkly_analytics_db_connect')) {
        $dbPath = __DIR__ . '/analytics_db.php';
        if (is_file($dbPath)) {
            require_once $dbPath;
            $cutoffDay = date('Y-m-d', $cutoffTs);
            $pdo = birkly_analytics_db_connect();
            if ($pdo instanceof PDO) {
                try {
                    $pdo->prepare('DELETE FROM analytics_events WHERE event_day < :cutoff')
                        ->execute([':cutoff' => $cutoffDay]);
                    $pdo->prepare('DELETE FROM analytics_daily_rollup WHERE event_day < :cutoff')
                        ->execute([':cutoff' => $cutoffDay]);
                } catch (Throwable $e) {
                    // Best-effort — JSON retention is source of truth.
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
function birkly_analytics_get_report(string $trackingId, string $startDate, string $endDate, array $options = []): array
{
    if (!function_exists('birkly_analytics_db_ensure_scope_index')) {
        $dbPath = __DIR__ . '/analytics_db.php';
        if (is_file($dbPath)) {
            require_once $dbPath;
        }
    }
    if (function_exists('birkly_analytics_db_ensure_scope_index')) {
        birkly_analytics_db_ensure_scope_index($trackingId, $startDate, $endDate);
    }

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
            'not_found' => 0,
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
        'bot_traffic' => [
            'bot_page_views' => 0,
            'suspected_bot_page_views' => 0,
            'human_page_views' => 0,
            'unknown_device_views' => 0,
            'bot_sessions' => 0,
            'top_bot_agents' => [],
        ],
        'journey' => [
            'sessions' => [],
            'visitors' => [],
            'paths' => [],
        ],
        'top_paths' => [],
        'ai_sources' => [],
        'quality' => null,
        'pages_per_visitor_suppressed' => false,
    ];

    if ($trackingId === '' || $trackingId === 'all' || $trackingId === '*') {
        $combined = birkly_analytics_get_combined_report($startDate, $endDate, $options);
        unset($combined['tracking_ids'], $combined['domains']);

        return array_merge($empty, $combined);
    }

    $rawEvents = birkly_analytics_load_events($trackingId, $startDate, $endDate);
    $events = !empty($options['include_bots']) ? $rawEvents : birkly_analytics_exclude_bot_events($rawEvents);
    $report = birkly_analytics_aggregate_events($events);
    $report['bot_traffic'] = birkly_analytics_summarize_bot_traffic($rawEvents);

    if (!empty($options['include_journey'])) {
        $report['journey'] = birkly_analytics_build_journey_index($events);
        $report['top_paths'] = $report['journey']['paths'] ?? [];
    }

    $report = birkly_analytics_finalize_report($rawEvents, $report);
    if (function_exists('birkly_analytics_db_count_events') && isset($report['quality']) && is_array($report['quality'])) {
        $report['quality']['indexed_event_count'] = birkly_analytics_db_count_events($trackingId, $startDate, $endDate);
    }

    return array_merge($empty, $report);
}
