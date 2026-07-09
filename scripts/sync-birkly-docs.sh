#!/usr/bin/env bash
# Sync markdown help topics from sibling Birkly-Docs into admin offline bundle (P27-M12).
#
# Usage:
#   ./scripts/sync-birkly-docs.sh
#   BIRKLY_DOCS_PATH=/path/to/Birkly-Docs ./scripts/sync-birkly-docs.sh
#
# Copies topic markdown to admin/docs/snapshots/ and regenerates
# admin/docs/help-offline-manifest.json from Birkly-Docs/help-manifest.json.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DOCS_PATH="${BIRKLY_DOCS_PATH:-$ROOT/../Birkly-Docs}"
SNAP_DIR="$ROOT/admin/docs/snapshots"
MANIFEST_SRC="$DOCS_PATH/help-manifest.json"
MANIFEST_OUT="$ROOT/admin/docs/help-offline-manifest.json"

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

php "$ROOT/scripts/sync-birkly-docs-manifest.php" "$DOCS_PATH" "$SNAP_DIR" "$MANIFEST_OUT"

echo "[sync-birkly-docs] DONE — snapshots in $SNAP_DIR"
echo "[sync-birkly-docs] manifest: $MANIFEST_OUT"
