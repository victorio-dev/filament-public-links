<?php

declare(strict_types=1);

namespace Victorio\PublicLinks\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 */
final class User extends Authenticatable
{
    protected $table = 'fixture_users';

    protected $guarded = [];
}
