#!/usr/bin/env bash
# Build a marketplace-ready zip from an official plugin SoT repo or installed copy.
#
# Usage:
#   ./scripts/build-plugin-zip.sh <plugin_id> [version]
#
# Source resolution (P71 — no Ops mirror fallback):
#   1. BIRKLY_PLUGIN_SOT_ROOT — repo root for this plugin (must contain plugin.json)
#   2. Sibling clone ../Birkly-Plugin-{Name} or ../birkly-plugin-{name}
#   3. Installed copy plugins/{plugin_id}/ (local dev only)
#
# Version defaults to plugin.json "version". Output:
#   dist/plugins/{marketplace_slug}-{version}.zip
# Prints the output path on stdout.

set -euo pipefail

PLUGIN_ID="${1:-}"
VERSION_ARG="${2:-}"

if [[ -z "$PLUGIN_ID" ]]; then
  echo "Usage: $0 <plugin_id> [version]" >&2
  exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_INSTALL="$ROOT/plugins/$PLUGIN_ID"
MANIFEST="$ROOT/scripts/cms-release-manifest.json"

declare -A SOT_REPO_CANONICAL=(
  [commerce]=Birkly-Plugin-Commerce
  [automation]=Birkly-Plugin-Automation
  [events]=Birkly-Plugin-Events
  [user_management]=Birkly-Plugin-User
  [studio]=Birkly-Plugin-Studio
)

declare -A SOT_REPO_LOCAL=(
  [commerce]=birkly-plugin-commerce
  [automation]=birkly-plugin-automation
  [events]=birkly-plugin-events
  [user_management]=birkly-plugin-user
  [studio]=birkly-plugin-studio
)

resolve_plugin_dir() {
  local plugin_id="$1"
  local candidate=""

  if [[ -n "${BIRKLY_PLUGIN_SOT_ROOT:-}" ]]; then
    if [[ -f "${BIRKLY_PLUGIN_SOT_ROOT}/plugin.json" ]]; then
      echo "$BIRKLY_PLUGIN_SOT_ROOT"
      return 0
    fi
    if [[ -d "${BIRKLY_PLUGIN_SOT_ROOT}/${plugin_id}" && -f "${BIRKLY_PLUGIN_SOT_ROOT}/${plugin_id}/plugin.json" ]]; then
      echo "${BIRKLY_PLUGIN_SOT_ROOT}/${plugin_id}"
      return 0
    fi
  fi

  local parent
  parent="$(dirname "$ROOT")"
  for name in "${SOT_REPO_CANONICAL[$plugin_id]:-}" "${SOT_REPO_LOCAL[$plugin_id]:-}"; do
    [[ -z "$name" ]] && continue
    candidate="$parent/$name"
    if [[ -f "$candidate/plugin.json" ]]; then
      echo "$candidate"
      return 0
    fi
  done

  if [[ -d "$PLUGIN_INSTALL" && -f "$PLUGIN_INSTALL/plugin.json" ]]; then
    echo "$PLUGIN_INSTALL"
    return 0
  fi

  echo "Plugin source not found for: $plugin_id" >&2
  echo "Set BIRKLY_PLUGIN_SOT_ROOT to the SoT repo root, or clone Birkly-Plugin-* as a sibling of Birkly." >&2
  return 1
}

PLUGIN_DIR="$(resolve_plugin_dir "$PLUGIN_ID")"

if [[ ! -f "$PLUGIN_DIR/plugin.json" ]]; then
  echo "Missing plugin.json in $PLUGIN_DIR" >&2
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  echo "php is required" >&2
  exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
  echo "zip is required" >&2
  exit 1
fi

read -r SLUG VERSION <<< "$(PLUGIN_ID="$PLUGIN_ID" VERSION_ARG="$VERSION_ARG" MANIFEST="$MANIFEST" PLUGIN_JSON="$PLUGIN_DIR/plugin.json" php -r '
$pluginId = getenv("PLUGIN_ID");
$versionArg = getenv("VERSION_ARG");
$manifestPath = getenv("MANIFEST");
$pluginJsonPath = getenv("PLUGIN_JSON");

$manifest = json_decode((string) file_get_contents($manifestPath), true);
$pluginJson = json_decode((string) file_get_contents($pluginJsonPath), true);
$slug = trim((string) ($pluginJson["marketplace_slug"] ?? ""));
if ($slug === "") {
    $slugs = is_array($manifest["official_marketplace_slugs"] ?? null)
        ? $manifest["official_marketplace_slugs"]
        : [];
    $slug = $slugs[$pluginId] ?? null;
}
if (!is_string($slug) || $slug === "") {
    fwrite(STDERR, "No marketplace slug for plugin id: {$pluginId} (set marketplace_slug in plugin.json)\n");
    exit(1);
}

$version = trim($versionArg);
if ($version === "") {
    $version = trim((string) ($pluginJson["version"] ?? ""));
}
if ($version === "" || !preg_match("/^\d+\.\d+\.\d+(-[a-zA-Z0-9.]+)?$/", $version)) {
    fwrite(STDERR, "Invalid or missing version for {$pluginId}\n");
    exit(1);
}

echo $slug . " " . $version;
')"

OUT_DIR="$ROOT/dist/plugins"
OUT_FILE="$OUT_DIR/${SLUG}-${VERSION}.zip"
mkdir -p "$OUT_DIR"
rm -f "$OUT_FILE"

(
  cd "$PLUGIN_DIR"
  zip -qr "$OUT_FILE" . \
    -x '*.git*' \
    -x '*/.DS_Store' \
    -x '.DS_Store'
)

if [[ ! -f "$OUT_FILE" ]]; then
  echo "Failed to create zip: $OUT_FILE" >&2
  exit 1
fi

SHA256="$(sha256sum "$OUT_FILE" | awk '{print $1}')"
SIZE_BYTES="$(wc -c < "$OUT_FILE" | tr -d ' ')"

echo "$OUT_FILE"
echo "sha256=$SHA256 size_bytes=$SIZE_BYTES" >&2
