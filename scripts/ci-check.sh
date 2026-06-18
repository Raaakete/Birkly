#!/usr/bin/env bash
# CI entry point — run on every PR and before autonomous merge (M1-T9).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "[ci-check] Birkly continuous integration"

if ! command -v php >/dev/null 2>&1; then
  echo "[ci-check] FAIL: php not found"
  exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
  echo "[ci-check] FAIL: curl not found (required for auth-probe)"
  exit 1
fi

chmod +x "$ROOT/scripts/agent-smoke-test.sh" "$ROOT/scripts/auth-probe.sh" 2>/dev/null || true
"$ROOT/scripts/agent-smoke-test.sh"

echo "[ci-check] RESULT: PASS"
exit 0
