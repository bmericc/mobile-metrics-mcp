# Installation

**English** · [Türkçe](installation.tr.md)

This guide installs Mobile Metrics MCP from the published Docker image. You do not need to build
anything or install PHP.

The stack has four containers, all from the same app image except the database:

| Service | What it does |
|---|---|
| `app` | Web panel, sign-in and the MCP endpoint (port 80 in the container) |
| `worker` | Runs the queued sync jobs that download store reports and reviews |
| `scheduler` | Queues a sync for every active store account every day at 06:00 |
| `db` | MariaDB |

## 1. Requirements

- A server with Docker and Docker Compose.
- A domain name and a reverse proxy with HTTPS (nginx, Caddy, Traefik, Nginx Proxy Manager...).
  Sign-in providers and MCP clients need a public HTTPS address.
- At least one sign-in provider: Google, Apple or Huawei. The app has no passwords.
  Google is needed anyway if you want to connect Google Play accounts.

## 2. Get the files

```bash
mkdir mobile-metrics-mcp && cd mobile-metrics-mcp
```

```bash
curl -fsSLO https://raw.githubusercontent.com/bmericc/mobile-metrics-mcp/main/docs/docker-compose.yml
```

```bash
curl -fsSL -o .env https://raw.githubusercontent.com/bmericc/mobile-metrics-mcp/main/docs/.env.example
```

The image is also on Docker Hub as `bmericc/mobile-metrics-mcp`; change `image:` in
`docker-compose.yml` if you prefer it.

## 3. Fill in `.env`

Generate the application key and paste it into `APP_KEY`:

```bash
docker run --rm ghcr.io/bmericc/mobile-metrics-mcp:1 php artisan key:generate --show
```

Keep this key safe and never change it: the store credentials you add later are encrypted with it.
If it is lost, every store account has to be added again.

Then set:

- `APP_URL`: the public HTTPS address, for example `https://metrics.example.com`.
- `DB_PASSWORD`: a long random password. The database container is created with it on first start.
- The sign-in provider(s), see the next step.

## 4. Set up a sign-in provider

Fill in at least one provider. A provider left empty is not shown on the login page.

**Google** (`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`)

1. In [Google Cloud Console](https://console.cloud.google.com/apis/credentials), create an
   OAuth client of type **Web application**.
2. Add both redirect URIs:
   - `{APP_URL}/auth/google/callback` (sign-in)
   - `{APP_URL}/connect/google_play/callback` (connecting Google Play accounts)
3. To connect Google Play, enable these APIs in the same project: **Google Play Android Developer
   API**, **Google Play Developer Reporting API** and **Cloud Storage**. They use sensitive scopes:
   while the OAuth consent screen is in testing, add your Google accounts as test users.

**Apple** (`APPLE_CLIENT_ID`, `APPLE_TEAM_ID`, `APPLE_KEY_ID`, `APPLE_PRIVATE_KEY`)

1. In the Apple Developer portal create a **Services ID** (this is the client ID) with Sign in with
   Apple enabled, the domain of `APP_URL`, and the return URL `{APP_URL}/auth/apple/callback`.
2. Create a key with Sign in with Apple and download the `.p8` file.
3. Copy the `.p8` file into the storage volume after the first start (step 5), for example
   `docker compose cp AuthKey_XXXX.p8 app:/app/storage/apple-signin.p8`, and set
   `APPLE_PRIVATE_KEY=/app/storage/apple-signin.p8`.

**Huawei** (`HUAWEI_CLIENT_ID`, `HUAWEI_CLIENT_SECRET`)

In AppGallery Connect, create a web app with Account Kit enabled and use its OAuth client ID and
secret. The redirect URL is `{APP_URL}/auth/huawei/callback`.

## 5. Start and initialise

```bash
docker compose up -d --wait
```

`--wait` returns once the database is ready and the app is running. Then create the tables and the
OAuth keys that MCP clients sign tokens with:

```bash
docker compose exec app php artisan migrate --force
```

```bash
docker compose exec app php artisan passport:keys
```

The worker and the scheduler may log "table not found" until the migration has run; they restart by
themselves.

The app now listens on `127.0.0.1:8080`. Point your reverse proxy at it and check that
`{APP_URL}/login` opens over HTTPS.

## 6. First sign-in

1. Open `{APP_URL}/login` and sign in. The first sign-in creates a user and a personal workspace.
2. Make yourself an administrator to see the system panel at `/admin` (users and sync runs):

   ```bash
   docker compose exec app php artisan tinker --execute "App\Models\User::where('email', 'you@example.com')->update(['is_admin' => true]);"
   ```

Every user who signs in gets their own workspace. Store accounts and data are kept per workspace.

## 7. Add store accounts

In the panel open **Store accounts** and add one account per developer account. Each account is
verified when you add it and synced right away; after that it syncs every day. The panel is in
Turkish; the button names are given below with a translation.

| Store | What you need |
|---|---|
| Google Play | Sign in with Google from **Google Play hesabı bağla** (connect Google Play account; needs the Google setup above), then enter the developer account name and its Cloud Storage URI: Play Console → Download reports → Statistics → **Copy Cloud Storage URI**. |
| App Store | **App Store hesabı ekle** (add App Store account). An App Store Connect team API key with the **Sales and Reports** and **Customer Support** roles: Users and Access → Integrations → Team Keys. Upload the `.p8` file and enter the Issuer ID. The vendor number (Payments and Financial Reports) adds download counts. |
| AppGallery | **Huawei hesabı ekle** (add Huawei account). An AppGallery Connect **Service Account** (developer-level, roles Operations and Customer service): Users and permissions → API key → Connect API. Upload the downloaded JSON and list the package names; AppGallery does not list apps. For reviews, also enter the review countries and an API client. |

The panel shows these steps next to each form.

## 8. Connect an AI assistant

The MCP endpoint is:

```
{APP_URL}/mcp
```

Add it to your assistant as a remote MCP server (in Claude: Settings → Connectors → Add custom
connector). The assistant registers itself, opens the sign-in page, and asks you to approve access.
It then sees only your workspaces and can only read:

`list-apps`, `get-metrics`, `compare-periods`, `top-countries`, `get-reviews`, `sync-status`.

## Upgrading

```bash
docker compose pull
```

```bash
docker compose up -d --wait
```

```bash
docker compose exec app php artisan migrate --force
```

The `:1` tag follows the latest 1.x release. Pin a full version such as `:1.0.1` to upgrade only by
hand. Read [CHANGELOG.md](../CHANGELOG.md) before upgrading.

## Backups

Back up these together:

- the `db-data` volume (or a `mariadb-dump` of the database)
- the `storage` volume: OAuth keys, the Apple key and the raw store reports
- the `.env` file, above all `APP_KEY`
