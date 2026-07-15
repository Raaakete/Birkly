#!/usr/bin/env bash
# Browser smoke for reference demos (optional — requires Playwright).
# Usage: ./scripts/reference-browser-smoke.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE_URL="${BIRKLY_BASE_URL:-http://127.0.0.1:8080}"

if ! command -v node >/dev/null 2>&1; then
  echo "[reference-browser-smoke] SKIP: node not available" >&2
  exit 0
fi

if [[ ! -d "$ROOT/node_modules/playwright" ]]; then
  echo "[reference-browser-smoke] Installing playwright (first run)..."
  (cd "$ROOT" && npm install --no-save playwright@1.49.1 2>/dev/null || true)
  if [[ -d "$ROOT/node_modules/playwright" ]]; then
    (cd "$ROOT" && npx playwright install chromium 2>/dev/null || true)
  fi
fi

if [[ ! -d "$ROOT/node_modules/playwright" ]]; then
  echo "[reference-browser-smoke] SKIP: playwright not installed" >&2
  exit 0
fi

export BIRKLY_BASE_URL="$BASE_URL"
FAILED=0

run_case() {
  local project="$1"
  local filter="$2"
  echo "[reference-browser-smoke] Preparing $project..."
  "$ROOT/scripts/prepare-reference-demo.sh" "$project" >/dev/null
  if ! node "$ROOT/tests/browser/reference-smoke.mjs" "$filter"; then
    FAILED=1
  fi
}

run_case blog blog
run_case coffee coffee
run_case yoga yoga
run_case social social
run_case influencer influencer

exit "$FAILED"
