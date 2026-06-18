#!/usr/bin/env bash
# M2-T5: Auth boundary regression probes — unauthenticated admin endpoints must return 401/403.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

PORT="${AUTH_PROBE_PORT:-18080}"
BASE="http://127.0.0.1:${PORT}"
SERVER_PID=""
FAIL=0

report() { echo "[auth-probe] $*"; }

cleanup() {
  if [[ -n "$SERVER_PID" ]] && kill -0 "$SERVER_PID" 2>/dev/null; then
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
  fi
}
trap cleanup EXIT

start_server() {
  php -S "127.0.0.1:${PORT}" -t "$ROOT" "$ROOT/server-router.php" >/dev/null 2>&1 &
  SERVER_PID=$!
  for _ in $(seq 1 30); do
    if curl -s -o /dev/null -w "%{http_code}" "${BASE}/api/public.php?action=list_collections" | grep -qE '^[0-9]+$'; then
      return 0
    fi
    sleep 0.2
  done
  report "FAIL: CMS server did not start on port ${PORT}"
  exit 1
}

# Expect HTTP status in {401,403,429} for protected admin endpoints without session
probe_denied() {
  local label="$1"
  local url="$2"
  local code
  code=$(curl -s -o /dev/null -w "%{http_code}" "$url")
  if [[ "$code" == "401" || "$code" == "403" ]]; then
    report "OK: $label returned $code"
  else
    report "FAIL: $label returned $code (expected 401 or 403)"
    FAIL=1
  fi
}

report "Starting auth boundary probes (M2)"
start_server

probe_denied "content.php list_collections" \
  "${BASE}/api/content.php?action=list_collections"
probe_denied "content.php get_entry" \
  "${BASE}/api/content.php?action=get_entry&collection=test&id=1"
probe_denied "content.php list_entries" \
  "${BASE}/api/content.php?action=list_entries&collection=test"
probe_denied "legacy media.php" \
  "${BASE}/api/media.php?action=list"
probe_denied "admin_media.php list" \
  "${BASE}/api/admin_media.php?action=list"
probe_denied "admin_media.php upload" \
  "${BASE}/api/admin_media.php?action=upload"

# Public API should remain reachable (200) for read-only collection list
code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE}/api/public.php?action=list_collections")
if [[ "$code" == "200" ]]; then
  report "OK: public.php list_collections returned 200"
else
  report "FAIL: public.php list_collections returned $code (expected 200)"
  FAIL=1
fi

if [[ "$FAIL" -ne 0 ]]; then
  report "RESULT: FAIL"
  exit 1
fi

report "RESULT: PASS"
exit 0
