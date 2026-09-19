#!/usr/bin/env bash
# Copy freshly built launcher artifacts into release/launchers/ for Go-less release builds.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DEST="$ROOT/release/launchers"

mkdir -p "$DEST"
for f in "Birkly Launch.exe" birkly-launch-mac birkly-launch-mac-intel birkly-launch-linux; do
  if [[ ! -f "$ROOT/$f" ]]; then
    echo "[sync-release-launchers] FAIL: missing $f — run build-birkly-launch.sh first" >&2
    exit 1
  fi
  cp "$ROOT/$f" "$DEST/$f"
done
rm -rf "$DEST/Birkly Launch.app"
cp -R "$ROOT/Birkly Launch.app" "$DEST/"
echo "[sync-release-launchers] OK"
