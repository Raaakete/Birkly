#!/usr/bin/env bash
# Sync the shared template engine into birkly-client.js (P51-M5).
#
# SOURCE OF TRUTH: birkly-template-engine.js (repo root).
# birkly-client.js embeds a byte-for-byte copy of that engine between the
# markers below so a single production <script src="/birkly-client.js"> keeps
# working with zero HTML changes. The engine, the SSR CLI bridge, and the
# birkly-render.js re-export therefore all share ONE implementation = parity.
#
# Usage:
#   scripts/sync-template-engine-inline.sh          # write: refresh the inlined copy
#   scripts/sync-template-engine-inline.sh --check   # verify: exit 1 on drift (CI)
#
# No third-party deps, no bundler — plain awk/diff.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENGINE="$ROOT/birkly-template-engine.js"
CLIENT="$ROOT/birkly-client.js"
BEGIN='/* BIRKLY_TEMPLATE_ENGINE_INLINE_BEGIN */'
END='/* BIRKLY_TEMPLATE_ENGINE_INLINE_END */'

CHECK=0
if [[ "${1:-}" == "--check" ]]; then
  CHECK=1
fi

for f in "$ENGINE" "$CLIENT"; do
  if [[ ! -f "$f" ]]; then
    echo "[sync-template-engine] missing file: $f" >&2
    exit 1
  fi
done

if ! grep -qF "$BEGIN" "$CLIENT" || ! grep -qF "$END" "$CLIENT"; then
  echo "[sync-template-engine] markers not found in birkly-client.js" >&2
  echo "  expected: $BEGIN … $END" >&2
  exit 1
fi

# Extract the current inlined block (content strictly between the markers).
extract_block() {
  awk -v b="$BEGIN" -v e="$END" '
    index($0, b) { inb = 1; next }
    index($0, e) { inb = 0; next }
    inb { print }
  ' "$CLIENT"
}

if [[ "$CHECK" -eq 1 ]]; then
  if diff -u <(cat "$ENGINE") <(extract_block) >/tmp/birkly-engine-inline.diff 2>&1; then
    echo "[sync-template-engine] OK: inlined engine matches birkly-template-engine.js"
    exit 0
  fi
  echo "[sync-template-engine] DRIFT: birkly-client.js inlined engine differs from source" >&2
  echo "  run: bash scripts/sync-template-engine-inline.sh" >&2
  sed -n '1,40p' /tmp/birkly-engine-inline.diff >&2
  exit 1
fi

# Write mode: rebuild birkly-client.js, replacing the between-marker content
# with the current engine file verbatim.
TMP="$(mktemp)"
awk -v b="$BEGIN" -v e="$END" -v engine="$ENGINE" '
  state == 0 {
    print
    if (index($0, b)) {
      while ((getline line < engine) > 0) print line
      close(engine)
      state = 1
    }
    next
  }
  state == 1 {
    if (index($0, e)) { print; state = 2 }
    next
  }
  { print }
' "$CLIENT" > "$TMP"

if ! grep -qF "$END" "$TMP"; then
  echo "[sync-template-engine] END marker lost during rewrite — aborting" >&2
  rm -f "$TMP"
  exit 1
fi

mv "$TMP" "$CLIENT"
echo "[sync-template-engine] synced birkly-template-engine.js -> birkly-client.js inlined block"
