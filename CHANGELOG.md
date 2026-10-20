# Changelog

All notable changes to `victorio/filament-public-links` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[semantic versioning](https://semver.org/spec/v2.0.0.html): the public API is everything not
marked `@internal`.

## [Unreleased]

### Added

First release.

- **Public links for any Eloquent model.** Add `HasPublicLinks` to a model and issue a link with
  `$model->publicLink('view')->expiresIn(days: 14)->maxUses(5)->issue()`. Works with int, ULID and
  UUID keys, on SQLite, MySQL and PostgreSQL.
- **Expiry, revocation and usage limits.** Links expire by default (at most 365 days, both
  configurable; permanent links only when you allow them), can be revoked at any time, and can be
  limited to a number of uses — counted on open or only on a successful action, as the handler
  decides.
- **Handlers decide what a link shows.** Register a handler per model and purpose with
  `PublicLinks::handle()`, optionally with a label; handlers can also accept CSRF-protected POST
  actions (e.g. "accept the quote").
- **A branded public page.** `<x-filament-public-links::layout>` with your name, logo, colour,
  footer and contact address, light and dark mode, readable on phones; branded 404 and 410 pages.
  Brand per tenant with your own `BrandingResolver`.
- **Filament UI.** A relation manager for any resource (status, expiry, uses, opens; create, copy,
  revoke, bulk revoke, access log) and `IssuePublicLinkAction` for any page or table. Permissions
  follow the owner record's `view` / `update` policy, tenancy is respected, and an optional plugin
  sets expiry options, purpose labels and the date format. English and Hungarian.
- **Security by default.** Tokens are looked up by their SHA-256 hash and compared in constant
  time; an encrypted copy lets admins copy a link again. Per-IP rate limits, `no-referrer`,
  `noindex` and `no-store` headers, no model ids in URLs.
- **Access log.** Every public request is logged with an HMAC of the IP and a shortened user agent,
  and pruned after 90 days via `model:prune`.
- **Events** for created, opened, first opened, action performed, revoked and used-up links.
- `php artisan filament-public-links:install`, publish tags for config, migrations, translations
  and views.
