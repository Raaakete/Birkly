# Birkly CMS — production image (Dokploy / Docker)
FROM php:8.3-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx libzip-dev libcurl4-openssl-dev unzip curl \
    && docker-php-ext-install zip curl \
    && rm -rf /var/lib/apt/lists/* \
    && rm -f /etc/nginx/sites-enabled/default

COPY docker/nginx-site.conf /etc/nginx/conf.d/default.conf
WORKDIR /app

ENV BIRKLY_DATA=/app/data

COPY . /app
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && mkdir -p content media settings users data cache logs temp plugins themes

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1/api/health.php || exit 1

ENTRYPOINT ["/entrypoint.sh"]
