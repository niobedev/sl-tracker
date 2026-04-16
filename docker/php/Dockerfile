# ─── Caddy binary ────────────────────────────────────────────────────────────
FROM caddy:2-alpine AS caddy-bin

# ─── Base: shared PHP + Caddy setup ─────────────────────────────────────────
FROM php:8.4-fpm AS base

RUN apt-get update && apt-get install -y \
        zip unzip libonig-dev libxml2-dev libzip-dev supervisor netcat-openbsd \
    && docker-php-ext-install pdo pdo_mysql mbstring zip opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=caddy-bin  /usr/bin/caddy    /usr/bin/caddy

WORKDIR /var/www/html

COPY docker/php/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php/php-fpm.conf     /usr/local/etc/php-fpm.d/zz-custom.conf
COPY docker/caddy/Caddyfile      /etc/caddy/Caddyfile

# ─── Dev: add xdebug, code is mounted at runtime ────────────────────────────
FROM base AS dev

RUN pecl install xdebug && docker-php-ext-enable xdebug

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# ─── Prod: no xdebug, code baked in ─────────────────────────────────────────
FROM base AS prod

# Dependency layer — cached unless composer.json/lock change
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --optimize-autoloader

# Application source
COPY . .

RUN rm -rf var/ secrets/ \
    && mkdir -p var/cache/prod var/log secrets /mnt/app_public \
    && chown -R www-data:www-data var/

COPY docker/php/entrypoint.prod.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
