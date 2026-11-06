# Filament Spool Tracker

Laravel 13 + Livewire 4 app behind SSO for tracking 3D-printer filament spools,
with a REST API (`/api/v1`) for an Android app and an MCP server (`/mcp`).
Runs as Docker containers behind a Cloudflare Tunnel at `filament.gekophetweb.nl`.
See [PLAN.md](PLAN.md) for the design.

In code, `FilamentProduct` is a catalog item and `Spool` is a physical spool you own
(not the Filament admin framework).

## First deploy

1. `cp .env.example .env` and set `DB_PASSWORD`, `REDIS_PASSWORD` and `CLOUDFLARE_TUNNEL_TOKEN`
   (see [Cloudflare Tunnel](#cloudflare-tunnel)). Leave SSO, the allow-lists and `APP_KEY` empty: the
   installation sets those itself (see step 3). The containers only read `.env`.
2. Build, start and prepare the database:
   ```bash
   docker compose build
   docker compose --profile tunnel up -d
   docker compose exec app php artisan migrate --force
   docker compose exec app php artisan db:seed --force   # all Bambu Lab filaments
   ```
3. Open the setup wizard. The first start generates `APP_KEY` and prints a one-time link:
   ```bash
   docker compose logs app | grep "Setup URL"
   ```
   The wizard only answers on `http://127.0.0.1:8000` on the Docker host (for a remote server:
   `ssh -L 8000:127.0.0.1:8000 user@server`), never through the tunnel. It walks through the public
   address, the identity provider (Keycloak, Authentik, Authelia, Zitadel, Pocket ID, Entra ID, Google,
   Okta, Auth0, GitLab or any OIDC provider, each with setup instructions, and a discovery check), who may use it (anyone, or only listed accounts and networks), and a real test sign-in. Only then does it save,
   reload the app and close itself for good (`storage/app/setup.lock`).
   Without a browser: `docker compose exec app php artisan sso:setup` (same steps, no test sign-in).
4. In Cloudflare Zero Trust, point the tunnel's public hostname to `http://app:8000`,
   then remove the `127.0.0.1:8000` port mapping from `docker-compose.yml`.

### Where the installation keeps its settings

`APP_KEY` (generated on the first start) and everything from the setup wizard are written to
`storage/app/.env.local` in the `app_storage` volume, not to your `.env`. That file belongs to this
installation only, is loaded before `.env` and wins over it. Earlier versions are kept in
`storage/app/env-backups/`. Include the volume in your backups: without `APP_KEY` encrypted data is lost.

```bash
docker compose exec app cat storage/app/.env.local                              # show
docker compose exec -T app cat storage/app/.env.local > .env.local              # copy out, then edit
docker compose exec -T app sh -c 'cat > storage/app/.env.local' < .env.local    # write back
docker compose restart app queue scheduler
```

Other changes: edit `.env` and run `docker compose up -d --force-recreate`. A key set in both files
keeps the `.env.local` value.

### Cloudflare Tunnel

The tunnel token is not assigned by the app; Docker Compose reads it from `.env` on the host when it
creates the `cloudflared` container:

1. Cloudflare dashboard → Zero Trust → Networks → Tunnels → Create a tunnel → Cloudflared.
2. Copy the token from the install command it shows (the long string after `--token`).
3. Put it in `.env` as `CLOUDFLARE_TUNNEL_TOKEN=...` and run `docker compose --profile tunnel up -d`.
4. Under Public hostnames, point your hostname to `http://app:8000`.

After changing the token, run `docker compose --profile tunnel up -d --force-recreate cloudflared`.
The setup wizard does not touch it, because the containers cannot change what Compose reads.

Each deploy: `docker compose build && docker compose up -d && docker compose exec app php artisan migrate --force`.
The config, route, view and event caches are built when the web container starts.

## Catalog

`php artisan db:seed` (or `--class=BambuLabCatalogSeeder`) loads every Bambu Lab filament from
`database/data/bambulab-filaments.json`: a snapshot of Bambu Studio's `filaments_color_codes.json`, with
Bambu's own article numbers (e.g. `10100` = PLA Basic Jade White), English names, type and hex color.
Re-running it is safe.

`php artisan catalog:import <file|https-url> [--format=csv|bambustudio|spoolmandb]` is idempotent on `article_number`.

- **bambustudio**: pull the latest list straight from upstream:
  `php artisan catalog:import https://raw.githubusercontent.com/bambulab/BambuStudio/master/resources/profiles/BBL/filament/filaments_color_codes.json --format=bambustudio`
- **CSV** columns: `brand,material,line,article_name,article_number,color_hex,color_hex_2,diameter,nominal_weight,has_refill,product_url`.
- **SpoolmanDB** has no SKUs, so those products get a stable `SDB-...` article number. Its hex values are approximate.

`color_family` is computed from the hex color on import.

## Access control

| Layer | Where |
|---|---|
| SSO + email/domain allow-list | `SSO_ALLOWED_EMAILS`; `*` = open sign-up (any verified email), empty = nobody can sign in |
| IP allow-list per surface | `ALLOWED_IPS_WEB/API/MCP`; `*` = any address, empty blocks everything in production |
| Trusted proxy | `TRUSTED_PROXIES` = the Docker `frontend` subnet only |
| Rate limits | Per user, shared by all their tokens: API read 120/min, write 30/min and 1000/day, MCP 60/min; plus 300/min per IP and login 10/min. 20 failed token logins block the IP for 10 minutes. Change them with the `RATE_LIMIT_*` variables (`config/access.php`) |
| Tokens | Devices page: read-only (`spools:read`) or read-write (`spools:read`, `spools:write`), valid for one year, at most 10 active per user (`MAX_TOKENS_PER_USER`) |

With open sign-up, the first sign-in through the identity provider creates the account, and each user
only sees their own spools. With Google, publish the OAuth consent screen (Audience → Publish app);
in "Testing" only its listed test users can sign in.

Blocked IPs are logged to `storage/logs/blocked-ips.log`. MCP calls are written to the
`mcp_audit_logs` table and `storage/logs/mcp-audit.log`.

Configure these in the Cloudflare dashboard (not in this repo): a WAF custom rule with the IP allow-list,
rate-limit rules for `/login` and `/api/*`, and optionally an Access application with a
service token or bypass for `/api/*` and `/mcp`.

## API

Bearer token from the Devices page. `GET /me`, `GET /catalog` (`q, material, brand, line, color, owned=0|1|low, updated_since, per_page<=100`),
`GET /catalog/{id}`, `GET|POST /spools`, `GET|PATCH|DELETE /spools/{uuid}` (soft delete),
`POST /spools/{uuid}/weigh` (`weight` = net grams left), `GET /spools/{uuid}/events`.
`GET /spools?updated_since=` also returns deleted spools (with `deleted_at`) for sync.

## MCP

`POST https://filament.gekophetweb.nl/mcp` with `Authorization: Bearer <token>`.
Tools: `search_catalog`, `list_spools`, `get_spool`, `low_stock_report` (read);
`add_spool`, `record_weight`, `mark_spool_empty` (only shown to write tokens). Resource `spool://{uuid}`,
prompt `suggest_reorder`.

## Development

Run the app locally without the tunnel: `docker compose up -d` and open **http://127.0.0.1:8000**.

| Local address | What |
|---|---|
| `http://127.0.0.1:8000` | The app (redirects to the setup wizard until SSO is set up) |
| `http://127.0.0.1:8000/setup` | Setup wizard; the token link is in `docker compose logs app \| grep "Setup URL"` |
| `http://127.0.0.1:8000/setup/callback` | Redirect URI for the wizard's test sign-in; register it at the identity provider |
| `http://127.0.0.1:8000/up` | Health check |

Tests run in a throwaway PHP container, so no local PHP or Composer is needed:

```bash
./docker/test.sh
```

## Backups

`./docker/backup.sh` writes a gzipped `pg_dump` to `./backups` and keeps 14 days.
Schedule it with host cron, copy the files off-host, and test a restore (the command is in the script).
