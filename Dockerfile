# Birkly CMS — production image (Dokploy / Docker)
FROM php:8.3-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx libzip-dev libcurl4-openssl-dev unzip curl \
    && docker-php-ext-install zip curl \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY docker/nginx-site.conf /etc/nginx/conf.d/default.conf
COPY docker/php-production.ini /usr/local/etc/php/conf.d/99-birkly-production.ini
WORKDIR /app

ENV BIRKLY_DATA=/app/data

ARG BIRKLY_BUILD_REVISION=unknown
ENV BIRKLY_BUILD_REVISION=$BIRKLY_BUILD_REVISION

COPY . /app
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && echo "$BIRKLY_BUILD_REVISION" > /app/BUILD_REVISION \
    && mkdir -p content media settings users data cache logs temp plugins themes

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=5 \
    CMD curl -fsS http://127.0.0.1/api/deploy_revision.php >/dev/null || curl -fsS http://127.0.0.1/api/health.php >/dev/null || exit 1

ENTRYPOINT ["/entrypoint.sh"]
