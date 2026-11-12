#!/usr/bin/env bash
# Copy the Birkly-Docs help manifest into the CMS repo.
#
# Product articles are not copied. Help fetches them from docs.birkly.io
# when a topic is opened (P104).
#
# Usage:
#   ./scripts/sync-birkly-docs.sh
#   BIRKLY_DOCS_PATH=/path/to/Birkly-Docs ./scripts/sync-birkly-docs.sh
#
# Output:
#   docs/help-manifest.json

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DOCS_PATH="${BIRKLY_DOCS_PATH:-$ROOT/../Birkly-Docs}"
MANIFEST_SRC="$DOCS_PATH/help-manifest.json"
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

cp "$MANIFEST_SRC" "$LIVE_MANIFEST_OUT"
echo "[sync-birkly-docs] DONE — manifest mirror: $LIVE_MANIFEST_OUT"
echo "[sync-birkly-docs] Articles stay on the docs origin. This script does not copy them."
