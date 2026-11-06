#!/bin/sh
# Prepares the app, then runs the container command (Octane, queue worker or
# scheduler). Settings come from the read-only, hand-edited /app/.env plus
# /app/storage/app/.env.local, which this installation writes itself (APP_KEY
# below, SSO from the setup wizard) and which wins over .env.
set -e

ENV_FILE=/app/.env
LOCAL_FILE=/app/storage/app/.env.local

if [ -d "$ENV_FILE" ]; then
  echo "ERROR: .env is missing on the Docker host, so Docker mounted an empty directory." >&2
  echo "       Next to docker-compose.yml run: rmdir .env; cp .env.example .env; docker compose up -d --force-recreate" >&2
  exit 1
fi

# Last value of KEY in a file, without surrounding quotes. Always succeeds (set -e).
value_in() { [ -f "$2" ] || return 0; sed -n "s/^[[:space:]]*$1[[:space:]]*=[[:space:]]*//p" "$2" | tail -n 1 | sed "s/^[\"']//; s/[\"']\$//"; }

# Effective value: .env.local first, then .env.
env_value() { v=$(value_in "$1" "$LOCAL_FILE"); [ -n "$v" ] && echo "$v" || value_in "$1" "$ENV_FILE"; }

case "$*" in *octane:start*) is_web=1 ;; *) is_web= ;; esac

if [ -n "$is_web" ]; then
  # A fresh install has no APP_KEY yet. Only the web container creates it; queue
  # and scheduler wait for it to be healthy (see docker-compose.yml).
  if [ -z "$(env_value APP_KEY)" ]; then
    if ! touch "$LOCAL_FILE" 2>/dev/null; then
      echo "ERROR: APP_KEY is empty and the app (uid $(id -u)) cannot write $LOCAL_FILE." >&2
      exit 1
    fi
    chmod 600 "$LOCAL_FILE"
    printf "APP_KEY='%s'\n" "$(php artisan key:generate --show --no-interaction)" >> "$LOCAL_FILE"
    echo "Generated APP_KEY in storage/app/.env.local."
  fi

  # Config is cached only here; `php artisan optimize` reads both files at start-up.
  if [ "$(env_value APP_ENV)" = "production" ]; then
    php artisan optimize --no-interaction >/dev/null
  fi

  # Prints the one-time setup link until the setup wizard has been completed.
  php artisan setup:url --no-interaction
fi

exec "$@"
