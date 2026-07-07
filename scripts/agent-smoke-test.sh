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

# Official plugins for dev/CI (P10-M1)
chmod +x "$ROOT/scripts/install-dev-plugins.sh" 2>/dev/null || true
if "$ROOT/scripts/install-dev-plugins.sh"; then
  report "OK: install-dev-plugins.sh"
else
  report "FAIL: install-dev-plugins.sh"
  FAIL=1
fi

# Key files exist
for f in server-router.php AGENTS.md docs/roadmap/ROADMAP.md docs/PROJECT_VALUES.md birkly-hydrate.js; do
  if [[ -f "$ROOT/$f" ]]; then
    report "OK: $f"
  else
    report "FAIL: missing $f"
    FAIL=1
  fi
done

# PHP syntax on critical API files (plugin paths only when present)
CORE_SYNTAX=(
  api/content.php
  api/media.php
  api/admin_media.php
  api/public.php
  api/page_bundle.php
  api/page_snapshot.php
  api/health.php
  api/automation.php
  api/updates.php
  core/public_read.php
  core/page_bundle_runner.php
  core/public_bootstrap.php
  core/version.php
  core/plugin_availability.php
  admin/template_parser.php
)
OPTIONAL_SYNTAX=(
  plugins/automation/cli/process_queue.php
)

syntax_check() {
  local rel="$1"
  if php -l "$ROOT/$rel" >/dev/null 2>&1; then
    report "OK: syntax $rel"
  else
    report "FAIL: syntax $rel"
    FAIL=1
  fi
}

for f in "${CORE_SYNTAX[@]}"; do
  syntax_check "$f"
done

for f in "${OPTIONAL_SYNTAX[@]}"; do
  if [[ -f "$ROOT/$f" ]]; then
    syntax_check "$f"
  else
    report "SKIP: syntax $f (plugin not installed)"
  fi
done

# M5 regression harness (template parser + auth boundaries)
if php "$ROOT/tests/run.php"; then
  report "OK: tests/run.php"
else
  report "FAIL: tests/run.php"
  FAIL=1
fi

# P2-M6: dev page snapshots available for hydrate smoke
if php "$ROOT/scripts/generate-page-snapshots.php" >/dev/null 2>&1; then
  if [[ -f "$ROOT/cache/page-snapshots/index.json" ]]; then
    report "OK: page snapshots generated"
  else
    report "FAIL: page snapshots missing index.json"
    FAIL=1
  fi
else
  report "FAIL: generate-page-snapshots.php"
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
