#!/usr/bin/env bash
# P11-M6b — Build official plugin zips and publish to the Birkly Marketplace registry API.
#
# Usage:
#   ./scripts/publish-official-plugins.sh [version]
#   ./scripts/publish-official-plugins.sh --dry-run [version]
#
# Environment:
#   MARKETPLACE_URL — default https://marketplace.birkly.io
#   MARKETPLACE_REGISTRY_PUBLISH_TOKEN — Bearer token (required unless --dry-run)
#
# On CI tag builds, version defaults to the git tag without the leading "v".

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MANIFEST="$ROOT/scripts/cms-release-manifest.json"
BUILD="$ROOT/scripts/build-plugin-zip.sh"
DRY_RUN=0
VERSION_ARG=""

for arg in "$@"; do
  case "$arg" in
    --dry-run)
      DRY_RUN=1
      ;;
    *)
      if [[ -z "$VERSION_ARG" ]]; then
        VERSION_ARG="$arg"
      fi
      ;;
  esac
done

if [[ -z "$VERSION_ARG" ]]; then
  if [[ -n "${GITHUB_REF_NAME:-}" ]]; then
    VERSION_ARG="${GITHUB_REF_NAME#v}"
  elif git -C "$ROOT" describe --tags --exact-match >/dev/null 2>&1; then
    VERSION_ARG="$(git -C "$ROOT" describe --tags --exact-match | sed 's/^v//')"
  fi
fi

MARKETPLACE_URL="${MARKETPLACE_URL:-https://marketplace.birkly.io}"
REGISTRY_ENDPOINT="${MARKETPLACE_URL%/}/api/v1/registry/official/releases"
TOKEN="${MARKETPLACE_REGISTRY_PUBLISH_TOKEN:-}"

if [[ ! -x "$BUILD" ]]; then
  chmod +x "$BUILD"
fi

if [[ "$DRY_RUN" -eq 0 && -z "$TOKEN" ]]; then
  echo "MARKETPLACE_REGISTRY_PUBLISH_TOKEN is required (or pass --dry-run)" >&2
  exit 1
fi

mapfile -t PLUGIN_IDS < <(MANIFEST="$MANIFEST" php -r '
$manifest = json_decode((string) file_get_contents(getenv("MANIFEST")), true);
$ids = $manifest["official_plugin_ids"] ?? [];
if (!is_array($ids)) { exit(1); }
foreach ($ids as $id) {
    if (is_string($id) && $id !== "") { echo $id, PHP_EOL; }
}
')

if [[ ${#PLUGIN_IDS[@]} -eq 0 ]]; then
  echo "No official_plugin_ids in $MANIFEST" >&2
  exit 1
fi

CHANGELOG="${CHANGELOG:-Release $VERSION_ARG}"

for plugin_id in "${PLUGIN_IDS[@]}"; do
  echo "[publish-official-plugins] Building $plugin_id version=${VERSION_ARG:-plugin.json}" >&2
  zip_path="$("$BUILD" "$plugin_id" ${VERSION_ARG:+"$VERSION_ARG"})"
  if [[ ! -f "$zip_path" ]]; then
    echo "Zip not found after build: $zip_path" >&2
    exit 1
  fi

  sha256="$(sha256sum "$zip_path" | awk '{print $1}')"
  size_bytes="$(wc -c < "$zip_path" | tr -d ' ')"
  marketplace_slug="$(PLUGIN_ID="$plugin_id" MANIFEST="$MANIFEST" php -r '
$manifest = json_decode((string) file_get_contents(getenv("MANIFEST")), true);
$slugs = $manifest["official_marketplace_slugs"] ?? [];
$id = getenv("PLUGIN_ID");
$slug = is_array($slugs) ? ($slugs[$id] ?? "") : "";
echo $slug;
')"

  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "[dry-run] $marketplace_slug $zip_path sha256=$sha256 size_bytes=$size_bytes -> $REGISTRY_ENDPOINT" >&2
    continue
  fi

  echo "[publish-official-plugins] Uploading $marketplace_slug to registry" >&2
  http_code="$(curl -sS -o /tmp/birkly-publish-response.json -w "%{http_code}" \
    -X POST "$REGISTRY_ENDPOINT" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json" \
    -F "marketplace_slug=$marketplace_slug" \
    -F "version=${VERSION_ARG}" \
    -F "checksum_sha256=$sha256" \
    -F "size_bytes=$size_bytes" \
    -F "changelog=$CHANGELOG" \
    -F "artifact=@$zip_path")"

  if [[ "$http_code" -lt 200 || "$http_code" -ge 300 ]]; then
    echo "Registry upload failed for $marketplace_slug (HTTP $http_code):" >&2
    cat /tmp/birkly-publish-response.json >&2 || true
    exit 1
  fi

  echo "[publish-official-plugins] Published $marketplace_slug ($http_code)" >&2
done

echo "[publish-official-plugins] Done." >&2
