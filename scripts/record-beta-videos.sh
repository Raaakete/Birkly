#!/usr/bin/env bash
# Record V-BETA-01…09 review videos (Playwright viewport capture, 1280×720).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

export BIRKLY_BASE_URL="${BIRKLY_BASE_URL:-http://127.0.0.1:8080}"

if ! curl -sf "$BIRKLY_BASE_URL/api/health.php" >/dev/null 2>&1; then
  echo "[record-beta-videos] Starting CMS server on 8080..."
  php -S 127.0.0.1:8080 -t . server-router.php >/dev/null 2>&1 &
  sleep 1
fi

if [[ ! -d node_modules/playwright ]]; then
  npm install --no-save playwright@1.49.1
  npx playwright install chromium
fi

FILTER="${1:-}"
node scripts/record-beta-videos.mjs "$FILTER"

echo "[record-beta-videos] Videos written to docs/reviews/artifacts/v-beta-*.mp4"
