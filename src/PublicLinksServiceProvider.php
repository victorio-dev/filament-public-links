<?php

declare(strict_types=1);

namespace Victorio\PublicLinks;

use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Victorio\PublicLinks\Support\TokenFactory;

final class PublicLinksServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-public-links';

    public function configurePackage(Package $package): void
    {
        // Publish tags follow from ->name() (rule D11): filament-public-links-config, -migrations, -views,
        // -translations. Drop a has*() call when the package has nothing of that kind.
        $package
            ->name(self::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            // Every migration explicitly, by its stub filename without the extension. Never edit a
            // migration that is part of a tagged release — add a new one (rule A4).
            ->hasMigrations(['create_public_links_table', 'create_public_link_accesses_table'])
            ->hasInstallCommand(function (InstallCommand $command): void {
                // php artisan filament-public-links:install (rule D11)
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations();
            });
    }

    public function packageBooted(): void
    {
        // Refuse a token length below the rule E1 minimum at boot, not at the first issue.
        TokenFactory::bytes();
    }
}
