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
- `php artisan filament-public-links:install`; publish tags `filament-public-links-config`,
  `-migrations`, `-translations`, `-views`.

### Changed

- nothing yet

### Fixed

- nothing yet
