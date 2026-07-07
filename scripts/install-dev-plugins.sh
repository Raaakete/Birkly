#!/usr/bin/env bash
# Install official plugin packages for local dev / CI (P10-M1).
# Skips plugins that already exist; otherwise extracts from marketplace catalog zips.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MARKETPLACE="${BIRKLY_MARKETPLACE:-$(dirname "$ROOT")/Birkly-Marketplace}"
PKG_DIR="$MARKETPLACE/catalog/packages"
MANIFEST="$ROOT/scripts/cms-release-manifest.json"

declare -A SLUGS=(
  [commerce]=birkly-commerce
  [events]=birkly-events
  [automation]=birkly-automation
  [user_management]=birkly-user-management
)

if [[ -f "$MANIFEST" ]] && command -v php >/dev/null 2>&1; then
  while IFS=$'\t' read -r id slug; do
    [[ -n "$id" && -n "$slug" ]] && SLUGS["$id"]="$slug"
  done < <(MANIFEST="$MANIFEST" php -r '
    $m = json_decode(file_get_contents(getenv("MANIFEST")), true);
    foreach (($m["official_marketplace_slugs"] ?? []) as $id => $slug) {
        echo $id . "\t" . $slug . "\n";
    }
  ')
fi

OFFICIAL_IDS=(commerce events automation user_management)

find_marketplace_zip() {
  local id="$1"
  local slug="${SLUGS[$id]:-$id}"
  local candidate=""

  if [[ ! -d "$PKG_DIR" ]]; then
    return 1
  fi

  for pattern in "${slug}-"*.zip "birkly-${id}-"*.zip "${id}-"*.zip; do
    for candidate in "$PKG_DIR"/$pattern; do
      if [[ -f "$candidate" ]]; then
        echo "$candidate"
        return 0
      fi
    done
  done
  return 1
}

install_from_zip() {
  local id="$1"
  local zip="$2"
  local dest="$ROOT/plugins/$id"
  local tmp
  tmp="$(mktemp -d)"

  unzip -q "$zip" -d "$tmp"
  mkdir -p "$ROOT/plugins"

  if [[ -f "$tmp/plugin.php" ]]; then
    mv "$tmp" "$dest"
  elif [[ -d "$tmp/$id" && -f "$tmp/$id/plugin.php" ]]; then
    mv "$tmp/$id" "$dest"
    rm -rf "$tmp"
  else
    local sub
    sub="$(find "$tmp" -mindepth 1 -maxdepth 1 -type d | head -1)"
    if [[ -n "$sub" && -f "$sub/plugin.php" ]]; then
      mv "$sub" "$dest"
      rm -rf "$tmp"
    else
      rm -rf "$tmp"
      echo "[install-dev-plugins] FAIL: could not locate plugin root in $zip" >&2
      return 1
    fi
  fi

  echo "[install-dev-plugins] installed $id from $(basename "$zip")"
}

for id in "${OFFICIAL_IDS[@]}"; do
  dest="$ROOT/plugins/$id"
  if [[ -d "$dest" && -f "$dest/plugin.php" ]]; then
    echo "[install-dev-plugins] skip $id (already present)"
    continue
  fi

  zip="$(find_marketplace_zip "$id" || true)"
  if [[ -z "$zip" ]]; then
    echo "[install-dev-plugins] WARN: $id missing and no marketplace zip in $PKG_DIR" >&2
    continue
  fi

  install_from_zip "$id" "$zip"
done

missing=0
for id in "${OFFICIAL_IDS[@]}"; do
  if [[ ! -f "$ROOT/plugins/$id/plugin.php" ]]; then
    echo "[install-dev-plugins] FAIL: official plugin not present: $id" >&2
    missing=1
  fi
done

if [[ "$missing" -ne 0 ]]; then
  exit 1
fi

echo "[install-dev-plugins] OK: all official plugins present"
exit 0
