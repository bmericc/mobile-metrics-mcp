# Changelog

All notable changes to this project are listed here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

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

[Unreleased]: https://github.com/bmericc/mobile-metrics-mcp/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/bmericc/mobile-metrics-mcp/releases/tag/v1.0.1
