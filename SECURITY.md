# Security policy

**English** · [Türkçe](SECURITY.tr.md)

## Supported versions

Security fixes go into the latest release only. Update to the newest image or tag before reporting.

| Version | Supported |
| --- | --- |
| 0.1.x (latest) | ✓ |
| older | ✗ |

## Reporting a vulnerability

**Do not open a public issue.** Report privately through GitHub:

<https://github.com/bmericc/mobile-metrics-mcp/security/advisories/new>

Include the version or image tag, how you run it (Docker image, Compose stack or local), steps to
reproduce and the impact. You will get an answer within a few days. Once a fix is released, the
advisory is published with credit unless you ask otherwise.

## Scope

In scope: the Laravel application in `www/`, the MCP endpoint and the published Docker images, for example:

- getting past sign-in or OAuth, or using an MCP token to read another user's workspace
- reading stored store credentials (service account JSON, `.p8` keys, client secrets)
- secrets leaking into logs, panels or MCP tool output
- CSRF, XSS or session issues in the panels

Out of scope: vulnerabilities in Google Play, App Store Connect or AppGallery Connect themselves;
setups without HTTPS; actions a workspace member is meant to be able to do.

## Running it safely

- Serve it over HTTPS only.
- Keep `.env`, `storage/oauth-*.key` and the database private, and back them up together.
  Never change `APP_KEY` after the first start: stored store credentials are encrypted with it.
- Give store accounts read-only roles where the store allows it.
- Update to new releases; Dependabot alerts are enabled for this repository.
