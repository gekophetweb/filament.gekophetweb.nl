# Filament Spool Tracker

Laravel 13 + Livewire 4 app behind SSO for tracking 3D-printer filament spools,
with a REST API (`/api/v1`) for an Android app and an MCP server (`/mcp`).
Runs as Docker containers behind a Cloudflare Tunnel at `filament.gekophetweb.nl`.
See [PLAN.md](PLAN.md) for the design.

In code, `FilamentProduct` is a catalog item and `Spool` is a physical spool you own
(not the Filament admin framework).

## First deploy

1. `cp .env.example .env` and fill in passwords, `SSO_*`, `SSO_ALLOWED_EMAILS`,
   `ALLOWED_IPS_*` and `CLOUDFLARE_TUNNEL_TOKEN`.
2. Build and prepare:
   ```bash
   docker compose build
   docker compose run --rm app php artisan key:generate --show   # paste into APP_KEY
   docker compose run --rm app php artisan migrate --force
   docker compose run --rm app php artisan catalog:import https://raw.githubusercontent.com/Donkie/SpoolmanDB/main/filaments/bambulab.json --format=spoolmandb
   docker compose --profile tunnel up -d
   ```
3. Register `https://filament.gekophetweb.nl/auth/callback` as the redirect URI at your IdP.
4. In Cloudflare Zero Trust, point the tunnel's public hostname `filament.gekophetweb.nl` to `http://app:8000`,
   then remove the `127.0.0.1:8000` port mapping from `docker-compose.yml`.

Each deploy: `docker compose build && docker compose run --rm app php artisan migrate --force && docker compose up -d`.
The config, route, view and event caches are built when the container starts (see below).

## Catalog

`php artisan catalog:import <file|https-url> [--format=csv|spoolmandb]` is idempotent on `article_number`.

- **CSV** columns: `brand,material,line,article_name,article_number,color_hex,color_hex_2,diameter,nominal_weight,has_refill,product_url`.
  Use it for real Bambu Lab SKUs from the store.
- **SpoolmanDB** has no SKUs, so those products get a stable `SDB-...` article number. Its hex values are approximate.

`color_family` is computed from the hex color on import.

## Access control

| Layer | Where |
|---|---|
| SSO + email/domain allow-list | `SSO_ALLOWED_EMAILS`; empty means nobody can sign in |
| IP allow-list per surface | `ALLOWED_IPS_WEB/API/MCP`; empty blocks everything in production |
| Trusted proxy | `TRUSTED_PROXIES` = the Docker `frontend` subnet only |
| Rate limits | `AppServiceProvider` (login 10/min, API read 120, write 30, MCP 60; 20 failed token logins block the IP for 10 minutes) |
| Tokens | Devices page: read-only (`spools:read`) or read-write (`spools:read`, `spools:write`), valid for one year |

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

Tests run in a throwaway PHP container, so no local PHP or Composer is needed:

```bash
./docker/test.sh
```

## Backups

`./docker/backup.sh` writes a gzipped `pg_dump` to `./backups` and keeps 14 days.
Schedule it with host cron, copy the files off-host, and test a restore (the command is in the script).
