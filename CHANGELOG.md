# Changelog

All notable changes to `victorio/filament-public-links` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[semantic versioning](https://semver.org/spec/v2.0.0.html): the public API is everything not
marked `@internal`.

## [Unreleased]

### Breaking

- nothing yet

### Added

- Data layer: `victorio_public_links` and `victorio_public_link_accesses` migrations (table names
  from config), `PublicLink` and `PublicLinkAccess` models (swappable through config).
- `HasPublicLinks` trait for models with int, ULID or UUID keys:
  `publicLink($purpose)->expiresIn()|expiresAt()|permanent()->maxUses()->label()->meta()->issue()`
  returning `IssuedPublicLink` (`url`, `token`, `link`); `publicLinks()`, `hasActivePublicLink()`,
  `revokePublicLinks()`.
- Tokens: 32 random bytes (base64url), SHA-256 lookup hash and an encrypted copy; never stored in
  plain text. A token length below 32 bytes is refused at boot.
- `PublicLink::status()`, `isUsable()`, `revoke()` (idempotent), atomic `recordUse()`, `url()`,
  `active()` / `forPurpose()` scopes.
- Events `PublicLinkCreated`, `PublicLinkRevoked`, `PublicLinkExhausted` (exactly once per link).
- Tenancy: the current Filament tenant is stored with the link.
- Pruning: access rows after `access_log.prune_after_days` (90), ended links after the optional
  `defaults.prune_links_after_days`.
- Handler registry: `PublicLinks::handle(Model::class, 'purpose', Handler::class)`, the
  `PublicLinkHandler` and `HandlesPublicLinkActions` contracts, `PublicLinkHandlerBase`,
  `PublicLinkVisit`. Issuing a link for a model and purpose without a handler throws
  `UnknownPurpose`; a second handler for the same pair throws `DuplicateHandler`.
- Public routes `GET /{prefix}/{token}` and `POST /{prefix}/{token}/{action}` (prefix, domain,
  middleware and route names from config; POST is CSRF-protected by the `web` group).
- Per-IP rate limit and a stricter limit for unknown tokens; 429s are not logged.
- Token resolution by SHA-256 lookup and constant-time comparison, the same steps for unknown
  tokens; 404 for unknown, 410 for expired, revoked, used-up links or a deleted target.
- Access log rows for every answered request: outcome, HMAC of the IP, truncated user agent, the
  link's tenant.
- The handler decides what a use is (`countsOpensAsUses()`, or `recordUse()` in an action).
- Events `PublicLinkOpened`, `PublicLinkFirstOpened` (once per link), `PublicLinkActionPerformed`.
- `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex, nofollow` and `Cache-Control: no-store`
  on every public response.
- Branded public layout `<x-filament-public-links::layout>` with `title`, default and `footer`
  slots: inline CSS, light and dark mode, no external assets, `lang` from the app locale,
  `header`/`main`/`footer` landmarks, brand-colour text picked for WCAG AA contrast.
- Branding config (`name`, `logo_url`, `primary_color`, `footer_text`, `contact_email`,
  `show_powered_by`) and the `BrandingResolver` contract with `ConfigBrandingResolver` as the
  default; a resolver bound in `branding.resolver` receives the link, for per-tenant branding.
  `Branding::make()` cleans every value (colours, logo URLs, e-mail addresses).
- The 404/410 pages use the layout; 410 pages offer `contact_email` when it is set.
- `php artisan filament-public-links:install`; publish tags `filament-public-links-config`,
  `-migrations`, `-translations`, `-views`.

### Changed

- nothing yet

### Fixed

- nothing yet
