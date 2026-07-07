#!/usr/bin/env bash
# P16-M4 — Cron-friendly production smoke wrapper (Birkly CMS).
#
# Usage:
#   ./scripts/scheduled-production-smoke.sh [cms-base-url] [marketplace-url]
#
# Env:
#   BIRKLY_SMOKE_BASE_URL, BIRKLY_MARKETPLACE_URL, BIRKLY_SMOKE_SKIP_MARKETPLACE
#   BIRKLY_SMOKE_LOG_DIR — default logs/
#
# Exit codes: 0 = pass, 1 = smoke fail, 2 = script error
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

OUTPUT=""
EXIT_CODE=0
if OUTPUT="$("$SMOKE_SCRIPT" "$BASE" "$MARKETPLACE" 2>&1)"; then
  RESULT="pass"
else
  EXIT_CODE=$?
  RESULT="fail"
fi

{
  echo "[$STAMP] scheduled-production-smoke result=$RESULT exit=$EXIT_CODE base=$BASE"
  echo "$OUTPUT"
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
];
file_put_contents($argv[6], json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$STAMP" "$RESULT" "$EXIT_CODE" "$BASE" "$MARKETPLACE" "$META_FILE"

echo "[$STAMP] production smoke: $RESULT (logged to $LOG_FILE)"
exit "$EXIT_CODE"
