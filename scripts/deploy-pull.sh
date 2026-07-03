#!/usr/bin/env bash
# Pull latest Birkly platform code on a server (repo root — not admin/ alone).
set -euo pipefail

ROOT="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
BRANCH="${2:-main}"

if [[ ! -d "$ROOT/admin" || ! -d "$ROOT/api" || ! -d "$ROOT/core" ]]; then
  echo "ERROR: $ROOT is not a Birkly CMS root (need admin/, api/, core/ siblings)." >&2
  echo "Clone: git clone https://github.com/Raaakete/Birkly.git && cd Birkly" >&2
  exit 1
fi

cd "$ROOT"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull origin "$BRANCH"

echo "Deployed $(git rev-parse --short HEAD) on branch $BRANCH"
echo "Verify: open /admin/deploy-check.php while logged in"
php -l admin/templates/content.html >/dev/null && echo "content.html: syntax OK"
php -l api/admin_content_helpers.php >/dev/null && echo "admin_content_helpers.php: syntax OK"

if command -v php >/dev/null 2>&1; then
  php -r "
    require '$ROOT/core/birkly_paths.php';
    require '$ROOT/core/content_page.php';
    \$d = birkly_load_content_page_data_safe();
    if (isset(\$d['error'])) {
      fwrite(STDERR, 'content smoke FAILED: ' . \$d['error'] . PHP_EOL);
      exit(1);
    }
    echo 'content smoke OK: ' . count(\$d['collections'] ?? []) . ' collections' . PHP_EOL;
  " || true
fi

if php -r "exit(function_exists('opcache_reset') ? 0 : 1);" 2>/dev/null; then
  php -r "
    if (function_exists('opcache_reset')) { opcache_reset(); }
    if (function_exists('opcache_invalidate')) {
      foreach (['admin/index.php','core/content_page.php','api/admin_content_helpers.php','admin/templates/content.html','admin/templates/entries.html'] as \$f) {
        @opcache_invalidate('$ROOT/' . \$f, true);
      }
    }
    echo 'OPcache reset' . PHP_EOL;
  " || true
fi
