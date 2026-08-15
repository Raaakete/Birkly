#!/usr/bin/env bash
# Minify public runtime JS and emit pre-compressed .gz for gzip_static (P64 perf).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

MODE="${1:-build}"
SOURCE="${BIRKLY_CLIENT_SOURCE:-$ROOT/birkly-client.source.js}"
CLIENT="$ROOT/birkly-client.js"
GZIP="$ROOT/birkly-client.js.gz"
STAMP="$ROOT/birkly-client.js.sha256"

mkdir -p "$ROOT/.build"

minify_client() {
  if [[ ! -f "$SOURCE" ]]; then
    echo "[build-client-assets] missing source: $SOURCE" >&2
    exit 1
  fi
  if command -v npx >/dev/null 2>&1; then
    npx --yes terser "$SOURCE" -c -m --comments '/BIRKLY_TEMPLATE_ENGINE_INLINE/' -o "$CLIENT"
  else
    echo "[build-client-assets] npx not found — cannot minify" >&2
    exit 1
  fi
  gzip -9 -c -f "$CLIENT" > "$GZIP"
  sha256sum "$CLIENT" | awk '{print $1}' > "$STAMP"
}

if [[ "$MODE" == "--check" ]]; then
  if [[ ! -f "$STAMP" ]]; then
    echo "[build-client-assets] DRIFT: missing $STAMP — run ./scripts/build-client-assets.sh" >&2
    exit 1
  fi
  if [[ ! -f "$GZIP" ]]; then
    echo "[build-client-assets] DRIFT: missing birkly-client.js.gz" >&2
    exit 1
  fi
  CURRENT_HASH=$(sha256sum "$CLIENT" | awk '{print $1}')
  EXPECTED_HASH=$(tr -d '\n' < "$STAMP")
  if [[ "$CURRENT_HASH" != "$EXPECTED_HASH" ]]; then
    echo "[build-client-assets] DRIFT: birkly-client.js hash mismatch — run ./scripts/build-client-assets.sh" >&2
    exit 1
  fi
  GZIP_HASH=$(gzip -dc "$GZIP" | sha256sum | awk '{print $1}')
  if [[ "$GZIP_HASH" != "$CURRENT_HASH" ]]; then
    echo "[build-client-assets] DRIFT: birkly-client.js.gz does not match birkly-client.js" >&2
    exit 1
  fi
  echo "[build-client-assets] check OK"
  exit 0
fi

minify_client
echo "[build-client-assets] minified birkly-client.js ($(wc -c < "$CLIENT") bytes) + birkly-client.js.gz ($(wc -c < "$GZIP") bytes)"
