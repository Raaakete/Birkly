#!/usr/bin/env bash
# M2-T5 / M5-T1: Auth boundary regression probes — delegates to fixture-driven PHP test.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "[auth-probe] Starting auth boundary probes (M5 fixture-driven)"
php "$ROOT/tests/auth_boundary_test.php"
echo "[auth-probe] RESULT: PASS"
