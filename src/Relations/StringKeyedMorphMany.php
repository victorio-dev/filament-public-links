<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Victorio\PublicLinks\Support\ModelKey;

/**
 * A MorphMany whose morph id column is a string, whatever the parent's key type.
 *
 * `linkable_id` is a string so int, ULID and UUID keyed models share one column (design.md a).
 * PostgreSQL rejects `varchar = integer`, so every parent key is bound as a string, and eager
 * loading uses a bound whereIn instead of Laravel's whereIntegerInRaw for int keys.
 *
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends MorphMany<TRelatedModel, TDeclaringModel>
 *
 * @internal
 */
final class StringKeyedMorphMany extends MorphMany
{
    public function getParentKey(): ?string
    {
        $key = parent::getParentKey();

        return $key === null ? null : ModelKey::stringify($key);
    }

    /**
     * The parameters stay untyped because the parent declares them untyped (rule D2 exception).
     *
     * @param  array<int, TDeclaringModel>  $models
     * @param  string|null  $key
     * @return array<int, string>
     */
    protected function getKeys(array $models, $key = null): array
    {
        return array_values(array_map(
            ModelKey::stringify(...),
            array_filter(parent::getKeys($models, $key), static fn (mixed $value): bool => $value !== null),
        ));
    }

    /**
     * @param  string  $key
     */
    protected function whereInMethod(Model $model, $key): string
    {
        return 'whereIn';
    }
}
