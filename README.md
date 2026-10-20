# victorio/filament-public-links

Expiring, revocable public links to any Eloquent model, opened without login — with an access log,
a branded public page and a Filament UI. Status: in development, see [docs/design.md](docs/design.md).

- Requires PHP `^8.3`, Laravel `^12.0|^13.0`, Filament `^4.0|^5.0`
- Free and open source (MIT), on public Packagist
- Depends on `victorio/support`

## Installation

Add the private repository and your licence credentials to the host app, then:

```bash
composer require victorio/filament-public-links
php artisan filament-public-links:install   # publishes config and migrations, offers to migrate
```

## Filament

Add the relation manager to any resource whose model uses `HasPublicLinks`:

```php
use Victorio\PublicLinks\Filament\RelationManagers\PublicLinksRelationManager;

public static function getRelations(): array
{
    return [PublicLinksRelationManager::class];
}
```

It lists the record's links (purpose, status, expiry, uses, opens), creates new ones (purpose,
expiry, usage limit) and shows the URL once with a copy button, copies an existing link's URL
on demand, revokes one or several links, and shows each link's access log.

Prefer a single button? Put `IssuePublicLinkAction` on a resource page or table instead:

```php
use Victorio\PublicLinks\Filament\Actions\IssuePublicLinkAction;

protected function getHeaderActions(): array
{
    return [IssuePublicLinkAction::make()];
}
```

Permissions follow the owner model's policy: `view` to see its links and their access log,
`update` to create, copy and revoke them; for a model without a policy, the panel's
`strictAuthorization()` setting decides, as everywhere in Filament.

The panel plugin is optional; register it only to change the defaults:

```php
use Victorio\PublicLinks\PublicLinksPlugin;

$panel->plugins([
    PublicLinksPlugin::make()
        ->expiryOptions([1, 7, 30])                 // days offered in the form
        ->defaultExpiry(7)
        ->purposeLabels(['view' => 'View quote']),  // shown instead of the raw purpose
]);
```

## Configuration

Every model, table name and swappable class lives in `config/filament-public-links.php`. TODO document
the keys that matter: models, tables, tenancy, public routes, tokens, access log.

## Customization

Start at the top of this list and stop at the first step that does the job.

1. **Config** — `config/filament-public-links.php`. The public pages take their look from
   `branding`:

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
2. **Translations** — every user-facing string. English is the base language, Hungarian ships
   alongside it:

   ```bash
   php artisan vendor:publish --tag="filament-public-links-translations"
   ```

3. **Swappable classes** — brand per tenant with a resolver bound in
   `branding.resolver`. It receives the link (null on the 404 page) and returns a `Branding`:

   ```php
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

   The models are swappable too (`models.link`, `models.access`).
4. **Views — last resort.**

   ```bash
   php artisan vendor:publish --tag="filament-public-links-views"
   ```

   > A published view is a copy: it no longer receives the package's fixes and new features.
   > Re-check it after every upgrade, and prefer the steps above whenever they can do the job.

## Multi-tenancy

TODO state what happens when the panel uses Filament tenancy, and which models are tenant-owned.

## Scheduling (required)

Access records and log rows are pruned by Laravel, not by the package. Retention comes from
`filament-public-links.access_log.prune_after_days` (90 days by default); nothing is deleted until the
host application schedules `model:prune`:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', [
    '--model' => [\Victorio\PublicLinks\Models\TODO::class],
])->daily();
```

Without this schedule the tables grow forever and personal data is kept past its retention
period.

## Upgrading

See [CHANGELOG.md](CHANGELOG.md). Breaking changes are listed under a `Breaking` heading and only
ever land in a new major version.

## Licence

MIT, see [LICENSE.md](LICENSE.md).
