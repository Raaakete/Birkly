#!/usr/bin/env bash
# Clear quarantine and start Birkly Launch (handles Intel Mac when .app is Apple Silicon–only).
set -euo pipefail
DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$DIR"

xattr -cr "Birkly Launch.app" birkly-launch-mac-intel . 2>/dev/null || true

ARCH="$(uname -m)"
INTEL_BIN="$DIR/birkly-launch-mac-intel"
APP_BIN="$DIR/Birkly Launch.app/Contents/MacOS/birkly-launch"

launch_intel_loose() {
  if [[ ! -f "$INTEL_BIN" ]]; then
    echo "Intel Mac: Birkly Launch.app needs Apple Silicon, and birkly-launch-mac-intel is missing."
    echo "Use ./start-server.sh or download the latest Birkly zip."
    exit 1
  fi
  chmod +x "$INTEL_BIN" 2>/dev/null || true
  echo "Starting Birkly Launch (Intel)… Leave this window open. Press Ctrl+C to stop."
  exec "$INTEL_BIN"
}

if [[ "$ARCH" == "x86_64" ]]; then
  if [[ -f "$APP_BIN" ]] && file "$APP_BIN" 2>/dev/null | grep -q 'x86_64'; then
    open "Birkly Launch.app"
    exit 0
  fi
  launch_intel_loose
fi

if [[ ! -d "Birkly Launch.app" ]]; then
  echo "Birkly Launch.app not found in $DIR"
  exit 1
fi

if [[ -f "$APP_BIN" ]] && ! file "$APP_BIN" 2>/dev/null | grep -q "$ARCH"; then
  echo "Birkly Launch.app binary does not match this Mac ($ARCH)."
  if [[ "$ARCH" == "x86_64" ]]; then
    launch_intel_loose
  fi
  exit 1
fi

open "Birkly Launch.app"
