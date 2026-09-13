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

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 10000
CMD ["/usr/local/bin/start.sh"]
