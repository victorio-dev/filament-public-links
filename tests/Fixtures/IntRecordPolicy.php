<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures;

/**
 * "viewer" may look, "stranger" may not even look, everyone else may do everything.
 */
final class IntRecordPolicy
{
    public function view(User $user, IntRecord $record): bool
    {
        return $user->name !== 'stranger';
    }

    public function update(User $user, IntRecord $record): bool
    {
        return ! in_array($user->name, ['viewer', 'stranger'], true);
    }
}
