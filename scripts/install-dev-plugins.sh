#!/usr/bin/env bash
# Install official plugin packages for local dev / CI (P35-M29).
# Pulls from marketplace registry API, with fallbacks to local catalog seed zips.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MARKETPLACE="${BIRKLY_MARKETPLACE:-$(dirname "$ROOT")/Birkly-Marketplace}"
PKG_DIR="$MARKETPLACE/catalog/packages"
MANIFEST="$ROOT/scripts/cms-release-manifest.json"
MARKETPLACE_URL="${MARKETPLACE_URL:-https://marketplace.birkly.cloud}"
USE_REGISTRY="${BIRKLY_INSTALL_FROM_REGISTRY:-1}"

declare -A SLUGS=(
  [commerce]=birkly-commerce
  [events]=birkly-events
  [automation]=birkly-automation
  [user_management]=birkly-user-management
  [studio]=birkly-studio
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

OFFICIAL_IDS=(commerce events automation user_management studio)

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

fetch_registry_release() {
  local slug="$1"
  local registry_json version release_json download_url tmp_zip

  registry_json="$(curl -fsSL --connect-timeout 15 --max-time 60 \
    "${MARKETPLACE_URL%/}/api/v1/registry" 2>/dev/null || true)"
  if [[ -z "$registry_json" ]]; then
    return 1
  fi

  version="$(SLUG="$slug" REGISTRY="$registry_json" php -r '
    $data = json_decode(getenv("REGISTRY"), true);
    $slug = getenv("SLUG");
    foreach (($data["offers"] ?? []) as $offer) {
        if (!is_array($offer) || ($offer["slug"] ?? "") !== $slug) continue;
        $v = trim((string) ($offer["latest_version"] ?? ""));
        if ($v !== "") { echo $v; exit(0); }
    }
    exit(1);
  ' 2>/dev/null || true)"

  if [[ -z "$version" ]]; then
    return 1
  fi

  release_json="$(curl -fsSL --connect-timeout 15 --max-time 60 \
    "${MARKETPLACE_URL%/}/api/v1/offers/${slug}/releases/${version}" 2>/dev/null || true)"
  if [[ -z "$release_json" ]]; then
    return 1
  fi

  download_url="$(RELEASE="$release_json" php -r '
    $data = json_decode(getenv("RELEASE"), true);
    $url = trim((string) ($data["download_url"] ?? ""));
    if ($url !== "") { echo $url; exit(0); }
    exit(1);
  ' 2>/dev/null || true)"

  if [[ -z "$download_url" ]]; then
    return 1
  fi

  tmp_zip="$(mktemp --suffix=.zip)"
  if ! curl -fsSL --connect-timeout 15 --max-time 120 -o "$tmp_zip" "$download_url" 2>/dev/null; then
    rm -f "$tmp_zip"
    return 1
  fi

  if [[ ! -s "$tmp_zip" ]]; then
    rm -f "$tmp_zip"
    return 1
  fi

  echo "$tmp_zip"
}

install_from_zip() {
  local id="$1"
  local zip="$2"
  local dest="$ROOT/plugins/$id"
  local tmp cleanup=0

  if [[ ! -f "$zip" ]]; then
    echo "[install-dev-plugins] FAIL: zip not found: $zip" >&2
    return 1
  fi

  if [[ "$zip" == /tmp/* || "$zip" == "${TMPDIR:-/tmp}"/* ]]; then
    cleanup=1
  fi

  tmp="$(mktemp -d)"
  unzip -q "$zip" -d "$tmp"
  mkdir -p "$ROOT/plugins"

  if [[ -f "$tmp/plugin.php" ]]; then
    rm -rf "$dest"
    mv "$tmp" "$dest"
  elif [[ -d "$tmp/$id" && -f "$tmp/$id/plugin.php" ]]; then
    rm -rf "$dest"
    mv "$tmp/$id" "$dest"
    rm -rf "$tmp"
  else
    local sub
    sub="$(find "$tmp" -mindepth 1 -maxdepth 1 -type d | head -1)"
    if [[ -n "$sub" && -f "$sub/plugin.php" ]]; then
      rm -rf "$dest"
      mv "$sub" "$dest"
      rm -rf "$tmp"
    else
      rm -rf "$tmp"
      echo "[install-dev-plugins] FAIL: could not locate plugin root in $zip" >&2
      [[ "$cleanup" -eq 1 ]] && rm -f "$zip"
      return 1
    fi
  fi

  [[ "$cleanup" -eq 1 ]] && rm -f "$zip"
  echo "[install-dev-plugins] installed $id from $(basename "$zip")"
}

for id in "${OFFICIAL_IDS[@]}"; do
  dest="$ROOT/plugins/$id"
  if [[ -d "$dest" && -f "$dest/plugin.php" ]]; then
    echo "[install-dev-plugins] skip $id (already present)"
    continue
  fi

  zip=""
  if [[ "$USE_REGISTRY" == "1" ]]; then
    slug="${SLUGS[$id]:-$id}"
    zip="$(fetch_registry_release "$slug" || true)"
    if [[ -n "$zip" ]]; then
      echo "[install-dev-plugins] fetched $id from registry ($slug)" >&2
    fi
  fi

  if [[ -z "$zip" ]]; then
    zip="$(find_marketplace_zip "$id" || true)"
    if [[ -n "$zip" ]]; then
      echo "[install-dev-plugins] using local seed zip for $id" >&2
    fi
  fi

  if [[ -z "$zip" ]]; then
    echo "[install-dev-plugins] WARN: $id missing — registry ($MARKETPLACE_URL) and local seed ($PKG_DIR) unavailable" >&2
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
