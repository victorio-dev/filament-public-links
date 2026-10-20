<?php

declare(strict_types=1);

namespace Victorio\PublicLinks;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class PublicLinksPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-public-links';
    }

    public function register(Panel $panel): void
    {
        // $panel->resources([...])->pages([...])->widgets([...]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
