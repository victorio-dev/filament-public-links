<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Victorio\PublicLinks\PublicLinksServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * The $app parameter stays untyped because the parent declares it untyped;
     * adding a native type here would break the signature (rule D2 exception).
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            // Filament and Livewire service providers are added here as soon as the
            // package renders a panel in tests. Read the list from the installed
            // packages in playground/vendor first — never guess class names (rule B4).
            PublicLinksServiceProvider::class,
        ];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // DB_CONNECTION=mysql|pgsql (`make test DB=...`, CI) keeps Testbench's own connection of
        // that name, whose config file reads DB_HOST, DB_DATABASE and friends. Without it the
        // default stays sqlite, and the suite runs on an in-memory database.
        if ($app['config']->get('database.default') !== 'sqlite') {
            return;
        }

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        // $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
