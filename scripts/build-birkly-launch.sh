#!/usr/bin/env bash
# Cross-compile Birkly Launch into the CMS root (release zip root).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/tools/birkly-launch"
VENDOR="$ROOT/release/launchers"

if [[ ! -f "$SRC/main.go" ]]; then
  echo "[build-birkly-launch] FAIL: missing $SRC/main.go" >&2
  exit 1
fi

build_with_go() {
  cd "$SRC"
  local LDFLAGS="-s -w"

  echo "[build-birkly-launch] windows/amd64 → Birkly Launch.exe"
  GOOS=windows GOARCH=amd64 CGO_ENABLED=0 go build -ldflags="$LDFLAGS" -o "$ROOT/Birkly Launch.exe" .

  echo "[build-birkly-launch] darwin/arm64 → birkly-launch-mac"
  GOOS=darwin GOARCH=arm64 CGO_ENABLED=0 go build -ldflags="$LDFLAGS" -o "$ROOT/birkly-launch-mac" .

  echo "[build-birkly-launch] darwin/amd64 → birkly-launch-mac-intel"
  GOOS=darwin GOARCH=amd64 CGO_ENABLED=0 go build -ldflags="$LDFLAGS" -o "$ROOT/birkly-launch-mac-intel" .

  echo "[build-birkly-launch] linux/amd64 → birkly-launch-linux"
  GOOS=linux GOARCH=amd64 CGO_ENABLED=0 go build -ldflags="$LDFLAGS" -o "$ROOT/birkly-launch-linux" .
}

assemble_mac_app() {
  local APP="$ROOT/Birkly Launch.app"
  local MACOS_DIR="$APP/Contents/MacOS"
  local MACOS_BIN="$MACOS_DIR/birkly-launch"
  local ARM_BIN="$MACOS_DIR/birkly-launch-arm64"
  local INTEL_BIN="$MACOS_DIR/birkly-launch-intel"

  if [[ ! -f "$ROOT/birkly-launch-mac" || ! -f "$ROOT/birkly-launch-mac-intel" ]]; then
    echo "[build-birkly-launch] FAIL: missing macOS launcher binaries" >&2
    exit 1
  fi

  rm -rf "$APP"
  mkdir -p "$MACOS_DIR"
  cp "$SRC/macos/Info.plist" "$APP/Contents/Info.plist"
  cp "$ROOT/birkly-launch-mac" "$ARM_BIN"
  cp "$ROOT/birkly-launch-mac-intel" "$INTEL_BIN"
  chmod 755 "$ARM_BIN" "$INTEL_BIN"

  cat > "$MACOS_BIN" << 'EOF'
#!/bin/bash
DIR="$(cd "$(dirname "$0")" && pwd)"
case "$(uname -m)" in
  arm64) exec "$DIR/birkly-launch-arm64" "$@" ;;
  x86_64) exec "$DIR/birkly-launch-intel" "$@" ;;
  *) exec "$DIR/birkly-launch-arm64" "$@" ;;
esac
EOF
  chmod 755 "$MACOS_BIN"

  if command -v codesign >/dev/null 2>&1; then
    codesign --force --deep --sign - "$APP" 2>/dev/null || true
  fi
}

copy_from_vendor() {
  echo "[build-birkly-launch] using vendored binaries from release/launchers/"
  for f in "Birkly Launch.exe" birkly-launch-mac birkly-launch-mac-intel birkly-launch-linux; do
    if [[ ! -f "$VENDOR/$f" ]]; then
      echo "[build-birkly-launch] FAIL: missing vendored $f" >&2
      return 1
    fi
    cp "$VENDOR/$f" "$ROOT/$f"
    chmod 755 "$ROOT/$f" 2>/dev/null || true
  done
  return 0
}

if command -v go >/dev/null 2>&1; then
  build_with_go
else
  copy_from_vendor
fi

assemble_mac_app

MACOS_BIN="$ROOT/Birkly Launch.app/Contents/MacOS/birkly-launch"
if [[ ! -f "$MACOS_BIN" ]]; then
  echo "[build-birkly-launch] FAIL: Birkly Launch.app not ready (no MacOS binary)" >&2
  exit 1
fi
chmod 755 "$MACOS_BIN" 2>/dev/null || true

echo "[build-birkly-launch] OK"
