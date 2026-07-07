#!/usr/bin/env bash
# Install Commerce + Events plugins into kekaba Birkly-0.1 for local demos (P10-M6).
#
# Usage:
#   ./scripts/install-kekaba-demo-plugins.sh
#   ./scripts/install-kekaba-demo-plugins.sh /path/to/kekaba
#
# Installs from Birkly-Marketplace catalog zips when available; otherwise copies from
# the platform dev tree (plugins/ in this repo). Activates both plugins in local
# settings/plugins.json (gitignored runtime data).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SITE_REPO="${1:-$(dirname "$ROOT")/kekaba}"
EMBED="$(cd "$SITE_REPO" && pwd)/Birkly-0.1"
MARKETPLACE="${BIRKLY_MARKETPLACE:-$(dirname "$ROOT")/Birkly-Marketplace}"
PKG_DIR="$MARKETPLACE/catalog/packages"
MANIFEST="$ROOT/scripts/cms-release-manifest.json"

DEMO_IDS=(commerce events)

declare -A SLUGS=(
  [commerce]=birkly-commerce
  [events]=birkly-events
)

if [[ -f "$MANIFEST" ]] && command -v php >/dev/null 2>&1; then
  while IFS=$'\t' read -r id slug; do
    [[ -n "$id" && -n "$slug" ]] && SLUGS["$id"]="$slug"
  done < <(MANIFEST="$MANIFEST" php -r '
    $m = json_decode(file_get_contents(getenv("MANIFEST")), true);
    foreach (($m["official_marketplace_slugs"] ?? []) as $id => $slug) {
        if (in_array($id, ["commerce", "events"], true)) {
            echo $id . "\t" . $slug . "\n";
        }
    }
  ')
fi

if [[ ! -d "$EMBED/admin" || ! -d "$EMBED/api" ]]; then
  echo "[install-kekaba-demo-plugins] FAIL: embed not found: $EMBED" >&2
  exit 1
fi

mkdir -p "$EMBED/plugins"

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
  local dest="$EMBED/plugins/$id"
  local tmp
  tmp="$(mktemp -d)"

  unzip -q "$zip" -d "$tmp"
  mkdir -p "$EMBED/plugins"
  rm -rf "$dest"

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
      echo "[install-kekaba-demo-plugins] FAIL: could not locate plugin root in $zip" >&2
      return 1
    fi
  fi

  echo "[install-kekaba-demo-plugins] installed $id from $(basename "$zip")"
}

copy_from_platform() {
  local id="$1"
  local src="$ROOT/plugins/$id"
  local dest="$EMBED/plugins/$id"

  if [[ ! -d "$src" || ! -f "$src/plugin.php" ]]; then
    echo "[install-kekaba-demo-plugins] FAIL: platform plugin missing: $src" >&2
    return 1
  fi

  rm -rf "$dest"
  cp -a "$src" "$dest"
  echo "[install-kekaba-demo-plugins] installed $id from platform dev tree"
}

for id in "${DEMO_IDS[@]}"; do
  dest="$EMBED/plugins/$id"
  if [[ -d "$dest" && -f "$dest/plugin.php" ]]; then
    echo "[install-kekaba-demo-plugins] skip $id (already present)"
    continue
  fi

  zip="$(find_marketplace_zip "$id" || true)"
  if [[ -n "$zip" ]]; then
    install_from_zip "$id" "$zip"
  else
    copy_from_platform "$id"
  fi
done

missing=0
for id in "${DEMO_IDS[@]}"; do
  if [[ ! -f "$EMBED/plugins/$id/plugin.php" ]]; then
    echo "[install-kekaba-demo-plugins] FAIL: demo plugin not present: $id" >&2
    missing=1
  fi
done

if [[ "$missing" -ne 0 ]]; then
  exit 1
fi

SETTINGS_DIR="$EMBED/settings"
SETTINGS_FILE="$SETTINGS_DIR/plugins.json"
mkdir -p "$SETTINGS_DIR"

DEMO_IDS_CSV="$(IFS=,; echo "${DEMO_IDS[*]}")"
SETTINGS_FILE="$SETTINGS_FILE" DEMO_IDS="$DEMO_IDS_CSV" php <<'PHP'
<?php
$settingsFile = getenv('SETTINGS_FILE');
$demoIds = array_filter(array_map('trim', explode(',', getenv('DEMO_IDS') ?: '')));
$states = [];
if (is_file($settingsFile)) {
    $decoded = json_decode(file_get_contents($settingsFile), true);
    if (is_array($decoded)) {
        $states = $decoded;
    }
}
foreach ($demoIds as $id) {
    $states[$id] = array_merge($states[$id] ?? [], [
        'active' => true,
        'settings' => $states[$id]['settings'] ?? [],
    ]);
}
file_put_contents($settingsFile, json_encode($states, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "[install-kekaba-demo-plugins] activated: " . implode(', ', $demoIds) . " in settings/plugins.json\n";
PHP

echo "[install-kekaba-demo-plugins] OK: commerce + events ready for kekaba demos"
exit 0
