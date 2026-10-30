# syntax=docker/dockerfile:1

# ---- Dependencias PHP ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
 && rm -rf tests tv-apps/*/dist tv-apps/*/out .git

# ---- Aplicación (php-fpm, cola y planificador) ----
FROM php:8.4-fpm-alpine AS application
RUN apk add --no-cache ffmpeg libpng libjpeg-turbo libwebp freetype \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" gd pcntl opcache \
 && apk del .build-deps
COPY ops/php/php.ini /usr/local/etc/php/conf.d/zz-nokoplay.ini
COPY ops/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-nokoplay.conf
WORKDIR /var/www/html
COPY --from=vendor --chown=www-data:www-data /app /var/www/html
RUN mkdir -p storage/app/private storage/database storage/backups storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod +x ops/entrypoint.sh
USER www-data
ENTRYPOINT ["/var/www/html/ops/entrypoint.sh"]
CMD ["php-fpm"]

# ---- Servidor web (estáticos, app de TV y archivos privados vía X-Accel-Redirect) ----
FROM nginxinc/nginx-unprivileged:1.29-alpine AS web
COPY ops/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=vendor /app/public /var/www/html/public
