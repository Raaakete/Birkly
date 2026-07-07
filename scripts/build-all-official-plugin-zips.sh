#!/usr/bin/env bash
# Build marketplace zips for all official Birkly plugins (see cms-release-manifest.json).

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD="$ROOT/scripts/build-plugin-zip.sh"

if [[ ! -x "$BUILD" ]]; then
  chmod +x "$BUILD"
fi

MANIFEST="$ROOT/scripts/cms-release-manifest.json"
mapfile -t PLUGIN_IDS < <(MANIFEST="$MANIFEST" php -r '
$manifest = json_decode((string) file_get_contents(getenv("MANIFEST")), true);
$ids = $manifest["official_plugin_ids"] ?? [];
if (!is_array($ids)) {
    exit(1);
}
foreach ($ids as $id) {
    if (is_string($id) && $id !== "") {
        echo $id, PHP_EOL;
    }
}
')

if [[ ${#PLUGIN_IDS[@]} -eq 0 ]]; then
  echo "No official_plugin_ids in $MANIFEST" >&2
  exit 1
fi

for plugin_id in "${PLUGIN_IDS[@]}"; do
  echo "[build-all-official-plugin-zips] $plugin_id" >&2
  "$BUILD" "$plugin_id"
done
