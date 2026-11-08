#!/bin/sh
# Updates the server to the latest commit of the checked-out branch: database backup,
# fast-forward, image build, migrations, then the new containers. Safe for cron: it does
# nothing (and prints nothing) when that commit is already deployed, retries a failed
# update on the next run, and never runs twice at once. The very first run always deploys.
#
#   ./docker/update.sh            update when the branch on GitHub has new commits
#   ./docker/update.sh --force    rebuild and restart even without new commits
#   ./docker/update.sh --no-backup
#
# Cron (crontab -e as the user that runs Docker), e.g. nightly at 04:00:
#   0 4 * * * /opt/filament/docker/update.sh >> $HOME/filament-update.log 2>&1
#
# Exits non-zero on any failure (cron mails the output when MAILTO is set). The running
# site stays on the old version until migrations have succeeded with the new image.
set -eu

PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
export PATH

cd "$(dirname "$0")/.."

force=
backup=1
for arg in "$@"; do
  case "$arg" in
    --force) force=1 ;;
    --no-backup) backup= ;;
    *) echo "Unknown option: $arg (use --force or --no-backup)" >&2; exit 2 ;;
  esac
done

log() { echo "$(date '+%Y-%m-%d %H:%M:%S') $*"; }
fail() { log "ERROR: $*" >&2; exit 1; }

# One update at a time, also when a slow build overlaps the next cron run.
exec 9>"${LOCK_FILE:-/tmp/filament-update.lock}"
flock -n 9 || { log "Another update is still running; skipping."; exit 0; }

[ -f .env ] || fail ".env is missing in $(pwd)."
[ -f docker-compose.override.yml ] && fail "docker-compose.override.yml is for local development only; remove it from the server."

compose() { docker compose --profile tunnel "$@"; }

git fetch --quiet || fail "git fetch failed."
old=$(git rev-parse HEAD)
new=$(git rev-parse '@{u}') || fail "The branch has no upstream to update from."

# The last commit that was fully deployed, so a failed run is retried next time even
# though the code itself was already fast-forwarded. Inside .git, so never committed.
deployed_file=.git/filament-deployed
deployed=$(cat "$deployed_file" 2>/dev/null || true)

if [ "$deployed" = "$new" ] && [ "$old" = "$new" ] && [ -z "$force" ]; then
  exit 0
fi

log "Updating $(git rev-parse --abbrev-ref HEAD) from $(git rev-parse --short "$old") to $(git rev-parse --short "$new")."
git log --oneline "$old..$new" | sed 's/^/  /'

if [ -n "$backup" ]; then
  log "Backing up the database."
  compose up -d --wait db
  ./docker/backup.sh || fail "Database backup failed; nothing was changed."
fi

# Fast-forward only. Local edits (such as removing the port in docker-compose.yml) are
# kept; when an incoming commit changes the same file, git refuses and nothing changes.
git merge --ff-only --quiet "$new" \
  || fail "Cannot fast-forward to $new (local commits or local edits to files this update changes). See: git status"

log "Building images."
compose pull --quiet --ignore-buildable || fail "Pulling the database, Redis and cloudflared images failed."
compose build --quiet || fail "Building the app image failed. The site still runs the old containers."

# Migrate with the new image while the old containers keep serving.
log "Running migrations."
compose up -d --wait db redis
compose run --rm --no-deps -T app php artisan migrate --force --no-interaction \
  || fail "Migrations failed. The site still runs the old containers; the code is at $new. To go back: git reset --hard $old && docker compose --profile tunnel build"

log "Starting the new containers."
compose up -d --remove-orphans --wait --wait-timeout 300 \
  || fail "The new containers did not become healthy. Check: docker compose --profile tunnel ps; docker compose logs app"

echo "$new" > "$deployed_file"
docker image prune -f >/dev/null
log "Update finished: $(git log -1 --format='%h %s')"
