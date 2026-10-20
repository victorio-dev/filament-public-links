# filament-public-links — design

Status: **approved** 2026-10-20 (open questions answered in section k). The short version lives
in [../CLAUDE.md](../CLAUDE.md); this file is the detailed plan.

## 0. Purpose and boundaries

Expiring, revocable public links to any Eloquent model, opened without login — a customer opens a
quote, a supplier downloads a document. The package owns the *link*: token, expiry, revocation,
usage limit, access log, a brandable public layout, the Filament UI to manage links, and events.
It does **not** know what is behind a link. Other packages register a **handler** per
`(model, purpose)` pair, and the handler renders the page or performs the action.

Free and open source (MIT, public repo, public Packagist); the paid Victorio packages build on it
([ADR-0007](../../docs/decisions.md)). Depends on `victorio/support` only.

Fixed decisions this design builds on (not open):

- Token: 32 random bytes; lookup by SHA-256 hash, constant-time compare; an encrypted copy
  (`Crypt`, app key) is stored so admins can copy the link again (rule E1, ADR-0008).
- Link primary key: ULID. Access log in its own table, IP HMAC-hashed, user agent truncated,
  `MassPrunable` (E4–E6). The link stores the tenant when Filament tenancy is on (D9).
- Public URL `/{prefix}/{token}`, prefix from config, default `p`; no model id in it (E2).
  Expired or revoked: branded page, HTTP 410. Unknown token: 404, not distinguishable by timing.

## a) Data model

Table names come from config (`filament-public-links.tables.*`); models from
`filament-public-links.models.*` (rule D5). Two migrations, both stubs registered by the service
provider and published with `--tag=filament-public-links-migrations`.

### `victorio_public_links` (`tables.links`)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `ulid` primary | Never appears in a public URL. |
| `linkable_type` | `string(255)` | Morph class (respects the host's morph map). |
| `linkable_id` | `string(36)` | **Always a string** — see the decision below. |
| `purpose` | `string(64)` | e.g. `view`, `accept`; matches a registered handler. |
| `tenant_id` (`tenancy.column`) | `string(36)`, nullable | Set from `Filament::getTenant()` at issue time when tenancy is active. |
| `token_hash` | `char(64)` | `hash('sha256', $token)`, hex. **Unique.** |
| `token_encrypted` | `text` | `Crypt::encryptString($token)`; never logged, never serialised (`$hidden`). |
| `label` | `string(255)`, nullable | Admin-facing note ("Sent to Anna, 2nd round"). |
| `expires_at` | `timestamp`, nullable | Null only for `->permanent()` links, allowed when `defaults.allow_permanent` is true. |
| `max_uses` | `unsignedInteger`, nullable | Null = unlimited. |
| `use_count` | `unsignedInteger`, default 0 | Incremented atomically. |
| `open_count` | `unsignedInteger`, default 0 | Every successful GET. |
| `first_opened_at`, `last_opened_at` | `timestamp`, nullable | |
| `revoked_at` | `timestamp`, nullable | |
| `created_by_type`, `created_by_id` | `string`, nullable | The admin who issued it (string id, same reason). |
| `revoked_by_type`, `revoked_by_id` | `string`, nullable | |
| `meta` | `json`, nullable | Handler-owned data (e.g. recipient). JSON column: F7 applies. |
| `created_at`, `updated_at` | timestamps | |

Indexes: `unique(token_hash)`; `(linkable_type, linkable_id, purpose)` for "links of this
record"; `(tenant_id)`; `(expires_at)` for an optional cleanup.

**Decision — `linkable_id` (and `tenant_id`, `*_by_id`) are always strings.** Alternatives were a
config switch (`int` | `ulid` | `uuid`) or Laravel's `morphUsingUlids()`. Both pick *one* key type
per application, but the point of this package is to sit under several others: in one app
`filament-quotes` may link an `int`-keyed `Quote` and `filament-documents` a ULID-keyed
`Document`. A `string(36)` column holds all three without configuration and without a migration
when a host changes its mind. The cost is real and handled explicitly:

- PostgreSQL refuses `varchar = integer`. The trait's relation is an internal `MorphMany` subclass
  that casts the parent key to string before it is bound, and every lookup the package writes
  binds strings. `make check-db` (rule F7) covers this on pgsql with an int-keyed, a ULID-keyed
  and a UUID-keyed fixture model.
- Int keys sort lexically in this column; nothing in the package orders by `linkable_id`.

Learned in step 1 (`make check-db`):

- MySQL normalises JSON key order, so `meta` comes back with its keys reordered. Nothing may rely
  on the key order of `meta`; tests compare it order-insensitively.
- Testbench's default `testing` sqlite connection has foreign keys off, which hides a missing
  cascade. The package TestCase replaces it with an in-memory connection that enforces them, like
  mysql and pgsql do.

Learned in step 2:

- Route settings (prefix, domain, middleware, names) apply at boot. Tests that need different
  values live in `tests/Config/` with their own TestCase (`SharePrefixTestCase`), not in a runtime
  `config()->set()`.
- A malformed token never matches the route: the framework answers 404 before any lookup, and no
  access row is written — such a request is not an access to any link.
- Laravel skips CSRF checks in unit tests; the 419 test binds a strict subclass of
  `ValidateCsrfToken` under the name the `web` group uses (`PreventRequestForgery` on Laravel 13,
  `ValidateCsrfToken` on 12). Version-specific in test code only.
- Of the "same steps" on hit and miss, only the misses limiter differs: it is hit on a miss only,
  so that brute force runs into it.

### `victorio_public_link_accesses` (`tables.accesses`)

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `id()` | Log rows are never exposed; a sequence is fine. |
| `public_link_id` | `ulid`, nullable, FK → links, `cascadeOnDelete` | Null for unknown-token attempts. |
| `tenant_id` | `string(36)`, nullable | Copied from the link, for tenant-scoped log views. |
| `outcome` | `string(32)` | `opened`, `action`, `expired`, `revoked`, `exhausted`, `not_found`. |
| `action` | `string(64)`, nullable | Handler action name for POSTs. |
| `ip_hash` | `char(64)` | `hash_hmac('sha256', $ip, key)` — never the raw IP (E4, E5). |
| `user_agent` | `string(255)`, nullable | Truncated to `access_log.user_agent_max_length`. |
| `accessed_at` | `timestamp`, **indexed** | The `prunable()` filter column (E6). |

`PublicLinkAccess` uses `MassPrunable`: `where('accessed_at', '<', now()->subDays(config(...prune_after_days)))`.
No `updated_at`; rows are append-only.

## b) Public PHP API

Namespace `Victorio\PublicLinks`. Everything in this section is public API (semver); everything
else in `src/` is `@internal` (rule D4).

### Model trait

```php
use Victorio\PublicLinks\Concerns\HasPublicLinks;

final class Quote extends Model
{
    use HasPublicLinks;
}
```

```php
// Issue: fluent builder, returns an IssuedPublicLink value object.
$issued = $quote->publicLink('view')       // purpose
    ->expiresIn(days: 14)                  // or ->expiresAt($carbon), or ->permanent();
                                           // default ttl_days, never beyond max_ttl_days
    ->maxUses(3)                           // optional
    ->label('Sent to Anna')                // optional
    ->meta(['recipient' => 'anna@example.test'])  // optional, handler-owned
    ->issue();

$issued->url;     // https://app.test/p/q8f…  — show/send it now
$issued->token;   // plain token, available only on this object
$issued->link;    // PublicLink model

// Query
$quote->publicLinks;                              // MorphMany<PublicLink>
$quote->publicLinks()->active()->forPurpose('view')->get();
$quote->hasActivePublicLink('view');              // bool

// Revoke
$quote->revokePublicLinks('view');                // all active links of that purpose; returns count
```

`publicLink()` throws `UnknownPurpose` when no handler is registered for this model + purpose, so
a typo fails at issue time, not when the customer clicks (from step 2, when the registry exists).
`->permanent()` throws `PermanentLinksDisabled` unless `defaults.allow_permanent` is true; an
expiry beyond `max_ttl_days` throws `ExpiryTooFar`.

### `PublicLink` model

```php
$link->isExpired();     // expires_at in the past
$link->isRevoked();
$link->isExhausted();   // max_uses reached
$link->isUsable();      // none of the above
$link->status();        // PublicLinkStatus enum: Active, Expired, Revoked, Exhausted

$link->revoke(by: auth()->user(), reason: null);  // idempotent; fires PublicLinkRevoked once
$link->recordUse();     // atomic; false when the limit was already reached
$link->url();           // decrypts token_encrypted; null if the app key changed (see ADR-0008)
$link->linkable;        // the model
$link->accesses();      // HasMany<PublicLinkAccess>
```

`recordUse()` is one statement, so two concurrent clicks cannot both take the last use:
`UPDATE … SET use_count = use_count + 1 WHERE id = ? AND (max_uses IS NULL OR use_count < max_uses)`;
one affected row means the use counted.

### Handlers

A handler is the code behind a link. A consuming package registers it once, in its service
provider's `boot()`:

```php
use Victorio\PublicLinks\Facades\PublicLinks;

PublicLinks::handle(Quote::class, 'view', ShowQuote::class);
PublicLinks::handle(Quote::class, 'accept', AcceptQuote::class);
```

The registry keys on the model's morph class, so a host morph map keeps working. A second
registration for the same pair throws (`DuplicateHandler`) — two packages must not silently fight
over one link type.

```php
use Victorio\PublicLinks\Contracts\PublicLinkHandler;
use Victorio\PublicLinks\Data\PublicLinkVisit;

final class ShowQuote implements PublicLinkHandler
{
    public function show(PublicLinkVisit $visit): Response
    {
        /** @var Quote $quote */
        $quote = $visit->linkable;

        return response()->view('filament-quotes::public.show', [
            'quote' => $quote,
            'link' => $visit->link,
        ]);
    }
}
```

`PublicLinkVisit` (final, readonly): `link` (the resolved `PublicLink`), `linkable` (the model,
already loaded), `request`, `action` (null for GET). Handlers that accept actions also implement
`HandlesPublicLinkActions::handleAction(string $action, PublicLinkVisit $visit): Response`; the
handler decides whether an action consumes a use (`$visit->link->recordUse()`).

Usage counting is declared by the handler (`PublicLinkHandler::countsOpensAsUses(): bool`,
default `true` via an abstract base class): a "view" link consumes a use per open, an "accept"
link only when the action succeeds (decided, k2).

### Public classes vs `@internal`

| Public (semver) | `@internal` |
| --- | --- |
| `PublicLinksPlugin`, `PublicLinksServiceProvider` | `Support\TokenFactory`, `Support\TokenHasher`, `Support\IpHasher` |
| `Concerns\HasPublicLinks`, `Builders\PendingPublicLink` | `Http\Controllers\PublicLinkController` |
| `Models\PublicLink`, `Models\PublicLinkAccess` (swappable, so **not final**, D3) | `Http\Middleware\*` |
| `Facades\PublicLinks`, `PublicLinkRegistry` | `Support\LinkResolver`, `Support\AccessRecorder` |
| `Contracts\PublicLinkHandler`, `Contracts\HandlesPublicLinkActions`, `Contracts\BrandingResolver` | `Relations\StringKeyedMorphMany` |
| `Data\IssuedPublicLink`, `Data\PublicLinkVisit`, `Data\Branding`, `Enums\PublicLinkStatus` | `Branding\ConfigBrandingResolver` (the default; the contract is public) |
| `Events\*`, `Exceptions\*` | |
| `Filament\RelationManagers\PublicLinksRelationManager`, `Filament\Actions\IssuePublicLinkAction` | |
| Blade component `<x-filament-public-links::layout>`, route name `public-links.show` / `.action`, config and translation keys | |

## c) HTTP layer

Routes (registered by the service provider, prefix/domain/middleware from config):

```
GET  /{prefix}/{token}            public-links.show
POST /{prefix}/{token}/{action}   public-links.action     action: [a-z0-9-]{1,64}
```

`{token}` is constrained to the token alphabet and length (base64url, 43 chars), so garbage never
reaches the database.

Middleware chain, in order:

1. `web` (configurable) — session and CSRF for the POST actions.
2. `throttle:public-links` — named limiter, per IP, `rate_limits.per_ip` (E3).
3. `ResolvePublicLink` — hashes the token, one indexed lookup on `token_hash`, then
   `hash_equals()` against the stored hash. On a miss it runs `hash_equals()` against a fixed
   dummy hash and hits the separate `misses_per_ip` limiter, so a miss does the same work as a
   hit up to this point. Miss → access row (`not_found`, no link id) → branded 404.
4. `EnsurePublicLinkIsUsable` — revoked / expired / exhausted → access row with that outcome →
   branded 410. Tenant is *not* checked here: a public visitor has no tenant; the link carries it.
5. `PublicLinkController` — looks up the handler in the registry by `(linkable_type, purpose)`,
   loads `linkable` (missing model → 410, the record was deleted), records the open
   (`open_count`, `first_opened_at` with a conditional update so "first" is race-free, `use_count`
   if the handler counts opens), writes the `opened` / `action` access row, fires events, calls
   the handler.
6. On every response from this route: `Referrer-Policy: no-referrer` (the token is in the URL and
   must not leak to third-party assets), `X-Robots-Tag: noindex, nofollow`,
   `Cache-Control: no-store, private` (`response_headers`, overridable).

Timing: the token space is 256 bits, so timing is about not *confirming* existence cheaply. Hit
and miss both do: rate-limiter hit, SHA-256, one unique-index lookup, one `hash_equals`, one
access-row insert, one branded view render. The 404 and 410 pages are the same view with different
copy. Tests assert the same steps run on both paths (structural), plus an opt-in timing benchmark
(see h).

## d) Branding

Config `branding.*`: `name` (default `config('app.name')`), `logo_url`, `primary_color`,
`footer_text`, `contact_email` (offered on 410 pages only, as a mailto link) and
`show_powered_by` (default false). They go through a `BrandingResolver` (config
`branding.resolver`, null = `ConfigBrandingResolver`); a SaaS host swaps in its own to brand per
tenant. The resolver receives the `PublicLink`, or null on a 404.

Every value passes through `Branding::make()`, the only way to build one: colours must be
`#rgb`/`#rrggbb` (they go into a `<style>` block, where HTML escaping does not protect), logo URLs
must be http(s) or start with `/`, invalid e-mail addresses are dropped. The text colour on the
brand colour is black or white, whichever contrasts more — at least ~4.58:1 for any colour.

The layout is a Blade class component with inline CSS driven by CSS variables, light and dark
(`prefers-color-scheme`), no Tailwind, no Vite, no JavaScript, nothing the host has to build:

```blade
<x-filament-public-links::layout :link="$visit->link" :page-title="$quote->number">
    <x-slot:title>{{ $quote->number }}</x-slot:title>
    …
    <x-slot:footer>Questions? Call us.</x-slot:footer>
</x-filament-public-links::layout>
```

Slots: `title` (the single `h1`), default (content), `footer`. `lang` comes from the app locale;
`header`/`main`/`footer` landmarks; the logo's alt text is the brand name. The 404/410 pages use
the same layout. Customization order (rule D11): config → translations → resolver → publishing the
views (`filament-public-links-views`), which the README marks as a last resort that stops
receiving updates.

## e) Events

`PublicLinkCreated`, `PublicLinkOpened` (every successful open), `PublicLinkFirstOpened` (once,
race-free), `PublicLinkActionPerformed`, `PublicLinkRevoked`, `PublicLinkExhausted` (the use that
hit the limit). Each carries the `PublicLink`; open events also carry the access row.

**No built-in notifications.** "Notify the sales rep when the customer first opens the quote"
belongs to the consuming package (it knows who the rep is) or to the host — a one-line listener on
`PublicLinkFirstOpened`. The README shows that listener.

## f) Filament

`PublicLinksRelationManager` — generic, `$relationship = 'publicLinks'`, drop it into any resource
whose model uses `HasPublicLinks`:

```php
public static function getRelations(): array
{
    return [PublicLinksRelationManager::class];
}
```

- Table: purpose, label, status badge, expires at, uses (`3 / 5`), opens, last opened, created.
  Filter by status and purpose. Tenant scoping comes from the owner record; no cross-tenant query.
- Header action **New link**: purpose (select of purposes registered for the owner's model),
  expiry, max uses, label. After creating, a modal shows the URL once with a copy button.
- Row action **Copy link**: decrypts on click, server side, and hands the URL to the browser's
  clipboard through Livewire's `js()`. The URL is never rendered into the table HTML (so not
  `copyableState()`), and every copy can be audited.
- Row action **Revoke**: confirmation, then `$link->revoke(by: auth()->user())`.
- Row action **Access log**: slide-over with the link's last 50 access rows (time, outcome, user
  agent; the IP hash is not shown).

`IssuePublicLinkAction::make('view')` — the same "new link + copy" flow as a page or table action,
for resources that do not want the full relation manager.

Authorization: viewing the relation manager follows the owner record's `view` policy; creating and
revoking follow `update` (decided, k4).

`PublicLinksPlugin` options (all optional): `->defaultExpiry(days: 14)`, `->purposeLabels([...])`,
no standalone "all links" resource in 0.1 (decided, k5). The plugin registers nothing by default; the relation manager works
without it.

Version check (rule C1, B4): Filament 5 + Livewire 4 were read in `playground/vendor`
(`RelationManager`, `getTenant()`, `CanBeCopied`, Livewire `js()`). The Filament 4 + Livewire 3
signatures are verified in step 4 before that code is written.

Learned in step 4 (verified against Filament 4.15 / Livewire 3.8 and Filament 5.10 / Livewire 4.4):

- Every API used exists with the same signature in both; nothing depends on a Livewire 3/4
  difference. Copying is plain Alpine in the modal, not Livewire `js()`.
- The relation manager works with `StringKeyedMorphMany` unchanged: Filament builds the table
  query from `$relationship->getQuery()`, which already carries the string-keyed constraint.
- Showing the URL after creating a link re-mounts the same action. Filament only treats a mounted
  action as replaced when its name or context changes, and resets the action object's arguments
  after the call, so the action adds a context marker and reads its "show URL" state from the
  component's mounted action entry. The link key in that entry is only trusted when the link
  belongs to the owner record (the client can mount actions with any arguments).
- Hidden actions cannot be mounted (`isDisabled()` includes `isHidden()`), so authorization is
  enforced by `visible()` and `authorize()` alike; tests also send hand-crafted mount calls.
- In tests, `assertMountedActionModalSee()` reads modal content on both versions (Filament 5
  renders action modals in a Livewire 4 partial).

## g) Config — `config/filament-public-links.php`

```php
return [
    'models' => [
        'link' => \Victorio\PublicLinks\Models\PublicLink::class,
        'access' => \Victorio\PublicLinks\Models\PublicLinkAccess::class,
    ],

    'tables' => [
        'links' => 'victorio_public_links',
        'accesses' => 'victorio_public_link_accesses',
    ],

    // Only takes effect when the current Filament panel has tenancy.
    'tenancy' => [
        'enabled' => true,
        'column' => 'tenant_id',
    ],

    'route' => [
        'prefix' => 'p',
        'domain' => null,
        'middleware' => ['web'],
        'name' => 'public-links.',
    ],

    'rate_limits' => [
        'per_ip' => ['max_attempts' => 60, 'decay_seconds' => 60],
        'misses_per_ip' => ['max_attempts' => 10, 'decay_seconds' => 600],
    ],

    'tokens' => [
        'bytes' => 32, // minimum 32 (rule E1); lower values are rejected at boot
    ],

    'defaults' => [
        'ttl_days' => 30,
        'max_ttl_days' => 365,             // ceiling for expiresIn()/expiresAt()
        'allow_permanent' => false,        // true allows ->permanent() (no expiry)
        'max_uses' => null,
        'prune_links_after_days' => null,  // null = keep expired/revoked links forever
    ],

    'access_log' => [
        'ip_hash_key' => env('PUBLIC_LINKS_IP_HASH_KEY'), // null = derived from app.key
        'user_agent_max_length' => 255,
        'prune_after_days' => 90, // MassPrunable; the host schedules model:prune (E6)
    ],

    'branding' => [
        'resolver' => \Victorio\PublicLinks\Branding\ConfigBrandingResolver::class,
        'name' => null, // null = config('app.name')
        'logo_url' => null,
        'primary_color' => '#1f2937',
        'accent_color' => '#f59e0b',
        'footer_text' => null,
        'support_email' => null,
    ],

    'response_headers' => [
        'Referrer-Policy' => 'no-referrer',
        'X-Robots-Tag' => 'noindex, nofollow',
        'Cache-Control' => 'no-store, private',
    ],
];
```

The hash algorithm is not configurable on purpose: SHA-256 is the rule (E1), and a knob could only
weaken it.

### Install command and publish tags (rule D11)

`php artisan filament-public-links:install` (package-tools `hasInstallCommand()`): publishes the
config and both migrations, then offers to run `migrate`. The README's installation section is
that command plus the `model:prune` schedule.

| Tag | Contents |
| --- | --- |
| `filament-public-links-config` | `config/filament-public-links.php` |
| `filament-public-links-migrations` | `create_public_links_table`, `create_public_link_accesses_table` |
| `filament-public-links-translations` | `lang/vendor/filament-public-links/{en,hu}` |
| `filament-public-links-views` | the public layout and the 404/410 pages — last resort |

Customization follows the D11 order: config (branding, defaults, rate limits, headers) →
translations (all public copy) → swappable classes (models, `BrandingResolver`, handlers) →
published views. The README warns that a published view stops receiving updates.

## h) Test plan

Pest, Testbench, three fixture models (int, ULID, UUID keys). Every data-layer and HTTP test runs
in `make check-db` (rule F7: migrations, queries, a JSON column).

Token and storage
- Token is 43 base64url chars from 32 random bytes; two issues never collide (sampled).
- DB holds the SHA-256 hash and a ciphertext that decrypts to the token; the plain token appears
  in no column, no log line, no event payload, no `toArray()`/JSON (`$hidden`).
- `url()` returns null (not an exception) after an app-key change without `previous_keys`.

Public access — the security cases
- Unknown token → 404, branded, `not_found` access row, no link id; malformed token → 404 without
  a DB query (route constraint).
- Expired / revoked / exhausted → 410, branded, matching outcome row, handler never called.
- Deleted linkable → 410.
- Limit: `max_uses = 1`, two opens → first 200, second 410; concurrent `recordUse()` calls take
  exactly the remaining uses (simulated with two connections on mysql/pgsql).
- First-open event fires once across repeated and concurrent opens.
- Rate limit: request 61 within a minute → 429; 11 misses → misses limiter kicks in, valid tokens
  from another IP unaffected.
- Response headers present on 200, 404, 410.
- Token never in the access row, the Laravel log, or the 404/410 HTML.
- Timing (structural): hit and miss paths both call the hasher, `hash_equals`, the limiter and the
  recorder (spies). Timing (benchmark, `--group=timing`, excluded from `make check`): median
  latency of 404 vs 410 within a tolerance over N requests.

Tenancy (D9)
- Issued under tenant A → `tenant_id` = A; relation manager under tenant B cannot list, copy or
  revoke it (Livewire tests); tenancy off → `tenant_id` null and everything still works.

Registry and API
- Unregistered purpose → `UnknownPurpose` at issue time; duplicate registration → exception;
  morph map alias resolves the handler.
- `revoke()` idempotent, event once; `revokePublicLinks()` touches only that purpose and record.

Filament (both stacks in CI)
- Relation manager renders, creates, shows the URL once, copy action returns the right URL and
  does not render it into the table HTML, revoke works, access log lists rows.

Pruning
- `model:prune` deletes access rows older than `prune_after_days`, keeps newer ones.

## i) README plan (the Filament directory showcase)

1. Name, one-line pitch, badges (Packagist version, tests, MIT), hero screenshot.
2. "Why": share a record with someone who has no login — safely (hash-only lookup, expiry,
   revocation, limits, audit log).
3. Screenshot slots: relation manager list · new-link modal with copy · branded public page ·
   410 page · access log slide-over.
4. Installation: `composer require`, publish config + migrations, `migrate`, **schedule
   `model:prune`** (E6).
5. Five-line usage:

   ```php
   use HasPublicLinks;                                            // on the model
   PublicLinks::handle(Quote::class, 'view', ShowQuote::class);   // in a service provider
   $url = $quote->publicLink('view')->expiresIn(days: 14)->issue()->url;
   // add PublicLinksRelationManager::class to the resource
   // the customer opens $url — no login
   ```

6. Writing a handler (show + action), using the layout component.
7. Filament: relation manager, `IssuePublicLinkAction`, plugin options.
8. Branding: config keys, per-tenant resolver.
9. Events, with the "notify on first open" listener example.
10. Security notes: what is stored, `APP_KEY` rotation and `previous_keys`, `Referrer-Policy`,
    web-server access logs contain URLs (configure them), rate limits.
11. Multi-tenancy, configuration reference, testing, upgrade notes, licence (MIT), "Part of the
    Victorio suite" link.

Playground demo: a small `Project` model and resource in the playground with the relation manager,
a demo handler that renders the project in the branded layout, and one seeded link per status
(active, expired, revoked, exhausted) so every page can be shown and screenshotted. The playground
is built by `docker/playground/setup.sh`, so the demo is a separate dev-environment task (k6).

## j) Implementation order

Each step ends with `make check PKG=filament-public-links` and `make check-db` green, its own
commit, and CHANGELOG entries.

1. **Data layer.** Migrations, `PublicLink` / `PublicLinkAccess` models, token factory + hasher +
   encryption, `HasPublicLinks`, `PendingPublicLink`, `IssuedPublicLink`, revoke / status /
   `recordUse()`, `StringKeyedMorphMany`, tenancy column, `PublicLinkCreated` / `Revoked` events,
   pruning. Tests: storage, keys on three model types, atomic use, prune.
2. **Registry and HTTP.** Facade + registry, handler contracts, `PublicLinkVisit`, routes,
   limiters, middleware chain, controller, access recorder, response headers, open / first-open /
   action / exhausted events. Plain (unbranded) 404/410 views for now. Tests: every security case
   in (h).
3. **Branding.** Layout component, branded 404/410, `BrandingResolver` + config resolver, en/hu
   copy. Tests: rendering, resolver swap, no token in HTML.
4. **Filament.** Verify Filament 4 / Livewire 3 APIs first. Relation manager, `IssuePublicLinkAction`,
   plugin options, authorization, en/hu labels. Tests: Livewire on both stacks, tenant isolation.
5. **Docs and demo.** README (section i), CHANGELOG, screenshots from the playground demo
   (separate dev-environment task for the demo itself), then `make release` with the version you
   give.

## k) Decisions on the open questions (2026-10-20)

1. **Expiry is mandatory** by default, at most `max_ttl_days` (365, configurable). With
   `defaults.allow_permanent = true` (default false) a link can be issued with `->permanent()`.
2. **The handler decides what a use is:** a view link spends one per open, an accept link one per
   successful action.
3. **Throttled (429) requests are not written to the database** — a documented exception to rule
   E4, recorded in the workspace CLAUDE.md.
4. **Authorization follows the owner record's policy:** `view` to see links, `update` to create
   and revoke.
5. **No standalone "all links" resource** in 0.1.
6. **The playground demo** is a separate dev-environment task.
7. **Optional cleanup** of expired and revoked links: `defaults.prune_links_after_days`, default
   null (off).
8. **First version 0.1.0**; the human submits it to Packagist.
