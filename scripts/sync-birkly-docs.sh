#!/usr/bin/env bash
# Sync markdown help topics from Birkly-Docs into CMS offline bundle (P27-M12, P50-M2).
#
# Run before a CMS release when Birkly-Docs help-manifest.json or topic markdown changes.
#
# Usage:
#   ./scripts/sync-birkly-docs.sh
#   BIRKLY_DOCS_PATH=/path/to/Birkly-Docs ./scripts/sync-birkly-docs.sh
#
# Outputs:
#   admin/docs/snapshots/*.md
#   admin/docs/help-offline-manifest.json  (offline bundle + ai_topic preserved)
#   docs/help-manifest.json                (live manifest mirror from Birkly-Docs)

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DOCS_PATH="${BIRKLY_DOCS_PATH:-$ROOT/../Birkly-Docs}"
SNAP_DIR="$ROOT/admin/docs/snapshots"
MANIFEST_SRC="$DOCS_PATH/help-manifest.json"
MANIFEST_OUT="$ROOT/admin/docs/help-offline-manifest.json"
LIVE_MANIFEST_OUT="$ROOT/docs/help-manifest.json"

if [[ ! -d "$DOCS_PATH" ]]; then
  echo "[sync-birkly-docs] FAIL: Birkly-Docs not found at $DOCS_PATH" >&2
  echo "Set BIRKLY_DOCS_PATH to the Birkly-Docs repo root." >&2
  exit 1
fi

if [[ ! -f "$MANIFEST_SRC" ]]; then
  echo "[sync-birkly-docs] FAIL: missing $MANIFEST_SRC" >&2
  exit 1
fi

mkdir -p "$SNAP_DIR"

php "$ROOT/scripts/sync-birkly-docs-manifest.php" "$DOCS_PATH" "$SNAP_DIR" "$MANIFEST_OUT" "$LIVE_MANIFEST_OUT"

echo "[sync-birkly-docs] DONE — snapshots in $SNAP_DIR"
echo "[sync-birkly-docs] offline manifest: $MANIFEST_OUT"
echo "[sync-birkly-docs] live manifest mirror: $LIVE_MANIFEST_OUT"
