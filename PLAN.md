# Filament Spool Tracker: Project Plan

Laravel web app behind SSO that tracks the filament spools you own, starting with the full Bambu Lab catalog. Includes a REST API for an Android app, an MCP server, IP restriction, rate limiting, and a Docker deployment (app and database in separate containers) exposed through a Cloudflare Tunnel.

Hostname: `filament.gekophetweb.nl`

---

## 1. Stack

- **Laravel 11/12** with **Livewire + Tailwind** for the web UI (filtering without a separate SPA)
- **PostgreSQL 17** for data, **Redis 7** for cache, queue, sessions and rate limits
- **Laravel Socialite** for SSO
- **Laravel Sanctum** for API tokens (with abilities)
- **Scribe or Scramble** to generate API docs for the Android side
- **laravel/mcp** for the MCP server
- **Pest** for tests

Naming: "Filament" is also a popular Laravel admin framework. In code, use `FilamentProduct` (catalog item) and `Spool` (physical item you own) to avoid confusion.

## 2. Data model

| Table | Key fields |
|---|---|
| `users` | id, name, email, sso_provider, sso_subject |
| `brands` | id, name (start with Bambu Lab; more brands later) |
| `materials` | id, name (PLA, PETG, ABS, ASA, TPU, PA, PC, PVA...) |
| `filament_products` | brand_id, material_id, line (Basic, Matte, Silk, Sparkle, Metal, Translucent, Galaxy, Marble, Aero, CF/GF...), **article_name**, **article_number/SKU**, **color_hex**, color_hex_2 (dual-color/gradient), color_family, diameter, nominal_weight, has_refill, product_url, is_active |
| `spools` | user_id, filament_product_id, uuid, status (sealed / in use / empty / archived), is_refill, initial_weight, remaining_weight, purchased_at, price, location/AMS slot, notes |
| `spool_events` | spool_id, type (weighed, used, moved, emptied), weight, created_at |

The catalog is global and maintained once; spools belong to a user.

## 3. SSO and access control

- All web and API routes sit behind `auth`. Only login and the SSO callback are public.
- Socialite with your IdP (Google, Microsoft/Entra, Keycloak, Authentik, or generic OIDC).
- **Allow-list** (specific emails, domain, or IdP group claim). Without it, "SSO" means anyone with an account at that provider.
- Create the user from the SSO subject ID, not only the email.
- Optional extra gate: **Cloudflare Access** in front of the website, using the same IdP.

## 4. IP restriction

Two layers, so one misconfiguration does not expose the site.

**Layer 1 (edge):** a Cloudflare WAF custom rule that blocks requests whose source IP is not in the allow-list.

**Layer 2 (Laravel middleware):**
- `EnsureIpAllowed` reads CIDR ranges from config (`ALLOWED_IPS_WEB`, `ALLOWED_IPS_API`, `ALLOWED_IPS_MCP`) and matches with Symfony `IpUtils::checkIp()` (IPv4, IPv6, CIDR).
- **Fail closed:** an empty list in production blocks everything.
- Configure **trusted proxies** to the fixed `frontend` Docker subnet, never `*`.
- Log blocked attempts (IP, path, time) to a separate channel; return a bare 403.
- Keep the allow-list in config/env, not in the database.

**Mobile caveat:** the Android app has no stable IP. Options:
1. WireGuard or Tailscale, and allow only the VPN range (strongest).
2. IP allow-list on the web UI and MCP only; protect the API with tokens, rate limits and a Cloudflare Access service token.
3. Use the app only on home Wi-Fi or VPN.

## 5. Rate limiting

Named limiters in `AppServiceProvider` via `RateLimiter::for()`, backed by Redis.

| Limiter | Key | Suggested limit |
|---|---|---|
| `login` / SSO redirect | IP | 10/min |
| `sso-callback` | IP | 20/min |
| `token-exchange` | IP | 10/min |
| `api-read` | token (fallback IP) | 120/min |
| `api-write` (POST/PATCH/DELETE, `weigh`) | token | 30/min |
| `mcp` | token or OAuth client | 60/min total, tools tighter |
| `web` | user id (fallback IP) | 120/min |
| Auth failures | IP | 20/min, then longer block |

- IP-based throttle **before** authentication so invalid-token floods are cut cheaply.
- Return **429 with `Retry-After`** and consistent JSON.
- Debounce the Livewire search box (about 300 ms); cap `per_page` (max 100).
- Sanctum abilities (`spools:read`, `spools:write`) so a leaked read token cannot modify data.
- Add Cloudflare rate-limiting rules (e.g. `/login`, `/api/*`) as an outer layer.

## 6. Web UI

- **Catalog:** grid of cards with a **color swatch** (from `color_hex`; CSS gradient when `color_hex_2` exists), prominent **article name**, material and line badges, SKU, "owned: N".
- **Filters** (Livewire, state in the URL): text search on article name/SKU, material, line, color family, owned / not owned / low stock.
- **Color family:** bucket computed from the hex's HSL values at import time (red, blue, green, black, white, grey, ...) and stored in a column.
- **My spools:** same card style plus remaining-weight bar, status, quick actions (add, update weight, mark empty).
- **Detail page:** weight history chart.
- Auto-pick black or white label text on swatches for contrast.

## 7. Catalog seeding (Bambu Lab)

No official public catalog API exists. In order of preference:
1. Community datasets such as SpoolmanDB (includes Bambu Lab with hex codes); check license and accuracy.
2. Bambu Studio filament profiles and official store pages to cross-check article names and SKUs.
3. Manual CSV import to fill gaps.

Build an **idempotent importer** (`php artisan catalog:import bambulab.csv`) keyed on SKU so it can be re-run for new colors. Hex values from datasets are approximations; verify against the store.

## 8. REST API (for the Android app)

Base: `/api/v1`, JSON, Sanctum bearer tokens.

Auth:
1. **Start simple:** generate a personal access token on a "Devices" page and enter it in the app.
2. **Later:** OIDC authorization code + PKCE (AppAuth) against the IdP, then `/api/v1/auth/exchange` returns a Sanctum token.

Endpoints:
- `GET /catalog` (filters `?material=&line=&color=&q=&owned=1`), `GET /catalog/{id}`
- `GET|POST /spools`, `GET|PATCH|DELETE /spools/{uuid}`
- `POST /spools/{uuid}/weigh`, `GET /spools/{uuid}/events`
- `GET /me`

API Resources, pagination, and `updated_since` for efficient sync. Spool UUIDs allow QR/NFC labels (encode only the UUID, not a full URL). Make the server URL a setting in the Android app.

## 9. MCP server

Use `laravel/mcp` (check current docs for the exact API), registered in `routes/ai.php` as an HTTP server.

**Architecture:** business logic in shared Action/Service classes (`AddSpool`, `RecordWeight`, `SearchCatalog`). REST controllers and MCP tools both call them, so rules and policies exist once.

**Tools:** `search_catalog`, `list_spools`, `get_spool`, `add_spool`, `record_weight`, `mark_spool_empty`, `low_stock_report`.
**Resources:** catalog entry, spool detail.
**Prompts (optional):** "What can I print with the filament I have left?", "Suggest what to reorder".

**Auth and scoping**
- `auth:sanctum` for simple token use, or OAuth via Laravel Passport so MCP clients connect through a proper login flow (authorize step redirects through SSO).
- Tools resolve data through the **authenticated user**, never through an ID the model supplies. Reuse the API Policies.
- Read-only tokens by default; writes need an explicit ability.
- No hard delete exposed; destructive tools clearly described and annotated.
- IP allow-list and the `mcp` rate limiter apply.
- Audit log of MCP calls (user, tool, arguments, status).
- Treat free text such as spool `notes` as untrusted input (prompt injection); expose no tools that send data elsewhere.

## 10. Docker

| Container | Image | Purpose | Exposed |
|---|---|---|---|
| `app` | custom (FrankenPHP + Laravel Octane) | Website, REST API, MCP endpoint | Internal only (via tunnel) |
| `db` | `postgres:17-alpine` | Database only | Nothing |
| `redis` | `redis:8-alpine` | Rate limits, cache, queue, sessions | Nothing |
| `queue` | same image as `app` | `php artisan queue:work` | Nothing |
| `scheduler` | same image as `app` | `php artisan schedule:work` | Nothing |
| `cloudflared` | `cloudflare/cloudflared` (pinned) | Cloudflare Tunnel | Outbound only |

- One image, three roles (`app`, `queue`, `scheduler`).
- Octane keeps the app in memory: avoid request or user state in statics and singletons (or run FrankenPHP in classic mode).
- Networks: `frontend` (app, cloudflared; fixed subnet) and `backend` (`internal: true`: app, queue, scheduler, db, redis). `cloudflared` must be on `frontend` because it needs outbound access.
- Named volumes: `db_data`, `redis_data` (optional), `app_storage`.
- **Never publish the database port.** Admin access via `docker compose exec db psql ...` or an SSH tunnel.
- Multi-stage Dockerfile: composer stage, node/Vite asset stage, FrankenPHP runtime stage; healthcheck on Laravel's `/up`.
- `.dockerignore` keeps `.env`, `vendor`, `node_modules`, `.git` and logs out of the image.
- Dev: `docker-compose.override.yml` with bind mounts, Vite dev server, Mailpit; or Laravel Sail locally.

Operations:
- Migrations as a one-off deploy step: `docker compose run --rm app php artisan migrate --force`.
- Cache on deploy: `config:cache route:cache view:cache event:cache`.
- Secrets in `.env` kept out of git and the image.
- Nightly `pg_dump` to an off-host location, with a tested restore.
- `LOG_CHANNEL=stderr`, plus separate channels for blocked-IP and MCP audit logs.
- Pin image versions; rebuild regularly.

## 11. Hostname and Cloudflare Tunnel

**Internal names:** Compose service names are DNS names on shared networks (`DB_HOST=db`, `REDIS_HOST=redis`). Aliases can be added without renaming.

**Public hostname:** `filament.gekophetweb.nl` via a Cloudflare Tunnel (token method).
1. Domain DNS managed by Cloudflare; Zero Trust enabled.
2. Create a tunnel, copy the token, add a public hostname `filament.gekophetweb.nl` -> `HTTP` `app:8000`.
3. `cloudflared` service in compose with `TUNNEL_TOKEN` from `.env`; no `ports:` on `app`.
4. `.env`: `APP_URL=https://filament.gekophetweb.nl`, `TRUSTED_PROXIES=<frontend subnet>`.
5. Register `https://filament.gekophetweb.nl/auth/callback` as the redirect URI at the IdP.

**Changing the hostname later** (nothing is baked into containers or the DB): update the tunnel's public hostname, `APP_URL`, add the new SSO redirect URI before removing the old one, then `config:clear` and `config:cache`. Sessions reset once; tokens keep working.

**Android and Access:** use a Cloudflare Access service token (`CF-Access-Client-Id` / `CF-Access-Client-Secret`) or a bypass policy for `/api/*` and `/mcp`; test MCP OAuth against Access early.

**Streaming:** Cloudflare closes idle proxied connections after about 100 s; send keep-alives on long MCP streams.

**Do not bypass the tunnel:** no published ports, host firewall closed for 80/443 if unused.

**Caveats:** Cloudflare terminates TLS and can see traffic; an outage makes the site unreachable, so keep a local or Tailscale route for emergencies.

## 12. Phases

1. **Foundation:** Laravel, Dockerfile and compose (app, db, redis, queue, scheduler), SSO, allow-list, trusted proxies, IP middleware, base rate limiters
2. **Catalog:** migrations, importer, Bambu data, grid, swatches, filters
3. **Spools:** CRUD, weight events, "my spools", Action classes
4. **API:** Sanctum abilities, resources, docs, tests
5. **MCP:** `laravel/mcp` server, tools, scopes, audit log, tests
6. **Deployment hardening:** Cloudflare Tunnel, WAF and Access rules, firewall, backups, health checks, CI that builds and tests the image
7. **Android auth upgrade:** OIDC/PKCE exchange
8. **Extras:** QR/NFC labels, low-stock alerts, usage stats, CSV export, other brands

## 13. Tests

- IP middleware: allowed, blocked, CIDR, IPv6, spoofed `X-Forwarded-For`, empty list.
- Each limiter returns 429 at its threshold and resets.
- API and catalog filters; policies so users only see their own spools.
- MCP: a tool cannot read another user's spool; a read-only token cannot call write tools.
- Docker: image builds and `/up` returns 200 in CI; `db` and `redis` ports unreachable from host and `frontend`.

## 14. Open questions

- Single-user or multi-user? (affects SSO allow-list and spool ownership)
- Which identity provider?
- Android app over VPN (Tailscale/WireGuard) or the open internet?
- Where does this run: home server/NAS or VPS?
