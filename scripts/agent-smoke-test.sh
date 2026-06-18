#!/usr/bin/env bash
# Agent smoke test — run at cloud session start (M1-T4).
# Exit 0 = READY, non-zero = FAIL (report P0 to human).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

FAIL=0
report() { echo "[smoke] $*"; }

report "Birkly agent smoke test"

# PHP available
if ! command -v php >/dev/null 2>&1; then
  report "FAIL: php not found"
  exit 1
fi
report "OK: php $(php -r 'echo PHP_VERSION;')"

# Key files exist
for f in server-router.php AGENTS.md docs/roadmap/ROADMAP.md docs/PROJECT_VALUES.md birkly-hydrate.js; do
  if [[ -f "$ROOT/$f" ]]; then
    report "OK: $f"
  else
    report "FAIL: missing $f"
    FAIL=1
  fi
done

# PHP syntax on critical API files
for f in api/content.php api/media.php api/admin_media.php api/public.php api/page_bundle.php api/page_snapshot.php core/public_read.php core/page_bundle_runner.php core/public_bootstrap.php admin/template_parser.php; do
  if php -l "$ROOT/$f" >/dev/null 2>&1; then
    report "OK: syntax $f"
  else
    report "FAIL: syntax $f"
    FAIL=1
  fi
done

# M5 regression harness (template parser + auth boundaries)
if php "$ROOT/tests/run.php"; then
  report "OK: tests/run.php"
else
  report "FAIL: tests/run.php"
  FAIL=1
fi

# Multi-repo siblings (cloud environment)
PARENT="$(dirname "$ROOT")"
for repo in Birkly-Website kekaba Birkly-Docs; do
  if [[ -d "$PARENT/$repo/.git" ]]; then
    report "OK: sibling repo $repo"
  else
    report "WARN: sibling repo $repo not found at $PARENT/$repo"
  fi
done

if [[ "$FAIL" -ne 0 ]]; then
  report "RESULT: FAIL"
  exit 1
fi

report "RESULT: READY"
exit 0
