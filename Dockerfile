# Birkly CMS — production image (Dokploy / Docker)
# Project SSR (P52) renders in-process with the PHP template engine
# (core/birkly_template_engine.php) — no Node.js runtime needed.
FROM php:8.3.36-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx libzip-dev libcurl4-openssl-dev unzip curl git \
    && docker-php-ext-install zip curl \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx-site.conf /etc/nginx/conf.d/default.conf
COPY docker/php-production.ini /usr/local/etc/php/conf.d/99-birkly-production.ini
WORKDIR /app

ENV BIRKLY_DATA=/app/data

ARG BIRKLY_BUILD_REVISION=unknown
ENV BIRKLY_BUILD_REVISION=$BIRKLY_BUILD_REVISION

COPY . /app
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && chmod +x /app/scripts/plugin-dev-git-sync.sh /app/scripts/plugin-dev-sync-enable.sh /app/scripts/plugin-dev-sync-start-loop.sh \
    && chmod +x /app/scripts/sync-template-engine-inline.sh /app/scripts/build-client-assets.sh \
    && bash /app/scripts/sync-template-engine-inline.sh \
    && bash /app/scripts/build-client-assets.sh \
    && echo "$BIRKLY_BUILD_REVISION" > /app/BUILD_REVISION \
    && mkdir -p content media settings users data cache logs temp plugins themes backups \
    && chown -R www-data:www-data /app/admin /app/api /app/core /app/fieldtypes /app/index.php /app/VERSION /app/server-router.php /app/invite.php /app/backups \
    && chown -R www-data:www-data content media settings users data cache logs temp plugins themes

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=5 \
    CMD curl -fsS http://127.0.0.1/api/deploy_revision.php >/dev/null || curl -fsS http://127.0.0.1/api/health.php >/dev/null || exit 1

ENTRYPOINT ["/entrypoint.sh"]
