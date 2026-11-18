<?php
/**
 * SQLite index for analytics events (Path B — P108).
 *
 * JSON daily files remain the raw append log; this DB powers consistent reporting.
 */

declare(strict_types=1);

require_once __DIR__ . '/analytics_config.php';
require_once __DIR__ . '/analytics_enrich.php';

function birkly_analytics_db_path(): string
{
    $override = getenv('BIRKLY_ANALYTICS_DB_PATH');
    if (is_string($override) && $override !== '') {
        return $override;
    }

    return birkly_analytics_dir() . '/analytics.db';
}

function birkly_analytics_db_connect(): ?PDO
{
    $path = birkly_analytics_db_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return null;
    }

    try {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA synchronous=NORMAL');
        birkly_analytics_db_migrate($pdo);

        return $pdo;
    } catch (Throwable $e) {
        return null;
    }
}

function birkly_analytics_db_migrate(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS analytics_meta (
            key TEXT PRIMARY KEY NOT NULL,
            value TEXT NOT NULL
        )'
    );
    $pdo->exec(
        "INSERT OR IGNORE INTO analytics_meta (key, value) VALUES ('schema_version', '1')"
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS analytics_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tracking_id TEXT NOT NULL,
            event_day TEXT NOT NULL,
            ts INTEGER NOT NULL,
            event_type TEXT NOT NULL,
            path TEXT NOT NULL,
            traffic_class TEXT NOT NULL,
            consent_state TEXT NOT NULL,
            session_id TEXT NOT NULL DEFAULT "",
            visitor_token_hash TEXT NOT NULL DEFAULT "",
            ip_hash TEXT NOT NULL DEFAULT "",
            quality_json TEXT NOT NULL DEFAULT "{}",
            event_json TEXT NOT NULL
        )'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_analytics_events_scope
         ON analytics_events (tracking_id, event_day, ts)'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_analytics_events_session
         ON analytics_events (tracking_id, session_id)'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS analytics_daily_rollup (
            tracking_id TEXT NOT NULL,
            event_day TEXT NOT NULL,
            page_views INTEGER NOT NULL DEFAULT 0,
            sessions INTEGER NOT NULL DEFAULT 0,
            visitors INTEGER NOT NULL DEFAULT 0,
            bot_page_views INTEGER NOT NULL DEFAULT 0,
            suspected_page_views INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY (tracking_id, event_day)
        )'
    );
}

/**
 * Persist one enriched event row (best-effort; failures do not block JSON write).
 *
 * @param array<string, mixed> $event
 */
function birkly_analytics_db_insert_event(string $trackingId, array $event): bool
{
    $pdo = birkly_analytics_db_connect();
    if ($pdo === null) {
        return false;
    }

    $ts = (int) ($event['timestamp'] ?? time());
    $eventDay = date('Y-m-d', $ts);
    $eventType = (string) ($event['event_type'] ?? 'page_view');
    $path = (string) ($event['path'] ?? '/');
    $trafficClass = birkly_analytics_event_traffic_class($event);
    $consentState = (string) ($event['consent_state'] ?? 'not_required');
    $sessionId = (string) ($event['session_id'] ?? '');
    $visitorHash = (string) ($event['visitor_token_hash'] ?? '');
    $ipHash = (string) ($event['ip_hash'] ?? $event['ip'] ?? '');
    $quality = $event['quality'] ?? [];
    if (!is_array($quality)) {
        $quality = [];
    }

    $json = json_encode($event, JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    $qualityJson = json_encode($quality, JSON_UNESCAPED_SLASHES);
    if ($qualityJson === false) {
        $qualityJson = '{}';
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO analytics_events (
                tracking_id, event_day, ts, event_type, path, traffic_class, consent_state,
                session_id, visitor_token_hash, ip_hash, quality_json, event_json
            ) VALUES (
                :tracking_id, :event_day, :ts, :event_type, :path, :traffic_class, :consent_state,
                :session_id, :visitor_token_hash, :ip_hash, :quality_json, :event_json
            )'
        );
        $stmt->execute([
            ':tracking_id' => $trackingId,
            ':event_day' => $eventDay,
            ':ts' => $ts,
            ':event_type' => $eventType,
            ':path' => $path,
            ':traffic_class' => $trafficClass,
            ':consent_state' => $consentState,
            ':session_id' => $sessionId,
            ':visitor_token_hash' => $visitorHash,
            ':ip_hash' => $ipHash,
            ':quality_json' => $qualityJson,
            ':event_json' => $json,
        ]);
        birkly_analytics_db_touch_rollup($pdo, $trackingId, $eventDay);
        $pdo->commit();

        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return false;
    }
}

function birkly_analytics_db_touch_rollup(PDO $pdo, string $trackingId, string $eventDay): void
{
    // Coarse counters; full rebuild script may reconcile later.
    $stmt = $pdo->prepare(
        'SELECT page_views, bot_page_views, suspected_page_views FROM analytics_daily_rollup
         WHERE tracking_id = :tid AND event_day = :day'
    );
    $stmt->execute([':tid' => $trackingId, ':day' => $eventDay]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $pv = (int) ($row['page_views'] ?? 0);
    $bot = (int) ($row['bot_page_views'] ?? 0);
    $sus = (int) ($row['suspected_page_views'] ?? 0);
    $pv++;

    $upsert = $pdo->prepare(
        'INSERT INTO analytics_daily_rollup (
            tracking_id, event_day, page_views, sessions, visitors, bot_page_views, suspected_page_views
        ) VALUES (:tid, :day, :pv, 0, 0, :bot, :sus)
        ON CONFLICT(tracking_id, event_day) DO UPDATE SET
            page_views = :pv,
            bot_page_views = :bot,
            suspected_page_views = :sus'
    );
    $upsert->execute([
        ':tid' => $trackingId,
        ':day' => $eventDay,
        ':pv' => $pv,
        ':bot' => $bot,
        ':sus' => $sus,
    ]);
}