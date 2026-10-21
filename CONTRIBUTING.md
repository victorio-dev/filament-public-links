# Contributing

Thank you for helping! Bug fixes and small improvements are welcome as pull requests; for anything
larger, please open an issue first so we can agree on the approach.

## Development

The package is developed in the [Victorio workspace](https://github.com/victorio-dev), where every
command runs in Docker through `make` (nothing PHP-related needs to be installed on your machine).
On its own, the package works with plain Composer:

```bash
composer install
composer test           # Pest
composer analyse        # PHPStan level 8, no baseline
composer format         # Pint
```

In the workspace, the same checks are:

```bash
make check PKG=filament-public-links      # tests, PHPStan, Pint
make check-db PKG=filament-public-links   # the suite on SQLite, MySQL and PostgreSQL
```

## Pull requests

- Add tests for new behaviour and for the failure cases.
- Keep Laravel 12 + Filament 4 and Laravel 13 + Filament 5 working; CI runs both.
- Add a line to `CHANGELOG.md` under "Unreleased".
- Every user-facing string needs a translation in all eight languages (see "Translations" below).
- Use [Conventional Commits](https://www.conventionalcommits.org/) for commit messages.

## Translations

English and Hungarian are checked by hand; German, Spanish, French, Italian, Dutch and Brazilian
Portuguese are machine-assisted. A pull request that improves a translation in
`resources/lang/<locale>/filament-public-links.php` is very welcome — keep every key and every
`:placeholder` (the test suite checks both).
