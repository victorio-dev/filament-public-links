<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Facades;

use Illuminate\Support\Facades\Facade;
use Victorio\PublicLinks\PublicLinkRegistry;

/**
 * @method static PublicLinkRegistry handle(string $modelClass, string $purpose, string $handlerClass)
 * @method static bool has(string $model, string $purpose)
 * @method static \Victorio\PublicLinks\Contracts\PublicLinkHandler|null handlerFor(string $morphClass, string $purpose)
 * @method static list<string> purposesFor(string $model)
 *
 * @see PublicLinkRegistry
 */
final class PublicLinks extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PublicLinkRegistry::class;
    }
}
