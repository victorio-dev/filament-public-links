# Changelog

All notable changes to `victorio/filament-public-links` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[semantic versioning](https://semver.org/spec/v2.0.0.html): the public API is everything not
marked `@internal`.

## [Unreleased]

### Added

- `CopyPublicLinkAction` and `ViewAccessLogAction` are public API: use them as row actions in
  your own Filament tables of `PublicLink` records (for example links that belong to a related
  model). Previously `@internal`; their behaviour is unchanged.
- `Support\IpHasher` is public API: `IpHasher::hash($ip)` and `IpHasher::forRequest($request)`
  return the same HMAC the access log stores, so other packages can record a matching IP hash.
  The algorithm and key derivation are now part of the contract.
- `PublicLinkVisit::$access`: handlers receive the access row written for the current request.
- Six more languages: German, Spanish, French, Italian, Dutch and Brazilian Portuguese (machine
  assisted; English and Hungarian are checked by hand). Corrections are welcome.
- `->locale('de')` when issuing a link: its public pages (and the handler) use that language.
  Without it, the visitor's browser language among `locales.supported` decides, then the app
  locale; `locales.accept_language = false` ignores the browser. The 404 page follows the
  browser, then the app. The Filament form offers a language, proposing the record's
  (`publicLinkLocale()` on the model) or the app's.
- Right-to-left languages (`locales.rtl`) get `dir="rtl"` on the public pages.
- `IssuePublicLinkAction` can issue a link to another model than the record, with the same form,
  URL modal and permissions: `->issueUsing()` (gets the form data and a pre-configured
  `PendingPublicLink` factory), `->linkOwnedUsing()` (which links the URL modal may show) and
  `->fields([...])` (which form fields to show).

### Upgrading

- A new migration adds the nullable `locale` column to the links table:
  `php artisan vendor:publish --tag="filament-public-links-migrations"`, then `php artisan migrate`.

### Changed

- Requires `victorio/support` `^0.3`. The internal `Relations\StringKeyedMorphMany` and
  `Support\ModelKey` are gone; the package uses `Victorio\Support\Database\*` instead (they
  were `@internal`, so this is not a breaking change).
- The access row of a POST action is written **before** the handler runs (it was written after).
  It therefore exists even when the handler throws, and the handler gets it as `$visit->access`.

## [0.1.0] - 2026-10-20

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
  revoke, bulk revoke, access log; the admin's note on a link shows under its purpose) and
  `IssuePublicLinkAction` for any page or table. Permissions
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
