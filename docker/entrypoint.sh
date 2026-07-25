#!/bin/sh
set -e

echo "[entrypoint] Birkly CMS docker image — document root /app (not /usr/share/nginx/html)"

if [ -f /app/BUILD_REVISION ]; then
  echo "[entrypoint] Build revision: $(tr -d '\n' < /app/BUILD_REVISION)"
fi

DATA_ROOT="${BIRKLY_DATA:-}"

# Symlink /app/<name> → $DATA_ROOT/<name>, migrating a real directory when present.
link_persistent_dir() {
  local name="$1"
  local target="$DATA_ROOT/$name"
  local link="/app/$name"

  mkdir -p "$target"

  if [ -L "$link" ]; then
    return 0
  fi

  if [ -d "$link" ]; then
    if [ -n "$(find "$link" -mindepth 1 -maxdepth 1 ! -name '.gitkeep' -print -quit 2>/dev/null)" ]; then
      echo "[entrypoint] Migrating /app/$name → $target"
      cp -a "$link/." "$target/" 2>/dev/null || true
    fi
    rm -rf "$link"
  fi

  ln -s "$target" "$link"
}

if [ -n "$DATA_ROOT" ]; then
  echo "[entrypoint] Unified data root: $DATA_ROOT (single volume mount)"
  mkdir -p "$DATA_ROOT"

  for name in content settings users media cache logs temp plugins themes; do
    link_persistent_dir "$name"
  done

  for name in ai_conversations ai_approvals webhooks invitations analytics mcp_audit mcp_rate feedback; do
    mkdir -p "$DATA_ROOT/$name"
  done
  mkdir -p "$DATA_ROOT/plugins/commerce" "$DATA_ROOT/plugins/payments"

  chmod_dirs="$DATA_ROOT/content $DATA_ROOT/media $DATA_ROOT/settings $DATA_ROOT/users $DATA_ROOT/cache $DATA_ROOT/logs $DATA_ROOT/temp $DATA_ROOT/plugins $DATA_ROOT/themes $DATA_ROOT/ai_conversations $DATA_ROOT/ai_approvals $DATA_ROOT/webhooks $DATA_ROOT/invitations $DATA_ROOT/analytics $DATA_ROOT/mcp_audit $DATA_ROOT/mcp_rate $DATA_ROOT/feedback"
else
  echo "[entrypoint] Legacy layout (no BIRKLY_DATA — multiple top-level folders)"
  DIRS="/app/content /app/media /app/settings /app/users /app/data /app/cache /app/logs /app/temp /app/plugins /app/themes"
  for dir in $DIRS; do
    mkdir -p "$dir"
  done
  chmod_dirs="/app/content /app/media /app/settings /app/users /app/data /app/cache /app/logs /app/temp /app/plugins /app/themes"
fi

# Public site files live under content (persisted). Symlink /app/project → content/project.
mkdir -p /app/content/project
if [ -L /app/project ]; then
  :
elif [ -d /app/project ]; then
  if [ -n "$(find /app/project -mindepth 1 -maxdepth 1 ! -name '.gitkeep' -print -quit 2>/dev/null)" ]; then
    cp -a /app/project/. /app/content/project/ 2>/dev/null || true
  fi
  rm -rf /app/project
  ln -s /app/content/project /app/project
else
  ln -s /app/content/project /app/project
fi

chmod -R 777 $chmod_dirs 2>/dev/null || true

if [ -f /app/scripts/repair-starter-content.php ]; then
  echo "[entrypoint] Running starter content repairs..."
  php /app/scripts/repair-starter-content.php || true
fi

echo "[entrypoint] Starting php-fpm..."
php-fpm -D

echo "[entrypoint] Testing nginx config..."
nginx -t

echo "[entrypoint] Starting nginx on :80..."
exec nginx -g 'daemon off;'
