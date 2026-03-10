FROM php:8.2-fpm-alpine

# ── Dependencias del sistema ──────────────────────────────────────────────────
RUN apk add --no-cache \
    git curl zip unzip \
    libpng-dev oniguruma-dev libxml2-dev \
    netcat-openbsd

# ── Extensiones PHP ───────────────────────────────────────────────────────────
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# ── Extensión Redis ───────────────────────────────────────────────────────────
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# ── Composer ──────────────────────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Instalar dependencias PHP (capa cacheada)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

# Copiar el resto de la aplicación
COPY . .

# Optimizar autoloader
RUN composer dump-autoload --optimize

# Permisos
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache \
    && chmod +x /var/www/docker/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["sh", "/var/www/docker/entrypoint.sh"]
