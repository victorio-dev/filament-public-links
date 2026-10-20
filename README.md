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

## Panel registration

```php
use Victorio\PublicLinks\PublicLinksPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel->plugins([
        PublicLinksPlugin::make(),
    ]);
}
```

## Configuration

Every model, table name and swappable class lives in `config/filament-public-links.php`. TODO document
the keys that matter: models, tables, tenancy, public routes, tokens, access log.

## Customization

Start at the top of this list and stop at the first step that does the job.

1. **Config** — `config/filament-public-links.php`. TODO the keys users change most: branding (name,
   logo, colours), behaviour switches, defaults.
2. **Translations** — every user-facing string. English is the base language, Hungarian ships
   alongside it:

   ```bash
   php artisan vendor:publish --tag="filament-public-links-translations"
   ```

3. **Swappable classes** — TODO the models, resolvers or providers bound in config, and the
   contract each replacement implements.
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
