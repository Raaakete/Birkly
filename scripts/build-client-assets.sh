#!/usr/bin/env bash
set -uo pipefail

SRC=/app/birkly-client.source.js
OUT=/app/birkly-client.js

if [ ! -f "$SRC" ]; then
  echo "[build-client-assets] no source file, skipping"
  exit 0
fi

# Minification is optional. Birkly ships without a build toolchain, so npx is
# normally absent — that is expected, not an error.
if command -v npx >/dev/null 2>&1; then
  if npx --yes terser "$SRC" -c -m -o "$OUT" 2>/dev/null; then
    echo "[build-client-assets] minified: $(stat -c%s "$OUT") bytes"
  else
    echo "[build-client-assets] terser failed — shipping unminified"
    cp "$SRC" "$OUT"
  fi
else
  echo "[build-client-assets] npx not present — shipping unminified (gzip still applies)"
  cp "$SRC" "$OUT"
fi

# Pre-compressed sibling for nginx gzip_static. gzip is in the base image.
if command -v gzip >/dev/null 2>&1; then
  gzip -9 -k -f "$OUT" && echo "[build-client-assets] wrote $OUT.gz ($(stat -c%s "$OUT.gz") bytes)"
fi

exit 0
