#!/bin/sh
# Startup must not exit before nginx — a volume/symlink issue should warn, not 502.

echo "[entrypoint] Birkly CMS docker image — document root /app (not /usr/share/nginx/html)"

if [ -f /app/BUILD_REVISION ]; then
  echo "[entrypoint] Build revision: $(tr -d '\n' < /app/BUILD_REVISION)"
fi

DATA_ROOT="${BIRKLY_DATA:-}"
USE_DATA_ROOT=0

# Symlink /app/<name> → $DATA_ROOT/<name>, migrating a real directory when present.
link_persistent_dir() {
  local name="$1"
  local target="$DATA_ROOT/$name"
  local link="/app/$name"

  if ! mkdir -p "$target" 2>/dev/null; then
    echo "[entrypoint] WARN: cannot create $target"
    return 1
  fi

  if [ -L "$link" ]; then
    return 0
  fi

  if [ -d "$link" ]; then
    if [ -n "$(find "$link" -mindepth 1 -maxdepth 1 ! -name '.gitkeep' -print -quit 2>/dev/null)" ]; then
      echo "[entrypoint] Migrating /app/$name → $target"
      cp -a "$link/." "$target/" 2>/dev/null || true
    fi
  fi

  rm -rf "$link" 2>/dev/null || true
  if ! ln -sfn "$target" "$link" 2>/dev/null; then
    echo "[entrypoint] WARN: failed to symlink $link → $target"
    return 1
  fi
  return 0
}

legacy_layout() {
  echo "[entrypoint] Using legacy in-container data dirs (check volume mount at /app/data)"
  DIRS="/app/content /app/media /app/settings /app/users /app/data /app/cache /app/logs /app/temp /app/plugins /app/themes /app/data/plugin-data"
  for dir in $DIRS; do
    mkdir -p "$dir" 2>/dev/null || true
  done
  chmod_dirs="/app/content /app/media /app/settings /app/users /app/data /app/cache /app/logs /app/temp /app/plugins /app/themes /app/data/plugin-data"
}

if [ -n "$DATA_ROOT" ]; then
  echo "[entrypoint] Unified data root: $DATA_ROOT (single volume mount)"
  if mkdir -p "$DATA_ROOT" 2>/dev/null; then
  USE_DATA_ROOT=1
  for name in content settings users media cache logs temp plugins themes; do
    if ! link_persistent_dir "$name"; then
      echo "[entrypoint] WARN: data symlink failed for $name — falling back to legacy layout"
      USE_DATA_ROOT=0
      break
    fi
  done
  else
    echo "[entrypoint] WARN: cannot write $DATA_ROOT — falling back to legacy layout"
    USE_DATA_ROOT=0
  fi
fi

if [ "$USE_DATA_ROOT" -eq 1 ]; then
  for name in ai_conversations ai_approvals webhooks invitations analytics mcp_audit mcp_rate feedback activity; do
    mkdir -p "$DATA_ROOT/$name" 2>/dev/null || true
  done
  # Only ensure the plugins root exists. Plugin-specific runtime dirs (e.g.
  # plugin-data/commerce/*) are created by each plugin on activate.
  mkdir -p "$DATA_ROOT/plugins" "$DATA_ROOT/plugin-data" 2>/dev/null || true
  # Official plugins are Hub-installed. No bundled plugin auto-seed (P71).
  # Git-cloned plugins under /app/data/plugins need CMS paths via ../.. from realpath.
  ln -sfn /app/core "$DATA_ROOT/core" 2>/dev/null || true
  ln -sfn /app/fieldtypes "$DATA_ROOT/fieldtypes" 2>/dev/null || true
  ln -sfn /app/api "$DATA_ROOT/api" 2>/dev/null || true
  chmod_dirs="$DATA_ROOT/content $DATA_ROOT/media $DATA_ROOT/settings $DATA_ROOT/users $DATA_ROOT/cache $DATA_ROOT/logs $DATA_ROOT/temp $DATA_ROOT/plugins $DATA_ROOT/themes $DATA_ROOT/ai_conversations $DATA_ROOT/ai_approvals $DATA_ROOT/webhooks $DATA_ROOT/invitations $DATA_ROOT/analytics $DATA_ROOT/mcp_audit $DATA_ROOT/mcp_rate $DATA_ROOT/feedback $DATA_ROOT/activity $DATA_ROOT/plugin-data"
else
  legacy_layout
fi

if [ ! -L /app/content ] && [ ! -d /app/content ]; then
  echo "[entrypoint] WARN: /app/content missing — creating empty content dir"
  mkdir -p /app/content 2>/dev/null || true
fi

mkdir -p /app/content/project /app/content/collections 2>/dev/null || true
if [ -L /app/content/collections ] && [ ! -d /app/content/collections ]; then
  rm -f /app/content/collections 2>/dev/null || true
  mkdir -p /app/content/collections 2>/dev/null || true
fi
chmod 777 /app/content/collections 2>/dev/null || true

if [ -L /app/project ]; then
  :
elif [ -d /app/project ]; then
  if [ -n "$(find /app/project -mindepth 1 -maxdepth 1 ! -name '.gitkeep' -print -quit 2>/dev/null)" ]; then
    cp -a /app/project/. /app/content/project/ 2>/dev/null || true
  fi
  rm -rf /app/project 2>/dev/null || true
  ln -sfn /app/content/project /app/project 2>/dev/null || true
else
  ln -sfn /app/content/project /app/project 2>/dev/null || true
fi

chmod -R 777 $chmod_dirs 2>/dev/null || true

# In-app Hub updates copy files under core/, admin/, api/, and fieldtypes/.
# The image ships those trees as root; PHP-FPM runs as www-data.
ensure_hub_program_tree_writable() {
  dir="$1"
  if [ ! -d "$dir" ]; then
    return 0
  fi
  chown -R www-data:www-data "$dir" 2>/dev/null || true
  find "$dir" -type d -exec chmod 775 {} \; 2>/dev/null || true
  find "$dir" -type f -exec chmod 664 {} \; 2>/dev/null || true
  if ! su -s /bin/sh www-data -c "touch \"$dir/.birkly-write-test\"" 2>/dev/null; then
    echo "[entrypoint] WARN: $dir not writable by www-data — applying permissive mode for Hub updates"
    chmod -R a+rwx "$dir" 2>/dev/null || true
  fi
  rm -f "$dir/.birkly-write-test" 2>/dev/null || true
}

for dir in /app/core /app/admin /app/api /app/fieldtypes; do
  ensure_hub_program_tree_writable "$dir"
done
for f in /app/index.php /app/VERSION /app/server-router.php /app/invite.php; do
  if [ -f "$f" ]; then
    chown www-data:www-data "$f" 2>/dev/null || true
    chmod 664 "$f" 2>/dev/null || true
  fi
done
mkdir -p /app/backups 2>/dev/null || true
chown -R www-data:www-data /app/backups 2>/dev/null || true
chmod 775 /app/backups 2>/dev/null || true

rm -f /var/run/nginx.pid /var/run/nginx.pid.lock 2>/dev/null || true

if [ -f /app/scripts/repair-starter-content.php ]; then
  echo "[entrypoint] Running starter content repairs..."
  php /app/scripts/repair-starter-content.php || true
fi

if [ -f /app/scripts/emergency-deactivate-broken-plugins.php ]; then
  echo "[entrypoint] Checking active plugins for syntax errors..."
  php /app/scripts/emergency-deactivate-broken-plugins.php || true
fi

# Plugin dev git sync (config on data volume — survives Dokploy redeploys).
# Dokploy UI: set GITHUB_TOKEN (+ optional PLUGIN_DEV_SYNC_ENABLED=1), then Redeploy.
if [ "$USE_DATA_ROOT" -eq 1 ]; then
  if [ -x /app/scripts/plugin-dev-sync-bootstrap-from-env.sh ]; then
    /app/scripts/plugin-dev-sync-bootstrap-from-env.sh || true
  fi
  if [ -x /app/scripts/plugin-dev-sync-start-loop.sh ]; then
    /app/scripts/plugin-dev-sync-start-loop.sh || true
  fi
fi

echo "[entrypoint] Starting php-fpm..."
if ! php-fpm -D; then
  echo "[entrypoint] ERROR: php-fpm failed to start"
  exit 1
fi

echo "[entrypoint] Testing nginx config..."
if ! nginx -t 2>&1; then
  echo "[entrypoint] ERROR: nginx config test failed"
  exit 1
fi

echo "[entrypoint] Starting nginx on :80..."
exec nginx -g 'daemon off;'
