#!/usr/bin/env bash
# Install a Birkly reference project into the CMS content tree.
# Usage: ./scripts/install-reference-project.sh <project-name>
# Example: ./scripts/install-reference-project.sh blog
#
# Copies reference-projects/<name>/collections/* → content/collections/
# Website files stay at reference-projects/<name>/website/ (served from CMS root).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PROJECT="${1:-}"

usage() {
  echo "Usage: $0 <project-name>" >&2
  echo "Available projects:" >&2
  if [[ -d "$ROOT/reference-projects" ]]; then
    for d in "$ROOT/reference-projects"/*/; do
      [[ -d "$d" ]] || continue
      name="$(basename "$d")"
      [[ "$name" == "README.md" ]] && continue
      echo "  - $name" >&2
    done
  fi
  exit 1
}

[[ -n "$PROJECT" ]] || usage

SRC="$ROOT/reference-projects/$PROJECT"
if [[ ! -d "$SRC" ]]; then
  echo "[install-reference-project] ERROR: unknown project '$PROJECT'" >&2
  usage
fi

COL_SRC="$SRC/collections"
WEB_SRC="$SRC/website"
DEST_COL="$ROOT/content/collections"

if [[ ! -d "$COL_SRC" ]]; then
  echo "[install-reference-project] ERROR: no collections/ in $SRC" >&2
  exit 1
fi

if [[ ! -d "$WEB_SRC" ]]; then
  echo "[install-reference-project] ERROR: no website/ in $SRC" >&2
  exit 1
fi

mkdir -p "$DEST_COL"

echo "[install-reference-project] Clearing existing collections for demo isolation..."
rm -rf "$DEST_COL"/*
mkdir -p "$DEST_COL"

MANIFEST="$SRC/manifest.json"

echo "[install-reference-project] Installing '$PROJECT'..."
echo "[install-reference-project] Collections → $DEST_COL"

for col in "$COL_SRC"/*/; do
  [[ -d "$col" ]] || continue
  slug="$(basename "$col")"
  dest="$DEST_COL/$slug"
  mkdir -p "$dest"
  cp -a "$col"/* "$dest/"
  echo "  + $slug"
done

# Flat variant seeds → proper sub_entries/ paths (commerce reference projects)
if command -v php >/dev/null 2>&1; then
  php "$ROOT/scripts/normalize-reference-sub-entries.php"
fi

# Reindex commerce/events catalogs when manifest requires those plugins
if [[ -f "$MANIFEST" ]] && command -v php >/dev/null 2>&1; then
  needs_commerce="$(MANIFEST="$MANIFEST" php -r '$m=json_decode(file_get_contents(getenv("MANIFEST")),true); echo in_array("commerce", $m["plugins"] ?? [], true) ? "1" : "";')"
  needs_events="$(MANIFEST="$MANIFEST" php -r '$m=json_decode(file_get_contents(getenv("MANIFEST")),true); echo in_array("events", $m["plugins"] ?? [], true) ? "1" : "";')"
  if [[ -n "$needs_commerce" ]]; then
    echo "[install-reference-project] Reindexing commerce catalog..."
    php -r "
      chdir('$ROOT');
      require 'plugins/commerce/init.php';
      require 'plugins/commerce/includes/CatalogIndexer.php';
      (new CatalogIndexer())->reindexAll('$ROOT/content');
    " 2>/dev/null || true
  fi
  if [[ -n "$needs_events" ]]; then
    echo "[install-reference-project] Reindexing events..."
    php -r "
      chdir('$ROOT');
      require 'plugins/events/init.php';
      require 'plugins/events/includes/EventIndexer.php';
      events_bootstrap();
      (new EventIndexer())->reindexAll('$ROOT/content');
    " 2>/dev/null || true
  fi
fi

# Install plugins listed in manifest.json when present
if [[ -f "$MANIFEST" ]] && command -v php >/dev/null 2>&1; then
  plugins="$(MANIFEST="$MANIFEST" php -r '
    $m = json_decode(file_get_contents(getenv("MANIFEST")), true);
  foreach (($m["plugins"] ?? []) as $p) echo $p . "\n";
  ')"
  if [[ -n "$plugins" ]]; then
    echo "[install-reference-project] Required plugins:"
    while IFS= read -r pid; do
      [[ -n "$pid" ]] && echo "  - $pid"
    done <<< "$plugins"
    if [[ -x "$ROOT/scripts/install-dev-plugins.sh" ]]; then
      echo "[install-reference-project] Running install-dev-plugins.sh..."
      "$ROOT/scripts/install-dev-plugins.sh" || true
    fi
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
' "$SETTINGS" "$plugins"
  fi
fi

# Clear page snapshot cache so client fetches fresh bundle data
rm -rf "$ROOT/cache/page-snapshots"/* 2>/dev/null || true
mkdir -p "$ROOT/cache/page-snapshots"

SITE_URL="/reference-projects/$PROJECT/website/index.html"
echo ""
echo "[install-reference-project] OK: '$PROJECT' installed."
echo "  Website:  http://localhost:8080$SITE_URL"
echo "  Admin:    http://localhost:8080/admin"
echo ""
echo "Start the CMS dev server if needed:"
echo "  php -S localhost:8080 -t . server-router.php"
