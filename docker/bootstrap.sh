#!/bin/sh
# Same as bootstrap.ps1 for Linux/macOS.
set -e
docker run --rm -v "$PWD:/work" -w /work composer:2 sh -c '
set -e
composer create-project laravel/laravel _tmp --no-interaction --prefer-dist --ignore-platform-reqs
cp -rn _tmp/. . && rm -rf _tmp
composer require --no-interaction --ignore-platform-reqs \
  laravel/socialite laravel/sanctum laravel/octane livewire/livewire laravel/mcp dedoc/scramble
composer require --dev --no-interaction --ignore-platform-reqs pestphp/pest pestphp/pest-plugin-laravel
'
[ -f .env ] || cp .env.example .env
echo "Edit .env, then: docker compose build && docker compose run --rm app php artisan key:generate && docker compose run --rm app php artisan migrate --force && docker compose up -d"
