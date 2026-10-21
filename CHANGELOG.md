# Changelog

All notable changes to `victorio/filament-public-links` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[semantic versioning](https://semver.org/spec/v2.0.0.html): the public API is everything not
marked `@internal`.

## [Unreleased]

Links speak your customer's language, and other packages can build on them.

### Added

- **Eight languages.** German, Spanish, French, Italian, Dutch and Brazilian Portuguese join
  English and Hungarian. English and Hungarian are checked by hand, the other six are
  machine-assisted — corrections are welcome.
- **A link remembers its language.** `->locale('de')` when issuing a link, or the new language
  field of the "New public link" form, which proposes the record's own language (return it from
  `publicLinkLocale()` on your model). Without one, the visitor's browser language decides among
  the supported ones, then your app's; switch the browser off with `locales.accept_language`.
  The 404 and 410 pages follow the same rule, and right-to-left languages get `dir="rtl"`.
- **Build on the "New public link" action.** `IssuePublicLinkAction` can issue a link to another
  model than the record (`->issueUsing()`, `->linkOwnedUsing()`), show only some form fields
  (`->fields([...])`) and use your own headings (`->modalHeading()`, `->urlModalHeading()`).
- **Copy link and Access log** (`CopyPublicLinkAction`, `ViewAccessLogAction`) are public API, for
  your own tables of links.
- **Match your records with the access log.** `IpHasher::forRequest($request)` gives the same
  IP hash the access log stores, and a handler receives the request's access row as
  `$visit->access`.

### Changed

- For a POST action, the access row is now written before your handler runs, so it exists even
  when the handler throws.
- Requires `victorio/support` `^0.3`.

### Upgrading

1. `composer update victorio/filament-public-links victorio/support`
2. Publish the new migration (it adds a nullable `locale` column to the links table) and run it:
   `php artisan vendor:publish --tag="filament-public-links-migrations"`, then
   `php artisan migrate`.
3. If you published the translations, publish them again or add the new keys (the form's
   language field); the six new languages need no action.

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
