<?php

declare(strict_types=1);

namespace Victorio\PublicLinks;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Victorio\PublicLinks\Http\Controllers\PublicLinkController;
use Victorio\PublicLinks\Http\Middleware\AddPublicLinkHeaders;
use Victorio\PublicLinks\Http\Middleware\EnsurePublicLinkIsUsable;
use Victorio\PublicLinks\Http\Middleware\ResolvePublicLink;
use Victorio\PublicLinks\Support\HashEqualsTokenComparer;
use Victorio\PublicLinks\Support\PublicLinksConfig;
use Victorio\PublicLinks\Support\TokenComparer;
use Victorio\PublicLinks\Support\TokenFactory;

final class PublicLinksServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-public-links';

    private const string RATE_LIMITER = 'filament-public-links';

    // base64url, 43 characters for the minimum 32 bytes; malformed tokens never reach the database.
    private const string TOKEN_PATTERN = '[A-Za-z0-9_-]{43,}';

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

    public function packageRegistered(): void
    {
        $this->app->singleton(PublicLinkRegistry::class);
        $this->app->bind(TokenComparer::class, HashEqualsTokenComparer::class);
    }

    public function packageBooted(): void
    {
        // Refuse a token length below the rule E1 minimum at boot, not at the first issue.
        TokenFactory::bytes();

        $this->registerRateLimiter();
        $this->registerRoutes();
    }

    /**
     * Per client IP (rule E3). Read at request time, so config changes apply immediately.
     */
    private function registerRateLimiter(): void
    {
        RateLimiter::for(self::RATE_LIMITER, function (Request $request): Limit {
            $limit = PublicLinksConfig::rateLimit('per_ip');

            return new Limit((string) $request->ip(), $limit['max_attempts'], $limit['decay_seconds']);
        });
    }

    /**
     * GET /{prefix}/{token} and POST /{prefix}/{token}/{action}. The headers middleware is
     * outermost so every answer carries it; CSRF comes from the configured `web` group.
     */
    private function registerRoutes(): void
    {
        $route = Route::middleware([
            AddPublicLinkHeaders::class,
            ...PublicLinksConfig::routeMiddleware(),
            'throttle:'.self::RATE_LIMITER,
            ResolvePublicLink::class,
            EnsurePublicLinkIsUsable::class,
        ])->prefix(PublicLinksConfig::routePrefix())->name(PublicLinksConfig::routeName());

        $domain = PublicLinksConfig::routeDomain();

        if ($domain !== null) {
            $route->domain($domain);
        }

        $route->group(function (): void {
            Route::get('{token}', [PublicLinkController::class, 'show'])
                ->where('token', self::TOKEN_PATTERN)
                ->name('show');

            Route::post('{token}/{action}', [PublicLinkController::class, 'action'])
                ->where(['token' => self::TOKEN_PATTERN, 'action' => '[a-z0-9][a-z0-9_-]{0,63}'])
                ->name('action');
        });
    }
}
