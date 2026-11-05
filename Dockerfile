# --- PHP dependencies ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --ignore-platform-reqs

# --- Frontend assets (Tailwind/Vite) ---
FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# --- Runtime ---
FROM dunglas/frankenphp:1-php8.4 AS app
RUN install-php-extensions pdo_pgsql redis pcntl opcache intl zip bcmath
WORKDIR /app
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s CMD curl -fs http://localhost:8000/up || exit 1
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
