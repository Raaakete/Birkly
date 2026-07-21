#!/usr/bin/env bash
# Admin content smoke test (P29-M12) — sidebar tabs + slug helpers.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "[admin-content-smoke] starting"

FAIL=0
report() { echo "[admin-content-smoke] $*"; }

if ! command -v php >/dev/null 2>&1; then
  report "FAIL: php not found"
  exit 1
fi

for test in \
  tests/entry_editor_sidebar_test.php \
  tests/collection_editor_sidebar_test.php \
  tests/entry_slug_test.php \
  tests/breadcrumb_truncate_test.php \
  tests/entry_field_validation_test.php \
  tests/dashboard_widget_api_test.php \
  tests/dashboard_widgets_test.php; do
  if php "$ROOT/$test"; then
    report "OK: $test"
  else
    report "FAIL: $test"
    FAIL=1
  fi
done

if [[ $FAIL -eq 0 ]]; then
  report "RESULT: PASS"
  exit 0
fi

report "RESULT: FAIL"
exit 1
