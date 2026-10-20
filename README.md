# victorio/filament-public-links

Expiring, revocable public links to any Eloquent model, opened without login — with an access log,
a branded public page and a Filament UI. Status: in design, see [docs/design.md](docs/design.md).

- Requires PHP `^8.3`, Laravel `^12.0|^13.0`, Filament `^4.0|^5.0`
- Free and open source (MIT), on public Packagist
- Depends on `victorio/support`

## Installation

```bash
composer require victorio/filament-public-links
php artisan vendor:publish --tag="filament-public-links-config"
php artisan vendor:publish --tag="filament-public-links-migrations"
php artisan migrate
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

## Translations

English is the base language, Hungarian ships alongside it. Override either by publishing them:

```bash
php artisan vendor:publish --tag="filament-public-links-translations"
```

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
