#!/usr/bin/env bash
# Admin polish smoke test (P27-M15) — dashboard paths, media URL helper, security policy.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "[admin-polish-smoke] starting"

FAIL=0
report() { echo "[admin-polish-smoke] $*"; }

if ! command -v php >/dev/null 2>&1; then
  report "FAIL: php not found"
  exit 1
fi

for test in tests/admin_dashboard_paths_test.php tests/security_policy_2fa_test.php; do
  if php "$ROOT/$test"; then
    report "OK: $test"
  else
    report "FAIL: $test"
    FAIL=1
  fi
done

if php "$ROOT/scripts/admin-polish-smoke.php"; then
  report "OK: admin-polish-smoke.php"
else
  report "FAIL: admin-polish-smoke.php"
  FAIL=1
fi

if [[ -f "$ROOT/scripts/sync-birkly-docs.sh" ]]; then
  report "OK: sync-birkly-docs.sh present"
else
  report "FAIL: sync-birkly-docs.sh missing"
  FAIL=1
fi

if [[ -f "$ROOT/core/security_policy.php" ]]; then
  report "OK: core/security_policy.php"
else
  report "FAIL: core/security_policy.php missing"
  FAIL=1
fi

if [[ $FAIL -eq 0 ]]; then
  report "RESULT: PASS"
  exit 0
fi

report "RESULT: FAIL"
exit 1
