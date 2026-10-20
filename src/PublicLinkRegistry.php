<?php

declare(strict_types=1);

namespace Victorio\PublicLinks;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Victorio\PublicLinks\Contracts\PublicLinkHandler;
use Victorio\PublicLinks\Exceptions\DuplicateHandler;

/**
 * Which handler serves the links of a model and purpose. Registered once per pair, usually in a
 * consuming package's service provider:
 *
 *     PublicLinks::handle(Quote::class, 'view', ShowQuote::class);
 *
 * Keys are morph classes, so a host morph map keeps working. Bound as a singleton.
 */
final class PublicLinkRegistry
{
    /**
     * @var array<string, array<string, class-string<PublicLinkHandler>>>
     */
    private array $handlers = [];

    /**
     * @var array<string, array<string, string>>
     */
    private array $labels = [];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  class-string<Model>  $modelClass
     * @param  class-string<PublicLinkHandler>  $handlerClass
     * @param  string|null  $label  shown in the admin UI; a plain text or a translation key
     */
    public function handle(string $modelClass, string $purpose, string $handlerClass, ?string $label = null): self
    {
        if (! is_subclass_of($handlerClass, PublicLinkHandler::class)) {
            throw new InvalidArgumentException(sprintf(
                '%s must implement %s.',
                $handlerClass,
                PublicLinkHandler::class,
            ));
        }

        $morphClass = self::morphClassOf($modelClass);
        $existing = $this->handlers[$morphClass][$purpose] ?? null;

        if ($existing !== null) {
            throw DuplicateHandler::for($morphClass, $purpose, $existing);
        }

        $this->handlers[$morphClass][$purpose] = $handlerClass;

        if ($label !== null && $label !== '') {
            $this->labels[$morphClass][$purpose] = $label;
        }

        return $this;
    }

    /**
     * @param  class-string<Model>|string  $model  a model class or a morph class (alias)
     */
    public function has(string $model, string $purpose): bool
    {
        return isset($this->handlers[self::morphClassOf($model)][$purpose]);
    }

    /**
     * The handler instance for a stored link, resolved from the container; null when nothing is
     * registered (any more) for that pair.
     */
    public function handlerFor(string $morphClass, string $purpose): ?PublicLinkHandler
    {
        $class = $this->handlers[$morphClass][$purpose] ?? null;

        if ($class === null) {
            return null;
        }

        $handler = $this->container->make($class);

        return $handler instanceof PublicLinkHandler ? $handler : null;
    }

    /**
     * The purposes registered for a model class or morph class, in registration order.
     *
     * @param  class-string<Model>|string  $model
     * @return list<string>
     */
    public function purposesFor(string $model): array
    {
        return array_keys($this->handlers[self::morphClassOf($model)] ?? []);
    }

    /**
     * The label of a purpose: the one given to handle() (translated when it is a translation key),
     * else the package's translation for common purposes (view, accept, download), else a
     * readable form of the name ("sign-contract" becomes "Sign contract"). The plugin's
     * purposeLabels() wins over all of these in the admin UI.
     *
     * @param  class-string<Model>|string  $model
     */
    public function labelFor(string $model, string $purpose): string
    {
        $registered = $this->labels[self::morphClassOf($model)][$purpose] ?? null;

        if ($registered !== null) {
            $translated = __($registered);

            return is_string($translated) ? $translated : $registered;
        }

        $key = 'filament-public-links::filament-public-links.purposes.'.$purpose;

        if (Lang::has($key)) {
            $translated = __($key);

            if (is_string($translated)) {
                return $translated;
            }
        }

        return Str::ucfirst(str_replace(['-', '_', '.'], ' ', $purpose));
    }

    /**
     * A model class becomes its morph class (alias when the host has a morph map); anything else
     * is taken to be a morph class already.
     */
    private static function morphClassOf(string $model): string
    {
        if (is_subclass_of($model, Model::class)) {
            return (new $model)->getMorphClass();
        }

        return $model;
    }
}
