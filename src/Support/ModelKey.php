<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Support;

use Illuminate\Database\Eloquent\Model;
use Stringable;
use UnexpectedValueException;

/**
 * Morph and tenant ids are stored as strings, so int, ULID and UUID keys share one column
 * (design.md a). Every id the package writes or binds goes through here.
 *
 * @internal
 */
final class ModelKey
{
    public static function of(Model $model): string
    {
        return self::stringify($model->getKey());
    }

    /**
     * Keys arrive as `mixed` from Eloquent; only scalars and Stringables are valid ids.
     */
    public static function stringify(mixed $key): string
    {
        if (is_scalar($key) || $key instanceof Stringable) {
            return (string) $key;
        }

        throw new UnexpectedValueException('A model key must be a scalar value.');
    }
}
