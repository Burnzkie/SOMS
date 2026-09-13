# Dockerfile
# Render deploys this by building the image and running the container's
# default CMD. Render injects $PORT at runtime — the app must bind to it.
#
# Roadmap Phase 2.1 — previously ran `php artisan serve` (Laravel's
# built-in dev server) in production. It's not built for the burst
# concurrency an event-day mass QR check-in produces. This now uses
# FrankenPHP (a real, production-grade PHP app server built on Caddy)
# on the same php:8.3-alpine base the old image used, so the extension
# install step below is unchanged.

FROM composer:2 AS vendor
WORKDIR /app
COPY database/ database/
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --optimize-autoloader \
    --ignore-platform-reqs

FROM dunglas/frankenphp:php8.3-alpine

# gd: for intervention/image + simple-qrcode (PNG rendering)
# pdo_mysql: talks to the Clever Cloud MySQL add-on
# redis: Roadmap Phase 2.3/2.4 -- native phpredis extension via PECL, so
# CACHE_STORE/QUEUE_CONNECTION can be switched to 'redis' (config/database.php's
# 'redis' connection + REDIS_CLIENT=phpredis in .env.example already expect
# this) without adding predis/predis to composer.json. Provision an actual
# Redis instance (e.g. Render's managed Redis) and set REDIS_HOST/PORT/PASSWORD
# before flipping the env vars -- installing the extension alone changes
# nothing until CACHE_STORE=redis / QUEUE_CONNECTION=redis are actually set.
RUN apk add --no-cache \
        libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev \
        autoconf gcc g++ make \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql bcmath zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf gcc g++ make

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN php artisan config:clear \
    && chown -R www-data:www-data storage bootstrap/cache

# Deploy-readiness fix (Sep 2026): the base dunglas/frankenphp image applies
# `setcap cap_net_bind_service=+ep` to the frankenphp binary at build time,
# so it can bind privileged ports (80/443) without running as root. Render's
# sandboxed runtime can't execute a binary carrying that capability at all —
# it fails the container with "frankenphp: Operation not permitted" (exit
# 126) before Caddy/FrankenPHP's own code ever runs. We don't need the
# capability anyway: Render injects a non-privileged $PORT (e.g. 10000) and
# the Caddyfile binds to :{$PORT}, well above 1024. Stripping it is the
# documented way to run FrankenPHP with zero capabilities on a non-
# privileged port (https://frankenphp.dev/docs/docker/#running-with-no-capabilities).
RUN apk add --no-cache libcap \
    && setcap -r /usr/local/bin/frankenphp \
    && apk del libcap

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 10000
CMD ["/usr/local/bin/start.sh"]
