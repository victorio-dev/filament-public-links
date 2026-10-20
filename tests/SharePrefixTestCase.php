<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests;

/**
 * Boots the package with a custom route prefix, which only takes effect at boot.
 */
abstract class SharePrefixTestCase extends TestCase
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('filament-public-links.route.prefix', 'share');
    }
}
