# =============================================================================
# Stage 1 – Node: compile frontend assets
# =============================================================================
FROM node:22-alpine AS node-build

WORKDIR /app

# Cache node_modules layer separately from source
COPY package*.json ./
RUN npm install

# Copy only what vite needs
COPY vite.config.js ./
COPY resources/ resources/
COPY public/ public/

# Build; blade.php falls back gracefully if Svelte packages aren't installed yet
RUN npm run build || mkdir -p public/build

# =============================================================================
# Stage 2 – PHP 8.4-FPM (shared image for app / reverb / worker)
# =============================================================================
FROM php:8.4-fpm AS app

# ---------------------------------------------------------------------------
# System dependencies
# ---------------------------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        zip \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libxml2-dev \
        libonig-dev \
        libssl-dev \
        libicu-dev \
    && docker-php-ext-install \
        pdo_mysql \
        bcmath \
        mbstring \
        xml \
        zip \
        pcntl \
        intl \
        sockets \
        opcache \
    # phpredis — required by REDIS_CLIENT=phpredis
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------------
# Opcache tuning (works for PHP-FPM and CLI / Reverb / Worker)
# ---------------------------------------------------------------------------
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# ---------------------------------------------------------------------------
# Composer
# ---------------------------------------------------------------------------
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# PHP dependencies — separate layer so it only rebuilds when lock changes
# ---------------------------------------------------------------------------
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --optimize-autoloader

# ---------------------------------------------------------------------------
# Application source
# ---------------------------------------------------------------------------
COPY . .

# ---------------------------------------------------------------------------
# Built frontend assets from Stage 1
# ---------------------------------------------------------------------------
COPY --from=node-build /app/public/build/ public/build/

# ---------------------------------------------------------------------------
# Finalise autoloader + storage permissions
# ---------------------------------------------------------------------------
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

# Default: PHP-FPM. docker-compose overrides this for reverb and worker.
CMD ["php-fpm"]
