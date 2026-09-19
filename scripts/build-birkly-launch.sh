#!/usr/bin/env bash
# Cross-compile Birkly Launch into the CMS root (release zip root). Optional — release build skips if go is missing.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/tools/birkly-launch"

if [[ ! -f "$SRC/main.go" ]]; then
  echo "[build-birkly-launch] FAIL: missing $SRC/main.go" >&2
  exit 1
fi

if ! command -v go >/dev/null 2>&1; then
  echo "[build-birkly-launch] SKIP: go not installed" >&2
  exit 0
fi

cd "$SRC"
LDFLAGS="-s -w"

echo "[build-birkly-launch] windows/amd64 → Birkly Launch.exe"
GOOS=windows GOARCH=amd64 CGO_ENABLED=0 go build -ldflags="$LDFLAGS" -o "$ROOT/Birkly Launch.exe" .

echo "[build-birkly-launch] darwin/arm64 → birkly-launch-mac"
GOOS=darwin GOARCH=arm64 CGO_ENABLED=0 go build -ldflags="$LDFLAGS" -o "$ROOT/birkly-launch-mac" .

echo "[build-birkly-launch] darwin/amd64 → birkly-launch-mac-intel"
GOOS=darwin GOARCH=amd64 CGO_ENABLED=0 go build -ldflags="$LDFLAGS" -o "$ROOT/birkly-launch-mac-intel" .

echo "[build-birkly-launch] linux/amd64 → birkly-launch-linux"
GOOS=linux GOARCH=amd64 CGO_ENABLED=0 go build -ldflags="$LDFLAGS" -o "$ROOT/birkly-launch-linux" .

APP="$ROOT/Birkly Launch.app"
MACOS_BIN="$APP/Contents/MacOS/birkly-launch"
rm -rf "$APP"
mkdir -p "$(dirname "$MACOS_BIN")"
cp "$SRC/macos/Info.plist" "$APP/Contents/Info.plist"

if command -v lipo >/dev/null 2>&1 && [[ -f "$ROOT/birkly-launch-mac" && -f "$ROOT/birkly-launch-mac-intel" ]]; then
  echo "[build-birkly-launch] lipo universal → Birkly Launch.app"
  lipo -create -output "$MACOS_BIN" "$ROOT/birkly-launch-mac" "$ROOT/birkly-launch-mac-intel"
else
  echo "[build-birkly-launch] Birkly Launch.app (arm64 binary in bundle)"
  cp "$ROOT/birkly-launch-mac" "$MACOS_BIN"
fi
chmod 755 "$MACOS_BIN"

echo "[build-birkly-launch] OK"
