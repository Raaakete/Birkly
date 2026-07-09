#!/usr/bin/env bash
# Studio end-to-end smoke (P25-M17) — compile + SSR without raw template tags.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "[studio-smoke] Studio compile + SSR smoke"

php "$ROOT/scripts/studio-smoke.php"
echo "[studio-smoke] RESULT: PASS"
exit 0
