#!/usr/bin/env bash
# P28-M16 — Validate Birkly-Marketplace seed catalog against offer manifest schema.
#
# Usage:
#   ./scripts/audit-marketplace-catalog.sh
#
# Env:
#   BIRKLY_MARKETPLACE_REPO — default sibling Birkly-Marketplace checkout
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MARKETPLACE_ROOT="${BIRKLY_MARKETPLACE_REPO:-$(dirname "$ROOT")/Birkly-Marketplace}"

report() { echo "[catalog-audit] $*"; }

if [[ ! -d "$MARKETPLACE_ROOT/catalog" ]]; then
  report "FAIL: marketplace catalog not found at $MARKETPLACE_ROOT/catalog"
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  report "FAIL: php not found"
  exit 1
fi

report "Marketplace repo: $MARKETPLACE_ROOT"

php -d display_errors=1 "$ROOT/scripts/audit-marketplace-catalog.php" "$MARKETPLACE_ROOT"
exit $?
