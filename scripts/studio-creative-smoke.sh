#!/usr/bin/env bash
# Studio creative suite smoke (P26-M15) — animations, popups, funnel, Figma import.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "[studio-creative-smoke] Studio creative suite smoke"

php "$ROOT/scripts/studio-creative-smoke.php"
echo "[studio-creative-smoke] RESULT: PASS"
exit 0
