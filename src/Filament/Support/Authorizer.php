<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Filament\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Links are managed with the owner record's permissions: `view` to see links and their access log,
 * `update` to create, copy and revoke them. Like Filament itself, a model without a policy is
 * allowed, unless the panel uses strictAuthorization().
 *
 * @internal
 */
final class Authorizer
{
    public const string VIEW = 'view';

    public const string UPDATE = 'update';

    public static function allows(string $ability, ?Model $owner): bool
    {
        if ($owner === null) {
            return false;
        }

        if (Gate::getPolicyFor($owner) === null) {
            return ! Filament::isAuthorizationStrict();
        }

        return Gate::allows($ability, $owner);
    }
}
