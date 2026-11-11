# Filament Spool Tracker

Laravel 13 + Livewire 4 app behind SSO for tracking 3D-printer filament spools,
with a REST API (`/api/v1`) for an Android app and an MCP server (`/mcp`).
Runs as Docker containers behind a Cloudflare Tunnel at `filament.gekophetweb.nl`.
See [PLAN.md](PLAN.md) for the design.

In code, `FilamentProduct` is a catalog item and `Spool` is a physical spool you own
(not the Filament admin framework).

## Install on a Debian server

The app runs from [docker-compose.yml](docker-compose.yml): the web app (Octane), a queue worker, the
scheduler, PostgreSQL, Redis and `cloudflared`, which makes it reachable at
`https://filament.gekophetweb.nl` without opening any ports. `gekophetweb.nl` must already be a domain
on your Cloudflare account.

1. Install Docker and git (skip if present):
   ```bash
   sudo apt update && sudo apt install -y ca-certificates curl git
   curl -fsSL https://get.docker.com | sudo sh
   sudo usermod -aG docker $USER && newgrp docker
   ```
2. Get the code:
   ```bash
   sudo mkdir -p /opt/filament && sudo chown $USER: /opt/filament
   git clone https://github.com/gekophetweb/filament.gekophetweb.nl.git /opt/filament
   cd /opt/filament
   ```
   Never put a `docker-compose.override.yml` on the server: that file is for local development only
   (see [Development](#development)).
3. Create the tunnel in Cloudflare:
   1. Cloudflare dashboard → Zero Trust → Networks → Tunnels → Create a tunnel → Cloudflared
      (name it e.g. `filament`).
   2. Copy the token from the install command it shows (the long string after `--token`). Do not
      install cloudflared on the host; the `cloudflared` container runs it.
   3. Add a public hostname (also called a published application route): subdomain `filament`,
      domain `gekophetweb.nl`, and as service URL **`http://app:8000`** (in forms with a separate
      type field: type **HTTP**, URL `app:8000`). Cloudflare creates the DNS record itself.

      `app` is the web service in `docker-compose.yml`, which the `cloudflared` container reaches by
      name over the `frontend` network, and `8000` is the port inside that container, so this keeps
      working after step 7. Do not use `localhost` or `127.0.0.1` (inside the `cloudflared` container
      that is the container itself) or `https://` (the app serves plain HTTP; Cloudflare does HTTPS).
4. `cp .env.example .env` and set:
   - `DB_PASSWORD` and `REDIS_PASSWORD`: long random strings (`openssl rand -hex 24`)
   - `CLOUDFLARE_TUNNEL_TOKEN`: the token from step 3

   `APP_URL`, `SSO_REDIRECT_URI` and `TRUSTED_PROXIES` are already right for this domain. Leave SSO,
   the allow-lists and `APP_KEY` empty: the installation sets those itself (see step 6). The
   containers only read `.env`; Docker Compose reads the tunnel token from it when it creates the
   `cloudflared` container.
5. Build, start and prepare the database:
   ```bash
   docker compose build
   docker compose --profile tunnel up -d
   docker compose exec app php artisan migrate --force
   docker compose exec app php artisan db:seed --force   # all Bambu Lab filaments
   ```
6. Open the setup wizard. Until it has run, nobody can sign in and the empty allow-lists block every
   address. The first start generates `APP_KEY` and prints a one-time link:
   ```bash
   docker compose logs app | grep "Setup URL"
   ```
   The wizard only answers on `http://127.0.0.1:8000` on the Docker host, never through the tunnel.
   From your own computer, forward the port and open the link there:
   ```bash
   ssh -L 8000:127.0.0.1:8000 user@server
   ```
   If something on your own computer already uses port 8000 (such as a local development copy of this
   app), SSH only warns that it cannot bind the port, and the link opens that local app instead (a
   404 when its setup is already done). Stop it first (`docker compose stop` in that folder) and
   reconnect, or forward another port (`ssh -L 8001:127.0.0.1:8000 user@server`), change `8000` to
   `8001` in the link, and register the callback URI the wizard shows (`http://127.0.0.1:8001/setup/callback`).

   It walks through the public address, the identity provider (Keycloak, Authentik, Authelia, Zitadel,
   Pocket ID, Entra ID, Google, Okta, Auth0, GitLab or any OIDC provider, each with setup instructions,
   and a discovery check), who may use it (anyone, or only listed accounts and networks), a real
   test sign-in, and how the app sends email (see [Mail settings](#mail-settings)). Register both `https://filament.gekophetweb.nl/auth/callback` and
   `http://127.0.0.1:8000/setup/callback` as redirect URIs at the identity provider. Only after the
   test sign-in does it save, reload the app and close itself for good (`storage/app/setup.lock`).
   Without a browser: `docker compose exec app php artisan sso:setup` (same steps, no test sign-in).
7. Remove the `ports:` mapping (`127.0.0.1:8000:8000`) from the `app` service in `docker-compose.yml`
   and run `docker compose --profile tunnel up -d`. The app is now only reachable through the tunnel
   at `https://filament.gekophetweb.nl`.

Check it with `docker compose ps` and `docker compose logs -f cloudflared app`; the tunnel shows as
Healthy in Zero Trust.

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

### Mail settings

The app emails the owner about print requests and sends sign-in codes to requesters without SSO. The
setup wizard's **Mail** step sets how: through an SMTP server, or only to the log (nothing is delivered).
It also sets the **send from** address and name, and optional **DKIM** signing:

- Fill in the DKIM domain (the from address's domain, or a parent of it) and a selector, then
  *Generate a new key* or paste your own RSA private key (PEM).
- Publish the TXT record the page shows at `<selector>._domainkey.<domain>`. Receivers only trust the
  signature once it is live.
- *Send a test email* sends right away with the values in the form, before saving, so you can check the
  `DKIM-Signature` result in the headers.

Leave the DKIM domain empty when your mail provider signs for you. The private key is stored base64
encoded as `MAIL_DKIM_PRIVATE_KEY` in `storage/app/.env.local`.

On an installation that is already set up, the same form is at `/mail-settings`. It opens with a
one-time link, valid for an hour:

```bash
docker compose exec app php artisan setup:mail-url
```

The command prints the link twice with the same token: on the public address (`APP_URL`, through the
tunnel) and on the Docker host (`http://127.0.0.1:8000`). The public link only opens from an address on
the web allow-list (`ALLOWED_IPS_WEB`). The Docker host link works from anywhere that reaches the
published port, for example through an SSH tunnel (`ssh -L 8000:127.0.0.1:8000 <user>@<server>`). You
can also set the `MAIL_*` keys in `storage/app/.env.local` by hand as shown above. Saving reloads the
app and restarts the queue worker.

### Missing settings

An installation set up with an older version can lack settings that were added later. At every start
the container log lists what is missing, with a one-time link to fix it:

```bash
docker compose logs app | grep -A6 "settings are missing"
```

Signed-in users also see a banner until it is fixed. For now this checks mail: it counts as missing
while email only goes to the log (`MAIL_MAILER=log`) or the send-from address is still an example
address (`@example.com`, `.test` and the like). SMTP mail without DKIM is only mentioned as a tip in
the log, without a link.

### Updating

[docker/update.sh](docker/update.sh) updates the server to the latest commit of its branch. It does
nothing when there is nothing new. Otherwise it backs up the database, fast-forwards the code, builds
the images, runs the migrations with the new image while the old containers keep serving, then starts
the new containers and waits until they are healthy.

```bash
/opt/filament/docker/update.sh            # update if GitHub has new commits
/opt/filament/docker/update.sh --force    # rebuild and restart anyway
```

To update automatically, add it to the crontab of the user that runs Docker (`crontab -e`), e.g.
nightly at 04:00:

```
0 4 * * * /opt/filament/docker/update.sh >> $HOME/filament-update.log 2>&1
```

It never runs twice at once and exits non-zero on any failure (with `MAILTO` set, cron mails the
output). Local edits such as the removed port mapping from step 7 are kept; if an update changes the
same file, it stops before changing anything and `git status` shows what to resolve.

By hand, an update is:

```bash
cd /opt/filament && git pull
docker compose --profile tunnel build
docker compose --profile tunnel run --rm --no-deps app php artisan migrate --force
docker compose --profile tunnel up -d
```

The config, route, view and event caches are built when the web container starts.

After changing the tunnel token in `.env`, run
`docker compose --profile tunnel up -d --force-recreate cloudflared`. The setup wizard does not touch
the token, because the containers cannot change what Compose reads.

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

## Share link

On **Share & export** (`/share-export`), *Create share link* makes a print request link (`/share/<40 characters>`).
*New link* replaces it (the old link stops working) and *Stop sharing* turns it off. The link is still behind
`ALLOWED_IPS_WEB`, so set that to `*` (or add the requesters' networks) for people elsewhere to open it. The link does
not list the owner's filament; old `/share/<token>/print-request` links redirect to it.

The link takes print requests once the owner fills in **Who may request prints** on Share & export: one email,
`@domain` or `*` (anyone with that email) per line. Empty means the page only says no requests are taken. Visitors
prove their email in one of two ways:

- **Email code:** they type their name and email and get a 6-digit code by email, valid for 15 minutes and 5 tries.
  Codes only go to emails on the list, but the page says the same either way, so the list cannot be probed. Each IP
  and each email get at most `RATE_LIMIT_PRINT_REQUEST_CODES_PER_HOUR` (default 5) codes an hour. This needs working
  `MAIL_*` settings and the `queue` container.
- **SSO:** the same provider and callback URL as the app, so nothing new has to be registered at the identity provider.

They don't get an account: only their verified email and name are kept in the session, and that email is the
request's contact. The owner, signed in to the app, may always send a request through their own link. Requesters
give their name, an STL file (at most `PRINT_REQUEST_MAX_UPLOAD_MB`, default 50) or a link to the model, and up to 4
colors picked from the owner's in-stock filament. Under the form they see their own requests with the status and the
owner's reply. Requests land in the **Print requests** inbox (`/print-requests`, the nav shows the unread count).
Opening one marks it read; there the owner downloads the STL, marks it printed or declined (or unread again), writes a
reply the requester sees and a private note only they see, or deletes it with its file. Folders show open, unread or
all requests. STL files are kept in
`storage/app/private/print-requests`. Each requester may send `RATE_LIMIT_PRINT_REQUESTS_PER_HOUR` (default 10) requests an hour.

The owner also gets an email for each new request (turn it off on the inbox page). Replying goes to the requester
(their verified sign-in email). Set up real delivery in the [Mail settings](#mail-settings); with the default
`MAIL_MAILER=log` the emails only go to the log. The `queue` container sends them.

## Export and import your spools

On **Share & export** (and with the button on **My spools**), *Export CSV* downloads all your spools (any status, deleted ones left out) with the
columns `id, article_number, filament, material, status, refill, purchased_at, added_at` (older files with `price`, `location` or `notes` columns still import; those columns are ignored).
*Import spools from CSV* reads such a file back, also after editing it in a spreadsheet (comma or
semicolon separated, UTF-8 or Excel's Windows encoding, at most 2 MB / 5000 rows). Only `article_number`
is required. A row with the `id` of one of your spools updates it; every other row adds a spool. Nothing is
deleted, and rows that fail are skipped and listed. `status` takes `boxed`, `on_spool`, `used_up`,
`archived` or their labels. New spools are dated by `added_at`; the history of events itself is not exported.

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

Spools are counted, not weighed: a spool is `boxed`, `on_spool` (on the spool holder) or `used_up`.

**Full reference:** `/docs/api` (readable page) and `/docs/api/openapi.json` (OpenAPI 3.1), both built
from `app/Support/ApiDocumentation.php`. They are public: no sign-in, no IP allow-list, only 60 requests a
minute per IP. If a Cloudflare WAF rule limits access by IP, exclude `/docs/*` from it as well.
`tests/Feature/ApiDocsTest.php` fails when an API route is added without documenting it.

**Connect by QR code.** On the Devices page, *Show QR code* shows a QR holding JSON:
`{"type":"filament-spools-pairing","api":"https://…/api/v1","code":"…","expires_at":"…"}`. The code works
once, for 10 minutes. The app sends `POST /api/v1/pair` with `{"code": "…", "device_name": "optional"}` (no
token needed) and gets `{"data": {"token", "token_type", "abilities", "expires_at", "api", "user"}}`; the
token is valid for a year. A bad, used or expired code answers 401 and counts toward the IP block.

Bearer token from the Devices page. `GET /me`, `GET /catalog` (`q, material, brand, line, color,
owned=1|0|boxed|on_spool|running_out, updated_since, per_page<=100`; each product has `boxed_count`,
`on_spool_count` and `buy_url`), `GET /catalog/{id}`, `GET|POST /spools`, `GET|PATCH /spools/{uuid}`,
`POST /spools/{uuid}/open` (boxed → on spool), `POST /spools/{uuid}/used-up`,
`DELETE /spools/{uuid}` (removed without counting as used; soft delete), `GET /spools/{uuid}/events`.
The status changes and `DELETE` take an optional `note`. `POST /spools` with `amount` (1–50) adds that many
identical spools in one call and returns them as a list in `data`; without it, one spool comes back as before.
Spools have no price, location or notes.
`GET /spools?updated_since=` also returns deleted spools (with `deleted_at`) for sync.

Buy links open a search in the Bambu Lab store set by `BAMBULAB_STORE_URL` (default the EU store).

## MCP

`POST https://filament.gekophetweb.nl/mcp` with `Authorization: Bearer <token>`.
Tools: `search_catalog`, `list_spools`, `get_spool`, `running_out_report` (read);
`add_spool` (optional `amount`, 1–50), `put_spool_on_holder`, `mark_spool_used_up` (only shown to write tokens). Resource `spool://{uuid}`,
prompt `suggest_reorder`.

## Development

Run the app locally without the tunnel: `docker compose up -d` and open **http://127.0.0.1:8000**.

| Local address | What |
|---|---|
| `http://127.0.0.1:8000` | The app (redirects to the setup wizard until SSO is set up) |
| `http://127.0.0.1:8000/setup` | Setup wizard; the token link is in `docker compose logs app \| grep "Setup URL"` |
| `http://127.0.0.1:8000/setup/callback` | Redirect URI for the wizard's test sign-in; register it at the identity provider |
| `http://127.0.0.1:8000/up` | Health check |

By default the containers run the code baked into the image, so changes need
`docker compose up -d --build`. For live code while developing, use the dev override
(local only, git-ignored):

```bash
cp docker-compose.override.example.yml docker-compose.override.yml
docker compose up -d
```

It mounts `app/`, `config/`, `database/`, `public/`, `resources/`, `routes/` and `docker/`
into the containers and runs `php artisan serve` instead of Octane, so edits show on the next
page load. Still run `docker compose exec app php artisan migrate` for new migrations,
`npm run dev` (or `npm run build`) for CSS/JS, and `docker compose up -d --build` after
`composer.json` changes. Delete the override file to go back to the production setup.

Tests run in a throwaway PHP container, so no local PHP or Composer is needed:

```bash
./docker/test.sh
```

## Backups

`./docker/backup.sh` writes a gzipped `pg_dump` to `./backups` and keeps 14 days.
Schedule it with host cron, copy the files off-host, and test a restore (the command is in the script).
Also back up the `filament_app_storage` volume: it holds `APP_KEY` in `storage/app/.env.local`, and
without it encrypted data is lost.
