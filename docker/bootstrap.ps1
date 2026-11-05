# Run once from the repo root (Docker Desktop must be running).
# Scaffolds Laravel + required packages inside a Composer container.
$ErrorActionPreference = "Stop"
docker run --rm -v "${PWD}:/work" -w /work composer:2 sh -c @'
set -e
composer create-project laravel/laravel _tmp --no-interaction --prefer-dist --ignore-platform-reqs
cp -rn _tmp/. . && rm -rf _tmp
composer require --no-interaction --ignore-platform-reqs \
  laravel/socialite laravel/sanctum laravel/octane livewire/livewire laravel/mcp dedoc/scramble
composer require --dev --no-interaction --ignore-platform-reqs pestphp/pest pestphp/pest-plugin-laravel
'@
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
Write-Host "Edit .env (passwords, SSO), then run:"
Write-Host "  docker compose build"
Write-Host "  docker compose run --rm app php artisan key:generate"
Write-Host "  docker compose run --rm app php artisan migrate --force"
Write-Host "  docker compose up -d"
