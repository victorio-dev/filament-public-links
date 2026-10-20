# Filament Public Links

Expiring, revocable public links to any Eloquent model — your customer opens the quote, no login
needed — with an access log, a branded public page and a Filament UI to manage them.

[![Tests](https://img.shields.io/github/actions/workflow/status/victorio-dev/filament-public-links/tests.yml?branch=main&label=tests)](https://github.com/victorio-dev/filament-public-links/actions/workflows/tests.yml)
[![Latest version](https://img.shields.io/packagist/v/victorio/filament-public-links)](https://packagist.org/packages/victorio/filament-public-links)
[![Downloads](https://img.shields.io/packagist/dt/victorio/filament-public-links)](https://packagist.org/packages/victorio/filament-public-links)
[![License](https://img.shields.io/packagist/l/victorio/filament-public-links)](LICENSE.md)
[![PHP](https://img.shields.io/packagist/dependency-v/victorio/filament-public-links/php)](composer.json)

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/victorio-dev/filament-public-links/main/docs/images/relation-manager-dark.png">
  <img alt="The public links of a record in Filament: status, expiry, uses, opens, with copy, access log and revoke actions" src="https://raw.githubusercontent.com/victorio-dev/filament-public-links/main/docs/images/relation-manager.png">
</picture>

- Hash-only token lookup, expiry, revocation, usage limits — for models with int, ULID or UUID keys
- A relation manager and a standalone action for Filament 4 and 5
- A branded public layout with light and dark mode; 404 and 410 pages included
- An access log with hashed IPs and automatic pruning; events for every step
- Your code decides what a link shows or does: register a handler per model and purpose

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Filament 4 or 5

## Installation

```bash
composer require victorio/filament-public-links
php artisan filament-public-links:install   # publishes config and migrations, offers to migrate
```

Access log rows are pruned by Laravel, so schedule `model:prune` (retention: 90 days by default):

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;
use Victorio\PublicLinks\Models\PublicLinkAccess;

Schedule::command('model:prune', ['--model' => [PublicLinkAccess::class]])->daily();
```

## Quick start

```php
use Illuminate\Database\Eloquent\Model;
use Victorio\PublicLinks\Concerns\HasPublicLinks;
use Victorio\PublicLinks\Facades\PublicLinks;

// 1. The model
class Quote extends Model
{
    use HasPublicLinks;
}

// 2. What the link shows — in a service provider's boot()
PublicLinks::handle(Quote::class, 'view', ShowQuote::class);

// 3. Issue a link and send it
$url = $quote->publicLink('view')->expiresIn(days: 14)->maxUses(5)->issue()->url;
```

A handler renders the page; the package has already checked the token, expiry, revocation and
usage limit, written the access row and loaded the model:

```php
use Symfony\Component\HttpFoundation\Response;
use Victorio\PublicLinks\Data\PublicLinkVisit;
use Victorio\PublicLinks\Handlers\PublicLinkHandlerBase;

final class ShowQuote extends PublicLinkHandlerBase
{
    public function show(PublicLinkVisit $visit): Response
    {
        return response()->view('quotes.public', ['quote' => $visit->linkable, 'link' => $visit->link]);
    }
}
```

In that view, `<x-filament-public-links::layout>` gives you the branded page (slots: `title`,
content, `footer`). Handlers that accept actions (`POST /p/{token}/{action}`, CSRF-protected)
implement `HandlesPublicLinkActions`; a handler decides whether an open or only a successful action
counts as a use.

<table>
  <tr>
    <td width="66%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/victorio-dev/filament-public-links/main/docs/images/public-page-dark.jpg">
        <img alt="A shared quote on the branded public page" src="https://raw.githubusercontent.com/victorio-dev/filament-public-links/main/docs/images/public-page.jpg">
      </picture>
    </td>
    <td width="34%">
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/victorio-dev/filament-public-links/main/docs/images/public-page-mobile-dark.jpg">
        <img alt="The same page on a phone" src="https://raw.githubusercontent.com/victorio-dev/filament-public-links/main/docs/images/public-page-mobile.jpg">
      </picture>
    </td>
  </tr>
</table>

## Filament

Add the relation manager to any resource whose model uses `HasPublicLinks`:

```php
use Victorio\PublicLinks\Filament\RelationManagers\PublicLinksRelationManager;

public static function getRelations(): array
{
    return [PublicLinksRelationManager::class];
}
```

It lists the record's links, creates new ones (purpose, expiry, usage limit) and shows the URL
once, copies an existing link's URL on demand, revokes one or several links, and shows each link's
access log. Prefer a single button? `IssuePublicLinkAction::make()` works as a page action or a
table row action, with the same form and URL modal.

<table>
  <tr>
    <td>
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/victorio-dev/filament-public-links/main/docs/images/new-link-form-dark.png">
        <img alt="The new link form: expiry, usage limit, note" src="https://raw.githubusercontent.com/victorio-dev/filament-public-links/main/docs/images/new-link-form.png">
      </picture>
    </td>
    <td>
      <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/victorio-dev/filament-public-links/main/docs/images/url-modal-dark.png">
        <img alt="The new link's URL with a copy button and its expiry" src="https://raw.githubusercontent.com/victorio-dev/filament-public-links/main/docs/images/url-modal.png">
      </picture>
    </td>
  </tr>
</table>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/victorio-dev/filament-public-links/main/docs/images/access-log-dark.jpg">
  <img alt="The access log of a link in a slide-over" src="https://raw.githubusercontent.com/victorio-dev/filament-public-links/main/docs/images/access-log.jpg">
</picture>

**Permissions** follow the owner model's policy: `view` to see its links and their access log,
`update` to create, copy and revoke them; for a model without a policy, the panel's
`strictAuthorization()` setting decides, as everywhere in Filament. With Filament tenancy, only the
current tenant's links are listed and new links belong to the current tenant.

**The panel plugin is optional** — register it only to change the defaults:

```php
use Victorio\PublicLinks\PublicLinksPlugin;

$panel->plugins([
    PublicLinksPlugin::make()
        ->expiryOptions([1, 7, 30])                 // days offered in the form
        ->defaultExpiry(7)
        ->purposeLabels(['view' => 'View quote'])   // wins over every other purpose label
        ->dateTimeFormat('d/m/Y H:i'),              // every time in the table, log and modal
]);
```

Purposes get readable labels on their own: the label given at registration
(`PublicLinks::handle(Quote::class, 'accept', AcceptQuote::class, 'Accept the quote')`, plain text
or a translation key), else the package's translation for `view`, `accept` and `download`, else the
name itself ("sign-contract" becomes "Sign contract"). Times are shown in Filament's display
timezone. When links change outside the relation manager it refreshes on its own; after changing
links in your own code, dispatch `PublicLinksRelationManager::LINKS_CHANGED` from the Livewire
component.

## Customization

Start at the top of this list and stop at the first step that does the job.

1. **Config** — `config/filament-public-links.php`: expiry defaults and limits, rate limits, route
   prefix and domain, access log retention, response headers, and the brand of the public pages:

   ```php
   'branding' => [
       'name' => 'Acme Ltd',                       // null = config('app.name')
       'logo_url' => 'https://cdn.acme.test/logo.svg',
       'primary_color' => '#1d4ed8',               // #rgb or #rrggbb; text colour is picked for contrast
       'footer_text' => 'Acme Ltd · 1 Main Street',
       'contact_email' => 'links@acme.test',       // offered on the "link no longer available" page
       'show_powered_by' => false,
   ],
   ```

   Admin-side times use the package's translated format (en `M j, Y H:i`, hu `Y. M j. H:i`);
   `PublicLinksPlugin::make()->dateTimeFormat('d/m/Y H:i')` sets your own for all of them.
2. **Translations** — every user-facing string, in English and Hungarian:
   `php artisan vendor:publish --tag="filament-public-links-translations"`.
3. **Swappable classes** — brand per tenant with a resolver bound in `branding.resolver`; it
   receives the link (null on the 404 page) and returns a `Branding` (example below). The models
   are swappable too (`models.link`, `models.access`).
4. **Views — last resort.** `php artisan vendor:publish --tag="filament-public-links-views"`.

   > A published view is a copy: it no longer receives the package's fixes and new features.
   > Re-check it after every upgrade, and prefer the steps above whenever they can do the job.

A per-tenant branding resolver:

```php
use App\Models\Team;
use Victorio\PublicLinks\Contracts\BrandingResolver;
use Victorio\PublicLinks\Data\Branding;
use Victorio\PublicLinks\Models\PublicLink;

final class TenantBranding implements BrandingResolver
{
    public function resolve(?PublicLink $link): Branding
    {
        $team = Team::find($link?->tenant_id);

        return Branding::make(name: $team->name ?? config('app.name'), logoUrl: $team?->logo_url);
    }
}
```

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/victorio-dev/filament-public-links/main/docs/images/gone-410-dark.jpg">
  <img alt="A revoked link: the branded 410 page with the contact address" src="https://raw.githubusercontent.com/victorio-dev/filament-public-links/main/docs/images/gone-410.jpg">
</picture>

## Events

| Event | When |
| --- | --- |
| `PublicLinkCreated` | a link was issued |
| `PublicLinkOpened` | a link was opened (every time) |
| `PublicLinkFirstOpened` | a link was opened for the first time (once per link) |
| `PublicLinkActionPerformed` | a handler action succeeded |
| `PublicLinkRevoked` | a link was revoked (once per link) |
| `PublicLinkExhausted` | the use that reached the usage limit (once per link) |

All live in `Victorio\PublicLinks\Events` and carry the `PublicLink`. There are no built-in
notifications; "tell the sales rep when the customer first opens the quote" is one listener on
`PublicLinkFirstOpened`.

## Security

- **Tokens**: 32 random bytes. The database holds their SHA-256 hash, used for the lookup and
  compared in constant time, and an encrypted copy (your app key) so admins can copy a link again;
  never the plain token. Rotating `APP_KEY` without `previous_keys` keeps links working but makes
  them un-copyable.
- **Responses** send `Referrer-Policy: no-referrer`, `X-Robots-Tag: noindex, nofollow` and
  `Cache-Control: no-store`. URLs contain only the token, never a model id.
- **Access log**: every request is logged with an HMAC of the IP and a shortened user agent, never
  the raw IP; rate-limited requests are not logged. Rows are pruned after 90 days.
- **Rate limits** per IP, with a stricter one for unknown tokens.
- Your web server's access log contains the public URLs — configure its retention accordingly.

Please report vulnerabilities privately, as described in [SECURITY.md](SECURITY.md).

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md). Breaking changes are listed under "Breaking" and only land in a
new major version (or a new minor version before 1.0).

## License

MIT — see [LICENSE.md](LICENSE.md). Part of the Victorio suite of Filament plugins; the paid
packages build on this one.
