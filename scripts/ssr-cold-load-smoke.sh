#!/usr/bin/env bash
# P58-M1: SSR cold-load smoke — assert MISS then HIT on consecutive requests.
#
# Usage:
#   ./scripts/ssr-cold-load-smoke.sh [base_url] [path]
#
# Examples:
#   # Built-in PHP server (from repo root):
#   php -S localhost:8080 -t . server-router.php &
#   ./scripts/ssr-cold-load-smoke.sh http://localhost:8080 /
#
#   # Project HTML under /project/:
#   ./scripts/ssr-cold-load-smoke.sh http://localhost:8080 /project/index.html
#
# Requires SSR enabled (render_mode server|auto, Birkly hosting, public_site_mode=project).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

BASE_URL="${1:-http://localhost:8080}"
REQUEST_PATH="${2:-/}"

URL="${BASE_URL%/}${REQUEST_PATH}"

# Clear SSR cache so the first request is a true cold MISS.
if command -v php >/dev/null 2>&1; then
  php -r "require '$ROOT/core/project_ssr.php'; birkly_project_ssr_invalidate_all();" >/dev/null 2>&1 || true
fi

fetch_headers() {
  curl -sS -o /dev/null -D - -w 'time_starttransfer:%{time_starttransfer}\n' "$URL" | tr -d '\r'
}

echo "[ssr-cold-load-smoke] URL: $URL"

HEADERS1="$(fetch_headers)"
CACHE1="$(echo "$HEADERS1" | awk 'tolower($0) ~ /^x-birkly-ssr-cache:/ {print $2}')"
RENDER1="$(echo "$HEADERS1" | awk 'tolower($0) ~ /^x-birkly-render:/ {print $2}')"
TTFB1="$(echo "$HEADERS1" | awk -F: '/^time_starttransfer:/ {print $2}')"

HEADERS2="$(fetch_headers)"
CACHE2="$(echo "$HEADERS2" | awk 'tolower($0) ~ /^x-birkly-ssr-cache:/ {print $2}')"
TTFB2="$(echo "$HEADERS2" | awk -F: '/^time_starttransfer:/ {print $2}')"

echo "Request 1: X-Birkly-Render=${RENDER1:-missing} X-Birkly-SSR-Cache=${CACHE1:-missing} TTFB=${TTFB1:-?}s"
echo "Request 2: X-Birkly-SSR-Cache=${CACHE2:-missing} TTFB=${TTFB2:-?}s"

FAIL=0
if [[ "${RENDER1:-}" != "server" ]]; then
  echo "FAIL: first response is not server SSR (X-Birkly-Render=${RENDER1:-missing})" >&2
  FAIL=1
fi
if [[ "${CACHE1:-}" != "MISS" ]]; then
  echo "FAIL: expected first X-Birkly-SSR-Cache=MISS, got ${CACHE1:-missing}" >&2
  FAIL=1
fi
if [[ "${CACHE2:-}" != "HIT" ]]; then
  echo "FAIL: expected second X-Birkly-SSR-Cache=HIT, got ${CACHE2:-missing}" >&2
  FAIL=1
fi

if [[ "$FAIL" -eq 0 ]]; then
  echo "PASS: SSR cache MISS → HIT"
  exit 0
fi
exit 1
