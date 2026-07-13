#!/usr/bin/env bash
# Prepare a single reference project for browser demos and video recording (P28).
# Installs collections, activates manifest plugins, reindexes commerce/events.
#
# Usage: ./scripts/prepare-reference-demo.sh <project-name>
# Example: ./scripts/prepare-reference-demo.sh yoga
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PROJECT="${1:-}"

if [[ -z "$PROJECT" ]]; then
  echo "Usage: $0 <project-name>" >&2
  echo "  blog | portfolio | coffee | yoga | social | influencer" >&2
  exit 1
fi

MANIFEST="$ROOT/reference-projects/$PROJECT/manifest.json"
if [[ ! -f "$MANIFEST" ]]; then
  echo "[prepare-reference-demo] ERROR: unknown project '$PROJECT'" >&2
  exit 1
fi

echo "[prepare-reference-demo] Installing reference project: $PROJECT"
"$ROOT/scripts/install-reference-project.sh" "$PROJECT"

if [[ -x "$ROOT/scripts/install-dev-plugins.sh" ]]; then
  "$ROOT/scripts/install-dev-plugins.sh"
fi

# Activate plugins listed in manifest.json
PLUGINS="$(MANIFEST="$MANIFEST" php -r '
$m = json_decode(file_get_contents(getenv("MANIFEST")), true);
foreach (($m["plugins"] ?? []) as $p) echo $p . "\n";
')"

SETTINGS="$ROOT/settings/plugins.json"
mkdir -p "$(dirname "$SETTINGS")"
php -r '
$settingsPath = $argv[1];
$plugins = array_filter(array_map("trim", explode("\n", $argv[2])));
$data = [];
if (is_file($settingsPath)) {
    $decoded = json_decode(file_get_contents($settingsPath), true);
    if (is_array($decoded)) $data = $decoded;
}
foreach ($plugins as $id) {
    if ($id === "") continue;
    if (!isset($data[$id]) || !is_array($data[$id])) {
        $data[$id] = ["active" => true, "settings" => []];
    } else {
        $data[$id]["active"] = true;
    }
}
file_put_contents($settingsPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "$SETTINGS" "$PLUGINS"

# Reindex plugin catalogs when needed
if echo "$PLUGINS" | grep -qx commerce; then
  echo "[prepare-reference-demo] Reindexing commerce catalog..."
  php -r "
    chdir('$ROOT');
    require 'plugins/commerce/init.php';
    require 'plugins/commerce/includes/CatalogIndexer.php';
    (new CatalogIndexer())->reindexAll('$ROOT/content');
  " 2>/dev/null || true
fi

if echo "$PLUGINS" | grep -qx events; then
  echo "[prepare-reference-demo] Reindexing events..."
  php -r "
    chdir('$ROOT');
    require 'plugins/events/init.php';
    require 'plugins/events/includes/EventIndexer.php';
    events_bootstrap();
    (new EventIndexer())->reindexAll('$ROOT/content');
  " 2>/dev/null || true
fi

# Clear page snapshot cache so client fetches fresh bundle data
rm -rf "$ROOT/cache/page-snapshots"/* 2>/dev/null || true
mkdir -p "$ROOT/cache/page-snapshots"

SITE_URL="/reference-projects/$PROJECT/website/index.html"
echo ""
echo "[prepare-reference-demo] OK — open http://localhost:8080$SITE_URL"
echo "  Plugins active: $(echo "$PLUGINS" | tr '\n' ' ')"
exit 0
