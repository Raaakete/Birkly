#!/bin/sh
set -e

echo "[entrypoint] Birkly CMS docker image — document root /app (not /usr/share/nginx/html)"

DIRS="/app/content /app/media /app/settings /app/users /app/data /app/cache /app/logs /app/plugins /app/themes"

for dir in $DIRS; do
  mkdir -p "$dir"
done

# Public site files live under the content volume (already mounted on every install).
# Symlink /app/project → /app/content/project so starter packs persist without an extra volume.
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

chmod -R 777 /app/content /app/media /app/settings /app/users /app/data /app/cache /app/logs /app/plugins /app/themes 2>/dev/null || true

echo "[entrypoint] Starting php-fpm..."
php-fpm -D

echo "[entrypoint] Testing nginx config..."
nginx -t

echo "[entrypoint] Starting nginx on :80..."
exec nginx -g 'daemon off;'
