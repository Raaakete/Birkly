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
  DIRS="/app/content /app/media /app/settings /app/users /app/data /app/cache /app/logs /app/temp /app/plugins /app/themes"
  for dir in $DIRS; do
    mkdir -p "$dir" 2>/dev/null || true
  done
  chmod_dirs="/app/content /app/media /app/settings /app/users /app/data /app/cache /app/logs /app/temp /app/plugins /app/themes"
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
  # Only ensure the plugins root exists. Do not pre-create empty plugin dirs
  # (e.g. commerce/) — Hub treats missing plugin.php as "not installed" but
  # extract used to hard-fail on any existing directory.
  mkdir -p "$DATA_ROOT/plugins" 2>/dev/null || true
  # Official plugins are Hub-installed. Do not auto-seed Automation into plugins/
  # (that made Hub show "Installed" on fresh sites). Opt-in for legacy images:
  #   BIRKLY_SEED_AUTOMATION=1
  if [ "${BIRKLY_SEED_AUTOMATION:-0}" = "1" ] && [ -d /app/bundled-plugins/automation ]; then
    auto_dir="$DATA_ROOT/plugins/automation"
    plugin_json_version() {
      # Prefer php (always in image); fall back to sed.
      f="$1"
      [ -f "$f" ] || { echo ""; return 0; }
      if command -v php >/dev/null 2>&1; then
        php -r '$j=@json_decode(@file_get_contents($argv[1]), true); echo is_array($j)&&isset($j["version"])?(string)$j["version"]:"";' "$f" 2>/dev/null || true
      else
        sed -n 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$f" 2>/dev/null | head -1
      fi
    }
    bundled_ver="$(plugin_json_version /app/bundled-plugins/automation/plugin.json)"
    installed_ver="$(plugin_json_version "$auto_dir/plugin.json")"
    if [ -f "$auto_dir/plugin.php" ] && [ -d "$auto_dir/.git" ]; then
      echo "[entrypoint] automation is a git dev clone — skip bundled copy (installed=${installed_ver:-unknown})"
    elif [ ! -f "$auto_dir/plugin.php" ]; then
      echo "[entrypoint] Seeding bundled automation plugin to data volume (BIRKLY_SEED_AUTOMATION=1)..."
      mkdir -p "$auto_dir" 2>/dev/null || true
      cp -a /app/bundled-plugins/automation/. "$auto_dir/" 2>/dev/null || true
    elif [ -n "$bundled_ver" ] && [ "$bundled_ver" != "$installed_ver" ]; then
      echo "[entrypoint] Refreshing automation plugin from bundle (${installed_ver:-none} -> ${bundled_ver})..."
      mkdir -p "$auto_dir" 2>/dev/null || true
      cp -a /app/bundled-plugins/automation/. "$auto_dir/" 2>/dev/null || true
    else
      echo "[entrypoint] automation plugin already present (version ${installed_ver:-unknown})"
    fi
  elif [ -d /app/bundled-plugins/automation ]; then
    echo "[entrypoint] Skipping automation auto-seed (install from Hub; set BIRKLY_SEED_AUTOMATION=1 to restore)"
  fi
  # Git-cloned plugins under /app/data/plugins need CMS paths via ../.. from realpath.
  ln -sfn /app/core "$DATA_ROOT/core" 2>/dev/null || true
  ln -sfn /app/fieldtypes "$DATA_ROOT/fieldtypes" 2>/dev/null || true
  ln -sfn /app/api "$DATA_ROOT/api" 2>/dev/null || true
  chmod_dirs="$DATA_ROOT/content $DATA_ROOT/media $DATA_ROOT/settings $DATA_ROOT/users $DATA_ROOT/cache $DATA_ROOT/logs $DATA_ROOT/temp $DATA_ROOT/plugins $DATA_ROOT/themes $DATA_ROOT/ai_conversations $DATA_ROOT/ai_approvals $DATA_ROOT/webhooks $DATA_ROOT/invitations $DATA_ROOT/analytics $DATA_ROOT/mcp_audit $DATA_ROOT/mcp_rate $DATA_ROOT/feedback $DATA_ROOT/activity"
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
