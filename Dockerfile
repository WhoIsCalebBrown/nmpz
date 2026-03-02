# Stage 1 — Build everything (needs both PHP and Node)
FROM php:8.4-cli-alpine AS build

# Install Node.js
RUN apk add --no-cache nodejs npm postgresql-dev \
    && docker-php-ext-install pdo_pgsql pgsql pcntl bcmath opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install PHP dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Install Node dependencies
COPY package.json package-lock.json ./
RUN npm ci

# Copy application source
COPY . .

# Run post-install scripts (package discovery, etc.)
RUN composer run-script post-autoload-dump

# Build frontend assets (wayfinder plugin needs PHP + artisan)
RUN npm run build

# Stage 2 — PHP-FPM application
FROM php:8.4-fpm-alpine AS app

RUN apk add --no-cache \
    postgresql-dev \
    linux-headers \
    && docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    pcntl \
    bcmath \
    opcache

WORKDIR /var/www/html

# Copy PHP dependencies from build stage
COPY --from=build /app/vendor vendor

# Copy application source
COPY . .

# Copy built frontend assets from build stage
COPY --from=build /app/public/build public/build

# Run post-install scripts (package discovery, etc.)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer run-script post-autoload-dump \
    && rm /usr/bin/composer

# Set permissions
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Entrypoint copies public assets to shared volume for nginx
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
