#!/usr/bin/env bash
# Sync platform Birkly into a client site Birkly-0.1 embed (P2-M7-T1).
#
# Usage:
#   ./scripts/sync-client-embed.sh /path/to/kekaba scripts/fixtures/kekaba-embed-excludes.txt
#   ./scripts/sync-client-embed.sh /path/to/Birkly-Website
#   BIRKLY_SRC=/custom/birkly ./scripts/sync-client-embed.sh /path/to/site
#
# Optional second arg: path to extra rsync --exclude-from file (one pattern per line).
#
# Plugins: official plugin packages are NOT copied into client embeds. The platform
# ships plugins/README.md (+ .gitkeep) only; install extensions after sync via the
# marketplace (Admin → Plugins) or ./scripts/install-kekaba-demo-plugins.sh for kekaba
# commerce/events demos. See docs/client-sites/cms-vendoring.md and
# scripts/cms-release-manifest.json (embed_sync_excludes).

set -euo pipefail

SITE_REPO="${1:-}"
EXTRA_EXCLUDES="${2:-}"

if [[ -z "$SITE_REPO" ]]; then
  echo "Usage: $0 <site-repo-path> [exclude-from-file]" >&2
  exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BIRKLY_SRC="${BIRKLY_SRC:-$ROOT}"
DEST="$(cd "$SITE_REPO" && pwd)/Birkly-0.1"

if [[ ! -d "$BIRKLY_SRC/admin" || ! -d "$BIRKLY_SRC/api" ]]; then
  echo "BIRKLY_SRC does not look like Birkly platform: $BIRKLY_SRC" >&2
  exit 1
fi

if [[ ! -d "$DEST" ]]; then
  echo "Creating embed directory: $DEST" >&2
  mkdir -p "$DEST"
fi

COMMON_EXCLUDES=(
  --exclude 'content/'
  --exclude 'users/'
  --exclude 'media/'
  --exclude 'settings/'
  --exclude 'cache/'
  --exclude 'logs/'
  --exclude 'temp/'
  --exclude 'backups/'
  --exclude 'data/'
  --exclude '.env'
  --exclude 'config.php'
  --exclude '.git/'
  --exclude '.cursor/'
  --exclude 'tests/'
  --exclude 'AGENTS.md'
  --exclude 'docs/roadmap/'
  --exclude 'docs/agent-ops/'
  --exclude 'docs/reviews/'
)

RSYNC_ARGS=(-a --delete "${COMMON_EXCLUDES[@]}")

if [[ -n "$EXTRA_EXCLUDES" && -f "$EXTRA_EXCLUDES" ]]; then
  RSYNC_ARGS+=(--exclude-from "$EXTRA_EXCLUDES")
fi

echo "[sync-client-embed] $BIRKLY_SRC -> $DEST"
rsync "${RSYNC_ARGS[@]}" "$BIRKLY_SRC/" "$DEST/"

# Rsync excludes do not remove destination-only paths under excluded trees (--delete skips them).
if [[ -n "$EXTRA_EXCLUDES" && -f "$EXTRA_EXCLUDES" ]] && grep -qE '^plugins(/|\*\*)' "$EXTRA_EXCLUDES"; then
  mkdir -p "$DEST/plugins"
  if [[ -d "$DEST/plugins" ]]; then
    find "$DEST/plugins" -mindepth 1 -maxdepth 1 \
      ! -name 'README.md' ! -name '.gitkeep' -exec rm -rf {} +
  fi
  for f in README.md .gitkeep; do
    if [[ -f "$BIRKLY_SRC/plugins/$f" ]]; then
      cp -a "$BIRKLY_SRC/plugins/$f" "$DEST/plugins/$f"
    fi
  done
  echo "[sync-client-embed] pruned plugin packages from embed (kept README.md / .gitkeep)"
fi

# Verify critical client-stack files
for f in birkly-client.js birkly-hydrate.js api/page_bundle.php api/page_snapshot.php; do
  if [[ -f "$DEST/$f" ]]; then
    echo "[sync-client-embed] OK: $f"
  else
    echo "[sync-client-embed] FAIL: missing $f" >&2
    exit 1
  fi
done

if [[ -f "$DEST/birkly-client-modules/birkly-bundle.js" ]]; then
  echo "[sync-client-embed] OK: birkly-client-modules/birkly-bundle.js"
else
  echo "[sync-client-embed] FAIL: missing birkly-client-modules/birkly-bundle.js" >&2
  exit 1
fi

echo "[sync-client-embed] DONE"
