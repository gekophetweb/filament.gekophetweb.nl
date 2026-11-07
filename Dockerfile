# --- PHP dependencies ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && composer dump-autoload --optimize --ignore-platform-reqs

# --- Frontend assets (Tailwind/Vite) ---
FROM node:24-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci --ignore-scripts || npm install --ignore-scripts
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# --- Runtime ---
FROM dunglas/frankenphp:1-php8.5 AS app
RUN install-php-extensions pdo_pgsql redis pcntl opcache intl zip bcmath
ENV OCTANE_SERVER=frankenphp
WORKDIR /app
COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint && rm -rf tests docker \
 && cp vendor/laravel/octane/src/Commands/stubs/frankenphp-worker.php public/frankenphp-worker.php \
 && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache /data/caddy /config/caddy
USER www-data
EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s \
  CMD php -r "exit(@file_get_contents('http://127.0.0.1:8000/up') === false ? 1 : 0);"
ENTRYPOINT ["app-entrypoint"]
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]
