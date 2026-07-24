#!/bin/sh
set -e

echo "[entrypoint] Birkly CMS docker image — document root /app (not /usr/share/nginx/html)"

DIRS="/app/content /app/media /app/settings /app/users /app/data /app/cache /app/logs /app/plugins /app/themes"

for dir in $DIRS; do
  mkdir -p "$dir"
done

chmod -R 777 /app/content /app/media /app/settings /app/users /app/data /app/cache /app/logs /app/plugins /app/themes 2>/dev/null || true

echo "[entrypoint] Starting php-fpm..."
php-fpm -D

echo "[entrypoint] Testing nginx config..."
nginx -t

echo "[entrypoint] Starting nginx on :80..."
exec nginx -g 'daemon off;'
