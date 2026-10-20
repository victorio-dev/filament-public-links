Global rules: ../CLAUDE.md

# victorio/filament-public-links

Package-level notes. The global rules are not repeated here — read them first, then this file.
Keep this file current: it is the context for every task in this package. The detailed plan is
[docs/design.md](docs/design.md); status: **step 1 of 5 (data layer) done**; next: registry and HTTP.

## Purpose

Expiring, revocable public links to any Eloquent model, opened without login (a customer opens a
quote). The package owns the link — token, expiry, revocation, usage limit, access log, branded
public layout, Filament UI, events — and nothing behind it: other packages register a handler per
`(model, purpose)`. Free and open source (MIT, public Packagist); the paid packages build on it.
Out of scope: rendering any domain content, notifications (events only), sending links by e-mail.

## Dependencies

- `victorio/support` — shared helpers, as they appear. Nothing else from Victorio, ever: every
  other package depends on this one.

## Models and tables

| Model | Table (config key) | Tenant-owned | Notes |
| --- | --- | --- | --- |
| `PublicLink` | `filament-public-links.tables.links` | yes, when tenancy is on | ULID key; `linkable_id`, `tenant_id` stored as strings |
| `PublicLinkAccess` | `filament-public-links.tables.accesses` | copied from the link | append-only, `MassPrunable` on `accessed_at` |

## Public API

Everything listed here follows semver; everything else in `src/` is `@internal`.

- `Concerns\HasPublicLinks` on any model: `publicLink($purpose)->expiresIn()->maxUses()->issue()`
  returns `Data\IssuedPublicLink` (`url`, `token`, `link`); `publicLinks()`, `revokePublicLinks()`.
- `Models\PublicLink`: `status()`, `isUsable()`, `revoke()`, `recordUse()` (atomic), `url()`.
- `Facades\PublicLinks::handle(Model::class, 'purpose', Handler::class)` in a consumer's `boot()`;
  handlers implement `Contracts\PublicLinkHandler` (+ `HandlesPublicLinkActions`) and receive a
  `Data\PublicLinkVisit` (`link`, `linkable`, `request`, `action`).
- `Filament\RelationManagers\PublicLinksRelationManager`, `Filament\Actions\IssuePublicLinkAction`,
  `PublicLinksPlugin`.
- `<x-filament-public-links::layout>`, `Contracts\BrandingResolver`, `Events\*` (created, opened,
  first opened, action performed, revoked, exhausted), `Exceptions\*`.
- Config keys in `config/filament-public-links.php` (design.md g); translation keys in
  `resources/lang/{en,hu}/filament-public-links.php`; route names `public-links.show|action`.

## Public routes and tokens

- `GET /{prefix}/{token}`, `POST /{prefix}/{token}/{action}`; prefix `p` by default. No model id in
  the URL (E2).
- Token: 32 random bytes, base64url. Stored as SHA-256 hash (lookup, unique index) plus an
  encrypted copy (`Crypt`, app key) for re-copying in Filament; never plain (E1, ADR-0008).
  Constant-time compare; a miss does the same work as a hit (dummy `hash_equals`, access row).
- Unknown token: 404. Expired, revoked, exhausted or deleted target: branded 410.
- Rate limits per IP, plus a stricter one for misses (E3). Headers: `Referrer-Policy: no-referrer`,
  `X-Robots-Tag: noindex`, `Cache-Control: no-store`.
- Access log per request: outcome, HMAC-hashed IP, truncated user agent, time (E4, E5); pruned
  after `prune_after_days` (E6).

## Conventions specific to this package

- Models are swappable through config, so they are **not final** (documented extension point, D3).
- Polymorphic and tenant ids are strings, so int, ULID and UUID keys coexist in one app. Every
  query change needs `make check-db` (F7) — pgsql rejects `varchar = integer`.
- Licence MIT, unlike the paid packages; the README is the Filament directory showcase.

## Commands

Run from the workspace root, in Docker — never on the host (rule I1):

```bash
make check PKG=filament-public-links     # git-check + F1-F3
make check-db PKG=filament-public-links  # tests on sqlite, mysql, pgsql (F7)
make check-lowest PKG=filament-public-links  # lowest stable dependencies on PHP 8.3
```

## Decisions

The open questions of the design are answered in [docs/design.md](docs/design.md), section k:
expiry mandatory (max 365 days, `->permanent()` only with `allow_permanent`), the handler decides
what a use is, 429s are not logged (E4 exception), owner-record policy for authorization, no
standalone resource in 0.1, demo in the playground, optional link pruning (off), first version
0.1.0.

## Open questions

- None at the moment.
