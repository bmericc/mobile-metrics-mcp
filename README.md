# Mobile Metrics MCP

<p align="center">
  <a href="https://github.com/bmericc/mobile-metrics-mcp/actions/workflows/tests.yml"><img src="https://github.com/bmericc/mobile-metrics-mcp/actions/workflows/tests.yml/badge.svg" alt="tests"></a>
  <a href="https://scorecard.dev/viewer/?uri=github.com/bmericc/mobile-metrics-mcp"><img src="https://api.scorecard.dev/projects/github.com/bmericc/mobile-metrics-mcp/badge" alt="OpenSSF Scorecard"></a>
  <a href="https://github.com/bmericc/mobile-metrics-mcp/actions/workflows/docker.yml"><img src="https://img.shields.io/github/actions/workflow/status/bmericc/mobile-metrics-mcp/docker.yml?label=docker&logo=docker&logoColor=white" alt="Docker image"></a>
  <a href="https://github.com/bmericc/mobile-metrics-mcp/releases/latest"><img src="https://img.shields.io/github/v/release/bmericc/mobile-metrics-mcp" alt="Latest release"></a>
  <a href="https://github.com/bmericc/mobile-metrics-mcp/actions/workflows/tests.yml"><img src="https://img.shields.io/badge/PHP-8.4%20%7C%208.5%20tested-777BB4?logo=php&logoColor=white" alt="PHP 8.4 | 8.5 tested"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13"></a>
  <a href="https://modelcontextprotocol.io"><img src="https://img.shields.io/badge/MCP-server-6E56CF" alt="Model Context Protocol server"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-GPL--3.0-blue" alt="GPL-3.0"></a>
  <a href="https://github.com/sponsors/bmericc"><img src="https://img.shields.io/badge/sponsor-%E2%9D%A4-ea4aaa?logo=githubsponsors" alt="Sponsor"></a>
</p>

**English** · [Türkçe](README.tr.md)

Collects the statistics of apps published on Google Play, the Apple App Store and Huawei AppGallery
(installs, uninstalls, active devices, revenue, crash/ANR, ratings) together with user reviews, and
serves them to AI assistants over MCP. Built with Laravel 13, Filament 5 and laravel/mcp.

## Repository layout

```
mobile-metrics-mcp/
├── www/         ← Laravel application
├── deploy/      ← Docker Compose stack used in production (db, app, worker, scheduler, nginx)
├── docs/        ← installation guide and example Compose stack
└── Dockerfile   ← self-contained production image (FrankenPHP)
```

## How it works

- **Sign-in:** Google, Apple or Huawei ID (`/login`). There are no passwords. A user who signs in with
  the same e-mail through another provider lands in the same account. The first sign-in creates a
  personal workspace.
- **Workspaces (tenants):** A user can belong to several workspaces. Each workspace can hold several
  developer accounts per store (`store_accounts`).
- **Sign-in identity is not store access.** Store data needs its own credentials:
  - Google Play: service account JSON and the report bucket (`pubsite_prod_rev_*`)
  - App Store Connect: Issuer ID, Key ID and the `.p8` key (plus the vendor number for sales reports)
  - AppGallery Connect: API client (client_id / client_secret)
- **Collection:** the `app/Stores/{GooglePlay,AppStore,AppGallery}` adapters implement the
  `StoreCollector` interface. Every day at 06:00 the scheduler queues `SyncStoreAccount` →
  `SyncAppMetrics` (re-fetches the last 7 days) and `SyncAppReviews` for every active account.
  Each run is recorded in `sync_runs`.
- **Data:** `daily_metrics` in long format (`store_app_id, date, country, metric, value`).
  `country = 'ZZ'` is the total without a country breakdown. Metric names are in `App\Stores\Metric`.
  `products` groups the same app across stores.
- **MCP:** `/mcp` (Passport OAuth, `Mcp::oauthRoutes()`). The tools are read-only and, through
  `ScopesToUser`, see only the token owner's workspaces:
  `list-apps`, `get-metrics`, `compare-periods`, `top-countries`, `get-reviews`, `sync-status`.
- **Panels:** `/app/{workspace}` for users, `/admin` for the system (`users.is_admin`).
- **Public pages:** `/` landing page, `/privacy` and `/terms` (TR), `/en/privacy` and `/en/terms` (EN).

## Status

All three store adapters are written and tested against faked HTTP responses; they have not been
tried with real accounts yet.

| Store | Metrics | Reviews | Known gaps |
|---|---|---|---|
| App Store | Analytics Reports (Standard) | customerReviews | No revenue (Sales reports) |
| Google Play | GCS bulk reports + Reporting API (crash/ANR rate) | reviews.list (last 7 days) | No revenue (earnings zip) |
| AppGallery | appDownloadExport (daily total + per-country by day) | Comments API (needs the country list and site) | No revenue; apps are found from a package name list |

Raw report files are kept under `storage/app/private/raw-reports`. When the column names do not
match, the sync stops with a clear error and stores the received column list in `raw_reports.error`.

## Running with Docker

Images are published for every release, for `linux/amd64` and `linux/arm64`:

- GitHub Container Registry: `ghcr.io/bmericc/mobile-metrics-mcp`
- Docker Hub: `bmericc/mobile-metrics-mcp`

The image runs the web app on port 80 (FrankenPHP); the same image runs the queue worker and the
scheduler. **[Installation guide](docs/installation.md)**: the Compose file, sign-in providers,
first sign-in, store accounts and connecting an AI assistant.

## Local development

```bash
cd www
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan passport:keys
php artisan serve
```

To make a user an admin: `php artisan tinker` → `User::where('email', '...')->update(['is_admin' => true])`.

Tests: `php artisan test`

## Production (Docker Compose)

`deploy/` is the stack the hosted instance runs: MariaDB, the app (php-fpm), the queue worker,
the scheduler and nginx, with `www/` bind-mounted into the containers.

```bash
cd deploy
cp .env.example .env      # real DB passwords
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan passport:keys
```

Later deploys: `deploy/deploy.sh`.

## Contributing and security

See [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities privately as described in
[SECURITY.md](SECURITY.md). Changes are listed in [CHANGELOG.md](CHANGELOG.md).

## License

Licensed under the [GNU General Public License v3.0 or later](LICENSE) (GPL-3.0-or-later).
