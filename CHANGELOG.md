# Changelog

All notable changes to this project are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed

- The logo text and the panel name come from `APP_NAME` (default `Mobile Metrics MCP`), so an
  installation can rename itself in `.env`.

## [1.0.2] - 2026-11-18

### Added

- Installation guide (`docs/installation.md`, EN/TR) with an example Compose stack built on the
  published image.

### Changed

- The Docker image runs the database migrations and creates the OAuth keys when the web server
  starts, waiting for the database if needed. Set `AUTO_MIGRATE=false` to run migrations by hand.
- The example Compose stack starts the worker and the scheduler after the app is healthy.
- The logo is now "Mobile Metrics MCP" set in Sora (SIL OFL 1.1, self-hosted) and the landing
  page footer shows the project name
  and version; the favicon is a neutral chart icon.
  Kale Host artwork removed.

## [1.0.1] - 2026-11-18

First public release.

### Added

- Store adapters for Google Play, the App Store and AppGallery that collect daily metrics
  (installs, uninstalls, active devices, crash/ANR, ratings) and reviews.
- Workspaces with several developer accounts per store, and sign-in with Google, Apple or Huawei ID.
- Read-only MCP server at `/mcp` with Passport OAuth: `list-apps`, `get-metrics`,
  `compare-periods`, `top-countries`, `get-reviews`, `sync-status`.
- Filament user and admin panels, landing page, privacy policy and terms (TR and EN).
- Production Docker image (FrankenPHP) published to GitHub Container Registry and Docker Hub.
- CI: tests on PHP 8.4 and 8.5, OpenSSF Scorecard, CodeQL and Dependabot.

### Changed

- Renamed the product from Kalehost Metrics to Mobile Metrics MCP.
- Licensed under GPL-3.0-or-later.

### Security

- Updated `laravel/framework` to 13.43 (fixes the concurrency process output injection advisory
  for versions before 13.40).

[Unreleased]: https://github.com/bmericc/mobile-metrics-mcp/compare/v1.0.2...HEAD
[1.0.2]: https://github.com/bmericc/mobile-metrics-mcp/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/bmericc/mobile-metrics-mcp/releases/tag/v1.0.1
