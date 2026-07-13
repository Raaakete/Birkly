#!/usr/bin/env bash
# migrate-kekaba-live-path.sh — Remove baked HTML markers; ensure live birkly-client.js path (P28-M9).
#
# Usage:
#   ./scripts/migrate-kekaba-live-path.sh [kekaba-root]
#
# Default kekaba root: ../kekaba (sibling of Birkly repo).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
KEKABA="${1:-$(dirname "$ROOT")/kekaba}"
TRANSFORM="$ROOT/scripts/migrate-kekaba-live-path-transform.php"

if [[ ! -d "$KEKABA" ]]; then
  echo "[migrate-kekaba] ERROR: kekaba repo not found at $KEKABA" >&2
  exit 1
fi

echo "[migrate-kekaba] Migrating $KEKABA to live client path..."

for file in "$KEKABA"/*.html; do
  [[ -f "$file" ]] || continue
  if grep -qE 'birkly-baked|birkly-hydrate\.js' "$file" 2>/dev/null; then
    php "$TRANSFORM" "$file"
    echo "  migrated $(basename "$file")"
  fi
done

if [[ -x "$KEKABA/scripts/sync-birkly-client.sh" ]]; then
  echo "[migrate-kekaba] Syncing Birkly-0.1 embed..."
  (cd "$KEKABA" && ./scripts/sync-birkly-client.sh) || echo "[migrate-kekaba] WARN: sync-birkly-client.sh failed (continuing)"
fi

echo "[migrate-kekaba] OK"
