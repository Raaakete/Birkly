#!/usr/bin/env bash
# Build a marketplace-ready zip from plugins/{plugin_id}/ (plugin.json at archive root).
#
# Usage:
#   ./scripts/build-plugin-zip.sh <plugin_id> [version]
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
OPS_ROOT="${BIRKLY_OPS_ROOT:-$(dirname "$ROOT")/Birkly-Marketplace}"
PLUGIN_SRC="${OFFICIAL_PLUGINS_SRC:-$OPS_ROOT/internal/cms/packages/official-plugins}"
PLUGIN_DIR="$PLUGIN_SRC/$PLUGIN_ID"
# Installed copy for local dev (optional)
PLUGIN_INSTALL="$ROOT/plugins/$PLUGIN_ID"
MANIFEST="$ROOT/scripts/cms-release-manifest.json"

if [[ ! -d "$PLUGIN_DIR" ]]; then
  if [[ -d "$PLUGIN_INSTALL" ]]; then
    PLUGIN_DIR="$PLUGIN_INSTALL"
  else
    echo "Plugin directory not found: $PLUGIN_SRC/$PLUGIN_ID (set OFFICIAL_PLUGINS_SRC)" >&2
    exit 1
  fi
fi

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
