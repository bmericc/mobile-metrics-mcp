# Contributing

**English** · [Türkçe](CONTRIBUTING.tr.md)

Thanks for helping. Bug reports, fixes and documentation improvements are welcome.

## Reporting bugs

Open an issue: <https://github.com/bmericc/mobile-metrics-mcp/issues>. Include:

- the version or image tag
- the store (Google Play, App Store or AppGallery) and the failing step (sync, reviews, MCP tool)
- what you did, what you expected and what happened, with the relevant log lines
  (`storage/logs/laravel.log` or `docker logs`) and, for sync errors, `raw_reports.error`

Remove credentials, tokens, app IDs and e-mail addresses you do not want to share.
**Security issues go through [SECURITY.md](SECURITY.md), not public issues.**

## Development setup

PHP 8.4 or newer and Composer:

```bash
cd www
```

```bash
composer install
```

```bash
cp .env.example .env && php artisan key:generate && php artisan passport:keys
```

The tests need no store accounts: store APIs are faked with `Http::fake()` and the database is
in-memory SQLite.

## Checks

CI runs the test suite on PHP 8.4 and 8.5 for every push and pull request. Run it before opening a PR:

```bash
php artisan test
```

## Pull requests

- Keep a PR to one change, and add or update tests for it.
- Add a line to the "Unreleased" section of [CHANGELOG.md](CHANGELOG.md) for user-visible changes.
- PRs are squash-merged.

By contributing you agree that your contribution is released under the
[GNU General Public License v3.0 or later](LICENSE) (GPL-3.0-or-later).
