# Dockerfile
# Render deploys this by building the image and running the container's
# default CMD. Render injects $PORT at runtime — the app must bind to it.

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

FROM php:8.3-cli-alpine

# gd: for intervention/image + simple-qrcode (PNG rendering)
# pdo_mysql: talks to the Clever Cloud MySQL add-on
RUN apk add --no-cache \
        libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql bcmath zip

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN php artisan config:clear \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

EXPOSE 10000
CMD ["/usr/local/bin/start.sh"]