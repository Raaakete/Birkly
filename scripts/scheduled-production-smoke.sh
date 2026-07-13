#!/usr/bin/env bash
# P16-M4, P28-M13 — Cron-friendly production smoke + J-HEALTH journey subset.
#
# Usage:
#   ./scripts/scheduled-production-smoke.sh [cms-base-url] [marketplace-url]
#
# Env:
#   BIRKLY_SMOKE_BASE_URL, BIRKLY_MARKETPLACE_URL, BIRKLY_SMOKE_SKIP_MARKETPLACE
#   BIRKLY_SMOKE_LOG_DIR — default logs/
#   BIRKLY_JOURNEY_BASE_URL — override CMS base for journey-runner (defaults to smoke base)
#
# Exit codes: 0 = pass, 1 = smoke or journey fail, 2 = script error
# Writes: logs/production-smoke.log, logs/last-production-smoke.json
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LOG_DIR="${BIRKLY_SMOKE_LOG_DIR:-$ROOT/logs}"
mkdir -p "$LOG_DIR"

STAMP="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
LOG_FILE="$LOG_DIR/production-smoke.log"
META_FILE="$LOG_DIR/last-production-smoke.json"

SMOKE_SCRIPT="$ROOT/scripts/production-smoke.sh"
if [[ ! -f "$SMOKE_SCRIPT" ]]; then
  echo "[$STAMP] FAIL: production-smoke.sh not found" >>"$LOG_FILE"
  exit 2
fi
chmod +x "$SMOKE_SCRIPT" 2>/dev/null || true

BASE="${1:-${BIRKLY_SMOKE_BASE_URL:-http://127.0.0.1:8080}}"
MARKETPLACE="${2:-${BIRKLY_MARKETPLACE_URL:-https://marketplace.birkly.io}}"
BASE="${BASE%/}"
JOURNEY_BASE="${BIRKLY_JOURNEY_BASE_URL:-$BASE}"
JOURNEY_BASE="${JOURNEY_BASE%/}"

OUTPUT=""
EXIT_CODE=0
if OUTPUT="$("$SMOKE_SCRIPT" "$BASE" "$MARKETPLACE" 2>&1)"; then
  SMOKE_RESULT="pass"
else
  EXIT_CODE=$?
  SMOKE_RESULT="fail"
fi

# J-HEALTH journey subset (P28-M13)
JOURNEY_OUTPUT=""
JOURNEY_EXIT=0
JOURNEY_RESULT="pass"
JOURNEY_METHOD="inline"
JOURNEY_RUNNER="$ROOT/scripts/journey-runner.php"

if [[ -f "$JOURNEY_RUNNER" ]]; then
  JOURNEY_METHOD="journey-runner"
  if JOURNEY_OUTPUT="$(php "$JOURNEY_RUNNER" --journey J-HEALTH --base "$JOURNEY_BASE" 2>&1)"; then
    JOURNEY_RESULT="pass"
  else
    JOURNEY_EXIT=$?
    JOURNEY_RESULT="fail"
  fi
else
  JOURNEY_METHOD="inline"
  INLINE_CHECKS=()
  INLINE_FAIL=0

  if ! command -v curl >/dev/null 2>&1; then
    INLINE_CHECKS+=("FAIL: curl not found")
    INLINE_FAIL=1
  else
    health_tmp=$(mktemp)
    health_code=$(curl -sS -o "$health_tmp" -w "%{http_code}" "$JOURNEY_BASE/api/health.php" 2>/dev/null || echo "000")
    if [[ "$health_code" == "200" ]] || [[ "$health_code" == "503" ]]; then
      if php -r '
        $data = json_decode(file_get_contents($argv[1]), true);
        if (!is_array($data)) { fwrite(STDERR, "invalid health JSON\n"); exit(1); }
        foreach (["settings", "content", "cache", "logs"] as $dir) {
          if (empty($data["writable"][$dir]["writable"])) {
            fwrite(STDERR, "not writable: $dir\n");
            exit(1);
          }
        }
        exit(0);
      ' "$health_tmp" 2>/dev/null; then
        INLINE_CHECKS+=("OK: health writable dirs")
      else
        INLINE_CHECKS+=("FAIL: health writable dirs")
        INLINE_FAIL=1
      fi
    else
      INLINE_CHECKS+=("FAIL: health HTTP $health_code")
      INLINE_FAIL=1
    fi
    rm -f "$health_tmp"

    if [[ "${BIRKLY_SMOKE_SKIP_MARKETPLACE:-}" == "1" ]]; then
      INLINE_CHECKS+=("SKIP: marketplace (BIRKLY_SMOKE_SKIP_MARKETPLACE=1)")
    else
      mp_code=$(curl -sS -o /dev/null -w "%{http_code}" --connect-timeout 10 "$MARKETPLACE/health" 2>/dev/null || echo "000")
      if [[ "$mp_code" == "200" ]] || [[ "$mp_code" == "503" ]]; then
        INLINE_CHECKS+=("OK: marketplace health ($MARKETPLACE/health)")
      else
        INLINE_CHECKS+=("FAIL: marketplace health HTTP $mp_code")
        INLINE_FAIL=1
      fi
    fi

    admin_code=$(curl -sS -o /dev/null -w "%{http_code}" "$JOURNEY_BASE/admin/index.php" 2>/dev/null || echo "000")
    if [[ "$admin_code" == "200" ]] || [[ "$admin_code" == "302" ]]; then
      INLINE_CHECKS+=("OK: admin login page ($admin_code)")
    else
      INLINE_CHECKS+=("FAIL: admin login page HTTP $admin_code")
      INLINE_FAIL=1
    fi
  fi

  JOURNEY_OUTPUT="$(printf '%s\n' "${INLINE_CHECKS[@]}")"
  if [[ "$INLINE_FAIL" -ne 0 ]]; then
    JOURNEY_RESULT="fail"
    JOURNEY_EXIT=1
  fi
fi

RESULT="$SMOKE_RESULT"
FINAL_EXIT="$EXIT_CODE"
if [[ "$JOURNEY_RESULT" == "fail" ]]; then
  RESULT="fail"
  FINAL_EXIT=1
fi

ALERT_DISPATCHED="false"
ALERT_REASON=""
if [[ "$RESULT" == "fail" ]]; then
  ALERT_JSON=$(php -r '
    $root = $argv[1];
    $base = $argv[2];
    $smokeResult = $argv[3];
    $journeyResult = $argv[4];
    require_once $root . "/core/health_alerts.php";

    $report = null;
    $healthUrl = rtrim($base, "/") . "/api/health.php";
    if (function_exists("curl_init")) {
        $ch = curl_init($healthUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body !== false && ($code === 200 || $code === 503)) {
            $decoded = json_decode((string) $body, true);
            if (is_array($decoded)) {
                $report = $decoded;
            }
        }
    }

    if (!is_array($report)) {
        $report = [
            "status" => "error",
            "service" => "birkly-cms",
            "time" => gmdate("c"),
            "checks" => [
                "scheduled_smoke" => ["status" => "error"],
                "journey_health" => ["status" => $journeyResult === "pass" ? "ok" : "error"],
            ],
        ];
    } elseif (($report["status"] ?? "ok") === "ok") {
        $report["status"] = "error";
        $report["checks"]["scheduled_smoke"] = ["status" => "error"];
        $report["checks"]["journey_health"] = ["status" => $journeyResult === "pass" ? "ok" : "error"];
    }

    $report["scheduled_smoke"] = [
        "smoke_result" => $smokeResult,
        "journey_result" => $journeyResult,
        "cms_base_url" => $base,
        "last_run" => gmdate("c"),
    ];

    $dispatch = birkly_health_dispatch_alerts($report);
    echo json_encode([
        "dispatched" => !empty($dispatch["dispatched"]),
        "reason" => $dispatch["reason"] ?? null,
        "email" => $dispatch["email"] ?? null,
        "webhook" => $dispatch["webhook"] ?? null,
    ], JSON_UNESCAPED_SLASHES);
  ' "$ROOT" "$BASE" "$SMOKE_RESULT" "$JOURNEY_RESULT" 2>/dev/null || echo '{"dispatched":false,"reason":"alert_script_error"}')

  ALERT_DISPATCHED=$(php -r 'echo json_decode(file_get_contents("php://stdin"), true)["dispatched"] ? "true" : "false";' <<<"$ALERT_JSON")
  ALERT_REASON=$(php -r '$d=json_decode(file_get_contents("php://stdin"),true); echo (string)($d["reason"]??"");' <<<"$ALERT_JSON")
fi

{
  echo "[$STAMP] scheduled-production-smoke result=$RESULT smoke=$SMOKE_RESULT journey=$JOURNEY_RESULT exit=$FINAL_EXIT base=$BASE"
  echo "$OUTPUT"
  echo "--- J-HEALTH ($JOURNEY_METHOD) ---"
  echo "$JOURNEY_OUTPUT"
  echo "---"
} >>"$LOG_FILE"

php -r '
$meta = [
    "last_run" => $argv[1],
    "result" => $argv[2],
    "exit_code" => (int) $argv[3],
    "cms_base_url" => $argv[4],
    "marketplace_url" => $argv[5],
    "service" => "birkly-cms",
    "smoke" => [
        "result" => $argv[6],
        "exit_code" => (int) $argv[7],
    ],
    "journey_health" => [
        "result" => $argv[8],
        "exit_code" => (int) $argv[9],
        "method" => $argv[10],
        "journey" => "J-HEALTH",
        "base_url" => $argv[11],
    ],
];
if ($argv[2] === "fail") {
    $meta["alert"] = [
        "dispatched" => $argv[12] === "true",
        "reason" => $argv[13] !== "" ? $argv[13] : null,
    ];
}
file_put_contents($argv[14], json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$STAMP" "$RESULT" "$FINAL_EXIT" "$BASE" "$MARKETPLACE" \
  "$SMOKE_RESULT" "$EXIT_CODE" "$JOURNEY_RESULT" "$JOURNEY_EXIT" "$JOURNEY_METHOD" "$JOURNEY_BASE" \
  "$ALERT_DISPATCHED" "$ALERT_REASON" "$META_FILE"

echo "[$STAMP] production smoke: $RESULT (smoke=$SMOKE_RESULT, journey=$JOURNEY_RESULT, logged to $LOG_FILE)"
exit "$FINAL_EXIT"
