#!/bin/sh
# Builds Laravel's config/route/view/event caches from the runtime environment,
# then runs the container command (Octane, queue worker or scheduler).
set -e

if [ "${APP_ENV:-production}" = "production" ] && [ -n "${APP_KEY:-}" ]; then
  php artisan optimize --no-interaction >/dev/null
fi

exec "$@"
