#!/usr/bin/env bash
# P13-M6 — kekaba staging smoke (site + CMS embed health).
#
# Runs kekaba site checks and Birkly production-smoke against the staging host.
#
# Usage:
#   ./scripts/staging-smoke-kekaba.sh
#   BIRKLY_STAGING_BASE_URL=https://service.kekaba.com ./scripts/staging-smoke-kekaba.sh
#
# Env:
#   BIRKLY_STAGING_BASE_URL   — site root (default https://service.kekaba.com)
#   KEKABA_REPO               — path to kekaba checkout (default: sibling of Birkly repo)
#   BIRKLY_MARKETPLACE_URL    — passed through to production-smoke.sh
#   BIRKLY_SMOKE_SKIP_MARKETPLACE=1 — skip marketplace probe (local/CI)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAGING="${BIRKLY_STAGING_BASE_URL:-https://service.kekaba.com}"
STAGING="${STAGING%/}"
CMS_BASE="${STAGING}/Birkly-0.1"
KEKABA_ROOT="${KEKABA_REPO:-$(dirname "$ROOT")/kekaba}"

report() { echo "[staging-smoke-kekaba] $*"; }

if [[ ! -x "$ROOT/scripts/production-smoke.sh" ]]; then
  report "FAIL: missing $ROOT/scripts/production-smoke.sh"
  exit 1
fi

if [[ ! -x "$KEKABA_ROOT/scripts/check-production.sh" ]]; then
  report "FAIL: missing $KEKABA_ROOT/scripts/check-production.sh"
  exit 1
fi

report "Staging site: $STAGING"
report "CMS embed: $CMS_BASE"
report "Kekaba repo: $KEKABA_ROOT"

report "==> kekaba site smoke"
"$KEKABA_ROOT/scripts/check-production.sh" "$STAGING"

report "==> CMS + marketplace smoke"
"$ROOT/scripts/production-smoke.sh" "$CMS_BASE"

report "RESULT: PASS"
