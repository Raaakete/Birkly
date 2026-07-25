#!/bin/sh
# Do not use set -e globally — a chmod/symlink warning must not prevent nginx from starting (502).

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

  if ! mkdir -p "$target"; then
    echo "[entrypoint] ERROR: cannot create $target"
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

  rm -rf "$link"
  if ! ln -sfn "$target" "$link"; then
    echo "[entrypoint] ERROR: failed to symlink $link → $target"
    return 1
  fi
}

if [ -n "$DATA_ROOT" ]; then
  echo "[entrypoint] Unified data root: $DATA_ROOT (single volume mount)"
  if ! mkdir -p "$DATA_ROOT"; then
    echo "[entrypoint] ERROR: cannot create data root $DATA_ROOT (check volume mount permissions)"
    exit 1
  fi

  for name in content settings users media cache logs temp plugins themes; do
    link_persistent_dir "$name" || exit 1
  done

  for name in ai_conversations ai_approvals webhooks invitations analytics mcp_audit mcp_rate feedback; do
    mkdir -p "$DATA_ROOT/$name" 2>/dev/null || true
  done
  mkdir -p "$DATA_ROOT/plugins/commerce" "$DATA_ROOT/plugins/payments" 2>/dev/null || true

  chmod_dirs="$DATA_ROOT/content $DATA_ROOT/media $DATA_ROOT/settings $DATA_ROOT/users $DATA_ROOT/cache $DATA_ROOT/logs $DATA_ROOT/temp $DATA_ROOT/plugins $DATA_ROOT/themes $DATA_ROOT/ai_conversations $DATA_ROOT/ai_approvals $DATA_ROOT/webhooks $DATA_ROOT/invitations $DATA_ROOT/analytics $DATA_ROOT/mcp_audit $DATA_ROOT/mcp_rate $DATA_ROOT/feedback"
else
  echo "[entrypoint] Legacy layout (no BIRKLY_DATA — multiple top-level folders)"
  DIRS="/app/content /app/media /app/settings /app/users /app/data /app/cache /app/logs /app/temp /app/plugins /app/themes"
  for dir in $DIRS; do
    mkdir -p "$dir" 2>/dev/null || true
  done
  chmod_dirs="/app/content /app/media /app/settings /app/users /app/data /app/cache /app/logs /app/temp /app/plugins /app/themes"
fi

# Public site files live under content (persisted). Symlink /app/project → content/project.
if [ ! -L /app/content ] && [ ! -d /app/content ]; then
  echo "[entrypoint] ERROR: /app/content missing after data setup"
  exit 1
fi

mkdir -p /app/content/project 2>/dev/null || true

if [ -L /app/project ]; then
  :
elif [ -d /app/project ]; then
  if [ -n "$(find /app/project -mindepth 1 -maxdepth 1 ! -name '.gitkeep' -print -quit 2>/dev/null)" ]; then
    cp -a /app/project/. /app/content/project/ 2>/dev/null || true
  fi
  rm -rf /app/project
  ln -sfn /app/content/project /app/project
else
  ln -sfn /app/content/project /app/project
fi

chmod -R 777 $chmod_dirs 2>/dev/null || true

rm -f /var/run/nginx.pid /var/run/nginx.pid.lock 2>/dev/null || true

if [ -f /app/scripts/repair-starter-content.php ]; then
  echo "[entrypoint] Running starter content repairs..."
  php /app/scripts/repair-starter-content.php || true
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
